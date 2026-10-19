<?php

namespace CaptainCore;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SpamSignalsCLI {

	/**
	 * Fleet spam reputation collected from managed sites for Anchor Hosting Protect.
	 *
	 * Shows how many sites are reporting, the signals stored by kind and verdict, and the
	 * hashes seen on the most sites. Hashes are shortened for display; nothing here is
	 * reversible to an IP, email or message.
	 *
	 * ## OPTIONS
	 *
	 * [--top=<n>]
	 * : Show the n most widespread hashes per kind. Default 10.
	 *
	 * [--min-sites=<n>]
	 * : Only list hashes seen on at least this many sites. Default 3.
	 *
	 * [--days=<n>]
	 * : Window in days. Default 90.
	 *
	 * [--prune]
	 * : Delete signals not seen within the window first.
	 *
	 * ## EXAMPLES
	 *
	 *     wp captaincore spam-signals
	 *     wp captaincore spam-signals --top=25 --min-sites=5
	 *     wp captaincore spam-signals --prune
	 *
	 * @when after_wp_load
	 */
	public function __invoke( $args = [], $assoc_args = [] ) {
		$days = (int) ( $assoc_args['days'] ?? 90 );
		$top  = (int) ( $assoc_args['top'] ?? 10 );
		$min  = (int) ( $assoc_args['min-sites'] ?? 3 );

		if ( isset( $assoc_args['prune'] ) ) {
			\WP_CLI::log( 'Pruned ' . SpamSignals::prune( $days ) . " signals not seen in $days days." );
		}

		$summary = SpamSignals::summary( $days );
		\WP_CLI::log( "Sites reporting in the last $days days: {$summary['sites']}" );
		if ( $summary['by_kind'] ) {
			\WP_CLI\Utils\format_items( 'table', array_map( 'get_object_vars', $summary['by_kind'] ), [ 'kind', 'verdict', 'signals', 'events' ] );
		}

		foreach ( SpamSignals::KINDS as $kind ) {
			$rows = SpamSignals::reputation( $kind, $min, $days, $top );
			\WP_CLI::log( "\n" . ucfirst( $kind ) . " hashes on $min+ sites: " . count( SpamSignals::reputation( $kind, $min, $days ) ) );
			if ( $rows ) {
				\WP_CLI\Utils\format_items( 'table', array_map( function ( $row ) {
					return [ 'hash' => substr( $row->hash, 0, 16 ), 'sites' => $row->sites, 'events' => $row->events, 'last_seen' => $row->last_seen ];
				}, $rows ), [ 'hash', 'sites', 'events', 'last_seen' ] );
			}
		}
	}

}
