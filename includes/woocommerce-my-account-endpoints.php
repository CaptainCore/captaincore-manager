<?php
class CaptainCore_My_Account_Cookbook_Endpoint {

	/**
	 * Custom endpoint name.
	 *
	 * @var string
	 */
	public static $endpoint = 'cookbook';

	/**
	 * Plugin actions.
	 */
	public function __construct() {
		// Inserting your new tab/page into the My Account page.
		add_filter( 'woocommerce_account_menu_items', array( $this, 'new_menu_items' ) );
		add_filter( 'woocommerce_get_endpoint_url', array( $this, 'maybe_redirect_endpoint' ), 10, 4 );
	}

	/**
	 * Insert the new endpoint into the My Account menu.
	 *
	 * @param array $items
	 * @return array
	 */
	public function new_menu_items( $items ) {

		// Insert your custom endpoint.
		$items[ self::$endpoint ] = __( 'Cookbook', 'woocommerce' );

		return $items;
	}

	/**
	 * Modify endpoint for custom URL.
	 *
	 * @return string
	 */
	public function maybe_redirect_endpoint ($url, $endpoint, $value, $permalink) {
		if( $endpoint == 'cookbook')
			$url = get_permalink( get_option('woocommerce_myaccount_page_id') ) . "sites#cookbook";
		return $url;
	}

}

class CaptainCore_My_Account_Handbook_Endpoint {

	/**
	 * Custom endpoint name.
	 *
	 * @var string
	 */
	public static $endpoint = 'handbook';

	/**
	 * Plugin actions.
	 */
	public function __construct() {
		// Inserting your new tab/page into the My Account page.
		add_filter( 'woocommerce_account_menu_items', array( $this, 'new_menu_items' ) );
		add_filter( 'woocommerce_get_endpoint_url', array( $this, 'maybe_redirect_endpoint' ), 10, 4 );
	}

	/**
	 * Insert the new endpoint into the My Account menu.
	 *
	 * @param array $items
	 * @return array
	 */
	public function new_menu_items( $items ) {

		// Insert your custom endpoint.
		$items[ self::$endpoint ] = __( 'Handbook', 'woocommerce' );

		return $items;
	}

	/**
	 * Modify endpoint for custom URL.
	 *
	 * @return string
	 */
	public function maybe_redirect_endpoint ($url, $endpoint, $value, $permalink) {
		if( $endpoint == 'handbook')
			$url = get_permalink( get_option('woocommerce_myaccount_page_id') ) . "sites#handbook";
		return $url;
	}

}

class CaptainCore_My_Account_Sites_Endpoint {

	/**
	 * Custom endpoint name.
	 *
	 * @var string
	 */
	public static $endpoint = 'sites';

	/**
	 * Plugin actions.
	 */
	public function __construct() {
		// Actions used to insert a new endpoint in the WordPress.
		add_action( 'init', array( $this, 'add_endpoints' ) );
		add_filter( 'query_vars', array( $this, 'add_query_vars' ), 0 );

		// Change the My Account page title.
		//add_filter( 'the_title', array( $this, 'endpoint_title' ) );

		// Inserting your new tab/page into the My Account page.
		add_filter( 'woocommerce_account_menu_items', array( $this, 'new_menu_items' ) );
		add_action( 'woocommerce_account_' . self::$endpoint . '_endpoint', array( $this, 'endpoint_content' ) );

	}

	/**
	 * Register new endpoint to use inside My Account page.
	 *
	 * @see https://developer.wordpress.org/reference/functions/add_rewrite_endpoint/
	 */
	public function add_endpoints() {
		add_rewrite_endpoint( self::$endpoint, EP_ROOT | EP_PAGES );
	}

	/**
	 * Add new query var.
	 *
	 * @param array $vars
	 * @return array
	 */
	public function add_query_vars( $vars ) {
		$vars[] = self::$endpoint;

		return $vars;
	}

	/**
	 * Insert the new endpoint into the My Account menu.
	 *
	 * @param array $items
	 * @return array
	 */
	public function new_menu_items( $items ) {

		// Insert your custom endpoint.
		$items[ self::$endpoint ] = __( 'Sites', 'woocommerce' );

		return $items;
	}

	/**
	 * Endpoint HTML content.
	 */
	public function endpoint_content() {
		wc_get_template( '../woocommerce/myaccount/sites-endpoint.php' );
	}

	/**
	 * Plugin install action.
	 * Flush rewrite rules to make our custom endpoint available.
	 */
	public static function install() {
		flush_rewrite_rules();
	}
}

// Flush rewrite rules on plugin activation.
register_activation_hook( __FILE__, array( 'CaptainCore_My_Account_Sites_Endpoint', 'install' ) );

// Load classes
new CaptainCore_My_Account_Sites_Endpoint();
new CaptainCore_My_Account_Cookbook_Endpoint();
new CaptainCore_My_Account_Handbook_Endpoint();
