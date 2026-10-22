<?php

namespace CaptainCore;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Uptime outages, as the CLI's `monitor run` sees them.
 *
 * After every run the CLI posts `monitor-state`: the records still in its
 * monitor.json (every monitored URL that is failing now) and the names it
 * checked. Each failing name gets one open incident; an open incident whose
 * name was checked and is no longer failing is resolved. The monitor emails
 * are unchanged. This table is what agents read instead of the inbox, and
 * they acknowledge an outage once they have looked at it. A change of HTTP
 * code puts an acknowledged outage back in front of them.
 */
class MonitorIncidents extends DB {

	static $primary_key = 'monitor_incident_id';

	/**
	 * @param array $down    MonitorRecord objects: url, name, http_code, html_valid, error, check_count, notify_count, created_at, updated_at.
	 * @param array $checked Names the run checked; only these can resolve.
	 * @return array{opened: int, updated: int, resolved: int}
	 */
	public static function sync( $down, $checked ) {
		global $wpdb;
		$table   = self::table_name();
		$now     = gmdate( 'Y-m-d H:i:s' );
		$opened  = 0;
		$updated = 0;
		$failing = [];
		foreach ( (array) $down as $r ) {
			$r    = (object) $r;
			$name = substr( (string) ( $r->name ?? '' ), 0, 190 );
			if ( $name === '' ) {
				continue;
			}
			$failing[ $name ] = true;
			$code  = substr( (string) ( $r->http_code ?? '' ), 0, 10 );
			$row   = $wpdb->get_row( $wpdb->prepare( "SELECT monitor_incident_id, http_code FROM $table WHERE name = %s AND status = 'open' ORDER BY monitor_incident_id DESC LIMIT 1", $name ) );
			$data  = [
				'url'            => substr( (string) ( $r->url ?? '' ), 0, 255 ),
				'http_code'      => $code,
				'html_valid'     => substr( (string) ( $r->html_valid ?? '' ), 0, 10 ),
				'error'          => substr( (string) ( $r->error ?? '' ), 0, 2000 ),
				'check_count'    => (int) ( $r->check_count ?? 0 ),
				'notify_count'   => (int) ( $r->notify_count ?? 0 ),
				'last_failed_at' => ! empty( $r->updated_at ) ? gmdate( 'Y-m-d H:i:s', (int) $r->updated_at ) : $now,
			];
			if ( $row ) {
				if ( (string) $row->http_code !== $code ) {
					// A different failure is a new look for whoever acknowledged the old one.
					$data['acknowledged_at'] = null;
					$data['acknowledged_by'] = '';
				}
				$wpdb->update( $table, $data, [ 'monitor_incident_id' => $row->monitor_incident_id ] );
				$updated++;
				continue;
			}
			[ $site_id, $environment ] = self::resolve_name( $name );
			$environment_id = $site_id ? (int) ( new Site( $site_id ) )->fetch_environment_id( $environment ) : 0;
			$wpdb->insert( $table, $data + [
				'site_id'        => $site_id,
				'environment_id' => $environment_id,
				'environment'    => $environment,
				'name'           => $name,
				'status'         => 'open',
				'started_at'     => ! empty( $r->created_at ) ? gmdate( 'Y-m-d H:i:s', (int) $r->created_at ) : $now,
				'created_at'     => $now,
			] );
			$opened++;
		}
		$resolved = 0;
		$checked  = array_flip( array_map( 'strval', (array) $checked ) );
		foreach ( $wpdb->get_results( "SELECT monitor_incident_id, name FROM $table WHERE status = 'open'" ) as $row ) {
			if ( isset( $failing[ $row->name ] ) || ! isset( $checked[ $row->name ] ) ) {
				continue;
			}
			$wpdb->update( $table, [ 'status' => 'resolved', 'resolved_at' => $now ], [ 'monitor_incident_id' => $row->monitor_incident_id ] );
			$resolved++;
		}
		return [ 'opened' => $opened, 'updated' => $updated, 'resolved' => $resolved ];
	}

