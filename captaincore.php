<?php

/**
 * The plugin bootstrap file
 *
 * This file is read by WordPress to generate the plugin information in the plugin
 * admin area. This file also includes all of the dependencies used by the plugin,
 * registers the activation and deactivation functions, and defines a function
 * that starts the plugin.
 *
 * @link              https://captaincore.io
 * @since             0.1.0
 * @package           Captaincore
 *
 * @wordpress-plugin
 * Plugin Name:       CaptainCore
 * Plugin URI:        https://captaincore.io
 * Description:       Open Source Toolkit for Managing WordPress Sites
 * Version:           0.6.0
 * Author:            Austin Ginder
 * Author URI:        https://twitter.com/austinginder
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       captaincore
 * Domain Path:       /languages
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Currently plugin version.
 * Start at version 0.1.0 and use SemVer - https://semver.org
 * Rename this for your plugin and update it as you release new versions.
 */
define( 'PLUGIN_NAME_VERSION', '0.1.0' );

/**
 * The code that runs during plugin activation.
 * This action is documented in includes/class-captaincore-activator.php
 */
function activate_captaincore() {
	require_once plugin_dir_path( __FILE__ ) . 'includes/class-captaincore-activator.php';
	Captaincore_Activator::activate();
}

/**
 * The code that runs during plugin deactivation.
 * This action is documented in includes/class-captaincore-deactivator.php
 */
function deactivate_captaincore() {
	require_once plugin_dir_path( __FILE__ ) . 'includes/class-captaincore-deactivator.php';
	Captaincore_Deactivator::deactivate();
}

register_activation_hook( __FILE__, 'activate_captaincore' );
register_deactivation_hook( __FILE__, 'deactivate_captaincore' );

/**
 * The core plugin class that is used to define internationalization,
 * admin-specific hooks, and public-facing site hooks.
 */
require plugin_dir_path( __FILE__ ) . 'includes/class-captaincore.php';
require plugin_dir_path( __FILE__ ) . 'includes/class-captaincore-db.php';

/**
 * Begins execution of the plugin.
 *
 * Since everything within the plugin is registered via hooks,
 * then kicking off the plugin from this point in the file does
 * not affect the page life cycle.
 *
 * @since    0.1.0
 */
function run_captaincore() {

	$plugin = new Captaincore();
	$plugin->run();

}
run_captaincore();

require 'includes/register-custom-fields.php';
require 'includes/constellix-api/constellix-api.php';
require 'includes/woocommerce-custom-password-fields.php';
require 'includes/mailgun-api.php';
require 'includes/process-functions.php';
require 'includes/bulk-actions.php';
require 'includes/Parsedown.php';

function captaincore_rewrite() {
	add_rewrite_rule( '^captaincore-api/([^/]*)/?', 'index.php?pagename=captaincore-api&callback=$matches[1]', 'top' );
	add_rewrite_rule( '^checkout-express/([^/]*)/?', 'index.php?pagename=checkout-express&callback=$matches[1]', 'top' );
	add_rewrite_tag( '%site%', '([^&]+)' );
	add_rewrite_tag( '%sitetoken%', '([^&]+)' );
	add_rewrite_tag( '%callback%', '([^&]+)' );

	register_taxonomy(
		'process_role', array( 'captcore_process' ), array(
			'hierarchical'   => true,
			'label'          => 'Roles',
			'singular_label' => 'Role',
			'rewrite'        => true,
		)
	);
}

add_action( 'init', 'captaincore_rewrite' );

function captaincore_disable_gutenberg( $can_edit, $post_type ) {
	$disabled_post_types = array( 'captcore_website', 'captcore_domain', 'captcore_customer', 'captcore_changelog' );
	if ( in_array( $post_type, $disabled_post_types ) ) {
		return false;
	}
	return $can_edit;
}
add_filter( 'use_block_editor_for_post_type', 'captaincore_disable_gutenberg', 10, 2 );

// Modify WooCommerce Menu: wc_get_account_menu_items() ;
function captaincore_my_account_order( $current_menu ) {

	unset( $current_menu['websites'] );
	unset( $current_menu['edit-account'] );
	$current_menu['edit-account'] = 'Account';
	unset( $current_menu['subscriptions'] );
	$current_menu['subscriptions'] = 'Billing';
	unset( $current_menu['customer-logout'] );
	$current_menu['payment-methods'] = 'Payment methods'; // Payment Methods
	$current_menu['customer-logout'] = 'Logout';

	$user = wp_get_current_user();

	$role_check_admin      = in_array( 'administrator', $user->roles );
	$role_check_subscriber = in_array( 'subscriber', $user->roles ) + in_array( 'administrator', $user->roles ) + in_array( 'editor', $user->roles );

	if ( ! $role_check_admin ) {
		unset( $current_menu['handbook'] );
		unset( $current_menu['cookbook'] );
		unset( $current_menu['manage'] );
	}
	if ( ! $role_check_subscriber ) {
		unset( $current_menu['dns'] );
		unset( $current_menu['logs'] );
	}
	if ( ! defined( "CONSTELLIX_API_KEY") or ! defined( "CONSTELLIX_SECRET_KEY")  ) {
		unset( $current_menu['dns'] );
	}
	return $current_menu;
}
// Need to run later to allow time for new items to be added to WooCommerce Menu
add_filter( 'woocommerce_account_menu_items', 'captaincore_my_account_order', 50 );

// Register Custom Post Type
function contact_post_type() {

	$labels       = array(
		'name'                  => _x( 'Contacts', 'Post Type General Name', 'captaincore' ),
		'singular_name'         => _x( 'Contact', 'Post Type Singular Name', 'captaincore' ),
		'menu_name'             => __( 'Contacts', 'captaincore' ),
		'name_admin_bar'        => __( 'Contacts', 'captaincore' ),
		'archives'              => __( 'Item Archives', 'captaincore' ),
		'parent_item_colon'     => __( 'Parent Item:', 'captaincore' ),
		'all_items'             => __( 'Contacts', 'captaincore' ),
		'add_new_item'          => __( 'Add New Item', 'captaincore' ),
		'add_new'               => __( 'Add New', 'captaincore' ),
		'new_item'              => __( 'New Item', 'captaincore' ),
		'edit_item'             => __( 'Edit Item', 'captaincore' ),
		'update_item'           => __( 'Update Item', 'captaincore' ),
		'view_item'             => __( 'View Item', 'captaincore' ),
		'search_items'          => __( 'Search Item', 'captaincore' ),
		'not_found'             => __( 'Not found', 'captaincore' ),
		'not_found_in_trash'    => __( 'Not found in Trash', 'captaincore' ),
		'featured_image'        => __( 'Featured Image', 'captaincore' ),
		'set_featured_image'    => __( 'Set featured image', 'captaincore' ),
		'remove_featured_image' => __( 'Remove featured image', 'captaincore' ),
		'use_featured_image'    => __( 'Use as featured image', 'captaincore' ),
		'insert_into_item'      => __( 'Insert into item', 'captaincore' ),
		'uploaded_to_this_item' => __( 'Uploaded to this item', 'captaincore' ),
		'items_list'            => __( 'Items list', 'captaincore' ),
		'items_list_navigation' => __( 'Items list navigation', 'captaincore' ),
		'filter_items_list'     => __( 'Filter items list', 'captaincore' ),
	);
	$capabilities = array(
		'edit_post'          => 'website_edit_post',
		'read_post'          => 'website_read_post',
		'delete_post'        => 'website_delete_post',
		'edit_posts'         => 'website_edit_posts',
		'edit_others_posts'  => 'website_edit_others_posts',
		'publish_posts'      => 'website_publish_posts',
		'read_private_posts' => 'website_read_private_posts',
	);
	$args         = array(
		'label'               => __( 'Contact', 'captaincore' ),
		'description'         => __( 'Contact Description', 'captaincore' ),
		'labels'              => $labels,
		'supports'            => array( 'author', 'revisions' ),
		'hierarchical'        => false,
		'public'              => false,
		'show_ui'             => true,
		'show_in_menu'        => false,
		'menu_position'       => 5,
		'menu_icon'           => 'dashicons-admin-users',
		'show_in_admin_bar'   => true,
		'show_in_nav_menus'   => true,
		'can_export'          => true,
		'has_archive'         => false,
		'exclude_from_search' => true,
		'publicly_queryable'  => false,
		'capability_type'     => 'page',
		'capabilities'        => $capabilities,
		'map_meta_cap'        => true,
	);
	register_post_type( 'captcore_contact', $args );

}
add_action( 'init', 'contact_post_type', 0 );

// Register Custom Post Type
function customer_post_type() {

	$labels       = array(
		'name'               => _x( 'Customers', 'Post Type General Name', 'captaincore' ),
		'singular_name'      => _x( 'Customer', 'Post Type Singular Name', 'captaincore' ),
		'menu_name'          => __( 'Customers', 'captaincore' ),
		'name_admin_bar'     => __( 'Customer', 'captaincore' ),
		'parent_item_colon'  => __( 'Parent Customer:', 'captaincore' ),
		'all_items'          => __( 'Customers', 'captaincore' ),
		'add_new_item'       => __( 'Add New Customer', 'captaincore' ),
		'add_new'            => __( 'Add New', 'captaincore' ),
		'new_item'           => __( 'New Customer', 'captaincore' ),
		'edit_item'          => __( 'Edit Customer', 'captaincore' ),
		'update_item'        => __( 'Update Customer', 'captaincore' ),
		'view_item'          => __( 'View Item', 'captaincore' ),
		'search_items'       => __( 'Search Customers', 'captaincore' ),
		'not_found'          => __( 'Not found', 'captaincore' ),
		'not_found_in_trash' => __( 'Not found in Trash', 'captaincore' ),
	);
	$capabilities = array(
		'edit_post'          => 'website_edit_post',
		'read_post'          => 'website_read_post',
		'delete_post'        => 'website_delete_post',
		'edit_posts'         => 'website_edit_posts',
		'edit_others_posts'  => 'website_edit_others_posts',
		'publish_posts'      => 'website_publish_posts',
		'read_private_posts' => 'website_read_private_posts',
	);
	$args         = array(
		'label'               => __( 'Customer', 'captaincore' ),
		'description'         => __( 'Customer Description', 'captaincore' ),
		'labels'              => $labels,
		'supports'            => array(),
		'hierarchical'        => false,
		'public'              => false,
		'show_ui'             => true,
		'show_in_menu'        => false,
		'menu_position'       => 5,
		'menu_icon'           => 'dashicons-groups',
		'show_in_admin_bar'   => true,
		'show_in_nav_menus'   => true,
		'can_export'          => true,
		'has_archive'         => true,
		'exclude_from_search' => true,
		'publicly_queryable'  => false,
		'capability_type'     => 'page',
		'show_in_rest'        => true,
		'capabilities'        => $capabilities,
		'map_meta_cap'        => true,
	);
	register_post_type( 'captcore_customer', $args );

}
add_action( 'init', 'customer_post_type', 0 );

// Register Custom Post Type
function website_post_type() {

	$labels       = array(
		'name'               => _x( 'Websites', 'Post Type General Name', 'captaincore' ),
		'singular_name'      => _x( 'Website', 'Post Type Singular Name', 'captaincore' ),
		'menu_name'          => __( 'Websites', 'captaincore' ),
		'parent_item_colon'  => __( 'Parent Website:', 'captaincore' ),
		'all_items'          => __( 'Websites', 'captaincore' ),
		'view_item'          => __( 'View Website', 'captaincore' ),
		'add_new_item'       => __( 'Add New Websites', 'captaincore' ),
		'add_new'            => __( 'New Websites', 'captaincore' ),
		'edit_item'          => __( 'Edit Website', 'captaincore' ),
		'update_item'        => __( 'Update Website', 'captaincore' ),
		'search_items'       => __( 'Search websites', 'captaincore' ),
		'not_found'          => __( 'No websites found', 'captaincore' ),
		'not_found_in_trash' => __( 'No websites found in Trash', 'captaincore' ),
	);
	$capabilities = array(
		'edit_post'          => 'website_edit_post',
		'read_post'          => 'website_read_post',
		'delete_post'        => 'website_delete_post',
		'edit_posts'         => 'website_edit_posts',
		'edit_others_posts'  => 'website_edit_others_posts',
		'publish_posts'      => 'website_publish_posts',
		'read_private_posts' => 'website_read_private_posts',
	);
	$args         = array(
		'label'               => __( 'website', 'captaincore' ),
		'description'         => __( 'Website information pages', 'captaincore' ),
		'labels'              => $labels,
		'supports'            => array(),
		'hierarchical'        => false,
		'public'              => false,
		'show_ui'             => true,
		'show_in_menu'        => false,
		'show_in_nav_menus'   => true,
		'show_in_admin_bar'   => true,
		'menu_position'       => 5,
		'menu_icon'           => 'dashicons-admin-multisite',
		'can_export'          => true,
		'has_archive'         => false,
		'exclude_from_search' => true,
		'publicly_queryable'  => false,
		'capability_type'     => 'page',
		'show_in_rest'        => true,
		'capabilities'        => $capabilities,
		'map_meta_cap'        => true,
	);
	register_post_type( 'captcore_website', $args );
}

// Hook into the 'init' action
add_action( 'init', 'website_post_type', 0 );

// Register Custom Post Type
function domain_post_type() {

	$labels       = array(
		'name'                  => _x( 'Domains', 'Post Type General Name', 'captaincore' ),
		'singular_name'         => _x( 'Domain', 'Post Type Singular Name', 'captaincore' ),
		'menu_name'             => __( 'Domains', 'captaincore' ),
		'name_admin_bar'        => __( 'Domain', 'captaincore' ),
		'archives'              => __( 'Domain Archives', 'captaincore' ),
		'attributes'            => __( 'Domain Attributes', 'captaincore' ),
		'parent_item_colon'     => __( 'Parent Domain:', 'captaincore' ),
		'all_items'             => __( 'Domains', 'captaincore' ),
		'add_new_item'          => __( 'Add New Domain', 'captaincore' ),
		'add_new'               => __( 'Add New', 'captaincore' ),
		'new_item'              => __( 'New Domain', 'captaincore' ),
		'edit_item'             => __( 'Edit Domain', 'captaincore' ),
		'update_item'           => __( 'Update Domain', 'captaincore' ),
		'view_item'             => __( 'View Domain', 'captaincore' ),
		'view_items'            => __( 'View Domains', 'captaincore' ),
		'search_items'          => __( 'Search Domain', 'captaincore' ),
		'not_found'             => __( 'Not found', 'captaincore' ),
		'not_found_in_trash'    => __( 'Not found in Trash', 'captaincore' ),
		'featured_image'        => __( 'Featured Image', 'captaincore' ),
		'set_featured_image'    => __( 'Set featured image', 'captaincore' ),
		'remove_featured_image' => __( 'Remove featured image', 'captaincore' ),
		'use_featured_image'    => __( 'Use as featured image', 'captaincore' ),
		'insert_into_item'      => __( 'Insert into item', 'captaincore' ),
		'uploaded_to_this_item' => __( 'Uploaded to this item', 'captaincore' ),
		'items_list'            => __( 'Items list', 'captaincore' ),
		'items_list_navigation' => __( 'Items list navigation', 'captaincore' ),
		'filter_items_list'     => __( 'Filter items list', 'captaincore' ),
	);
	$capabilities = array(
		'edit_post'          => 'website_edit_post',
		'read_post'          => 'website_read_post',
		'delete_post'        => 'website_delete_post',
		'edit_posts'         => 'website_edit_posts',
		'edit_others_posts'  => 'website_edit_others_posts',
		'publish_posts'      => 'website_publish_posts',
		'read_private_posts' => 'website_read_private_posts',
	);
	$args         = array(
		'label'               => __( 'Domain', 'captaincore' ),
		'description'         => __( 'Domain Description', 'captaincore' ),
		'labels'              => $labels,
		'supports'            => array( 'title', 'editor' ),
		'hierarchical'        => false,
		'public'              => true,
		'show_ui'             => true,
		'show_in_menu'        => false,
		'menu_position'       => 5,
		'menu_icon'           => 'dashicons-welcome-widgets-menus',
		'show_in_admin_bar'   => true,
		'show_in_nav_menus'   => true,
		'can_export'          => true,
		'has_archive'         => false,
		'exclude_from_search' => true,
		'publicly_queryable'  => false,
		'capability_type'     => 'page',
		'show_in_rest'        => true,
		'capabilities'        => $capabilities,
		'map_meta_cap'        => true,
	);
	register_post_type( 'captcore_domain', $args );

}
add_action( 'init', 'domain_post_type', 0 );

// Adds new permissions
function add_theme_caps() {
	// gets the administrator role
	$admins = get_role( 'administrator' );
	$admins->add_cap( 'website_edit_post' );
	$admins->add_cap( 'website_read_post' );
	$admins->add_cap( 'website_delete_post' );
	$admins->add_cap( 'website_edit_posts' );
	$admins->add_cap( 'website_edit_others_posts' );
	$admins->add_cap( 'website_publish_posts' );
	$admins->add_cap( 'website_read_private_posts' );
}
add_action( 'admin_init', 'add_theme_caps' );

// Register Custom Post Type
function changelog_post_type() {

	$labels       = array(
		'name'               => _x( 'Website Logs', 'Post Type General Name', 'captaincore' ),
		'singular_name'      => _x( 'Website Log', 'Post Type Singular Name', 'captaincore' ),
		'menu_name'          => __( 'Website Logs', 'captaincore' ),
		'parent_item_colon'  => __( 'Parent Changelog:', 'captaincore' ),
		'all_items'          => __( 'Changelogs', 'captaincore' ),
		'view_item'          => __( 'View Changelog', 'captaincore' ),
		'add_new_item'       => __( 'Add New Changelog', 'captaincore' ),
		'add_new'            => __( 'New Changelog', 'captaincore' ),
		'edit_item'          => __( 'Edit Changelog', 'captaincore' ),
		'update_item'        => __( 'Update Changelog', 'captaincore' ),
		'search_items'       => __( 'Search changelogs', 'captaincore' ),
		'not_found'          => __( 'No changelogs found', 'captaincore' ),
		'not_found_in_trash' => __( 'No changelogs found in Trash', 'captaincore' ),
	);
	$capabilities = array(
		'edit_post'          => 'website_edit_post',
		'read_post'          => 'website_read_post',
		'delete_post'        => 'website_delete_post',
		'edit_posts'         => 'website_edit_posts',
		'edit_others_posts'  => 'website_edit_others_posts',
		'publish_posts'      => 'website_publish_posts',
		'read_private_posts' => 'website_read_private_posts',
	);
	$args         = array(
		'label'               => __( 'changelog', 'captaincore' ),
		'description'         => __( 'Changelog information pages', 'captaincore' ),
		'labels'              => $labels,
		'supports'            => array(),
		'hierarchical'        => false,
		'public'              => false,
		'show_ui'             => true,
		'show_in_menu'        => false,
		'show_in_nav_menus'   => true,
		'show_in_admin_bar'   => true,
		'menu_position'       => 5,
		'menu_icon'           => 'dashicons-media-spreadsheet',
		'can_export'          => true,
		'has_archive'         => false,
		'exclude_from_search' => true,
		'publicly_queryable'  => false,
		'capability_type'     => 'page',
		'show_in_rest'        => true,
		'capabilities'        => $capabilities,
		'map_meta_cap'        => true,
	);
	register_post_type( 'captcore_changelog', $args );

}

// Hook into the 'init' action
add_action( 'init', 'changelog_post_type', 0 );

// Register Custom Post Type
function process_post_type() {

	$labels       = array(
		'name'                  => _x( 'Processes', 'Post Type General Name', 'captaincore' ),
		'singular_name'         => _x( 'Process', 'Post Type Singular Name', 'captaincore' ),
		'menu_name'             => __( 'Processes', 'captaincore' ),
		'name_admin_bar'        => __( 'Process', 'captaincore' ),
		'archives'              => __( 'Process Archives', 'captaincore' ),
		'parent_item_colon'     => __( 'Parent Process:', 'captaincore' ),
		'all_items'             => __( 'Processes', 'captaincore' ),
		'add_new_item'          => __( 'Add New Process', 'captaincore' ),
		'add_new'               => __( 'Add New', 'captaincore' ),
		'new_item'              => __( 'New Process', 'captaincore' ),
		'edit_item'             => __( 'Edit Process', 'captaincore' ),
		'update_item'           => __( 'Update Process', 'captaincore' ),
		'view_item'             => __( 'View Process', 'captaincore' ),
		'search_items'          => __( 'Search Processes', 'captaincore' ),
		'not_found'             => __( 'Not found', 'captaincore' ),
		'not_found_in_trash'    => __( 'Not found in Trash', 'captaincore' ),
		'featured_image'        => __( 'Featured Image', 'captaincore' ),
		'set_featured_image'    => __( 'Set featured image', 'captaincore' ),
		'remove_featured_image' => __( 'Remove featured image', 'captaincore' ),
		'use_featured_image'    => __( 'Use as featured image', 'captaincore' ),
		'insert_into_item'      => __( 'Insert into process', 'captaincore' ),
		'uploaded_to_this_item' => __( 'Uploaded to this process', 'captaincore' ),
		'items_list'            => __( 'Processes list', 'captaincore' ),
		'items_list_navigation' => __( 'Processes list navigation', 'captaincore' ),
		'filter_items_list'     => __( 'Filter items list', 'captaincore' ),
	);
	$capabilities = array(
		'edit_post'          => 'website_edit_post',
		'read_post'          => 'website_read_post',
		'delete_post'        => 'website_delete_post',
		'edit_posts'         => 'website_edit_posts',
		'edit_others_posts'  => 'website_edit_others_posts',
		'publish_posts'      => 'website_publish_posts',
		'read_private_posts' => 'website_read_private_posts',
	);
	$args         = array(
		'label'               => __( 'Process', 'captaincore' ),
		'description'         => __( 'Process Description', 'captaincore' ),
		'labels'              => $labels,
		'supports'            => array( 'title', 'editor', 'author', 'thumbnail', 'revisions' ),
		'hierarchical'        => false,
		'public'              => true,
		'show_ui'             => true,
		'show_in_menu'        => false,
		'menu_position'       => 5,
		'menu_icon'           => 'dashicons-controls-repeat',
		'show_in_admin_bar'   => true,
		'show_in_nav_menus'   => true,
		'can_export'          => true,
		'has_archive'         => false,
		'exclude_from_search' => true,
		'publicly_queryable'  => true,
		'capability_type'     => 'page',
		'capabilities'        => $capabilities,
		'map_meta_cap'        => true,
	);
	register_post_type( 'captcore_process', $args );

}
add_action( 'init', 'process_post_type', 0 );

// Register Custom Post Type
function process_log_post_type() {

	$labels       = array(
		'name'                  => _x( 'Process Logs', 'Post Type General Name', 'captaincore' ),
		'singular_name'         => _x( 'Process Log', 'Post Type Singular Name', 'captaincore' ),
		'menu_name'             => __( 'Process Logs', 'captaincore' ),
		'name_admin_bar'        => __( 'Process Log', 'captaincore' ),
		'archives'              => __( 'Process Log Archives', 'captaincore' ),
		'parent_item_colon'     => __( 'Parent Process Log:', 'captaincore' ),
		'all_items'             => __( 'Process Logs', 'captaincore' ),
		'add_new_item'          => __( 'Add New Process Log', 'captaincore' ),
		'add_new'               => __( 'Add New', 'captaincore' ),
		'new_item'              => __( 'New Process Log', 'captaincore' ),
		'edit_item'             => __( 'Edit Process Log', 'captaincore' ),
		'update_item'           => __( 'Update Process Log', 'captaincore' ),
		'view_item'             => __( 'View Process Log', 'captaincore' ),
		'search_items'          => __( 'Search Process Logs', 'captaincore' ),
		'not_found'             => __( 'Not found', 'captaincore' ),
		'not_found_in_trash'    => __( 'Not found in Trash', 'captaincore' ),
		'featured_image'        => __( 'Featured Image', 'captaincore' ),
		'set_featured_image'    => __( 'Set featured image', 'captaincore' ),
		'remove_featured_image' => __( 'Remove featured image', 'captaincore' ),
		'use_featured_image'    => __( 'Use as featured image', 'captaincore' ),
		'insert_into_item'      => __( 'Insert into process log', 'captaincore' ),
		'uploaded_to_this_item' => __( 'Uploaded to this process log', 'captaincore' ),
		'items_list'            => __( 'Process Logs list', 'captaincore' ),
		'items_list_navigation' => __( 'Process Logs list navigation', 'captaincore' ),
		'filter_items_list'     => __( 'Filter items list', 'captaincore' ),
	);
	$capabilities = array(
		'edit_post'          => 'website_edit_post',
		'read_post'          => 'website_read_post',
		'delete_post'        => 'website_delete_post',
		'edit_posts'         => 'website_edit_posts',
		'edit_others_posts'  => 'website_edit_others_posts',
		'publish_posts'      => 'website_publish_posts',
		'read_private_posts' => 'website_read_private_posts',
	);
	$args         = array(
		'label'               => __( 'Process Log', 'captaincore' ),
		'description'         => __( 'Process Log Description', 'captaincore' ),
		'labels'              => $labels,
		'supports'            => array( 'author', 'thumbnail', 'revisions' ),
		'hierarchical'        => false,
		'public'              => false,
		'show_ui'             => true,
		'show_in_menu'        => false,
		'menu_position'       => 5,
		'menu_icon'           => 'dashicons-media-spreadsheet',
		'show_in_admin_bar'   => true,
		'show_in_nav_menus'   => true,
		'can_export'          => true,
		'has_archive'         => true,
		'exclude_from_search' => true,
		'publicly_queryable'  => false,
		'capability_type'     => 'page',
		'show_in_rest'        => true,
		'capabilities'        => $capabilities,
		'map_meta_cap'        => true,
	);
	register_post_type( 'captcore_processlog', $args );

}
add_action( 'init', 'process_log_post_type', 0 );

// Register Custom Post Type
function process_item_log_post_type() {

	$labels       = array(
		'name'                  => _x( 'Process Item Logs', 'Post Type General Name', 'captaincore' ),
		'singular_name'         => _x( 'Process Item Log', 'Post Type Singular Name', 'captaincore' ),
		'menu_name'             => __( 'Process Item Logs', 'captaincore' ),
		'name_admin_bar'        => __( 'Process Item Log', 'captaincore' ),
		'archives'              => __( 'Process Item Log Archives', 'captaincore' ),
		'parent_item_colon'     => __( 'Parent Process Item Log:', 'captaincore' ),
		'all_items'             => __( 'Process Item Logs', 'captaincore' ),
		'add_new_item'          => __( 'Add New Process Item Log', 'captaincore' ),
		'add_new'               => __( 'Add New', 'captaincore' ),
		'new_item'              => __( 'New Process Item Log', 'captaincore' ),
		'edit_item'             => __( 'Edit Process Item Log', 'captaincore' ),
		'update_item'           => __( 'Update Process Item Log', 'captaincore' ),
		'view_item'             => __( 'View Process Item Log', 'captaincore' ),
		'search_items'          => __( 'Search Process Item Logs', 'captaincore' ),
		'not_found'             => __( 'Not found', 'captaincore' ),
		'not_found_in_trash'    => __( 'Not found in Trash', 'captaincore' ),
		'featured_image'        => __( 'Featured Image', 'captaincore' ),
		'set_featured_image'    => __( 'Set featured image', 'captaincore' ),
		'remove_featured_image' => __( 'Remove featured image', 'captaincore' ),
		'use_featured_image'    => __( 'Use as featured image', 'captaincore' ),
		'insert_into_item'      => __( 'Insert into process item log', 'captaincore' ),
		'uploaded_to_this_item' => __( 'Uploaded to this process item log', 'captaincore' ),
		'items_list'            => __( 'Process Item Logs list', 'captaincore' ),
		'items_list_navigation' => __( 'Process Item Logs list navigation', 'captaincore' ),
		'filter_items_list'     => __( 'Filter items list', 'captaincore' ),
	);
	$capabilities = array(
		'edit_post'          => 'website_edit_post',
		'read_post'          => 'website_read_post',
		'delete_post'        => 'website_delete_post',
		'edit_posts'         => 'website_edit_posts',
		'edit_others_posts'  => 'website_edit_others_posts',
		'publish_posts'      => 'website_publish_posts',
		'read_private_posts' => 'website_read_private_posts',
	);
	$args         = array(
		'label'               => __( 'Process Item Log', 'captaincore' ),
		'description'         => __( 'Process Item Log Description', 'captaincore' ),
		'labels'              => $labels,
		'supports'            => array( 'author', 'thumbnail', 'revisions' ),
		'hierarchical'        => false,
		'public'              => false,
		'show_ui'             => true,
		'show_in_menu'        => false,
		'menu_position'       => 5,
		'menu_icon'           => 'dashicons-media-spreadsheet',
		'show_in_admin_bar'   => true,
		'show_in_nav_menus'   => true,
		'can_export'          => true,
		'has_archive'         => true,
		'exclude_from_search' => true,
		'publicly_queryable'  => false,
		'capability_type'     => 'page',
		'show_in_rest'        => true,
		'capabilities'        => $capabilities,
		'map_meta_cap'        => true,
	);
	register_post_type( 'captcore_processitem', $args );

}
// add_action( 'init', 'process_item_log_post_type', 0 );

