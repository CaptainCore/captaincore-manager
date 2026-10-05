<?php

namespace CaptainCore;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Uptime monitor auto-recovery attempts.
 *
 * The CLI's `monitor run` hands a site that failed two runs in a row to
 * `monitor recover`, which probes the PHP-FPM pool over SSH and posts here
 * (`monitor-recovery-start`). When the probe shows a saturated pool the
 * Manager restarts PHP through the provider, then the CLI re-checks the URL
 * and reports back (`monitor-recovery-finish`), which sends the email.
 *
 * action:  restarted | skipped | failed
 * outcome: restored | still_down | not_attempted
 */
class MonitorRecoveries extends DB {

	static $primary_key = 'monitor_recovery_id';

	/**
	 * Restarts are capped server-side as well as in the CLI, so a misbehaving
	 * monitor (or a replayed request) cannot restart PHP on a site in a loop.
	 */
	const RESTART_COOLDOWN = 45 * MINUTE_IN_SECONDS;

	/**
	 * The most recent restart for an environment within the cooldown, if any.
	 * Ordered by PK, not created_at (two rows can share a timestamp).
	 */
	public static function recent_restart( $site_id, $environment_id, $seconds = self::RESTART_COOLDOWN ) {
		global $wpdb;
		$table = $wpdb->prefix . 'captaincore_monitor_recoveries';
		$since = date( 'Y-m-d H:i:s', time() - (int) $seconds );
		return $wpdb->get_row( $wpdb->prepare(
			"SELECT * FROM $table WHERE site_id = %d AND environment_id = %d AND action = 'restarted' AND created_at >= %s ORDER BY monitor_recovery_id DESC LIMIT 1",
			$site_id, $environment_id, $since
		) );
	}

	/**
	 * "16 of 16 PHP workers busy, 5 more requests waiting". The probe counts
	 * connections on the pool's socket, which includes requests queued behind
	 * a full pool, so busy can exceed max.
	 */
	public static function describe_workers( $busy, $max ) {
		if ( $busy === null || $busy === '' || empty( $max ) ) {
			return 'PHP worker count unavailable';
		}
		$busy = (int) $busy;
		$max  = (int) $max;
		if ( $busy > $max ) {
			$waiting = $busy - $max;
			return "{$max} of {$max} PHP workers busy, {$waiting} more " . ( $waiting === 1 ? 'request' : 'requests' ) . " waiting";
		}
		return "{$busy} of {$max} PHP workers busy";
	}

	/**
	 * Latest attempts across the fleet, newest first.
	 */
	public static function latest( $limit = 50, $site_id = 0 ) {
		global $wpdb;
		$table = $wpdb->prefix . 'captaincore_monitor_recoveries';
		if ( ! empty( $site_id ) ) {
			return $wpdb->get_results( $wpdb->prepare(
				"SELECT * FROM $table WHERE site_id = %d ORDER BY monitor_recovery_id DESC LIMIT %d",
				$site_id, $limit
			) );
		}
		return $wpdb->get_results( $wpdb->prepare(
			"SELECT * FROM $table ORDER BY monitor_recovery_id DESC LIMIT %d",
			$limit
		) );
	}

}
