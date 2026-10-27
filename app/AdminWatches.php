<?php

namespace CaptainCore;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Accounts on watch after an account-takeover signal.
 *
 * A new administrator, a new super admin or a takeover capability granted to a
 * single account puts that account on a seven day watch, whatever the signal's
 * verdict. A benign review on day one does not end it: an attacker who creates
 * an admin account usually uses it within the first few days, and a hijacked
 * account that looks legitimate is the case the watch is for. Each daily
 * session snapshot is new evidence, and `dossier()` lays it out per day
 * (sessions, login IPs, application passwords, recent posts) next to every
 * other signal on the environment since the watch opened. An agent reviews
 * the watches with new evidence once a day: the watch stays open, is cleared
 * early, or is flagged, which raises an actionable signal on the attention list.
 */
class AdminWatches extends DB {

	static $primary_key = 'admin_watch_id';

	const DAYS     = 7;
	const TRIGGERS = [ 'new_admin_capable_account', 'new_super_admin', 'injected_caps' ];
	const OUTCOMES = [ 'ok', 'clear', 'flag' ];

	/**
	 * Open (or extend) a watch for each account-takeover anomaly in a session snapshot.
	 *
	 * Call after MalwareFindings::record has stored the anomalies as signals.
	 *
	 * @param array  $anomalies SessionAnomalyDetector anomalies.
	 * @param object $payload   The snapshot payload the anomalies came from.
	 * @return int Watches opened or extended.
	 */
	public static function open_from_anomalies( $site_id, $environment_id, $environment, $anomalies, $payload ) {
		global $wpdb;
		$table   = self::table_name();
		$now     = gmdate( 'Y-m-d H:i:s' );
		$expires = gmdate( 'Y-m-d H:i:s', time() + self::DAYS * DAY_IN_SECONDS );
		$count   = 0;
		foreach ( (array) $anomalies as $x ) {
			$x = (array) $x;
			if ( ! in_array( $x['type'] ?? '', self::TRIGGERS, true ) || strpos( (string) ( $x['subject'] ?? '' ), 'db:user/' ) !== 0 ) {
				continue;
			}
			[ $login, $user_id ] = self::identify( substr( $x['subject'], 8 ), $payload );
			// The signal this anomaly was stored as, new or already known.
			$finding_id = (int) $wpdb->get_var( $wpdb->prepare(
				"SELECT malware_finding_id FROM {$wpdb->base_prefix}captaincore_malware_findings WHERE environment_id = %d AND path = %s AND signature_id = %s ORDER BY malware_finding_id DESC LIMIT 1",
				$environment_id, $x['subject'], 'session-' . str_replace( '_', '-', $x['type'] )
			) );
			$existing   = $wpdb->get_row( $wpdb->prepare(
				"SELECT admin_watch_id FROM $table WHERE environment_id = %d AND status = 'watching' AND ( ( login <> '' AND login = %s ) OR ( user_id > 0 AND user_id = %d ) ) ORDER BY admin_watch_id DESC LIMIT 1",
				$environment_id, $login, $user_id
			) );
			if ( $existing ) {
				$wpdb->update( $table, [ 'expires_at' => $expires ], [ 'admin_watch_id' => $existing->admin_watch_id ] );
				$count++;
				continue;
			}
			$wpdb->insert( $table, [
				'site_id'            => (int) $site_id,
				'environment_id'     => (int) $environment_id,
				'environment'        => substr( (string) $environment, 0, 40 ),
				'login'              => substr( $login, 0, 190 ),
				'user_id'            => $user_id,
				'trigger_type'       => $x['type'],
				'trigger_detail'     => substr( (string) ( $x['detail'] ?? '' ), 0, 1000 ),
				'malware_finding_id' => $finding_id,
				'status'             => 'watching',
				'opened_at'          => $now,
				'expires_at'         => $expires,
				'review_count'       => 0,
				'created_at'         => $now,
			] );
			if ( $finding_id ) {
				MalwareFindingNotes::add( $finding_id, 'watch', 'On watch until ' . $expires . ' UTC (admin watch #' . (int) $wpdb->insert_id . ').', 'scanner' );
			}
			$count++;
		}
		return $count;
	}

