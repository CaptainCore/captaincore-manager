<?php

namespace CaptainCore;

class SessionSnapshots extends DB {

	static $primary_key = 'session_snapshot_id';

	/**
	 * The most recent prior snapshot for an environment, by auto-increment PK (not created_at —
	 * two snapshots can share a timestamp, e.g. a manual re-sync right after the daily one, and
	 * ordering by created_at would pick the wrong baseline). Used as the delta-detection baseline.
	 */
	public static function latest_for( $site_id, $environment_id ) {
		global $wpdb;
		$table = $wpdb->prefix . 'captaincore_session_snapshots';
		return $wpdb->get_row( $wpdb->prepare(
			"SELECT * FROM $table WHERE site_id = %d AND environment_id = %d ORDER BY session_snapshot_id DESC LIMIT 1",
			$site_id, $environment_id
		) );
	}

}
