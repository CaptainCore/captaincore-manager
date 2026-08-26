<?php

namespace CaptainCore;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CoreUpdateRunsCLI {

	/**
	 * List core-update probe/apply runs, or show one run's results.
	 *
	 * ## OPTIONS
	 *
	 * [<run_id>]
	 * : Show a single run. Omit to list recent runs.
	 *
	 * [--result=<result>]
	 * : Filter results: ok or fail.
	 *
	 * [--stage=<stage>]
	 * : Filter by stage (boot, render, http, root, memory, ...).
	 *
	 * [--error-class=<class>]
	 * : Filter by error_class fingerprint.
	 *
	 * [--status=<status>]
	 * : Filter by triage status: open, triaged, resolved, ignored.
	 *
	 * [--group]
	 * : Print error_class counts instead of rows.
	 *
	 * [--format=<format>]
	 * : table, json, csv. Default table.
	 *
	 * ## EXAMPLES
	 *
	 *     wp captaincore core-update-runs
	 *     wp captaincore core-update-runs 12 --result=fail --format=json
	 *     wp captaincore core-update-runs 12 --group
	 *
	 * @when after_wp_load
	 */
	public function __invoke( $args = [], $assoc_args = [] ) {
		$format = isset( $assoc_args['format'] ) ? $assoc_args['format'] : 'table';
		$run_id = isset( $args[0] ) ? (int) $args[0] : 0;

		if ( ! $run_id ) {
			global $wpdb;
			$table = $wpdb->prefix . 'captaincore_core_update_runs';
			$rows  = $wpdb->get_results( "SELECT core_update_run_id, created_at, target, version_requested, version_resolved, total, updated_count, skipped_count, failed_count, duration_seconds FROM {$table} ORDER BY core_update_run_id DESC LIMIT 20" );
			\WP_CLI\Utils\format_items( $format, $rows ?: [], [ 'core_update_run_id', 'created_at', 'target', 'version_requested', 'version_resolved', 'total', 'updated_count', 'skipped_count', 'failed_count', 'duration_seconds' ] );
			return;
		}

		$run = ( new CoreUpdateRun( $run_id ) )->get( false );
		if ( ! $run ) {
			\WP_CLI::error( "Run {$run_id} not found." );
		}

		if ( ! empty( $assoc_args['group'] ) ) {
			\WP_CLI\Utils\format_items( $format, $run->groups ?: [], [ 'error_class', 'result', 'n' ] );
			return;
		}

		$filters = [];
		if ( ! empty( $assoc_args['result'] ) ) {
			$filters['result'] = $assoc_args['result'];
		}
		if ( ! empty( $assoc_args['stage'] ) ) {
			$filters['stage'] = $assoc_args['stage'];
		}
		if ( ! empty( $assoc_args['error-class'] ) ) {
			$filters['error_class'] = $assoc_args['error-class'];
		}
		if ( ! empty( $assoc_args['status'] ) ) {
			$filters['status'] = $assoc_args['status'];
		}

		$rows = ( new CoreUpdateRun( $run_id ) )->results( $filters );
		\WP_CLI\Utils\format_items(
			$format,
			$rows ?: [],
			[ 'core_update_result_id', 'site', 'result', 'action', 'stage', 'error_class', 'core_before', 'core_after', 'home_url', 'reason', 'status' ]
		);
	}
}