	/**
	 * Watches for agents and the dashboard, soonest to expire first.
	 *
	 * @param string $status watching | flagged | cleared | expired | all
	 * @param bool   $due    Only watches with a snapshot newer than their last review.
	 */
	public static function listing( $status = 'watching', $due = false, $limit = 200 ) {
		global $wpdb;
		self::expire();
		$table     = self::table_name();
		$sites     = $wpdb->base_prefix . 'captaincore_sites';
		$envs      = $wpdb->base_prefix . 'captaincore_environments';
		$snapshots = $wpdb->prefix . 'captaincore_session_snapshots';
		$findings  = $wpdb->base_prefix . 'captaincore_malware_findings';
		$where     = [ '1=1' ];
		if ( in_array( $status, [ 'watching', 'flagged', 'cleared', 'expired' ], true ) ) {
			$where[] = "w.status = '" . $status . "'";
		}
		$rows = $wpdb->get_results( $wpdb->prepare(
			"SELECT w.*, s.name AS site_name, s.site AS site_slug, e.home_url, f.verdict AS trigger_verdict,
			        ( SELECT MAX( COALESCE( ss.collected_at, ss.created_at ) ) FROM $snapshots ss WHERE ss.environment_id = w.environment_id ) AS last_snapshot_at
			 FROM $table w
			 LEFT JOIN $sites s ON s.site_id = w.site_id
			 LEFT JOIN $envs e ON e.environment_id = w.environment_id
			 LEFT JOIN $findings f ON f.malware_finding_id = w.malware_finding_id
			 WHERE " . implode( ' AND ', $where ) . ' ORDER BY w.expires_at ASC, w.admin_watch_id ASC LIMIT %d',
			(int) $limit
		) );
		$out = [];
		foreach ( $rows as $row ) {
			$row->due       = $row->status === 'watching' && $row->last_snapshot_at && ( ! $row->last_reviewed_at || $row->last_snapshot_at > $row->last_reviewed_at );
			$row->days_left = max( 0, (int) ceil( ( strtotime( $row->expires_at . ' UTC' ) - time() ) / DAY_IN_SECONDS ) );
			if ( $due && ! $row->due ) {
				continue;
			}
			$out[] = $row;
		}
		return $out;
	}

	/**
	 * Everything collected about a watched account since the day before its watch
	 * opened: one entry per session snapshot, the IPs it used (and which of them
	 * other admins on the site also use), application passwords and posts that
	 * appeared during the watch, and every other signal on the environment since.
	 */
	public static function dossier( $admin_watch_id ) {
		global $wpdb;
		$watch = self::get( (int) $admin_watch_id );
		if ( ! $watch ) {
			return null;
		}
		$snapshots = $wpdb->prefix . 'captaincore_session_snapshots';
		$findings  = $wpdb->base_prefix . 'captaincore_malware_findings';
		$since     = gmdate( 'Y-m-d H:i:s', strtotime( $watch->opened_at . ' UTC' ) - DAY_IN_SECONDS );
		// Start one snapshot before the one that opened the watch: the day the account was not there yet.
		$first     = (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT session_snapshot_id FROM $snapshots WHERE environment_id = %d AND COALESCE( collected_at, created_at ) <= %s ORDER BY session_snapshot_id DESC LIMIT 1 OFFSET 1",
			$watch->environment_id, $watch->opened_at
		) ) ?: PHP_INT_MAX; // none before it: the time window alone
		$rows      = $wpdb->get_results( $wpdb->prepare(
			"SELECT session_snapshot_id, collected_at, created_at, payload FROM $snapshots WHERE environment_id = %d AND ( session_snapshot_id >= %d OR COALESCE( collected_at, created_at ) >= %s ) ORDER BY session_snapshot_id ASC LIMIT 60",
			$watch->environment_id, $first, $since
		) );

