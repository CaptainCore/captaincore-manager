<?php

namespace CaptainCore;

class ErrorLogCLI {

	/**
	 * List environments by error log size with per-file details.
	 *
	 * ## OPTIONS
	 *
	 * [--limit=<number>]
	 * : Number of environments to show. Default 50.
	 *
	 * [--format=<format>]
	 * : Output format. Accepts table, csv, json. Default table.
	 *
	 * ## EXAMPLES
	 *
	 *     wp captaincore error-log-sizes
	 *     wp captaincore error-log-sizes --limit=25
	 *     wp captaincore error-log-sizes --format=csv
	 *     wp captaincore error-log-sizes --format=json
	 *
	 * @when after_wp_load
	 */
	public function __invoke( $args = [], $assoc_args = [] ) {
		$limit  = isset( $assoc_args['limit'] ) ? (int) $assoc_args['limit'] : 50;
		$format = isset( $assoc_args['format'] ) ? $assoc_args['format'] : 'table';

		global $wpdb;

		$sites_table = $wpdb->prefix . 'captaincore_sites';
		$env_table   = $wpdb->prefix . 'captaincore_environments';

		$results = $wpdb->get_results( $wpdb->prepare( "
			SELECT e.home_url AS site,
			       CONCAT( s.site, '-', LOWER( e.environment ) ) AS site_key,
			       JSON_EXTRACT( e.details, '$.error_logs' ) AS error_logs_json
			FROM {$env_table} e
			JOIN {$sites_table} s ON e.site_id = s.site_id
			WHERE s.status = 'active'
			  AND JSON_EXTRACT( e.details, '$.error_logs' ) IS NOT NULL
			  AND JSON_LENGTH( JSON_EXTRACT( e.details, '$.error_logs' ) ) > 0
			ORDER BY s.site ASC
			LIMIT %d
		", $limit ) );

		if ( empty( $results ) ) {
			\WP_CLI::log( 'No error log data found. Run `captaincore sync-data @all` to collect.' );
			return;
		}

		$output = [];
		$rank   = 0;

		// Build flat list with per-file rows, sorted by total size descending
		$entries = [];
		foreach ( $results as $row ) {
			$files      = json_decode( $row->error_logs_json, true );
			$total_size = 0;
			$total_lines = 0;

			if ( ! is_array( $files ) || empty( $files ) ) {
				continue;
			}

			foreach ( $files as $file ) {
				$total_size  += (int) $file['size'];
				$total_lines += (int) $file['lines'];
			}

			$entries[] = [
				'site'        => $row->site,
				'site_key'    => $row->site_key,
				'files'       => $files,
				'total_size'  => $total_size,
				'total_lines' => $total_lines,
			];
		}

		// Sort by total size descending
		usort( $entries, function ( $a, $b ) {
			return $b['total_size'] - $a['total_size'];
		} );

		foreach ( $entries as $entry ) {
			$rank++;

			$output[] = [
				'#'        => $rank,
				'site'     => $entry['site'],
				'site_key' => $entry['site_key'],
				'files'    => count( $entry['files'] ),
				'lines'    => number_format( $entry['total_lines'] ),
				'size'     => self::format_bytes( $entry['total_size'] ),
			];
		}

		\WP_CLI\Utils\format_items( $format, $output, [ '#', 'site', 'site_key', 'files', 'lines', 'size' ] );
	}

	private static function format_bytes( $bytes ) {
		$bytes = (int) $bytes;
		if ( $bytes >= 1073741824 ) {
			return round( $bytes / 1073741824 ) . ' GB';
		}
		if ( $bytes >= 1048576 ) {
			return round( $bytes / 1048576 ) . ' MB';
		}
		if ( $bytes >= 1024 ) {
			return round( $bytes / 1024 ) . ' KB';
		}
		return $bytes . ' B';
	}

}
