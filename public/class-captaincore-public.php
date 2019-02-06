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

		wp_enqueue_style( 'google-material-icons', 'https://fonts.googleapis.com/icon?family=Material+Icons', array(), '2017-09-03' );
		wp_enqueue_style( 'font-awesome', "https://use.fontawesome.com/releases/v5.0.13/css/all.css", array() );
		wp_enqueue_style( 'dashicons' );

		$materialize_needed = false;

		if ( is_user_logged_in() ) {

			// Pages: DNS, Licenses, Websites, Processes
			if ( isset( $wp_query->query['dns'] ) || isset( $wp_query->query['licenses'] ) || isset( $wp_query->query['websites'] ) || isset( $wp_query->query['captcore_process'] ) ) {
				$materialize_needed = true;
			}
			// Page: my-account dashboard when logged in
			if ( isset( $wp_query->query['pagename'] ) and $wp_query->query['pagename'] == "my-account" and isset( $wp_query->query['page']) ) {
				$materialize_needed = true;
			}
		}

		if ( $materialize_needed ) {
			wp_enqueue_style( 'materialize', plugin_dir_url( __FILE__ ) . 'css/materialize.min.css', array(), '2017-09-08' );
			wp_enqueue_script( 'materialize', plugin_dir_url( __FILE__ ) . 'js/materialize.min.js', array( 'jquery' ), '2016-12-30', false );
		}

		wp_enqueue_style( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'css/captaincore-public.2019-02-06.css', array(), $this->version, 'all' );


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
