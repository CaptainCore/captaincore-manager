<?php

namespace CaptainCore;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Restart a Kinsta environment's PHP engine by hand.
 *
 * The uptime monitor already restarts a saturated pool on its own (see
 * MonitorRecoveries); this is the path for an operator who has diagnosed a
 * site and wants a restart now. Each restart is noted on the site's timeline
 * as a private entry.
 */
class RestartPhpCLI {

	/**
	 * Restart PHP on a Kinsta environment and wait for Kinsta to finish.
	 *
	 * ## OPTIONS
	 *
	 * <site>
	 * : Site ID or domain name.
	 *
	 * [--environment=<environment>]
	 * : Environment to restart.
	 * ---
	 * default: production
	 * options:
	 *   - production
	 *   - staging
	 * ---
	 *
	 * [--reason=<text>]
	 * : Why PHP was restarted. Added to the timeline entry.
	 *
	 * [--[no-]wait]
	 * : Wait up to two minutes for Kinsta to finish the restart. Default true.
	 *
	 * ## EXAMPLES
	 *
	 *     wp captaincore restart-php example.com
	 *     wp captaincore restart-php 123 --environment=staging --reason="Pool stuck after a deploy"
	 *
	 * @when after_wp_load
	 */
	public function __invoke( $args, $assoc_args ) {
		$site = self::resolve_site( $args[0] );
		if ( ! $site ) {
			\WP_CLI::error( "No single site matches '{$args[0]}'." );
		}
		if ( $site->provider !== 'kinsta' ) {
			\WP_CLI::error( "{$site->name} is not on Kinsta (provider: " . ( $site->provider ?: 'none' ) . '), so PHP cannot be restarted through the API.' );
		}

		$environment = \WP_CLI\Utils\get_flag_value( $assoc_args, 'environment', 'production' );
		$reason      = trim( (string) \WP_CLI\Utils\get_flag_value( $assoc_args, 'reason', '' ) );
		$restart     = Providers\Kinsta::restart_php( (int) $site->site_id, $environment );
		if ( is_wp_error( $restart ) ) {
			\WP_CLI::error( $restart->get_error_message() );
		}
		\WP_CLI::log( "Kinsta accepted the restart for {$site->name} ({$environment}), operation {$restart->operation_id}." );

		// Kinsta reports 202 while running, 200 when done and 500 on failure.
		$status = '202';
		if ( \WP_CLI\Utils\get_flag_value( $assoc_args, 'wait', true ) ) {
			for ( $i = 0; $i < 24 && $status === '202'; $i++ ) {
				sleep( 5 );
				$status = Providers\Kinsta::operation_status( (int) $site->site_id, $restart->operation_id );
			}
		}

		$outcomes = [
			'200' => 'PHP restarted',
			'500' => 'PHP restart failed at Kinsta',
			'202' => 'PHP restart requested',
		];
		$outcome = $outcomes[ $status ] ?? 'PHP restart requested, status unknown';
		ProcessLog::insert( trim( "{$outcome} from WP-CLI ({$environment}). {$reason}" ), (int) $site->site_id, 0 );

		if ( $status === '200' ) {
			\WP_CLI::success( 'PHP restarted.' );
		} elseif ( $status === '500' ) {
			\WP_CLI::error( 'Kinsta reported that the restart failed.' );
		} elseif ( \WP_CLI\Utils\get_flag_value( $assoc_args, 'wait', true ) ) {
			\WP_CLI::warning( $status === '202' ? 'The restart was still running after two minutes.' : 'Could not read the operation status from Kinsta.' );
		}
	}

	/**
	 * A site by numeric ID, or by exact site slug or name when only one matches.
	 *
	 * @return object|null
	 */
	private static function resolve_site( $identifier ) {
		if ( is_numeric( $identifier ) ) {
			return Sites::get( (int) $identifier );
		}
		foreach ( [ 'site', 'name' ] as $column ) {
			$matches = ( new Sites )->where( [ $column => $identifier ] );
			if ( count( $matches ) === 1 ) {
				return $matches[0];
			}
		}
		return null;
	}
}