// Register Custom Post Type
function snapshot_post_type() {

	$labels       = array(
		'name'                  => _x( 'Snapshots', 'Post Type General Name', 'captaincore' ),
		'singular_name'         => _x( 'Snapshots', 'Post Type Singular Name', 'captaincore' ),
		'menu_name'             => __( 'Snapshots', 'captaincore' ),
		'name_admin_bar'        => __( 'Snapshot', 'captaincore' ),
		'archives'              => __( 'Item Archives', 'captaincore' ),
		'parent_item_colon'     => __( 'Parent Item:', 'captaincore' ),
		'all_items'             => __( 'Snapshots', 'captaincore' ),
		'add_new_item'          => __( 'Add New Item', 'captaincore' ),
		'add_new'               => __( 'Add New', 'captaincore' ),
		'new_item'              => __( 'New Item', 'captaincore' ),
		'edit_item'             => __( 'Edit Item', 'captaincore' ),
		'update_item'           => __( 'Update Item', 'captaincore' ),
		'view_item'             => __( 'View Item', 'captaincore' ),
		'search_items'          => __( 'Search Item', 'captaincore' ),
		'not_found'             => __( 'Not found', 'captaincore' ),
		'not_found_in_trash'    => __( 'Not found in Trash', 'captaincore' ),
		'featured_image'        => __( 'Featured Image', 'captaincore' ),
		'set_featured_image'    => __( 'Set featured image', 'captaincore' ),
		'remove_featured_image' => __( 'Remove featured image', 'captaincore' ),
		'use_featured_image'    => __( 'Use as featured image', 'captaincore' ),
		'insert_into_item'      => __( 'Insert into item', 'captaincore' ),
		'uploaded_to_this_item' => __( 'Uploaded to this item', 'captaincore' ),
		'items_list'            => __( 'Items list', 'captaincore' ),
		'items_list_navigation' => __( 'Items list navigation', 'captaincore' ),
		'filter_items_list'     => __( 'Filter items list', 'captaincore' ),
	);
	$capabilities = array(
		'edit_post'          => 'website_edit_post',
		'read_post'          => 'website_read_post',
		'delete_post'        => 'website_delete_post',
		'edit_posts'         => 'website_edit_posts',
		'edit_others_posts'  => 'website_edit_others_posts',
		'publish_posts'      => 'website_publish_posts',
		'read_private_posts' => 'website_read_private_posts',
	);
	$args         = array(
		'label'               => __( 'Snapshot', 'captaincore' ),
		'description'         => __( 'Snapshot Description', 'captaincore' ),
		'labels'              => $labels,
		'supports'            => array(),
		'hierarchical'        => false,
		'public'              => false,
		'show_ui'             => true,
		'show_in_menu'        => false,
		'menu_position'       => 5,
		'menu_icon'           => 'dashicons-backup',
		'show_in_admin_bar'   => true,
		'show_in_nav_menus'   => true,
		'can_export'          => true,
		'has_archive'         => false,
		'exclude_from_search' => true,
		'publicly_queryable'  => false,
		'capability_type'     => 'page',
		'capabilities'        => $capabilities,
		'map_meta_cap'        => true,
	);
	register_post_type( 'captcore_snapshot', $args );

}
add_action( 'init', 'snapshot_post_type', 0 );

function captaincore_website_tabs() {

	$screen = get_current_screen();

	// Only edit post screen:
	$pages = array( 'captcore_website', 'captcore_customer', 'captcore_contact', 'captcore_domain', 'captcore_changelog', 'captcore_process', 'captcore_processlog', 'captcore_snapshot', 'captcore_quicksave' );
	if ( in_array( $screen->post_type, $pages ) ) {
		// Before:
		add_action(
			'all_admin_notices', function() {
					include 'includes/admin-website-tabs.php';
				echo '';
			}
		);

		// After:
		add_action(
			'in_admin_footer', function() {
				echo '';
			}
		);
	}
};

add_action( 'load-post-new.php', 'captaincore_website_tabs' );
add_action( 'load-edit.php', 'captaincore_website_tabs' );
add_action( 'load-post.php', 'captaincore_website_tabs' );

function my_remove_extra_product_data( $data, $post, $context ) {
	// make sure you've got the right custom post type
	if ( 'captcore_website' !== $data['type'] ) {
		return $data;
	}
	// now proceed as you saw in the other examples
	if ( $context !== 'view' || is_wp_error( $data ) ) {
		return $data;
	}
	// unset unwanted fields
	unset( $data['link'] );

	// finally, return the filtered data
	return $data;
}

// make sure you use the SAME filter hook as for regular posts
add_filter( 'json_prepare_post', 'my_remove_extra_product_data', 12, 3 );

// meta fields functions
function slug_get_post_meta_array( $object, $field_name, $request ) {
	return get_field( $field_name, $object['id'] );
}
function slug_get_post_meta_revisions( $object, $field_name, $request ) {
	$gist = get_post_meta( $object['id'], $field_name );
	return $gist;
}
function slug_get_post_meta_cb( $object, $field_name, $request ) {
	return get_post_meta( $object['id'], $field_name );
}
function slug_get_paid_by( $object, $field_name, $request ) {
	$post_title = "";
	$post_id = get_post_meta( $object['id'], $field_name );
	if ( !isset( $post_id ) || !isset( $post_id[0] ) || !isset( $post_id[0][0] ) ) {
		return $post_title;
	}
	$post_id = $post_id[0][0];
	if ( $post_id ) {
		$post_title = get_the_title( $post_id );
	}
	return $post_title;
}
function slug_update_post_meta_cb( $value, $object, $field_name ) {
	if ( is_object( $object ) ) {
		$object_id = $object->ID;
	} else {
		$object_id = $object['id'];
	}
	return update_post_meta( $object_id, $field_name, $value );
}

function slug_get_paid_by_me( $object, $field_name, $request ) {

	$paid_by_me = array();
	$post_id    = $object['id'];

	$websites = get_posts(
		array(
			'post_type'      => 'captcore_customer',
			'posts_per_page' => '-1',
			'meta_query'     => array(
				'relation' => 'AND',
				array(
					'key'     => 'paid_by', // name of custom field
					'value'   => '"' . $post_id . '"', // matches exactly "123", not just 123. This prevents a match for "1234"
					'compare' => 'LIKE',
				),
				array(
					'key'     => 'status',
					'value'   => 'cancelled',
					'compare' => '!=',
				),
			),
		)
	);

	if ( $websites ) :

		foreach ( $websites as $website ) :
			$domain = get_the_title( $website->ID );
			$data   = array(
				'id'           => $website->ID,
				'website'      => $domain,
				'addons'       => get_field( 'addons', $website->ID ),
				'price'        => get_field( 'hosting_price', $website->ID ),
				'visits'       => get_field( 'visits', $website->ID ),
				'storage'      => get_field( 'storage', $website->ID ),
				'total_price'  => get_field( 'total_price', $website->ID ),
				'hosting_plan' => get_field( 'hosting_plan', $website->ID ),
			);

			array_push( $paid_by_me, $data );

		endforeach;
	endif;

	return $paid_by_me;

}

function slug_get_process_description( $object, $field_name, $request ) {

	$Parsedown = new Parsedown();
	$description = get_post_meta( $object['id'], $field_name );

	if ( $description[0] ) {
		$description = $Parsedown->text( $description[0] );
	} else {
		// ACF field should be in an array if not then return nothing via API.
		$description = '';
	}

	return $description;
}

function slug_get_process( $object, $field_name, $request ) {
	$process_id = get_post_meta( $object['id'], $field_name );
	$process_id = $process_id[0][0];

	return get_the_title( $process_id );
}

function slug_get_server( $object, $field_name, $request ) {
	$server_id = get_post_meta( $object['id'], $field_name );
	$server_id = $server_id[0][0];

	$provider_field = get_field_object( 'field_5803a848814c7' );
	$provider_value = get_field( 'provider', $server_id );

	if ( $server_id ) {

		$server = array(
			'address'  => get_field( 'address', $server_id ),
			'provider' => $provider_field['choices'][ $provider_value ],
		);

	} else {
		$server = '';
	}

	return $server;
}

function my_relationship_query( $args, $field, $post ) {
	// increase the posts per page
	$args['posts_per_page'] = 25;
	$args['meta_query']     = array(
		array(
			'key'     => 'partner',
			'value'   => true,
			'compare' => '=',
		),
	);

	return $args;
}

// filter for a specific field based on it's key
add_filter( 'acf/fields/relationship/query/key=field_56181a38cf6e3', 'my_relationship_query', 10, 3 );

function captaincore_website_relationship_result( $title, $post, $field, $post_id ) {

	// load a custom field from this $object and show it in the $result
	$status = get_field( 'status', $post->ID );

	if ( $status == 'closed' ) {

		// append to title
		$title .= ' (closed)';

	}

	// return
	return $title;

}

// filter for a specific field based on it's name
add_filter( 'acf/fields/relationship/result/name=website', 'captaincore_website_relationship_result', 10, 4 );

function captaincore_subscription_relationship_result( $title, $post, $field, $post_id ) {

	// load a custom field from this $object and show it in the $result
	$subscription = wcs_get_subscription( $post->ID );
	$user         = $subscription->get_user();

	// append to title
	$title = 'Subscription #' . $post->ID . ' - (' . $subscription->get_formatted_order_total() . ") $user->first_name $user->last_name $user->user_email";

	// return
	return $title;

}

// filter for a specific field based on it's name
add_filter( 'acf/fields/relationship/result/name=subscription', 'captaincore_subscription_relationship_result', 10, 4 );

function captaincore_subscription_relationship_query( $args, $field, $post ) {

		// Current search term
		$search_term    = $args['s'];
		$found_user_ids = array();

		// Search users
		$search_users = get_users( array( 'search' => '*' . $search_term . '*' ) );

	foreach ( $search_users as $found_user ) {
		$found_user_ids[] = $found_user->ID;
	}

		// Array of WP_User objects.
	foreach ( $blogusers as $user ) {
		echo '<span>' . esc_html( $user->user_email ) . '</span>';
	}

	if ( $found_user_ids ) {
		// Check for Subscriptions assigned to Users
		$args['meta_query'] = array(
			array(
				'key'     => '_customer_user',
				'value'   => $found_user_ids,
				'type'    => 'numeric',
				'compare' => 'IN',
			),
		);
	} else {
		$args['posts_per_page'] = 0;
	}
		// Remove standard search
		unset( $args['s'] );

		// print_r( $args ); wp_die();
	return $args;
}

// filter for a specific field based on it's key
add_filter( 'acf/fields/relationship/query/name=subscription', 'captaincore_subscription_relationship_query', 10, 3 );

// Validate domain is unique
add_action( 'acf/validate_save_post', 'my_acf_validate_save_post', 10, 0 );

function my_acf_validate_save_post() {

	// Runs only when creating a new domain post.
	if ( $_POST['post_type'] == 'domain' ) {

		$post_id = $_POST['post_ID'];
		$domain  = $_POST['post_title'];

		// Check for duplicate domain.
		$domain_exists = get_posts(
			array(
				'title'          => $domain,
				'post_type'      => 'captcore_domain',
				'posts_per_page' => '-1',
				'post_status'    => 'publish',
				'fields'         => 'ids',
			)
		);

		 // Remove current ID from results
		if ( ( $key = array_search( $post_id, $domain_exists ) ) !== false ) {
			unset( $domain_exists[ $key ] );
		}

		 // If results still exists then give an error
		if ( count( $domain_exists ) > 0 ) {
				acf_add_validation_error( '', 'Domain has already been added.' );
		}
	}

}


// run before ACF saves the $_POST['acf'] data
add_action( 'acf/save_post', 'captaincore_acf_save_post_before', 1 );
function captaincore_acf_save_post_before( $post_id ) {

	if ( get_post_type( $post_id ) == 'captcore_website' ) {

		if ( get_field( 'launch_date', $post_id ) == '' and $_POST['acf']['field_52d167f4ac39e'] == '' ) {
			// No date was entered for Launch Date, assign to today.
			$_POST['acf']['field_52d167f4ac39e'] = date( 'Ymd' );
		}
	}

}

function acf_load_color_field_choices( $field ) {

	global $woocommerce;

	// reset choices
	$field['choices'] = array();

	// Args
	$args = array(
		'status'         => array( 'draft', 'pending', 'private', 'publish' ),
		'type'           => array_merge( array_keys( wc_get_product_types() ) ),
		'parent'         => null,
		'sku'            => '',
		'category'       => array(),
		'tag'            => array(),
		'limit'          => get_option( 'posts_per_page' ),
		'offset'         => null,
		'page'           => 1,
		'include'        => array(),
		'exclude'        => array(),
		'orderby'        => 'date',
		'order'          => 'DESC',
		'return'         => 'objects',
		'paginate'       => false,
		'shipping_class' => array(),
	);

	// List all products
	$products = wc_get_products( $args );

	$choices = array();
	foreach ( $products as $product ) {

		if ( $product->get_type() == 'variable-subscription' or $product->get_type() == 'variable' ) {
			$variations = $product->get_available_variations();

			foreach ( $variations as $variation ) {
				// print_r($variation);
				$id         = $variation['id'];
				$name       = $variation['name'];
				$attributes = $variation['attributes'];
				$data       = array(
					'id'         => $id,
					'name'       => $name,
					'attributes' => $attributes,
				);

				array_push( $choices, $data );

			}
		} else {
			$id   = $product->get_id();
			$name = $product->get_title();
			$data = array(
				'id'   => $id,
				'name' => $name,
			);
			array_push( $choices, $data );
		}

		// echo "<pre>";
		// print_r();
		// echo "</pre>";
	}

	// loop through array and add to field 'choices'
	if ( is_array( $choices ) ) {

		foreach ( $choices as $choice ) {

			$attributes           = $choice['attributes'];
			$formatted_attributes = '';
			foreach ( $attributes as $attribute ) {
				$formatted_attributes .= ' - ' . $attribute;
			}

			$field['choices'][ $choice['id'] ] = $choice['name'] . $formatted_attributes;

		}
	}

	// return the field
	return $field;

}

// add_filter( 'acf/load_field/key=field_590681f3c0775', 'acf_load_color_field_choices' );

// run after ACF saves
add_action( 'acf/save_post', 'captaincore_acf_save_post_after', 20 );
function captaincore_acf_save_post_after( $post_id ) {

	if ( get_post_type( $post_id ) == 'captcore_website' ) {
		$customer      = get_field( 'customer', $post_id );
		$visits        = get_field( 'visits', $post_id );
		$status        = get_field( 'status', $post_id );
		$total         = 0;
		$addon_total   = 0;

		
		if ( $customer == '' ) {

			// No customer found, generate and assign the customer. Create customer object.
			$my_post = array(
				'post_title'  => get_the_title( $post_id ),
				'post_type'   => 'captcore_customer',
				'post_status' => 'publish',
				'post_author' => 1,
			);

			// Insert the post into the database
			$customer_post_id = wp_insert_post( $my_post );

			// Set customer status as active
			update_field( 'field_561936147136b', "active", $customer_post_id );
			
			// Link website to customer
			update_field( 'field_56181a1fcf6e2', $customer_post_id, $post_id );

		} else {

			// Load customer data
			$customer_id = $customer[0];

			if ( is_array( $customer ) ) {
				$customer_id = $customer[0];
			}

			$billing_terms      = get_field( 'billing_terms', $customer_id );
			$billing_date       = date( 'Y-m-d', strtotime( get_field( 'billing_date', $customer_id ) ) );
			$billing_date_month = date( 'm', strtotime( get_field( 'billing_date', $customer_id ) ) );
			$current_month      = date( 'm' );

			// If yearly then calculate date of beginning of current pay period
			if ( $billing_terms == 'year' ) {
				$billing_period = '';
			}
			// If monthly then calculate date of beginning of current pay period
			if ( $billing_terms == 'month' ) {
				$billing_period = '';
			}
			// If quarterly then calculate date of beginning of current pay period
			if ( $billing_terms == 'quarter' ) {
				$billing_period = '';
			}
		}

		// Update customer usage
		$total_sites   = 0;
		$total_visits  = 0;
		$total_storage = 0;

			/*
			*  Query posts for a relationship value.
			*  This method uses the meta_query LIKE to match the string "123" to the database value a:1:{i:0;s:3:"123";} (serialized array)
			*/

			$websites = get_posts(
				array(
					'post_type'      => 'captcore_website',
					'posts_per_page' => '-1',
					'meta_query'     => array(
						'relation' => 'AND',
						array(
							'key'     => 'status', // name of custom field
							'value'   => 'active', // matches exactly "123", not just 123. This prevents a match for "1234"
							'compare' => '=',
						),
						array(
							'key'     => 'customer', // name of custom field
							'value'   => '"' . $customer_id . '"', // matches exactly "123", not just 123. This prevents a match for "1234"
							'compare' => 'LIKE',
						),
					),
				)
			);

		$db_environments = new CaptainCore\environments();

			if ( $websites ) :
				foreach ( $websites as $website ) :

				$environments = $db_environments->fetch_environments( $website->ID );

				$storage = $environments[0]->storage;
				$visits = $environments[0]->visits;

				$total_storage = $total_storage + $storage;
				$total_visits  = $total_visits + $visits;
				$total_sites++;

				 endforeach;
			endif;

		update_field( 'storage', $total_storage, $customer_id );
		update_field( 'visits', $total_visits, $customer_id );
		update_field( 'sites', $total_sites, $customer_id );

	}
	if ( get_post_type( $post_id ) == 'captcore_customer' ) {
		$custom        = get_post_custom( $post_id );
		$hosting_price = $custom['hosting_price'][0];
		$addons        = get_field( 'addons', $post_id );

		// check if the repeater field has rows of data
		if ( have_rows( 'addons' ) ) :

			// loop through the rows of data
			while ( have_rows( 'addons' ) ) :
				the_row();

				// vars
				$name        = get_sub_field( 'name' );
				$price       = get_sub_field( 'price' );
				$addon_total = $price + $addon_total;

			endwhile;

		else :

			// no rows found
		endif;
		$total_price = $hosting_price + $addon_total;
		update_field( 'field_56181aaed39a9', $total_price, $post_id );
	}

	if ( get_post_type( $post_id ) == 'captcore_domain' ) {

		if ( get_field( 'domain_id', $post_id ) == '' ) {

			$domainname = get_the_title( $post_id );

			// Load domains from transient
			$constellix_all_domains = get_transient( 'constellix_all_domains' );

			// If empty then update transient with large remote call
			if ( empty( $constellix_all_domains ) ) {

				$constellix_all_domains = constellix_api_get( 'domains' );

				// Save the API response so we don't have to call again until tomorrow.
				set_transient( 'constellix_all_domains', $constellix_all_domains, HOUR_IN_SECONDS );

			}

			// Search API for domain ID
			foreach ( $constellix_all_domains as $domain ) {
				if ( $domainname == $domain->name ) {
					$domain_id = $domain->id;
				}
			}

			if ( $domain_id ) {
				// Found domain ID from API so update post
				update_field( 'domain_id', $domain_id, $post_id );
			} else {
				// Generate a new domain zone and adds the new domain ID to the post
				$post = array( 'names' => array( $domainname ) );

				$response = constellix_api_post( 'domains', $post );

				foreach ( $response as $domain ) {
					// Capture new domain IDs from $response
					$domain_id = $domain->id;
				}
				update_field( 'domain_id', $domain_id, $post_id );
			}
			// Assign domain to customers
			$args        = array(
				'title'     => $domainname,
				'post_type' => 'captcore_website',
			);
			$website     = get_posts( $args );
			$website_id  = $website[0]->ID;
			$customer    = get_field( 'customer', $website_id );
			$customer_id = $customer[0];
			$domains     = get_field( 'domains', $customer_id );

			// Add domains to customer if not already assigned
			if ( ! in_array( $post_id, $domains ) ) {
				$domains[] = $post_id;
				update_field( 'domains', $domains, $customer_id );
			}
		}
	}

	if ( get_post_type( $post_id ) == 'captcore_processlog' ) {
		$custom     = get_post_custom( $post_id );
		$process_id = get_field( 'process', $post_id );
		$process_id = $process_id[0];
		$roles      = has_term( 'maintenance', 'process_role', $process_id ) + has_term( 'growth', 'process_role', $process_id ) + has_term( 'support', 'process_role', $process_id );
		// Check if process is under the maintenance, growth or support role.
		if ( $roles > 0 ) {
			// Making log public which will be viewable over WP REST API
			update_field( 'field_584dc76e7eec2', '1', $post_id );
		} else {
			// Make it private
			update_field( 'field_584dc76e7eec2', '', $post_id );
		}
	}

	if ( get_post_type( $post_id ) == 'captcore_contact' ) {

		$first_name = get_field( 'first_name', $post_id );
		$last_name  = get_field( 'last_name', $post_id );
		$email      = get_field( 'email', $post_id );

		$new_title = '';

		if ( $first_name and $last_name ) {
			$new_title = $first_name . ' ' . $last_name . ' (' . $email . ')';
		} else {
			$new_title = $email;
		}

		// Update post
		  $my_post = array(
			  'ID'         => $post_id,
			  'post_title' => $new_title,
		  );

		// Update the post title
		wp_update_post( $my_post );

	}
}

function captaincore_client_options_func( WP_REST_Request $request ) {

	$data = array(
		'profile_image'          => get_field( 'profile_image', 'option' ),
		'description'            => get_field( 'description', 'option' ),
		'contact_info'           => get_field( 'contact_info', 'option' ),
		'business_name'          => get_field( 'business_name', 'option' ),
		'business_tagline'       => get_field( 'business_tagline', 'option' ),
		'business_link'          => get_field( 'business_link', 'option' ),
		'business_logo'          => get_field( 'business_logo', 'option' ),
		'hosting_dashboard_link' => get_field( 'hosting_dashboard_link', 'option' ),
		'preinstall_plugins'     => get_field( 'preinstall_plugins', 'option' ),
	);

	return $data;

}

function captaincore_api_func( WP_REST_Request $request ) {

	$post = json_decode( file_get_contents( 'php://input' ) );

	$archive             = $post->archive;
	$command             = $post->command;
	$environment         = $post->environment;
	$storage             = $post->storage;
	$visits              = $post->visits;
	$email               = $post->email;
	$server              = $post->server;
	$core                = $post->core;
	$plugins             = $post->plugins;
	$themes              = $post->themes;
	$users               = $post->users;
	$fathom              = $post->fathom;
	$home_url            = $post->home_url;
	$subsite_count       = $post->subsite_count;
	$git_commit          = $post->git_commit;
	$git_status          = trim( base64_decode( $post->git_status ) );
	$token_key           = $post->token_key;
	$data                = $post->data;
	$site_id             = $post->site_id;
	$user_id             = $post->user_id;
	$notes               = $post->notes;

	// Error if token not valid
	if ( $post->token != CAPTAINCORE_CLI_TOKEN ) {
		// Create the response object
		return new WP_Error( 'token_invalid', 'Invalid Token', array( 'status' => 404 ) );
	}

	// Error if site not valid
	if ( get_post_type( $site_id ) != "captcore_website" ) {
		// Create the response object
		return new WP_Error( 'command_invalid', 'Invalid Command', array( 'status' => 404 ) );
	}

	$site_name   = get_field( 'site', $site_id );
	$domain_name = get_the_title( $site_id );

	if ( $environment == "production" ) {
		$environment_id = get_field( 'environment_production_id', $site_id );
	}
	if ( $environment == "staging" ) {
		$environment_id = get_field( 'environment_staging_id', $site_id );
	}

	// Copy site
	if ( $command == 'copy' and $email ) {

		$site_source      = get_the_title( $post->site_source_id );
		$site_destination = get_the_title( $post->site_destination_id );
		$business_name    = get_field('business_name', 'option');

		// Send out completed email notice
		$to      = $email;
		$subject = "$business_name - Copy site ($site_source) to ($site_destination) completed";
		$body    = "Completed copying $site_source to $site_destination.<br /><br /><a href=\"http://$site_destination\">$site_destination</a>";
		$headers = array( 'Content-Type: text/html; charset=UTF-8' );

		wp_mail( $to, $subject, $body, $headers );

		echo 'copy-site email sent';

	}

	// Production deploy to staging
	if ( $command == 'production-to-staging' and $email ) {

		$business_name = get_field('business_name', 'option');
		$domain_name = get_the_title( $site_id );
		$db          = new CaptainCore\Site;
		$site        = $db->get( $site_id );
		$link        = $site->environments[1]["link"];

		// Send out completed email notice
		$to      = $email;
		$subject = "$business_name - Deploy to Staging ($domain_name)";
		$body    = 'Deploy to staging completed for ' . $domain_name . '.<br /><br /><a href="' . $link . '">' . $link . '</a>';
		$headers = array( 'Content-Type: text/html; charset=UTF-8' );

		wp_mail( $to, $subject, $body, $headers );

		echo 'production-to-staging email sent';

	}

	// Kinsta staging deploy to production
	if ( $command == 'staging-to-production' and $email ) {

		$business_name = get_field('business_name', 'option');
		$domain_name = get_the_title( $site_id );
		$db          = new CaptainCore\Site;
		$site        = $db->get( $site_id );
		$link        = $site->environments[0]["link"];

		// Send out completed email notice
		
		$to      = $email;
		$subject = "$business_name - Deploy to Production ($domain_name)";
		$body    = 'Deploy to production completed for ' . $domain_name . '.<br /><br /><a href="' . $link . '">' . $link . '</a>';
		$headers = array( 'Content-Type: text/html; charset=UTF-8' );

		wp_mail( $to, $subject, $body, $headers );

		echo 'staging-to-production email sent';

	}

	// Generate a new snapshot.
	if ( $command == 'snapshot' and $archive and $storage ) {

		if ( $environment == "production" ) {
			$environment_id = get_field( 'environment_production_id', $site_id );
		}
		if ( $environment == "staging" ) {
			$environment_id = get_field( 'environment_staging_id', $site_id );
		}

		if ( $user_id == "") {
			$user_id = "0";
		}

		$time_now = date("Y-m-d H:i:s");
		$in_24hrs = date("Y-m-d H:i:s", strtotime ( date("Y-m-d H:i:s")."+24 hours" ) );
		$token    = bin2hex( openssl_random_pseudo_bytes( 16 ) );
		$snapshot = array(
			'user_id'        => $user_id,
			'site_id'        => $site_id,
			'environment_id' => $environment_id,
			'snapshot_name'  => $archive,
			'created_at'     => $time_now,
			'storage'        => $storage,
			'email'          => $email,
			'notes'          => $notes,
			'expires_at'     => $in_24hrs,
			'token'          => $token
		);

		$db = new CaptainCore\snapshots();
		$snapshot_id = $db->insert( $snapshot );

		// Send out snapshot email
		captaincore_download_snapshot_email( $snapshot_id );

	}

	// Load Token Key
	if ( $command == 'token' and isset( $token_key ) ) {

		// defines the ACF keys to use
		$token_id = 'field_52d16819ac39f';

		// update the repeater
		update_field( $token_id, $token_key, $site_id );
		echo "Adding token key. \n";

	}

	// Sync site data
	if ( $command == 'sync-data' and $core and $plugins and $themes and $users ) {
		
		// Updates site with latest $plugins, $themes, $core, $home_url and $users
		$environment_update = array(
			'site_id'            => $site_id,
			'environment'        => ucfirst($environment),
			'users'              => json_encode( $users ),
			'themes'             => json_encode( $themes ),
			'plugins'            => json_encode( $plugins ),
			'fathom'             => json_encode( $fathom ),
			'core'               => $core,
			'home_url'					 => $home_url,
			'subsite_count'      => $subsite_count
		);

		$time_now = date("Y-m-d H:i:s");
		$environment_update['updated_at'] = $time_now;

		$upload_dir       = wp_upload_dir();
		$screenshot_check = $upload_dir['basedir'] . "/screenshots/{$site_name}_{$site_id}/$environment/screenshot-800.png";
		if ( file_exists( $screenshot_check ) ) {
			$environment_update['screenshot'] = true;
		} else {
			$environment_update['screenshot'] = false;
		}
		$db_environments = new CaptainCore\environments();
		$db_environments->update( $environment_update, array( "environment_id" => $environment_id ) );

		$response = array( 
			"response"       => "Completed sync-data for $site_id",
			"environment_id" => $environment_id,
			"environment"    => $environment_update
		);

	}

	// Imports update log
	if ( $command == 'import-update-log' ) {

		foreach ( $data as $row ) {

			// Format for mysql timestamp format. Changes "2018-06-20-091520" to "2018-06-20 09:15:20"
			$date_formatted = substr_replace( $row->date, ' ', 10, 1 );
			$date_formatted = substr_replace( $date_formatted, ':', 13, 0 );
			$date_formatted = substr_replace( $date_formatted, ':', 16, 0 );
			$update_log     = json_encode( $row->updates );

			$new_update_log = array(
				'site_id'        => $site_id,
				'environment_id' => $environment_id,
				'update_type'    => $row->type,
				'update_log'     => $update_log,
				'created_at'     => $date_formatted,
			);

			$new_update_log_check = array(
				'site_id'    => $site_id,
				'created_at' => $date_formatted,
			);

			$db_update_logs = new CaptainCore\update_logs();

			$valid_check = $db_update_logs->valid_check( $new_update_log_check );

			// Add new update log if not added.
			if ( $valid_check ) {
				$db_update_logs->insert( $new_update_log );
			}
		}
	}

	// Imports update log
	if ( $command == 'import-quicksaves' ) {

		// If new info sent then update otherwise continue with quicksave import
		if ( $plugins &&  $themes && $users && $core && $home_url ) {
			update_field( 'field_5a9421b004ed3', wp_slash( $plugins ), $site_id );
			update_field( 'field_5a9421b804ed4', wp_slash( $themes ), $site_id );
			update_field( 'field_5b2a900c85a77', wp_slash( $users ), $site_id );
			update_field( 'field_5a9421bc04ed5', $core, $site_id );
			update_field( 'field_5a944358bf146', $home_url, $site_id );
		}

		if ( $environment == "production" ) {
			$environment_id = get_field( 'environment_production_id', $site_id );
		}
		if ( $environment == "staging" ) {
			$environment_id = get_field( 'environment_staging_id', $site_id );
		}

		foreach ( $data as $row ) {

			// Format for mysql timestamp format. Changes "1530817828" to "2018-06-20 09:15:20"
			$epoch = $row->date;
			$dt = new DateTime("@$epoch");  // convert UNIX timestamp to PHP DateTime
			$date_formatted = $dt->format('Y-m-d H:i:s'); // output = 2017-01-01 00:00:00

			$themes         = json_encode( $row->themes );
			$plugins        = json_encode( $row->plugins );

			$new_quicksave = array(
				'site_id'        => $site_id,
				'environment_id' => $environment_id,
				'created_at'     => $date_formatted,
				'git_status'     => $row->git_status,
				'git_commit'     => $row->git_commit,
				'core'           => $row->core,
				'themes'         => $themes,
				'plugins'        => $plugins,
			);

			$new_quicksave_check = array(
				'site_id'    => $site_id,
				'created_at' => $date_formatted,
			);

			$db_quicksaves = new CaptainCore\quicksaves();

			$valid_check = $db_quicksaves->valid_check( $new_quicksave_check );

			// Add new update log if not added.
			if ( $valid_check ) {
				$db_quicksaves->insert( $new_quicksave );
			}
		}
	}

	// Updates visits and storage usage
	if ( $command == 'usage-update' ) {

		if ( $environment == "production" ) {
			$environment_id = get_field( 'environment_production_id', $site_id );
		}
		if ( $environment == "staging" ) {
			$environment_id = get_field( 'environment_staging_id', $site_id );
		}
		
		// Updates site with latest $plugins, $themes, $core, $home_url and $users
		$environment = array(
			'site_id'            => $site_id,
			'environment'        => ucfirst($environment),
			'storage'            => $storage,
			'visits'             => $visits
		);

		$time_now = date("Y-m-d H:i:s");
		$environment['updated_at'] = $time_now;

		$db_environments = new CaptainCore\environments();
		$db_environments->update( $environment, array( "environment_id" => $environment_id ) );

		$response = array( 
			"response" => "Completed usage-update for $site_id",
			"environment_id" => $environment_id,
			"environment" => $environment
		);

		do_action( 'acf/save_post', $site_id ); // Runs ACF save post hooks
		return $response;
	}

	if ( $server ) {
		echo 'Server assign';
		// args
		$args = array(
			'numberposts' => 1,
			'post_type'   => 'captcore_server',
			'meta_key'    => 'address',
			'meta_value'  => $server,
		);

		// query
		$the_query = new WP_Query( $args );

		if ( $the_query->have_posts() ) :

			while ( $the_query->have_posts() ) :
				$the_query->the_post();

				$server_id = get_the_ID();

				update_field( 'field_5803aaa489114', $server_id, $site_id );

			endwhile;

		endif;

	}

	return $response;

}

