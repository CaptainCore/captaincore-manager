<?php

/**
 * Define the internationalization functionality
 *
 * Loads and defines the internationalization files for this plugin
 * so that it is ready for translation.
 *
 * @link       https://anchor.host
 * @since      0.1.0
 *
 * @package    Captaincore
 * @subpackage Captaincore/includes
 */

/**
 * Define the internationalization functionality.
 *
 * Loads and defines the internationalization files for this plugin
 * so that it is ready for translation.
 *
 * @since      0.1.0
 * @package    Captaincore
 * @subpackage Captaincore/includes
 * @author     Anchor Hosting <support@anchor.host>
 */
class Captaincore_i18n {


	/**
	 * Load the plugin text domain for translation.
	 *
	 * @since    0.1.0
	 */
	public function load_plugin_textdomain() {

		load_plugin_textdomain(
			'captaincore',
			false,
			dirname( dirname( plugin_basename( __FILE__ ) ) ) . '/languages/'
		);

	}



}
