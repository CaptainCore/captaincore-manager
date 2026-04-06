<?php

namespace CaptainCore;

class ComponentQueueCLI {

	/**
	 * Generate a prioritized queue of un-audited component hashes across the fleet.
	 *
	 * Unlike scan-queue (site-centric), this command returns unique component
	 * builds that need auditing, deduplicated by content hash. For each hash,
	 * it picks one source site to download from.
	 *
	 * ## OPTIONS
	 *
	 * [--limit=<number>]
	 * : Number of components to return. Default 20.
	 *
	 * [--format=<format>]
	 * : Output format. Accepts table, json. Default table.
	 *
	 * [--all]
	 * : Show all un-audited hashes, not just the top N.
	 *
	 * ## EXAMPLES
	 *
	 *     wp captaincore component-queue
	 *     wp captaincore component-queue --limit=50
	 *     wp captaincore component-queue --format=json
	 *
	 * @when after_wp_load
	 */
	public function __invoke( $args = [], $assoc_args = [] ) {
		$limit    = isset( $assoc_args['limit'] ) ? (int) $assoc_args['limit'] : 20;
		$format   = $assoc_args['format'] ?? 'table';
		$show_all = isset( $assoc_args['all'] );

		$quiet = $format !== 'table';
		$log   = function( $msg ) use ( $quiet ) {
			if ( ! $quiet ) { \WP_CLI::log( $msg ); }
		};

		$log( 'Gathering fleet component inventory...' );
		$sites_data = self::gather_sites();
		$log( sprintf( 'Found %d active production sites.', count( $sites_data ) ) );

		// Build a map of unique hashes → component info + source sites
		$hash_map    = []; // hash → {slug, version, type, sites, source_ssh}
		$no_hash     = []; // slug|version|type → {slug, version, type, sites, source_ssh} (legacy, no hash)

		foreach ( $sites_data as $site ) {
			$ssh = '';
			if ( ! empty( $site->address ) && ! empty( $site->username ) ) {
				$ssh = "{$site->username}@{$site->address}";
				if ( ! empty( $site->port ) && $site->port !== '22' ) {
					$ssh .= " -p {$site->port}";
				}
			}

			$components = self::extract_components( $site );

			foreach ( $components as $comp ) {
				$hash = $comp['hash'] ?? '';

				if ( $hash ) {
					if ( ! isset( $hash_map[ $hash ] ) ) {
						$hash_map[ $hash ] = [
							'hash'       => $hash,
							'slug'       => $comp['slug'],
							'version'    => $comp['version'],
							'type'       => $comp['type'],
							'title'      => $comp['title'] ?? $comp['slug'],
							'sites'      => 0,
							'source_ssh' => $ssh,
						];
					}
					$hash_map[ $hash ]['sites']++;
				} else {
					$key = "{$comp['type']}|{$comp['slug']}|{$comp['version']}";
					if ( ! isset( $no_hash[ $key ] ) ) {
						$no_hash[ $key ] = [
							'hash'       => '',
							'slug'       => $comp['slug'],
							'version'    => $comp['version'],
							'type'       => $comp['type'],
							'title'      => $comp['title'] ?? $comp['slug'],
							'sites'      => 0,
							'source_ssh' => $ssh,
						];
					}
					$no_hash[ $key ]['sites']++;
				}
			}
		}

		$all_components = array_merge( array_values( $hash_map ), array_values( $no_hash ) );
		$log( sprintf( 'Found %d unique component builds (%d with hashes, %d without).', count( $all_components ), count( $hash_map ), count( $no_hash ) ) );

		// Check which hashes/components have been audited
		$log( 'Checking audit coverage...' );
		$unaudited = self::filter_unaudited( $all_components );

		if ( empty( $unaudited ) ) {
			\WP_CLI::success( 'All component builds are audited! 100% coverage.' );
			return;
		}

		// Sort by site count descending (most fleet exposure first)
		usort( $unaudited, function ( $a, $b ) {
			return $b['sites'] - $a['sites'];
		} );

		if ( ! $show_all ) {
			$unaudited = array_slice( $unaudited, 0, $limit );
		}

		$log( '' );
		$log( sprintf( 'Un-audited components: %d (showing %s)', count( $unaudited ), $show_all ? 'all' : "top $limit" ) );

		if ( $format === 'json' ) {
			echo wp_json_encode( $unaudited, JSON_PRETTY_PRINT ) . "\n";
			return;
		}

		// Table output — truncate hash for display
		$table_output = array_map( function( $item ) {
			return [
				'hash'    => $item['hash'] ? substr( $item['hash'], 0, 12 ) . '...' : 'none',
				'slug'    => $item['slug'],
				'version' => $item['version'] ?: '-',
				'type'    => $item['type'],
				'sites'   => $item['sites'],
				'source'  => $item['source_ssh'] ?: 'N/A',
			];
		}, $unaudited );

		\WP_CLI\Utils\format_items( $format, $table_output, [ 'hash', 'slug', 'version', 'type', 'sites', 'source' ] );
	}

