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
	 * [--model=<id>]
	 * : Canonical auditor ID (e.g. claude-opus-4-7). When set, hashes audited only by
	 * other models are still returned — supports layered multi-model audits.
	 *
	 * ## EXAMPLES
	 *
	 *     wp captaincore component-queue
	 *     wp captaincore component-queue --limit=50
	 *     wp captaincore component-queue --format=json
	 *     wp captaincore component-queue --model=claude-opus-4-7
	 *
	 * @when after_wp_load
	 */
	public function __invoke( $args = [], $assoc_args = [] ) {
		$limit    = isset( $assoc_args['limit'] ) ? (int) $assoc_args['limit'] : 20;
		$format   = $assoc_args['format'] ?? 'table';
		$show_all = isset( $assoc_args['all'] );
		$model    = isset( $assoc_args['model'] ) ? (string) $assoc_args['model'] : '';

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
		$unaudited = self::filter_unaudited( $all_components, $model );

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
	 * Filter components to only those that haven't been audited (by the given
	 * model, if scoped) and tag each with an audit-state tier the caller can
	 * use for ordering. Reads from the WP Registry plugin's manifest via
	 * RegistryClient (direct REST, app-password auth, transient-cached).
	 *
	 * Returned items are annotated with a `_audit_tier` key:
	 *   0 = NEVER audited by anyone (highest priority — completely fresh)
	 *   1 = audited by another model, NOT by $model (older audit, layered re-audit eligible)
	 *
	 * When $model is empty, every kept item is tier 0 (any-model-unaudited).
	 *
	 * Fail-open: if the registry isn't configured or the fetch fails, every
	 * component is treated as tier-0 unaudited. That's strictly more work but
	 * never silently skips a real vulnerability.
	 */
	public static function filter_unaudited( array $components, string $model = '' ) {
		if ( ! RegistryClient::ready() ) {
			foreach ( $components as &$c ) {
				$c['_audit_tier'] = 0;
			}
			return $components;
		}

		$type_to_endpoint = [
			'plugin'    => 'plugins',
			'theme'     => 'themes',
			'mu-plugin' => 'mu-plugins',
			'file'      => 'files',
		];

		// Per type, pull two manifests:
		//   any[$type]  — every audited hash regardless of auditor
		//   mine[$type] — only hashes audited by $model (empty array if no $model)
		$any  = [];
		$mine = [];
		$needed_types = [];
		foreach ( $components as $c ) {
			$t = $c['type'] ?? '';
			if ( isset( $type_to_endpoint[ $t ] ) ) {
				$needed_types[ $t ] = true;
			}
		}
		foreach ( array_keys( $needed_types ) as $t ) {
			$endpoint = $type_to_endpoint[ $t ];
			$any[ $t ]  = RegistryClient::manifest( $endpoint, '' );
			$mine[ $t ] = $model !== '' ? RegistryClient::manifest( $endpoint, $model ) : [];
		}

		$out = [];
		foreach ( $components as $comp ) {
			$hash = $comp['hash'] ?? '';
			$type = $comp['type'] ?? '';

			$any_manifest  = $any[ $type ]  ?? null;
			$mine_manifest = $mine[ $type ] ?? [];

			if ( ! is_array( $any_manifest ) ) {
				// Type not supported by registry — pass through as fresh.
				$comp['_audit_tier'] = 0;
				$out[] = $comp;
				continue;
			}

			if ( $hash === '' ) {
				// Legacy: no hash. Manifest is hash-keyed so we can't lookup;
				// treat as never-audited (tier 0) so it surfaces.
				$comp['_audit_tier'] = 0;
				$out[] = $comp;
				continue;
			}

			$audited_by_anyone = isset( $any_manifest[ $hash ] );
			$audited_by_me     = isset( $mine_manifest[ $hash ] );

			if ( $audited_by_me ) {
				continue; // covered by this model — skip entirely
			}

			if ( $audited_by_anyone ) {
				if ( $model === '' ) {
					continue; // default queue: hide everything anyone has audited
				}
				$comp['_audit_tier'] = 1; // model mode: keep, marked for layered re-audit
			} else {
				$comp['_audit_tier'] = 0;
			}
			$out[] = $comp;
		}

		return $out;
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
					'hash'           => '',
					'source_ssh'     => '',
					'home_directory' => '',
					'audit_tier'     => $comp['_audit_tier'] ?? 0,
					'_max_sites'     => -1,
					'_primary_tier'  => PHP_INT_MAX, // tier of the currently-selected primary hash
				];
			}
			$version_map[ $key ]['sites'] += $comp['sites'];
			$version_map[ $key ]['hashes_count']++;

			// Worst-case tier wins: if any hash for this slug+version is fully
			// unaudited (tier 0), the version-group is treated as unaudited.
			$tier = (int) ( $comp['_audit_tier'] ?? 0 );
			if ( $tier < $version_map[ $key ]['audit_tier'] ) {
				$version_map[ $key ]['audit_tier'] = $tier;
			}

			// Pick the primary hash to surface to clients. Prefer a hash whose
			// audit_tier is lowest (i.e. genuinely needs work), then within that
			// tier prefer the most common hash. Otherwise the queue would point
			// crews at an already-audited hash even though other variants for
			// the same slug+version are still unaudited.
			$current_tier = (int) $version_map[ $key ]['_primary_tier'];
			$current_max  = (int) $version_map[ $key ]['_max_sites'];
			$is_better    = $tier < $current_tier
				|| ( $tier === $current_tier && $comp['sites'] > $current_max );

			if ( $is_better ) {
				$version_map[ $key ]['_primary_tier']  = $tier;
				$version_map[ $key ]['_max_sites']     = $comp['sites'];
				$version_map[ $key ]['hash']           = $comp['hash'];
				$version_map[ $key ]['source_ssh']     = $comp['source_ssh'] ?? '';
				$version_map[ $key ]['home_directory'] = $comp['home_directory'] ?? '';
			}
		}

		// Remove internal tracking fields
		$result = array_values( $version_map );
		foreach ( $result as &$item ) {
			unset( $item['_max_sites'], $item['_primary_tier'] );
		}

		return $result;
	}

	/**
	 * Per-slug aggregation for customer-safety prioritization.
	 *
	 * For each slug returns ONE row representing the latest-in-fleet version,
	 * with total fleet site count summed across ALL versions of that slug.
	 * Used by /wp-registry-scan default mode (added 2026-05-16) where the
	 * user's primary goal is to keep the fleet safe by auditing the version
	 * customers actually run — not the wp.org-latest version that may not
	 * be deployed anywhere.
	 *
	 * Sort key downstream is `sites` (total fleet exposure for the slug).
	 *
	 * Row shape:
	 *   slug                  — plugin/theme slug
	 *   latest_version        — newest version present in the fleet (PHP version_compare)
	 *   sites                 — total fleet sites running ANY version of this slug
	 *   versions_in_fleet     — distinct version count
	 *   hash                  — content hash of the latest-version primary variant
	 *   source_ssh, home_directory — for downloading the latest variant
	 *   audit_tier            — tier of the latest-version primary variant (NOT of any older version)
	 *   type, title           — passthroughs
	 *
	 * Older-version backlog is NOT in this queue. A separate call with
	 * group_by=version covers historical-version audits when the user wants
	 * to backfill (Patchstack mode) — but customer-safety treats the
	 * latest-in-fleet as the highest-priority audit target.
	 */
	public static function build_slug_latest_queue( array $hash_map ): array {
		// First pass — group all (slug, version) entries by slug.
		// Each entry in $slug_map holds the per-version data so we can pick
		// the latest in pass two and sum sites across all of them.
		$slug_map = []; // "type|slug" → [ versions => [ version => combined ], total_sites ]

		foreach ( $hash_map as $hash => $comp ) {
			$key = "{$comp['type']}|{$comp['slug']}";
			$ver = $comp['version'] ?? '';
			if ( ! isset( $slug_map[ $key ] ) ) {
				$slug_map[ $key ] = [
					'slug'        => $comp['slug'],
					'type'        => $comp['type'],
					'title'       => $comp['title'] ?? $comp['slug'],
					'total_sites' => 0,
					'versions'    => [],
				];
			}

			if ( ! isset( $slug_map[ $key ]['versions'][ $ver ] ) ) {
				$slug_map[ $key ]['versions'][ $ver ] = [
					'version'        => $ver,
					'sites'          => 0,
					'hashes_count'   => 0,
					'hash'           => '',
					'source_ssh'     => '',
					'home_directory' => '',
					'audit_tier'     => PHP_INT_MAX,
					'_max_sites'     => -1,
					'_primary_tier'  => PHP_INT_MAX,
				];
			}

			$slug_map[ $key ]['total_sites']                 += $comp['sites'];
			$slug_map[ $key ]['versions'][ $ver ]['sites']   += $comp['sites'];
			$slug_map[ $key ]['versions'][ $ver ]['hashes_count']++;

			$tier = (int) ( $comp['_audit_tier'] ?? 0 );
			if ( $tier < $slug_map[ $key ]['versions'][ $ver ]['audit_tier'] ) {
				$slug_map[ $key ]['versions'][ $ver ]['audit_tier'] = $tier;
			}

			// Same primary-hash selection logic as build_version_queue.
			$current_tier = (int) $slug_map[ $key ]['versions'][ $ver ]['_primary_tier'];
			$current_max  = (int) $slug_map[ $key ]['versions'][ $ver ]['_max_sites'];
			$is_better    = $tier < $current_tier
				|| ( $tier === $current_tier && $comp['sites'] > $current_max );
			if ( $is_better ) {
				$slug_map[ $key ]['versions'][ $ver ]['_primary_tier']  = $tier;
				$slug_map[ $key ]['versions'][ $ver ]['_max_sites']     = $comp['sites'];
				$slug_map[ $key ]['versions'][ $ver ]['hash']           = $comp['hash'];
				$slug_map[ $key ]['versions'][ $ver ]['source_ssh']     = $comp['source_ssh'] ?? '';
				$slug_map[ $key ]['versions'][ $ver ]['home_directory'] = $comp['home_directory'] ?? '';
			}
		}

		// Second pass — for each slug, pick the latest version via version_compare.
		$result = [];
		foreach ( $slug_map as $key => $bucket ) {
			if ( empty( $bucket['versions'] ) ) {
				continue;
			}

			$versions = array_keys( $bucket['versions'] );
			// version_compare handles WP-style versions (e.g. "2.10.2", "1.0.0-beta1").
			// Empty-string versions sort last by default — fine because they're rare.
			usort( $versions, 'version_compare' );
			$latest_ver  = end( $versions );
			$latest_data = $bucket['versions'][ $latest_ver ];

			$result[] = [
				'slug'              => $bucket['slug'],
				'latest_version'    => $latest_ver,
				'sites'             => $bucket['total_sites'], // total across all versions of this slug
				'versions_in_fleet' => count( $bucket['versions'] ),
				'type'              => $bucket['type'],
				'title'             => $bucket['title'],
				'hash'              => $latest_data['hash'],
				'hashes_count'      => $latest_data['hashes_count'],
				'source_ssh'        => $latest_data['source_ssh'],
				'home_directory'    => $latest_data['home_directory'],
				'audit_tier'        => $latest_data['audit_tier'] === PHP_INT_MAX ? 0 : $latest_data['audit_tier'],
				// Also expose the per-version site count for the latest, in case
				// the caller wants to surface "X of Y fleet sites are on latest".
				'sites_on_latest'   => $latest_data['sites'],
			];
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
