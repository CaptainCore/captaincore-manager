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
	 * Whether the probe shows a pool that cannot take another request.
	 *
	 * Socket connections at pm.max_children are the direct reading, and the
	 * count can exceed max when requests are queued behind a full pool. An
	 * ondemand or dynamic pool also counts as full once it has spawned every
	 * child: nginx drops a connection when its read times out while the worker
	 * is still inside the request, so the socket count can sit one under the
	 * process count. A static pool always has max_children processes, so its
	 * process count says nothing. Host load is not used; inside a container it
	 * is the node's load.
	 *
	 * @param bool   $ssh_ok The probe reached the site.
	 * @param object $probe  Fields from php-worker-check.
	 */
	public static function pool_saturated( $ssh_ok, $probe ) {
		if ( empty( $ssh_ok ) || ! is_object( $probe ) ) {
			return false;
		}
		$max = ( isset( $probe->max_workers ) && is_numeric( $probe->max_workers ) ) ? (int) $probe->max_workers : 0;
		if ( $max < 1 ) {
			return false;
		}
		$busy = ( isset( $probe->busy_workers ) && is_numeric( $probe->busy_workers ) ) ? (int) $probe->busy_workers : null;
		if ( $busy !== null && $busy >= $max ) {
			return true;
		}
		$pm = strtolower( trim( (string) ( $probe->pm ?? '' ) ) );
		if ( $pm !== 'ondemand' && $pm !== 'dynamic' ) {
			return false;
		}
		$processes = ( isset( $probe->fpm_processes ) && is_numeric( $probe->fpm_processes ) ) ? (int) $probe->fpm_processes : null;
		return $processes !== null && $processes >= $max;
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