		$days      = [];
		$ips       = [];
		$other_ips = [];
		$first_ips = null;
		$app_new   = [];
		$posts     = [];
		foreach ( $rows as $row ) {
			$payload = json_decode( (string) $row->payload, true ) ?: [];
			$account = null;
			foreach ( (array) ( $payload['accounts'] ?? [] ) as $acc ) {
				if ( self::matches( $acc, $watch ) ) {
					$account = $acc;
					continue;
				}
				foreach ( (array) ( $acc['sessions'] ?? [] ) as $s ) {
					if ( ! empty( $s['ip'] ) ) {
						$other_ips[ $s['ip'] ] = true;
					}
				}
			}
			$day = [
				'snapshot_id'  => (int) $row->session_snapshot_id,
				'collected_at' => $row->collected_at ?: $row->created_at,
				'present'      => (bool) $account,
			];
			if ( $account ) {
				$sessions = (array) ( $account['sessions'] ?? [] );
				$day_ips  = array_values( array_unique( array_filter( array_column( $sessions, 'ip' ) ) ) );
				foreach ( $day_ips as $ip ) {
					$ips[ $ip ] = $ips[ $ip ] ?? $day['collected_at'];
				}
				if ( $first_ips === null ) {
					$first_ips = $day_ips;
				}
				$day += [
					'roles'         => $account['roles'] ?? [],
					'is_admin'      => ! empty( $account['is_admin'] ),
					'sessions'      => $sessions,
					'app_passwords' => $account['app_passwords'] ?? null,
					'recent_posts'  => $account['recent_posts'] ?? null,
				];
				// The collector sends both timestamps in UTC, comparable as strings.
				foreach ( (array) ( $account['app_passwords'] ?? [] ) as $ap ) {
					if ( ( $ap['created'] ?? '' ) >= $since ) {
						$app_new[ ( $ap['name'] ?? '' ) . '|' . $ap['created'] ] = $ap;
					}
				}
				foreach ( (array) ( $account['recent_posts']['items'] ?? [] ) as $p ) {
					if ( ! empty( $p['id'] ) && ( $p['modified'] ?? '' ) >= $since ) {
						$posts[ $p['id'] ] = $p;
					}
				}
			}
			$days[] = $day;
		}

		$signals = $wpdb->get_results( $wpdb->prepare(
			"SELECT malware_finding_id, source, signature_id, path, severity, status, verdict, LEFT( verdict_reason, 500 ) AS verdict_reason, first_seen, last_seen
			 FROM $findings WHERE environment_id = %d AND last_seen >= %s AND malware_finding_id <> %d ORDER BY last_seen DESC LIMIT 50",
			$watch->environment_id, $watch->opened_at, (int) $watch->malware_finding_id
		) );