function captaincore_customers_func( $request ) {

	$customers = new CaptainCore\Customers();
	$all_customers = array();

	foreach( $customers->all() as $customer ) {
		$all_customers[] = ( new CaptainCore\Customer )->get( $customer );
	}

	return $all_customers;

}

function captaincore_sites_func( $request ) {

	$sites = new CaptainCore\Sites();
	$all_sites = array();
	
	foreach( $sites->all() as $site ) {
		$all_sites[] = ( new CaptainCore\Site )->get( $site );
	}

	return $all_sites;

}

function captaincore_site_func( $request ) {

	$site_id = $request['id'];

	if ( ! captaincore_verify_permissions( $site_id ) ) {
		return new WP_Error( 'token_invalid', 'Invalid Token', array( 'status' => 403 ) );
	}

	$site = new CaptainCore\Site;
	return $site->get( $site_id );

}

function captaincore_domain_func( $request ) {

	$domain_id = $request['id'];

	if ( ! captaincore_verify_permissions_domain( $domain_id ) ) {
		return new WP_Error( 'token_invalid', 'Invalid Token', array( 'status' => 403 ) );
	}

	$domain   = constellix_api_get( "domains/$domain_id" );
	$response = constellix_api_get( "domains/$domain_id/records" );
	if ( ! $response->errors ) {
		array_multisort( array_column( $response, 'type' ), SORT_ASC, array_column( $response, 'name' ), SORT_ASC, $response );
	}

	return $response;

}

function captaincore_domains_func( $request ) {

	$domains = (new CaptainCore\Domains())->all();
	return $domains;

}

function captaincore_site_snapshots_func( $request ) {
	$site_id = $request['id'];

	if ( ! captaincore_verify_permissions( $site_id ) ) {
		return new WP_Error( 'token_invalid', 'Invalid Token', array( 'status' => 403 ) );
	}

	$db = new CaptainCore\snapshots;
	$snapshots = $db->fetch_by_environments( $site_id );
	foreach( $snapshots as $environment ) {

		foreach( $environment as $snapshot ) {
			if ( $snapshot->user_id == 0 ) {
				$user_name = "System";
			} else {
				$user_name = get_user_by( 'id', $snapshot->user_id )->display_name;
			}
			$snapshot->user = (object) [
				"user_id" => $snapshot->user_id,
				"name"    => $user_name
			];
			unset( $snapshot->user_id );
		}

	}
	return $snapshots;
}

function captaincore_site_snapshot_download_func( $request ) {
	$site_id       = $request['id'];
	$token         = $request['token'];
	$snapshot_id   = $request['snapshot_id'];
	$snapshot_name = $request['snapshot_name'] . ".zip";

	// Verify Snapshot link is valid
	$db = new CaptainCore\snapshots();
	$snapshot = $db->get( $snapshot_id );

	if ( $snapshot->snapshot_name != $snapshot_name || $snapshot->site_id != $site_id || $snapshot->token != $token ) {
		return new WP_Error( 'token_invalid', 'Invalid Token', array( 'status' => 403 ) );
	}

	$snapshot_url = captaincore_snapshot_download_link( $snapshot_id  );
	header('Location: ' . $snapshot_url);
	exit;
}

function captaincore_site_quicksaves_func( $request ) {
	$site_id = $request['id'];

	if ( ! captaincore_verify_permissions( $site_id ) ) {
		return new WP_Error( 'token_invalid', 'Invalid Token', array( 'status' => 403 ) );
	}

	$results = array();
	$db_quicksaves = new CaptainCore\quicksaves;

	$environment_id = get_field( 'environment_production_id', $site_id );
	$quicksaves = $db_quicksaves->fetch_environment( $site_id, $environment_id );

	foreach ($quicksaves as $key => $quicksave) {
		$compare_key = $key + 1;
		$quicksaves[$key]->plugins = json_decode($quicksaves[$key]->plugins);
		$quicksaves[$key]->themes = json_decode($quicksaves[$key]->themes);
		$quicksaves[$key]->view_changes = false;
		$quicksaves[$key]->view_files = [];
		$quicksaves[$key]->filtered_files = [];
		$quicksaves[$key]->loading = true;
		$quicksaves[$key]->search = "";

		// Skips compare check on oldest quicksave or if not found.
		if ( !isset($quicksaves[$compare_key]) ) {
			continue;
		}

		$compare_plugins = json_decode( $quicksaves[$compare_key]->plugins );
		$compare_themes = json_decode( $quicksaves[$compare_key]->themes );
		$plugins_names = array_column( $quicksaves[$key]->plugins, 'name' );
		$themes_names = array_column( $quicksaves[$key]->themes, 'name' );
		$compare_plugins_names = array_column( $compare_plugins, 'name' );
		$compare_themes_names = array_column( $compare_themes, 'name' );
		$removed_plugins = array_diff( $compare_plugins_names, $plugins_names );
		$removed_themes = array_diff( $compare_themes_names, $themes_names );

		foreach( $quicksaves[$key]->plugins as $plugin ) {
			$compare_plugin_key = null;

			// Check if plugin exists in previous Quicksave
			foreach( $compare_plugins as $compare_key => $compare_plugin ) {
				if ( $compare_plugin->name == $plugin->name ) {
					$compare_plugin_key = $compare_key;
				}
			}
			// If not found then mark as newly added.
			if ( is_null($compare_plugin_key) ) {
				$plugin->compare = false;
				$plugin->highlight = "new";
				continue;
			}

			if ( $plugin->version != $compare_plugins[$compare_plugin_key]->version ) {
				$plugin->compare = false;
				$plugin->changed_version = true;
			}

			if ( $plugin->status != $compare_plugins[$compare_plugin_key]->status ) {
				$plugin->compare = false;
				$plugin->changed_status = true;
			}

			if( isset($plugin->changed_status) or isset($plugin->changed_version) ) {
				continue;
			}

			// Plugin is the same
			$plugin->compare = true;
		}

		foreach( $quicksaves[$key]->themes as $theme ) {
			$compare_theme_key = null;

			// Check if plugin exists in previous Quicksave
			foreach( $compare_themes as $compare_key => $compare_theme ) {
				if ( $compare_theme->name == $theme->name ) {
					$compare_theme_key = $compare_key;
				}
			}
			// If not found then mark as newly added.
			if ( is_null($compare_theme_key) ) {
				$theme->compare = false;
				$theme->highlight = "new";
				continue;
			}

			if ( $theme->version != $compare_themes[$compare_theme_key]->version ) {
				$theme->compare = false;
				$theme->changed_version = true;
			}

			if ( $theme->status != $compare_themes[$compare_theme_key]->status ) {
				$theme->compare = false;
				$theme->changed_status = true;
			}

			if( isset($theme->changed_status) or isset($theme->changed_version) ) {
				continue;
			}

			// Theme is the same
			$theme->compare = true;
		}

		// Attached removed themes
		foreach ($removed_themes as $removed_theme) {
			$theme_key = array_search( $removed_theme, array_column( $compare_themes ,'name' ) );
			$theme = $compare_themes[$theme_key];
			$theme->compare = false;
			$theme->deleted = true;
			$quicksaves[$key]->deleted_themes[] = $theme;
		}

		// Attached removed plugins
		foreach ($removed_plugins as $removed_plugin) {
			$plugin_key = array_search( $removed_plugin, array_column( $compare_plugins ,'name' ) );
			$plugin = $compare_plugins[$plugin_key];
			$plugin->compare = false;
			$plugin->deleted = true;
			$quicksaves[$key]->deleted_plugins[] = $plugin;
		}

	}

	$results["Production"] = $quicksaves; 

	$environment_id = get_field( 'environment_staging_id', $site_id );
	$quicksaves = $db_quicksaves->fetch_environment( $site_id, $environment_id );

	foreach ($quicksaves as $key => $quicksave) {
		$compare_key = $key + 1;
		$quicksaves[$key]->plugins = json_decode($quicksaves[$key]->plugins);
		$quicksaves[$key]->themes = json_decode($quicksaves[$key]->themes);
		$quicksaves[$key]->view_changes = false;
		$quicksaves[$key]->view_files = [];
		$quicksaves[$key]->filtered_files = [];
		$quicksaves[$key]->loading = true;
		$quicksaves[$key]->search = "";

		// Skips compare check on oldest quicksave or if not found.
		if ( !isset($quicksaves[$compare_key]) ) {
			continue;
		}

		$compare_plugins = json_decode( $quicksaves[$compare_key]->plugins );
		$compare_themes = json_decode( $quicksaves[$compare_key]->themes );
		$plugins_names = array_column( $quicksaves[$key]->plugins, 'name' );
		$themes_names = array_column( $quicksaves[$key]->themes, 'name' );
		$compare_plugins_names = array_column( $compare_plugins, 'name' );
		$compare_themes_names = array_column( $compare_themes, 'name' );
		$removed_plugins = array_diff( $compare_plugins_names, $plugins_names );
		$removed_themes = array_diff( $compare_themes_names, $themes_names );

		foreach( $quicksaves[$key]->plugins as $plugin ) {
			$compare_plugin_key = null;

			// Check if plugin exists in previous Quicksave
			foreach( $compare_plugins as $compare_key => $compare_plugin ) {
				if ( $compare_plugin->name == $plugin->name ) {
					$compare_plugin_key = $compare_key;
				}
			}
			// If not found then mark as newly added.
			if ( is_null($compare_plugin_key) ) {
				$plugin->compare = false;
				$plugin->highlight = "new";
				continue;
			}

			if ( $plugin->version != $compare_plugins[$compare_plugin_key]->version ) {
				$plugin->compare = false;
				$plugin->changed_version = true;
			}

			if ( $plugin->status != $compare_plugins[$compare_plugin_key]->status ) {
				$plugin->compare = false;
				$plugin->changed_status = true;
			}

			if( isset($plugin->changed_status) or isset($plugin->changed_version) ) {
				continue;
			}

			// Plugin is the same
			$plugin->compare = true;
		}

		foreach( $quicksaves[$key]->themes as $theme ) {
			$compare_theme_key = null;

			// Check if plugin exists in previous Quicksave
			foreach( $compare_themes as $compare_key => $compare_theme ) {
				if ( $compare_theme->name == $theme->name ) {
					$compare_theme_key = $compare_key;
				}
			}
			// If not found then mark as newly added.
			if ( is_null($compare_theme_key) ) {
				$theme->compare = false;
				$theme->highlight = "new";
				continue;
			}

			if ( $theme->version != $compare_themes[$compare_theme_key]->version ) {
				$theme->compare = false;
				$theme->changed_version = true;
			}

			if ( $theme->status != $compare_themes[$compare_theme_key]->status ) {
				$theme->compare = false;
				$theme->changed_status = true;
			}

			if( isset($theme->changed_status) or isset($theme->changed_version) ) {
				continue;
			}

			// Theme is the same
			$theme->compare = true;
		}

		// Attached removed themes
		foreach ($removed_themes as $removed_theme) {
			$theme_key = array_search( $removed_theme, array_column( $compare_themes ,'name' ) );
			$theme = $compare_themes[$theme_key];
			$theme->compare = false;
			$theme->deleted = true;
			$quicksaves[$key]->deleted_themes[] = $theme;
		}

		// Attached removed plugins
		foreach ($removed_plugins as $removed_plugin) {
			$plugin_key = array_search( $removed_plugin, array_column( $compare_plugins ,'name' ) );
			$plugin = $compare_plugins[$plugin_key];
			$plugin->compare = false;
			$plugin->deleted = true;
			$quicksaves[$key]->deleted_plugins[] = $plugin;
		}

	}

	$results["Staging"] = $quicksaves; 

	return $results;
}

add_action( 'rest_api_init', 'captaincore_register_rest_endpoints' );

function captaincore_register_rest_endpoints() {

	// Custom endpoint for CaptainCore Client plugin
	register_rest_route(
		'captaincore/v1', '/client', array(
			'methods'  => 'GET',
			'callback' => 'captaincore_client_options_func',
		)
	);

	// Custom endpoint for CaptainCore API
	register_rest_route(
		'captaincore/v1', '/api', array(
			'methods'       => 'POST',
			'callback'      => 'captaincore_api_func',
			'show_in_index' => false
		)
	);

	// Custom endpoint for CaptainCore login
	register_rest_route(
		'captaincore/v1', '/login', array(
			'methods'       => 'POST',
			'callback'      => 'captaincore_login_func',
			'show_in_index' => false
		)
	);

	// Custom endpoint for CaptainCore site
	register_rest_route(
		'captaincore/v1', '/site/(?P<id>[\d]+)/quicksaves', array(
			'methods'       => 'GET',
			'callback'      => 'captaincore_site_quicksaves_func',
			'show_in_index' => false
		)
	);

	// Custom endpoint for CaptainCore site
	register_rest_route(
		'captaincore/v1', '/site/(?P<id>[\d]+)/snapshots', array(
			'methods'       => 'GET',
			'callback'      => 'captaincore_site_snapshots_func',
			'show_in_index' => false
		)
	);

	// Custom endpoint for CaptainCore site
	register_rest_route(
		'captaincore/v1', '/site/(?P<id>[\d]+)/snapshots/(?P<snapshot_id>[\d]+)-(?P<token>[a-zA-Z0-9-]+)/(?P<snapshot_name>[a-zA-Z0-9-]+)', array(
			'methods'       => 'GET',
			'callback'      => 'captaincore_site_snapshot_download_func',
			'show_in_index' => false
		)
	);

	// Custom endpoint for CaptainCore site
	register_rest_route(
		'captaincore/v1', '/site/(?P<id>[\d]+)', array(
			'methods'       => 'GET',
			'callback'      => 'captaincore_site_func',
			'show_in_index' => false
		)
	);

	// Custom endpoint for CaptainCore site
	register_rest_route(
		'captaincore/v1', '/sites/', array(
			'methods'       => 'GET',
			'callback'      => 'captaincore_sites_func',
			'show_in_index' => false
		)
	);

	// Custom endpoint for domain
	register_rest_route(
		'captaincore/v1', '/domain/(?P<id>[\d]+)', array(
			'methods'       => 'GET',
			'callback'      => 'captaincore_domain_func',
			'show_in_index' => false
		)
	);

	// Custom endpoint for domains
	register_rest_route(
		'captaincore/v1', '/domains/', array(
			'methods'       => 'GET',
			'callback'      => 'captaincore_domains_func',
			'show_in_index' => false
		)
	);

	// Custom endpoint for CaptainCore site
	register_rest_route(
		'captaincore/v1', '/customers/', array(
			'methods'       => 'GET',
			'callback'      => 'captaincore_customers_func',
			'show_in_index' => false
		)
	);

	// Add meta fields to API
	register_rest_field(
		'captcore_website', 'launch_date',
		array(
			'get_callback'    => 'slug_get_post_meta_cb',
			'update_callback' => 'slug_update_post_meta_cb',
			'schema'          => null,
		)
	);
	register_rest_field(
		'captcore_website', 'closed_date',
		array(
			'get_callback'    => 'slug_get_post_meta_cb',
			'update_callback' => 'slug_update_post_meta_cb',
			'schema'          => null,
		)
	);
	register_rest_field(
		'captcore_website', 'storage',
		array(
			'get_callback'    => 'slug_get_post_meta_cb',
			'update_callback' => 'slug_update_post_meta_cb',
			'schema'          => null,
		)
	);
	register_rest_field(
		'captcore_website', 'address',
		array(
			'get_callback'    => 'slug_get_post_meta_cb',
			'update_callback' => 'slug_update_post_meta_cb',
			'schema'          => null,
		)
	);
	register_rest_field(
		'captcore_website', 'server',
		array(
			'get_callback'    => 'slug_get_server',
			'update_callback' => 'slug_update_server',
			'schema'          => null,
		)
	);
	register_rest_field(
		'captcore_website', 'visits',
		array(
			'get_callback'    => 'slug_get_post_meta_cb',
			'update_callback' => 'slug_update_post_meta_cb',
			'schema'          => null,
		)
	);
	register_rest_field(
		'captcore_customer', 'billing_terms',
		array(
			'get_callback'    => 'slug_get_post_meta_array',
			'update_callback' => 'slug_update_post_meta_cb',
			'schema'          => null,
		)
	);
	register_rest_field(
		'captcore_customer', 'addons',
		array(
			'get_callback'    => 'slug_get_post_meta_array',
			'update_callback' => 'slug_update_post_meta_cb',
			'schema'          => null,
		)
	);
	register_rest_field(
		'captcore_customer', 'preloaded_email',
		array(
			'get_callback'    => 'slug_get_post_meta_array',
			'update_callback' => 'slug_update_post_meta_cb',
			'schema'          => null,
		)
	);
	register_rest_field(
		'captcore_customer', 'preloaded_users',
		array(
			'get_callback'    => 'slug_get_post_meta_array',
			'update_callback' => 'slug_update_post_meta_cb',
			'schema'          => null,
		)
	);
	register_rest_field(
		'captcore_customer', 'default_timezone',
		array(
			'get_callback'    => 'slug_get_post_meta_array',
			'update_callback' => 'slug_update_post_meta_cb',
			'schema'          => null,
		)
	);
	register_rest_field(
		'captcore_customer', 'default_recipes',
		array(
			'get_callback'    => 'slug_get_post_meta_array',
			'update_callback' => 'slug_update_post_meta_cb',
			'schema'          => null,
		)
	);
	register_rest_field(
		'captcore_customer', 'billing_method',
		array(
			'get_callback'    => 'slug_get_post_meta_cb',
			'update_callback' => 'slug_update_post_meta_cb',
			'schema'          => null,
		)
	);
	register_rest_field(
		'captcore_customer', 'billing_email',
		array(
			'get_callback'    => 'slug_get_post_meta_cb',
			'update_callback' => 'slug_update_post_meta_cb',
			'schema'          => null,
		)
	);
	register_rest_field(
		'captcore_customer', 'billing_date',
		array(
			'get_callback'    => 'slug_get_post_meta_cb',
			'update_callback' => 'slug_update_post_meta_cb',
			'schema'          => null,
		)
	);
	register_rest_field(
		'captcore_customer', 'storage',
		array(
			'get_callback'    => 'slug_get_post_meta_cb',
			'update_callback' => 'slug_update_post_meta_cb',
			'schema'          => null,
		)
	);
	register_rest_field(
		'captcore_customer', 'visits',
		array(
			'get_callback'    => 'slug_get_post_meta_cb',
			'update_callback' => 'slug_update_post_meta_cb',
			'schema'          => null,
		)
	);
	register_rest_field(
		'captcore_customer', 'paid_by',
		array(
			'get_callback'    => 'slug_get_paid_by',
			'update_callback' => 'slug_update_post_meta_cb',
			'schema'          => null,
		)
	);
	register_rest_field(
		'captcore_customer', 'paid_by_me',
		array(
			'get_callback'    => 'slug_get_paid_by_me',
			'update_callback' => 'slug_update_post_meta_cb',
			'schema'          => null,
		)
	);
	register_rest_field(
		'captcore_customer', 'hosting_price',
		array(
			'get_callback'    => 'slug_get_post_meta_cb',
			'update_callback' => 'slug_update_post_meta_cb',
			'schema'          => null,
		)
	);
	register_rest_field(
		'captcore_website', 'status',
		array(
			'get_callback'    => 'slug_get_post_meta_cb',
			'update_callback' => 'slug_update_post_meta_cb',
			'schema'          => null,
		)
	);
	register_rest_field(
		'captcore_customer', 'hosting_plan',
		array(
			'get_callback'    => 'slug_get_post_meta_cb',
			'update_callback' => 'slug_update_post_meta_cb',
			'schema'          => null,
		)
	);
	register_rest_field(
		'captcore_customer', 'total_price',
		array(
			'get_callback'    => 'slug_get_post_meta_cb',
			'update_callback' => 'slug_update_post_meta_cb',
			'schema'          => null,
		)
	);
	register_rest_field(
		'captcore_website', 'customer',
		array(
			'get_callback'    => 'slug_get_post_meta_cb',
			'update_callback' => 'slug_update_post_meta_cb',
			'schema'          => null,
		)
	);
	register_rest_field(
		'captcore_processlog', 'description',
		array(
			'get_callback'    => 'slug_get_process_description',
			'update_callback' => 'slug_update_post_meta_cb',
			'schema'          => null,
		)
	);
	register_rest_field(
		'captcore_processlog', 'process',
		array(
			'get_callback'    => 'slug_get_process',
			'update_callback' => 'slug_update_post_meta_cb',
			'schema'          => null,
		)
	);

};

function captaincore_login_func( WP_REST_Request $request ) {

	$post = json_decode( file_get_contents( 'php://input' ) );

	if ( $post->command == "signOut" ) {
		wp_logout();
	}

}

add_action( 'manage_posts_custom_column', 'customer_custom_columns' );
add_filter( 'manage_edit-captcore_website_columns', 'website_edit_columns' );
add_filter( 'manage_edit-captcore_customer_columns', 'customer_edit_columns' );
add_filter( 'manage_edit-captcore_customer_sortable_columns', 'customer_sortable_columns' );
add_filter( 'manage_edit-captcore_changelog_columns', 'changelog_edit_columns' );

function customer_sortable_columns( $columns ) {
	$columns['hosting_plan'] = 'hosting_plan';
	$columns['renewal']      = 'renewal';
	$columns['url']          = 'url';
	$columns['total']        = 'total';

	return $columns;
}

function changelog_edit_columns( $columns ) {
	$columns = array(
		'cb'     => '<input type="checkbox" />',
		'title'  => 'Title',
		'client' => 'Client',
		'date'   => 'Date',
	);

	return $columns;
}

function customer_edit_columns( $columns ) {
	$columns = array(
		'cb'           => '<input type="checkbox" />',
		'title'        => 'Title',
		'hosting_plan' => 'Plan',
		'renewal'      => 'Renews',
		'addons'       => 'Addons',
		'total'        => 'Total',
		'status'       => 'Status',

	);

	return $columns;
}
function website_edit_columns( $columns ) {
	$columns = array(
		'cb'       => '<input type="checkbox" />',
		'title'    => 'Title',
		'customer' => 'Customer',
		'partner'  => 'Partner',
		'launched' => 'Launched',
		'status'   => 'Status',

	);

	return $columns;
}

function captaincore_formatted_acf_value_storage( $value, $id, $column ) {

	if ( $column instanceof ACA_ACF_Column ) {
			$meta_key  = $column->get_meta_key(); // This gets the ACF field key
			$acf_field = $column->get_acf_field(); // Gets an ACF object
			$acf_type  = $column->get_acf_field_option( 'type' ); // Get the ACF field type

		if ( 'storage' == $meta_key and is_numeric( $value ) ) {
			// Alter the display $value
			$value = human_filesize( $value );
		}
	}

	return $value;
}
add_filter( 'ac/column/value', 'captaincore_formatted_acf_value_storage', 10, 3 );

function my_pre_get_posts( $query ) {

	// only modify queries for 'website' post type
	if ( $query->query_vars['post_type'] == 'captcore_customer' ) {

		$orderby = $query->get( 'orderby' );

		if ( 'hosting_plan' == $orderby ) {
			$query->set( 'orderby', 'meta_value' );
			$query->set( 'meta_key', 'hosting_plan' );
		}

		if ( 'renewal' == $orderby ) {
			$query->set( 'orderby', 'meta_value_num' );
			$query->set( 'meta_key', 'billing_date' );
		}

		if ( 'total' == $orderby ) {
			$query->set( 'orderby', 'meta_value_num' );
			$query->set( 'meta_key', 'total_price' );
		}
	}

	// return
	return $query;

}

// add_action('pre_get_posts', 'my_pre_get_posts');
function customer_custom_columns( $column ) {
	global $post;

	switch ( $column ) {
		case 'hosting_plan':
			$custom = get_post_custom();
			echo ucfirst( $custom['hosting_plan'][0] );
			break;
		case 'client':
			$clients = get_field( 'website', $post->ID );
			if ( $clients ) :
				foreach ( $clients as $p ) : // variable must NOT be called $post (IMPORTANT)
					echo edit_post_link( get_the_title( $p ), '<p>', '</p>', $p );
				endforeach;
			endif;
			break;
		case 'customer':
			$hosting_price = get_field( 'hosting_price', $post->ID );
			$customers     = get_field( 'customer', $post->ID );
			if ( $customers ) :
				foreach ( $customers as $customer ) : // variable must be called $post (IMPORTANT)
					edit_post_link( get_the_title( $customer ), '<p>', '</p>', $customer );
				endforeach;
				// wp_reset_postdata(); // IMPORTANT - reset the $post object so the rest of the page works correctly
			endif;
			// echo "<small>";
			// echo "Old Details<br />";
			// the_field('hosting_plan', $post->ID);
			// the_field('hosting_price', $post->ID);
			// $addons = get_field('addons', $post->ID);
			// print_r($addons);
			// check if the repeater field has rows of data
			// if( have_rows('addons', $post->ID) ):
			//
			// loop through the rows of data
			// while ( have_rows('addons', $post->ID) ) : the_row();
			//
			// vars
			// $name = get_sub_field('name');
			// $price = get_sub_field('price');
			// $addon_total = $price + $addon_total;
			//
			// endwhile;
			//
			// else :
			//
			// no rows found
			//
			// endif;
			// $total_price = $hosting_price + $addon_total;
			// echo $total_price;
			// the_field('addons', $post->ID);
			// the_field('billing_date', $post->ID);
			// the_field('billing_method', $post->ID);
			// the_field('billing_email', $post->ID);
			// echo "</small>";
			break;
		case 'partner':
			$partners = get_field( 'partner', $post->ID );
			if ( $partners ) :
				foreach ( $partners as $partner ) : // variable must be called $post (IMPORTANT)
					edit_post_link( get_the_title( $partner ), '<p>', '</p>', $partner );
				endforeach;
				// wp_reset_postdata(); // IMPORTANT - reset the $post object so the rest of the page works correctly
			endif;
			break;
		case 'renewal':
			date_default_timezone_set( 'America/New_York' );
			$date = get_field( 'billing_date', $post->ID );
			if ( $date ) {
				echo date( 'Y-m-d', strtotime( $date ) );
			}
			break;
		case 'launched':
			date_default_timezone_set( 'America/New_York' );
			$date = get_field( 'launch_date', $post->ID );
			if ( $date ) {
				echo date( 'Y-m-d', strtotime( $date ) );
			}
			break;
		case 'total':
			$billing_terms = get_field( 'billing_terms', $post->ID );
			$total_price   = get_field( 'total_price', $post->ID );
			echo '$' . $total_price;
			if ( isset( $billing_terms ) ) {
				echo '/' . $billing_terms;
			}
			break;
		case 'addons':
			$custom        = get_post_custom();
			$hosting_plan  = $custom['hosting_plan'][0];
			$hosting_price = $custom['hosting_price'][0];
			$addons        = get_field( 'addons', $post->ID );
			$addon_total   = 0;

			$billing_info = '<p>';
			if ( $addons ) {
				$billing_info .= count( $addons ) . ' addons';
			}
			$billing_info .= '</p>';

			echo $billing_info;
			break;
		case 'status':
			$storage = get_field( 'storage', $post->ID );
			$status  = get_field( 'status', $post->ID );
			echo ucfirst( $status );
			break;
		case 'storage':
			$storage = get_field( 'storage', $post->ID );
			if ( $storage ) {
				echo human_filesize( $storage );
			}
			break;
	}
}

function human_filesize( $bytes, $decimals = 2 ) {
	$size   = array( 'B', 'kB', 'MB', 'GB', 'TB', 'PB', 'EB', 'ZB', 'YB' );
	$factor = floor( ( strlen( $bytes ) - 1 ) / 3 );
	return sprintf( "%.{$decimals}f", $bytes / pow( 1024, $factor ) ) . @$size[ $factor ];
}

function my_relationship_result( $title, $post, $field, $post_id ) {

	// load a custom field from this $object and show it in the $result
	$process       = get_field( 'process', $post->ID );
	$process_title = $post->ID . ' - ' . get_the_title( $process[0] ) . ' - ' . get_the_author_meta( 'display_name', $post->post_author );

	// overide to title
	$title = $process_title;

	// return
	return $title;

}

// filter for every field
add_filter( 'acf/fields/relationship/result/name=captcore_processlog', 'my_relationship_result', 10, 4 );

/**
 * Deregister matching post types.
 */
function custom_unregister_theme_post_types() {
	global $wp_post_types;
	foreach ( array( 'project' ) as $post_type ) {
		if ( isset( $wp_post_types[ $post_type ] ) ) {
			unset( $wp_post_types[ $post_type ] );
		}
	}
}
add_action( 'init', 'custom_unregister_theme_post_types', 20 );

