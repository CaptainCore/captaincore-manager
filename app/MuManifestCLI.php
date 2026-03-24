<?php

namespace CaptainCore;

class MuManifestCLI {

	/**
	 * Auto-generate mu-plugin manifests from fleet data.
	 *
	 * Scans all production environments' mu_plugin_files data, classifies each
	 * mu-plugin into a pattern, auto-generates manifests for simple patterns,
	 * and flags complex patterns for agent review.
	 *
	 * ## OPTIONS
	 *
	 * [--dry-run]
	 * : Show what would be generated without creating manifests.
	 *
	 * [--format=<format>]
	 * : Output format. Accepts table, json. Default table.
	 *
	 * ## EXAMPLES
	 *
	 *     wp captaincore mu-manifest-generate
	 *     wp captaincore mu-manifest-generate --dry-run
	 *
	 * @when after_wp_load
	 */
	public function __invoke( $args = [], $assoc_args = [] ) {
		$dry_run = isset( $assoc_args['dry-run'] );
		$format  = $assoc_args['format'] ?? 'table';

		\WP_CLI::log( 'Scanning fleet for mu-plugin file structures...' );

		// Step 1: Gather all environments with mu_plugin_files data
		$sites_data = ComponentQueueCLI::gather_sites();
		$total_sites = count( $sites_data );

		// Step 2: Build inventory of mu-plugin slugs → file listings
		$inventory = []; // slug → { title, version, sites, file_listings => [ [files...] => count ] }
		$sites_with_data = 0;
		$sites_without_data = 0;

		foreach ( $sites_data as $site ) {
			$details = ! empty( $site->details ) ? json_decode( $site->details, true ) : [];
			$mu_files = $details['mu_plugin_files'] ?? null;

			if ( ! $mu_files ) {
				$sites_without_data++;
				continue;
			}
			$sites_with_data++;

			// Get mu-plugin entries from plugins JSON
			$plugins = ! empty( $site->plugins ) ? json_decode( $site->plugins ) : [];
			$mu_plugins = [];
			if ( is_array( $plugins ) ) {
				foreach ( $plugins as $p ) {
					if ( $p->status === 'must-use' ) {
						$mu_plugins[ $p->name ] = $p;
					}
				}
			}

			// File listing keys (e.g. "kinsta-mu-plugins.php", "kinsta-mu-plugins/")
			$file_keys = array_keys( $mu_files );

			// For each mu-plugin slug on this site, record what files exist
			foreach ( $mu_plugins as $slug => $plugin ) {
				if ( ! isset( $inventory[ $slug ] ) ) {
					$inventory[ $slug ] = [
						'title'         => html_entity_decode( $plugin->title ?? $slug ),
						'version'       => $plugin->version ?? '',
						'sites'         => 0,
						'file_listings' => [],
					];
				}
				$inventory[ $slug ]['sites']++;

				// Determine which files belong to this slug
				$manifest_files = self::classify_files( $slug, $file_keys );
				$listing_key    = implode( '|', $manifest_files );

				if ( ! isset( $inventory[ $slug ]['file_listings'][ $listing_key ] ) ) {
					$inventory[ $slug ]['file_listings'][ $listing_key ] = [
						'files' => $manifest_files,
						'count' => 0,
					];
				}
				$inventory[ $slug ]['file_listings'][ $listing_key ]['count']++;
			}
		}

		\WP_CLI::log( sprintf(
			'Found %d unique mu-plugin slugs across %d sites (%d with file data, %d without).',
			count( $inventory ), $total_sites, $sites_with_data, $sites_without_data
		) );

		// Step 3: Check existing manifests
		$existing_manifests = self::get_existing_manifests();
		$existing_slugs     = [];
		foreach ( $existing_manifests as $m ) {
			$existing_slugs[ $m['slug'] ] = true;
		}

		// Step 4: Classify and generate
		$auto_generated = [];
		$already_exists = [];
		$flagged        = [];
		$no_data        = [];

		// Sort by site count descending
		uasort( $inventory, function ( $a, $b ) {
			return $b['sites'] - $a['sites'];
		} );

		foreach ( $inventory as $slug => $data ) {
			// Already has a manifest?
			if ( isset( $existing_slugs[ $slug ] ) ) {
				$already_exists[] = $slug;
				continue;
			}

			// No file data available?
			if ( empty( $data['file_listings'] ) ) {
				$no_data[] = $slug;
				continue;
			}

			// Use the most common file listing
			$best_listing = null;
			$best_count   = 0;
			foreach ( $data['file_listings'] as $listing ) {
				if ( $listing['count'] > $best_count ) {
					$best_listing = $listing['files'];
					$best_count   = $listing['count'];
				}
			}

			// Check if this was auto-resolvable or needs review
			if ( empty( $best_listing ) || ( count( $best_listing ) === 0 ) ) {
				$flagged[] = [
					'slug'   => $slug,
					'reason' => 'No matching files found in mu-plugins directory',
					'sites'  => $data['sites'],
				];
				continue;
			}

			// Check for orphan directories in the listing that couldn't be attributed
			$has_orphan = false;
			foreach ( $best_listing as $f ) {
				if ( $f === '__orphan__' ) {
					$has_orphan = true;
					break;
				}
			}

			if ( $has_orphan ) {
				$flagged[] = [
					'slug'   => $slug,
					'reason' => 'Slug does not match any .php file — likely an orphan directory loaded by another plugin',
					'sites'  => $data['sites'],
				];
				continue;
			}

			// Auto-generate manifest
			$auto_generated[] = [
				'slug'    => $slug,
				'title'   => $data['title'],
				'version' => $data['version'],
				'files'   => $best_listing,
				'sites'   => $data['sites'],
			];

			if ( ! $dry_run ) {
				self::create_manifest( $slug, $data['title'], $data['version'], $best_listing );
			}
		}

		// Step 5: Report
		\WP_CLI::log( '' );
		\WP_CLI::success( sprintf(
			'Auto-generated: %d | Already manifested: %d | Flagged for review: %d | No file data: %d',
			count( $auto_generated ),
			count( $already_exists ),
			count( $flagged ),
			count( $no_data )
		) );

		if ( $dry_run ) {
			\WP_CLI::log( '(Dry run — no manifests were created)' );
		}

		if ( ! empty( $auto_generated ) && $format === 'table' ) {
			\WP_CLI::log( '' );
			\WP_CLI::log( 'Auto-generated manifests:' );
			$table = array_map( function ( $item ) {
				return [
					'slug'  => $item['slug'],
					'files' => implode( ', ', $item['files'] ),
					'sites' => $item['sites'],
				];
			}, $auto_generated );
			\WP_CLI\Utils\format_items( 'table', $table, [ 'slug', 'files', 'sites' ] );
		}

		if ( ! empty( $flagged ) && $format === 'table' ) {
			\WP_CLI::log( '' );
			\WP_CLI::warning( 'Flagged for agent review:' );
			$table = array_map( function ( $item ) {
				return [
					'slug'   => $item['slug'],
					'reason' => $item['reason'],
					'sites'  => $item['sites'],
				];
			}, $flagged );
			\WP_CLI\Utils\format_items( 'table', $table, [ 'slug', 'reason', 'sites' ] );
		}

		if ( $format === 'json' ) {
			echo wp_json_encode( [
				'auto_generated'   => $auto_generated,
				'already_exists'   => $already_exists,
				'flagged'          => $flagged,
				'no_data'          => $no_data,
				'sites_with_data'  => $sites_with_data,
				'sites_without'    => $sites_without_data,
			], JSON_PRETTY_PRINT ) . "\n";
		}
	}