		$all_ips = array_keys( $ips );
		return [
			'watch'         => $watch,
			'trigger'       => $watch->malware_finding_id ? MalwareFindings::rows( [ (int) $watch->malware_finding_id ] )[0] ?? null : null,
			'trail'         => $watch->malware_finding_id ? MalwareFindingNotes::for_findings( [ (int) $watch->malware_finding_id ] ) : [],
			'summary'       => [
				'snapshots'         => count( $days ),
				'days_present'      => count( array_filter( array_column( $days, 'present' ) ) ),
				'ips'               => $ips,
				'new_ips'           => array_values( array_diff( $all_ips, (array) $first_ips ) ),
				'ips_shared_with_other_admins' => array_values( array_intersect( $all_ips, array_keys( $other_ips ) ) ),
				'new_app_passwords' => array_values( $app_new ),
				'recent_posts'      => array_values( $posts ),
			],
			'days'          => $days,
			'other_signals' => $signals,
		];
	}

	/**
	 * Record a daily review. ok keeps watching, clear ends the watch early, flag
	 * ends it and raises an actionable signal for the account on the attention list.
	 */
	public static function review( $admin_watch_id, $outcome, $note, $by ) {
		global $wpdb;
		$watch = self::get( (int) $admin_watch_id );
		if ( ! $watch ) {
			return new \WP_Error( 'not_found', 'No such watch.', [ 'status' => 404 ] );
		}
		if ( ! in_array( $outcome, self::OUTCOMES, true ) ) {
			return new \WP_Error( 'bad_outcome', 'outcome must be one of ' . implode( ', ', self::OUTCOMES ), [ 'status' => 400 ] );
		}
		$now    = gmdate( 'Y-m-d H:i:s' );
		$update = [
			'last_reviewed_at' => $now,
			'last_reviewed_by' => substr( (string) $by, 0, 60 ),
			'note'             => substr( (string) $note, 0, 4000 ),
			'review_count'     => (int) $watch->review_count + 1,
		];
		if ( $outcome === 'clear' ) {
			$update['status']   = 'cleared';
			$update['ended_at'] = $now;
		}
		if ( $outcome === 'flag' ) {
			$path   = 'db:user/' . ( $watch->login !== '' ? $watch->login : '#' . $watch->user_id );
			$result = MalwareFindings::record( (int) $watch->site_id, (int) $watch->environment_id, $watch->environment, 'session', [ (object) [
				'filename'              => $path,
				'signature_id'          => 'session-admin-watch-flag',
				'signature_name'        => 'Session anomaly: watched account flagged',
				'signature_description' => $note,
				'matched_text'          => $note,
				'severity'              => 'critical',
			] ] );
			$flagged = $result['new'][0]->malware_finding_id ?? (int) $wpdb->get_var( $wpdb->prepare(
				"SELECT malware_finding_id FROM {$wpdb->base_prefix}captaincore_malware_findings WHERE environment_id = %d AND path = %s AND signature_id = 'session-admin-watch-flag' ORDER BY malware_finding_id DESC LIMIT 1",
				$watch->environment_id, $path
			) );
			if ( $flagged ) {
				MalwareFindings::set_verdict( $flagged, 'actionable', $note, $by );
			}
			$update['status']          = 'flagged';
			$update['ended_at']        = $now;
			$update['flag_finding_id'] = (int) $flagged;
		}
		$wpdb->update( self::table_name(), $update, [ 'admin_watch_id' => (int) $watch->admin_watch_id ] );
		if ( $watch->malware_finding_id ) {
			MalwareFindingNotes::add( $watch->malware_finding_id, 'watch', "Watch review ({$outcome}): {$note}", $by );
		}
		return self::get( (int) $watch->admin_watch_id );
	}

	/**
	 * How many accounts are on watch now, for the Signals screen.
	 */
	public static function watching_count() {
		global $wpdb;
		self::expire();
		return (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . self::table_name() . " WHERE status = 'watching'" );
	}

	/**
	 * Watches past their seven days end as expired.
	 */
	public static function expire() {
		global $wpdb;
		$now = gmdate( 'Y-m-d H:i:s' );
		return $wpdb->query( $wpdb->prepare( 'UPDATE ' . self::table_name() . " SET status = 'expired', ended_at = %s WHERE status = 'watching' AND expires_at < %s", $now, $now ) );
	}

	/**
	 * A signal subject is a login, or #<user id> for an injected-capability account,
	 * which the snapshot lists by id only. Resolve the other half from the payload.
	 *
	 * @return array{0: string, 1: int} login and user id (either may be empty).
	 */
	private static function identify( $subject, $payload ) {
		$payload = json_decode( wp_json_encode( $payload ), true ) ?: [];
		$login   = $subject;
		$user_id = 0;
		if ( preg_match( '/^#(\d+)$/', $subject, $m ) ) {
			$login   = '';
			$user_id = (int) $m[1];
		}
		foreach ( (array) ( $payload['accounts'] ?? [] ) as $acc ) {
			if ( $login !== '' && ( $acc['login'] ?? '' ) === $login ) {
				return [ $login, (int) ( $acc['user_id'] ?? 0 ) ];
			}
			if ( $user_id && (int) ( $acc['user_id'] ?? 0 ) === $user_id ) {
				return [ (string) ( $acc['login'] ?? '' ), $user_id ];
			}
		}
		return [ $login, $user_id ];
	}

	private static function matches( $acc, $watch ) {
		if ( $watch->login !== '' && ( $acc['login'] ?? '' ) === $watch->login ) {
			return true;
		}
		return $watch->user_id && (int) ( $acc['user_id'] ?? 0 ) === (int) $watch->user_id;
	}

	private static function table_name() {
		global $wpdb;
		return $wpdb->base_prefix . 'captaincore_admin_watches';
	}

}