function checkApiAuth( $result ) {

	if ( ! empty( $result ) ) {
			return $result;
	}

	global $wp;

	// Strips first part of endpoint
	$endpoint_all = str_replace( 'wp-json/wp/v2/', '', $wp->request );
	if ( strpos( $wp->request, 'wp-json/captaincore/v1' ) !== false ) {
		return $result;
	}

	// Breaks apart endpoint into array
	$endpoint_all = explode( '/', $endpoint_all );

	// Grabs only the first part of the endpoint
	$endpoint = $endpoint_all[0];

	// User not logged in so do custom token auth
	if ( ! is_user_logged_in() ) {

		if ( $endpoint == 'posts' ) {
			return $result;
		}

		// custom auth on changelog endpoint, exlcuding global posts
		if ( $endpoint == 'captcore_changelog' and ! isset( $_GET['global'] ) ) {

			$token   = $_GET['token'];
			$website = $_GET['website'];

			$token_lookup = get_field( 'token', $website );

			// Token lookup
			if ( $token and $token == $token_lookup ) {
				return $result;
			}
		} elseif ( $endpoint == 'captcore_changelog' and isset( $_GET['global'] ) ) {
			// Return global changelogs for non logged in users
			return $result;
		}

		// custom auth on changelog endpoint, exlcuding global posts
		if ( $endpoint == 'captcore_processlog' ) {

			$token   = $_GET['token'];
			$website = $_GET['website'];

			$token_lookup = get_field( 'token', $website );

			// Token lookup
			if ( $token and $token == $token_lookup ) {
				return $result;
			}
		}

		// custom auth on website endpoint, excluding global posts
		if ( $endpoint == 'captcore_website' ) {

			$website_id = null;

			if ( isset($endpoint_all[1]) ) {
			$website_id = $endpoint_all[1];
			}

			$token  = $_GET['token'];
			$domain = $_GET['search'];

			// Token lookup
			// WP_Query arguments
			$args = array(
				'post_type'      => array( 'captcore_website' ),
				'name'           => $domain,
				'exact'          => true,
				'posts_per_page' => '1',
			);

			// The Query
			$query = new WP_Query( $args );

			// The Loop
			if ( $query->have_posts() ) {
				while ( $query->have_posts() ) {
					$query->the_post();
					$token_lookup = get_field( 'token' );
				}
			} else {
				// no posts found
			}

			// Restore original Post Data
			wp_reset_postdata();

			if ( $token == $token_lookup and $token <> '' and $token_lookup <> '' ) {
				return $result;
			}
		}

		// custom auth on customer endpoint, excluding global posts
		if ( $endpoint == 'captcore_customer' ) {

			$token       = $_GET['token'];
			$token_match = false;
			$id          = $endpoint_all[1];

			/*
			*  Query posts for a relationship value.
			*  This method uses the meta_query LIKE to match the string "123" to the database value a:1:{i:0;s:3:"123";} (serialized array)
			*/

			// Token lookup. Find all websites attached to customer to find a token match.
			$websites = get_posts(
				array(
					'post_type'      => 'captcore_website',
					'posts_per_page' => '-1',
					'meta_query'     => array(
						'relation' => 'OR',
						array(
							'key'     => 'customer', // name of custom field
							'value'   => '"' . $id . '"', // matches exactly "123", not just 123. This prevents a match for "1234"
							'compare' => 'LIKE',
						),
						array(
							'key'     => 'partner', // name of custom field
							'value'   => '"' . $id . '"', // matches exactly "123", not just 123. This prevents a match for "1234"
							'compare' => 'LIKE',
						),
					),
				)
			);

			if ( $websites ) :
				foreach ( $websites as $website ) :

					$token_lookup = get_field( 'token', $website->ID );
					if ( $token_lookup == $token ) {
						$token_match = true;
					}
				endforeach;
			endif;

			if ( $token_match ) {
				return $result;
			}
		}
		// User not logged in and no valid bypass token found
		return new WP_Error( 'rest_not_logged_in', 'You are not currently logged in.', array( 'status' => 401 ) );

	} else {

		// User logged in so check captaincore_verify_permissions
		if ( $endpoint == 'captcore_website' ) {

			$website_id = $endpoint_all[1];

			if ( ! captaincore_verify_permissions( $website_id ) ) {
				return new WP_Error( 'rest_token_invalid', __( 'Token is invalid' ), array( 'status' => 403 ) );
			}
		}

		if ( $endpoint == 'captcore_customer' ) {

			$customer_id = $endpoint_all[1];

			if ( ! captaincore_verify_permissions_customer( $customer_id ) ) {
				return new WP_Error( 'rest_token_invalid', __( 'Token is invalid' ), array( 'status' => 403 ) );
			}
		}
	}
	return $result;

}
add_filter( 'rest_authentication_errors', 'checkApiAuth' );

// Loads all domains for partners
function captaincore_get_domains_per_partner( $partner_id ) {

	$all_domains = [];

	// Load websites assigned to partner
	$websites = get_posts(
		array(
			'post_type'      => 'captcore_website',
			'posts_per_page' => '-1',
			'order'          => 'asc',
			'orderby'        => 'title',
			'fields'         => 'ids',
			'meta_query'     => array(
				'relation' => 'AND',
				array(
					'key'     => 'partner', // name of custom field
					'value'   => '"' . $partner_id . '"', // matches exactly "123", not just 123. This prevents a match for "1234"
					'compare' => 'LIKE',
				),
			),
		)
	);
	foreach ( $websites as $website ) :
		$customers = get_field( 'customer', $website );

		foreach ( $customers as $customer ) :

			$domains = get_field( 'domains', $customer );
			if ( $domains ) {
				foreach ( $domains as $domain ) :
					$domain_name = get_the_title( $domain );
					if ( $domain_name ) {
						$all_domains[ $domain_name ] = $domain;
					}
				endforeach;
			}

		endforeach;

	endforeach;

	// Sort array by domain name
	ksort( $all_domains );

	// None found, check for customer
	if ( count( $all_domains ) == 0 ) {

		// Load websites assigned to partner
		$websites = get_posts(
			array(
				'post_type'      => 'captcore_website',
				'posts_per_page' => '-1',
				'order'          => 'asc',
				'orderby'        => 'title',
				'fields'         => 'ids',
				'meta_query'     => array(
					'relation' => 'AND',
					array(
						'key'     => 'customer', // name of custom field
						'value'   => '"' . $partner_id . '"', // matches exactly "123", not just 123. This prevents a match for "1234"
						'compare' => 'LIKE',
					),
				),
			)
		);
		foreach ( $websites as $website ) :
			$customers = get_field( 'customer', $website );

			foreach ( $customers as $customer ) :

				$domains = get_field( 'domains', $customer );
				if ( $domains ) {
					foreach ( $domains as $domain ) :
						$domain_name                 = get_the_title( $domain );
						$all_domains[ $domain_name ] = $domain;
					endforeach;
				}

			endforeach;

		endforeach;

		// Sort array by domain name
		ksort( $all_domains );

	}

	// None found, check directly
	if ( count( $all_domains ) == 0 ) {

		$domains = get_field( 'domains', $partner_id );
		if ( $domains ) {
			foreach ( $domains as $domain ) :
				$domain_name                 = get_the_title( $domain );
				$all_domains[ $domain_name ] = $domain;
			endforeach;
		}

		// Sort array by domain name
		ksort( $all_domains );

	}

	return $all_domains;
}

// Checks current user for valid permissions
function captaincore_verify_permissions( $website_id ) {

	$current_user = wp_get_current_user();
	$role_check   = in_array( 'administrator', $current_user->roles );

	// Checks for a current user. If admin found pass if not check permissions
	if ( $current_user && $role_check ) {
		return true;
	}

	// Checks for other roles
	$role_check = in_array( 'subscriber', $current_user->roles ) + in_array( 'customer', $current_user->roles ) + in_array( 'partner', $current_user->roles ) + in_array( 'editor', $current_user->roles );

	// Checks current users permissions
	$partner = get_field( 'partner', 'user_' . get_current_user_id() );

	// Bail if incorrect role or nothing assigned.
	if ( !$role_check or !$partner ) {
		return false;
	}

	foreach ( $partner as $partner_id ) {

		$websites = get_posts(
			array(
				'post_type'      => 'captcore_website',
				'posts_per_page' => '-1',
				'order'          => 'asc',
				'orderby'        => 'title',
				'meta_query'     => array(
					'relation' => 'AND',
					array(
						'key'     => 'partner', // name of custom field
						'value'   => '"' . $partner_id . '"', // matches exactly "123", not just 123. This prevents a match for "1234"
						'compare' => 'LIKE',
					),
				),
			)
		);
		if ( $websites ) :
			foreach ( $websites as $website ) :
				$customer_id = get_field( 'customer', $website->ID );
				if ( $website_id == $website->ID ) {
					return true;
				}
			endforeach;
		endif;

		// Load websites assigned to partner
		$arguments = array(
			'fields'         => 'ids',
			'post_type'      => 'captcore_website',
			'posts_per_page' => '-1',
			'meta_query'     => array(
				'relation' => 'AND',
				array(
					'key'     => 'customer',
					'value'   => '"' . $partner_id . '"',
					'compare' => 'LIKE',
				),
			),
		);

		$sites = new WP_Query( $arguments );

		foreach($sites->posts as $site_id) {
			if( $website_id == $site_id) {
				return true;
			}
		}

	}

	// No permissions found
	return false;
}

// Checks current user for valid permissions
function captaincore_verify_permissions_customer( $customer_id ) {

	$current_user = wp_get_current_user();
	$role_check   = in_array( 'administrator', $current_user->roles );

	// Checks for a current user. If admin found pass if not check permissions
	if ( $current_user && $role_check ) {
		return true;
	} else {
		// Checks for other roles
		$role_check = in_array( 'partner', $current_user->roles );

		// Checks current users permissions
		$partner = get_field( 'partner', 'user_' . get_current_user_id() );

		if ( $partner and $role_check ) {
			foreach ( $partner as $partner_id ) {

				$websites = get_posts(
					array(
						'post_type'      => 'captcore_website',
						'posts_per_page' => '-1',
						'order'          => 'asc',
						'orderby'        => 'title',
						'meta_query'     => array(
							'relation' => 'AND',
							array(
								'key'     => 'partner', // name of custom field
								'value'   => '"' . $partner_id . '"', // matches exactly "123", not just 123. This prevents a match for "1234"
								'compare' => 'LIKE',
							),
							array(
								'key'     => 'status',
								'value'   => 'closed',
								'compare' => '!=',
							),
						),
					)
				);

				if ( $websites ) :

					foreach ( $websites as $website ) :
						$website_customer_id = get_field( 'customer', $website->ID );

						if ( $customer_id == $website_customer_id[0] ) {
							return true;
						}

					endforeach;
				endif;

			}
		}
	}

	// No permissions found
	return false;
}

// Checks current user for valid permissions
function captaincore_verify_permissions_domain( $domain_id ) {

	$domain_exists = get_posts(
		array(
			'post_type'      => 'captcore_domain',
			'posts_per_page' => '-1',
			'meta_query'     => array(
				array(
					'key'     => 'domain_id',
					'value'   => $domain_id,
					'compare' => '=',
				),
			),
		)
	);

	// Check if domain exists
	if ( $domain_exists ) {

		$current_user = wp_get_current_user();
		$role_check   = in_array( 'administrator', $current_user->roles );

		// Checks for a current user. If admin found pass if not check permissions
		if ( $current_user && $role_check ) {
			return true;
		} elseif ( $current_user ) {
			// Not an administrator so proceed with checking permissions
			// Checks current users permissions
			$partner = get_field( 'partner', 'user_' . get_current_user_id() );

			foreach ( $partner as $partner_id ) {

				$websites = get_posts(
					array(
						'post_type'      => 'captcore_website',
						'posts_per_page' => '-1',
						'order'          => 'asc',
						'orderby'        => 'title',
						'meta_query'     => array(
							'relation' => 'AND',
							array(
								'key'     => 'partner',
								'value'   => '"' . $partner_id . '"',
								'compare' => 'LIKE',
							),
						),
					)
				);

				if ( $websites ) :

					foreach ( $websites as $website ) :
						$website_customer_id = get_field( 'customer', $website->ID );
						$domains             = get_field( 'domains', $website_customer_id[0] );
						if ( $domains ) {
							foreach ( $domains as $domain ) {
								if ( $domain_id == get_field( 'domain_id', $domain ) ) {
									return true;
								}
							}
						}

					endforeach;
				endif;

			}

			foreach ( $partner as $partner_id ) {

				$websites = get_posts(
					array(
						'post_type'      => 'captcore_website',
						'posts_per_page' => '-1',
						'order'          => 'asc',
						'orderby'        => 'title',
						'meta_query'     => array(
							'relation' => 'AND',
							array(
								'key'     => 'customer',
								'value'   => '"' . $partner_id . '"',
								'compare' => 'LIKE',
							),
						),
					)
				);

				if ( $websites ) :

					foreach ( $websites as $website ) :
						$website_customer_id = get_field( 'customer', $website->ID );
						$domains             = get_field( 'domains', $website_customer_id[0] );
						if ( $domains ) {
							foreach ( $domains as $domain ) {
								if ( $domain_id == get_field( 'domain_id', $domain ) ) {
									return true;
								}
							}
						}

					endforeach;
				endif;

			}

			foreach ( $partner as $partner_id ) {

				$domains = get_field( 'domains', $partner_id );
				if ( $domains ) {
					foreach ( $domains as $domain ) {
						if ( $domain_id == get_field( 'domain_id', $domain ) ) {
							return true;
						}
					}
				}
			}
		}
	}

	// No permissions found
	return false;
}

// Processes install events (new install, remove install, setup configs)
add_action( 'wp_ajax_captaincore_dns', 'captaincore_dns_action_callback' );

function captaincore_dns_action_callback() {
	global $wpdb; // this is how you get access to the database

	$domain_id      = intval( $_POST['domain_key'] );
	$record_updates = $_POST['record_updates'];

	$responses = '[';

	foreach ( $record_updates as $record_update ) {

		$record_id     = $record_update['record_id'];
		$record_type   = strtolower($record_update['record_type']);
		$record_name   = $record_update['record_name'];
		$record_value  = $record_update['record_value'];
		$record_ttl    = $record_update['record_ttl'];
		$record_status = $record_update['record_status'];

		if ( $record_status == 'new-record' ) {
			if ( $record_type == 'mx' ) {

				// Formats MX records into array which API can read
				$mx_records = [];
				foreach ( $record_value as $mx_record ) {
					$mx_records[] = array(
						'value'       => $mx_record['value'],
						'level'       => $mx_record['level'],
						'disableFlag' => false,
					);
				}

				$post = array(
					'recordOption' => 'roundRobin',
					'name'         => $record_name,
					'ttl'          => $record_ttl,
					'roundRobin'   => $mx_records,
				);

			} elseif ( $record_type == 'txt' or $record_type == 'a' or $record_type == 'aname' or $record_type == 'aaaa' or $record_type == 'spf' ) {

				// Formats A and TXT records into array which API can read
				$records = [];
				foreach ( $record_value as $record ) {
					$records[] = array(
						'value'       => stripslashes( $record['value'] ),
						'disableFlag' => false,
					);
				}

				$post = array(
					'recordOption' => 'roundRobin',
					'name'         => "$record_name",
					'ttl'          => $record_ttl,
					'roundRobin'   => $records,
				);

			} elseif ( $record_type == 'cname' ) {

				$post = array(
					'name' => $record_name,
					'host' => $record_value,
					'ttl'  => $record_ttl,
				);

			} elseif ( $record_type == 'httpredirection' ) {

				$post = array(
					'name'           => $record_name,
					'ttl'            => $record_ttl,
					'url'            => $record_value,
					'redirectTypeId' => '3',
				);

			} elseif ( $record_type == 'srv' ) {

				// Formats SRV records into array which API can read
				$srv_records = [];
				foreach ( $record_value as $srv_record ) {
					$srv_records[] = array(
						'value'    => $srv_record['value'],
						'priority' => $srv_record['priority'],
						'weight'   => $srv_record['weight'],
						'port'     => $srv_record['port'],
					);
				}

				$post = array(
					'recordOption' => 'roundRobin',
					'name'         => $record_name,
					'ttl'          => $record_ttl,
					'roundRobin'   => $srv_records,
				);

			} else {
				$post = array(
					'recordOption' => 'roundRobin',
					'name'         => $record_name,
					'ttl'          => $record_ttl,
					'roundRobin'   => $record_value,
				);

			}

			$response = constellix_api_post( "domains/$domain_id/records/$record_type", $post );

			foreach ( $response as $result ) {
				if ( is_array( $result ) ) {
					$result['errors'] = $result[0];
					$responses        = $responses . json_encode( $result ) . ',';
				} else {
					$responses = $responses . json_encode( $result ) . ',';
				}
			}
		}

		if ( $record_status == 'edit-record' ) {
			if ( $record_type == 'mx' ) {

				// Formats MX records into array which API can read
				$mx_records = [];
				foreach ( $record_value as $mx_record ) {
					$mx_records[] = array(
						'value'       => $mx_record['value'],
						'level'       => $mx_record['level'],
						'disableFlag' => false,
					);
				}

				$post = array(
					'recordOption' => 'roundRobin',
					'name'         => $record_name,
					'ttl'          => $record_ttl,
					'roundRobin'   => $mx_records,
				);

			} elseif ( $record_type == 'txt' or $record_type == 'a' or $record_type == 'aname' or $record_type == 'aaaa' or $record_type == 'spf' ) {

				// Formats A and TXT records into array which API can read
				$records = [];
				foreach ( $record_value as $record ) {
					$value = stripslashes( $record['value'] );
					// Wrap TXT value in double quotes if not currently
					if ( $record_type == 'txt' and $value[0] != '"' and $value[-1] != '"' ) {
						$value = "\"{$value}\"";
					}
					$records[] = array(
						'value'       => $value,
						'disableFlag' => false,
					);
				}

				$post = array(
					'recordOption' => 'roundRobin',
					'name'         => "$record_name",
					'ttl'          => $record_ttl,
					'roundRobin'   => $records,
				);

			} elseif ( $record_type == 'httpredirection' ) {

				$post = array(
					'name'           => $record_name,
					'ttl'            => $record_ttl,
					'url'            => $record_value,
					'redirectTypeId' => '3',
				);

			} elseif ( $record_type == 'cname' ) {

				$post = array(
					'name' => $record_name,
					'host' => $record_value,
					'ttl'  => $record_ttl,
				);

			} elseif ( $record_type == 'srv' ) {

				// Formats SRV records into array which API can read
				$srv_records = [];
				foreach ( $record_value as $srv_record ) {
					$srv_records[] = array(
						'value'    => $srv_record['value'],
						'priority' => $srv_record['priority'],
						'weight'   => $srv_record['weight'],
						'port'     => $srv_record['port'],
					);
				}

				$post = array(
					'recordOption' => 'roundRobin',
					'name'         => $record_name,
					'ttl'          => $record_ttl,
					'roundRobin'   => $srv_records,
				);

			} else {
				$post = array(
					'recordOption' => 'roundRobin',
					'name'         => $record_name,
					'ttl'          => $record_ttl,
					'roundRobin'   => array(
						array(
							'value'       => stripslashes( $record_value ),
							'disableFlag' => false,
						),
					),
				);

			}
			$response              = constellix_api_put( "domains/$domain_id/records/$record_type/$record_id", $post );
			$response->domain_id   = $domain_id;
			$response->record_id   = $record_id;
			$response->record_type = $record_type;
			$responses             = $responses . json_encode( $response ) . ',';
		}

		if ( $record_status == 'remove-record' ) {
			$response              = constellix_api_delete( "domains/$domain_id/records/$record_type/$record_id" );
			$response->domain_id   = $domain_id;
			$response->record_id   = $record_id;
			$response->record_type = $record_type;
			$responses             = $responses . json_encode( $response ) . ',';
		}
	}
	$responses = rtrim( $responses, ',' ) . ']';

	echo $responses;

	wp_die(); // this is required to terminate immediately and return a proper response
}

add_action( 'wp_ajax_captaincore_local', 'captaincore_local_action_callback' );
function captaincore_local_action_callback() {
	global $wpdb; // this is how you get access to the database
	$cmd   = $_POST['command'];
	$value = $_POST['value'];

	if ( $cmd == 'fetchDefaults' ) {
		$user_id = get_current_user_id();
		$accounts = array();

		$partner = get_field( 'partner', 'user_' . get_current_user_id() );
		if ( $partner ) {
			foreach ( $partner as $partner_id ) {
				$default_users = get_field( 'preloaded_users', $partner_id );
				$default_recipes = get_field( 'default_recipes', $partner_id );
				if ( $default_users == "" ){
					$default_users = array();
				}
				$accounts[] = (object) [
					'account'          => array(
						'id'               => $partner_id,
						'name'             => get_the_title( $partner_id ),
					),
					'default_email'    => get_field( 'preloaded_email', $partner_id ),
					'default_users'    => $default_users,
					'default_recipes'  => $default_recipes,
					'default_timezone' => get_field( 'default_timezone', $partner_id ),
				];
			}
		}

		echo json_encode( $accounts );
	}

	if ( $cmd == 'saveDefaults' ) {
		$user_id = get_current_user_id();
		$record = (object) $value;
		$account_id = $record->account["id"];
		$account_ids = get_field( 'partner', 'user_' . get_current_user_id() );
		if ( in_array( $account_id, $account_ids ) ) {
			update_field( 'preloaded_email', $record->default_email, $account_id );
			update_field( 'preloaded_users', $record->default_users, $account_id );
			update_field( 'default_recipes', $record->default_recipes, $account_id );
			update_field( 'default_timezone', $record->default_timezone, $account_id );
			echo json_encode( "Record updated." );
		} else {
			echo json_encode( "Permission denied" );
		}
	}

	if ( $cmd == 'fetchTimelineLogs' ) {

		$Parsedown = new Parsedown();
		$accounts = array();

		$user = wp_get_current_user();
		$role_check = in_array( 'subscriber', $user->roles ) + in_array( 'customer', $user->roles ) + in_array( 'administrator', $user->roles) + in_array( 'editor', $user->roles );
		$partner = get_field('partner', 'user_'. get_current_user_id());

		if ($partner and $role_check) {

			// Loop through each partner assigned to current user
			foreach ($partner as $partner_id) {

				// Load websites assigned to partner
				$arguments = array(
					'post_type' 		=> 'captcore_website',
					'posts_per_page'	=> '-1',
					'fields'			=> 'ids',
					'order'				=> 'asc',
					'orderby'			=> 'title',
					'meta_query'		=> array(
						array(
							'key' => 'partner',
							'value' => '"' . $partner_id . '"',
							'compare' => 'LIKE'
						),
					)
				);

				// Loads websites
				$websites = get_posts( $arguments );

				if ( count( $websites ) == 0 ) {

					// Load websites assigned to partner
					$websites = get_posts(array(
						'post_type' 		=> 'captcore_website',
						'posts_per_page'	=> '-1',
						'fields'			=> 'ids',
						'order'				=> 'asc',
						'orderby'			=> 'title',
						'meta_query'		=> array(
								array(
									'key' => 'customer', // name of custom field
									'value' => '"' . $partner_id . '"', // matches exactly "123", not just 123. This prevents a match for "1234"
									'compare' => 'LIKE'
								),
						)
					));
				}

				if( $websites ): 
				
					$account = get_the_title($partner_id);
					$website_count = count($websites);
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

					$processlogs_fetch = array();
					$process_logs = get_posts($arguments);
					// Fetch new process_log and return as json
					foreach ($process_logs as $process_log) {

						$process     = get_field( "process", $process_log->ID );
						$author_id   = $process_log->post_author;
						$author      = get_the_author_meta( 'display_name', $author_id );
						$description = $Parsedown->text( get_field("description", $process_log->ID ) );
						$sites       = array();
						foreach( get_field("website", $process_log->ID ) as $website_id ) {
							$site = get_post( $website_id );
							if ( in_array($website_id, $websites) ) {
								$sites[] = (object) [ 
									'id'   => $site->ID,
									'name' => $site->post_title,
								];
							}
						}

						$processlogs_fetch[] = (object) [
							'id'              => $process_log->ID,
							'process_id'      => $process[0],
							'title'           => get_the_title( $process[0] ),
							'author'          => $author,
							'description'     => $description,
							'description_raw' => get_field("description", $process_log->ID ),
							'websites'        => $sites,
							'created_at'      => $process_log->post_date,
						];
					} 
				
				$accounts[] = (object) [
					'account'  => array(
						'id'   => $partner_id,
						'name' => $account,
						'website_count' => $website_count
					),
					'logs'     => $processlogs_fetch,
				];

				endif;
			}
		}
		echo json_encode( $accounts );
	}
	wp_die();

}

