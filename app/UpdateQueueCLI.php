<?php

namespace CaptainCore;

class UpdateQueueCLI {

	/**
	 * Build and cache the "update before audit" queue for the Coverage tab.
	 *
	 * Walks every production environment, resolves each unaudited / out-of-date
	 * plugin & theme to the version it should move to (wp.org published-latest
	 * when available, else the newest build already in the fleet), and stores
	 * the result in the `cc_update_queue` transient that the
	 * GET /captaincore/v1/update-queue endpoint serves.
	 *
	 * This is the heavy job — run it from system cron, e.g.:
	 *
	 *   0 8 * * * /usr/local/bin/wp captaincore update-queue --path=/path/to/site
	 *
	 * ## OPTIONS
	 *
	 * [--skip-wporg]
	 * : Skip live api.wordpress.org lookups; overlay only already-cached
	 *   versions. Faster, but published-latest may be missing/stale.
	 *
	 * [--format=<format>]
	 * : Summary output format. Accepts table, json. Default table.
	 *
	 * ## EXAMPLES
	 *
	 *     wp captaincore update-queue
	 *     wp captaincore update-queue --skip-wporg
	 *
	 * @when after_wp_load
	 */
	public function __invoke( $args = [], $assoc_args = [] ) {
		$live_wporg = empty( $assoc_args['skip-wporg'] );
		$format     = $assoc_args['format'] ?? 'table';

		\WP_CLI::log( 'Building update-before-audit queue' . ( $live_wporg ? ' (with wp.org lookups)' : ' (cached wp.org only)' ) . '…' );

		$data = \captaincore_build_update_queue_data( $live_wporg );

		if ( $format === 'json' ) {
			\WP_CLI::line( wp_json_encode( [
				'generated_at' => $data['generated_at'],
				'count'        => $data['count'],
				'needs_update' => $data['needs_update'],
			] ) );
			return;
		}

		$by_source = [ 'wp.org' => 0, 'drift' => 0 ];
		foreach ( $data['items'] as $it ) {
			if ( ! empty( $it['needs_update'] ) ) {
				$by_source[ $it['update_source'] ] = ( $by_source[ $it['update_source'] ] ?? 0 ) + 1;
			}
		}

		\WP_CLI::success( sprintf(
			'Cached %d components — %d need updating (%d via wp.org, %d via drift-steer).',
			$data['count'],
			$data['needs_update'],
			$by_source['wp.org'],
			$by_source['drift']
		) );
	}
}
