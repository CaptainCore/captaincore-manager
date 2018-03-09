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
		$user = wp_get_current_user();
		$role_check = in_array( 'partner', $user->roles ) + in_array( 'administrator', $user->roles );

		if ($role_check) {
			// Actions used to insert a new endpoint in the WordPress.
			add_action( 'init', array( $this, 'add_endpoints' ) );
			add_filter( 'query_vars', array( $this, 'add_query_vars' ), 0 );

			// Change the My Accout page title.
			add_filter( 'the_title', array( $this, 'endpoint_title' ) );

			// Insering your new tab/page into the My Account page.
			add_filter( 'woocommerce_account_menu_items', array( $this, 'new_menu_items' ) );
			add_action( 'woocommerce_account_' . self::$endpoint .  '_endpoint', array( $this, 'endpoint_content' ) );
		}
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
		$user = wp_get_current_user();
		$role_check = in_array( 'partner', $user->roles ) + in_array( 'administrator', $user->roles );

		if ($role_check) {
			// Actions used to insert a new endpoint in the WordPress.
			add_action( 'init', array( $this, 'add_endpoints' ) );
			add_filter( 'query_vars', array( $this, 'add_query_vars' ), 0 );

			// Change the My Accout page title.
			add_filter( 'the_title', array( $this, 'endpoint_title' ) );

			// Insering your new tab/page into the My Account page.
			add_filter( 'woocommerce_account_menu_items', array( $this, 'new_menu_items' ) );
			add_action( 'woocommerce_account_' . self::$endpoint .  '_endpoint', array( $this, 'endpoint_content' ) );
		}
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
		if ( class_exists( 'Jetpack' ) && Jetpack::is_module_active( 'markdown' ) ) :
			jetpack_require_lib( 'markdown' );
		endif;
		?>

		<div class="col s12">
          <?php /* <div class="card">
            <div class="card-content row">
						<div class="col s12">
             <p>Yearly activity report</p>
					 </div>
						</div>
					</div>*/ ?>
						 <?php
						 $user = wp_get_current_user();
						 $role_check = in_array( 'subscriber', $user->roles ) + in_array( 'customer', $user->roles ) + in_array( 'partner', $user->roles ) + in_array( 'administrator', $user->roles) + in_array( 'editor', $user->roles );
						 $partner = get_field('partner', 'user_'. get_current_user_id());
						 if ($partner and $role_check) {

						 	// Loop through each partner assigned to current user
						 	foreach ($partner as $partner_id) {

						 		// Load websites assigned to partner
						 		$arguments = array(
						 			'post_type' 			=> 'captcore_website',
						 			'posts_per_page'	=> '-1',
									'fields'         => 'ids',
						 			'order'						=> 'asc',
						 			'orderby'					=> 'title',
						 			'meta_query'			=> array(
						 				'relation'			=> 'AND',
						 				array(
						 					'key' => 'partner',
						 					'value' => '"' . $partner_id . '"',
						 					'compare' => 'LIKE'
						 				),
						 				array(
						 					'key'	  	=> 'status',
						 					'value'	  	=> 'closed',
						 					'compare' 	=> '!=',
						 				),
						 			)
						 		);

						 	// Loads websites
						 	$websites = get_posts( $arguments );

						 	if ( count( $websites ) == 0 ) {

						 		// Load websites assigned to partner
						 		$websites = get_posts(array(
						 			'post_type' 			=> 'captcore_website',
						 			'posts_per_page'	=> '-1',
									'fields'					=> 'ids',
						 			'order'						=> 'asc',
						 			'orderby'					=> 'title',
						 			'meta_query'			=> array(
						 				'relation'			=> 'AND',
						 					array(
						 						'key' => 'customer', // name of custom field
						 						'value' => '"' . $partner_id . '"', // matches exaclty "123", not just 123. This prevents a match for "1234"
						 						'compare' => 'LIKE'
						 					),
						 					array(
						 						'key'	  	=> 'status',
						 						'value'	  	=> 'closed',
						 						'compare' 	=> '!=',
						 					),
						 			)
						 		));

						 	}

						 	if( $websites ): ?>

						 		<h3>Account: <?php echo get_the_title($partner_id); ?> <small>(<?php echo count($websites);?> sites)</small></h3>

						 			<div class="website-group">
						 			<?php

									$pattern = '("' . implode('"|"', $websites ) . '")';

									$arguments = array(
										'post_type'      => 'captcore_processlog',
										'posts_per_page' => '-1',
										'meta_query'	=> array(
											array(
												'key'	 	=> 'website',
												'value'	  	=> $pattern,
												'compare' 	=> 'REGEXP',
											),
									));

									$process_logs = get_posts($arguments);
									$year = '';
									foreach ($process_logs as $process_log) {

										// filter only for sites within websites
										$website = get_field("website", $process_log->ID );
										//print_r($website);
										//$website = array_intersect_assoc( $website, $websites);

										$description = get_field("description", $process_log->ID );
										$description = WPCom_Markdown::get_instance()->transform(
											$description, array(
												'id'      => false,
												'unslash' => false,
											)
										);
										$process = get_field("process", $process_log->ID );

										$post_year = date( 'Y', strtotime( $process_log->post_date ) );

										if ( $year != $post_year ) {
											$currentyear = true;
											$year        = $post_year;
											if ( $year <> '' ) {
												echo '</ul>';
											}
											echo '<h3>' . $year . '</h3>';
											echo "<ul class='changelog'>";
										} ?>
										<li>
											<div class="changelog-item">
												<div class="title"><?php foreach($website as $website_id) { if ( in_array($website_id, $websites)) echo get_the_title($website_id) ." "; } ?> - <?php echo get_the_title( $process[0] ); ?></div>
												<?php
												if ( $description ) { ?>
											<div class="content show"><i class="fas fa-sticky-note"></i> <?php echo $description; ?></div><?php } ?>
											<div class="author"><i class="far fa-user"></i> <?php echo get_the_author( $process_log->ID ); ?></div>
											<div class="changelog-date"><?php echo date( 'd M', strtotime( $process_log->post_date ) ); ?></div>
											</div>
										</li>
									<?php } ?>
									</ul>
						 		</div>
						 <?php endif;

						  }
						 } ?>

        </div>
		<?php

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
		$user = wp_get_current_user();
		$role_check = in_array( 'administrator', $user->roles );

		if ($role_check) {
			// Actions used to insert a new endpoint in the WordPress.
			add_action( 'init', array( $this, 'add_endpoints' ) );
			add_filter( 'query_vars', array( $this, 'add_query_vars' ), 0 );

			// Insering your new tab/page into the My Account page.
			add_filter( 'woocommerce_account_menu_items', array( $this, 'new_menu_items' ) );
			add_action( 'woocommerce_account_' . self::$endpoint .  '_endpoint', array( $this, 'endpoint_content' ) );
		}
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
		$user = wp_get_current_user();
		$role_check = in_array( 'subscriber', $user->roles ) + in_array( 'customer', $user->roles ) + in_array( 'partner', $user->roles ) + in_array( 'administrator', $user->roles );

		if ($role_check) {
			// Actions used to insert a new endpoint in the WordPress.
			add_action( 'init', array( $this, 'add_endpoints' ) );
			add_filter( 'query_vars', array( $this, 'add_query_vars' ), 0 );

			// Change the My Accout page title.
			add_filter( 'the_title', array( $this, 'endpoint_title' ) );

			// Insering your new tab/page into the My Account page.
			add_filter( 'woocommerce_account_menu_items', array( $this, 'new_menu_items' ) );
			add_action( 'woocommerce_account_' . self::$endpoint .  '_endpoint', array( $this, 'endpoint_content' ) );
		}
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
		global $wp_query;

		if ( $wp_query->query_vars["anchor-dns"] ) {

			$domain_id = $wp_query->query_vars["anchor-dns"];

			if ( anchor_verify_permissions_domain( $domain_id ) ) {
			//  Display single DNS page

				$domain = constellix_api_get("domains/$domain_id");
				$response = constellix_api_get("domains/$domain_id/records");
				if ( !$response->errors ) {
					array_multisort(array_column($response,'type'), SORT_ASC, array_column($response,'name'), SORT_ASC, $response);
				}
				$record_count = 0;
				foreach ($response as $record) {
					if ( is_array($record->value) ) {
						$record_count = $record_count + count( $record->value );
					} else {
						$record_count = $record_count + 1;
					}
				};
				 ?>
				 <script>
				 /*** Copyright 2013 Teun Duynstee Licensed under the Apache License, Version 2.0 ***/
!function(n,t){"function"==typeof define&&define.amd?define([],t):"object"==typeof exports?module.exports=t():n.firstBy=t()}(this,function(){var n=function(){function n(n){return n}function t(n){return"string"==typeof n?n.toLowerCase():n}function e(e,r){if(r="number"==typeof r?{direction:r}:r||{},"function"!=typeof e){var i=e;e=function(n){return n[i]?n[i]:""}}if(1===e.length){var o=e,f=r.ignoreCase?t:n;e=function(n,t){return f(o(n))<f(o(t))?-1:f(o(n))>f(o(t))?1:0}}return r.direction===-1?function(n,t){return-e(n,t)}:e}function r(n,t){var i="function"==typeof this&&this,o=e(n,t),f=i?function(n,t){return i(n,t)||o(n,t)}:o;return f.thenBy=r,f}return r}();return n});
				 jQuery(document).ready( function () {

					 ajaxurl = "/wp-admin/admin-ajax.php";

					 new_dns_record = jQuery('.dns_record[data-status="new-record"]').clone();

					 // Changing record types via dropdown
					 jQuery('.dns_records').on("change", "tr select", function() {

						 record_type = jQuery(this).val().toLowerCase();
						 record_row = jQuery(this).parent().parent("tr");

						 jQuery(record_row).data( "type", record_type );
						 jQuery(record_row).attr( "data-type", record_type );

					 });

					 // Editing or Removing record
					 jQuery('.dns_records').on("click","tr td.actions a", function( event ) {
						 record_row = jQuery(this).parent("td.actions").parent("tr");
						 record_status = jQuery(record_row).data( "status" );
						 action_status = jQuery(this).attr('class');

						 if (record_status == action_status) {
							 jQuery(record_row).removeData( "status" );
							 jQuery(record_row).removeAttr( "data-status" );
						 } else {
							 jQuery(record_row).data( "status", jQuery(this).attr('class') );
							 jQuery(record_row).attr( "data-status", jQuery(this).attr('class') );
						 }

						 event.preventDefault();
					 });

					 jQuery('.dns_records').on("click",'.dns_record[data-status="new-record"] > td:last-child a.remove-record', function( event ) {
						jQuery(this).parent().parent("tr").remove();
						event.preventDefault();
					 });


					 jQuery('.dns_records').on("keyup",'tr[data-type="mx"] td.value table td:nth-child(2) input', function( event ) {
						 value = jQuery(this).val();
						 if ( value.substring(value.length-1) == "." ) {
							 jQuery(this).parent().removeClass("display-domain-notice");
						 } else {
							 jQuery(this).parent().addClass("display-domain-notice");
						 }
						 event.preventDefault();
					 });

					 jQuery('.dns_records').on("keyup",'tr[data-type="cname"] td.value input', function( event ) {
						 value = jQuery(this).val();
						 if ( value.substring(value.length-1) == "." ) {
							 jQuery(this).parent().removeClass("display-domain-notice");
						 } else {
							 jQuery(this).parent().addClass("display-domain-notice");
						 }
						 event.preventDefault();
					 });

					 jQuery('.dns_records .mx a.add-record').click(function( event ) {
						 jQuery(this).parent().parent("tr").before('<tr class="dns_record" data-status="new-record" data-type="a"><td><input type="text"></td><td><input type="text"></td><td><a class="remove-record" href=""><i class="fas fa-times"></i></a></td></tr>');
						 event.preventDefault();
					 });
					 jQuery('.dns_records .mx').on("click","a.remove-record", function( event ) {
						 jQuery(this).parent().parent("tr").remove();
						 event.preventDefault();
					 });
					 jQuery('.dns_records tr[data-type="txt"] a.add-record').click(function( event ) {
						 jQuery(this).parent().parent("tr").before('<tr><td><input type="text"></td><td><a class="remove-record" href=""><i class="fas fa-times"></i></a></td></tr>');
						 event.preventDefault();
					 });
					 jQuery('.dns_records tr[data-type="txt"] table').on("click","a.remove-record", function( event ) {
						 jQuery(this).parent().parent("tr").remove();
						 event.preventDefault();
					 });
					 jQuery('.dns_records').on("click",'tr[data-type="a"] a.add-record', function( event ) {
						 jQuery(this).parent().parent("tr").before('<tr><td><input type="text"></td><td><a class="remove-record" href=""><i class="fas fa-times"></i></a></td></tr>');
						 event.preventDefault();
					 });
					 jQuery('.dns_records tr[data-type="a"] table').on("click","a.remove-record", function( event ) {
						 jQuery(this).parent().parent("tr").remove();
						 event.preventDefault();
					 });
					 jQuery('.dns_records').on("click",'tr[data-type="srv"] a.add-record', function( event ) {
						 jQuery(this).parent().parent("tr").before('<tr><td><input type="text"></td><td><input type="text"></td><td><input type="text"></td><td><input type="text"></td><td><a class="remove-record" href=""><i class="fas fa-times"></i></a></td></tr>');
						 event.preventDefault();
					 });
					 jQuery('.dns_records tr[data-type="srv"] table').on("click","a.remove-record", function( event ) {
						 jQuery(this).parent().parent("tr").remove();
						 event.preventDefault();
					 });
					 jQuery('.dns_records tr[data-type="spf"] a.add-record').click(function( event ) {
						 jQuery(this).parent().parent("tr").before('<tr><td><input type="text"></td><td><input type="text"></td><td><input type="text"></td><td><input type="text"></td><td><a class="remove-record" href=""><i class="fas fa-times"></i></a></td></tr>');
						 event.preventDefault();
					 });
					 jQuery('.dns_records tr[data-type="srv"] table').on("click","a.remove-record", function( event ) {
						 jQuery(this).parent().parent("tr").remove();
						 event.preventDefault();
					 });

					 jQuery('.add-additional-record').click(function( event ) {

						 jQuery('.dns_records > tbody > tr:last-child').before( new_dns_record.clone() );
						 event.preventDefault();

					 });

					 jQuery('.save_records').click(function( event ) {

						 // Show loader and dim table
						 jQuery('.progress').css("opacity","1");
						 jQuery('.dns_records').css("opacity","0.3");

						 record_updates = [];
						 // Loop through all modified dns records
						 jQuery('.dns_records tr.dns_record[data-status]').each(function() {

							  record_id = jQuery(this).data('id');
							 	record_type = jQuery(this).data('type');
								record_name = jQuery(this).find('.name input').val();
								record_value = jQuery(this).find('.value input').val();
								record_ttl = jQuery(this).find('.ttl input').val();
								record_status = jQuery(this).data('status');

								if (record_type == "mx") {

									record_values = [];

									jQuery(this).find('tr:has("td")').each(function() {
										priority = jQuery(this).find("input:first").val();
										value = jQuery(this).find("input:last").val();
										if (priority && value) {
											record_values.push({
												"priority": priority,
												"value": value
											});
										}
									});

									record_value = record_values;

								}
								if (record_type == "txt" || record_type == "a") {

									record_values = [];

									jQuery(this).find('tr:has("td")').each(function() {
										value = jQuery(this).find("input:last").val();
										if ( value ) {
											record_values.push({
												"value": value
											});
										}
									});
									if (record_values.length > 0) {
										record_value = record_values;
									}
								}

								new_record = {
									"record_id": record_id,
									"record_type": record_type,
									"record_name": record_name,
									"record_value": record_value,
									"record_ttl": record_ttl,
									"record_status": record_status
								};

							 // Prep new/modified items
							 if ( record_type ) {
								 if ( record_value || record_name ) {
							 		record_updates.push( new_record );
								}
						 	 }

						 });

						 // Submit DNS Updates
						 var data = {
							'action': 'anchor_dns',
							'domain_key': <?php echo $domain_id; ?>,
							'record_updates': record_updates
						 };

						jQuery.post(ajaxurl, data, function(response) {
							console.log(response);
							var response = jQuery.parseJSON(response);
							jQuery(response).each(function() {
								// Display success
								if ( this["success"] ) {
									if( this["success"] == "Record  deleted successfully" ) {
										record_id = this["record_id"];
										jQuery('tr[data-id='+record_id+']').remove();
									}
									if( this["success"] == "Record  updated successfully" ) {
										record_id = this["record_id"];
										record_type = this["record_type"];
										record_row = jQuery('tr[data-id='+record_id+']');
										record_name = record_row.find('.name .record-editable input').val();
										record_row.find('.name .record-view').html( record_name );
										if ( record_type == "mx" ) {
											record_values = [];
											record_row.find('.value .record-editable table tr').each(function() {
												record_priority = jQuery(this).find("td:nth-child(1) input").val();
												record_server = jQuery(this).find("td:nth-child(2) input").val();
												if (record_priority && record_server) {
													record_values.push ( '<p>'+record_priority+' '+record_server+'</p>' );
												}
											});
											record_row.find('.value .record-view').html( "" );
											jQuery(record_values).each(function() {
												record_row.find('.value .record-view').append( this );
											});
										} else if ( record_type == "txt" || record_type == "a" ) {
											record_values = [];
											record_row.find('.value .record-editable table input').each(function() {
												record_values.push ( '<p>'+jQuery(this).val()+'</p>' );
											});
											record_row.find('.value .record-view').html( "" );
											jQuery(record_values).each(function() {
												record_row.find('.value .record-view').append( this );
											});
										} else {
											record_value = record_row.find('.value .record-editable input').val();
											record_row.find('.value .record-view').html( record_value );
										}
										record_ttl = record_row.find('.ttl .record-editable input').val();
										record_row.find('.ttl .record-view').html( record_ttl );
										jQuery(record_row).removeData( "status" );
										jQuery(record_row).removeAttr( "data-status" );
									}

									Materialize.toast( this["success"] , 4000);
								}
								// Display errors
								if ( this["errors"] ) {
									Materialize.toast( this["errors"], 4000 );
								}
								// New record
								if ( this["id"] ) {
									record_id = this["id"];
									record_type = this["type"];
									record_recordtype = this["recordType"];
									if ( Array.isArray( this["value"]) ) {
										if ( typeof this["value"][0] === 'object') {
											record_value = this["value"][0]["value"];
										} else {
											record_value = this["value"][0];
										}
									} else {
										record_value = this["value"];
									}
									record_name = this["name"];
									record_zone = this["zone"];
									record_ttl = this["ttl"];
									new_dns_record_html = jQuery('tr.dns_record:first').clone();
									new_dns_record_html.find('.type div:first-child').html( record_type );
									new_dns_record_html.find('.name .record-view').html( record_name );
									new_dns_record_html.find('.name .record-editable input').val( record_name );
									new_dns_record_html.find('.value .record-view').html( record_value );
									new_dns_record_html.find('.value .record-editable input').val( record_value );
									new_dns_record_html.find('.ttl .record-view').html( record_ttl );
									new_dns_record_html.find('.ttl .record-editable input').val( record_ttl );
									new_dns_record_html.data("id", record_id);
									new_dns_record_html.attr("data-id", record_id);
									new_dns_record_html.data("type", record_recordtype);
									new_dns_record_html.attr("data-type", record_recordtype);
									jQuery('.dns_records > tbody > tr:first-child').before( new_dns_record_html.clone() );
									jQuery('tr.dns_record:first').find("select option").filter(function() {
									    return (jQuery(this).text() == record_type );
									}).prop('selected', true);
									jQuery('.dns_record[data-status="new-record"]').remove();
									var dnsrecords = jQuery('.dns_records');
									var dnsrecordstr = jQuery(dnsrecords).children('tbody').children('tr').get();
									dnsrecordstr.sort(
									    firstBy(function (v) { return jQuery(v).attr("data-type"); })
									    .thenBy(function (v) { return jQuery(v).find(".name .record-view").text().trim(); })
									);
									jQuery.each(dnsrecordstr, function(idx, itm) { dnsrecords.append(itm); });
									Materialize.toast( "New record added" , 4000);
									jQuery('.add-additional-record').click();
								}

							});

							// Hide loader and reveal table
							jQuery('.progress').css("opacity","0");
							jQuery('.dns_records').css("opacity","1");

						});

						event.preventDefault();
					 });
				 });
				 </script>
				<h3>Domain: <?php echo $domain->name; ?> <small class="alignright"><?php echo $record_count; ?> records</small></h3>

				<hr>

				<table class="dns_records">
        <thead>
          <tr>
              <th>Type</th>
              <th>Name</th>
              <th>Value</th>
							<th>TTL</th>
							<th></th>
          </tr>
        </thead>
				<tbody>
				<?php
				foreach($response as $records) {
				  $record_id = $records->id;
				  $record_name = $records->name;
					$record_type = $records->type;
				  $record_host = $records->host;  // Used for CNAME records
					$record_ttl = $records->ttl;
					$record_url = $records->url;
				  $record_values = $records->value; ?>
					<tr data-id="<?php echo $record_id; ?>" data-type="<?php echo strtolower($record_type); ?>" class="dns_record">
						<td class="type">
							<div>
								<?php if ($record_type == "HTTPRedirection") {
									echo "HTTP Redirect";
								} else {
									echo $record_type;
								}  ?>
							</div>
							<div class="record-non-editable">
								<select>
									<option<?php if( $record_type == "A" ) { echo " selected"; } ?>>A</option>
									<option<?php if( $record_type == "AAAA" ) { echo " selected"; } ?>>AAAA</option>
									<option<?php if( $record_type == "ANAME" ) { echo " selected"; } ?>>ANAME</option>
									<option<?php if( $record_type == "CNAME" ) { echo " selected"; } ?>>CNAME</option>
									<option<?php if( $record_type == "HTTPRedirection" ) { echo " selected"; } ?> value="HTTPRedirection">HTTP Redirect</option>
									<option<?php if( $record_type == "MX" ) { echo " selected"; } ?>>MX</option>
									<option<?php if( $record_type == "SPF" ) { echo " selected"; } ?>>SPF</option>
									<option<?php if( $record_type == "SRV" ) { echo " selected"; } ?>>SRV</option>
									<option<?php if( $record_type == "TXT" ) { echo " selected"; } ?>>TXT</option>

								</select>

							</div>
						</td>
						<td class="name">
							<div class="record-view">
								<?php echo $record_name; ?>
							</div>
							<div class="record-editable">
								<input type="text" value="<?php echo $record_name; ?>">
							</div>
						</td>
						<td class="value">
							<?php
						if ( $records->type == "MX" ) {
							array_multisort(array_column($record_values,'level'), SORT_ASC, array_column($record_values,'value'), SORT_ASC, $record_values); ?>
							<div class="mx">
								<div class="record-view">
							<?php foreach($record_values as $record) {
								$record_value = $record->value;
								$record_level = $record->level;  // Used by MX records ?>

								<p><?php echo $record_level; ?> <?php echo $record_value; ?></p>
							<?php } ?>
							</div>
							<div class="record-editable">
								<table>
									<tr><th>Priority</th><th>Mail Server</th><th></th></tr>
							<?php foreach($record_values as $record) {
								$record_value = $record->value;
								$record_level = $record->level;  // Used by MX records ?>
								<tr><td><input type="text" value="<?php echo $record_level; ?>"></td><td><div class="message">.<?php echo $domain->name; ?></div><input type="text" value="<?php echo $record_value; ?>"></td><td><a class="remove-record" href=""><i class="fas fa-times"></i></a></td></tr>
								<?php } ?>
									<tr><td colspan="3"><a href="#" class="add-record">Add Additional Record</a></td></tr>
								 </table>
							 </div>
						 </div>
<?php 		}
					if ( $records->type == "CNAME" or $records->type == "HTTPRedirection") { ?>
							<div class="record-view">
								<?php echo $record_values; ?>
							</div>
							<div class="record-editable">
								<div class="message">.<?php echo $domain->name; ?></div>
								<input type="text" value="<?php echo $record_values; ?>">
							</div>
<?php 		}
					if ( $records->type == "AAAA" or $records->type == "ANAME" ) {

						foreach($record_values as $record) {

				    	$record_value = $record->value;
							if ( ! isset($record->value) ) { $record_value = $record; } ?>
							<div class="record-view">
								<?php echo $record_value; ?>
							</div>
							<div class="record-editable">
								<input type="text" value="<?php echo htmlspecialchars($record_value); ?>">
							</div>
<?php 			}
					}
					if ( $records->type == "A" ) { ?>

						<div class="record-view">
					<?php foreach($record_values as $record) { ?>
						<p><?php echo $record; ?></p>
					<?php } ?>
					</div>
					<div class="record-editable">
						<table>
							<tr><th>Value</th><th></th></tr>
					<?php foreach($record_values as $record) { ?>
						<tr><td><input type="text" value='<?php echo $record; ?>'></td><td><a class="remove-record" href=""><i class="fas fa-times"></i></a></td></tr>
						<?php } ?>
							<tr><td colspan="2"><a href="#" class="add-record">Add Additional Record</a></td></tr>
						 </table>
					 </div>
<?php
					}
					if ( $records->type == "SPF" or $records->type == "TXT" ) { ?>

						<div class="record-view">
					<?php foreach($record_values as $record) {
						$record_value = $record->value;
						 ?>

						<p><?php echo $record_value; ?></p>
					<?php } ?>
					</div>
					<div class="record-editable">
						<table>
							<tr><th>Value</th><th></th></tr>
					<?php foreach($record_values as $record) {
						$record_value = $record->value; ?>
						<tr><td><input type="text" value='<?php echo $record_value; ?>'></td><td><a class="remove-record" href=""><i class="fas fa-times"></i></a></td></tr>
						<?php } ?>
							<tr><td colspan="2"><a href="#" class="add-record">Add Additional Record</a></td></tr>
						 </table>
					 </div>
<?php
					}
					if ( $records->type == "SRV" ) { ?>
						<div class="srv">
							<div class="record-view">
							<?php foreach($record_values as $record) {

							$record_value = $record->value;
							$record_priority = $record->priority;
							$record_weight = $record->weight;
							$record_port = $record->port;

							?>
								<p><?php echo $record_value; ?> <?php echo $record_priority; ?> <?php echo $record_weight; ?> <?php echo $record_port; ?></p>
							<?php } ?>
						</div>
						<div class="record-editable">
							<table>
								<tr><th>Priority</th><th>Weight</th><th>Port</th><th>Host</th><th></th></tr>
							<?php foreach($record_values as $record) { ?>
											<tr>
												<td><input type="text" value="<?php echo $record_priority; ?>"></td><td><input type="text" value="<?php echo $record_weight; ?>"></td><td><input type="text" value="<?php echo $record_port; ?>"></td>
												<td><input type="text" value="<?php echo $record_value; ?>"></td><td><a class="remove-record" href=""><i class="fas fa-times"></i></a></td>
											</tr>
							<?php } ?>
									<tr><td colspan="5"><a href="#" class="add-record">Add Additional Record</a></td></tr>
							 </table>
							</div>
						</div>
					<?php } ?>
				  </td>
					<td class="ttl">
						<div class="record-view">
							<?php echo $record_ttl; ?>
						</div>
						<div class="record-editable">
							<input type="text" value="<?php echo $record_ttl; ?>">
						</div>
					</td>
					<td class="actions">
						<a class="edit-record" href=""><i class="fas fa-edit"></i></a>
						<a class="remove-record"  href=""><i class="fas fa-times"></i></a>
					</td>
				</tr>
					<?php

				} ?>
				<tr class="dns_record" data-status="new-record" data-type="a">
					<td>
						<select>
							<option selected>A</option>
							<option>AAAA</option>
							<option>ANAME</option>
							<option>CNAME</option>
							<option value="httpredirection">HTTP Redirect</option>
							<option>MX</option>
							<option>SPF</option>
							<option>SRV</option>
							<option>TXT</option>

						</select>
					</td>
					<td class="name"><input type="text"></td>
					<td class="value">
						<div class="value">
						<div class="message">.<?php echo $domain->name; ?></div>
						<input type="text">
					</div>
					<div class="mx">
						<table>
							<tr><th>Priority</th><th>Mail Server</th><th></th></tr>
							<tr><td><input type="text"></td><td><input type="text"></td><td><a class="remove-record" href=""><i class="fas fa-times"></i></a></td></tr>
							<tr><td><input type="text"></td><td><input type="text"></td><td><a class="remove-record" href=""><i class="fas fa-times"></i></a></td></tr>
							<tr><td><input type="text"></td><td><input type="text"></td><td><a class="remove-record" href=""><i class="fas fa-times"></i></a></td></tr>
							<tr><td><input type="text"></td><td><input type="text"></td><td><a class="remove-record" href=""><i class="fas fa-times"></i></a></td></tr>
							<tr><td><input type="text"></td><td><input type="text"></td><td><a class="remove-record" href=""><i class="fas fa-times"></i></a></td></tr>
							<tr><td colspan="3"><a href="#" class="add-record">Add Additional Record</a></td></tr>
					 </table>
					</div>

					<div class="srv">
						<table>
							<tr><th>Priority</th><th>Weight</th><th>Port</th><th>Host</th><th></th></tr>
							<tr><td><input type="text"></td><td><input type="text"></td><td><input type="text"></td><td><input type="text"></td><td><a class="remove-record" href=""><i class="fas fa-times"></i></a></td></tr>
							<tr><td><input type="text"></td><td><input type="text"></td><td><input type="text"></td><td><input type="text"></td><td><a class="remove-record" href=""><i class="fas fa-times"></i></a></td></tr>
							<tr><td><input type="text"></td><td><input type="text"></td><td><input type="text"></td><td><input type="text"></td><td><a class="remove-record" href=""><i class="fas fa-times"></i></a></td></tr>
							<tr><td><input type="text"></td><td><input type="text"></td><td><input type="text"></td><td><input type="text"></td><td><a class="remove-record" href=""><i class="fas fa-times"></i></a></td></tr>
							<tr><td><input type="text"></td><td><input type="text"></td><td><input type="text"></td><td><input type="text"></td><td><a class="remove-record" href=""><i class="fas fa-times"></i></a></td></tr>
							<tr><td colspan="5"><a href="#" class="add-record">Add Additional Record</a></td></tr>
					 </table>
					</div>

					</td>
					<td class="ttl"><input type="text" value="1800"></td>
					<td>
						<a class="remove-record" href=""><i class="fas fa-times"></i></a>
					</td>
				</tr>
				<tr data-type="z">
					<td colspan="5"><p><a href="#" class="add-additional-record">Add Additional Record</a></p></td>
				</tr>
			</tbody>
      </table>
			<div class="progress" style="opacity:0;">
	      <div class="indeterminate"></div>
	  </div>
			<a class="button save_records">Save Records</a>
			<a href="<?php echo get_site_url(null,'/my-account/anchor-dns/'); ?>" class="alignright button">View All Domains</a>
		<?php
	} else { ?>
		Domain not found
		<a href="<?php echo get_site_url(null,'/my-account/anchor-dns/'); ?>" class="alignright button">View All Domains</a>
	<?php
	}

} else { // Display DNS listing page ?>
			<div class="row">
        <div class="col s12">
          <div class="card">
            <div class="card-content row">
						<div class="col s12 m7">
             <p>Anchor DNS is available for all <a href="https://anchor.host/plans/">customers</a> and helps keep things running smooth.
			It allows you to manage your own zone records and use <a href="https://constellix.com/">Constellix</a>, an enterprise grade DNS service built by DNS Made Easy.
			Not comfortable with DNS? As always email <a href="mailto:support@anchor.host">support@anchor.host</a> and we'll take care of any DNS updates for you.</p>
						</div>
						<div class="col s12 m1"></div>
						<div class="col s12 m4">
		          <span class="card-title">Nameservers</span>
		            <ul>
								  <li>ns11.constellix.com</li>
									<li>ns21.constellix.com</li>
									<li>ns31.constellix.com</li>
									<li>ns41.constellix.net</li>
									<li>ns51.constellix.net</li>
									<li>ns61.constellix.net</li>
								</ul>
		        </div>
            </div>
          </div>
        </div>
      </div>

		<?php

		$user_id = get_current_user_id();

		$partner = get_field('partner', 'user_'. get_current_user_id() );
		if ($partner) {
			foreach ($partner as $partner_id) {


				// Get all domains partner has access to
				$domains = get_domains_per_partner( $partner_id );

				if( $domains ):
					echo '<h3>Account: '. get_the_title($partner_id) .' <small class="alignright">'. count($domains).' domains</small></h3>';
					echo '<div class="row dns_records">';
					foreach( $domains as $domain ): ?>


			 <div class="col s12 m6">
				 <div class="card">

					 <div class="card-content">
						 <p><span class="card-title"><?php echo get_the_title( $domain ); ?></span></p>
					 </div>
					 <div class="card-action">
						 <a href="<?php echo get_site_url(null,'/my-account/anchor-dns'); ?>/<?php echo get_field("domain_id", $domain ); ?>/">Modify DNS</a>
					 </div>
				 </div>
			 </div>

					<?php endforeach;
					echo "</div>";
				endif;

			}

		} // end foreach ($partner as $partner_id)
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
register_activation_hook( __FILE__, array( 'Anchor_My_Account_Dns_Endpoint', 'install' ) );

// Load classes after plugins are loaded
add_action('plugins_loaded','construct_my_class');
function construct_my_class() {
	new Anchor_My_Account_Config_Endpoint();
	new Anchor_My_Account_Dns_Endpoint();
	new Anchor_My_Account_Logs_Endpoint();
	new Anchor_My_Account_Handbook_Endpoint();

}