add_action( 'wp_ajax_captaincore_ajax', 'captaincore_ajax_action_callback' );
function captaincore_ajax_action_callback() {
	global $wpdb; // this is how you get access to the database

	if ( is_array( $_POST['post_id'] ) ) {
		$post_ids       = array();
		$post_ids_array = $_POST['post_id'];
		foreach ( $post_ids_array as $id ) {
			$post_ids[] = intval( $id );
		}
	} else {
		$post_id = intval( $_POST['post_id'] );
	}

	// Only proceed if have permission to particular site id.
	if ( ! captaincore_verify_permissions( $post_id ) ) {
		echo "Permission denied";
		wp_die();
		return;
	}

	// Only proceed if access to command 
	$user = wp_get_current_user();
	$role_check_admin = in_array( 'administrator', $user->roles );
	$admin_commands = array( 'addDomain', 'fetchConfigs', 'newRecipe', 'updateRecipe', 'updateLogEntry', 'newLogEntry', 'newProcess', 'updateProcess', 'fetchProcess', 'fetchProcessLogs', 'updateFathom', 'updatePlan', 'newSite', 'editSite', 'deleteSite' );
	if ( ! $role_check_admin && in_array( $_POST['command'], $admin_commands ) ) {
		echo "Permission denied";
		wp_die();
		return;
	}

	$cmd   = $_POST['command'];
	if ( isset($_POST['value']) ){
		$value = $_POST['value'];
	}

	$site = get_field( 'site', $post_id );
	$environment = $_POST['environment'];
	$remote_command = false;

	if ( $cmd == 'mailgun' ) {

		$mailgun = get_field( "mailgun", $post_id );
		$mailgun_events = mailgun_events( $mailgun );
		$response = [];

		if ( $mailgun_events->paging ) {
			// TO DO add paging
			// print_r($mailgun_events->paging);
		}
		foreach ( $mailgun_events->items as $mailgun_event ) {

			if ( $mailgun_event->envelope ) {
				$mailgun_description = $mailgun_event->event . ': ' . $mailgun_event->envelope->sender . ' -> ' . $mailgun_event->recipient;
			} else {
				$mailgun_description = $mailgun_event->event . ': ' . $mailgun_event->recipient;
			}

			$response[] = array(
				'timestamp' => date( 'M jS Y g:ia', $mailgun_event->timestamp ),
				'description' => $mailgun_description,
				'event' => $mailgun_event,
			);

		}

		echo json_encode( $response );

	}

	if ( $cmd == 'addDomain' ) {

		$record = (object) $value;
		// Check for duplicate domain.
		$domain_exists = get_posts(
			array(
				'title'          => $record->name,
				'post_type'      => 'captcore_domain',
				'posts_per_page' => '-1',
				'post_status'    => 'publish',
				'fields'         => 'ids',
			)
		);

		 // If results still exists then give an error
		if ( count( $domain_exists ) > 0 ) {
			echo json_encode( array( "error" => 'Domain has already been added.' ) );
			wp_die();
		}

		// Create post object
		$new_domain = array(
			'post_status' => 'publish',
			'post_type'   => 'captcore_domain',
			'post_title'  => $record->name,
			'post_author' => get_current_user_id(),
		);

		// Insert the post into the database
		$domain_id = wp_insert_post( $new_domain );

		echo json_encode( $record );

	}

	if ( $cmd == 'fetchLink' ) {
		// Fetch snapshot details
		$db = new CaptainCore\snapshots;
		$in_24hrs = date("Y-m-d H:i:s", strtotime ( date("Y-m-d H:i:s")."+24 hours" ) );

		// Generate new token
		$token = bin2hex( openssl_random_pseudo_bytes( 16 ) );
		$db->update(
			array( "token"       => $token,
				   "expires_at"  => $in_24hrs ),
			array( "snapshot_id" => $value )
		);
		echo json_encode( 
			array( 
				"token"       => $token,
				"expires_at"  => $in_24hrs
			)
		);
	}

	if ( $cmd == 'fetchPlugins' ) {
		require_once ABSPATH . 'wp-admin/includes/plugin-install.php';
		$arguments = array(
			'per_page' => 9,
			'page'     => $_POST['page'],
			'browse'   => 'popular', 
			'is_ssl'   => true,
		);
		if ( $value ) {
			$arguments['search'] = $value;
			unset( $arguments['browse'] );
		}
		$response = plugins_api( 'query_plugins', $arguments );

		echo json_encode( $response ); 
	};

	if ( $cmd == 'fetchThemes' ) {
		require_once ABSPATH . 'wp-admin/includes/plugin-install.php';
		$arguments = array(
			'per_page' => 9,
			'page'     => $_POST['page'],
			'browse'   => 'popular', 
			'is_ssl'   => true,
		);
		if ( $value ) {
			$arguments['search'] = $value;
			unset( $arguments['browse'] );
	}
		$response = themes_api( 'query_themes', $arguments );

		echo json_encode( $response ); 
	};

	if ( $cmd == 'fetchStats' ) {
		
		if ($environment == "Production") {
			$environment_id = get_field( 'environment_production_id', $post_id );
			$site_name = get_the_title( $post_id );
		}
		if ($environment == "Staging") {
			$environment_id = get_field( 'environment_staging_id', $post_id );
			$db_environments = new CaptainCore\environments();
			$data = $db_environments->fetch_field( $post_id, "Staging", "home_url" );
			$site_name = $data[0]->home_url;
			$site_name = str_replace( "http://", '', $site_name );
			$site_name = str_replace( "https://", '', $site_name );
		}
		
		$captaincore_settings = get_option( 'captaincore_settings' );

		if ( defined( 'CAPTAINCORE_DEBUG' ) ) {
			$fathom_instance = "https://{$captaincore_settings->captaincore_tracker}";
		} else {
			$fathom_instance = "https://{$captaincore_settings->captaincore_tracker}";
		}
		$login_details = array(
				'email'    => $captaincore_settings->captaincore_tracker_user, 
				'password' => $captaincore_settings->captaincore_tracker_pass
		);

		// Load sites from transient
		$auth = get_transient( 'captaincore_fathom_auth' );

		// If empty then update transient with large remote call
		if ( empty( $auth ) ) {

			// Authenticate to Fathom instance
			$auth = wp_remote_post( "$fathom_instance/api/session", array( 
				'method'  => 'POST',
				'headers' => array( 'Content-Type' => 'application/json; charset=utf-8' ),
				'body'    => json_encode( $login_details )
			) );

			// Save the API response so we don't have to call again until tomorrow.
			set_transient( 'captaincore_fathom_auth', $auth, HOUR_IN_SECONDS );

		}

		if ( is_wp_error( $auth ) ) {
			$error_message = $auth->get_error_message();
			echo json_encode( array ( "error" => $error_message ) );
			wp_die();
			return;
		}

		// Load sites from transient
		$sites = get_transient( 'captaincore_fathom_sites' );

		// If empty then update transient with large remote call
		if ( empty( $sites ) ) {

			// Fetch Sites
			$response = wp_remote_get( "$fathom_instance/api/sites", array( 
				'cookies' => $auth['cookies']
			) );
			$sites = json_decode( $response['body'] )->Data;

			// Save the API response so we don't have to call again until tomorrow.
			set_transient( 'captaincore_fathom_sites', $sites, HOUR_IN_SECONDS );

		}

		foreach( $sites as $s ) {
			if ( $s->name == $site_name ) {
				// Fetch 12 months of stats (From June 1st 2018 to May 31st 2019)
				$before = strtotime( "now" );
				$after  = strtotime( date( 'Y-m-01 04:00:00' ). "-11 months" ); 
				$response = wp_remote_get( "$fathom_instance/api/sites/{$s->id}/stats/site?before=$before&after=$after", array(
					'cookies' => $auth['cookies']
				) );
				$stats = json_decode( $response['body'] )->Data;

				$response = wp_remote_get( "$fathom_instance/api/sites/{$s->id}/stats/site/agg?before=$before&after=$after", array(
					'cookies' => $auth['cookies']
				) );
				$agg = json_decode( $response['body'] )->Data;

				$response = wp_remote_get( "$fathom_instance/api/sites/{$s->id}/stats/pages/agg?before=$before&after=$after&offset=0&limit=15", array(
					'cookies' => $auth['cookies']
				) );
				$pages = json_decode( $response['body'] )->Data;

				$response = wp_remote_get( "$fathom_instance/api/sites/{$s->id}/stats/referrers/agg?before=$before&after=$after&offset=0&limit=15", array(
					'cookies' => $auth['cookies']
				) );
				$referrers = json_decode( $response['body'] )->Data;
			}
		}

		if ( $stats ) {
			echo json_encode( array( "stats" => $stats, "agg" => $agg, "pages" => $pages, "referrers" => $referrers ) );
		} else {
			echo json_encode( array("Error" => "Site not found in Fathom" ) );
		}
		

	}

	if ( $cmd == 'fetchConfigs' ) {
		$remote_command = true;
		$command = "configs fetch vars";
	};

	if ( $cmd == 'newProcess' ) {

		$process = (object) $value;

		// Create post object
		$new_process = array(
			'post_status' => 'publish',
			'post_type'   => 'captcore_process',
			'post_title'  => $process->title,
			'post_author' => get_current_user_id(),
		);

		// Insert the post into the database
		$process_id = wp_insert_post( $new_process );

		update_field( 'time_estimate', $process->time_estimate, $process_id );
		update_field( 'repeat', $process->repeat, $process_id );
		update_field( 'repeat_quantity', $process->repeat_quantity, $process_id );
		update_field( 'description', $process->description, $process_id );
		wp_set_post_terms( $process_id, $process->role, 'process_role' );

		// Prepare to send back
		$all_processes = get_posts( $args );
		$repeat_field  = get_field_object( 'field_57f791d6363f4' );

		$process = get_post( $process_id );
		$repeat_value  = get_field( 'repeat', $process->ID  );
		$repeat = $repeat_field['choices'][ $repeat_value ];
		$role = get_the_terms( $process->ID , 'process_role' );
			if ( ! empty( $role ) && ! is_wp_error( $role ) ) {
				$role = join(' ', wp_list_pluck( $role, 'name' ) );
		}

		$process_added = (object) [
			"id"              => $process->ID,
			"title"           => get_the_title( $process->ID ),
			"created_at"      => $process->post_date,
			"time_estimate"   => get_field( 'time_estimate', $process->ID ),
			"repeat"          => $repeat,
			"repeat_quantity" => get_field( 'repeat_quantity', $process->ID ),
			"role"            => $role
		];

		echo json_encode( $process_added );
	}

	if ( $cmd == 'updateProcess' ) {

		$process = (object) $value;
		$process_id = $process->id;

		// Create post object
		$update_process = array(
			'ID'          => $process_id,
			'post_title'  => $process->title,
			'post_author' => get_current_user_id(),
		);

		// Update post
		wp_update_post( $update_process );

		update_field( 'time_estimate', $process->time_estimate, $process_id );
		update_field( 'repeat', $process->repeat_value, $process_id );
		update_field( 'repeat_quantity', $process->repeat_quantity, $process_id );
		update_field( 'description', $process->description_raw, $process_id );
		wp_set_post_terms( $process_id, $process->role_id, 'process_role' );

		// Prepare to send back
		$all_processes = get_posts( $args );
		$repeat_field  = get_field_object( 'field_57f791d6363f4' );

		$process = get_post( $process_id );
		$repeat_value  = get_field( 'repeat', $process->ID  );
		$repeat = $repeat_field['choices'][ $repeat_value ];
		$role = get_the_terms( $process->ID , 'process_role' );
			if ( ! empty( $role ) && ! is_wp_error( $role ) ) {
				$role = join(' ', wp_list_pluck( $role, 'name' ) );
		}

		$process_updated = (object) [
			"id"              => $process->ID,
			"title"           => get_the_title( $process->ID ),
			"created_at"      => $process->post_date,
			"time_estimate"   => get_field( 'time_estimate', $process->ID ),
			"repeat"          => $repeat,
			"repeat_quantity" => get_field( 'repeat_quantity', $process->ID ),
			"role"            => $role
		];

		echo json_encode( $process_updated );
	}

	if ( $cmd == 'fetchProcess' ) {

		$process = get_post( $post_id );
		$Parsedown = new Parsedown();
		$description = $GLOBALS['wp_embed']->autoembed( get_field("description", $post_id ) ) ;
		$description = $Parsedown->text( $description );
		$repeat_field  = get_field_object( 'field_57f791d6363f4' );
		$repeat_value  = get_field( 'repeat', $process->ID  );
		$repeat = $repeat_field['choices'][ $repeat_value ];
		$role = get_the_terms( $process->ID , 'process_role' );
			if ( ! empty( $role ) && ! is_wp_error( $role ) ) {
				$role = join(' ', wp_list_pluck( $role, 'name' ) );
		}

		$process_fetch = (object) [
			"id"              => $process->ID,
			"title"           => get_the_title( $process->ID ),
			"created_at"      => $process->post_date,
			"description"     => $description,
			"description_raw" => get_field( 'description', $process->ID ),
			"time_estimate"   => get_field( 'time_estimate', $process->ID ),
			"repeat"          => $repeat,
			"repeat_value"    => $repeat_value,
			"repeat_quantity" => get_field( 'repeat_quantity', $process->ID ),
			"role"            => $role,
			"role_id"         => get_the_terms( $process->ID , 'process_role' )[0]->term_id,
		];
		
		echo json_encode( $process_fetch );
	}

	if ( $cmd == 'fetchProcessLog' ) {

		$process_log = get_post( $value ) ;

		// Fetch new process_log and return as json
		$Parsedown = new Parsedown();

			$process     = get_field( "process", $process_log->ID );
			$author_id   = $process_log->post_author;
			$author      = get_the_author_meta( 'display_name', $author_id );
			$description = $Parsedown->text( get_field("description", $process_log->ID ) );
			$websites    = array();
			foreach( get_field("website", $process_log->ID ) as $website_id ) {
				$site = get_post( $website_id );
				$websites[] = (object) [ 
					'id'   => $site->ID,
					'name' => $site->post_title,
				];
			}

			$process_log_fetch = (object) [
				'id'              => $process_log->ID,
				'process_id'      => $process[0],
				'title'           => get_the_title( $process[0] ),
				'author'          => $author,
				'description'     => $description,
				'description_raw' => get_field("description", $process_log->ID ),
				'websites'        => $websites,
				'created_at'      => $process_log->post_date,
			];

		
		echo json_encode( $process_log_fetch );

	}

	if ( $cmd == 'fetchProcessLogs' ) {

		$processlogs_fetch = array();

		$process_logs = get_posts(
			array(
				'post_type'      => 'captcore_processlog',
				'posts_per_page' => '-1',
				'meta_key'       => 'status',
				'meta_value'     => 'completed',

			)
		);

		// Fetch new process_log and return as json
		$Parsedown = new Parsedown();

		foreach ( $process_logs as $process_log ) {

			$process     = get_field( "process", $process_log->ID );
			$author_id   = $process_log->post_author;
			$author      = get_the_author_meta( 'display_name', $author_id );
			$description = $Parsedown->text( get_field("description", $process_log->ID ) );
			$websites    = array();
			foreach( get_field("website", $process_log->ID ) as $website_id ) {
				$site = get_post( $website_id );
				$websites[] = (object) [ 
					'id'   => $site->ID,
					'name' => $site->post_title,
				];
			}

			$processlogs_fetch[] = (object) [
				'id'              => $process_log->ID,
				'process_id'      => $process[0],
				'title'           => get_the_title( $process[0] ),
				'author'          => $author,
				'description'     => $description,
				'description_raw' => get_field("description", $process_log->ID ),
				'websites'        => $websites,
				'created_at'      => $process_log->post_date,
			];

		}
		
		echo json_encode( $processlogs_fetch );
	}

	if ( $cmd == 'newLogEntry' ) {

		$process_id = $_POST['process_id'];

		// Create post object
		$my_post = array(
			'post_status' => 'publish',
			'post_type'   => 'captcore_processlog',
			'post_author' => get_current_user_id(),
		);

		// Insert the post into the database
		$process_log_id = wp_insert_post( $my_post );

		// Assign process to ACF relationship field
		update_field( 'field_57f862ec5b466', $process_id, $process_log_id );

		// Assign website to ACF relationship field
		if ( $post_ids ) {
			update_field( 'field_57fae6d263704', $post_ids, $process_log_id );
		}

		// Mark public
		update_field( 'field_584dc76e7eec2', true, $process_log_id );

		// Assign description
		update_field( 'field_57fc396b04e0a', $value, $process_log_id );

		// Mark completed
		update_field( 'field_588bb7bd3cab6', 'completed', $process_log_id );           // Sets status field to completed
		update_field( 'field_588bb8423cab7', date( 'Y-m-d H:i:s' ), $process_log_id );


		// Loop through each site and fetch updated timeline to return.
		$timelines = array();
		$Parsedown = new Parsedown();

		foreach ( $post_ids as $post_id ) {

			$arguments = array(
				'post_type'      => 'captcore_processlog',
				'posts_per_page' => '-1',
				'meta_query'	=> array(
					array(
						'key'	 	=> 'website',
						'value'	  	=> '"'.$post_id.'"',
						'compare' 	=> 'LIKE',
					),
			));
	
			$process_logs = get_posts( $arguments );
			$timeline_items = array();

			foreach ($process_logs as $process_log) {

				$process = get_field("process", $process_log->ID );
				$author_id = $process_log->post_author;
				$author = get_the_author_meta( 'display_name', $author_id );
		$description = $Parsedown->text( get_field("description", $process_log->ID ) );

				$timeline_items[] = (object) [
			'id'              => $process_log->ID,
			'process_id'      => $process[0],
			'title'           => get_the_title( $process[0] ),
			'author'          => $author,
			'description'     => $description,
			'description_raw' => get_field("description", $process_log->ID ),
			'created_at'      => $process_log->post_date,
		];

			} 
			$timelines[ $post_id ] = $timeline_items;

		}

		echo json_encode( $timelines ) ;

	}

	if ( $cmd == 'updateLogEntry' ) {

		$process_log_update = (object) $_POST['log'];

		$process_log = get_post( $process_log_update->id );
		wp_update_post(
			array (
					'ID'            => $process_log_update->id, // ID of the post to update
					'post_date'     => $process_log_update->created_at,
					'post_date_gmt' => get_gmt_from_date( $process_log_update->created_at ),
					'post_author'   => get_current_user_id()
			)
		);

		// Assign process to ACF relationship field
		update_field( 'field_57f862ec5b466', $process_log_update->process_id, $process_log->ID );

		// Assign website to ACF relationship field
		update_field( 'field_57fae6d263704', $post_ids, $process_log->ID );

		// Assign description
		update_field( 'field_57fc396b04e0a', $process_log_update->description_raw, $process_log->ID );

		// Mark completed
		update_field( 'field_588bb7bd3cab6', 'completed', $process_log->ID );           // Sets status field to completed
		update_field( 'field_588bb8423cab7', date( 'Y-m-d H:i:s' ), $process_log->ID );

		// Loop through each site and fetch updated timeline to return.
		$timelines = array();
		$Parsedown = new Parsedown();

		foreach ( $post_ids as $post_id ) {

			$arguments = array(
				'post_type'      => 'captcore_processlog',
				'posts_per_page' => '-1',
				'meta_query'	=> array(
					array(
						'key'	 	=> 'website',
						'value'	  	=> '"'.$post_id.'"',
						'compare' 	=> 'LIKE',
					),
			));
	
			$process_logs = get_posts( $arguments );
			$timeline_items = array();

			foreach ($process_logs as $process_log) {

				$process = get_field("process", $process_log->ID );
				$author_id = $process_log->post_author;
				$author = get_the_author_meta( 'display_name', $author_id );
		$description = $Parsedown->text( get_field("description", $process_log->ID ) );

				$timeline_items[] = (object) [
			'id'              => $process_log->ID,
			'process_id'      => $process[0],
			'title'           => get_the_title( $process[0] ),
			'author'          => $author,
			'description'     => $description,
			'description_raw' => get_field("description", $process_log->ID ),
			'created_at'      => $process_log->post_date,
		];

			} 
			$timelines[ $post_id ] = $timeline_items;

		}

		echo json_encode( $timelines ) ;

	}

	if ( $cmd == 'timeline' ) {
		
		$arguments = array(
			'post_type'      => 'captcore_processlog',
			'posts_per_page' => '-1',
			'meta_query'	=> array(
				array(
					'key'	 	=> 'website',
					'value'	  	=> '"'.$post_id.'"',
					'compare' 	=> 'LIKE',
				),
		));

		$process_logs = get_posts( $arguments );

		$Parsedown = new Parsedown();
		$timeline_items = array();

		foreach ($process_logs as $process_log) {

			$process = get_field("process", $process_log->ID );
			$author_id = $process_log->post_author;
			$author = get_the_author_meta( 'display_name', $author_id );
			$description = $Parsedown->text( get_field("description", $process_log->ID ) );

			$timeline_items[] = (object) [
				'id'              => $process_log->ID,
				'process_id'      => $process[0],
				'title'           => get_the_title( $process[0] ),
				'author'          => $author,
				'description'     => $description,
				'description_raw' => get_field("description", $process_log->ID ),
				'created_at'      => $process_log->post_date,
			];

		} 

		echo json_encode( $timeline_items ) ;

	}

	if ( $cmd == 'newRecipe' ) {

		$recipe = (object) $value;
		$time_now = date("Y-m-d H:i:s");

		$new_recipe = array(
			'user_id'        => get_current_user_id(),
			'title'          => $recipe->title,
			'updated_at'     => $time_now,
			'created_at'     => $time_now,
			'content'        => stripslashes_deep( $recipe->content ),
			'public'         => $recipe->public,
		);

		$db_recipes = new CaptainCore\recipes();
		$recipe_id = $db_recipes->insert( $new_recipe );
		echo json_encode( $db_recipes->fetch_recipes("title","ASC") );

		$remote_command = true;
		$silence = true;
		$recipe_content = base64_encode( stripslashes_deep( $recipe->content ) );
		$command = "recipe add $recipe_content --id=$recipe_id --name=\"{$recipe->title}\"";

	}

	if ( $cmd == 'updateRecipe' ) {

		$recipe = (object) $value;
		$time_now = date("Y-m-d H:i:s");

		$recipe_update = array(
			'title'          => $recipe->title,
			'updated_at'     => $time_now,
			'content'        => stripslashes_deep( $recipe->content ),
			'public'         => $recipe->public,
		);

		$db_recipes = new CaptainCore\recipes();
		$db_recipes->update( $recipe_update, array( "recipe_id" => $recipe->recipe_id ) );

		echo json_encode( $db_recipes->fetch_recipes( "title", "ASC" ) );

		$remote_command = true;
		$silence = true;
		$recipe_content = base64_encode( stripslashes_deep( $recipe->content ) );
		$command = "recipe add $recipe_content --id={$recipe->recipe_id} --name=\"{$recipe->title}\"";

	}

	if ( $cmd == 'usage-breakdown' ) {

		$customer     = get_field( "customer", $post_id );
		$customer_id  = $customer[0];
		$hosting_plan = get_field( 'hosting_plan', $customer_id );
		$addons       = get_field( 'addons', $customer_id );
		$storage      = get_field( 'storage', $customer_id );
		$visits       = get_field( 'visits', $customer_id );
		$visits_plan_limit = get_field( 'visits_limit', $customer_id );
		$storage_limit     = get_field( 'storage_limit', $customer_id );
		$sites_limit       = get_field( 'sites_limit', $customer_id );

		if ( isset( $visits ) ) {
			$visits_percent = round( $visits / $visits_plan_limit * 100, 0 );
		}

		$storage_gbs = round( $storage / 1024 / 1024 / 1024, 1 );
		$storage_percent = round( $storage_gbs / $storage_limit * 100, 0 );

		$sites = array();
		$total = array();

		$websites_for_customer = get_posts(
			array(
				'post_type'      => 'captcore_website',
				'posts_per_page' => '-1',
				'order'          => 'ASC',
				'orderby'        => 'title',
				'meta_query' => array(
					'relation' => 'AND',
					array(
						'key'     => 'status', // name of custom field
						'value'   => 'active', // matches exactly "123", not just 123. This prevents a match for "1234"
						'compare' => '=',
					),
					array(
						'key'     => 'customer', // name of custom field
						'value'   => '"' . $customer_id . '"', // matches exactly "123", not just 123. This prevents a match for "1234"
						'compare' => 'LIKE',
					),
					),
			)
		);

		if ( $websites_for_customer ) :
			foreach ( $websites_for_customer as $website_for_customer ) :
				$site = ( new CaptainCore\Site )->get( $website_for_customer->ID );
				$website_for_customer_storage = $site->storage_raw;
				$website_for_customer_visits  = $site->visits;
				$sites[] = array(
					'name'    => get_the_title( $website_for_customer->ID ),
					'storage' => round( $website_for_customer_storage / 1024 / 1024 / 1024, 1 ),
					'visits'  => $website_for_customer_visits
				);
			endforeach;

			$total = array(
				$storage_percent . "% storage<br /><strong>" . $storage_gbs ."GB/". $storage_limit ."GB</strong>",
				$visits_percent . "% traffic<br /><strong>" . number_format( $visits ) . "</strong> <small>Yearly Estimate</small>"
			);

		endif;

		$usage_breakdown = array ( 'sites' => $sites, 'total' => $total );

		$mock_usage_breakdown = array(
			'sites' => array(
					array(
						'name' => 'anchor.host',
						'storage' => '.4',
						'visits' => '22164'
					),
					array(
						'name' => 'anchorhost1.wpengine.com',
						'storage' => '2.5',
						'visits' => '10352'
					)
			),
			'total' =>  array(
				'25% storage<br />24.9GB/100GB',
				'86% traffic<br />86,112 Yearly Estimate'
			),
		);

		echo json_encode( $usage_breakdown ) ;
	}

	if ( $cmd == 'updateFathom' ) {

		// Append environment if needed
		if ( $environment == "Staging" ) {
			$site = "{$site}-staging";
		}

		$db_environments = new CaptainCore\environments();

		// Saves update settings for a site
		$environment_update = array(
			'fathom' => json_encode($value),
		);
		$environment_update['updated_at'] = date("Y-m-d H:i:s");

		if ($environment == "Production") {
			$environment_id = get_field( 'environment_production_id', $post_id );
			$db_environments->update( $environment_update, array( "environment_id" => $environment_id ) );
		}
		if ($environment == "Staging") {
			$environment_id = get_field( 'environment_staging_id', $post_id );
			$db_environments->update( $environment_update, array( "environment_id" => $environment_id ) );
		}

		// Remote Sync
		$run_in_background = true;
		$remote_command = true;
		$command = "stats-deploy $site '" . json_encode($value) . "'";

	}

	if ( $cmd == 'updatePlan' ) {

		// Regenerate usage info for customer
		captaincore_acf_save_post_after( $post_id );

		$customer     = get_field( "customer", $post_id );
		$customer_id  = $customer[0];
		$hosting_plan = $value["hosting_plan"];
		$addons       = $value["addons"];

		update_field( 'hosting_plan', $hosting_plan["name"], $customer_id );
		update_field( 'visits_limit', $hosting_plan["visits_limit"], $customer_id );
		update_field( 'storage_limit', $hosting_plan["storage_limit"], $customer_id );
		update_field( 'sites_limit', $hosting_plan["sites_limit"], $customer_id );
		update_field( 'price', $hosting_plan["price"], $customer_id );
		update_field( 'addons', $addons, $customer_id );

	}

	if ( $cmd == 'updateSettings' ) {

		$db_environments = new CaptainCore\environments();

		// Saves update settings for a site
		$environment_update = array(
			'updates_enabled'         => $value["updates_enabled"],
			'updates_exclude_themes'  => implode(",", $value["exclude_themes"]),
			'updates_exclude_plugins' => implode(",", $value["exclude_plugins"]),
		);
		$environment_update['updated_at'] = date("Y-m-d H:i:s");

		if ($environment == "Production") {
			$environment_id = get_field( 'environment_production_id', $post_id );
			$db_environments->update( $environment_update, array( "environment_id" => $environment_id ) );
		}
		if ($environment == "Staging") {
			$environment_id = get_field( 'environment_staging_id', $post_id );
			$db_environments->update( $environment_update, array( "environment_id" => $environment_id ) );
		}

		// Remote Sync
		$run_in_background = true;
		$remote_command = true;
		$command = "site update" . captaincore_site_fetch_details( $post_id );

	}

	if ( $cmd == 'newSite' ) {

		// Create new site
		$site = ( new CaptainCore\Site )->create( $value );
		echo json_encode( $site );

	}

	if ( $cmd == 'editSite' ) {

		// Updates site
		$site = ( new CaptainCore\Site )->update( $value );
		echo json_encode( $site );

	}

	if ( $cmd == 'deleteSite' ) {

		// Delete site on CaptainCore CLI
		$run_in_background = true;
		$remote_command = true;
		$command = "site delete $site";

		// Delete site locally
		( new CaptainCore\Site )->delete( $post_id );
	
	}

	if ( $cmd == 'fetch-site' ) {
		$sites = array();
		if ( count( $post_ids ) > 0 ) {
			foreach( $post_ids as $id ) {
				$sites[] = ( new CaptainCore\Site )->get( $id );
			}
		} else {
			$sites[] = ( new CaptainCore\Site )->get( $post_id );
		}
		echo json_encode( $sites );
	}

	if ( $cmd == 'fetch-users' ) {

		# Fetch from custom table
		$results = array(
			"Production" => json_decode(get_field( "field_5b2a900c85a77", $post_id )),
			"Staging" => json_decode(get_field( "field_5c6758d67ad20", $post_id ))
		);
		echo json_encode($results);
			 
	}

	if ( $cmd == 'fetch-update-logs' ) {

		$db = new CaptainCore\update_logs;

		$environment_production_id = get_field( 'environment_production_id', $post_id );
		$environment_staging_id = get_field( 'environment_staging_id', $post_id );

		# Fetch from custom table
		$results = array(
			"Production" => $db->fetch_logs( $post_id, $environment_production_id ),
			"Staging" => $db->fetch_logs( $post_id, $environment_staging_id )
		);

		echo json_encode($results);

	}

	if ( $cmd == 'fetch-one-time-login' ) {

		if ($environment == "Production") {
			$home_url = get_field( "field_5a944358bf146", $post_id );
		}
		if ($environment == "Staging") {
			$home_url = get_field( "field_5c6758df7ad21", $post_id );
		}

		$args = array(
			"body" => json_encode( array(
					"command"    => "login",
					"user_login" => $value,
					"token"      => get_field( "token", $post_id ),
			) ),
			"method"    => 'POST',
			"sslverify" => false,
		);

		$response = wp_remote_post( $home_url . "/wp-admin/admin-ajax.php?action=captaincore_quick_login", $args );
		$login_url = $response["body"];
		echo $login_url;
		wp_die();

	}

	if ( $remote_command ) {

		// Disable https when debug enabled
		if ( defined( 'CAPTAINCORE_DEBUG' ) ) {
			add_filter( 'https_ssl_verify', '__return_false' );
		}

		$data = array( 
			'timeout' => 45,
			'headers' => array(
				'Content-Type' => 'application/json; charset=utf-8', 
				'token'        => CAPTAINCORE_CLI_TOKEN 
			), 
			'body' => json_encode( array(
				"command" => $command 
			)), 
			'method'      => 'POST', 
			'data_format' => 'body' 
		);

		if ( $run_in_background ) {

			// Add command to dispatch server
			$response = wp_remote_post( CAPTAINCORE_CLI_ADDRESS . "/tasks", $data );
			$response = json_decode( $response["body"] );
			
			// Response with task id
			if ( $response && $response->token ) { 
				echo $response->token; 
			}

			wp_die(); // this is required to terminate immediately and return a proper response
		}

		// Add command to dispatch server
		$response = wp_remote_post( CAPTAINCORE_CLI_ADDRESS . "/run", $data );
		$response = $response["body"];

		if ( $silence ) {
			wp_die(); // this is required to terminate immediately and return a proper response
		}

		echo $response;
		
		// Store results in wp_options.captaincore_settings
		if ( $cmd == "fetchConfigs" ) {
			$captaincore_settings = json_decode( $response );
			unset($captaincore_settings->websites);
			update_option("captaincore_settings", $captaincore_settings );
		}
		
		wp_die(); // this is required to terminate immediately and return a proper response

	}

	wp_die(); // this is required to terminate immediately and return a proper response
}

add_action( 'wp_ajax_captaincore_install', 'captaincore_install_action_callback' );
function captaincore_install_action_callback() {
	global $wpdb; // this is how you get access to the database

	// Assign post id
	$post_id = intval( $_POST['post_id'] );

	// Many sites found, check permissions
	if ( is_array( $_POST['post_id'] ) ) {
		$post_ids       = array();
		foreach ( $_POST['post_id'] as $id ) {

			// Checks permissions
			if ( ! captaincore_verify_permissions( $id ) ) {
				echo 'Permission denied';
				wp_die(); // this is required to terminate immediately and return a proper response
				return;
			}

			$post_ids[] = intval( $id );
		}

		// Patch in the first from the post_ids
		$post_id = $post_ids[0];

	}

	// Checks permissions
	if ( ! captaincore_verify_permissions( $post_id ) ) {
		echo 'Permission denied';
		wp_die(); // this is required to terminate immediately and return a proper response
		return;
	}

	$cmd          = $_POST['command'];
	$value        = $_POST['value'];
	$commit       = $_POST['commit'];
	$arguments    = $_POST['arguments'];
	$filters      = $_POST['filters'];
	$addon_type   = $_POST['addon_type'];
	$date         = $_POST['date'];
	$name         = $_POST['name'];
	$environment  = $_POST['environment'];
	$quicksave_id = $_POST['quicksave_id'];
	$link         = $_POST['link'];
	$background   = $_POST['background'];
	$job_id       = $_POST['job_id'];
	$notes        = $_POST['notes'];

	$site         = get_field( 'site', $post_id );
	$provider     = get_field( 'provider', $post_id );
	$domain       = get_the_title( $post_id );

	$partners = get_field( 'partner', $post_id );
	if ( $partners ) {
		$preloadusers = implode( ',', $partners );
	}

	// Append environment if needed
	if ( $environment == "Staging" ) {
		$site = get_field( 'site', $post_id ) . "-staging";
	}

	// Append provider if exists
	if ( $provider != '' ) {
		$site = $site . '@' . $provider;
	}

	// If many sites, fetch their names
	if ( count($post_ids) > 0 ) {
		$site_names = array();
		foreach( $post_ids as $id ) {

			if ( $environment == "Production" or $environment == "Both" ) {
				$site_names[]   = get_field( 'site', $id );
			}

			$address_staging  = get_field( 'field_57b7a25d2cc60', $id );

			// Add staging if needed
			if ( isset( $address_staging ) && $address_staging != "" ) {
				if ( $environment == "Staging" or $environment == "Both" ) {
					$site_names[] = get_field( 'site', $id ) . '-staging';
				}
			}
		}
		$site = implode( " ", $site_names );
	}

	if ( $background ) {
		$run_in_background = true;
	}
	if ( $cmd == 'new' ) {
		$command = 'site add' . captaincore_site_fetch_details( $post_id );
		$run_in_background = true;
	}
	if ( $cmd == 'deploy-defaults' ) {
		$command = "deploy-defaults $site";
		$run_in_background = true;
	}
	if ( $cmd == 'update' ) {
		$command = 'site update' . captaincore_site_fetch_details( $post_id );
		$run_in_background = true;
	}
	if ( $cmd == 'update-wp' ) {
		$command = "update $site";
		$run_in_background = true;
	}
	if ( $cmd == 'update-fetch' ) {
		$command = "update-fetch $site";

		if ( defined( 'CAPTAINCORE_DEBUG' ) ) {
			// return mock data
			$command = CAPTAINCORE_DEBUG_MOCK_UPDATES;
		}
	}
	if ( $cmd == 'users-fetch' ) {
		$command = "ssh $site --command='wp user list --format=json'";

		$run_in_background = true;

		if ( defined( 'CAPTAINCORE_DEBUG' ) ) {
			// return mock data
			$command = CAPTAINCORE_DEBUG_MOCK_USERS;
		}
	}
	if ( $cmd == 'copy' ) {
		// Find destination site and verify we have permission to it
		if ( captaincore_verify_permissions( $value ) ) {
			$current_user = wp_get_current_user();
			$email        = $current_user->user_email;
			$run_in_background = true;
			$site_destination = get_field( 'site', $value );
			$command   = "copy $site $site_destination --email=$email";
		}
	}
	if ( $cmd == 'migrate' ) {
		$run_in_background = true;
		$command = "ssh $site --script=migrate --url=\"$value\"";
		if ( $_POST['update_urls'] == "true" ) {
			$command = "$command --update-urls";
		}
	}
	if ( $cmd == 'recipe' ) {
		$run_in_background = true;
		$command = "ssh $site --recipe=$value";
	}
	if ( $cmd == 'mailgun' ) {
		$run_in_background = true;
		mailgun_setup( $domain );
		$command = "ssh $site --script=deploy-mailgun --key=\"" . MAILGUN_API_KEY . "\" --domain=$domain";
	}
	if ( $cmd == 'launch' ) {
		$run_in_background = true;
		$command = "ssh $site --script=launch --domain=$value";
	}
	if ( $cmd == 'apply-https' ) {
		$run_in_background = true;
		$command = "ssh $site --script=apply-https";
	}
	if ( $cmd == 'apply-https-with-www' ) {
		$run_in_background = true;
		$command = "ssh $site --script=apply-https-with-www";
	}
	if ( $cmd == 'production-to-staging' ) {
		$run_in_background = true;
		if ( $value ) {
			$command = "copy-production-to-staging $site --email=$value";
		} else {
			$command = "copy-production-to-staging $site";
		}
	}
	if ( $cmd == 'staging-to-production' ) {
		$run_in_background = true;
		if ( $value ) {
			$command = "copy-staging-to-production $site --email=$value";
		} else {
			$command = "copy-staging-to-production $site";
		}
	}
	if ( $cmd == 'sync-data' ) {
		$run_in_background = true;
		$command = "sync-data $site";
	}
	if ( $cmd == 'remove' ) {
		$command = "site delete $site";
	}
	if ( $cmd == 'quick_backup' ) {
		$run_in_background = true;
		$command   = "quicksave $site";
	}
	if ( $cmd == 'backup' ) {
		$run_in_background = true;
		$command = "backup $site";
	}
	if ( $cmd == 'snapshot' ) {
		$run_in_background = true;
		$user_id = get_current_user_id();
		if ( $date && $value ) {
			$command = "snapshot $site --email=$value --rollback=\"$date\" --user_id=$user_id --notes=\"$notes\"";
		} elseif ( $value ) {
			$command = "snapshot $site --email=$value --user_id=$user_id --notes=\"$notes\"";
		} else {
			$command = "snapshot $site --user_id=$user_id --notes=\"$notes\"";
		}

		if ( $filters ) {
			$filters = implode(",", $filters); 
			$command = $command . " --filter={$filters}";
		}
	}
	if ( $cmd == 'deactivate' ) {
		$run_in_background = true;
		$command = "deactivate $site --name=\"$name\" --link=\"$link\"";
	}
	if ( $cmd == 'activate' ) {
		$run_in_background = true;
		$command = "activate $site";
	}

	if ( $cmd == 'view_quicksave_changes' ) {
		$command = "quicksave-view-changes $site --hash=$value";
	}

	if ( $cmd == 'run' ) {
		$code = base64_encode( stripslashes_deep( $value ) );
		$command = "run $site --code=$code";
	}

	if ( $cmd == 'manage' ) {

		$run_in_background = true;

		if ( is_array($post_ids) ) {
			$command = '';
			$sites   = array();
			foreach ( $post_ids as $site_id ) {
				$sites[] = get_field( 'site', $site_id );
			}

			foreach ( $value as $bulk_command ) {
				$bulk_arguments = array();
				foreach ( $arguments as $argument ) {
					if ( $argument['command'] == $bulk_command && isset( $argument['input'] ) && $argument['input'] != '' ) {
						$bulk_arguments[] = $argument['input'];
						$command         .= "$bulk_command " . implode( ' ', $sites ) . " --" . $argument['value'] . "=\"" . $argument['input'] . "\"";
					}
				}
			}
		}

		if ( is_int($post_id) ) {
			$command = "$value $site --" . $arguments['value'] . '="' . stripslashes($arguments['input']) . '"';
		}

	}

	if ( $cmd == 'quicksave_file_diff' ) {
		$db_quicksaves = new CaptainCore\quicksaves;
		$quicksaves = $db_quicksaves->get( $quicksave_id );
		$git_commit = $quicksaves->git_commit;
		$command    = "quicksave-file-diff $site --hash=$commit --file=$value --html";
	}

	if ( $cmd == 'rollback' ) {
		$run_in_background = true;
		$db_quicksaves = new CaptainCore\quicksaves;
		$quicksaves = $db_quicksaves->get( $quicksave_id );
		$git_commit = $quicksaves->git_commit;
		$command    = "rollback $site $git_commit --$addon_type=$value";
	}

	if ( $cmd == 'quicksave_rollback' ) {
		$run_in_background = true;
		$db_quicksaves = new CaptainCore\quicksaves;
		$quicksaves = $db_quicksaves->get( $quicksave_id );
		$git_commit = $quicksaves->git_commit;
		$command    = "rollback $site $git_commit --all";
	}

	if ( $cmd == 'quicksave_file_restore' ) {
		$run_in_background = true;
		$db_quicksaves = new CaptainCore\quicksaves;
		$quicksaves = $db_quicksaves->get( $quicksave_id );
		$git_commit = $quicksaves->git_commit;
		$command    = "rollback $site $git_commit --file=$value";
	}

	// Disable https when debug enabled
	if ( defined( 'CAPTAINCORE_DEBUG' ) ) {
		add_filter( 'https_ssl_verify', '__return_false' );
	}

	$data = array( 
		'timeout' => 45,
		'headers' => array(
			'Content-Type' => 'application/json; charset=utf-8', 
			'token'        => CAPTAINCORE_CLI_TOKEN 
		), 
		'body' => json_encode( array(
			"command" => $command 
		)), 
		'method'      => 'POST', 
		'data_format' => 'body' 
	);

	if ( $cmd == 'job-fetch' ) {

		$data['body'] = "";
		$data['method'] = "GET";

		// Add command to dispatch server
		$response = wp_remote_get( CAPTAINCORE_CLI_ADDRESS . "/task/${job_id}", $data );
		$response = json_decode( $response["body"] );
		
		// Response with task id
		if ( $response && $response->Status == "Completed" ) { 
			echo json_encode(array(
				"response" => $response->Response,
				"status" => "Completed",
				"job_id" => $job_id
			));
			wp_die(); // this is required to terminate immediately and return a proper response
		}

		echo "Job ID $job_id is still running.";

		wp_die(); // this is required to terminate immediately and return a proper response
	}

	if ( $run_in_background ) {

		// Add command to dispatch server
		$response = wp_remote_post( CAPTAINCORE_CLI_ADDRESS . "/tasks", $data );
		$response = json_decode( $response["body"] );
		
		// Response with token for task
		if ( $response && $response->token ) { 
			echo $response->token;
		}

		wp_die(); // this is required to terminate immediately and return a proper response
	}

	// Add command to dispatch server
	$response = wp_remote_post( CAPTAINCORE_CLI_ADDRESS . "/run", $data );
	if ( is_wp_error( $response ) ) {
		$error_message = $response->get_error_message();
		$response = "Something went wrong: $error_message";
	} else {
		$response = $response["body"];
	}
	
	echo $response;
	
	wp_die(); // this is required to terminate immediately and return a proper response
}

