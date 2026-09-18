<?php

namespace CaptainCore;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class OrphanRowsCLI {

	/**
	 * Report or remove rows whose site no longer exists.
	 *
	 * A hard site delete used to remove only the site row, so environments,
	 * account links, captures, session snapshots and per-environment scripts
	 * piled up behind deleted sites. This walks the site_ids that appear in
	 * those tables but not in captaincore_sites and removes their rows through
	 * Site::delete_rows_for_site(), the same cascade a delete runs now.
	 * Snapshots, audits and process logs are left alone on purpose (see
	 * Site::delete).
	 *
	 * ## OPTIONS
	 *
	 * [--delete]
	 * : Remove the rows. Without it the command only reports counts.
	 *
	 * [--limit=<n>]
	 * : Maximum number of orphaned site_ids to process in this run. Default 200.
	 *
	 * ## EXAMPLES
	 *
	 *     wp captaincore orphan-rows
	 *     wp captaincore orphan-rows --delete --limit=100
	 *
	 * @when after_wp_load
	 */
	public function __invoke( $args = [], $assoc_args = [] ) {
		global $wpdb;

		$delete = isset( $assoc_args['delete'] );
		$limit  = max( 1, intval( $assoc_args['limit'] ?? 200 ) );
		$p      = $wpdb->prefix;
		$sites  = "{$p}captaincore_sites";

		// Tables keyed on site_id. Scripts hang off environments and are
		// reached through them inside the cascade.
		$tables = [ 'environments', 'account_site', 'captures', 'session_snapshots' ];

		\WP_CLI::log( 'Rows with no site behind them:' );
		$orphan_ids = [];
		foreach ( $tables as $table ) {
			$t     = "{$p}captaincore_{$table}";
			$count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$t} c LEFT JOIN {$sites} s ON s.site_id = c.site_id WHERE s.site_id IS NULL" );
			\WP_CLI::log( sprintf( '  %-20s %s', $table, number_format( $count ) ) );
			$ids = $wpdb->get_col( "SELECT DISTINCT c.site_id FROM {$t} c LEFT JOIN {$sites} s ON s.site_id = c.site_id WHERE s.site_id IS NULL" );
			foreach ( $ids as $id ) {
				$orphan_ids[ intval( $id ) ] = true;
			}
		}
		$scripts = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$p}captaincore_scripts c LEFT JOIN {$p}captaincore_environments e ON e.environment_id = c.environment_id WHERE e.environment_id IS NULL" );
		\WP_CLI::log( sprintf( '  %-20s %s (via missing environment)', 'scripts', number_format( $scripts ) ) );

		unset( $orphan_ids[0] );
		ksort( $orphan_ids );
		$total = count( $orphan_ids );
		\WP_CLI::log( sprintf( '%s deleted site_id(s) still referenced.', number_format( $total ) ) );

		if ( ! $delete ) {
			\WP_CLI::log( 'Dry run. Re-run with --delete to remove them (--limit caps site_ids per run).' );
			return;
		}
		if ( $total === 0 ) {
			\WP_CLI::success( 'Nothing to remove.' );
			return;
		}

		$batch   = array_slice( array_keys( $orphan_ids ), 0, $limit );
		$totals  = [];
		$done    = 0;
		foreach ( $batch as $site_id ) {
			// Belt and braces: never touch a site_id that has a site row.
			if ( $wpdb->get_var( $wpdb->prepare( "SELECT site_id FROM {$sites} WHERE site_id = %d", $site_id ) ) ) {
				continue;
			}
			$removed = Site::delete_rows_for_site( $site_id );
			foreach ( $removed as $table => $n ) {
				$totals[ $table ] = ( $totals[ $table ] ?? 0 ) + $n;
			}
			$done++;
		}

		foreach ( $totals as $table => $n ) {
			\WP_CLI::log( sprintf( '  removed %-20s %s', $table, number_format( $n ) ) );
		}
		$left = $total - $done;
		\WP_CLI::success( sprintf( 'Cleaned %d site_id(s); %d left. %s', $done, $left, $left > 0 ? 'Run again to continue.' : '' ) );

		// Scripts whose environment vanished before this sweeper existed.
		if ( $scripts > 0 ) {
			$n = (int) $wpdb->query( "DELETE c FROM {$p}captaincore_scripts c LEFT JOIN {$p}captaincore_environments e ON e.environment_id = c.environment_id WHERE e.environment_id IS NULL" );
			\WP_CLI::log( sprintf( '  removed %-20s %s (orphaned by environment)', 'scripts', number_format( $n ) ) );
		}
	}
}