	/**
	 * Classify which files belong to a mu-plugin slug based on the directory listing.
	 *
	 * Handles patterns 1-3 automatically:
	 *   Pattern 1: slug.php (standalone)
	 *   Pattern 2: slug.php + slug.js / slug.css (file + companion)
	 *   Pattern 3: slug.php + slug/ (file + matching directory)
	 *
	 * Returns ['__orphan__'] for patterns 4-5 that need agent investigation.
	 */
	private static function classify_files( string $slug, array $file_keys ): array {
		$slug_php = "{$slug}.php";
		$slug_dir = "{$slug}/";

		$has_php = in_array( $slug_php, $file_keys, true );
		$has_dir = in_array( $slug_dir, $file_keys, true );

		if ( ! $has_php ) {
			// Slug doesn't match any .php file — likely loaded by an umbrella plugin
			return [ '__orphan__' ];
		}

		$files = [ $slug_php ];

		// Check for matching directory (Pattern 3)
		if ( $has_dir ) {
			$files[] = $slug_dir;
		}

		// Check for companion assets with same base name (Pattern 2)
		foreach ( $file_keys as $f ) {
			if ( $f === $slug_php || $f === $slug_dir ) {
				continue;
			}
			// Match slug.* but not directories
			if ( ! str_ends_with( $f, '/' ) && str_starts_with( $f, $slug . '.' ) ) {
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
		return $wpdb->get_results( "SELECT slug, title, version, files FROM {$table}", ARRAY_A );
	}

	/**
	 * Create a manifest directly in the Security Finder database.
	 */
	private static function create_manifest( string $slug, string $title, string $version, array $files ): void {
		if ( ! class_exists( '\\SecurityFinder\\Database' ) ) {
			\WP_CLI::warning( "Security Finder not available — cannot create manifest for {$slug}" );
			return;
		}

		global $wpdb;
		$table = \SecurityFinder\Database::table( 'mu_plugin_manifests' );

		$sorted_files = $files;
		sort( $sorted_files );
		$files_json = wp_json_encode( $sorted_files );
		$files_hash = hash( 'sha256', $files_json );

		// Check if manifest already exists for this slug + files_hash
		$existing = $wpdb->get_var( $wpdb->prepare(
			"SELECT id FROM {$table} WHERE slug = %s AND files_hash = %s",
			$slug, $files_hash
		) );

		if ( $existing ) {
			return; // Already exists
		}

		$wpdb->insert( $table, [
			'slug'       => $slug,
			'title'      => $title ?: null,
			'version'    => $version ?: null,
			'files'      => $files_json,
			'files_hash' => $files_hash,
		] );
	}
}
