<?php

namespace CaptainCore;

class TopPluginsCLI {

	/**
	 * List the most used active plugins across production environments.
	 *
	 * ## OPTIONS
	 *
	 * [--limit=<number>]
	 * : Number of plugins to show. Default 100.
	 *
	 * [--format=<format>]
	 * : Output format. Accepts table, csv, json. Default table.
	 *
	 * ## EXAMPLES
	 *
	 *     wp captaincore top-plugins
	 *     wp captaincore top-plugins --limit=25
	 *     wp captaincore top-plugins --format=csv
	 *     wp captaincore top-plugins --format=json
	 *
	 * @when after_wp_load
	 */
	public function __invoke( $args = [], $assoc_args = [] ) {
		$limit  = isset( $assoc_args['limit'] ) ? (int) $assoc_args['limit'] : 100;
		$format = isset( $assoc_args['format'] ) ? $assoc_args['format'] : 'table';

		$results = Environments::top_plugins( $limit );

		if ( empty( $results ) ) {
			\WP_CLI::log( 'No active plugins found across production environments.' );
			return;
		}

		\WP_CLI\Utils\format_items( $format, $results, [ 'name', 'title', 'site_count' ] );
	}

}
