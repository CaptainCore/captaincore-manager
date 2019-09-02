<?php

/**
 * The public-facing functionality of the plugin.
 *
 * @link       https://captaincore.io
 * @since      0.1.0
 *
 * @package    Captaincore
 * @subpackage Captaincore/public
 */

/**
 * The public-facing functionality of the plugin.
 *
 * Defines the plugin name, version, and two examples hooks for how to
 * enqueue the public-facing stylesheet and JavaScript.
 *
 * @package    Captaincore
 * @subpackage Captaincore/public
 * @author     Austin Ginder
 */
class Captaincore_Public {

	/**
	 * The ID of this plugin.
	 *
	 * @since    0.1.0
	 * @access   private
	 * @var      string    $plugin_name    The ID of this plugin.
	 */
	private $plugin_name;

	/**
	 * The version of this plugin.
	 *
	 * @since    0.1.0
	 * @access   private
	 * @var      string    $version    The current version of this plugin.
	 */
	private $version;

	/**
	 * Initialize the class and set its properties.
	 *
	 * @since    0.1.0
	 * @param      string $plugin_name       The name of the plugin.
	 * @param      string $version    The version of this plugin.
	 */
	public function __construct( $plugin_name, $version ) {

		$this->plugin_name = $plugin_name;
		$this->version     = $version;

	}

	/**
	 * Register the stylesheets for the public-facing side of the site.
	 *
	 * @since    0.1.0
	 */
	public function enqueue_styles() {

		/**
		 * This function is provided for demonstration purposes only.
		 *
		 * An instance of this class should be passed to the run() function
		 * defined in Captaincore_Loader as all of the hooks are defined
		 * in that particular class.
		 *
		 * The Captaincore_Loader will then create the relationship
		 * between the defined hooks and the functions defined in this
		 * class.
		 */

		 global $wp_query;

	}

	/**
	 * Register the JavaScript for the public-facing side of the site.
	 *
	 * @since    0.1.0
	 */
	public function enqueue_scripts() {

		/**
		 * This function is provided for demonstration purposes only.
		 *
		 * An instance of this class should be passed to the run() function
		 * defined in Captaincore_Loader as all of the hooks are defined
		 * in that particular class.
		 *
		 * The Captaincore_Loader will then create the relationship
		 * between the defined hooks and the functions defined in this
		 * class.
		 */
		if ( is_user_logged_in() ) {
			wp_localize_script( 'wp-api', 'wpApiSettings', array(
			    'root' => esc_url_raw( rest_url() ),
			    'nonce' => wp_create_nonce( 'wp_rest' )
			) );
			wp_enqueue_script( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'js/captaincore-public.2018-06-30.js', array( 'jquery', 'wp-api' ), $this->version, false );
		} else {
			wp_enqueue_script( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'js/captaincore-public.2018-06-30.js', array( 'jquery' ), $this->version, false );
		}

	}

}