	/**
	 * Extract active plugins/themes from a site row, including hashes.
	 */
	public static function extract_components( $site ) {
		$components = [];

		if ( ! empty( $site->plugins ) ) {
			$plugins = json_decode( $site->plugins );
			if ( is_array( $plugins ) ) {
				foreach ( $plugins as $p ) {
					if ( $p->status === 'active' || $p->status === 'must-use' ) {
						$components[] = [
							'slug'    => $p->name,
							'version' => $p->version,
							'type'    => $p->status === 'must-use' ? 'mu-plugin' : 'plugin',
							'hash'    => $p->hash ?? '',
							'title'   => html_entity_decode( $p->title ?? $p->name ),
						];
					}
				}
			}
		}

		if ( ! empty( $site->themes ) ) {
			$themes = json_decode( $site->themes );
			if ( is_array( $themes ) ) {
				foreach ( $themes as $t ) {
					if ( $t->status === 'active' ) {
						$components[] = [
							'slug'    => $t->name,
							'version' => $t->version,
							'type'    => 'theme',
							'hash'    => $t->hash ?? '',
							'title'   => html_entity_decode( $t->title ?? $t->name ),
						];
					}
				}
			}
		}

		// Loose files and mu-plugin metadata from environment details
		if ( ! empty( $site->details ) ) {
			$details = json_decode( $site->details );

			// Note: _mu_plugins directory hash is intentionally excluded from the queue.
			// MU-plugins are tracked via the manifest-based approach (individual slugs),
			// not whole-directory hashes. The hash is still stored for drift detection.

			// Core extra/modified file hashes
			if ( ! empty( $details->core_file_hashes ) ) {
				foreach ( $details->core_file_hashes as $path => $hash ) {
					$components[] = [
						'slug'    => $path,
						'version' => '',
						'type'    => 'file',
						'hash'    => $hash,
						'title'   => "core: $path",
					];
				}
			}

			// Loose wp-content PHP file hashes
			if ( ! empty( $details->loose_file_hashes ) ) {
				foreach ( $details->loose_file_hashes as $path => $hash ) {
					$components[] = [
						'slug'    => $path,
						'version' => '',
						'type'    => 'file',
						'hash'    => $hash,
						'title'   => "wp-content: $path",
					];
				}
			}
		}

		return $components;
	}

