<?php

namespace CaptainCore;

/**
 * Pure delta-detection over consecutive session snapshots (WP Registry compromise telemetry,
 * Phase 2). Compares a NEW snapshot payload to the PREVIOUS one for the same environment and
 * returns the anomalies that changed. Design principle learned in Phase 1 triage: alert on
 * the DELTA, not on absolute state — a persistently broad plugin role (Woo shop_manager via
 * Members, miniOrange support_administrator) is normal; the same role *gaining* a takeover
 * cap between snapshots is the signal. Stateless + side-effect-free so it is unit-testable.
 */
class SessionAnomalyDetector {

	// Capabilities that confer effective site takeover (defines admin_capable). Mirrors the
	// collector's TAKEOVER list; unfiltered_html / edit_files are intentionally excluded
	// (editors hold them by default — they are content-tier, not admin-tier).
	const TAKEOVER = [
		'manage_options', 'edit_users', 'promote_users', 'delete_users', 'create_users',
		'remove_users', 'install_plugins', 'install_themes', 'update_plugins', 'update_core',
		'activate_plugins', 'edit_plugins', 'edit_themes',
	];

	const SEVERITY_RANK = [ 'none' => 0, 'low' => 1, 'medium' => 2, 'high' => 3, 'critical' => 4 ];

	// Spike rules require BOTH an absolute floor and a relative multiplier so tiny baselines
	// don't false-alarm and large ones still trip.
	const IP_SPIKE_ABS       = 4;
	const IP_SPIKE_MULT      = 2;
	const SESSION_SPIKE_ABS  = 8;
	const SESSION_SPIKE_MULT = 3;

