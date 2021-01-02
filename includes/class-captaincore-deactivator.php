<?php

/**
 * Fired during plugin deactivation
 *
 * @link       https://captaincore.io
 * @since      0.1.0
 *
 * @package    Captaincore
 * @subpackage Captaincore/includes
 */

/**
 * Fired during plugin deactivation.
 *
 * This class defines all code necessary to run during the plugin's deactivation.
 *
 * @since      0.1.0
 * @package    Captaincore
 * @subpackage Captaincore/includes
 * @author     Austin Ginder
 */
class Captaincore_Deactivator {

	/**
	 * Short Description. (use period)
	 *
	 * Long Description.
	 *
	 * @since    0.1.0
	 */
	public static function deactivate() {
		wp_clear_scheduled_hook( 'captaincore_cron' );
	}

}
