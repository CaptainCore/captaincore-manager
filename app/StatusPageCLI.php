<?php

namespace CaptainCore;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The status page: import from Statuspage.io, list, and send queued email.
 */
class StatusPageCLI {

	/**
	 * Import every incident, update and postmortem from a Statuspage.io page.
	 *
	 * Reads the page's public JSON, so the page must still be up. Safe to run
	 * again: rows are matched on their Statuspage id and refreshed. Nobody is
	 * emailed.
	 *
	 * ## OPTIONS
	 *
	 * [--url=<url>]
	 * : The Statuspage page.
	 * ---
	 * default: https://status.anchor.host
	 * ---
	 *
	 * [--file=<file>]
	 * : Import from a JSON file (an array of incidents/<id>.json bodies) instead of fetching.
	 *
	 * [--save=<file>]
	 * : Also write what was fetched to this file.
	 *
	 * ## EXAMPLES
	 *
	 *     wp captaincore status import
	 *     wp captaincore status import --file=incidents.json
	 *
	 * @when after_wp_load
	 */
	public function import( $args, $assoc ) {
		if ( ! empty( $assoc['file'] ) ) {
			$data = json_decode( (string) file_get_contents( $assoc['file'] ), true );
			if ( ! is_array( $data ) ) {
				\WP_CLI::error( 'Not a JSON array: ' . $assoc['file'] );
			}
		} else {
			\WP_CLI::log( 'Reading ' . $assoc['url'] . ' …' );
			$data = StatusPage::fetch_statuspage( $assoc['url'], function ( $m ) { \WP_CLI::log( $m ); } );
			if ( is_wp_error( $data ) ) {
				\WP_CLI::error( $data->get_error_message() );
			}
			if ( ! empty( $assoc['save'] ) ) {
				file_put_contents( $assoc['save'], wp_json_encode( $data, JSON_PRETTY_PRINT ) );
			}
		}
		$c = StatusPage::import_statuspage( $data );
		\WP_CLI::success( sprintf( '%d incidents (%d new, %d refreshed), %d updates, %d postmortems.', $c['created'] + $c['updated'], $c['created'], $c['updated'], $c['updates'], $c['postmortems'] ) );
	}

	/**
	 * Subscribe everyone in a Statuspage subscriber export.
	 *
	 * Active rows are subscribed and linked to the Manager login with the same
	 * email, so their Profile toggle shows on. Anyone who already
	 * unsubscribed here stays unsubscribed. Nobody is emailed.
	 *
	 * ## OPTIONS
	 *
	 * <csv>
	 * : results.csv from the Statuspage subscriber export.
	 *
	 * [--dry-run]
	 * : Count only.
	 *
	 * ## EXAMPLES
	 *
	 *     wp captaincore status import-subscribers results.csv --dry-run
	 *
	 * @subcommand import-subscribers
	 * @when after_wp_load
	 */
	public function import_subscribers( $args, $assoc ) {
		$c = StatusPage::import_subscribers( $args[0], ! empty( $assoc['dry-run'] ) );
		if ( is_wp_error( $c ) ) {
			\WP_CLI::error( $c->get_error_message() );
		}
		\WP_CLI::success( sprintf( '%s%d subscribed (%d linked to a login), %d skipped, %d kept unsubscribed. %d active subscribers.',
			! empty( $assoc['dry-run'] ) ? '[dry run] ' : '', $c['subscribed'], $c['linked'], $c['skipped'], $c['kept_unsubscribed'], StatusPage::subscriber_count() ) );
	}

	/**
	 * List incidents.
	 *
	 * ## OPTIONS
	 *
	 * [--limit=<n>]
	 * : How many.
	 * ---
	 * default: 20
	 * ---
	 *
	 * [--format=<format>]
	 * : table, json or csv.
	 * ---
	 * default: table
	 * ---
	 *
	 * @when after_wp_load
	 */
	public function list( $args, $assoc ) {
		$rows = StatusPage::listing( (int) $assoc['limit'] )['incidents'];
		$rows = array_map( function ( $r ) {
			return [ 'id' => $r['id'], 'started_at' => $r['started_at'], 'kind' => $r['kind'], 'status' => $r['status'], 'impact' => $r['impact'], 'updates' => $r['update_count'], 'postmortem' => $r['postmortem_published_at'] ? 'yes' : '', 'title' => $r['title'] ];
		}, $rows );
		\WP_CLI\Utils\format_items( $assoc['format'], $rows, [ 'id', 'started_at', 'kind', 'status', 'impact', 'updates', 'postmortem', 'title' ] );
	}

	/**
	 * Send queued status email now (cron does this every minute).
	 *
	 * @when after_wp_load
	 */
	public function send( $args, $assoc ) {
		\WP_CLI::success( StatusPage::run_due( 300 ) . ' emails sent.' );
	}

}
