<?php

/**
 * The admin-specific functionality of the plugin.
 *
 * @link       https://captaincore.io
 * @since      0.1.0
 *
 * @package    Captaincore
 * @subpackage Captaincore/admin
 */

/**
 * The admin-specific functionality of the plugin.
 *
 * Defines the plugin name, version, and two examples hooks for how to
 * enqueue the admin-specific stylesheet and JavaScript.
 *
 * @package    Captaincore
 * @subpackage Captaincore/admin
 * @author     Austin Ginder
 */
class Captaincore_Admin {

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
	 * @param      string    $plugin_name       The name of this plugin.
	 * @param      string    $version    The version of this plugin.
	 */
	public function __construct( $plugin_name, $version ) {

		$this->plugin_name = $plugin_name;
		$this->version = $version;
		add_action( 'admin_menu', [ $this, 'admin_menu' ] );

	}

	public function admin_menu() {
        if ( current_user_can( 'manage_options' ) ) {
            add_management_page( "CaptainCore", "CaptainCore", "manage_options", "captaincore", array( $this, 'admin_view' ) );
        }
    }

	public function admin_view() {
        require_once plugin_dir_path( __DIR__ ) . '/templates/admin.php';
    }

	/**
	 * Register the stylesheets for the admin area.
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

		wp_enqueue_style( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'css/captaincore-admin.css', [], $this->version, 'all' );

	}

	/**
	 * Register the JavaScript for the admin area.
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

		wp_enqueue_script( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'js/captaincore-admin.js', [ 'jquery' ], $this->version, false );

	}

}