	/**
	 * @param mixed $new  Decoded new snapshot payload (object or array).
	 * @param mixed $prev Decoded previous snapshot payload, or null for the first snapshot.
	 * @return array{anomalies:array,count:int,max_severity:string}
	 */
	public static function detect( $new, $prev = null ) {
		$new  = self::arr( $new );
		$prev = $prev !== null ? self::arr( $prev ) : null;
		$a    = [];

		// Rule 1 — injected caps (state assertion; fires even with no baseline). Near-zero
		// false positives: a takeover cap on a non-admin-labelled account is a backdoor.
		$new_inj  = self::by_uid( $new['injected_users'] ?? [] );
		$prev_inj = $prev ? self::by_uid( $prev['injected_users'] ?? [] ) : [];
		foreach ( $new_inj as $uid => $u ) {
			if ( isset( $prev_inj[ $uid ] ) ) {
				continue; // already alerted on a prior snapshot — delta-gated, no daily repeat
			}
			$caps  = implode( ', ', (array) ( $u['injected_caps'] ?? [] ) );
			// New collector payload carries clean base_roles; fall back to legacy `roles` field.
			$roles = implode( ', ', (array) ( $u['base_roles'] ?? $u['roles'] ?? [] ) );
			$roles = $roles !== '' ? $roles : 'none';
			$a[]   = self::mk( 'injected_caps', 'critical',
				"User #{$uid} (role: {$roles}) holds individually-granted takeover cap(s) [{$caps}] — likely injected-capability backdoor" );
		}

		// First snapshot: establish baseline, only absolute rules apply.
		if ( $prev === null ) {
			return self::finalize( $a );
		}

		// Rule 2 — new admin-capable account (created, or a low account just elevated).
		$new_acc  = self::logins( $new['accounts'] ?? [] );
		$prev_acc = self::logins( $prev['accounts'] ?? [] );
		foreach ( array_diff( array_keys( $new_acc ), array_keys( $prev_acc ) ) as $login ) {
			$is_admin = ! empty( $new_acc[ $login ]['is_admin'] );
			$a[]      = self::mk( 'new_admin_capable_account', 'high',
				'New ' . ( $is_admin ? 'administrator' : 'admin-capable' ) . " account appeared: {$login}" );
		}

		// Rule 3 — a role gained a takeover cap (the shop_manager-gains-install_plugins case).
		$new_audit  = (array) ( $new['redefined_role_audit'] ?? [] );
		$prev_audit = (array) ( $prev['redefined_role_audit'] ?? [] );
		foreach ( $new_audit as $role => $caps ) {
			$newc  = array_intersect( (array) $caps, self::TAKEOVER );
			$prevc = isset( $prev_audit[ $role ] ) ? array_intersect( (array) $prev_audit[ $role ], self::TAKEOVER ) : [];
			$added = array_diff( $newc, $prevc );
			if ( $added ) {
				$a[] = self::mk( 'role_cap_escalation', 'high',
					"Role '{$role}' gained takeover cap(s): " . implode( ', ', $added ) );
			}
		}
		// A brand-new admin-capable role appearing (other than the built-in administrator).
		foreach ( array_diff( (array) ( $new['admin_capable_roles'] ?? [] ), (array) ( $prev['admin_capable_roles'] ?? [] ) ) as $role ) {
			if ( $role === 'administrator' ) {
				continue;
			}
			$a[] = self::mk( 'new_admin_capable_role', 'high', "New admin-capable role created: '{$role}'" );
		}

		// Rules 4 & 5 — IP / session spikes per tier.
		foreach ( [ 'administrator', 'admin_capable' ] as $tier ) {
			$ip_n = (int) ( $new[ $tier ]['unique_ips'] ?? 0 );
			$ip_p = (int) ( $prev[ $tier ]['unique_ips'] ?? 0 );
			if ( $ip_n >= $ip_p + self::IP_SPIKE_ABS && $ip_n >= max( 1, $ip_p ) * self::IP_SPIKE_MULT ) {
				$a[] = self::mk( 'admin_ip_spike', 'medium', "{$tier} unique login IPs spiked {$ip_p} → {$ip_n}" );
			}
			$s_n = (int) ( $new[ $tier ]['active_sessions'] ?? 0 );
			$s_p = (int) ( $prev[ $tier ]['active_sessions'] ?? 0 );
			if ( $s_n >= $s_p + self::SESSION_SPIKE_ABS && $s_n >= max( 1, $s_p ) * self::SESSION_SPIKE_MULT ) {
				$a[] = self::mk( 'admin_session_spike', 'medium', "{$tier} active sessions spiked {$s_p} → {$s_n}" );
			}
		}

		// Rule 6 — new super admin (multisite).
		foreach ( array_diff( (array) ( $new['super_admins'] ?? [] ), (array) ( $prev['super_admins'] ?? [] ) ) as $sa ) {
			$a[] = self::mk( 'new_super_admin', 'high', "New super admin granted: {$sa}" );
		}

		return self::finalize( $a );
	}

	private static function finalize( $anomalies ) {
		$max = 'none';
		foreach ( $anomalies as $x ) {
			if ( self::SEVERITY_RANK[ $x['severity'] ] > self::SEVERITY_RANK[ $max ] ) {
				$max = $x['severity'];
			}
		}
		return [ 'anomalies' => array_values( $anomalies ), 'count' => count( $anomalies ), 'max_severity' => $max ];
	}

	private static function mk( $type, $severity, $detail ) {
		return [ 'type' => $type, 'severity' => $severity, 'detail' => $detail ];
	}

	private static function arr( $x ) {
		return json_decode( json_encode( $x ), true ) ?: [];
	}

	private static function by_uid( $list ) {
		$o = [];
		foreach ( (array) $list as $u ) {
			$u = (array) $u;
			if ( isset( $u['user_id'] ) ) {
				$o[ $u['user_id'] ] = $u;
			}
		}
		return $o;
	}

	private static function logins( $accounts ) {
		$o = [];
		foreach ( (array) $accounts as $acc ) {
			$acc = (array) $acc;
			if ( isset( $acc['login'] ) ) {
				$o[ $acc['login'] ] = $acc;
			}
		}
		return $o;
	}
}
