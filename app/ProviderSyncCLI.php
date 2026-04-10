<?php

namespace CaptainCore;

class ProviderSyncCLI {

	/**
	 * Reconcile local SFTP/SSH credentials with each site's hosting provider.
	 *
	 * Crawls sites slowly (throttled) and calls Site::remote_sync() on each,
	 * updating drifted address / port / username / password / home_directory
	 * values from the provider's API. Sites whose provider doesn't yet
	 * implement remote sync are marked "skipped" and move on.
	 *
	 * The last successful sync timestamp is written to
	 * wp_captaincore_sites.details.last_remote_sync_at so re-runs can use
	 * --stale to skip recently-checked sites and resume naturally after
	 * interruptions.
	 *
	 * ## OPTIONS
	 *
	 * [--provider=<provider>]
	 * : Only sync sites hosted by this provider (e.g. kinsta). Defaults to all.
	 *
	 * [--site=<site_id>]
	 * : Sync a single site by id. Overrides --provider, --stale and --limit.
	 *
	 * [--throttle=<seconds>]
	 * : Seconds to sleep between sites. Default 3.
	 *
	 * [--limit=<n>]
	 * : Maximum number of sites to process in this run.
	 *
	 * [--stale=<duration>]
	 * : Skip sites synced more recently than this. Accepts values like
	 *   "6d", "12h", "30m". Sites that have never been synced are always
	 *   included. Default: process all eligible sites.
	 *
	 * [--dry-run]
	 * : Compute diffs but don't write changes to the DB, trigger site syncs,
	 *   or stamp last_remote_sync_at.
	 *
	 * [--verbose]
	 * : Print the full change list for each updated site.
	 *
	 * ## EXAMPLES
	 *
	 *     wp captaincore provider-sync --site=2912
	 *     wp captaincore provider-sync --limit=10 --dry-run
	 *     wp captaincore provider-sync --provider=kinsta --stale=6d
	 *     wp captaincore provider-sync --throttle=5 --verbose
	 *
	 * @when after_wp_load
	 */
	public function __invoke( $args = [], $assoc_args = [] ) {
		$provider = isset( $assoc_args['provider'] ) ? (string) $assoc_args['provider'] : '';
		$site_id  = isset( $assoc_args['site'] ) ? (int) $assoc_args['site'] : 0;
		$throttle = isset( $assoc_args['throttle'] ) ? (float) $assoc_args['throttle'] : 3;
		$limit    = isset( $assoc_args['limit'] ) ? (int) $assoc_args['limit'] : 0;
		$stale    = isset( $assoc_args['stale'] ) ? (string) $assoc_args['stale'] : '';
		$dry_run  = isset( $assoc_args['dry-run'] );
		$verbose  = isset( $assoc_args['verbose'] );

		$site_ids = $site_id
			? $this->single_site_ids( $site_id )
			: $this->select_site_ids( $provider, $stale, $limit );

		$total = count( $site_ids );
		if ( $total === 0 ) {
			\WP_CLI::log( 'No sites matched.' );
			return;
		}

		\WP_CLI::log( sprintf(
			'Processing %d site(s)%s%s%s',
			$total,
			$provider ? " provider={$provider}" : '',
			$stale ? " stale>{$stale}" : '',
			$dry_run ? ' (dry run)' : ''
		) );

		$counts = [ 'updated' => 0, 'in-sync' => 0, 'skipped' => 0, 'error' => 0 ];
		$started = microtime( true );

		foreach ( $site_ids as $i => $current_id ) {
			$site = ( new Sites )->get( $current_id );
			if ( empty( $site ) ) {
				\WP_CLI::warning( sprintf( '[%d/%d] site #%d not found', $i + 1, $total, $current_id ) );
				$counts['error']++;
				continue;
			}

			$name     = $site->name ?: "site#{$current_id}";
			$provider_label = $site->provider ?: 'none';

			try {
				$result = ( new Site( $current_id ) )->remote_sync( $dry_run );
			} catch ( \Throwable $e ) {
				$counts['error']++;
				\WP_CLI::warning( sprintf(
					'[%d/%d] %s (%s) — exception: %s',
					$i + 1, $total, $name, $provider_label, $e->getMessage()
				) );
				$this->throttle( $throttle );
				continue;
			}

			$status  = $result['status'] ?? 'error';
			$counts[ $status ] = ( $counts[ $status ] ?? 0 ) + 1;

			$line = sprintf( '[%d/%d] %s (%s) — %s', $i + 1, $total, $name, $provider_label, $status );
			if ( $status === 'updated' ) {
				$summary = [];
				foreach ( ( $result['changes'] ?? [] ) as $change ) {
					$summary[] = "{$change['environment']}.{$change['field']}";
				}
				$line .= ': ' . implode( ', ', $summary );
			} elseif ( in_array( $status, [ 'error', 'skipped' ], true ) ) {
				$line .= ': ' . ( $result['message'] ?? '' );
			}
			\WP_CLI::log( $line );

			if ( $verbose && ! empty( $result['changes'] ) ) {
				foreach ( $result['changes'] as $change ) {
					\WP_CLI::log( sprintf(
						'        %s.%s: %s -> %s',
						$change['environment'], $change['field'], $change['before'], $change['after']
					) );
				}
			}
			if ( $verbose && ! empty( $result['warnings'] ) ) {
				foreach ( $result['warnings'] as $warning ) {
					\WP_CLI::log( '        warning: ' . $warning );
				}
			}

			$this->throttle( $throttle );
		}

		$elapsed = microtime( true ) - $started;
		\WP_CLI::success( sprintf(
			'Done in %s. updated=%d in-sync=%d skipped=%d error=%d',
			$this->format_elapsed( $elapsed ),
			$counts['updated'], $counts['in-sync'], $counts['skipped'], $counts['error']
		) );
	}

