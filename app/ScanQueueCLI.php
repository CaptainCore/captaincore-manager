<?php

namespace CaptainCore;

class ScanQueueCLI {

	/**
	 * Generate a prioritized scan queue for Security Finder audits.
	 *
	 * Queries all production environments, checks audit coverage via
	 * Security Finder's scan-queue API, and returns the top sites
	 * ranked by number of unaudited components.
	 *
	 * ## OPTIONS
	 *
	 * [--limit=<number>]
	 * : Number of sites to return. Default 5.
	 *
	 * [--format=<format>]
	 * : Output format. Accepts table, json, csv. Default table.
	 *
	 * [--all]
	 * : Show all sites needing audits, not just the top N.
	 *
	 * ## EXAMPLES
	 *
	 *     wp captaincore scan-queue
	 *     wp captaincore scan-queue --limit=10
	 *     wp captaincore scan-queue --format=json
	 *     wp captaincore scan-queue --all --format=csv
	 *
	 * @when after_wp_load
	 */
	public function __invoke( $args = [], $assoc_args = [] ) {
		$limit    = isset( $assoc_args['limit'] ) ? (int) $assoc_args['limit'] : 5;
		$format   = $assoc_args['format'] ?? 'table';
		$show_all = isset( $assoc_args['all'] );

		$api_url = self::api_url();
		if ( ! $api_url ) {
			\WP_CLI::error( 'SECURITY_FINDER_API_URL is not configured. Add it to wp-config.php.' );
			return;
		}

		$quiet = $format !== 'table';
		$log   = function( $msg ) use ( $quiet ) {
			if ( ! $quiet ) { \WP_CLI::log( $msg ); }
		};

		// Step 1: Gather all production environments with SSH + plugin/theme data
		$log( 'Gathering production environment data...' );
		$sites_data = self::gather_sites();
		$log( sprintf( 'Found %d active production sites.', count( $sites_data ) ) );

		// Step 2: Build payload for scan-queue API
		$payload = [];
		$ssh_map = []; // site_slug => ssh connection string
		foreach ( $sites_data as $site ) {
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
							];
						}
					}
				}
			}

			if ( empty( $components ) ) {
				continue;
			}

			// Build site slug from domain (strip TLD-like suffixes)
			$domain    = $site->home_url ? preg_replace( '#^https?://(www\.)?#', '', $site->home_url ) : $site->name;
			$site_slug = preg_replace( '/[^a-z0-9]/', '', explode( '.', $domain )[0] );

			$payload[] = [
				'site_slug'  => $site_slug,
				'domain'     => $domain,
				'components' => $components,
			];

			// Store SSH connection info
			if ( ! empty( $site->address ) && ! empty( $site->username ) ) {
				$ssh = "{$site->username}@{$site->address}";
				if ( ! empty( $site->port ) && $site->port !== '22' ) {
					$ssh .= " -p {$site->port}";
				}
				$ssh_map[ $site_slug ] = [
					'ssh'            => $ssh,
					'domain'         => $domain,
					'site_id'        => $site->site_id,
					'name'           => $site->name,
					'provider'       => $site->provider,
					'home_directory' => $site->home_directory,
				];
			}
		}

		// Step 3: Call Security Finder scan-queue API
		$log( 'Checking audit coverage via Security Finder...' );

		// Batch into chunks of 200 sites to avoid massive payloads
		$all_queue = [];
		$chunks    = array_chunk( $payload, 200 );
		$progress  = $quiet ? null : \WP_CLI\Utils\make_progress_bar( 'Processing', count( $chunks ) );

		foreach ( $chunks as $chunk ) {
			$response = wp_remote_post( $api_url . '/api.php?action=scan-queue', [
				'headers'   => [ 'Content-Type' => 'application/json' ],
				'body'      => wp_json_encode( $chunk ),
				'timeout'   => 60,
				'sslverify' => false,
			] );

			if ( is_wp_error( $response ) ) {
				\WP_CLI::warning( 'API error: ' . $response->get_error_message() );
				if ( $progress ) { $progress->tick(); }
				continue;
			}

			$body = json_decode( wp_remote_retrieve_body( $response ), true );
			if ( ! empty( $body['queue'] ) ) {
				$all_queue = array_merge( $all_queue, $body['queue'] );
			}

			if ( $progress ) { $progress->tick(); }
		}

		if ( $progress ) { $progress->finish(); }

		if ( empty( $all_queue ) ) {
			\WP_CLI::success( 'All sites are fully covered! No audits needed.' );
			return;
		}

		// Re-sort combined results
		usort( $all_queue, function ( $a, $b ) {
			$diff = $b['needs_audit'] - $a['needs_audit'];
			if ( $diff !== 0 ) return $diff;
			return $b['total_components'] - $a['total_components'];
		} );

		// Trim to limit
		if ( ! $show_all ) {
			$all_queue = array_slice( $all_queue, 0, $limit );
		}

		// Step 4: Enrich with SSH connection strings
		$output = [];
		foreach ( $all_queue as $item ) {
			$ssh_info = $ssh_map[ $item['site_slug'] ] ?? null;
			$row = [
				'site_slug'      => $item['site_slug'],
				'domain'         => $item['domain'],
				'needs_audit'    => $item['needs_audit'],
				'total'          => $item['total_components'],
				'coverage'       => $item['coverage_pct'] . '%',
				'ssh'            => $ssh_info ? $ssh_info['ssh'] : 'N/A',
			];
			$output[] = $row;
		}

		$log( '' );
		$log( sprintf( 'Sites needing audits: %d (showing %s)', count( $all_queue ), $show_all ? 'all' : "top $limit" ) );

		if ( $format === 'json' ) {
			// For JSON, include full details including unaudited_slugs and SSH
			$json_output = [];
			foreach ( $all_queue as $item ) {
				$ssh_info = $ssh_map[ $item['site_slug'] ] ?? null;
				$json_output[] = array_merge( $item, [
					'ssh'            => $ssh_info ? $ssh_info['ssh'] : null,
					'site_id'        => $ssh_info ? $ssh_info['site_id'] : null,
					'name'           => $ssh_info ? $ssh_info['name'] : null,
					'provider'       => $ssh_info ? $ssh_info['provider'] : null,
					'home_directory' => $ssh_info ? $ssh_info['home_directory'] : null,
				] );
			}
			echo wp_json_encode( $json_output, JSON_PRETTY_PRINT ) . "\n";
			return;
		}

		\WP_CLI\Utils\format_items( $format, $output, [ 'site_slug', 'domain', 'needs_audit', 'total', 'coverage', 'ssh' ] );
	}

	/**
	 * Gather all production environments with SSH credentials and plugin/theme data.
	 */
	private static function gather_sites() {
		global $wpdb;

		$sites_table = $wpdb->prefix . 'captaincore_sites';
		$env_table   = $wpdb->prefix . 'captaincore_environments';

		return $wpdb->get_results( "
			SELECT s.site_id, s.name, s.provider,
			       e.address, e.username, e.port, e.home_url, e.home_directory,
			       e.plugins, e.themes
			FROM {$env_table} e
			JOIN {$sites_table} s ON e.site_id = s.site_id
			WHERE s.status = 'active'
			  AND e.environment = 'Production'
			  AND e.plugins IS NOT NULL
			ORDER BY s.name ASC
		" );
	}

	/**
	 * Get the Security Finder API base URL.
	 */
	private static function api_url() {
		if ( defined( 'SECURITY_FINDER_API_URL' ) ) {
			return rtrim( SECURITY_FINDER_API_URL, '/' );
		}
		return get_option( 'security_finder_api_url', '' );
	}

}
