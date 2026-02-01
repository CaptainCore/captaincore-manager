<?php
/**
 * Scheduled Reports WP-CLI Command
 *
 * Manually trigger scheduled maintenance reports to avoid
 * timeout issues with WordPress cron.
 *
 * @author   Austin Ginder
 */

namespace CaptainCore;

class ScheduledReportsCLI {

	/**
	 * Send scheduled maintenance reports.
	 *
	 * ## OPTIONS
	 *
	 * [--dry-run]
	 * : Show which reports are due without sending them.
	 *
	 * [--all]
	 * : Send all scheduled reports regardless of their next_run time.
	 *
	 * [--id=<id>]
	 * : Send a specific scheduled report by ID.
	 *
	 * ## EXAMPLES
	 *
	 *     wp captaincore scheduled-reports send
	 *     wp captaincore scheduled-reports send --dry-run
	 *     wp captaincore scheduled-reports send --all
	 *     wp captaincore scheduled-reports send --id=5
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 */
	public function send( $args = [], $assoc_args = [] ) {
		$dry_run = isset( $assoc_args['dry-run'] );
		$all     = isset( $assoc_args['all'] );
		$id      = isset( $assoc_args['id'] ) ? (int) $assoc_args['id'] : null;

		// Handle specific report by ID
		if ( $id ) {
			$report = ScheduledReports::get( $id );

			if ( ! $report ) {
				\WP_CLI::error( "Scheduled report with ID {$id} not found." );
				return;
			}

			if ( $dry_run ) {
				\WP_CLI::log( '' );
				\WP_CLI::log( '--- Dry Run Mode ---' );
				\WP_CLI::log( '' );
				self::display_report_info( $report );
				\WP_CLI::log( '' );
				\WP_CLI::success( 'Dry run complete. Report would be sent.' );
				return;
			}

			\WP_CLI::log( sprintf( 'Sending scheduled report #%d...', $id ) );
			$start_time = microtime( true );

			ScheduledReports::send_scheduled_report( $report );

			$elapsed = round( microtime( true ) - $start_time, 2 );
			\WP_CLI::success( sprintf( 'Report #%d sent successfully in %s seconds.', $id, $elapsed ) );
			return;
		}

		// Get all scheduled reports
		$reports = ScheduledReports::all();

		if ( empty( $reports ) ) {
			\WP_CLI::log( 'No scheduled reports found.' );
			return;
		}

		$now = current_time( 'mysql' );

		// Filter to only due reports unless --all flag is set
		if ( ! $all ) {
			$reports = array_filter( $reports, function( $report ) use ( $now ) {
				return $report->next_run <= $now;
			} );
		}

		if ( empty( $reports ) ) {
			\WP_CLI::log( 'No scheduled reports are due at this time.' );
			\WP_CLI::log( 'Use --all to send all reports regardless of schedule, or --list to see all scheduled reports.' );
			return;
		}

		\WP_CLI::log( sprintf( 'Found %d report(s) to process.', count( $reports ) ) );

		if ( $dry_run ) {
			\WP_CLI::log( '' );
			\WP_CLI::log( '--- Dry Run Mode ---' );
			\WP_CLI::log( 'The following reports would be sent:' );
			\WP_CLI::log( '' );

			foreach ( $reports as $report ) {
				self::display_report_info( $report );
				\WP_CLI::log( '' );
			}

			\WP_CLI::success( sprintf( 'Dry run complete. %d report(s) would be sent.', count( $reports ) ) );
			return;
		}

		$progress = \WP_CLI\Utils\make_progress_bar( 'Sending reports', count( $reports ) );
		$success_count = 0;
		$error_count = 0;

		foreach ( $reports as $report ) {
			try {
				\WP_CLI::log( sprintf( 'Sending report #%d to %s...', $report->scheduled_report_id, $report->recipient ) );
				$start_time = microtime( true );

				ScheduledReports::send_scheduled_report( $report );

				$elapsed = round( microtime( true ) - $start_time, 2 );
				\WP_CLI::log( sprintf( '  Completed in %s seconds.', $elapsed ) );
				$success_count++;
			} catch ( \Exception $e ) {
				\WP_CLI::warning( sprintf( 'Failed to send report #%d: %s', $report->scheduled_report_id, $e->getMessage() ) );
				$error_count++;
			}

			$progress->tick();
		}

		$progress->finish();

		if ( $error_count > 0 ) {
			\WP_CLI::warning( sprintf( 'Completed with %d success(es) and %d error(s).', $success_count, $error_count ) );
		} else {
			\WP_CLI::success( sprintf( 'All %d report(s) sent successfully.', $success_count ) );
		}
	}

	/**
	 * List all scheduled reports.
	 *
	 * ## EXAMPLES
	 *
	 *     wp captaincore scheduled-reports list
	 *
	 * @subcommand list
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 */
	public function list_( $args = [], $assoc_args = [] ) {
		$reports = ScheduledReports::all();

		if ( empty( $reports ) ) {
			\WP_CLI::log( 'No scheduled reports found.' );
			return;
		}

		$now = current_time( 'mysql' );

		\WP_CLI::log( sprintf( 'Found %d scheduled report(s):', count( $reports ) ) );
		\WP_CLI::log( '' );

		foreach ( $reports as $report ) {
			$is_due = $report->next_run <= $now ? ' [DUE]' : '';
			self::display_report_info( $report );
			if ( $is_due ) {
				\WP_CLI::log( sprintf( '       Status: DUE' ) );
			}
			\WP_CLI::log( '' );
		}
	}

	/**
	 * Display information about a scheduled report.
	 *
	 * @param object $report The report object.
	 */
	private static function display_report_info( $report ) {
		$site_ids = json_decode( $report->site_ids, true );
		$site_count = is_array( $site_ids ) ? count( $site_ids ) : 0;
		$date_range = ScheduledReports::get_date_range( $report->interval );

		\WP_CLI::log( sprintf( '  [#%d] %s report', $report->scheduled_report_id, ucfirst( $report->interval ) ) );
		\WP_CLI::log( sprintf( '       Recipient: %s', $report->recipient ) );
		\WP_CLI::log( sprintf( '       Sites: %d', $site_count ) );
		\WP_CLI::log( sprintf( '       Date range: %s to %s', $date_range['start'], $date_range['end'] ) );
		\WP_CLI::log( sprintf( '       Next run: %s', $report->next_run ) );
		\WP_CLI::log( sprintf( '       Last run: %s', $report->last_run ?: 'Never' ) );
	}

}