	/**
	 * Return an array containing a single site id if the site exists.
	 */
	protected function single_site_ids( $site_id ) {
		global $wpdb;
		$table = $wpdb->prefix . 'captaincore_sites';
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT site_id FROM {$table} WHERE site_id = %d", $site_id ) );
		return $row ? [ (int) $row->site_id ] : [];
	}

	/**
	 * Select site ids to process, ordered by staleness (never-synced first,
	 * then oldest last_remote_sync_at). Applies --provider, --stale and
	 * --limit filters.
	 */
	protected function select_site_ids( $provider, $stale, $limit ) {
		global $wpdb;
		$table = $wpdb->prefix . 'captaincore_sites';

		$where   = [ "status = 'active'" ];
		$prepare = [];

		if ( $provider !== '' ) {
			$where[]   = 'provider = %s';
			$prepare[] = $provider;
		}

		$stale_seconds = $this->parse_duration( $stale );
		if ( $stale_seconds > 0 ) {
			$cutoff    = gmdate( 'Y-m-d H:i:s', time() - $stale_seconds );
			$where[]   = "( JSON_EXTRACT( details, '$.last_remote_sync_at' ) IS NULL OR JSON_UNQUOTE( JSON_EXTRACT( details, '$.last_remote_sync_at' ) ) < %s )";
			$prepare[] = $cutoff;
		}

		$sql = "SELECT site_id FROM {$table} WHERE " . implode( ' AND ', $where )
			. " ORDER BY ( JSON_EXTRACT( details, '$.last_remote_sync_at' ) IS NULL ) DESC,"
			. " JSON_UNQUOTE( JSON_EXTRACT( details, '$.last_remote_sync_at' ) ) ASC,"
			. " site_id ASC";

		if ( $limit > 0 ) {
			$sql      .= ' LIMIT %d';
			$prepare[] = $limit;
		}

		$sql = $prepare ? $wpdb->prepare( $sql, $prepare ) : $sql;
		return array_map( 'intval', $wpdb->get_col( $sql ) );
	}

	/**
	 * Parse a duration string like "6d", "12h", "30m", "45s" into seconds.
	 * Bare integers are treated as seconds. Returns 0 for empty/unknown.
	 */
	protected function parse_duration( $value ) {
		$value = trim( (string) $value );
		if ( $value === '' ) {
			return 0;
		}
		if ( ! preg_match( '/^(\d+)([smhd]?)$/i', $value, $m ) ) {
			return 0;
		}
		$n    = (int) $m[1];
		$unit = strtolower( $m[2] );
		switch ( $unit ) {
			case 'd': return $n * 86400;
			case 'h': return $n * 3600;
			case 'm': return $n * 60;
			default:  return $n;
		}
	}

	protected function throttle( $seconds ) {
		$seconds = (float) $seconds;
		if ( $seconds <= 0 ) {
			return;
		}
		usleep( (int) ( $seconds * 1000000 ) );
	}

	protected function format_elapsed( $seconds ) {
		if ( $seconds < 60 ) {
			return round( $seconds, 1 ) . 's';
		}
		$minutes = floor( $seconds / 60 );
		$rem     = $seconds - ( $minutes * 60 );
		if ( $minutes < 60 ) {
			return sprintf( '%dm %ds', $minutes, $rem );
		}
		$hours   = floor( $minutes / 60 );
		$minutes = $minutes - ( $hours * 60 );
		return sprintf( '%dh %dm', $hours, $minutes );
	}

}
