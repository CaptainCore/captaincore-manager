<?php

namespace CaptainCore;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Fleet spam reputation for Breakwater. Each managed site reports, with its daily
 * sync, hashes of what it judged in the last 26 hours: the IP and email of spam senders, and a
 * fingerprint of every message with its verdict. A hash seen on several different sites is a
 * campaign; one or two sites is ordinary (people ask a few businesses the same question).
 */
class SpamSignals extends DB {

	static $primary_key = 'spam_signal_id';

	const KINDS    = [ 'ip', 'email', 'message' ];
	const VERDICTS = [ 'spam', 'ham' ];

	/**
	 * Merge one environment's report. Repeats (the 26-hour window overlaps the next day's)
	 * widen first/last seen; events only grow when the report reaches past what is stored.
	 *
	 * @return int rows written
	 */
	public static function record( $site_id, $environment_id, $items ) {
		global $wpdb;
		$table = $wpdb->base_prefix . 'captaincore_spam_signals';
		$now   = current_time( 'mysql', true );
		$rows  = [];
		foreach ( (array) $items as $item ) {
			$item    = (object) $item;
			$kind    = (string) ( $item->k ?? '' );
			$hash    = strtolower( (string) ( $item->h ?? '' ) );
			$verdict = (string) ( $item->v ?? '' );
			if ( ! in_array( $kind, self::KINDS, true ) || ! in_array( $verdict, self::VERDICTS, true ) || ! preg_match( '/^[a-f0-9]{64}$/', $hash ) ) {
				continue;
			}
			$first  = self::datetime( $item->f ?? '' ) ?: $now;
			$last   = self::datetime( $item->t ?? '' ) ?: $first;
			$rows[] = $wpdb->prepare(
				'(%s, %s, %s, %d, %d, %s, %s, %d, 1, %s, %s, %s, %s)',
				$kind, $hash, $verdict, $site_id, $environment_id,
				substr( sanitize_key( $item->s ?? '' ), 0, 20 ), substr( sanitize_key( $item->l ?? '' ), 0, 40 ),
				max( 1, (int) ( $item->n ?? 1 ) ), $first, $last, $now, $now
			);
		}
		$written = 0;
		foreach ( array_chunk( $rows, 250 ) as $chunk ) {
			$written += (int) $wpdb->query(
				"INSERT INTO $table (kind, hash, verdict, site_id, environment_id, source, labeller, events, reports, first_seen, last_seen, created_at, updated_at) VALUES "
				. implode( ',', $chunk )
				. ' ON DUPLICATE KEY UPDATE
					events = events + IF( VALUES(last_seen) > last_seen, VALUES(events), 0 ),
					reports = reports + 1,
					first_seen = LEAST( first_seen, VALUES(first_seen) ),
					last_seen = GREATEST( last_seen, VALUES(last_seen) ),
					source = VALUES(source),
					labeller = VALUES(labeller),
					updated_at = VALUES(updated_at)'
			);
		}
		return count( $rows );
	}

	/**
	 * Hashes seen on at least $min_sites distinct sites since $days ago. IPs and emails count
	 * spam verdicts only; a message counts whatever each site decided, since the same pitch
	 * reaching many sites is the signal on its own. A hash any site owner marked "Not spam"
	 * (labeller feedback) is left out: one correction outweighs every automated verdict.
	 */
	public static function reputation( $kind, $min_sites = 3, $days = 90, $limit = 0 ) {
		global $wpdb;
		$table   = $wpdb->base_prefix . 'captaincore_spam_signals';
		$verdict = 'message' === $kind ? '' : "AND verdict = 'spam'";
		$sql     = $wpdb->prepare(
			"SELECT hash, COUNT(DISTINCT site_id) AS sites, SUM(events) AS events, MAX(last_seen) AS last_seen
			FROM $table WHERE kind = %s $verdict AND last_seen > %s
			AND hash NOT IN ( SELECT hash FROM $table WHERE kind = %s AND verdict = 'ham' AND labeller = 'feedback' )
			GROUP BY hash HAVING sites >= %d ORDER BY sites DESC, last_seen DESC",
			$kind, gmdate( 'Y-m-d H:i:s', time() - $days * DAY_IN_SECONDS ), $kind, $min_sites
		);
		if ( $limit > 0 ) {
			$sql .= $wpdb->prepare( ' LIMIT %d', $limit );
		}
		return $wpdb->get_results( $sql );
	}

	/** Totals by kind and verdict, and how many sites are reporting. */
	public static function summary( $days = 90 ) {
		global $wpdb;
		$table = $wpdb->base_prefix . 'captaincore_spam_signals';
		$since = gmdate( 'Y-m-d H:i:s', time() - $days * DAY_IN_SECONDS );
		return [
			'sites'   => (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(DISTINCT site_id) FROM $table WHERE last_seen > %s", $since ) ),
			'by_kind' => $wpdb->get_results( $wpdb->prepare( "SELECT kind, verdict, COUNT(*) AS signals, SUM(events) AS events FROM $table WHERE last_seen > %s GROUP BY kind, verdict ORDER BY kind, verdict", $since ) ),
		];
	}

	/** Forget signals not seen for $days. */
	public static function prune( $days = 90 ) {
		global $wpdb;
		$table = $wpdb->base_prefix . 'captaincore_spam_signals';
		return (int) $wpdb->query( $wpdb->prepare( "DELETE FROM $table WHERE last_seen < %s", gmdate( 'Y-m-d H:i:s', time() - $days * DAY_IN_SECONDS ) ) );
	}

	private static function datetime( $value ) {
		$time = strtotime( (string) $value . ( preg_match( '/[zZ]|[+-]\d\d:?\d\d$/', (string) $value ) ? '' : ' UTC' ) );
		return $time ? gmdate( 'Y-m-d H:i:s', $time ) : '';
	}

}
