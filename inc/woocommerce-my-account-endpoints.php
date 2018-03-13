<?php
class Anchor_My_Account_Config_Endpoint {

	/**
	 * Custom endpoint name.
	 *
	 * @var string
	 */
	public static $endpoint = 'configs';

	/**
	 * Plugin actions.
	 */
	public function __construct() {

		// Actions used to insert a new endpoint in the WordPress.
		add_action( 'init', array( $this, 'add_endpoints' ) );
		add_filter( 'query_vars', array( $this, 'add_query_vars' ), 0 );

		// Change the My Accout page title.
		add_filter( 'the_title', array( $this, 'endpoint_title' ) );

		// Insering your new tab/page into the My Account page.
		add_filter( 'woocommerce_account_menu_items', array( $this, 'new_menu_items' ) );
		add_action( 'woocommerce_account_' . self::$endpoint .  '_endpoint', array( $this, 'endpoint_content' ) );

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
	 * Set endpoint title.
	 *
	 * @param string $title
	 * @return string
	 */
	public function endpoint_title( $title ) {
		global $wp_query;

		$is_endpoint = isset( $wp_query->query_vars[ self::$endpoint ] );

		if ( $is_endpoint && ! is_admin() && is_main_query() && in_the_loop() && is_account_page() ) {
			// New page title.
			$title = __( 'Configurations', 'woocommerce' );

			remove_filter( 'the_title', array( $this, 'endpoint_title' ) );
		}

		return $title;
	}

	/**
	 * Insert the new endpoint into the My Account menu.
	 *
	 * @param array $items
	 * @return array
	 */
	public function new_menu_items( $items ) {

		// Insert your custom endpoint.
		$items[ self::$endpoint ] = __( 'Configurations', 'woocommerce' );

		return $items;
	}

	/**
	 * Endpoint HTML content.
	 */
	public function endpoint_content() {
		acf_form_head();
		$user_id = get_current_user_id();


		$partner = get_field('partner', 'user_'. get_current_user_id() );
		if ($partner) {
			foreach ($partner as $partner_id) {
				echo "<h3>Account: ". get_the_title($partner_id) ."</h3>";
		$options = array(

			/* (string) Unique identifier for the form. Defaults to 'acf-form' */
			'id' => 'acf-form',

			/* (int|string) The post ID to load data from and save data to. Defaults to the current post ID.
			Can also be set to 'new_post' to create a new post on submit */
			'post_id' => $partner_id,

			/* (array) An array of post data used to create a post. See wp_insert_post for available parameters.
			The above 'post_id' setting must contain a value of 'new_post' */
			'new_post' => false,

			/* (array) An array of field group IDs/keys to override the fields displayed in this form */
			'field_groups' => array(1987),

			/* (array) An array of field IDs/keys to override the fields displayed in this form */
			'fields' => array("field_590e67c47a3f4", "field_57c34cd07185e", "field_5879880d78843"),

			/* (boolean) Whether or not to show the post title text field. Defaults to false */
			'post_title' => false,

			/* (boolean) Whether or not to show the post content editor field. Defaults to false */
			'post_content' => false,

			/* (boolean) Whether or not to create a form element. Useful when a adding to an existing form. Defaults to true */
			'form' => true,

			/* (array) An array or HTML attributes for the form element */
			'form_attributes' => array(),

			/* (string) The URL to be redirected to after the form is submit. Defaults to the current URL with a GET parameter '?updated=true'.
			A special placeholder '%post_url%' will be converted to post's permalink (handy if creating a new post) */
			'return' => '',

			/* (string) Extra HTML to add before the fields */
			'html_before_fields' => '',

			/* (string) Extra HTML to add after the fields */
			'html_after_fields' => '',

			/* (string) The text displayed on the submit button */
			'submit_value' => __("Update", 'acf'),

			/* (string) A message displayed above the form after being redirected. Can also be set to false for no message */
			'updated_message' => __("Post updated", 'acf'),

			/* (string) Determines where field labels are places in relation to fields. Defaults to 'top'.
			Choices of 'top' (Above fields) or 'left' (Beside fields) */
			'label_placement' => 'top',

			/* (string) Determines where field instructions are places in relation to fields. Defaults to 'label'.
			Choices of 'label' (Below labels) or 'field' (Below fields) */
			'instruction_placement' => 'label',

			/* (string) Determines element used to wrap a field. Defaults to 'div'
			Choices of 'div', 'tr', 'td', 'ul', 'ol', 'dl' */
			'field_el' => 'div',

			/* (string) Whether to use the WP uploader or a basic input for image and file fields. Defaults to 'wp'
			Choices of 'wp' or 'basic'. Added in v5.2.4 */
			'uploader' => 'wp',

			/* (boolean) Whether to include a hidden input field to capture non human form submission. Defaults to true. Added in v5.3.4 */
			'honeypot' => true

		);
		acf_form( $options );
		}
		}
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
register_activation_hook( __FILE__, array( 'Anchor_My_Account_Config_Endpoint', 'install' ) );

class Anchor_My_Account_Logs_Endpoint {

	/**
	 * Custom endpoint name.
	 *
	 * @var string
	 */
	public static $endpoint = 'logs';

	/**
	 * Plugin actions.
	 */
	public function __construct() {

		// Actions used to insert a new endpoint in the WordPress.
		add_action( 'init', array( $this, 'add_endpoints' ) );
		add_filter( 'query_vars', array( $this, 'add_query_vars' ), 0 );

		// Change the My Accout page title.
		add_filter( 'the_title', array( $this, 'endpoint_title' ) );

		// Insering your new tab/page into the My Account page.
		add_filter( 'woocommerce_account_menu_items', array( $this, 'new_menu_items' ) );
		add_action( 'woocommerce_account_' . self::$endpoint .  '_endpoint', array( $this, 'endpoint_content' ) );

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
	 * Set endpoint title.
	 *
	 * @param string $title
	 * @return string
	 */
	public function endpoint_title( $title ) {
		global $wp_query;

		$is_endpoint = isset( $wp_query->query_vars[ self::$endpoint ] );

		if ( $is_endpoint && ! is_admin() && is_main_query() && in_the_loop() && is_account_page() ) {
			// New page title.
			$title = __( 'Website Logs', 'woocommerce' );

			remove_filter( 'the_title', array( $this, 'endpoint_title' ) );
		}

		return $title;
	}

	/**
	 * Insert the new endpoint into the My Account menu.
	 *
	 * @param array $items
	 * @return array
	 */
	public function new_menu_items( $items ) {

		// Insert your custom endpoint.
		$items[ self::$endpoint ] = __( 'Website Logs', 'woocommerce' );

		return $items;
	}

	/**
	 * Endpoint HTML content.
	 */
	public function endpoint_content() {
		wc_get_template( '../woocommerce/myaccount/website-log-endpoint.php' );
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
register_activation_hook( __FILE__, array( 'Anchor_My_Account_Logs_Endpoint', 'install' ) );

class Anchor_My_Account_Handbook_Endpoint {

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

		// Actions used to insert a new endpoint in the WordPress.
		add_action( 'init', array( $this, 'add_endpoints' ) );
		add_filter( 'query_vars', array( $this, 'add_query_vars' ), 0 );

		// Insering your new tab/page into the My Account page.
		add_filter( 'woocommerce_account_menu_items', array( $this, 'new_menu_items' ) );
		add_action( 'woocommerce_account_' . self::$endpoint .  '_endpoint', array( $this, 'endpoint_content' ) );

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
		$items[ self::$endpoint ] = __( 'Handbook', 'woocommerce' );

		return $items;
	}

	/**
	 * Endpoint HTML content.
	 */
	public function endpoint_content() {

		echo "";

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
register_activation_hook( __FILE__, array( 'Anchor_My_Account_Handbook_Endpoint', 'install' ) );

class Anchor_My_Account_Dns_Endpoint {

	/**
	 * Custom endpoint name.
	 *
	 * @var string
	 */
	public static $endpoint = 'anchor-dns';

	/**
	 * Plugin actions.
	 */
	public function __construct() {
		// Actions used to insert a new endpoint in the WordPress.
		add_action( 'init', array( $this, 'add_endpoints' ) );
		add_filter( 'query_vars', array( $this, 'add_query_vars' ), 0 );

		// Change the My Accout page title.
		add_filter( 'the_title', array( $this, 'endpoint_title' ) );

		// Insering your new tab/page into the My Account page.
		add_filter( 'woocommerce_account_menu_items', array( $this, 'new_menu_items' ) );
		add_action( 'woocommerce_account_' . self::$endpoint .  '_endpoint', array( $this, 'endpoint_content' ) );
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
	 * Set endpoint title.
	 *
	 * @param string $title
	 * @return string
	 */
	public function endpoint_title( $title ) {
		global $wp_query;

		$is_endpoint = isset( $wp_query->query_vars[ self::$endpoint ] );

		if ( $is_endpoint && ! is_admin() && is_main_query() && in_the_loop() && is_account_page() ) {
			// New page title.
			$title = __( 'Anchor DNS', 'woocommerce' );

			remove_filter( 'the_title', array( $this, 'endpoint_title' ) );
		}

		return $title;
	}

	/**
	 * Insert the new endpoint into the My Account menu.
	 *
	 * @param array $items
	 * @return array
	 */
	public function new_menu_items( $items ) {

		// Insert your custom endpoint.
		$items[ self::$endpoint ] = __( 'Anchor DNS', 'woocommerce' );

		return $items;
	}

	/**
	 * Endpoint HTML content.
	 */
	public function endpoint_content() {
		wc_get_template( '../woocommerce/myaccount/dns-endpoint.php' );
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
register_activation_hook( __FILE__, array( 'Anchor_My_Account_Dns_Endpoint', 'install' ) );

// Load classes
new Anchor_My_Account_Config_Endpoint();
new Anchor_My_Account_Dns_Endpoint();
new Anchor_My_Account_Logs_Endpoint();
new Anchor_My_Account_Handbook_Endpoint();
