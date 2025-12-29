<?php

/**
 * Fired during plugin activation
 *
 * @link       https://captaincore.io
 * @since      0.1.0
 *
 * @package    Captaincore
 * @subpackage Captaincore/includes
 */

/**
 * Fired during plugin activation.
 *
 * This class defines all code necessary to run during the plugin's activation.
 *
 * @since      0.1.0
 * @package    Captaincore
 * @subpackage Captaincore/includes
 * @author     Austin Ginder
 */
class Captaincore_Activator {

	/**
	 * Short Description. (use period)
	 *
	 * Long Description.
	 *
	 * @since    0.1.0
	 */
	public static function activate() {
		wp_schedule_event( time(), 'hourly', 'captaincore_cron' );
		CaptainCore\DB::upgrade();
		// Add the rewrite rules first
        captaincore_rewrite(); 
        
        // Then flush them
		flush_rewrite_rules(); 
		function captaincore_activation_redirect() {
			if( ! defined( 'WP_CLI' ) ) {
				wp_safe_redirect( '/account' );
				exit;
			}
		}
		add_action( 'activated_plugin', 'captaincore_activation_redirect' );
	}

	

}