// Logs a process completion
add_action( 'wp_ajax_log_process', 'process_log_action_callback' );

function captaincore_site_fetch_details( $post_id ) {

	$site_details = ( new CaptainCore\Site )->get( $post_id );

	$site                    = get_field( 'site', $post_id );
	$provider                = get_field( 'provider', $post_id );
	$domain                  = get_the_title( $post_id );
	$partners                = get_field( 'partner', $post_id );
	$address                 = $site_details->environments[0]["address"];
	$username                = $site_details->environments[0]["username"];
	$password                = $site_details->environments[0]["password"];
	$protocol                = $site_details->environments[0]["protocol"];
	$port                    = $site_details->environments[0]["port"];
	$home_directory          = ( isset($site_details->environments[0]["home_directory"]) ? $site_details->environments[0]["home_directory"] : '' );
	$fathom                  = ( isset($site_details->environments[0]["fathom"]) ? json_encode( $site_details->environments[0]["fathom"] ) : '' );
	$updates_enabled         = ( isset($site_details->environments[0]["updates_enabled"]) ? $site_details->environments[0]["updates_enabled"] : '' );
	$updates_exclude_themes  = ( isset($site_details->environments[0]["updates_exclude_themes"]) ? implode(",", $site_details->environments[0]["updates_exclude_themes"] ) : '' );
	$updates_exclude_plugins = ( isset($site_details->environments[0]["updates_exclude_plugins"]) ? implode(",", $site_details->environments[0]["updates_exclude_plugins"] ) : '' );
	$offload_enabled         = ( isset($site_details->environments[0]["offload_enabled"]) ? $site_details->environments[0]["offload_enabled"] : '' );
	$offload_provider        = ( isset($site_details->environments[0]["offload_provider"]) ? $site_details->environments[0]["offload_provider"] : '' );
	$offload_access_key      = ( isset($site_details->environments[0]["offload_access_key"]) ? $site_details->environments[0]["offload_access_key"] : '' );
	$offload_secret_key      = ( isset($site_details->environments[0]["offload_secret_key"]) ? $site_details->environments[0]["offload_secret_key"] : '' );
	$offload_bucket          = ( isset($site_details->environments[0]["offload_bucket"]) ? $site_details->environments[0]["offload_bucket"] : '' );
	$offload_path            = ( isset($site_details->environments[0]["offload_path"]) ? $site_details->environments[0]["offload_path"] : '' );
	$staging_address         = ( isset($site_details->environments[1]["address"]) ? $site_details->environments[1]["address"] : '' );
	$staging_username        = ( isset($site_details->environments[1]["username"]) ? $site_details->environments[1]["username"] : '' );
	$staging_password        = ( isset($site_details->environments[1]["password"]) ? $site_details->environments[1]["password"] : '' );
	$staging_protocol        = ( isset($site_details->environments[1]["protocol"]) ? $site_details->environments[1]["protocol"] : '' );
	$staging_port            = ( isset($site_details->environments[1]["port"]) ? $site_details->environments[1]["port"] : '' );
	$staging_home_directory  = ( isset($site_details->environments[1]["home_directory"]) ? $site_details->environments[1]["home_directory"] : '' );
	$staging_fathom                  = ( isset($site_details->environments[1]["fathom"]) ? json_encode( $site_details->environments[1]["fathom"] ) : '' );
	$staging_updates_enabled         = ( isset($site_details->environments[1]["updates_enabled"]) ? $site_details->environments[1]["updates_enabled"] : '' );
	$staging_updates_exclude_themes  = ( isset($site_details->environments[1]["updates_exclude_themes"]) ? implode(",", $site_details->environments[1]["updates_exclude_themes"] ) : '' );
	$staging_updates_exclude_plugins = ( isset($site_details->environments[1]["updates_exclude_plugins"]) ? implode(",", $site_details->environments[1]["updates_exclude_plugins"] ) : '' );
	$staging_offload_enabled         = ( isset($site_details->environments[1]["offload_enabled"]) ? $site_details->environments[1]["offload_enabled"] : '' );
	$staging_offload_provider        = ( isset($site_details->environments[1]["offload_provider"]) ? $site_details->environments[1]["offload_provider"] : '' );
	$staging_offload_access_key      = ( isset($site_details->environments[1]["offload_access_key"]) ? $site_details->environments[1]["offload_access_key"] : '' );
	$staging_offload_secret_key      = ( isset($site_details->environments[1]["offload_secret_key"]) ? $site_details->environments[1]["offload_secret_key"] : '' );
	$staging_offload_bucket          = ( isset($site_details->environments[1]["offload_bucket"]) ? $site_details->environments[1]["offload_bucket"] : '' );
	$staging_offload_path            = ( isset($site_details->environments[1]["offload_path"]) ? $site_details->environments[1]["offload_path"] : '' );

	if ( $partners ) {
		$preloadusers = implode( ',', $partners );
	} else {
		$preloadusers     = "";
	}

	// Append provider if exists
	if ( $provider != '' ) {
		$site = $site . '@' . $provider;
	}

	$command = '' .
	( $site ? " $site" : '' ) .
	( $post_id ? " --id=$post_id" : '' ) .
	( $domain ? " --domain=$domain" : '' ) .
	( $preloadusers ? " --preloadusers=$preloadusers" : '' ) .
	( $address ? " --address=$address" : '' ) .
	( $username ? " --username=$username" : '' ) .
	( $password ? ' --password=' . rawurlencode( base64_encode( $password ) ) : '' ) .
	( $protocol ? " --protocol=$protocol" : '' ) .
	( $port ? " --port=$port" : '' ) .
	( $home_directory ? " --home_directory=$home_directory" : '' ) .
	( $fathom ? " --fathom=$fathom" : '' ) .
	( $updates_enabled ? " --updates_enabled=$updates_enabled" : ' --updates_enabled=0' ) .
	( $updates_exclude_themes ? " --updates_exclude_themes=$updates_exclude_themes" : '' ) .
	( $updates_exclude_plugins ? " --updates_exclude_plugins=$updates_exclude_plugins" : '' ) .
	( $offload_enabled ? " --offload_enabled=$offload_enabled" : ' --offload_enabled=0' ) .
	( $offload_provider ? " --offload_provider=$offload_provider" : '' ) .
	( $offload_access_key ? " --offload_access_key=$offload_access_key" : '' ) .
	( $offload_secret_key ? " --offload_secret_key=$offload_secret_key" : '' ) .
	( $offload_bucket ? " --offload_bucket=$offload_bucket" : '' ) .
	( $offload_path ? " --offload_path=$offload_path" : '' ) .
	( $staging_address ? " --staging_address=$staging_address" : '' ) .
	( $staging_username ? " --staging_username=$staging_username" : '' ) .
	( $staging_password ? ' --staging_password=' . rawurlencode( base64_encode( $staging_password ) ) : '' ) .
	( $staging_protocol ? " --staging_protocol=$staging_protocol" : '' ) .
	( $staging_port ? " --staging_port=$staging_port" : '' ) .
	( $staging_home_directory ? " --staging_home_directory=$staging_home_directory" : '' ) .
	( $staging_fathom ? " --staging_fathom=$staging_fathom" : '' ) .
	( $staging_updates_enabled ? " --staging_updates_enabled=$staging_updates_enabled" : ' --staging_updates_enabled=0' ) .
	( $staging_updates_exclude_themes ? " --staging_updates_exclude_themes=$staging_updates_exclude_themes" : '' ) .
	( $staging_updates_exclude_plugins ? " --staging_updates_exclude_plugins=$staging_updates_exclude_plugins" : '' ) .
	( $staging_offload_enabled ? " --staging_offload_enabled=$staging_offload_enabled" : ' --staging_offload_enabled=0' ) .
	( $staging_offload_provider ? " --staging_offload_provider=$staging_offload_provider" : '' ) .
	( $staging_offload_access_key ? " --staging_offload_access_key=$staging_offload_access_key" : '' ) .
	( $staging_offload_secret_key ? " --staging_offload_secret_key=$staging_offload_secret_key" : '' ) .
	( $staging_offload_bucket ? " --staging_offload_bucket=$staging_offload_bucket" : '' ) .
	( $staging_offload_path ? " --staging_offload_path=$staging_offload_path" : '' );
	return $command;

}

function captaincore_create_tables() {

	global $wpdb;
	$required_version = 15;
	$version = (int) get_site_option('captcorecore_db_version');
	$charset_collate = $wpdb->get_charset_collate();

	if ( $version < $required_version ) {

		$sql = "CREATE TABLE `{$wpdb->base_prefix}captaincore_update_logs` (
			log_id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			site_id bigint(20) UNSIGNED NOT NULL,
			environment_id bigint(20) UNSIGNED NOT NULL,
			created_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
			update_type varchar(255),
			update_log longtext,
		PRIMARY KEY  (log_id)
		) $charset_collate;";

		require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
		dbDelta($sql);
		$success = empty($wpdb->last_error);

		$sql = "CREATE TABLE `{$wpdb->base_prefix}captaincore_quicksaves` (
			quicksave_id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			site_id bigint(20) UNSIGNED NOT NULL,
			environment_id bigint(20) UNSIGNED NOT NULL,
			created_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
			git_status varchar(255),
			git_commit varchar(100),
			core varchar(10),
			themes longtext,
			plugins longtext,
		PRIMARY KEY  (quicksave_id)
		) $charset_collate;";

		require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
		dbDelta($sql);
		$success = empty($wpdb->last_error);

		$sql = "CREATE TABLE `{$wpdb->base_prefix}captaincore_snapshots` (
			snapshot_id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			user_id bigint(20) UNSIGNED NOT NULL,
			site_id bigint(20) UNSIGNED NOT NULL,
			environment_id bigint(20) UNSIGNED NOT NULL,
			created_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
			snapshot_name varchar(255),
			storage varchar(20),
			email varchar(100),
			notes longtext,
			expires_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
			token varchar(32),
		PRIMARY KEY  (snapshot_id)
		) $charset_collate;";

		require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
		dbDelta($sql);
		$success = empty($wpdb->last_error);

		$sql = "CREATE TABLE `{$wpdb->base_prefix}captaincore_environments` (
			environment_id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			site_id bigint(20) UNSIGNED NOT NULL,
			created_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
			updated_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
			environment varchar(255),
			address varchar(255),
			username varchar(255),
			password varchar(255),
			protocol varchar(255),
			port varchar(255),
			fathom varchar(255),
			home_directory varchar(255),
			database_username varchar(255),
			database_password varchar(255),
			offload_enabled boolean,
			offload_provider varchar(255),
			offload_access_key varchar(255),
			offload_secret_key varchar(255),
			offload_bucket varchar(255),
			offload_path varchar(255),
			storage varchar(20),
			visits varchar(20),
			core varchar(10),
			subsite_count varchar(10),
			home_url varchar(255),
			themes longtext,
			plugins longtext,
			users longtext,
			screenshot boolean,
			updates_enabled boolean,
			updates_exclude_themes longtext,
			updates_exclude_plugins longtext,
		PRIMARY KEY  (environment_id)
		) $charset_collate;";

		require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
		dbDelta($sql);
		$success = empty($wpdb->last_error);

		$sql = "CREATE TABLE `{$wpdb->base_prefix}captaincore_recipes` (
			recipe_id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			user_id bigint(20) UNSIGNED NOT NULL,
			title varchar(255),
			created_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
			updated_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
			content longtext,
			public boolean,
		PRIMARY KEY  (recipe_id)
		) $charset_collate;";

		require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
		dbDelta($sql);
		$success = empty($wpdb->last_error);

		update_site_option('captcorecore_db_version', $required_version );
	}

}

add_action('acf/save_post', 'captaincore_acf_save_post', 1);

function captaincore_acf_save_post( $post_id ) {
    
	// bail early if no ACF data
	if( empty($_POST['acf']) ) {
			return;
	}
	
	// array of field values
	$fields = $_POST['acf'];

	// bail if environment field not found
	if ( ! isset( $fields['field_5619c94518f1c'] ) ) {
		return;
	}

	$environment_production_id = get_field( 'environment_production_id', $post_id );
	$environment_staging_id = get_field( 'environment_staging_id', $post_id );

	$environment = array(
		'site_id'                 => $post_id,
		'environment'             => "Production",
		'address'                 => $fields['field_5619c94518f1c'],
		'username'                => $fields['field_5619c97c18f1d'],
		'password'                => $fields['field_5619c98218f1e'],
		'protocol'                => $fields['field_5619c98918f1f'],
		'port'                    => $fields['field_5619c99d18f20'],
		'home_directory'          => $fields['field_58422bd538c32'],
		'database_username'       => $fields['field_5a69f0a6e9686'],
		'database_password'       => $fields['field_5a69f0cce9687'],
		'offload_enabled'         => $fields['field_58e14eee75e79'],
		'offload_provider'        => $fields['field_5c67581c7ad15'],
		'offload_access_key'      => $fields['field_58e14fc275e7a'],
		'offload_secret_key'      => $fields['field_58e1500875e7b'],
		'offload_bucket'          => $fields['field_58e1502475e7c'],
		'offload_path'            => $fields['field_58e1503075e7d'],
		'users'                   => stripslashes($fields['field_5b2a900c85a77']),
		'themes'                  => stripslashes($fields['field_5a9421b804ed4']),
		'plugins'                 => stripslashes($fields['field_5a9421b004ed3']),
		'home_url'                => $fields['field_5a944358bf146'],
		'core'                    => $fields['field_5a9421bc04ed5'],
		'updates_enabled'         => $fields['field_5b2a902585a78'],
		'updates_exclude_themes'  => $fields['field_5b231746b9731'],
		'updates_exclude_plugins' => $fields['field_5b231770b9732'],
	);


	$db_environments = new CaptainCore\environments();

	if ( $environment_production_id ) {
		// Updating production environment
		$environment['updated_at'] = date("Y-m-d H:i:s");
		$db_environments->update( $environment, array( "environment_id" => $environment_production_id ) );
	} else {
		// Creating production environment
		$time_now = date("Y-m-d H:i:s");
		$environment['created_at'] = $time_now;
		$environment['updated_at'] = $time_now;
		$environment_id = $db_environments->insert( $environment );
		update_field( 'environment_production_id', $environment_id, $post_id );
	}

	$environment = array(
		'site_id'                 => $post_id,
		'environment'             => "Staging",
		'address'                 => $fields['field_57b7a25d2cc60'],
		'username'                => $fields['field_57b7a2642cc61'],
		'password'                => $fields['field_57b7a26b2cc62'],
		'protocol'                => $fields['field_57b7a2712cc63'],
		'port'                    => $fields['field_57b7a2772cc64'],
		'home_directory'          => $fields['field_5845da68fc2c9'],
		'database_username'       => $fields['field_5a90ba0c6c61a'],
		'database_password'       => $fields['field_5a90ba1e6c61b'],
		'offload_enabled'         => $fields['field_5c6757d97ad13'],
		'offload_provider'        => $fields['field_5c67584d7ad16'],
		'offload_access_key'      => $fields['field_5c6757e77ad14'],
		'offload_secret_key'      => $fields['field_5c6758667ad17'],
		'offload_bucket'          => $fields['field_5c6758797ad18'],
		'offload_path'            => $fields['field_5c67588f7ad19'],
		'users'                   => stripslashes($fields['field_5c6758d67ad20']),
		'themes'                  => stripslashes($fields['field_5c6758cc7ad1f']),
		'plugins'                 => stripslashes($fields['field_5c6758c57ad1e']),
		'home_url'                => $fields['field_5c6758df7ad21'],
		'core'                    => $fields['field_5c6758bb7ad1d'],
		'updates_enabled'         => $fields['field_5c6758987ad1a'],
		'updates_exclude_themes'  => $fields['field_5c6758a37ad1b'],
		'updates_exclude_plugins' => $fields['field_5c6758b37ad1c'],
	);

	$db_environments = new CaptainCore\environments();

	if ( $environment_staging_id ) {
		// Updating staging environment
		$environment['updated_at'] = date("Y-m-d H:i:s");
		$db_environments->update( $environment, array( "environment_id" => $environment_staging_id ) );
	} else {
		// Creating staging environment
		$time_now = date("Y-m-d H:i:s");
		$environment['created_at'] = $time_now;
		$environment['updated_at'] = $time_now;
		$environment_id = $db_environments->insert( $environment );
		update_field( 'environment_staging_id', $environment_id, $post_id );
	}
	
}

add_filter( 'acf/update_value', 'captaincore_disregard_acf_fields', 10, 3 );

function captaincore_disregard_acf_fields( $value, $post_id, $field ) {

	$fields_to_disregard = array(
    "field_57b7a25d2cc60",
    "field_57b7a2642cc61",
    "field_57b7a26b2cc62",
    "field_57b7a2712cc63",
    "field_57b7a2772cc64",
    "field_5845da68fc2c9",
    "field_5a90ba0c6c61a",
    "field_5a90ba1e6c61b",
    "field_5c6758987ad1a",
    "field_5c6758a37ad1b",
    "field_5c6758b37ad1c",
    "field_5c6758d67ad20",
    "field_5c6758cc7ad1f",
    "field_5c6758c57ad1e",
    "field_5c6758df7ad21",
    "field_5c6758bb7ad1d",
    "field_5c6757d97ad13",
    "field_5c67584d7ad16",
    "field_5c6757e77ad14",
    "field_5c6758667ad17",
    "field_5c6758797ad18",
    "field_5c67588f7ad19",
    "field_5619c94518f1c",
    "field_5619c97c18f1d",
    "field_5619c98218f1e",
    "field_5619c98918f1f",
    "field_5619c99d18f20",
    "field_58422bd538c32",
    "field_5a69f0a6e9686",
    "field_5a69f0cce9687",
    "field_5b2a902585a78",
    "field_5b231746b9731",
    "field_5b231770b9732",
    "field_5b2a900c85a77",
    "field_5a9421b804ed4",
    "field_5a9421b004ed3",
    "field_5a944358bf146",
    "field_5a9421bc04ed5",
    "field_58e14eee75e79",
    "field_5c67581c7ad15",
    "field_58e14fc275e7a",
    "field_58e1500875e7b",
    "field_58e1502475e7c",
    "field_58e1503075e7d"
	);

	// Disregard updating certain fields as they've already been stored in a custom table.
	if ( in_array( $field['key'], $fields_to_disregard ) ) {
		return null;
	}

	return $value;

}

add_filter( 'acf/load_value', 'captaincore_load_environments', 11, 3 );

function captaincore_load_environments( $value, $post_id, $field ) {

	$fields_table_map = array(
    "field_5619c94518f1c" => array( "environment" => "Production", "field" => 'address'                ),
    "field_5619c97c18f1d" => array( "environment" => "Production", "field" => 'username'               ),
    "field_5619c98218f1e" => array( "environment" => "Production", "field" => 'password'               ),
    "field_5619c98918f1f" => array( "environment" => "Production", "field" => 'protocol'               ),
    "field_5619c99d18f20" => array( "environment" => "Production", "field" => 'port'                   ),
    "field_58422bd538c32" => array( "environment" => "Production", "field" => 'home_directory'         ),
    "field_5a69f0a6e9686" => array( "environment" => "Production", "field" => 'database_username'      ),
    "field_5a69f0cce9687" => array( "environment" => "Production", "field" => 'database_password'      ),
    "field_5b2a902585a78" => array( "environment" => "Production", "field" => 'updates_enabled'        ),
    "field_5b231746b9731" => array( "environment" => "Production", "field" => 'updates_exclude_themes' ),
    "field_5b231770b9732" => array( "environment" => "Production", "field" => 'updates_exclude_plugins'),
    "field_5b2a900c85a77" => array( "environment" => "Production", "field" => 'users'                  ),
    "field_5a9421b804ed4" => array( "environment" => "Production", "field" => 'themes'                 ),
    "field_5a9421b004ed3" => array( "environment" => "Production", "field" => 'plugins'                ),
    "field_5a944358bf146" => array( "environment" => "Production", "field" => 'home_url'               ),
    "field_5a9421bc04ed5" => array( "environment" => "Production", "field" => 'core'                   ),
    "field_58e14eee75e79" => array( "environment" => "Production", "field" => 'offload_enabled'        ),
    "field_5c67581c7ad15" => array( "environment" => "Production", "field" => 'offload_provider'       ),
    "field_58e14fc275e7a" => array( "environment" => "Production", "field" => 'offload_access_key'     ),
    "field_58e1500875e7b" => array( "environment" => "Production", "field" => 'offload_secret_key'     ),
    "field_58e1502475e7c" => array( "environment" => "Production", "field" => 'offload_bucket'         ),
    "field_58e1503075e7d" => array( "environment" => "Production", "field" => 'offload_path'           ),
    "field_57b7a25d2cc60" => array( "environment" => "Staging", "field" => 'address'                   ),
    "field_57b7a2642cc61" => array( "environment" => "Staging", "field" => 'username'                  ),
    "field_57b7a26b2cc62" => array( "environment" => "Staging", "field" => 'password'                  ),
    "field_57b7a2712cc63" => array( "environment" => "Staging", "field" => 'protocol'                  ),
    "field_57b7a2772cc64" => array( "environment" => "Staging", "field" => 'port'                      ),
    "field_5845da68fc2c9" => array( "environment" => "Staging", "field" => 'home_directory'            ),
    "field_5a90ba0c6c61a" => array( "environment" => "Staging", "field" => 'database_username'         ),
    "field_5a90ba1e6c61b" => array( "environment" => "Staging", "field" => 'database_password'         ),
    "field_5c6758987ad1a" => array( "environment" => "Staging", "field" => 'updates_enabled'           ),
    "field_5c6758a37ad1b" => array( "environment" => "Staging", "field" => 'updates_exclude_themes'    ),
    "field_5c6758b37ad1c" => array( "environment" => "Staging", "field" => 'updates_exclude_plugins'   ),
    "field_5c6758d67ad20" => array( "environment" => "Staging", "field" => 'users'                     ),
    "field_5c6758cc7ad1f" => array( "environment" => "Staging", "field" => 'themes'                    ),
    "field_5c6758c57ad1e" => array( "environment" => "Staging", "field" => 'plugins'                   ),
    "field_5c6758df7ad21" => array( "environment" => "Staging", "field" => 'home_url'                  ),
    "field_5c6758bb7ad1d" => array( "environment" => "Staging", "field" => 'core'                      ),
    "field_5c6757d97ad13" => array( "environment" => "Staging", "field" => 'offload_enabled'           ),
    "field_5c67584d7ad16" => array( "environment" => "Staging", "field" => 'offload_provider'          ),
    "field_5c6757e77ad14" => array( "environment" => "Staging", "field" => 'offload_access_key'        ),
    "field_5c6758667ad17" => array( "environment" => "Staging", "field" => 'offload_secret_key'        ),
    "field_5c6758797ad18" => array( "environment" => "Staging", "field" => 'offload_bucket'            ),
    "field_5c67588f7ad19" => array( "environment" => "Staging", "field" => 'offload_path'              )
	);

	// Fetch certain records from custom table
	if ( in_array( $field['key'], array_keys( $fields_table_map ) ) ) {

		$db_environments = new CaptainCore\environments();

		$item = $fields_table_map[ $field['key'] ];
		$data =	$db_environments->fetch_field( $post_id, $item["environment"], $item['field'] );
		if ( $data && $data[0]) {
			return $data[0]->{$item['field']};
		}
	}

	return $value;

}

