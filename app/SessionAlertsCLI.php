<?php

namespace CaptainCore;

class SessionAlertsCLI {

	/**
	 * Email a digest of unalerted session / privilege anomalies (Phase 2 compromise telemetry).
	 *
	 * Reads session-snapshot rows whose change-detection fired at high/critical severity and
	 * have not yet been alerted (alerted_at IS NULL), groups them by environment, sends a single
	 * digest email to the admin, and stamps the rows alerted so they are not re-sent. Intended to
	 * run hourly from system cron — detection itself happens inline at snapshot ingest.
	 *
	 * ## OPTIONS
	 *
	 * [--severity=<level>]
	 * : Minimum severity to alert on. Accepts high, critical. Default high.
	 *
	 * [--to=<email>]
	 * : Override recipient. Default the site admin_email.
	 *
	 * [--dry-run]
	 * : Show what would be sent without sending or marking rows alerted.
	 *
	 * ## EXAMPLES
	 *
	 *     wp captaincore session-alerts
	 *     wp captaincore session-alerts --dry-run
	 *     wp captaincore session-alerts --severity=critical
	 *
	 * @when after_wp_load
	 */
	public function __invoke( $args = [], $assoc_args = [] ) {
		global $wpdb;

		$dry   = isset( $assoc_args['dry-run'] );
		$to    = $assoc_args['to'] ?? get_option( 'admin_email' );
		$ranks = [ 'high' => 3, 'critical' => 4 ];
		$min   = $assoc_args['severity'] ?? 'high';
		$min_r = $ranks[ $min ] ?? 3;
		$sevs  = array_keys( array_filter( $ranks, function ( $r ) use ( $min_r ) { return $r >= $min_r; } ) );

		$table = $wpdb->prefix . 'captaincore_session_snapshots';
		$in    = implode( ',', array_fill( 0, count( $sevs ), '%s' ) );
		$rows  = $wpdb->get_results( $wpdb->prepare(
			"SELECT * FROM $table WHERE anomaly_count > 0 AND alerted_at IS NULL AND max_severity IN ($in) ORDER BY created_at ASC",
			$sevs
		) );

		if ( empty( $rows ) ) {
			\WP_CLI::success( 'No unalerted session anomalies.' );
			return;
		}

		// One card per environment (latest unalerted snapshot wins), but stamp ALL matched rows.
		$by_env = [];
		$ids    = [];
		foreach ( $rows as $r ) {
			$ids[]                                              = (int) $r->session_snapshot_id;
			$by_env[ $r->site_id . ':' . $r->environment_id ]   = $r; // ASC order -> last (newest) wins
		}

		$items = [];
		foreach ( $by_env as $r ) {
			$site = Sites::get( $r->site_id );
			$env  = ( new Environments )->get( $r->environment_id );
			$name = $site->name ?? ( 'site ' . $r->site_id );
			// Use the environment's own home_url so Production vs Staging cards are distinct
			// (the site name is identical across environments). Fall back to the site domain.
			$home_url = ! empty( $env->home_url ) ? $env->home_url : ( $name ? "https://{$name}" : '' );
			$items[] = [
				'site_name'    => $name,
				'environment'  => $env->environment ?? '',
				'home_url'     => $home_url,
				'max_severity' => $r->max_severity,
				'collected_at' => $r->collected_at,
				'anomalies'    => json_decode( $r->anomalies ) ?: [],
			];
		}

		if ( $dry ) {
			\WP_CLI::log( 'DRY RUN — would email ' . $to . ' a digest of ' . count( $items ) . ' environment(s):' );
			$table_rows = [];
			foreach ( $items as $it ) {
				$table_rows[] = [
					'site'        => $it['site_name'],
					'environment' => $it['environment'],
					'severity'    => $it['max_severity'],
					'anomalies'   => implode( '; ', array_map( function ( $a ) { return $a->detail; }, $it['anomalies'] ) ),
				];
			}
			\WP_CLI\Utils\format_items( 'table', $table_rows, [ 'site', 'environment', 'severity', 'anomalies' ] );
			\WP_CLI::log( '(' . count( $ids ) . ' snapshot row(s) would be marked alerted)' );
			return;
		}

		$sent = Mailer::send_session_anomaly_digest( $to, $items );
		if ( ! $sent ) {
			\WP_CLI::warning( 'Digest had no items to send.' );
			return;
		}

		$now = current_time( 'mysql' );
		$wpdb->query( $wpdb->prepare(
			"UPDATE $table SET alerted_at = %s WHERE session_snapshot_id IN (" . implode( ',', array_fill( 0, count( $ids ), '%d' ) ) . ")",
			array_merge( [ $now ], $ids )
		) );

		\WP_CLI::success( 'Sent digest to ' . $to . ' for ' . count( $items ) . ' environment(s); marked ' . count( $ids ) . ' snapshot(s) alerted.' );
	}
}
