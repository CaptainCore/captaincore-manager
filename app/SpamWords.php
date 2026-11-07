<?php

namespace CaptainCore;

/**
 * Breakwater's fleet word filter: counts of hashed tokens (words, word pairs, link domains and the
 * sender's email domain) in every site's spam and kept messages, and the model built from them
 * that sites score against.
 *
 * Each site's daily sync reports the previous full UTC day (fetch-site-data, `words` in the spam
 * signal). A day is taken once per environment. A token counts once 3 sites and 5 messages have
 * used it, so names and one-off words never reach the model. The model follows Breakwater's
 * backtest (tests/backtest/words.py): Robinson's smoothing, the strongest 25,000 tokens, and the
 * 10,000 words most used in kept messages, which sites use to abstain on unfamiliar wording.
 */
class SpamWords {

	const MIN_SITES = 3;
	const MIN_DOCS  = 5;
	const CLUES     = 25000;
	const KNOWN     = 10000;
	const CACHE     = 'captaincore_spam_words_model';

	private static function table() {
		global $wpdb;
		return $wpdb->base_prefix . 'captaincore_spam_words';
	}

	private static function days() {
		global $wpdb;
		return $wpdb->base_prefix . 'captaincore_spam_word_days';
	}

	/**
	 * Add one environment's day of word counts. Returns how many tokens were counted, 0 when the
	 * payload is invalid, too old, or this day was already counted for the environment.
	 */
	public static function record( $site_id, $environment_id, $words ) {
		global $wpdb;
		$words = (object) $words;
		$day   = (string) ( $words->day ?? '' );
		$time  = preg_match( '/^\d{4}-\d\d-\d\d$/', $day ) ? strtotime( $day . ' 00:00:00 UTC' ) : false;
		if ( 1 !== (int) ( $words->v ?? 0 ) || ! $time || $time > time() || $time < time() - 3 * DAY_IN_SECONDS ) {
			return 0;
		}
		$docs      = (object) ( $words->docs ?? [] );
		$spam_docs = max( 0, (int) ( $docs->spam ?? 0 ) );
		$ham_docs  = max( 0, (int) ( $docs->ham ?? 0 ) );
		$rows      = [];
		foreach ( (array) ( $words->t ?? [] ) as $hash => $counts ) {
			$hash   = strtolower( (string) $hash );
			$counts = array_values( (array) $counts );
			if ( ! preg_match( '/^=?[0-9a-f]{16}$/', $hash ) || 2 !== count( $counts ) ) {
				continue;
			}
			$spam = min( $spam_docs, max( 0, (int) $counts[0] ) );
			$ham  = min( $ham_docs, max( 0, (int) $counts[1] ) );
			if ( $spam + $ham > 0 ) {
				$rows[] = $wpdb->prepare( '(%s, %d, %d, 1, %s, %s)', $hash, $spam, $ham, (string) (int) $site_id, $day );
			}
		}
		if ( ! $rows || count( $rows ) > 25000 ) {
			return 0;
		}
		$added = $wpdb->query( $wpdb->prepare(
			'INSERT IGNORE INTO ' . self::days() . ' (environment_id, day, site_id, spam, ham, tokens, created_at) VALUES (%d, %s, %d, %d, %d, %d, %s)',
			(int) $environment_id, $day, (int) $site_id, $spam_docs, $ham_docs, count( $rows ), current_time( 'mysql', true )
		) );
		if ( 1 !== (int) $added ) {
			return 0; // This environment's day is already counted.
		}
		// MySQL assigns left to right, so sites is decided before site_ids changes.
		foreach ( array_chunk( $rows, 500 ) as $chunk ) {
			$wpdb->query(
				'INSERT INTO ' . self::table() . ' (hash, spam, ham, sites, site_ids, last_seen) VALUES ' . implode( ',', $chunk ) . '
				ON DUPLICATE KEY UPDATE
					spam = spam + VALUES(spam),
					ham = ham + VALUES(ham),
					sites = IF( sites < ' . self::MIN_SITES . ' AND NOT FIND_IN_SET( VALUES(site_ids), site_ids ), sites + 1, sites ),
					site_ids = IF( FIND_IN_SET( VALUES(site_ids), site_ids ) OR sites >= ' . self::MIN_SITES . ', site_ids, CONCAT( site_ids, \',\', VALUES(site_ids) ) ),
					last_seen = GREATEST( last_seen, VALUES(last_seen) )'
			);
		}
		delete_transient( self::CACHE );
		return count( $rows );
	}

	/** Messages counted so far: [ spam, kept ]. */
	public static function docs() {
		global $wpdb;
		$row = $wpdb->get_row( 'SELECT COALESCE(SUM(spam), 0) AS spam, COALESCE(SUM(ham), 0) AS ham FROM ' . self::days() );
		return [ (int) ( $row->spam ?? 0 ), (int) ( $row->ham ?? 0 ) ];
	}

	/**
	 * What sites score against: each strong token's spam probability per mille, keyed by the first
	 * 12 hex characters of its hash, and the words known from kept messages (first 10 hex). Built at
	 * most twice a day. The model is empty until both spam and kept messages have been counted.
	 */
	public static function model() {
		global $wpdb;
		$cached = get_transient( self::CACHE );
		if ( is_array( $cached ) ) {
			return $cached;
		}
		list( $spam_docs, $ham_docs ) = self::docs();
		$model = [ 'v' => 1, 'generated' => gmdate( 'c' ), 'p' => [], 'k' => [] ];
		$since = gmdate( 'Y-m-d', time() - 90 * DAY_IN_SECONDS );
		if ( $spam_docs && $ham_docs ) {
			$clues = [];
			$rows  = $wpdb->get_results( $wpdb->prepare(
				'SELECT hash, spam, ham FROM ' . self::table() . ' WHERE sites >= %d AND spam + ham >= %d AND last_seen >= %s',
				self::MIN_SITES, self::MIN_DOCS, $since
			) );
			foreach ( $rows as $row ) {
				$n  = (int) $row->spam + (int) $row->ham;
				$bs = (int) $row->spam / $spam_docs;
				$gs = (int) $row->ham / $ham_docs;
				$p  = ( 0.5 + $n * ( $bs / ( $bs + $gs ) ) ) / ( 1 + $n ); // Robinson: strength 1, prior .5
				if ( abs( $p - 0.5 ) >= 0.1 ) {
					$clues[] = [ abs( $p - 0.5 ) * log1p( $n ), substr( ltrim( $row->hash, '=' ), 0, 12 ), $p ];
				}
			}
			usort( $clues, function ( $a, $b ) { return $b[0] <=> $a[0]; } );
			foreach ( array_slice( $clues, 0, self::CLUES ) as $clue ) {
				$model['p'][ $clue[1] ] = (int) round( $clue[2] * 1000 );
			}
			$model['k'] = array_map(
				function ( $hash ) { return substr( $hash, 1, 10 ); },
				$wpdb->get_col( $wpdb->prepare(
					"SELECT hash FROM " . self::table() . " WHERE hash LIKE '=%%' AND ham >= 3 AND sites >= %d AND last_seen >= %s ORDER BY ham DESC LIMIT %d",
					self::MIN_SITES, $since, self::KNOWN
				) )
			);
		}
		$model['p'] = (object) $model['p']; // Keys stay strings in the JSON, even all-digit ones.
		set_transient( self::CACHE, $model, 12 * HOUR_IN_SECONDS );
		return $model;
	}

	/** Forget tokens not seen for $days, and one-off tokens after a month. */
	public static function prune( $days = 90 ) {
		global $wpdb;
		$old   = (int) $wpdb->query( $wpdb->prepare( 'DELETE FROM ' . self::table() . ' WHERE last_seen < %s', gmdate( 'Y-m-d', time() - $days * DAY_IN_SECONDS ) ) );
		$small = (int) $wpdb->query( $wpdb->prepare( 'DELETE FROM ' . self::table() . ' WHERE spam + ham < %d AND last_seen < %s', self::MIN_DOCS, gmdate( 'Y-m-d', time() - 30 * DAY_IN_SECONDS ) ) );
		return $old + $small;
	}

	/** Counts for the WP-CLI summary. */
	public static function summary() {
		global $wpdb;
		list( $spam_docs, $ham_docs ) = self::docs();
		return [
			'days'      => (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . self::days() ),
			'spam_docs' => $spam_docs,
			'ham_docs'  => $ham_docs,
			'tokens'    => (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . self::table() ),
			'usable'    => (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . self::table() . ' WHERE sites >= %d AND spam + ham >= %d', self::MIN_SITES, self::MIN_DOCS ) ),
		];
	}
}