function captaincore_website_acf_actions( $field ) {

	if ( $field and $field['label'] == 'Websites' ) {

		$id = get_the_ID();

		/*
		*  Query posts for a relationship value.
		*  This method uses the meta_query LIKE to match the string "123" to the database value a:1:{i:0;s:3:"123";} (serialized array)
		*/

		$websites = get_posts(
			array(
				'post_type'      => 'captcore_website',
				'posts_per_page' => '-1',
				'meta_query'     => array(

					'relation' => 'AND',
					array(
						'key'     => 'server', // name of custom field
						'value'   => '"' . $id . '"', // matches exactly "123", not just 123. This prevents a match for "1234"
						'compare' => 'LIKE',
					),
					array(
						'key'     => 'status', // name of custom field
						'value'   => 'closed',
						'compare' => '!=',
					),
				),
			)
		);
		?>
		<?php
		if ( $websites ) :
			$total_storage = 0;
		?>
			<ul>
			<?php
			foreach ( $websites as $website ) :
				$domain        = get_the_title( $website->ID );
				$storage       = get_field( 'storage', $website->ID );
				$total_storage = $total_storage + $storage;
				?>
				<li>
					<?php edit_post_link( $domain, '', '', $website->ID ); ?> - <?php echo human_filesize( $storage ); ?>
				</li>
			<?php endforeach; ?>

				<li>Total storage: <?php echo human_filesize( $total_storage ); ?></li>
			</ul>
		<?php
		endif;

	}

	if ( $field and $field['label'] == 'Websites included with Plan' ) {

		$id = get_the_ID();

		/*
		*  Query posts for a relationship value.
		*  This method uses the meta_query LIKE to match the string "123" to the database value a:1:{i:0;s:3:"123";} (serialized array)
		*/

		$websites = get_posts(
			array(
				'post_type'      => 'captcore_website',
				'posts_per_page' => '-1',
				'meta_query'     => array(
					array(
						'key'     => 'customer', // name of custom field
						'value'   => '"' . $id . '"', // matches exactly "123", not just 123. This prevents a match for "1234"
						'compare' => 'LIKE',
					),
				),
			)
		);
		?>
		<?php if ( $websites ) : ?>
			<ul>
			<?php foreach ( $websites as $website ) : ?>
				<li>
					<a href="<?php echo get_permalink( $website->ID ); ?>">
						<?php $domain = get_the_title( $website->ID ); ?>
						<?php edit_post_link( $domain, '<p>', '</p>', $website->ID ); ?>
					</a>
				</li>
			<?php endforeach; ?>
			</ul>
		<?php
		endif;

	}
	if ( $field and $field['label'] == 'Websites managed by Partner' ) {

		$id = get_the_ID();

		/*
		*  Query posts for a relationship value.
		*  This method uses the meta_query LIKE to match the string "123" to the database value a:1:{i:0;s:3:"123";} (serialized array)
		*/

		$websites = get_posts(
			array(
				'post_type'      => 'captcore_website',
				'posts_per_page' => '-1',
				'meta_query'     => array(
					array(
						'key'     => 'partner', // name of custom field
						'value'   => '"' . $id . '"', // matches exactly "123", not just 123. This prevents a match for "1234"
						'compare' => 'LIKE',
					),
				),
			)
		);
		?>
		<?php if ( $websites ) : ?>
			<ul>
			<?php foreach ( $websites as $website ) : ?>
				<li>
					<a href="<?php echo get_permalink( $website->ID ); ?>">
						<?php $domain = get_the_title( $website->ID ); ?>
						<?php edit_post_link( $domain, '', '', $website->ID ); ?>
						<?php the_field( 'status', $website->ID ); ?>
					</a>
				</li>
			<?php endforeach; ?>
			</ul>
		<strong>Active Installs</strong>
		<?php
		foreach ( $websites as $website ) :
			if ( get_field( 'status', $website->ID ) == 'active' ) {
				echo get_field( 'site', $website->ID ) . ' ';
			}
		endforeach;
		?>
		<?php
		endif;

	}

	if ( $field and $field['label'] == 'Plans paid by Partner' ) {

		$id = get_the_ID();

		/*
		*  Query posts for a relationship value.
		*  This method uses the meta_query LIKE to match the string "123" to the database value a:1:{i:0;s:3:"123";} (serialized array)
		*/

		$websites = get_posts(
			array(
				'post_type'      => 'captcore_customer',
				'posts_per_page' => '-1',
				'meta_query'     => array(
					array(
						'key'     => 'paid_by', // name of custom field
						'value'   => '"' . $id . '"', // matches exactly "123", not just 123. This prevents a match for "1234"
						'compare' => 'LIKE',
					),
				),
			)
		);
		?>
		<?php if ( $websites ) : ?>
			<ul>
			<?php foreach ( $websites as $website ) : ?>
				<li>
					<a href="<?php echo get_permalink( $website->ID ); ?>">
						<?php
						$domain                       = get_the_title( $website->ID );
										 $total_price = get_field( 'total_price' );
											?>
										<p><?php edit_post_link( $domain, '', '', $website->ID ); ?> - $<?php echo $total_price; ?></p>
					</a>
				</li>
			<?php endforeach; ?>
			</ul>
		<?php
		endif;
	}

	if ( $field and $field['label'] == 'Production FTP Details' ) {
	?>

<textarea rows='6'>
<?php the_title(); ?>&#13;
<?php the_field( 'address' ); ?>&#13;
<?php the_field( 'username' ); ?>&#13;
<?php the_field( 'password' ); ?>&#13;
<?php the_field( 'protocol' ); ?>&#13;
<?php the_field( 'port' ); ?>
</textarea>

	<?php
	}

	if ( $field and $field['label'] == 'Staging FTP Details' ) {
	?>

<textarea rows='6'>
<?php the_title(); ?> (Staging)&#13;
<?php the_field( 'address_staging' ); ?>&#13;
<?php the_field( 'username_staging' ); ?>&#13;
<?php the_field( 'password_staging' ); ?>&#13;
<?php the_field( 'protocol_staging' ); ?>&#13;
<?php the_field( 'port_staging' ); ?>
</textarea>

	<?php
	}

	if ( $field and $field['label'] == 'Customer' ) {

		$id       = get_the_ID();
		$customer = get_field( 'customer', $id );
		if ( isset( $customer[0] ) ) {
			$customer_id = $customer[0];
			$name        = get_the_title( $customer_id );
			$link        = get_edit_post_link( $customer_id );
			?>
			<a href="<?php echo $link; ?>&classic-editor" class="button" target="_parent"><i class="fa fa-user"></i> <?php echo $name; ?></a>
		<?php
		}
	}

	if ( $field and $field['label'] == 'Website Actions' ) {
	?>
		<link rel="stylesheet" href="https://use.fontawesome.com/releases/v5.0.11/css/all.css" integrity="sha384-p2jx59pefphTFIpeqCcISO9MdVfIm4pNnsL08A6v5vaQc4owkQqxMV8kg4Yvhaw/" crossorigin="anonymous">

		<style>
		@-ms-keyframes spin {
			from { -ms-transform: rotate(0deg); }
			to { -ms-transform: rotate(360deg); }
		}
		@-moz-keyframes spin {
			from { -moz-transform: rotate(0deg); }
			to { -moz-transform: rotate(360deg); }
		}
		@-webkit-keyframes spin {
			from { -webkit-transform: rotate(0deg); }
			to { -webkit-transform: rotate(360deg); }
		}
		@keyframes spin {
			from {
				transform:rotate(0deg);
			}
			to {
				transform:rotate(360deg);
			}
		}

		i.fa.fa-spinner {
		  -webkit-animation-name: spin;
		   -webkit-animation-duration: 4000ms;
		   -webkit-animation-iteration-count: infinite;
		   -webkit-animation-timing-function: linear;
		   -moz-animation-name: spin;
		   -moz-animation-duration: 4000ms;
		   -moz-animation-iteration-count: infinite;
		   -moz-animation-timing-function: linear;
		   -ms-animation-name: spin;
		   -ms-animation-duration: 4000ms;
		   -ms-animation-iteration-count: infinite;
		   -ms-animation-timing-function: linear;

		   animation-name: spin;
		   animation-duration: 4000ms;
		   animation-iteration-count: infinite;
		   animation-timing-function: linear;
		}
		.install-result {
		  padding: 12px;
		  background: #f7f7f7;
		  margin-top: 1em;
		  color: #606060;
		  display: none;
		  white-space: pre;
		}
		</style>
		<p>Runs initial backup, setups up token, install plugins and load custom configs into wp-config.php and .htaccess in a background process. Sends email when completed. </p>
		<script>
		jQuery(document).ready(function(){
		  jQuery(".captaincore_commands button").click(function(e){

			// Loading
			jQuery('.install-result').html( '<i class="fa fa-spinner" aria-hidden="true"></i>' ).show();

			var command = jQuery(this).val().toLowerCase();
				e.preventDefault();
				var data = {
					'action': 'captaincore_install',
					'post_id': acf.data.post_id,
					'command': command
				};

			  // since 2.8 ajaxurl is always defined in the admin header and points to admin-ajax.php
			  jQuery.post(ajaxurl, data, function(response) {
			  jQuery('.install-result').html( response );
			  });
		  });

		});
		</script>
		<div class="captaincore_commands">
			<button value="New" class="button"><i class="fas fa-plus-circle"></i> New</button>
			<button value="Update" class="button"><i class="fas fa-pen-square"></i> Update</button>
			<button value="Mailgun" class="button"><i class="fas fa-envelope"></i> Deploy Mailgun</button>
			<button value="Backup" class="button"><i class="fas fa-cloud"></i> Backup</button>
			<button value="Snapshot" class="button"><i class="fas fa-save"></i> Snapshot</button>
			<button value="Activate" class="button"><i class="fas fa-toggle-on"></i> Activate</button>
			<button value="Deactivate" class="button"><i class="fas fa-toggle-off"></i> Deactivate</button>
			<button value="Remove" class="button"><i class="fas fa-trash-alt"></i> Remove</button>
		</div>
		<div class="install-result"></div>
		<?php
	}

}
add_action( 'acf/render_field/type=message', 'captaincore_website_acf_actions', 10, 1 );

function process_log_action_callback() {
	global $wpdb; // this is how you get access to the database

	$post_id = intval( $_POST['post_id'] );

	// Create post object
	$my_post = array(
		'post_status' => 'publish',
		'post_type'   => 'captcore_processlog',
		'post_author' => get_current_user_id(),
	);

	// Insert the post into the database
	$process_log_id = wp_insert_post( $my_post );

	// Assign process to ACF relationship field
	update_field( 'field_57f862ec5b466', $post_id, $process_log_id );

	// Copies checklist from process and stores into new process log
	$process_checklist = get_field( 'checklist', $post_id );
	update_field( 'field_58e9288d66c07', $process_checklist, $process_log_id );

	// Debug output
	$redirect_url = get_permalink( $post_id );
	echo "{ \"redirect_url\" : \"$redirect_url\", \"process_id\": \"$process_log_id\" }";

	wp_die(); // this is required to terminate immediately and return a proper response
}

// Sets process log status to completed
add_action( 'wp_ajax_log_process_completed', 'log_process_completed_callback' );

function log_process_completed_callback() {
	global $wpdb; // this is how you get access to the database

	$post_id = intval( $_POST['post_id'] );

	date_default_timezone_set( 'America/New_York' );

	// Sets process log status to completed
	update_field( 'field_588bb7bd3cab6', 'completed', $post_id );           // Sets status field to completed
	update_field( 'field_588bb8423cab7', date( 'Y-m-d H:i:s' ), $post_id );   // Sets completed field to current timestamp

	if ( get_field( 'status', $post_id ) == 'completed' ) {
		echo '1';
	}

	wp_die(); // this is required to terminate immediately and return a proper response
}

function captaincore_download_snapshot_email( $snapshot_id ) {

	// Fetch snapshot details
	$db       = new CaptainCore\snapshots;
	$snapshot = $db->get( $snapshot_id );
	$name     = $snapshot->snapshot_name;
	$domain   = get_the_title( $snapshot->site_id );
	$site     = get_field( 'site', $snapshot->site_id );

	// Generate download url to snapshot
	$home_url = home_url();
	$file_name = substr($snapshot->snapshot_name, 0, -4);
	$download_url = "{$home_url}/wp-json/captaincore/v1/site/{$snapshot->site_id}/snapshots/{$snapshot->snapshot_id}-{$snapshot->token}/{$file_name}";

	// Build email
	$company = get_field( 'business_name', 'option' );
	$to      = $snapshot->email;
	$subject = "$company - Snapshot #$snapshot_id";
	$body    = "Snapshot #{$snapshot_id} for {$domain}. Expires after 1 week.<br /><br /><a href=\"{$download_url}\">Download Snapshot</a>";
	$headers = array( 'Content-Type: text/html; charset=UTF-8' );

	// Send email
	wp_mail( $to, $subject, $body, $headers );

}

function captaincore_snapshot_download_link( $snapshot_id ) {

	$db       = new CaptainCore\snapshots;
	$snapshot = $db->get( $snapshot_id );
	$name     = $snapshot->snapshot_name;
	$domain   = get_the_title( $snapshot->site_id );
	$site     = get_field( 'site', $snapshot->site_id);

	// Get new auth from B2
	$account_id      = CAPTAINCORE_B2_ACCOUNT_ID;  // Obtained from your B2 account page
	$application_key = CAPTAINCORE_B2_ACCOUNT_KEY; // Obtained from your B2 account page
	$credentials     = base64_encode( $account_id . ':' . $application_key );
	$url             = 'https://api.backblazeb2.com/b2api/v1/b2_authorize_account';

	$session = curl_init( $url );

	// Add headers
	$headers   = array();
	$headers[] = 'Accept: application/json';
	$headers[] = 'Authorization: Basic ' . $credentials;
	curl_setopt( $session, CURLOPT_HTTPHEADER, $headers ); // Add headerss
	curl_setopt( $session, CURLOPT_HTTPGET, true );        // HTTP GET
	curl_setopt( $session, CURLOPT_RETURNTRANSFER, true ); // Receive server response
	$server_output = curl_exec( $session );
	curl_close( $session );
	$output = json_decode( $server_output );

	// Variables for Backblaze
	$api_url          = 'https://api001.backblazeb2.com'; // From b2_authorize_account call
	$auth_token       = $output->authorizationToken;      // From b2_authorize_account call
	$bucket_id        = CAPTAINCORE_B2_BUCKET_ID;         // The file name prefix of files the download authorization will allow
	$valid_duration   = 604800;                           // The number of seconds the authorization is valid for
	$file_name_prefix = 'Snapshots/' . $site;             // The file name prefix of files the download authorization will allow

	$session = curl_init( $api_url . '/b2api/v1/b2_get_download_authorization' );

	// Add post fields
	$data        = array(
		'bucketId'               => $bucket_id,
		'validDurationInSeconds' => $valid_duration,
		'fileNamePrefix'         => $file_name_prefix,
	);
	$post_fields = json_encode( $data );
	curl_setopt( $session, CURLOPT_POSTFIELDS, $post_fields );

	// Add headers
	$headers   = array();
	$headers[] = 'Authorization: ' . $auth_token;
	curl_setopt( $session, CURLOPT_HTTPHEADER, $headers );
	curl_setopt( $session, CURLOPT_POST, true );           // HTTP POST
	curl_setopt( $session, CURLOPT_RETURNTRANSFER, true ); // Receive server response
	$server_output = curl_exec( $session );                // Let's do this!
	curl_close( $session );                                // Clean up
	$server_output = json_decode( $server_output );
	$auth          = $server_output->authorizationToken;
	$b2_snapshots  = CAPTAINCORE_B2_SNAPSHOTS;
	$url           = "https://f001.backblazeb2.com/file/{$b2_snapshots}/{$site}_{$snapshot->site_id}/{$name}?Authorization={$auth}";

	return $url;
}

// Add reports to customers
add_action( 'admin_menu', 'captaincore_custom_pages' );

function captaincore_custom_pages() {
	add_menu_page( 'CaptainCore', 'CaptainCore', 'read', 'captaincore-admin', '', 'data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMTUxcHgiIGhlaWdodD0iMTQ5cHgiIHZpZXdCb3g9IjAgMCAxNTEgMTQ5IiB2ZXJzaW9uPSIxLjEiIHhtbG5zPSJodHRwOi8vd3d3LnczLm9yZy8yMDAwL3N2ZyIgeG1sbnM6eGxpbms9Imh0dHA6Ly93d3cudzMub3JnLzE5OTkveGxpbmsiPgogICAgPGcgaWQ9IlBhZ2UtMSIgc3Ryb2tlPSJub25lIiBzdHJva2Utd2lkdGg9IjEiIGZpbGw9Im5vbmUiIGZpbGwtcnVsZT0iZXZlbm9kZCI+CiAgICAgICAgPGcgaWQ9Im1haW4td2ViLWljb25zLTAxIiB0cmFuc2Zvcm09InRyYW5zbGF0ZSgtMi4wMDAwMDAsIC0xLjAwMDAwMCkiIGZpbGw9IiM4NUNFRTQiPgogICAgICAgICAgICA8cGF0aCBkPSJNMTQ5Ljg5MSwxMTkgTDEzMi4wODcsMTE5IEMxMzAuMzczLDExOSAxMjguOTc4LDEyMC4zOTUgMTI4Ljk3OCwxMjIuMTE2IEwxMjksMTI4LjgxIEMxMjksMTMxLjMwNSAxMjguNDk1LDEzMS44MSAxMjYsMTMxLjgxIEMxMjEuMjA0LDEzMS44MSAxMTcuNDI5LDEyOC43MjQgMTEyLjY0OSwxMjQuODE2IEMxMDYuMTA2LDExOS40NjcgOTcuOTYzLDExMi44MSA4NCwxMTIuODEgTDg0LDExNi44MSBDOTYuNTM2LDExNi44MSAxMDMuNzUxLDEyMi43MDggMTEwLjExOCwxMjcuOTEzIEMxMTUuMDg1LDEzMS45NzQgMTE5Ljc3NywxMzUuODEgMTI2LDEzNS44MSBDMTMwLjcxLDEzNS44MSAxMzMsMTMzLjUyIDEzMywxMjguODAzIEwxMzIuOTgsMTIzIEwxNDksMTIzIEwxNDksMTQwLjMxMyBDMTQ5LDE0My4zOTYgMTQ2LjQ4MiwxNDYgMTQzLjUwMywxNDYgTDEzNywxNDYgQzEyOC43OTcsMTQ2IDEyMS40MywxMzkuNzMgMTEzLjYzLDEzMy4wOTIgQzEwNC44MDIsMTI1LjU3OSA5NS42NzMsMTE3LjgxIDg0LDExNy44MSBMODQsMTIxLjgxIEM5NC4yMDEsMTIxLjgxIDEwMi43NiwxMjkuMDk0IDExMS4wMzgsMTM2LjEzOSBDMTE5LjA0NSwxNDIuOTUzIDEyNy4zMjUsMTUwIDEzNywxNTAgTDE0My41MDMsMTUwIEMxNDguNzQsMTUwIDE1MywxNDUuNjU0IDE1MywxNDAuMzEzIEwxNTMsMTIyLjEwOSBDMTUzLDEyMC4zOTUgMTUxLjYwNSwxMTkgMTQ5Ljg5MSwxMTkiIGlkPSJGaWxsLTEiPjwvcGF0aD4KICAgICAgICAgICAgPHBhdGggZD0iTTE0Niw5NS44MSBDMTQ0Ljg5Niw5NS44MSAxNDQsOTYuNzA1IDE0NCw5Ny44MSBMMTQ0LDExMyBDMTQ0LDExNC4xMDQgMTQ0Ljg5NiwxMTUgMTQ2LDExNSBDMTQ3LjEwNCwxMTUgMTQ4LDExNC4xMDQgMTQ4LDExMyBMMTQ4LDk3LjgxIEMxNDgsOTYuNzA1IDE0Ny4xMDQsOTUuODEgMTQ2LDk1LjgxIiBpZD0iRmlsbC0yIj48L3BhdGg+CiAgICAgICAgICAgIDxwYXRoIGQ9Ik0xNDQsODYuODEgTDE0NCw4OC44MSBDMTQ0LDg5LjkxNCAxNDQuODk2LDkwLjgxIDE0Niw5MC44MSBDMTQ3LjEwNCw5MC44MSAxNDgsODkuOTE0IDE0OCw4OC44MSBMMTQ4LDg2LjgxIEMxNDgsODUuNzA1IDE0Ny4xMDQsODQuODEgMTQ2LDg0LjgxIEMxNDQuODk2LDg0LjgxIDE0NCw4NS43MDUgMTQ0LDg2LjgxIiBpZD0iRmlsbC0zIj48L3BhdGg+CiAgICAgICAgICAgIDxwYXRoIGQ9Ik0xMzQsOTkuODEgTDEzNCwxMDguODEgQzEzNCwxMDkuOTE0IDEzNC44OTYsMTEwLjgxIDEzNiwxMTAuODEgQzEzNy4xMDQsMTEwLjgxIDEzOCwxMDkuOTE0IDEzOCwxMDguODEgTDEzOCw5OS44MSBDMTM4LDk4LjcwNSAxMzcuMTA0LDk3LjgxIDEzNiw5Ny44MSBDMTM0Ljg5Niw5Ny44MSAxMzQsOTguNzA1IDEzNCw5OS44MSIgaWQ9IkZpbGwtNCI+PC9wYXRoPgogICAgICAgICAgICA8cGF0aCBkPSJNNywzMi45MDggQzcsMTcuNzggMzcuOTgzLDUgNzQuNjU3LDUgQzExMS4zMyw1IDE0Mi4yNjgsMTcuNzggMTQyLjI2OCwzMi45MDggQzE0Mi4yNjgsMzguNzM1IDEzOCw0My40NCAxMzMsNDYuNzY4IEwxMzMsNDYuMzEyIEMxMzMsNDIuNDkyIDEyOC4zNjQsMzkuOTE0IDExNy44NjUsMzcuOTUxIEMxMTAuNTY4LDM2LjU4NyAxMDAuODc1LDM1LjYzMiA4OS42MzQsMzUuMTY1IEM4OS44MjMsMzAuNTY0IDg3LjYwOCwyNi4wNDggODMuNTUsMjMuNDY2IEw4MS4yOTUsMjIuMDI4IEM3Ni45ODksMTkuMjg4IDcxLjQyNywxOS4yODkgNjcuMTIxLDIyLjAyOCBMNjQuODYxLDIzLjQ2NiBDNjAuODA0LDI2LjA0OSA1OC41NzgsMzAuNTY0IDU4Ljc2NywzNS4xNjUgQzQ3LjQxMSwzNS42MzcgMzcuNjE3LDM2LjYwNyAzMC4zMDcsMzcuOTkgQzI1LjYzNSwzOC44NzQgMjIuMTQxLDM5Ljg4MyAxOS43MDIsNDEuMDcyIEMxOS41ODEsNDAuODk5IDE5LjQzOSw0MC43MzggMTkuMjYsNDAuNjA2IEMxNS42MDYsMzcuOTAyIDEzLjU5NCwzNS4xNjkgMTMuNTk0LDMyLjkwOCBDMTMuNTk0LDI5LjcwNiAxOC4yMSwyNC40OTMgMjguNTIzLDIwLjA2NSBDMjkuNTM5LDE5LjYyOSAzMC4wMDgsMTguNDU0IDI5LjU3MiwxNy40MzggQzI5LjEzNiwxNi40MjMgMjcuOTYsMTUuOTU0IDI2Ljk0NSwxNi4zOSBDMTYuMDgxLDIxLjA1NSA5LjU5NCwyNy4yMjkgOS41OTQsMzIuOTA4IEM5LjU5NCwzNi4zOTggMTEuOTUyLDQwLjAzNyAxNi40MDksNDMuNDYgQzE1LjczNiw0NC4yNzcgMTUuMzg2LDQ1LjE2NCAxNS4zNDcsNDYuMTMyIEMxMS4wNSw0Mi45MDcgNywzOC40MDkgNywzMi45MDggWiBNMTE1Ljg4NSw3Ny4yNzkgQzExNi4wNjgsNzguNTggMTE2LjI0Miw3OS44MDkgMTE2LjA3Myw4MC44MTcgQzExNS44MjYsODIuMjk5IDExNS4xMDgsODMuNzM3IDExNC40MTUsODUuMTI4IEMxMTMuOTI4LDg2LjEwNCAxMTMuNDI1LDg3LjExNCAxMTMuMDcxLDg4LjE1NyBDMTEyLjgwNSw4OC45MzggMTEyLjU3NSw4OS43MDIgMTEyLjM1MSw5MC40NDMgQzExMS43ODQsOTIuMzIgMTExLjI5NCw5My45NDEgMTEwLjQsOTUuMjY0IEMxMDkuMDIxLDk3LjMwMyAxMDYuOTg5LDk4LjY4NCAxMDQuODM3LDEwMC4xNDYgTDEwNC4zNzcsMTAwLjQ1OSBDMTAyLjE4NiwxMDEuOTU0IDk5LjkwNSwxMDMuMTQgOTcuMjY0LDEwNC41MTIgTDk2LjYxNiwxMDQuODQ4IEM5My4xNjksMTA2LjY0NCA4OS4yNzYsMTA1Ljk0IDg0LjM1LDEwNS4wNTEgQzgxLjM2NywxMDQuNTEzIDc3Ljk4NiwxMDMuOTAxIDc0LjIyNywxMDMuNzk0IEM2OC44MzksMTAzLjYzNiA2NS40MzMsMTA0LjM2OCA2Mi42OTksMTA0Ljk1NSBDNTkuNjYyLDEwNS42MDYgNTcuOTksMTA1Ljk2NSA1NS4yMzEsMTA0LjgxMiBDNTEuODIxLDEwMy4zODQgNDkuMzk1LDEwMi4yMiA0Ni44NTYsMTAwLjc5MiBMNDYuNTEsMTAwLjU5OCBDNDQuMDE2LDk5LjE5OCA0MS44NjIsOTcuOTkgNDAuMTQyLDk1LjkxIEMzOC45ODEsOTQuNTA2IDM4LjMxNiw5Mi44MDcgMzcuNTQ2LDkwLjg0IEMzNy4yOTQsOTAuMTk2IDM3LjAzMyw4OS41MyAzNi43NDQsODguODQzIEMzNi4yOCw4Ny43MzcgMzUuNjI2LDg2LjcwNyAzNC45OTUsODUuNzEyIEMzNC4wOTIsODQuMjg5IDMzLjI0LDgyLjk0NiAzMi45MjcsODEuNDUyIEMzMi40LDc4Ljk0NSAzMi4wMjQsNzYuMjg3IDMxLjc3Niw3My4zMjcgQzMxLjY2Niw3Mi4wMTUgMzEuNzI5LDcxLjMzNiAzMS44MTUsNzAuMzk4IEMzMS44MTgsNzAuMzYzIDMxLjgyMSw3MC4zMjQgMzEuODI1LDcwLjI4OSBDMzIuMzQxLDcwLjQ1OSAzMi44NjcsNzAuNjI3IDMzLjQxNSw3MC43OTIgQzQzLjk2OCw3My45ODEgNTguNDU3LDc1LjczNyA3NC4yMTIsNzUuNzM3IEM4OS45NjcsNzUuNzM3IDEwNC40NTYsNzMuOTgxIDExNS4wMSw3MC43OTIgQzExNS40MDIsNzAuNjc0IDExNS43ODEsNzAuNTUzIDExNi4xNTYsNzAuNDMzIEMxMTUuODk5LDcxLjA3OSAxMTUuNjYyLDcxLjg2MSAxMTUuNTkyLDcyLjkyNiBDMTE1LjQ5LDc0LjQ4IDExNS43LDc1Ljk2NyAxMTUuODg1LDc3LjI3OSBaIE03NC4yMTIsNDkuNjkzIEMxMDkuMjM0LDQ5LjY5MyAxMjcuNTcxLDUzLjQwOCAxMjkuMDksNTUuOTUzIEMxMjguOTE0LDY0LjgxMiAxMDQuODczLDcxLjczNyA3NC4yMTIsNzEuNzM3IEM0My41NTIsNzEuNzM3IDE5LjUxLDY0LjgxMiAxOS4zMzQsNTUuOTUzIEMyMC44NTQsNTMuNDA4IDM5LjE5LDQ5LjY5MyA3NC4yMTIsNDkuNjkzIFogTTg4Ljc1NSwzOS40NTUgQzg4Ljc5NywzOS4zNDkgODguNzgxLDM5LjI0MSA4OC44MiwzOS4xMzQgQzExOC44MzksNDAuMzE2IDEyOSw0NC41MTMgMTI5LDQ2LjMxMiBMMTI5LDUxLjI1NCBDMTI2LDQ5Ljk3MSAxMjEuNDQ4LDQ4Ljc2IDExMy41MjQsNDcuNzQ5IEMxMDUuOTcyLDQ2Ljc4NiA5Ni41NDksNDYuMTM5IDg2LjIwNSw0NS44NTYgTDg4Ljc1NSwzOS40NTUgWiBNNjMuMzksMzcuOTg2IEM2MS43NzMsMzMuODkyIDYzLjI5NiwyOS4yMDUgNjcuMDEsMjYuODQxIEw2OS4yNzEsMjUuNDAzIEM3Mi4yNzMsMjMuNDkzIDc2LjE1MiwyMy40OTMgNzkuMTUzLDI1LjQwMyBMODEuNDE0LDI2Ljg0MSBDODUuMTI4LDI5LjIwNCA4Ni42NSwzMy44OTIgODUuMDM0LDM3Ljk4NiBMODEuOTY1LDQ1Ljc2MSBDNzkuNDIsNDUuNzE3IDc2LjgzMiw0NS42OTMgNzQuMjEyLDQ1LjY5MyBDNzEuNTkxLDQ1LjY5MyA2OS4wMDQsNDUuNzE3IDY2LjQ1OSw0NS43NjEgTDYzLjM5LDM3Ljk4NiBaIE0xOSw0Ni4zMTIgQzE5LDQ0LjUyNCAyOS4zNjYsNDAuMzIzIDU5LjM5MywzOS4xMzYgQzU5LjQzMiwzOS4yNDIgNTkuNTQ0LDM5LjM1IDU5LjU4NiwzOS40NTUgTDYyLjE1NSw0NS44NTYgQzUxLjgxMSw0Ni4xMzkgNDIuMjIsNDYuNzg2IDM0LjY2Nyw0Ny43NDkgQzI2Ljc0Myw0OC43NiAyMiw0OS45NzEgMTksNTEuMjU0IEwxOSw0Ni4zMTIgWiBNOTguMzE3LDEzMy44NzUgQzk3LjE2LDEzNS4xOTMgOTQuNzUyLDEzNy45MzggOTQuMDEzLDEzOC4zNDggQzkxLjMzNSwxMzkuNzQxIDg3LjY2NiwxNDEuNjQ5IDg0LjQ4MSwxNDIuMzgyIEM4Mi4xNDYsMTQyLjkyIDc5LjI1OCwxNDIuODg5IDc2LjQ2NiwxNDIuODYyIEM3NS44MSwxNDIuODU1IDc1LjE1NCwxNDIuODQ5IDc0LjUwNCwxNDIuODQ5IEM3Mi40NDgsMTQyLjg0OSA3MC4zNzMsMTQyLjE3NCA2OC4zNjgsMTQxLjUyMSBDNjcuMjY4LDE0MS4xNjMgNjYuMTMsMTQwLjc5MyA2NC45OTEsMTQwLjUzIEM2MS44MzMsMTM5LjgwNCA1OC45MzEsMTM4LjczOSA1Ni4xMTksMTM3LjI3NiBDNTUuMzg5LDEzNi44OTYgNTQuNjQ0LDEzNi41MzIgNTMuODk4LDEzNi4xNjkgQzUxLjY0MywxMzUuMDY4IDQ5LjUxMywxMzQuMDI4IDQ3LjgxLDEzMi41MzggQzQ2LjU0MSwxMzEuNDI5IDQ1LjYxMSwxMjkuODYxIDQ0LjYyNywxMjguMjAyIEM0My45NDgsMTI3LjA1NyA0My4yNDYsMTI1Ljg3MiA0Mi4zODMsMTI0Ljc1MyBDNDEuNDExLDEyMy40OTIgNDAuMTk2LDEyMi40NDggMzkuMDIxLDEyMS40MzggQzM3LjYyMSwxMjAuMjM1IDM2LjI5OSwxMTkuMSAzNS41NTMsMTE3Ljc1NCBDMzQuODc1LDExNi41MzIgMzQuMDU4LDExNS4zNTMgMzMuMjY4LDExNC4yMTMgQzMyLjEzNiwxMTIuNTggMzEuMDY4LDExMS4wMzcgMzAuNCwxMDkuNDIgQzI5LjY1OSwxMDcuNjI1IDI5LjExNSwxMDUuNTg2IDI4LjU4OCwxMDMuNjE0IEMyOC4yOCwxMDIuNDYxIDI3Ljk2MSwxMDEuMjY5IDI3LjYwMiwxMDAuMTEyIEMyNy4xNTQsOTguNjcxIDI3LjA3OSw5Ny4wMzUgMjcsOTUuMzA0IEMyNi45MjcsOTMuNzE2IDI2Ljg1Miw5Mi4wNzQgMjYuNDc3LDkwLjQ2IEMyNi4wOSw4OC43OTYgMjUuNDMyLDg3LjI0NyAyNC43OTYsODUuNzQ5IEMyNC4xMjgsODQuMTc2IDIzLjQ5OCw4Mi42OSAyMy4yNTUsODEuMjUyIEMyMy4wNDEsNzkuOTgxIDIzLjQ4Nyw3OC40NTUgMjMuOTU5LDc2LjgzOSBDMjQuNDc2LDc1LjA3NCAyNS4wMDksNzMuMjQ4IDI0Ljc5OCw3MS4zNzIgQzI0LjYyOCw2OS44NjUgMjQuMzQ3LDY4LjQzNCAyNC4wNjcsNjcuMDQxIEMyNS4yMDcsNjcuNjU5IDI2LjQ5LDY4LjI2NiAyNy45MjYsNjguODU3IEMyNy45LDY5LjI4OSAyNy44NjYsNjkuNjYzIDI3LjgzMiw3MC4wMyBDMjcuNzM5LDcxLjA0MiAyNy42NSw3MS45OTggMjcuNzksNzMuNjYxIEMyOC4wNTIsNzYuNzg3IDI4LjQ1Miw3OS42MDQgMjkuMDEyLDgyLjI3NCBDMjkuNDc0LDg0LjQ3OCAzMC42MTMsODYuMjcxIDMxLjYxNyw4Ny44NTQgQzMyLjE4Niw4OC43NSAzMi43MjIsODkuNTk3IDMzLjA1Nyw5MC4zOTIgQzMzLjMzMyw5MS4wNDkgMzMuNTgxLDkxLjY4NCAzMy44MjEsOTIuMjk4IEMzNC42NzksOTQuNDkgMzUuNDksOTYuNTYxIDM3LjA2LDk4LjQ1OSBDMzkuMjU1LDEwMS4xMTMgNDEuODI4LDEwMi41NTggNDQuNTUyLDEwNC4wODYgTDQ0Ljg5NSwxMDQuMjc4IEM0Ny41NDIsMTA1Ljc2OCA1MC4xNzIsMTA3LjAzIDUzLjY4NywxMDguNTAxIEM1Ny42MDIsMTEwLjEzOSA2MC4zNTMsMTA5LjU1MiA2My41MzgsMTA4Ljg2NiBDNjYuMDYyLDEwOC4zMjQgNjkuMjA0LDEwNy42NTIgNzQuMTEyLDEwNy43OTIgQzc3LjU3MSwxMDcuODkyIDgwLjY1NiwxMDguNDQ4IDgzLjYzOSwxMDguOTg3IEM4Ni4zNjcsMTA5LjQ3OSA4OS4wMTYsMTA5Ljk1OCA5MS41ODcsMTA5Ljk1OCBDOTMuOTQzLDEwOS45NTggOTYuMjM1LDEwOS41NTcgOTguNDYzLDEwOC4zOTYgTDk5LjEwOCwxMDguMDYyIEMxMDEuNzQ4LDEwNi42ODkgMTA0LjI0MiwxMDUuMzk0IDEwNi42MzEsMTAzLjc2NCBMMTA3LjA4NSwxMDMuNDU1IEMxMDkuNDU1LDEwMS44NDUgMTExLjkwNSwxMDAuMTc5IDExMy43MTQsOTcuNTA0IEMxMTQuOTQ4LDk1LjY3OSAxMTUuNTQ2LDkzLjY5NyAxMTYuMTgsOTEuNiBDMTE2LjM5MSw5MC45IDExNi42MDgsOTAuMTgxIDExNi44NTgsODkuNDQzIEMxMTcuMTI1LDg4LjY1NyAxMTcuNTQ4LDg3LjgxIDExNy45OTUsODYuOTEyIEMxMTguNzgxLDg1LjMzNSAxMTkuNjcyLDgzLjU0OCAxMjAuMDE5LDgxLjQ3OCBDMTIwLjI4OSw3OS44NiAxMjAuMDYzLDc4LjI2NCAxMTkuODQ2LDc2LjcyIEMxMTkuNjcyLDc1LjQ5MSAxMTkuNTA4LDc0LjMzIDExOS41ODMsNzMuMTg5IEMxMTkuNjM2LDcyLjM4NCAxMTkuODQ1LDcxLjkzMSAxMjAuMTM1LDcxLjMwMyBDMTIwLjQ0NSw3MC42MjkgMTIwLjgxMiw2OS44MzIgMTIwLjk3OCw2OC42NTcgQzEyMi40NSw2OC4wMzMgMTIzLjc1Myw2Ny4zOTIgMTI0LjkwMyw2Ni43NCBDMTI0LjQ3Miw2OC4yMjkgMTI0LjA1OSw2OS43NTkgMTIzLjg4MSw3MS4zMzYgQzEyMy43MDIsNzIuOTIyIDEyMy43OTMsNzQuNTA5IDEyMy44ODEsNzYuMDQzIEMxMjMuOTc5LDc3Ljc2MSAxMjQuMDczLDc5LjM4MyAxMjMuODA5LDgwLjk0OCBDMTIzLjUyNyw4Mi42MjMgMTIzLjYxNSw4NC4zMDcgMTIzLjcwMSw4NS45MzUgQzEyMy43OSw4Ny42MzIgMTIzLjg3NSw4OS4yMzQgMTIzLjU0LDkwLjY3MSBDMTIzLjI2Myw5MS44NjUgMTIyLjI4NCw5My4xMDIgMTIxLjI0Nyw5NC40MDkgQzEyMC4wOSw5NS44NyAxMTguODk0LDk3LjM4MSAxMTguMzE5LDk5LjIyOSBDMTE3LjcyOCwxMDEuMTMyIDExNy45NzEsMTAzLjA5IDExOC4yMDcsMTA0Ljk4MiBDMTE4LjQxMSwxMDYuNjI2IDExOC42MDQsMTA4LjE3OCAxMTguMTYzLDEwOS4yNDggQzExNy41MjMsMTEwLjc5NyAxMTYuMzIxLDExMi40MzggMTE1LjE1OCwxMTQuMDI2IEMxMTQuMjUzLDExNS4yNjIgMTEzLjMxNywxMTYuNTQgMTEyLjYwNCwxMTcuODI4IEwxMTIuMjYyLDExOC40NTUgQzExMS45OTIsMTE4Ljk1NSAxMTEuOTM2LDExOS4wNiAxMTEuNDc3LDExOS42NzYgTDExNC42ODQsMTIyLjA2NiBDMTE1LjI5NCwxMjEuMjQ3IDExNS40NTMsMTIwLjk2NSAxMTUuNzg0LDEyMC4zNTEgTDExNi4xMDIsMTE5Ljc2OCBDMTE2LjY5MywxMTguNyAxMTcuNTE2LDExNy41NzcgMTE4LjM4NiwxMTYuMzkgQzExOS42ODksMTE0LjYwOSAxMjEuMDM3LDExMi43NyAxMjEuODYsMTEwLjc3MyBDMTIyLjcwNCwxMDguNzI5IDEyMi40MjMsMTA2LjQ3NyAxMjIuMTc2LDEwNC40ODkgQzEyMS45ODQsMTAyLjk0OSAxMjEuODAzLDEwMS40OTQgMTIyLjEzOCwxMDAuNDE3IEMxMjIuNDkyLDk5LjI4IDEyMy40MSw5OC4xMjEgMTI0LjM4Myw5Ni44OTQgQzEyNS42NDcsOTUuMjk3IDEyNi45NTYsOTMuNjQ2IDEyNy40MzYsOTEuNTc3IEMxMjcuODk5LDg5LjU4OSAxMjcuNzk1LDg3LjYyNSAxMjcuNjk1LDg1LjcyNSBDMTI3LjYxOCw4NC4yNDggMTI3LjU0NCw4Mi44NTQgMTI3Ljc1Myw4MS42MTIgQzEyOC4wOTIsNzkuNjA0IDEyNy45ODEsNzcuNjc4IDEyNy44NzUsNzUuODE0IEMxMjcuNzkyLDc0LjM4MyAxMjcuNzE1LDczLjAzMiAxMjcuODU1LDcxLjc4NCBDMTI4LjAyMSw3MC4zMTYgMTI4LjQ4NSw2OC43MzIgMTI4LjkzNCw2Ny4yMDEgQzEyOS4zNDcsNjUuNzkgMTI5LjcxOCw2NC4zNDIgMTI5LjkzMiw2Mi45MTcgQzEzMi4zMjIsNjAuNDAyIDEzMyw1Ny45MjUgMTMzLDU1Ljg1NSBMMTMzLDUxLjU1OCBDMTQyLDQ2LjE3NCAxNDYuMjY4LDM5Ljc0NSAxNDYuMjY4LDMyLjkwOCBDMTQ2LjI2OCwxNS4wMTYgMTE0LjgxNSwxIDc0LjYzNCwxIEMzNC40NTMsMSAyLjgyMiwxNS4wMTYgMi44MjIsMzIuOTA4IEMyLjgyMiwzOS40OTIgNyw0NS43MjYgMTUsNTAuOTkyIEwxNSw1NS44NTUgQzE1LDU4LjE4NSAxNi4wODMsNjEuMDMyIDE5LjI2NSw2My44NjkgQzE5LjQzMiw2NS4wNzMgMTkuNzUyLDY2LjI4MSAxOS45OSw2Ny40NjEgQzIwLjI3OCw2OC44ODQgMjAuNjE2LDcwLjM1NCAyMC43ODIsNzEuODIgQzIwLjkwMyw3Mi44OTcgMjAuNTIzLDc0LjI2NyAyMC4wOTksNzUuNzE3IEMxOS41NDgsNzcuNjAzIDE4LjkzNCw3OS43NCAxOS4zMDEsODEuOTE2IEMxOS42MjEsODMuODE5IDIwLjM4LDg1LjU5NSAyMS4xMDksODcuMzEyIEMyMS43MDUsODguNzE1IDIyLjI3LDkwLjA0IDIyLjU3OCw5MS4zNjYgQzIyLjg3LDkyLjYyMyAyMi45MzUsOTQuMDE0IDIzLjAwMyw5NS40ODYgQzIzLjA5LDk3LjM5NSAyMy4xODEsOTkuMzY3IDIzLjc4MiwxMDEuMyBDMjQuMTE3LDEwMi4zNzkgMjQuNDEyLDEwMy40OCAyNC43MjMsMTA0LjY0NiBDMjUuMjgzLDEwNi43NDMgMjUuODYyLDEwOC45MSAyNi43MDIsMTEwLjk0NSBDMjcuNTM1LDExMi45NjIgMjguNzc4LDExNC43NTYgMjkuOTgsMTE2LjQ5MSBDMzAuNzQ4LDExNy42IDMxLjQ3NCwxMTguNjQ2IDMyLjA1NSwxMTkuNjk0IEMzMy4xNDgsMTIxLjY2NiAzNC44MDksMTIzLjA5MyAzNi40MTUsMTI0LjQ3MyBDMzcuNDgzLDEyNS4zOTEgMzguNDkzLDEyNi4yNTkgMzkuMjE1LDEyNy4xOTQgQzM5LjkyOSwxMjguMTIxIDQwLjU0LDEyOS4xNTEgNDEuMTg3LDEzMC4yNDIgQzQyLjI4OCwxMzIuMDk5IDQzLjQyNywxMzQuMDIgNDUuMTc2LDEzNS41NDkgQzQ3LjI4LDEzNy4zOSA0OS43NTIsMTM4LjU5NyA1Mi4xNDQsMTM5Ljc2NCBDNTIuODU4LDE0MC4xMTIgNTMuNTcyLDE0MC40NiA1NC4yNzIsMTQwLjgyNSBDNTcuMzg5LDE0Mi40NDYgNjAuNjAyLDE0My42MjUgNjQuMDk0LDE0NC40MjkgQzY1LjA2LDE0NC42NSA2Ni4wNjUsMTQ0Ljk3OSA2Ny4xMywxNDUuMzI0IEM2OS4zMjUsMTQ2LjAzOSA3MS44MTMsMTQ2Ljg0OSA3NC41MDQsMTQ2Ljg0OSBDNzUuMTQxLDE0Ni44NDkgNzUuNzgzLDE0Ni44NTUgNzYuNDI1LDE0Ni44NjEgQzc3LjA3NSwxNDYuODY4IDc3LjcyOSwxNDYuODc1IDc4LjM4NCwxNDYuODc1IEM4MC43NzksMTQ2Ljg3NSA4My4xODEsMTQ2Ljc4NiA4NS4zNzgsMTQ2LjI4IEM4OS4wNiwxNDUuNDM0IDkyLjk4OSwxNDMuMzg5IDk1Ljg1OSwxNDEuODk2IEM5Ny4xMywxNDEuMjM0IDk4Ljg0OCwxMzkuMzM1IDEwMS4zMjQsMTM2LjUxMyBDMTAyLjE0LDEzNS41ODIgMTAyLjk4NCwxMzQuNjIgMTAzLjM1MSwxMzQuMjg1IEwxMDAuNjQ5LDEzMS4zMzQgQzEwMC4xMjIsMTMxLjgxOCA5OS4zMjUsMTMyLjcyNiA5OC4zMTcsMTMzLjg3NSBMOTguMzE3LDEzMy44NzUgWiIgaWQ9IkZpbGwtNSI+PC9wYXRoPgogICAgICAgICAgICA8cGF0aCBkPSJNMTE5LjksMjAuMDY1IEMxMzAuMjEzLDI0LjQ5MyAxMzQuODMsMjkuNzA2IDEzNC44MywzMi45MDggQzEzNC44MywzNC4wMTIgMTM1LjcyNSwzNC45MDggMTM2LjgzLDM0LjkwOCBDMTM3LjkzNCwzNC45MDggMTM4LjgzLDM0LjAxMiAxMzguODMsMzIuOTA4IEMxMzguODMsMjcuMjI5IDEzMi4zNDMsMjEuMDU0IDEyMS40NzksMTYuMzkgQzEyMC40NjMsMTUuOTU0IDExOS4yODcsMTYuNDIzIDExOC44NTIsMTcuNDM4IEMxMTguNDE2LDE4LjQ1NCAxMTguODg1LDE5LjYyOSAxMTkuOSwyMC4wNjUiIGlkPSJGaWxsLTYiPjwvcGF0aD4KICAgICAgICAgICAgPHBhdGggZD0iTTE0NywxMzcuOTQ4IEwxNDcsMTM0IEwxNDMsMTM0IEwxNDMsMTM3Ljk0OCBDMTQzLDEzOC45NzUgMTQyLjE2NSwxNDAgMTQxLjEzOCwxNDAgTDEzOSwxNDAgTDEzOSwxNDQgTDE0MS4xMzgsMTQ0IEMxNDQuMzcxLDE0NCAxNDcsMTQxLjE4MSAxNDcsMTM3Ljk0OCIgaWQ9IkZpbGwtNyI+PC9wYXRoPgogICAgICAgIDwvZz4KICAgIDwvZz4KPC9zdmc+' );
	add_submenu_page( 'captaincore-admin', 'CaptainCore Dashboard', 'Dashboard', 'manage_options', 'captaincore-admin', 'captaincore_dashboard_callback' );
}

