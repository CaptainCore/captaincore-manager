<?php

/**
 * The public-facing functionality of the plugin.
 *
 * @link       https://anchor.host
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
 * @author     Anchor Hosting <support@anchor.host>
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
	 * @param      string    $plugin_name       The name of the plugin.
	 * @param      string    $version    The version of this plugin.
	 */
	public function __construct( $plugin_name, $version ) {

		$this->plugin_name = $plugin_name;
		$this->version = $version;

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

		wp_enqueue_style( 'google-material-icons', "https://fonts.googleapis.com/icon?family=Material+Icons", array(), '2017-09-03' );
		wp_enqueue_style( 'materialize', plugin_dir_url( __FILE__ ) . "css/materialize.min.css", array(), '2017-09-08' );
		wp_enqueue_style( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'css/captaincore-public.2018-06-04.css', array(), $this->version, 'all' );
		wp_enqueue_script( 'font-awesome', "https://use.fontawesome.com/releases/v5.0.6/js/all.js", array() );
		wp_enqueue_script( 'materialize', plugin_dir_url( __FILE__ ) . 'js/materialize.min.js', array(), '2016-12-30', true );

		if ( isset($wp_query->query_vars["pagename"]) && $wp_query->query_vars["pagename"] == "contact" ) {
			 wp_deregister_style( 'materialize' );
		 }
		if ( isset($wp_query->query["configs"]) || isset($wp_query->query["edit-address"]) ) {
			 wp_deregister_style( 'materialize' );
		}

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

		wp_enqueue_script( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'js/captaincore-public.2018-06-03.js', array( 'jquery' ), $this->version, false );

	}

}