	/**
	 * Monitor names are the site slug, plus -<environment> when it is not production.
	 *
	 * @return array{0: int, 1: string} site_id (0 when unknown) and environment.
	 */
	private static function resolve_name( $name ) {
		global $wpdb;
		$sites = $wpdb->base_prefix . 'captaincore_sites';
		$id    = (int) $wpdb->get_var( $wpdb->prepare( "SELECT site_id FROM $sites WHERE site = %s AND status = 'active' ORDER BY site_id DESC LIMIT 1", $name ) );
		if ( $id ) {
			return [ $id, 'Production' ];
		}
		if ( preg_match( '/^(.+)-([a-z]+)$/', $name, $m ) ) {
			$id = (int) $wpdb->get_var( $wpdb->prepare( "SELECT site_id FROM $sites WHERE site = %s AND status = 'active' ORDER BY site_id DESC LIMIT 1", $m[1] ) );
			if ( $id ) {
				return [ $id, ucfirst( $m[2] ) ];
			}
		}
		return [ 0, 'Production' ];
	}

	/**
	 * Incidents for agents and the dashboard, newest outage first, with the site
	 * name and the latest auto-recovery attempt made during the outage.
	 *
	 * @param string $status open | resolved | all
	 */
	public static function listing( $status = 'open', $include_acknowledged = true, $limit = 200 ) {
		global $wpdb;
		$table      = self::table_name();
		$sites      = $wpdb->base_prefix . 'captaincore_sites';
		$recoveries = $wpdb->prefix . 'captaincore_monitor_recoveries';
		$where      = [ '1=1' ];
		if ( in_array( $status, [ 'open', 'resolved' ], true ) ) {
			$where[] = "i.status = '" . $status . "'";
		}
		if ( ! $include_acknowledged ) {
			$where[] = 'i.acknowledged_at IS NULL';
		}
		$rows = $wpdb->get_results( $wpdb->prepare(
			"SELECT i.*, s.name AS site_name FROM $table i LEFT JOIN $sites s ON s.site_id = i.site_id WHERE " . implode( ' AND ', $where ) . ' ORDER BY i.started_at DESC, i.monitor_incident_id DESC LIMIT %d',
			(int) $limit
		) );
		foreach ( $rows as $row ) {
			$row->recovery = null;
			if ( $row->environment_id ) {
				$row->recovery = $wpdb->get_row( $wpdb->prepare(
					"SELECT monitor_recovery_id, action, outcome, reason, created_at, completed_at FROM $recoveries WHERE environment_id = %d AND created_at >= %s ORDER BY monitor_recovery_id DESC LIMIT 1",
					$row->environment_id, $row->started_at
				) );
			}
		}
		return $rows;
	}

	/**
	 * An agent looked at these outages. They drop out of the unacknowledged
	 * list until the HTTP code changes or the outage resolves and recurs.
	 */
	public static function acknowledge( $ids, $note, $by ) {
		global $wpdb;
		$table   = self::table_name();
		$results = [];
		$done    = 0;
		foreach ( array_unique( array_filter( array_map( 'intval', (array) $ids ) ) ) as $id ) {
			$changed = $wpdb->update( $table, [
				'acknowledged_at' => gmdate( 'Y-m-d H:i:s' ),
				'acknowledged_by' => substr( (string) $by, 0, 60 ),
				'note'            => substr( (string) $note, 0, 4000 ),
			], [ 'monitor_incident_id' => $id ] );
			if ( $changed === false || ( $changed === 0 && ! self::get( $id ) ) ) {
				$results[] = [ 'id' => $id, 'error' => 'No such incident.' ];
				continue;
			}
			$done++;
			$results[] = [ 'id' => $id, 'acknowledged' => true ];
		}
		return [ 'acknowledged' => $done, 'results' => $results ];
	}

	private static function table_name() {
		global $wpdb;
		return $wpdb->base_prefix . 'captaincore_monitor_incidents';
	}

}