function captaincore_dashboard_callback() {
	// Loads the Customer Report template
	require_once dirname( __FILE__ ) . '/includes/admin-captaincore-dashboard.php';
}

function captaincore_settings_callback() {
	// Loads the Customer Report template
	require_once dirname( __FILE__ ) . '/includes/admin-captaincore-settings.php';
}

// allow SVGs
function cc_mime_types( $mimes ) {
	$mimes['svg'] = 'image/svg+xml';
	return $mimes;
}
add_filter( 'upload_mimes', 'cc_mime_types' );

// After payment received, connect up the Stripe info into the subscription.
function captaincore_woocommerce_payment_complete( $order_id ) {

	$customer_id    = get_field( '_customer_user', $order_id );
	$payment_tokens = WC_Payment_Tokens::get_customer_tokens( $customer_id );

	foreach ( $payment_tokens as $payment_token ) {
		$token_id = $payment_token->get_token();
	}

	$payment_cus_id  = get_field( '_stripe_customer_id', 'user_' . $customer_id );
	$payment_card_id = $token_id;

	// Find parent subscription id
	if ( wcs_order_contains_subscription( $order_id ) ) {
		$subscription    = wcs_get_subscriptions_for_order( $order_id );
		$subscription_id = key( $subscription );
	} else {
		$subscription_id = get_field( '_subscription_renewal', $order_id );
	}

	update_post_meta( $subscription_id, '_stripe_customer_id', $payment_cus_id );
	update_post_meta( $subscription_id, '_stripe_card_id', $payment_card_id );
	update_post_meta( $subscription_id, '_requires_manual_renewal', 'false' );
	update_post_meta( $subscription_id, '_payment_method', 'stripe' );
	update_post_meta( $subscription_id, '_payment_method_title', 'Credit card' );

}
add_action( 'woocommerce_payment_complete', 'captaincore_woocommerce_payment_complete' );


// Custom payment link for speedy checkout
function captaincore_get_checkout_payment_url( $payment_url ) {

	// Current $payment_url is
	// https://captcore-sitename.com/checkout/order-pay/1918?pay_for_order=true&key=wc_order_576c79296c346&subscription_renewal=true
	// Replace with
	// https://captcore-sitename.com/checkout-express/1918/?pay_for_order=true&key=wc_order_576c79296c346&subscription_renewal=true
	$home_url = esc_url( home_url( '/' ) );

	$new_payment_url = str_replace( $home_url . 'checkout/order-pay/', $home_url . 'checkout-express/', $payment_url );

	return $new_payment_url;
}

// Checks subscription for additional emails
add_filter( 'woocommerce_email_recipient_customer_completed_renewal_order', 'woocommerce_email_customer_invoice_add_recipients', 10, 2 );
add_filter( 'woocommerce_email_recipient_customer_renewal_invoice', 'woocommerce_email_customer_invoice_add_recipients', 10, 2 );
add_filter( 'woocommerce_email_recipient_customer_invoice', 'woocommerce_email_customer_invoice_add_recipients', 10, 2 );

function woocommerce_email_customer_invoice_add_recipients( $recipient, $order ) {

	// Finds subscription for the order
	$subscription = wcs_get_subscriptions_for_order( $order, array( 'order_type' => array( 'parent', 'renewal' ) ) );

	if ( $subscription and array_values( $subscription )[0] ) {
		// Find first subscription ID
		$subscription_id = array_values( $subscription )[0]->id;
		// Check ACF field for additional emails
		$additional_emails = get_field( 'additional_emails', $subscription_id );

		if ( $additional_emails ) {
			// Found additional emails so add them to the $recipients list
			$recipient .= ', ' . $additional_emails;
		}
	}
	return $recipient;
}
function my_acf_input_admin_footer() {
?>
<script type="text/javascript">
	acf.add_action('ready', function( $el ){

	// $el will be equivalent to $('body')

	// find a specific field
	staging_address = jQuery('#acf-field_57b7a2532cc5f');

	if(staging_address) {

		function sync_button() {
			// Copy production address to staging field
			jQuery('#acf-field_57b7a25d2cc60').val(jQuery('#acf-field_5619c94518f1c').val());

			// Copy production username to staging field
			if (jQuery('#acf-field_5619c94518f1c').val().includes(".kinsta.") ) {
				jQuery('#acf-field_57b7a2642cc61').val(jQuery('#acf-field_5619c97c18f1d').val() );
			} else {
				jQuery('#acf-field_57b7a2642cc61').val(jQuery('#acf-field_5619c97c18f1d').val() + "-staging");
			}

			// Copy production password to staging field (If Kinsta address)
			if (jQuery('#acf-field_5619c94518f1c').val().includes(".kinsta.") ) {
				jQuery('#acf-field_57b7a26b2cc62').val(jQuery('#acf-field_5619c98218f1e').val());
			}

			// Copy production protocol to staging field
			jQuery('#acf-field_57b7a2712cc63').val(jQuery('#acf-field_5619c98918f1f').val());

			// Copy production port to staging field
			jQuery('#acf-field_57b7a2772cc64').val(jQuery('#acf-field_5619c99d18f20').val());

			// Copy production database info to staging fields
			jQuery('#acf-field_5a90ba0c6c61a').val(jQuery('#acf-field_5a69f0a6e9686').val());
			jQuery('#acf-field_5a90ba1e6c61b').val(jQuery('#acf-field_5a69f0cce9687').val());

			// Copy production home directory to staging field
			jQuery('#acf-field_5845da68fc2c9').val(jQuery('#acf-field_58422bd538c32').val());
		}

		jQuery('.acf-field.acf-field-text.acf-field-57b7a25d2cc60').before('<div class="sync-button acf-field acf-field-text"><a href="#">Preload from Production</a></div>');
		jQuery('.sync-button a').click(function(e) {
			sync_button();
			return false;
		});
	}

	// do something to $field

});
</script>
<style>
.acf-postbox.seamless > .acf-fields > .acf-field.sync-button {
	position: absolute;
	right: 10px;
	padding-top: 10px;
	z-index: 9999;
}

</style>
<?php

}

add_action( 'acf/input/admin_footer', 'my_acf_input_admin_footer' );

add_filter(
	'query_vars', function( $vars ) {
		$vars[] = 'tag__in';
		$vars[] = 'tag__not_in';
		return $vars;
	}
);

add_filter( 'rest_query_vars', 'test_query_vars' );
function test_query_vars( $vars ) {
	$vars[] = 'captcore_website';
	$vars[] = 'global';
	return $vars;
}

add_action( 'pre_get_posts', 'test_pre_get_posts' );
function test_pre_get_posts( $query ) {

	global $wp;

	if ( isset( $wp->query_vars['rest_route'] ) ) {
		$rest_route = $wp->query_vars['rest_route'];
	}

	if ( isset( $rest_route ) and $rest_route == '/wp/v2/captcore_processlog' ) {

			// Filter only logs attached to processes in Growth, Maintenance and Support roles.
			$meta_query = array(
				array(
					'key'     => 'website',
					'value'   => '"' . $_GET['website'] . '"',
					'compare' => 'like',
				),
				array(
					'key'     => 'public',
					'value'   => '1',
					'compare' => 'like',
				),
			);

			$query->set( 'meta_query', $meta_query );

			return $query;

	} else {

		if ( isset( $_GET['website'] ) ) {

			$meta_query = array(
				array(
					'key'     => 'captcore_website',
					'value'   => '"' . $_GET['website'] . '"',
					'compare' => 'like',
				),
			);

			$query->set( 'meta_query', $meta_query );

			return $query;
		}
		if ( isset( $_GET['global'] ) ) {

			$meta_query = array(
				array(
					'key'     => 'global',
					'value'   => '1',
					'compare' => '=',
				),
			);

			$query->set( 'meta_query', $meta_query );

			return $query;
		}
	}
}

// Load custom WooCommerce templates from plugin's woocommerce directory
function captaincore_plugin_path() {
	// gets the absolute path to this plugin directory
	return untrailingslashit( plugin_dir_path( __FILE__ ) );
}
add_filter( 'woocommerce_locate_template', 'captaincore_woocommerce_locate_template', 10, 3 );

function captaincore_woocommerce_locate_template( $template, $template_name, $template_path ) {
	global $woocommerce;

	$_template = $template;

	if ( ! $template_path ) {
		$template_path = $woocommerce->template_url;
	}

	$plugin_path = captaincore_plugin_path() . '/woocommerce/';

	// Look within passed path within the theme - this is priority
	$template = locate_template(

		array(
			$template_path . $template_name,
			$template_name,
		)
	);

	// Modification: Get the template from this plugin, if it exists
	if ( ! $template && file_exists( $plugin_path . $template_name ) ) {
		$template = $plugin_path . $template_name;
	}

	// Use default template
	if ( ! $template ) {
		$template = $_template;
	}

	// Return what we found
	return $template;
}

// Hook in custom page templates
class PageTemplater {

	/**
	 * A reference to an instance of this class.
	 */
	private static $instance;

	/**
	 * The array of templates that this plugin tracks.
	 */
	protected $templates;

	/**
	 * Returns an instance of this class.
	 */
	public static function get_instance() {

		if ( null == self::$instance ) {
			self::$instance = new PageTemplater();
		}

		return self::$instance;

	}

	/**
	 * Initializes the plugin by setting filters and administration functions.
	 */
	private function __construct() {

		$this->templates = array();

		// Add a filter to the attributes metabox to inject template into the cache.
		if ( version_compare( floatval( get_bloginfo( 'version' ) ), '4.7', '<' ) ) {

			// 4.6 and older
			add_filter(
				'page_attributes_dropdown_pages_args',
				array( $this, 'register_project_templates' )
			);

		} else {

			// Add a filter to the wp 4.7 version attributes metabox
			add_filter(
				'theme_page_templates', array( $this, 'add_new_template' )
			);

		}

		// Add a filter to the save post to inject out template into the page cache
		add_filter(
			'wp_insert_post_data',
			array( $this, 'register_project_templates' )
		);

		// Add a filter to the template include to determine if the page has our
		// template assigned and return it's path
		add_filter(
			'template_include',
			array( $this, 'view_project_template' )
		);

		// Add your templates to this array.
		$this->templates = array(
			'templates/page-company-handbook.php' => 'Company Handbook',
			'templates/page-activities.php'       => 'Activities',
			'templates/page-checkout-express.php' => 'Checkout Express',
			'templates/page-websites.php'         => 'Website Recommendations',
		);

	}

	/**
	 * Adds our template to the page dropdown for v4.7+
	 */
	public function add_new_template( $posts_templates ) {
		$posts_templates = array_merge( $posts_templates, $this->templates );
		return $posts_templates;
	}

	/**
	 * Adds our template to the pages cache in order to trick WordPress
	 * into thinking the template file exists where it doens't really exist.
	 */
	public function register_project_templates( $atts ) {

		// Create the key used for the themes cache
		$cache_key = 'page_templates-' . md5( get_theme_root() . '/' . get_stylesheet() );

		// Retrieve the cache list.
		// If it doesn't exist, or it's empty prepare an array
		$templates = wp_get_theme()->get_page_templates();
		if ( empty( $templates ) ) {
			$templates = array();
		}

		// New cache, therefore remove the old one
		wp_cache_delete( $cache_key, 'themes' );

		// Now add our template to the list of templates by merging our templates
		// with the existing templates array from the cache.
		$templates = array_merge( $templates, $this->templates );

		// Add the modified cache to allow WordPress to pick it up for listing
		// available templates
		wp_cache_add( $cache_key, $templates, 'themes', 1800 );

		return $atts;

	}

	/**
	 * Checks if the template is assigned to the page
	 */
	public function view_project_template( $template ) {

		// Get global post
		global $post;

		// Return template if post is empty
		if ( ! $post ) {
			return $template;
		}

		// Return default template if we don't have a custom one defined
		if ( ! isset(
			$this->templates[ get_post_meta(
				$post->ID, '_wp_page_template', true
			) ]
		) ) {
			return $template;
		}

		$file = plugin_dir_path( __FILE__ ) . get_post_meta(
			$post->ID, '_wp_page_template', true
		);

		// Just to be safe, we check if the file exist first
		if ( file_exists( $file ) ) {
			return $file;
		} else {
			echo $file;
		}

		// Return template
		return $template;

	}

}
add_action( 'plugins_loaded', array( 'PageTemplater', 'get_instance' ), 10 );

/* Filter the single_template with our custom function*/

// Hooks in custom post template from plugin
add_filter( 'single_template', 'captaincore_custom_template' );

function captaincore_custom_template( $single ) {

	global $wp_query, $post;

	/* Checks for single template by post type */
	if ( $post->post_type == 'captcore_process' ) {
		if ( file_exists( plugin_dir_path( __FILE__ ) . '/templates/single-captcore_process.php' ) ) {
			return plugin_dir_path( __FILE__ ) . '/templates/single-captcore_process.php';
		}
	}

	return $single;

}

// Custom filesize function
function captaincore_human_filesize( $size, $precision = 2 ) {
	$units = array( 'B', 'kB', 'MB', 'GB', 'TB', 'PB', 'EB', 'ZB', 'YB' );
	$step  = 1024;
	$i     = 0;
	while ( ( $size / $step ) > 0.9 ) {
		$size = $size / $step;
		$i++;
	}
	return round( $size, $precision ) . $units[ $i ];
}

// Adds ACF Option page
if( function_exists('acf_add_options_page') ) {
	acf_add_options_page();
}

function captaincore_activation_redirect( $plugin ) {
	if( $plugin == plugin_basename( __FILE__ ) ) {
			exit( wp_redirect( admin_url( 'admin.php?page=captaincore-admin' ) ) );
	}
}
add_action( 'activated_plugin', 'captaincore_activation_redirect' );

function sort_by_name($a, $b) {
    return strcmp($a["name"], $b["name"]);
}

function captaincore_fetch_socket_address() {

	$socket_address = str_replace( "https://", "wss://", CAPTAINCORE_CLI_ADDRESS );

	if ( defined( 'CAPTAINCORE_CLI_SOCKET_ADDRESS' ) ) {
		$socket_address = "wss://" . CAPTAINCORE_CLI_SOCKET_ADDRESS;
	}

	return $socket_address;
}

// Load CaptainCore on page /account/
add_filter( 'template_include', 'load_captaincore_template' );
function load_captaincore_template( $original_template ) {
  if ( is_user_logged_in() && is_account_page() ) {
	global $wp;
	$request = explode( '/', $wp->request );
	if ( end($request) == 'my-account' ) {
		wp_redirect("/account");
	}
  }
  if ( is_page( 'account' ) ) {
    return plugin_dir_path( __FILE__ ) . 'templates/core.php';
  } else {
    return $original_template;
  }
}

function captaincore_head_content() {
    ob_start();
    do_action('wp_head');
    return ob_get_clean();
}

function captaincore_header_content_extracted() {
	$output = "<script type='text/javascript'>\n/* <![CDATA[ */\n";
	$head = captaincore_head_content();
	preg_match_all('/(var wpApiSettings.+)/', $head, $results );
	foreach( $results as $matches ) {
		foreach( $matches as $match ) {
			$output = $output . $match . "\n";
		}
	}
	$output = $output . "</script>";
	preg_match_all('/(<link rel="(icon|apple-touch-icon).+)/', $head, $results );
	if ( isset($results ) && $results[0] ) {
		$output = $output . implode("\n", $results[0]);
	}
	echo $output;
}