	/**
	 * Filter components to only those that haven't been audited.
	 */
	public static function filter_unaudited( array $components ) {
		global $wpdb;

		$components_t = "{$wpdb->prefix}captaincore_sf_components";
		$audits_t     = "{$wpdb->prefix}captaincore_sf_audits";
		$table_exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $audits_t ) );
		if ( empty( $table_exists ) ) {
			return $components; // No audit tables = everything is unaudited
		}

		$unaudited = [];

		foreach ( $components as $comp ) {
			$hash    = $comp['hash'] ?? '';
			$slug    = $comp['slug'] ?? '';
			$version = $comp['version'] ?? '';
			$type    = $comp['type'] ?? '';
			$row     = null;

			// Hash-first lookup
			if ( $hash ) {
				$row = $wpdb->get_row( $wpdb->prepare(
					"SELECT c.id, a.audit_date FROM {$components_t} c JOIN {$audits_t} a ON c.audit_id = a.id WHERE c.content_hash = %s ORDER BY a.audit_date DESC LIMIT 1",
					$hash
				) );
				if ( $row ) {
					continue; // Hash matched = definitively audited, never stale
				}
				// Has hash but no hash match — needs auditing regardless of slug+version matches
				$unaudited[] = $comp;
				continue;
			}

			// No hash — fall back to slug+version+type (legacy components without hashes)
			if ( $type === 'mu-plugin' && ( $version === '' || $version === null ) ) {
				$row = $wpdb->get_row( $wpdb->prepare(
					"SELECT c.id, a.audit_date FROM {$components_t} c JOIN {$audits_t} a ON c.audit_id = a.id WHERE c.slug = %s AND c.component_type = %s ORDER BY a.audit_date DESC LIMIT 1",
					$slug, $type
				) );
			} elseif ( $type ) {
				$row = $wpdb->get_row( $wpdb->prepare(
					"SELECT c.id, a.audit_date FROM {$components_t} c JOIN {$audits_t} a ON c.audit_id = a.id WHERE c.slug = %s AND c.version = %s AND c.component_type = %s ORDER BY a.audit_date DESC LIMIT 1",
					$slug, $version, $type
				) );
			}

			if ( ! $row ) {
				$unaudited[] = $comp;
			} else {
				$days_ago = (int) ( ( time() - strtotime( $row->audit_date ) ) / 86400 );
				if ( $days_ago > 90 ) {
					$unaudited[] = $comp;
				}
			}
		}

		return $unaudited;
	}

	/**
	 * Get all distinct hashes for a given slug+version+type across the fleet,
	 * with source SSH info and site count per hash.
	 */
	public static function get_hashes_for_version( string $slug, string $version, string $type = 'plugin' ): array {
		$sites_data = self::gather_sites();
		$hashes     = []; // hash → { hash, sites, source_ssh, home_directory }

		foreach ( $sites_data as $site ) {
			$ssh = '';
			if ( ! empty( $site->address ) && ! empty( $site->username ) ) {
				$ssh = "{$site->username}@{$site->address}";
				if ( ! empty( $site->port ) && $site->port !== '22' ) {
					$ssh .= " -p {$site->port}";
				}
			}
			$home_directory = $site->home_directory ?? '';

			$components = self::extract_components( $site );

			foreach ( $components as $comp ) {
				$h = $comp['hash'] ?? '';
				if ( ! $h ) {
					continue;
				}
				if ( $comp['slug'] !== $slug || $comp['version'] !== $version || $comp['type'] !== $type ) {
					continue;
				}
				if ( ! isset( $hashes[ $h ] ) ) {
					$hashes[ $h ] = [
						'hash'           => $h,
						'sites'          => 0,
						'source_ssh'     => $ssh,
						'home_directory' => $home_directory,
					];
				}
				$hashes[ $h ]['sites']++;
			}
		}

		// Sort by site count descending
		usort( $hashes, function ( $a, $b ) {
			return $b['sites'] - $a['sites'];
		} );

		return array_values( $hashes );
	}

	/**
	 * Build a version-grouped queue: aggregate by slug+version instead of individual hash.
	 * Each entry includes total sites, number of distinct hashes, and the most common hash with source_ssh.
	 */
	public static function build_version_queue( array $hash_map ): array {
		$version_map = []; // "type|slug|version" → aggregated data

		foreach ( $hash_map as $hash => $comp ) {
			$key = "{$comp['type']}|{$comp['slug']}|{$comp['version']}";
			if ( ! isset( $version_map[ $key ] ) ) {
				$version_map[ $key ] = [
					'slug'           => $comp['slug'],
					'version'        => $comp['version'],
					'type'           => $comp['type'],
					'title'          => $comp['title'] ?? $comp['slug'],
					'sites'          => 0,
					'hashes_count'   => 0,
					'hash'           => $comp['hash'],           // most-common hash (updated below)
					'source_ssh'     => $comp['source_ssh'] ?? '',
					'home_directory' => $comp['home_directory'] ?? '',
					'_max_sites'     => 0,                       // track which hash has the most sites
				];
			}
			$version_map[ $key ]['sites'] += $comp['sites'];
			$version_map[ $key ]['hashes_count']++;

			// Keep the hash with the most sites as the primary
			if ( $comp['sites'] > $version_map[ $key ]['_max_sites'] ) {
				$version_map[ $key ]['_max_sites']     = $comp['sites'];
				$version_map[ $key ]['hash']            = $comp['hash'];
				$version_map[ $key ]['source_ssh']      = $comp['source_ssh'] ?? '';
				$version_map[ $key ]['home_directory']   = $comp['home_directory'] ?? '';
			}
		}

		// Remove internal tracking field
		$result = array_values( $version_map );
		foreach ( $result as &$item ) {
			unset( $item['_max_sites'] );
		}

		return $result;
	}

	/**
	 * Gather all production environments with SSH + plugin/theme data + details.
	 */
	public static function gather_sites() {
		global $wpdb;

		$sites_table = $wpdb->prefix . 'captaincore_sites';
		$env_table   = $wpdb->prefix . 'captaincore_environments';

		return $wpdb->get_results( "
			SELECT s.site_id, s.name, s.provider,
			       e.address, e.username, e.port, e.home_url, e.home_directory,
			       e.plugins, e.themes, e.details
			FROM {$env_table} e
			JOIN {$sites_table} s ON e.site_id = s.site_id
			WHERE s.status = 'active'
			  AND s.provider IS NOT NULL
			  AND e.environment = 'Production'
			  AND e.home_url IS NOT NULL AND e.home_url != ''
			  AND e.plugins IS NOT NULL
			ORDER BY s.name ASC
		" );
	}

}
