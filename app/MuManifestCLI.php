<?php

namespace CaptainCore;

class MuManifestCLI {

	/**
	 * Report on mu-plugin manifest coverage across the fleet.
	 *
	 * Compares each site's mu_plugin_files data against stored manifests to find:
	 * - Unmanifested mu-plugins (need agent to SSH in and create manifests)
	 * - Manifest drift (site has files not in the stored manifest)
	 * - Orphan files (files/dirs not claimed by any manifest or mu-plugin slug)
	 * - Version changes (mu-plugin version differs from manifested version)
	 *
	 * ## OPTIONS
	 *
	 * [--format=<format>]
	 * : Output format. Accepts table, json. Default table.
	 *
	 * ## EXAMPLES
	 *
	 *     wp captaincore mu-manifest-generate
	 *     wp captaincore mu-manifest-generate --format=json
	 *
	 * @when after_wp_load
	 */
	public function __invoke( $args = [], $assoc_args = [] ) {
		$format = $assoc_args['format'] ?? 'table';

		if ( $format !== 'json' ) {
			\WP_CLI::log( 'Scanning fleet for mu-plugin manifest coverage...' );
		}

		// Load existing manifests
		$manifests       = self::get_existing_manifests();
		$manifest_by_slug = [];
		foreach ( $manifests as $m ) {
			$m['files'] = json_decode( $m['files'], true ) ?: [];
			$manifest_by_slug[ $m['slug'] ][] = $m;
		}

		// Gather fleet data
		$sites_data      = ComponentQueueCLI::gather_sites();
		$total_sites     = count( $sites_data );
		$sites_with_data = 0;

		// Track issues across fleet
		$unmanifested   = []; // slug → { title, version, sites, observed_files }
		$drift          = []; // slug → { manifest_files, observed_files, sites }
		$orphan_files   = []; // file → sites count (files not claimed by any manifest)
		$version_changes = []; // slug → { manifest_version, observed_version, sites }
		$covered        = []; // slug → sites count (fully covered by manifest)

		foreach ( $sites_data as $site ) {
			$details  = ! empty( $site->details ) ? json_decode( $site->details, true ) : [];
			$mu_files = $details['mu_plugin_files'] ?? null;

			if ( ! $mu_files ) {
				continue;
			}
			$sites_with_data++;

			$file_keys = array_keys( $mu_files );

			// Get mu-plugin entries from plugins JSON
			$plugins    = ! empty( $site->plugins ) ? json_decode( $site->plugins ) : [];
			$mu_plugins = [];
			if ( is_array( $plugins ) ) {
				foreach ( $plugins as $p ) {
					if ( $p->status === 'must-use' ) {
						$mu_plugins[ $p->name ] = $p;
					}
				}
			}

			// Track which files are claimed by manifests on this site
			$claimed_files = [];

			foreach ( $mu_plugins as $slug => $plugin ) {
				$site_manifests = $manifest_by_slug[ $slug ] ?? [];

				if ( empty( $site_manifests ) ) {
					// No manifest at all for this slug
					if ( ! isset( $unmanifested[ $slug ] ) ) {
						$unmanifested[ $slug ] = [
							'title'          => html_entity_decode( $plugin->title ?? $slug ),
							'version'        => $plugin->version ?? '',
							'sites'          => 0,
							'observed_files' => [],
						];
					}
					$unmanifested[ $slug ]['sites']++;

					// Record what files we see for this slug on this site
					$observed = self::guess_files_for_slug( $slug, $file_keys );
					$obs_key  = implode( '|', $observed );
					if ( ! isset( $unmanifested[ $slug ]['observed_files'][ $obs_key ] ) ) {
						$unmanifested[ $slug ]['observed_files'][ $obs_key ] = [
							'files' => $observed,
							'count' => 0,
						];
					}
					$unmanifested[ $slug ]['observed_files'][ $obs_key ]['count']++;

					// Still claim the obvious files so they don't show as orphans
					foreach ( $observed as $f ) {
						if ( $f !== '__unknown__' ) {
							$claimed_files[ $f ] = true;
						}
					}
					continue;
				}

				// Has manifest — check for drift and version changes
				$best_manifest = $site_manifests[0]; // Use first (manifests sorted by version DESC)

				// Claim all manifest files
				foreach ( $best_manifest['files'] as $f ) {
					$claimed_files[ $f ] = true;
				}

				// Check version change
				$site_version = $plugin->version ?? '';
				$manifest_version = $best_manifest['version'] ?? '';
				if ( $site_version && $manifest_version && $site_version !== $manifest_version ) {
					$vc_key = "{$slug}|{$site_version}";
					if ( ! isset( $version_changes[ $vc_key ] ) ) {
						$version_changes[ $vc_key ] = [
							'slug'              => $slug,
							'title'             => html_entity_decode( $plugin->title ?? $slug ),
							'manifest_version'  => $manifest_version,
							'observed_version'  => $site_version,
							'sites'             => 0,
						];
					}
					$version_changes[ $vc_key ]['sites']++;
				}

				// Check for drift: are there files on the site matching this slug
				// that aren't in the manifest?
				$site_slug_files = self::guess_files_for_slug( $slug, $file_keys );
				$manifest_file_set = array_flip( $best_manifest['files'] );
				$extra_files = [];
				foreach ( $site_slug_files as $f ) {
					if ( $f !== '__unknown__' && ! isset( $manifest_file_set[ $f ] ) ) {
						$extra_files[] = $f;
					}
					// Also claim these
					if ( $f !== '__unknown__' ) {
						$claimed_files[ $f ] = true;
					}
				}

				if ( ! empty( $extra_files ) ) {
					$drift_key = "{$slug}|" . implode( ',', $extra_files );
					if ( ! isset( $drift[ $drift_key ] ) ) {
						$drift[ $drift_key ] = [
							'slug'            => $slug,
							'title'           => html_entity_decode( $plugin->title ?? $slug ),
							'manifest_files'  => $best_manifest['files'],
							'extra_files'     => $extra_files,
							'sites'           => 0,
						];
					}
					$drift[ $drift_key ]['sites']++;
				}

				// Track covered
				if ( empty( $extra_files ) ) {
					$covered[ $slug ] = ( $covered[ $slug ] ?? 0 ) + 1;
				}
			}

			// Check for orphan files (not claimed by any manifest or slug)
			foreach ( $file_keys as $f ) {
				if ( ! isset( $claimed_files[ $f ] ) ) {
					$orphan_files[ $f ] = ( $orphan_files[ $f ] ?? 0 ) + 1;
				}
			}
		}

		// Sort everything by site count
		uasort( $unmanifested, fn( $a, $b ) => $b['sites'] - $a['sites'] );
		uasort( $drift, fn( $a, $b ) => $b['sites'] - $a['sites'] );
		uasort( $version_changes, fn( $a, $b ) => $b['sites'] - $a['sites'] );
		arsort( $orphan_files );
		arsort( $covered );

		// Report
		if ( $format === 'json' ) {
			// Simplify observed_files for JSON output
			$unmanifested_out = [];
			foreach ( $unmanifested as $slug => $data ) {
				$best_obs = null;
				$best_cnt = 0;
				foreach ( $data['observed_files'] as $obs ) {
					if ( $obs['count'] > $best_cnt ) {
						$best_obs = $obs['files'];
						$best_cnt = $obs['count'];
					}
				}
				$unmanifested_out[] = [
					'slug'           => $slug,
					'title'          => $data['title'],
					'version'        => $data['version'],
					'sites'          => $data['sites'],
					'observed_files' => $best_obs,
					'variants'       => count( $data['observed_files'] ),
				];
			}

			echo wp_json_encode( [
				'summary' => [
					'total_sites'     => $total_sites,
					'sites_with_data' => $sites_with_data,
					'manifested_slugs' => count( $manifest_by_slug ),
					'covered_sites'   => array_sum( $covered ),
				],
				'unmanifested'    => $unmanifested_out,
				'drift'           => array_values( $drift ),
				'version_changes' => array_values( $version_changes ),
				'orphan_files'    => $orphan_files,
				'covered'         => $covered,
			], JSON_PRETTY_PRINT ) . "\n";
			return;
		}

		// Table output
		\WP_CLI::log( '' );
		\WP_CLI::log( sprintf(
			'Fleet: %d sites with mu_plugin_files data (of %d total)',
			$sites_with_data, $total_sites
		) );
		\WP_CLI::log( sprintf(
			'Manifests: %d stored | Covered slugs: %d | Covered site-instances: %d',
			count( $manifests ), count( $covered ), array_sum( $covered )
		) );

		if ( ! empty( $unmanifested ) ) {
			\WP_CLI::log( '' );
			\WP_CLI::warning( sprintf( 'Unmanifested mu-plugins: %d (need agent investigation)', count( $unmanifested ) ) );
			$table = [];
			foreach ( $unmanifested as $slug => $data ) {
				// Get most common observed files
				$best_obs = null;
				$best_cnt = 0;
				foreach ( $data['observed_files'] as $obs ) {
					if ( $obs['count'] > $best_cnt ) {
						$best_obs = $obs['files'];
						$best_cnt = $obs['count'];
					}
				}
				$table[] = [
					'slug'     => $slug,
					'version'  => $data['version'] ?: '-',
					'sites'    => $data['sites'],
					'observed' => implode( ', ', $best_obs ?? [] ),
					'variants' => count( $data['observed_files'] ),
				];
			}
			\WP_CLI\Utils\format_items( 'table', $table, [ 'slug', 'version', 'sites', 'observed', 'variants' ] );
		}

		if ( ! empty( $drift ) ) {
			\WP_CLI::log( '' );
			\WP_CLI::warning( sprintf( 'Manifest drift: %d (site has files not in manifest)', count( $drift ) ) );
			$table = [];
			foreach ( $drift as $d ) {
				$table[] = [
					'slug'        => $d['slug'],
					'extra_files' => implode( ', ', $d['extra_files'] ),
					'sites'       => $d['sites'],
				];
			}
			\WP_CLI\Utils\format_items( 'table', $table, [ 'slug', 'extra_files', 'sites' ] );
		}

		if ( ! empty( $version_changes ) ) {
			\WP_CLI::log( '' );
			\WP_CLI::log( sprintf( 'Version changes: %d (site version differs from manifest)', count( $version_changes ) ) );
			$table = [];
			foreach ( $version_changes as $vc ) {
				$table[] = [
					'slug'     => $vc['slug'],
					'manifest' => $vc['manifest_version'],
					'observed' => $vc['observed_version'],
					'sites'    => $vc['sites'],
				];
			}
			\WP_CLI\Utils\format_items( 'table', $table, [ 'slug', 'manifest', 'observed', 'sites' ] );
		}

		if ( ! empty( $orphan_files ) ) {
			\WP_CLI::log( '' );
			\WP_CLI::warning( sprintf( 'Orphan files: %d (not claimed by any manifest or slug)', count( $orphan_files ) ) );
			$table = [];
			foreach ( array_slice( $orphan_files, 0, 20, true ) as $file => $count ) {
				$table[] = [
					'file'  => $file,
					'sites' => $count,
				];
			}
			\WP_CLI\Utils\format_items( 'table', $table, [ 'file', 'sites' ] );
			if ( count( $orphan_files ) > 20 ) {
				\WP_CLI::log( sprintf( '  ... and %d more', count( $orphan_files ) - 20 ) );
			}
		}

		if ( empty( $unmanifested ) && empty( $drift ) && empty( $orphan_files ) ) {
			\WP_CLI::success( 'All mu-plugins are fully manifested with no drift or orphans!' );
		}
	}

