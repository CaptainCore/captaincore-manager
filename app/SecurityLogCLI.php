<?php

namespace CaptainCore;

class SecurityLogCLI {

	/**
	 * List environments by security log size.
	 *
	 * ## OPTIONS
	 *
	 * [--limit=<number>]
	 * : Number of results to show. Default 50.
	 *
	 * [--format=<format>]
	 * : Output format. Accepts table, csv, json. Default table.
	 *
	 * ## EXAMPLES
	 *
	 *     wp captaincore security-log-sizes
	 *     wp captaincore security-log-sizes --limit=25
	 *     wp captaincore security-log-sizes --format=csv
	 *     wp captaincore security-log-sizes --format=json
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
			       CAST( JSON_UNQUOTE( JSON_EXTRACT( e.details, '$.security_log.size' ) ) AS UNSIGNED ) AS size_bytes,
			       CAST( JSON_UNQUOTE( JSON_EXTRACT( e.details, '$.security_log.rows' ) ) AS UNSIGNED ) AS row_count
			FROM {$env_table} e
			JOIN {$sites_table} s ON e.site_id = s.site_id
			WHERE s.status = 'active'
			  AND JSON_EXTRACT( e.details, '$.security_log' ) IS NOT NULL
			  AND CAST( JSON_UNQUOTE( JSON_EXTRACT( e.details, '$.security_log.size' ) ) AS UNSIGNED ) > 0
			ORDER BY size_bytes DESC
			LIMIT %d
		", $limit ) );

		if ( empty( $results ) ) {
			\WP_CLI::log( 'No security log data found. Run `captaincore sync-data @all` to collect.' );
			return;
		}

		$output = [];
		foreach ( $results as $i => $row ) {
			$output[] = [
				'#'        => $i + 1,
				'site'     => $row->site,
				'site_key' => $row->site_key,
				'rows'     => number_format( (int) $row->row_count ),
				'size'     => self::format_bytes( $row->size_bytes ),
			];
		}

		\WP_CLI\Utils\format_items( $format, $output, [ '#', 'site', 'site_key', 'rows', 'size' ] );
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