	/**
	 * Guess which files belong to a slug based on naming conventions.
	 *
	 * Returns file paths that match the slug pattern. Does NOT trace includes —
	 * that requires the agent to SSH in and read code.
	 *
	 * Returns ['__unknown__'] if the slug doesn't match any files.
	 */
	private static function guess_files_for_slug( string $slug, array $file_keys ): array {
		$slug_php = "{$slug}.php";
		$slug_dir = "{$slug}/";

		$has_php = in_array( $slug_php, $file_keys, true );
		$has_dir = in_array( $slug_dir, $file_keys, true );

		if ( ! $has_php && ! $has_dir ) {
			return [ '__unknown__' ];
		}

		$files = [];

		if ( $has_php ) {
			$files[] = $slug_php;
		}

		if ( $has_dir ) {
			$files[] = $slug_dir;
		}

		// Check for companion assets (slug.js, slug.css, etc.)
		foreach ( $file_keys as $f ) {
			if ( isset( $files[ $f ] ) ) {
				continue;
			}
			if ( ! str_ends_with( $f, '/' ) && str_starts_with( $f, $slug . '.' ) && $f !== $slug_php ) {
				$files[] = $f;
			}
		}

		sort( $files );
		return $files;
	}

	/**
	 * Get existing manifests from Security Finder.
	 */
	private static function get_existing_manifests(): array {
		if ( ! class_exists( '\\SecurityFinder\\Database' ) ) {
			return [];
		}
		global $wpdb;
		$table = \SecurityFinder\Database::table( 'mu_plugin_manifests' );
		$exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
		if ( empty( $exists ) ) {
			return [];
		}
		return $wpdb->get_results( "SELECT slug, title, version, files FROM {$table} ORDER BY slug, version DESC", ARRAY_A );
	}
}
