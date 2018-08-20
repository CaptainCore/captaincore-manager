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
 * Plugin Name:       CaptainCore GUI
 * Plugin URI:        https://captaincore.io
 * Description:       Open Source Toolkit for Managing WordPress Sites
 * Version:           0.2.7
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

require 'inc/woocommerce-my-account-endpoints.php';
require 'inc/constellix-api/constellix-api.php';
require 'inc/woocommerce-custom-password-fields.php';
require 'inc/mailgun-api.php';
require 'inc/process-functions.php';
require 'inc/bulk-actions.php'; // Custom bulk actions.

function captaincore_rewrite() {
	add_rewrite_rule( '^captaincore-api/([^/]*)/?', 'index.php?pagename=captaincore-api&callback=$matches[1]', 'top' );
	add_rewrite_rule( '^checkout-express/([^/]*)/?', 'index.php?pagename=checkout-express&callback=$matches[1]', 'top' );

	// Removed the following paths from Google by forcing a 404 response
	// TO DO: Remove after Google results for "site:anchor.host" are clean
	add_rewrite_rule( '^captcore_quicksave/([^/]*)/?', 'index.php?pagename=404', 'top' );
	add_rewrite_rule( '^cc_quicksave/([^/]*)/?', 'index.php?pagename=404', 'top' );
	add_rewrite_rule( '^captcore_snapshot/([^/]*)/?', 'index.php?pagename=404', 'top' );
	add_rewrite_rule( '^captcore_contact/([^/]*)/?', 'index.php?pagename=404', 'top' );

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
add_filter( 'gutenberg_can_edit_post_type', 'captaincore_disable_gutenberg', 10, 2 );

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
	$role_check_partner    = in_array( 'partner', $user->roles ) + in_array( 'administrator', $user->roles ) + in_array( 'editor', $user->roles );
	$role_check_subscriber = in_array( 'subscriber', $user->roles ) + in_array( 'partner', $user->roles ) + in_array( 'administrator', $user->roles ) + in_array( 'editor', $user->roles );

	if ( ! $role_check_admin ) {
		unset( $current_menu['handbook'] );
		unset( $current_menu['health'] );
		unset( $current_menu['manage'] );
	}
	if ( ! $role_check_partner ) {
		unset( $current_menu['licenses'] );
		unset( $current_menu['configs'] );
	}
	if ( ! $role_check_subscriber ) {
		unset( $current_menu['dns'] );
		unset( $current_menu['logs'] );
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


function captaincore_admin_menu() {
	add_menu_page(
		'CaptainCore',
		'CaptainCore',
		'read',
		'captaincore',
		'', // Callback, leave empty
		'dashicons-welcome-widgets-menus',
		5
	);
}

add_action( 'admin_menu', 'captaincore_admin_menu' );

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
		'show_in_menu'        => 'captaincore',
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
		'supports'            => array( 'title', 'editor', 'author', 'thumbnail', 'revisions', 'wpcom-markdown' ),
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
function server_post_type() {

	$labels       = array(
		'name'                  => _x( 'Servers', 'Post Type General Name', 'captaincore' ),
		'singular_name'         => _x( 'Server', 'Post Type Singular Name', 'captaincore' ),
		'menu_name'             => __( 'Servers', 'captaincore' ),
		'name_admin_bar'        => __( 'Server', 'captaincore' ),
		'archives'              => __( 'Server Archives', 'captaincore' ),
		'parent_item_colon'     => __( 'Parent Server:', 'captaincore' ),
		'all_items'             => __( 'Servers', 'captaincore' ),
		'add_new_item'          => __( 'Add New Server', 'captaincore' ),
		'add_new'               => __( 'Add New', 'captaincore' ),
		'new_item'              => __( 'New Server', 'captaincore' ),
		'edit_item'             => __( 'Edit Server', 'captaincore' ),
		'update_item'           => __( 'Update Server', 'captaincore' ),
		'view_item'             => __( 'View Server', 'captaincore' ),
		'search_items'          => __( 'Search Servers', 'captaincore' ),
		'not_found'             => __( 'Not found', 'captaincore' ),
		'not_found_in_trash'    => __( 'Not found in Trash', 'captaincore' ),
		'featured_image'        => __( 'Featured Image', 'captaincore' ),
		'set_featured_image'    => __( 'Set featured image', 'captaincore' ),
		'remove_featured_image' => __( 'Remove featured image', 'captaincore' ),
		'use_featured_image'    => __( 'Use as featured image', 'captaincore' ),
		'insert_into_item'      => __( 'Insert into server', 'captaincore' ),
		'uploaded_to_this_item' => __( 'Uploaded to this server', 'captaincore' ),
		'items_list'            => __( 'Server list', 'captaincore' ),
		'items_list_navigation' => __( 'Server list navigation', 'captaincore' ),
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
		'label'               => __( 'Server', 'captaincore' ),
		'description'         => __( 'Server Description', 'captaincore' ),
		'labels'              => $labels,
		'supports'            => array(),
		'hierarchical'        => false,
		'public'              => false,
		'show_ui'             => true,
		'show_in_menu'        => false,
		'menu_position'       => 10,
		'menu_icon'           => 'dashicons-building',
		'show_in_admin_bar'   => true,
		'show_in_nav_menus'   => true,
		'can_export'          => true,
		'has_archive'         => true,
		'exclude_from_search' => true,
		'publicly_queryable'  => false,
		'capability_type'     => 'page',
		'capabilities'        => $capabilities,
		'map_meta_cap'        => true,
	);
	register_post_type( 'captcore_server', $args );

}
add_action( 'init', 'server_post_type', 0 );

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
	$pages = array( 'captcore_website', 'captcore_customer', 'captcore_contact', 'captcore_domain', 'captcore_changelog', 'captcore_process', 'captcore_processlog', 'captcore_server', 'captcore_snapshot', 'captcore_quicksave' );
	if ( in_array( $screen->post_type, $pages ) ) {
		// Before:
		add_action(
			'all_admin_notices', function() {
					include 'inc/admin-website-tabs.php';
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
		$post_id = get_post_meta( $object['id'], $field_name )[0][0];
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
					'value'   => '"' . $post_id . '"', // matches exaclty "123", not just 123. This prevents a match for "1234"
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
				'views'        => get_field( 'views', $website->ID ),
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
	jetpack_require_lib( 'markdown' );

	$description = get_post_meta( $object['id'], $field_name );

	if ( $description[0] ) {
		$description = WPCom_Markdown::get_instance()->transform(
			$description[0], array(
				'id'      => false,
				'unslash' => false,
			)
		);
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

add_filter( 'acf/load_field/key=field_590681f3c0775', 'acf_load_color_field_choices' );

// run after ACF saves
add_action( 'acf/save_post', 'captaincore_acf_save_post_after', 20 );
function captaincore_acf_save_post_after( $post_id ) {

	if ( get_post_type( $post_id ) == 'captcore_website' ) {
		$custom        = get_post_custom( $post_id );
		$hosting_plan  = $custom['hosting_plan'][0];
		$hosting_price = $custom['hosting_price'][0];
		$addons        = get_field( 'addons', $post_id );
		$customer      = get_field( 'customer', $post_id );
		$views         = get_field( 'views', $post_id );
		$status        = $custom['status'][0];
		$total         = 0;
		$addon_total   = 0;

		if ( $customer == '' ) {
			// no customer found, generate and assign the customer
			if ( get_field( 'billing_date', $post_id ) ) {
				$website_billing_date = date( 'Ymd', strtotime( get_field( 'billing_date', $post_id ) ) );
			}
			$website_hosting_plan   = get_field( 'hosting_plan', $post_id );
			$website_hosting_price  = get_field( 'hosting_price', $post_id );
			$website_addons         = get_field( 'addons', $post_id );
			$website_billing_method = get_field( 'billing_method', $post_id );
			$website_billing_email  = get_field( 'billing_email', $post_id );

			// Create customer object
			$my_post = array(
				'post_title'  => get_the_title( $post_id ),
				'post_type'   => 'captcore_customer',
				'post_status' => 'publish',
				'post_author' => 1,
			);

			// Insert the post into the database
			$customer_post_id = wp_insert_post( $my_post );

			// Add data to customer
			if ( $website_hosting_plan ) {
				update_field( 'field_549d42b57c687', $website_hosting_plan, $customer_post_id );
			} else {
				update_field( 'field_549d42b57c687', 'basic', $customer_post_id );
			}
			if ( $website_hosting_price ) {
				// assign hosting plan
				update_field( 'field_549d42d07c688', $website_hosting_price, $customer_post_id );

				// calculate and assign new total price
				$hosting_price = get_field( 'hosting_price', $post_id );
				$addons        = get_field( 'addons', $post_id );

				// check if the repeater field has rows of data
				if ( have_rows( 'addons', $post_id ) ) :

					// loop through the rows of data
					while ( have_rows( 'addons', $post_id ) ) :
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
				update_field( 'field_56181aaed39a9', $total_price, $customer_post_id );
			} else {
				update_field( 'field_549d42d07c688', '240', $customer_post_id );    // Hosting Price
				update_field( 'field_56181aaed39a9', '240', $customer_post_id );    // Total Price
				update_field( 'field_56252d8051ee2', 'year', $customer_post_id );   // Billing Terms
			}
			if ( $website_billing_date ) {
				update_field( 'field_549d430d7c68c', $website_billing_date, $customer_post_id );
			} else {
				// No date so assign the first day of the next month
				$first_day_next_month = date( 'Ymd', strtotime( date( 'm', strtotime( '+1 month' ) ) . '/01/' . date( 'Y', strtotime( '+1 month' ) ) . ' 00:00:00' ) );
				update_field( 'field_549d430d7c68c', $first_day_next_month, $customer_post_id );
			}
			if ( $website_addons ) {
				update_field( 'field_549ed77808354', $website_addons, $customer_post_id );
			}
			if ( $website_billing_method ) {
				update_field( 'field_549d42d37c689', $website_billing_method, $customer_post_id );
			}
			if ( $website_billing_email ) {
				update_field( 'field_549d43087c68b', $website_billing_email, $customer_post_id );
			}
			update_field( 'field_561936147136b', 'active', $customer_post_id );

			// Link website to customer
			update_field( 'field_56181a1fcf6e2', $customer_post_id, $post_id );

		} else {

			// Load customer data
			$customer_id = $customer[0];

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
		if ( isset( $views ) and is_array( $customer ) ) {

			$views       = 0;
			$storage     = 0;
			$customer_id = $customer[0];

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
							'value'   => 'active', // matches exaclty "123", not just 123. This prevents a match for "1234"
							'compare' => '=',
						),
						array(
							'key'     => 'customer', // name of custom field
							'value'   => '"' . $customer_id . '"', // matches exaclty "123", not just 123. This prevents a match for "1234"
							'compare' => 'LIKE',
						),
					),
				)
			);

			if ( $websites ) :
				foreach ( $websites as $website ) :

					$storage = $storage + get_field( 'storage', $website->ID );
					$views   = $views + get_field( 'views', $website->ID );

				 endforeach;
			endif;

			update_field( 'field_59089b37bd588', $storage, $customer_id );
			update_field( 'field_59089b3ebd589', $views, $customer_id );

		}
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
	$storage             = $post->storage;
	$views               = $post->views;
	$email               = $post->email;
	$server              = $post->server;
	$core                = $post->core;
	$plugins             = $post->plugins;
	$themes              = $post->themes;
	$users               = $post->users;
	$home_url            = $post->home_url;
	$git_commit          = $post->git_commit;
	$git_status          = trim( base64_decode( $post->git_status ) );
	$token_key           = $post->token_key;
	$data                = $post->data;
	$site_id             = $post->site_id;

	// Error if token not valid
	if ( $post->token != CAPTAINCORE_CLI_TOKEN ) {
		// Create the response object
		return new WP_Error( 'token_invalid', 'Invalid Token', array( 'status' => 404 ) );
	}

	// Error if site not valid
	if ( get_post_type( $site_id) != "captcore_website" ) {
		// Create the response object
		return new WP_Error( 'command_invalid', 'Invalid Command', array( 'status' => 404 ) );
	}

	$site_name   = get_field( 'site', $site_id );
	$domain_name = get_the_title( $site_id );

	// Copy site
	if ( $command == 'copy' and $email ) {

		$site_source      = get_the_title( $post->site_source_id );
		$site_destination = get_the_title( $post->site_destination_id );

		// Send out completed email notice
		$to      = $email;
		$subject = "Anchor Hosting - Copy site ($site_source) to ($site_destination) completed";
		$body    = "Completed copying $site_source to $site_destination.<br /><br /><a href=\"http://$site_destination\">$site_destination</a>";
		$headers = array( 'Content-Type: text/html; charset=UTF-8' );

		wp_mail( $to, $subject, $body, $headers );

		echo 'copy-site email sent';

	}

	// Production deploy to staging
	if ( $command == 'production-to-staging' and $email ) {

		$url         = 'https://staging-' . get_field( 'site_staging', $site_id ) . '.kinsta.com';

		// Send out completed email notice
		$to      = $email;
		$subject = "Anchor Hosting - Deploy to Staging ($domain_name)";
		$body    = 'Deploy to staging completed for ' . $domain_name . '.<br /><br /><a href="' . $url . '">' . $url . '</a>';
		$headers = array( 'Content-Type: text/html; charset=UTF-8' );

		wp_mail( $to, $subject, $body, $headers );

		echo 'production-to-staging email sent';

	}

	// Kinsta staging deploy to production
	if ( $command == 'staging-to-production' and $email ) {

		$site_name   = get_field( 'site', $site_id );
		$domain_name = get_the_title( $site_id );
		$url         = 'https://' . get_field( 'site', $site_id ) . '.kinsta.com';

		// Send out completed email notice
		$to      = $email;
		$subject = "Anchor Hosting - Deploy to Production ($domain_name)";
		$body    = 'Deploy to production completed for ' . $domain_name . '.<br /><br /><a href="' . $url . '">' . $domain_name . '</a>';
		$headers = array( 'Content-Type: text/html; charset=UTF-8' );

		wp_mail( $to, $subject, $body, $headers );

		echo 'staging-to-production email sent';

	}

	// Generate a new snapshot.
	if ( $command == 'snapshot' and $archive and $storage ) {

		// Create post object
		$my_post = array(
			'post_title'  => 'Snapshot',
			'post_type'   => 'captcore_snapshot',
			'post_status' => 'publish',
		);

		// Insert the post into the database
		$snapshot_id = wp_insert_post( $my_post );

		update_field( 'field_580b7cf4f2790', $archive, $snapshot_id );
		update_field( 'field_580b9776f2791', $storage, $snapshot_id );
		update_field( 'field_580b9784f2792', $site_id, $snapshot_id );
		update_field( 'field_59aecbd173318', $email, $snapshot_id );

		// Adds snapshot ID to title
		$my_post = array(
			'ID'         => $snapshot_id,
			'post_title' => 'Snapshot ' . $snapshot_id,
		);

		wp_update_post( $my_post );

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
		update_field( 'field_5a9421b004ed3', wp_slash( json_encode( $plugins ) ), $site_id );
		update_field( 'field_5a9421b804ed4', wp_slash( json_encode( $themes ) ), $site_id );
		update_field( 'field_5b2a900c85a77', wp_slash( json_encode( $users ) ), $site_id );
		update_field( 'field_5a9421bc04ed5', $core, $site_id );
		update_field( 'field_5a944358bf146', $home_url, $site_id );

		echo '{"response":"Completed sync-data for ' . $site_id . '"}';

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
				'site_id'     => $site_id,
				'update_type' => $row->type,
				'update_log'  => $update_log,
				'created_at'  => $date_formatted,
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

		// If new info sent then update otherwise continue with quicksavee import
		if ( $plugins &&  $themes && $users && $core && $home_url ) {
			update_field( 'field_5a9421b004ed3', wp_slash( $plugins ), $site_id );
			update_field( 'field_5a9421b804ed4', wp_slash( $themes ), $site_id );
			update_field( 'field_5b2a900c85a77', wp_slash( $users ), $site_id );
			update_field( 'field_5a9421bc04ed5', $core, $site_id );
			update_field( 'field_5a944358bf146', $home_url, $site_id );
		}

		foreach ( $data as $row ) {

			// Format for mysql timestamp format. Changes "1530817828" to "2018-06-20 09:15:20"
			$epoch = $row->date;
			$dt = new DateTime("@$epoch");  // convert UNIX timestamp to PHP DateTime
			$date_formatted = $dt->format('Y-m-d H:i:s'); // output = 2017-01-01 00:00:00

			$themes         = json_encode( $row->themes );
			$plugins        = json_encode( $row->plugins );

			$new_quicksave = array(
				'site_id'    => $site_id,
				'created_at' => $date_formatted,
				'git_status' => $row->git_status,
				'git_commit' => $row->git_commit,
				'core'       => $row->core,
				'themes'     => $themes,
				'plugins'    => $plugins,
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

	// Updates views and storage usage
	if ( $command == 'usage-update' ) {
		update_field( 'field_57e0b2b17eb2a', $storage, $site_id );
		update_field( 'field_57e0b2c07eb2b', $views, $site_id );
		do_action( 'acf/save_post', $site_id ); // Runs ACF save post hooks
		return array( "response" => "Completed usage-update for ' . $site_id" );
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

function captaincore_site_func( $request ) {
	$site_id = $request['id'];

	if ( ! captaincore_verify_permissions( $site_id ) ) {
		return new WP_Error( 'token_invalid', 'Invalid Token', array( 'status' => 403 ) );
	}

	$db_quicksaves = new CaptainCore\quicksaves;
	$quicksaves = $db_quicksaves->fetch( $site_id );
	foreach ($quicksaves as $key => $quicksave) {
		$quicksaves[$key]->plugins = json_decode($quicksaves[$key]->plugins);
		$quicksaves[$key]->themes = json_decode($quicksaves[$key]->themes);
		$quicksaves[$key]->view_changes = false;
		$quicksaves[$key]->view_files = [];
	}
	return $quicksaves;
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

	// Custom endpoint for CaptainCore site
	register_rest_route(
		'captaincore/v1', '/site/(?P<id>[\d]+)/quicksaves', array(
			'methods'       => 'GET',
			'callback'      => 'captaincore_site_func',
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
		'captcore_website', 'views',
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
		'captcore_customer', 'preloaded_plugins',
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
		'captcore_customer', 'views',
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

			$website_id = $endpoint_all[1];

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

		// custom auth on customer endpoint, exlcuding global posts
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
							'value'   => '"' . $id . '"', // matches exaclty "123", not just 123. This prevents a match for "1234"
							'compare' => 'LIKE',
						),
						array(
							'key'     => 'partner', // name of custom field
							'value'   => '"' . $id . '"', // matches exaclty "123", not just 123. This prevents a match for "1234"
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
					'value'   => '"' . $partner_id . '"', // matches exaclty "123", not just 123. This prevents a match for "1234"
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
						'value'   => '"' . $partner_id . '"', // matches exaclty "123", not just 123. This prevents a match for "1234"
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
						'value'   => '"' . $partner_id . '"', // matches exaclty "123", not just 123. This prevents a match for "1234"
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

// List sites current user has access to
function captaincore_fetch_customers() {

	$user       = wp_get_current_user();
	$role_check = in_array( 'administrator', $user->roles );

	// Bail if role not assigned
	if ( !$role_check ) {
		return "Error: Please log in.";
	}

	$customers = get_posts( array(
		'order'          => 'asc',
		'orderby'        => 'title',
		'posts_per_page' => '-1',
		'post_type'      => 'captcore_customer'
	) );

	return $customers;

}

// List sites current user has access to
function captaincore_fetch_sites() {

	$user       = wp_get_current_user();
	$role_check = in_array( 'subscriber', $user->roles ) + in_array( 'customer', $user->roles ) + in_array( 'partner', $user->roles ) + in_array( 'administrator', $user->roles ) + in_array( 'editor', $user->roles );
	$partner    = get_field( 'partner', 'user_' . get_current_user_id() );

	// Bail if not assigned a role
	if ( !$role_check ) {
		return "Error: Please log in.";
	}

	// Administrators return all sites
	if ( $partner && $role_check && in_array( 'administrator', $user->roles ) ) {
		$sites = get_posts( array(
			'order'          => 'asc',
			'orderby'        => 'title',
			'posts_per_page' => '-1',
			'post_type'      => 'captcore_website',
			'meta_query'     => array(
				'relation' => 'AND',
				array(
					'key'     => 'status',
					'value'   => 'closed',
					'compare' => '!=',
				),
		) ) );

		return $sites;
	}

	// Bail if no partner set.
	if ( ! is_array($partner) ) {
		return;
	}

	// New array to collect IDs
	$site_ids = array();

	// Loop through each partner assigned to current user
	foreach ( $partner as $partner_id ) {

	// Load websites assigned to partner
	$arguments = array(
		'fields'         => 'ids',
		'post_type'      => 'captcore_website',
		'posts_per_page' => '-1',
		'meta_query'     => array(
			'relation' => 'AND',
			array(
				'key'     => 'partner',
				'value'   => '"' . $partner_id . '"',
				'compare' => 'LIKE',
			),
			array(
				'key'     => 'status',
				'value'   => 'closed',
				'compare' => '!=',
			),
			array(
				'key'     => 'address',
				'compare' => 'EXISTS',
			),
			array(
				'key'     => 'address',
				'value'   => '',
				'compare' => '!=',
			),
		),
	);

	$sites = new WP_Query( $arguments );

	foreach($sites->posts as $site_id) {
		if( !in_array($site_id, $site_ids) ) {
			$site_ids[] = $site_id;
		}
	}

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
			array(
				'key'     => 'status',
				'value'   => 'closed',
				'compare' => '!=',
			),
			array(
				'key'     => 'address',
				'compare' => 'EXISTS',
			),
			array(
				'key'     => 'address',
				'value'   => '',
				'compare' => '!=',
			),
		),
	);

	$sites = new WP_Query( $arguments );

	foreach($sites->posts as $site_id) {
		if( !in_array($site_id, $site_ids) ) {
			$site_ids[] = $site_id;
		}
	}

	}

	// Bail if no site ids found
	if ( count($site_ids) == 0 ) {
		return;
	}

	$sites = get_posts( array(
		'include'        => $site_ids,
		'order'          => 'asc',
		'orderby'        => 'title',
		'posts_per_page' => '-1',
		'post_type'      => 'captcore_website'
	) );

	return $sites;

}

// Loads all domains for current user
function captaincore_fetch_domains() {

	$user        = wp_get_current_user();
	$role_check  = in_array( 'subscriber', $user->roles ) + in_array( 'customer', $user->roles ) + in_array( 'partner', $user->roles ) + in_array( 'administrator', $user->roles ) + in_array( 'editor', $user->roles );
	$partner     = get_field( 'partner', 'user_' . get_current_user_id() );
	$all_domains = [];

	// Bail if not assigned a role
	if ( !$role_check ) {
		return "Error: Please log in.";
	}

	// Administrators return all sites
	if ( in_array( 'administrator', $user->roles ) ) {
		$customers = get_posts( array(
			'order'          => 'asc',
			'orderby'        => 'title',
			'posts_per_page' => '-1',
			'post_type'      => 'captcore_customer',
			'meta_query'     => array(
				'relation' => 'AND',
				array(
					'key'     => 'status',
					'value'   => 'closed',
					'compare' => '!=',
				),
			) ) );
	}

	if ( in_array( 'subscriber', $user->roles ) or in_array( 'customer', $user->roles ) or in_array( 'partner', $user->roles ) or in_array( 'editor', $user->roles ) ) {

		$customers = array();

		$user_id = get_current_user_id();
		$partner = get_field( 'partner', 'user_' . get_current_user_id() );
		if ( $partner ) {
			foreach ( $partner as $partner_id ) {
				$websites_for_partner = get_posts(
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
								'value'   => '"' . $partner_id . '"', // matches exaclty "123", not just 123. This prevents a match for "1234"
								'compare' => 'LIKE',
							),
						),
					)
				);
				foreach ( $websites_for_partner as $website ) :
					$customers[] = get_field( 'customer', $website );
				endforeach;
			}
		}
	}

	foreach ( $customers as $customer ) :

		if ( is_array( $customer ) ) {
			$customer = $customer[0];
		}

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

	// Sort array by domain name
	ksort( $all_domains );

	return $all_domains;

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
								'value'   => '"' . $partner_id . '"', // matches exaclty "123", not just 123. This prevents a match for "1234"
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
		$record_type   = $record_update['record_type'];
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
						'level'       => $mx_record['priority'],
						'disableFlag' => false,
					);
				}

				$post = array(
					'recordOption' => 'roundRobin',
					'name'         => $record_name,
					'ttl'          => $record_ttl,
					'roundRobin'   => $mx_records,
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
					'roundRobin'   => array(
						array(
							'value'       => $record_value,
							'disableFlag' => false,
						),
					),
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
						'level'       => $mx_record['priority'],
						'disableFlag' => false,
					);
				}

				$post = array(
					'recordOption' => 'roundRobin',
					'name'         => $record_name,
					'ttl'          => $record_ttl,
					'roundRobin'   => $mx_records,
				);

			} elseif ( $record_type == 'txt' or $record_type == 'a' ) {

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

// Processes ajax events (new install, remove install, setup configs)
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
		echo "Permission defined";
		wp_die();
		return;
	}

	$cmd   = $_POST['command'];
	$value = $_POST['value'];
	$site  = get_field( 'site', $post_id );

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

		echo json_encode($response);

	}

	if ( $cmd == 'usage-breakdown' ) {

		$customer = get_field( "customer", $post_id );
		$customer_id = $customer[0];
		$hosting_plan    = get_field( 'hosting_plan', $customer_id );
		$addons          = get_field( 'addons', $customer_id );
		$storage         = get_field( 'storage', $customer_id );
		$views           = get_field( 'views', $customer_id );

		if ( $hosting_plan == 'basic' ) {
			$views_plan_limit = '100000';
		}
		if ( $hosting_plan == 'standard' ) {
			$views_plan_limit = '500000';
		}
		if ( $hosting_plan == 'professional' ) {
			$views_plan_limit = '1000000';
		}
		if ( $hosting_plan == 'business' ) {
			$views_plan_limit = '2000000';
		}
		if ( isset( $views ) ) {
			$views_percent = round( $views / $views_plan_limit * 100, 0 );
		}

		$storage_gbs = round( $storage / 1024 / 1024 / 1024, 1 );
		$storage_cap = '10';
		if ( $addons ) {
			foreach ( $addons as $item ) {
				// Evaluate if contains word storage
				if ( stripos( $item['name'], 'storage' ) !== false ) {
					// Found storage addon, now extract number and add to cap.
					$extracted_gbs = filter_var( $item['name'], FILTER_SANITIZE_NUMBER_INT );
					$storage_cap   = $storage_cap + $extracted_gbs;
				}
			}
		}

		$storage_percent = round( $storage_gbs / $storage_cap * 100, 0 );

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
						'value'   => 'active', // matches exaclty "123", not just 123. This prevents a match for "1234"
						'compare' => '=',
					),
					array(
						'key'     => 'customer', // name of custom field
						'value'   => '"' . $customer_id . '"', // matches exaclty "123", not just 123. This prevents a match for "1234"
						'compare' => 'LIKE',
					),
					array(
						'key'     => 'address',
						'compare' => 'EXISTS',
					),
					array(
						'key'     => 'address',
						'value'   => '',
						'compare' => '!=',
					),
				),
			)
		);
		if ( $websites_for_customer ) :
			foreach ( $websites_for_customer as $website_for_customer ) :
				$website_for_customer_storage = get_field( 'storage', $website_for_customer->ID );
				$website_for_customer_views   = get_field( 'views', $website_for_customer->ID );
				$sites[] = array(
					'name' => get_the_title( $website_for_customer->ID ),
					'storage' => round( $website_for_customer_storage / 1024 / 1024 / 1024, 1 ),
					'views' => $website_for_customer_views
				);
			endforeach;
			$total = array(
				$storage_percent . "% storage<br /><strong>" . $storage_gbs ."GB/". $storage_cap ."GB</strong>",
				$views_percent . "% traffic<br /><strong>" . number_format( $views ) . "</strong> <small>Yearly Estimate</small>"
			);

		endif;

		$usage_breakdown = array ( 'sites' => $sites, 'total' => $total );

		$mock_usage_breakdown = array(
			'sites' => array(
					array(
						'name' => 'anchor.host',
						'storage' => '.4',
						'views' => '22164'
					),
					array(
						'name' => 'anchorhost1.wpengine.com',
						'storage' => '2.5',
						'views' => '10352'
					)
			),
			'total' =>  array(
				'25% storage<br />24.9GB/100GB',
				'86% traffic<br />86,112 Yearly Estimate'
			),
		);

		echo json_encode( $usage_breakdown ) ;
	}

	if ( $cmd == 'updateSettings' ) {

		// Saves update settings for a site
		$exclude_plugins = implode(",", $value["exclude_plugins"]);
		$exclude_themes = implode(",", $value["exclude_themes"]);

		update_field( 'field_5b231770b9732', $exclude_plugins, $post_id );
		update_field( 'field_5b231746b9731', $exclude_themes, $post_id );
		update_field( 'field_5b2a902585a78', $value["updates_enabled"], $post_id );

		// Remote Sync
		$run_in_background = true;
		$remote_command = true;
		$command = "captaincore site update" . captaincore_site_fetch_details( $post_id );

	}

	if ( $cmd == 'newSite' ) {

		// Create new site
		$new_site = (new CaptainCore\Site)->create( $value );
		echo json_encode($new_site);

	}

	if ( $cmd == 'fetch-site' ) {

		// Create new site
		$site = (new CaptainCore\Site)->get( $post_id );
		echo json_encode($site);

	}

	if ( $cmd == 'fetch-users' ) {

		# Fetch from custom table
		echo get_field( "users", $post_id );

	}

	if ( $cmd == 'fetch-update-logs' ) {

		# Fetch from custom table
		$db_update_logs = new CaptainCore\update_logs;

		echo json_encode($db_update_logs->fetch_logs( $post_id ));

	}

	if ( $run_in_background ) {

		// Generate unique $job_id for tracking
		$job_id = round(microtime(true) * 1000);

		// Tie in the site_id to make sure jobs are only viewed by people with access.
		$background_id = "${post_id}_${job_id}";

		// Tack on CaptaionCore global arguments for tracking purposes
		$command = "$command --run-in-background=$background_id &";

	}

	if ( $remote_command ) {

		// Runs command on remote on production
		require_once ABSPATH . '/vendor/autoload.php';

		$ssh = new \phpseclib\Net\SSH2( CAPTAINCORE_CLI_ADDRESS, CAPTAINCORE_CLI_PORT );
		if ( ! $ssh->login( CAPTAINCORE_CLI_USER, CAPTAINCORE_CLI_KEY ) ) {
			exit( 'Login Failed' );
		}

		// Returns command if debug enabled otherwise executes command over SSH and returns output
		if ( defined( 'CAPTAINCORE_DEBUG' ) ) {
			$response = $command;
		} else {
			$response = $ssh->exec( $command );
			// Background jobs need job_id returned in order to begin repeating AJAX checks
			if ( $run_in_background ) {
				$response = $job_id;
			}
		}

		// Return response
		echo $response;

	}

	wp_die(); // this is required to terminate immediately and return a proper response
}

// Processes install events (new install, remove install, setup configs)
add_action( 'wp_ajax_captaincore_install', 'captaincore_install_action_callback' );

function captaincore_install_action_callback() {
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

	// Checks permissions
	if ( ! captaincore_verify_permissions( $post_id ) ) {
		echo 'Permission denied';
		wp_die(); // this is required to terminate immediately and return a proper response
		return;
	}

	$cmd        = $_POST['command'];
	$value      = $_POST['value'];
	$commit     = $_POST['commit'];
	$arguments  = $_POST['arguments'];
	$addon_type = $_POST['addon_type'];
	$date       = $_POST['date'];
	$name       = $_POST['name'];
	$link       = $_POST['link'];
	$background = $_POST['background'];
	$job_id     = $_POST['job_id'];

	$site             = get_field( 'site', $post_id );
	$provider         = get_field( 'provider', $post_id );
	$domain           = get_the_title( $post_id );
	$address          = get_field( 'address', $post_id );
	$username         = get_field( 'username', $post_id );
	$password         = get_field( 'password', $post_id );
	$protocol         = get_field( 'protocol', $post_id );
	$port             = get_field( 'port', $post_id );
	$homedir          = get_field( 'homedir', $post_id );
	$staging_site     = get_field( 'site_staging', $post_id );
	$staging_address  = get_field( 'address_staging', $post_id );
	$staging_username = get_field( 'username_staging', $post_id );
	$staging_password = get_field( 'password_staging', $post_id );
	$staging_protocol = get_field( 'protocol_staging', $post_id );
	$staging_port     = get_field( 'port_staging', $post_id );
	$staging_homedir  = get_field( 'homedir_staging', $post_id );
	$updates_enabled  = get_field( 'updates_enabled', $post_id );
	$exclude_themes   = get_field( 'exclude_themes', $post_id );
	$exclude_plugins  = get_field( 'exclude_plugins', $post_id );
	$s3accesskey      = get_field( 's3_access_key', $post_id );
	$s3secretkey      = get_field( 's3_secret_key', $post_id );
	$s3bucket         = get_field( 's3_bucket', $post_id );
	$s3path           = get_field( 's3_path', $post_id );

	$partners = get_field( 'partner', $post_id );
	if ( $partners ) {
		$preloadusers = implode( ',', $partners );
	}

	// Assume this is a subsite and reconfigure as such
	if ( $site == '' ) {
		$site    = $domain;
		$domain  = '';
		$subsite = 'true';
	}

	// Append provider if exists
	if ( $provider != '' ) {
		$site = $site . '@' . $provider;
	}

	// Disable SSL verification due to self signed cert on other end
	$arrContextOptions = array(
		'ssl' => array(
			'verify_peer'      => false,
			'verify_peer_name' => false,
		),
	);

	if ($background) {
		$run_in_background = true;
	}

	if ( $cmd == 'new' ) {
		$command = 'captaincore site add' . captaincore_site_fetch_details( $post_id );

		// Run in background without confirmation. Will display first 5 secs of output
		date_default_timezone_set( 'America/New_York' );
		$timestamp = date( 'Y-m-d-hms', time() );
		$file = "~/Tmp/command-site_update_${site}_${timestamp}.txt";
		$command = "$command > $file 2>&1 & sleep 5; head $file";
	}
	if ( $cmd == 'update' ) {
		$command = 'captaincore site update' . captaincore_site_fetch_details( $post_id );

		// Run in background without confirmation. Will display first 5 secs of output
		date_default_timezone_set( 'America/New_York' );
		$timestamp = date( 'Y-m-d-hms', time() );
		$file = "~/Tmp/command-site_update_${site}_${timestamp}.txt";
		$command = "$command > $file 2>&1 & sleep 5; head $file";
	}
	if ( $cmd == 'update-wp' ) {
		$command = "captaincore update $site";
		$run_in_background = true;
	}
	if ( $cmd == 'update-fetch' ) {
		$command = "captaincore update-fetch $site";

		if ( defined( 'CAPTAINCORE_DEBUG' ) ) {
			// return mock data
			$command = CAPTAINCORE_DEBUG_MOCK_UPDATES;
		}
	}
	if ( $cmd == 'users-fetch' ) {
		$command = "captaincore ssh $site --command='wp user list --format=json'";

		$run_in_background = true;

		if ( defined( 'CAPTAINCORE_DEBUG' ) ) {
			// return mock data
			$command = CAPTAINCORE_DEBUG_MOCK_USERS;
		}
	}
	if ( $cmd == 'login' ) {
		$command = "captaincore login $site $value";

		$run_in_background = true;

		if ( defined( 'CAPTAINCORE_DEBUG' ) ) {
			// return mock data
			$command = CAPTAINCORE_DEBUG_MOCK_LOGIN;
		}

	}
	if ( $cmd == 'job-fetch' ) {
		$command = "captaincore job-fetch ${post_id}_${job_id}";

		if ( defined( 'CAPTAINCORE_DEBUG' ) ) {
			// return mock data
			$command = CAPTAINCORE_DEBUG_MOCK_JOB_FETCH;
		}
	}
	if ( $cmd == 'copy' ) {
		// Find destination site and verify we have permission to it
		$site_destination    = get_posts(
			array(
				'post_type'  => 'captcore_website',
				'meta_key'   => 'site',
				'meta_value' => $value,
			)
		);
		$site_destination_id = $site_destination[0]->ID;
		if ( captaincore_verify_permissions( $site_destination_id ) ) {
			$current_user = wp_get_current_user();
			$email        = $current_user->user_email;
			date_default_timezone_set( 'America/New_York' );
			$timestamp = date( 'Y-m-d-hms', time() );
			$command   = "nohup captaincore copy $site $value --email=$email --mark-when-completed > ~/Tmp/job-copy-$timestamp.txt 2>&1 &";
		}
	}
	if ( $cmd == 'mailgun' ) {
		date_default_timezone_set( 'America/New_York' );
		$timestamp = date( 'Y-m-d-hms', time() );
		mailgun_setup( $domain );
		$command = "captaincore ssh $site --script=deploy-mailgun --key=\"" . MAILGUN_API_KEY . '" --domain=' . $domain . " > ~/Tmp/$timestamp-deploy_mailgun_$site.txt 2>&1 &";
	}
	if ( $cmd == 'apply-https' ) {
		$command = "captaincore ssh $site --script=apply-https &";
	}
	if ( $cmd == 'apply-https-with-www' ) {
		$command = "captaincore ssh $site --script=apply-https-with-www &";
	}
	if ( $cmd == 'production-to-staging' ) {
		date_default_timezone_set( 'America/New_York' );
		$timestamp = date( 'Y-m-d-hms', time() );
		if ( $value ) {
			$command = "captaincore copy-production-to-staging $site --email=$value > ~/Tmp/$timestamp-deploy_production_to_staging_$site.txt 2>&1 & sleep 5; head ~/Tmp/$timestamp-deploy_production_to_staging_$site.txt";
		} else {
			$command = "captaincore copy-production-to-staging $site > ~/Tmp/$timestamp-deploy_production_to_staging_$site.txt 2>&1 & sleep 5; head ~/Tmp/$timestamp-deploy_production_to_staging_$site.txt";
		}
	}
	if ( $cmd == 'staging-to-production' ) {
		date_default_timezone_set( 'America/New_York' );
		$timestamp = date( 'Y-m-d-hms', time() );
		if ( $value ) {
			$command = "captaincore copy-staging-to-production $site --email=$value > ~/Tmp/$timestamp-deploy_staging_to_production_$site.txt 2>&1 & sleep 5; head ~/Tmp/$timestamp-deploy_staging_to_production_$site.txt";
		} else {
			$command = "captaincore copy-staging-to-production $site > ~/Tmp/$timestamp-deploy_staging_to_production_$site.txt 2>&1 & sleep 5; head ~/Tmp/$timestamp-deploy_staging_to_production_$site.txt";
		}
	}
	if ( $cmd == 'remove' ) {
		$command = "captaincore site delete $site";
	}
	if ( $cmd == 'quick_backup' ) {
		date_default_timezone_set( 'America/New_York' );
		$timestamp = date( 'Y-m-d-hms', time() );
		$command   = "captaincore quicksave $site > ~/Tmp/$timestamp-quicksave_$site.txt 2>&1";
	}
	if ( $cmd == 'backup' ) {
		date_default_timezone_set( 'America/New_York' );
		$timestamp = date( 'Y-m-d-hms', time() );
		$command   = "captaincore backup $site > ~/Tmp/$timestamp-backup_$site.txt 2>&1 & sleep 5; head ~/Tmp/$timestamp-backup_$site.txt";
	}
	if ( $cmd == 'snapshot' ) {
		date_default_timezone_set( 'America/New_York' );
		$timestamp = date( 'Y-m-d-hms', time() );
		if ( $date && $value ) {
			$command = "captaincore snapshot $site --email=$value --rollback='$date' > ~/Tmp/$timestamp-snapshot_$site.txt 2>&1 & sleep 2; head ~/Tmp/$timestamp-snapshot_$site.txt";
		} elseif ( $value ) {
			$command = "captaincore snapshot $site --email=$value > ~/Tmp/$timestamp-snapshot_$site.txt 2>&1 & sleep 2; head ~/Tmp/$timestamp-snapshot_$site.txt";
		} else {
			$command = "captaincore snapshot $site > ~/Tmp/$timestamp-snapshot_$site.txt 2>&1 & sleep 2; head ~/Tmp/$timestamp-snapshot_$site.txt";
		}
	}
	if ( $cmd == 'deactivate' ) {
		$command = "nohup captaincore deactivate $site --name=\"$name\" --link=\"$link\" &";
	}
	if ( $cmd == 'activate' ) {
		$command = "nohup captaincore activate $site &";
	}

	if ( $cmd == 'view_quicksave_changes' ) {
		$command = "captaincore quicksave-view-changes $site --hash=$value";
		if ( defined( 'CAPTAINCORE_DEBUG' ) ) {
			// return mock data
			$command = CAPTAINCORE_DEBUG_MOCK_QUICKSAVE_VIEW_CHANGES;
		}
	}

	if ( $cmd == 'manage' ) {

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
						date_default_timezone_set( 'America/New_York' );
						$timestamp  = date( 'Y-m-d-hms', time() );
						$command         .= "captaincore $bulk_command " . implode( ' ', $sites ) . " --" . $argument['value'] . "=\"" . $argument['input'] . "\" > ~/Tmp/$timestamp-bulk.txt 2>&1 & sleep 1; head ~/Tmp/$timestamp-bulk.txt;";
					}
				}
			}
		}

		if ( is_int($post_id) ) {
			$command = "captaincore $value $site --" . $arguments['value'] . '="' . $arguments['input'] . '"';
		}

	}

	if ( $cmd == 'quicksave_file_diff' ) {
		$command    = "captaincore quicksave-file-diff $site --hash=$commit --file=\"$value\"";
	}

	if ( $cmd == 'rollback' ) {
		date_default_timezone_set( 'America/New_York' );
		$timestamp  = date( 'Y-m-d-hms', time() );
		$git_commit = get_field( 'git_commit', $post_id );
		$website_id = get_field( 'website', $post_id );
		$site       = get_field( 'site', $website_id[0] );
		$command    = "captaincore rollback $site $git_commit --$addon_type=$value > ~/Tmp/$timestamp-rollback_$site.txt 2>&1 & sleep 1; head ~/Tmp/$timestamp-rollback_$site.txt";
		$post_id    = $website_id[0];
	}

	if ( $cmd == 'quicksave_rollback' ) {
		date_default_timezone_set( 'America/New_York' );
		$timestamp  = date( 'Y-m-d-hms', time() );
		$git_commit = get_field( 'git_commit', $post_id );
		$website_id = get_field( 'website', $post_id );
		$site       = get_field( 'site', $website_id[0] );
		$command    = "captaincore rollback $site $git_commit --all > ~/Tmp/$timestamp-rollback_$site.txt 2>&1 & sleep 1; head ~/Tmp/$timestamp-rollback_$site.txt";
		$post_id    = $website_id[0];
	}

	if ( $cmd == 'quicksave_file_restore' ) {
		$git_commit = get_field( 'git_commit', $post_id );
		$website_id = get_field( 'website', $post_id );
		$site       = get_field( 'site', $website_id[0] );
		$command    = "nohup captaincore rollback $site $git_commit --file=\"$value\" &";
		$post_id    = $website_id[0];
	}

	// Runs command on remote on production
	require_once ABSPATH . '/vendor/autoload.php';

	$ssh = new \phpseclib\Net\SSH2( CAPTAINCORE_CLI_ADDRESS, CAPTAINCORE_CLI_PORT );

	if ( ! $ssh->login( CAPTAINCORE_CLI_USER, CAPTAINCORE_CLI_KEY ) ) {
		exit( 'Login Failed' );
	}

	if ( $run_in_background ) {

		// Generate unique $job_id for tracking
		$job_id = round(microtime(true) * 1000);

		// Tie in the site_id to make sure jobs are only viewed by people with access.
		$background_id = "${post_id}_${job_id}";

		// Tack on CaptaionCore global arguments for tracking purposes
		$command = "$command --run-in-background=$background_id &";

	}

	// Returns command if debug enabled otherwise executes command over SSH and returns output
	if ( defined( 'CAPTAINCORE_DEBUG' ) ) {
		$response = $command;
	} else {
		$response = $ssh->exec( $command );

		// Background jobs need job_id returned in order to begin repeating AJAX checks
		if ( $run_in_background ) {
			$response = $job_id;
		}

	}

	// Return response
	echo $response;

	wp_die(); // this is required to terminate immediately and return a proper response
}

// Logs a process completion
add_action( 'wp_ajax_log_process', 'process_log_action_callback' );

function captaincore_site_fetch_details( $post_id ) {

	$site             = get_field( 'site', $post_id );
	$provider         = get_field( 'provider', $post_id );
	$domain           = get_the_title( $post_id );
	$address          = get_field( 'address', $post_id );
	$username         = get_field( 'username', $post_id );
	$password         = get_field( 'password', $post_id );
	$protocol         = get_field( 'protocol', $post_id );
	$port             = get_field( 'port', $post_id );
	$homedir          = get_field( 'homedir', $post_id );
	$staging_site     = get_field( 'site_staging', $post_id );
	$staging_address  = get_field( 'address_staging', $post_id );
	$staging_username = get_field( 'username_staging', $post_id );
	$staging_password = get_field( 'password_staging', $post_id );
	$staging_protocol = get_field( 'protocol_staging', $post_id );
	$staging_port     = get_field( 'port_staging', $post_id );
	$staging_homedir  = get_field( 'homedir_staging', $post_id );
	$updates_enabled  = get_field( 'updates_enabled', $post_id );
	$exclude_themes   = get_field( 'exclude_themes', $post_id );
	$exclude_plugins  = get_field( 'exclude_plugins', $post_id );
	$s3accesskey      = get_field( 's3_access_key', $post_id );
	$s3secretkey      = get_field( 's3_secret_key', $post_id );
	$s3bucket         = get_field( 's3_bucket', $post_id );
	$s3path           = get_field( 's3_path', $post_id );
	$partners         = get_field( 'partner', $post_id );

	if ( $partners ) {
		$preloadusers = implode( ',', $partners );
	}

	// Append provider if exists
	if ( $provider != '' ) {
		$site = $site . '@' . $provider;
	}

	$command = '' .
	( $site ? " $site" : '' ) .
	( $post_id ? " --id=$post_id" : '' ) .
	( $domain ? " --domain=$domain" : '' ) .
	( $username ? " --username=$username" : '' ) .
	( $password ? ' --password=' . rawurlencode( base64_encode( $password ) ) : '' ) .
	( $address ? " --address=$address" : '' ) .
	( $protocol ? " --protocol=$protocol" : '' ) .
	( $port ? " --port=$port" : '' ) .
	( $staging_username ? " --staging_username=$staging_username" : '' ) .
	( $staging_password ? ' --staging_password=' . rawurlencode( base64_encode( $staging_password ) ) : '' ) .
	( $staging_address ? " --staging_address=$staging_address" : '' ) .
	( $staging_protocol ? " --staging_protocol=$staging_protocol" : '' ) .
	( $staging_port ? " --staging_port=$staging_port" : '' ) .
	( $preloadusers ? " --preloadusers=$preloadusers" : '' ) .
	( $homedir ? " --homedir=$homedir" : '' ) .
	( $subsite ? " --subsite=$subsite" : '' ) .
	( $updates_enabled ? " --updates_enabled=$updates_enabled" : ' --updates_enabled=0' ) .
	( $exclude_themes ? " --exclude_themes=$exclude_themes" : '' ) .
	( $exclude_plugins ? " --exclude_plugins=$exclude_plugins" : '' ) .
	( $s3accesskey ? " --s3accesskey=$s3accesskey" : '' ) .
	( $s3secretkey ? " --s3secretkey=$s3secretkey" : '' ) .
	( $s3bucket ? " --s3bucket=$s3bucket" : '' ) .
	( $s3path ? " --s3path=$s3path" : '' );

	return $command;

}

function captaincore_create_tables() {
    global $wpdb;

		$version = (int) get_site_option('captcorecore_db_version');
    $charset_collate = $wpdb->get_charset_collate();

		if ( $version < 1 ) {
			$sql = "CREATE TABLE `{$wpdb->base_prefix}captaincore_update_logs` (
				log_id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
				site_id bigint(20) UNSIGNED NOT NULL,
				created_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
				update_type varchar(255),
				update_log longtext,
			PRIMARY KEY  (log_id)
			) $charset_collate;";

			require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
			dbDelta($sql);
			$success = empty($wpdb->last_error);

			update_site_option('captcorecore_db_version', 1);
		}

		if ( $version < 2 && $sql == "" ) {
			$sql = "CREATE TABLE `{$wpdb->base_prefix}captaincore_quicksaves` (
				quicksave_id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
				site_id bigint(20) UNSIGNED NOT NULL,
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

			update_site_option('captcorecore_db_version', 2);
		}

		return $success;

}

function captaincore_website_acf_actions( $field ) {

	if ( $field and $field['label'] == 'Download Snapshot' ) {

		$id = get_the_ID();

		?>
		<script>
		jQuery(document).ready(function(){
		  jQuery("#download").click(function(e){
			e.preventDefault();
			var data = {
				'action': 'snapshot_email',
				'snapshot_id': <?php echo $id; ?>,
				'email': 'austin@anchor.host'
			};

				// since 2.8 ajaxurl is always defined in the admin header and points to admin-ajax.php
				jQuery.post(ajaxurl, data, function(response) {
					jQuery('.download-result').html(response);
				});

		  });
		});
		</script>
		<input type="button" value="Download Snapshot" id="download" class="button">
		<div class="download-result"></div>
		<?php

	}

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
						'value'   => '"' . $id . '"', // matches exaclty "123", not just 123. This prevents a match for "1234"
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
						'value'   => '"' . $id . '"', // matches exaclty "123", not just 123. This prevents a match for "1234"
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

// Fetch Backblaze link for snapshot and sends email
add_action( 'wp_ajax_snapshot_email', 'snapshot_email_action_callback' );

function snapshot_email_action_callback() {
	global $wpdb; // this is how you get access to the database

	// Variables from JS request
	$snapshot_id = intval( $_POST['snapshot_id'] );
	captaincore_download_snapshot_email( $snapshot_id );

	wp_die(); // this is required to terminate immediately and return a proper response
}

function captaincore_download_snapshot_email( $snapshot_id ) {
	$email      = get_field( 'email', $snapshot_id );
	$name       = get_field( 'name', $snapshot_id );
	$website    = get_field( 'website', $snapshot_id );
	$website_id = $website[0];
	$domain     = get_the_title( $website_id );
	$site       = get_field( 'site', $website_id );

	// Get new auth from B2
	$account_id      = CAPTAINCORE_B2_ACCOUNT_ID; // Obtained from your B2 account page
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
	$url           = 'https://f001.backblazeb2.com/file/' . CAPTAINCORE_B2_SNAPSHOTS . "/${site}_${website_id}/$name?Authorization=" . $auth;

	echo $url;

	$to      = $email;
	$subject = "Anchor Hosting - Snapshot #$snapshot_id";
	$body    = 'Snapshot #' . $snapshot_id . ' for ' . $domain . '. Expires after 1 week.<br /><br /><a href="' . $url . '">Download Snapshot</a>';
	$headers = array( 'Content-Type: text/html; charset=UTF-8' );

	wp_mail( $to, $subject, $body, $headers );
}

// Add reports to customers
add_action( 'admin_menu', 'captaincore_custom_pages' );

function captaincore_custom_pages() {
	add_submenu_page( 'captaincore', 'Customers Report', 'Reports', 'manage_options', 'captaincore_report', 'captaincore_customer_report_callback' );
	add_submenu_page( null, 'Partners Report', 'Partners', 'manage_options', 'captaincore_partner', 'captaincore_partner_report_callback' );
	add_submenu_page( null, 'Installs', 'Installs', 'manage_options', 'captaincore_installs', 'captaincore_installs_report_callback' );
	add_submenu_page( null, 'Customers Timeline', 'Timeline', 'manage_options', 'captaincore_timeline', 'captaincore_timeline_report_callback' );
	add_submenu_page( null, 'KPI', 'KPI', 'manage_options', 'captaincore_kpi', 'captaincore_kpi_report_callback' );
	add_submenu_page( null, 'Quicksaves', 'Quicksaves', 'manage_options', 'captaincore_quicksaves', 'captaincore_quicksaves_report_callback' );
}

function captaincore_customer_report_callback() {
	// Loads the Customer Report template
	require_once dirname( __FILE__ ) . '/inc/admin-customer-report.php';
}

function captaincore_partner_report_callback() {
	// Loads the Customer Report template
	require_once dirname( __FILE__ ) . '/inc/admin-partner-report.php';
}

function captaincore_installs_report_callback() {
	// Loads the Customer Report template
	require_once dirname( __FILE__ ) . '/inc/admin-installs-report.php';
}

function captaincore_timeline_report_callback() {
	// Loads the Customer Report template
	require_once dirname( __FILE__ ) . '/inc/admin-timeline-report.php';
}

function captaincore_kpi_report_callback() {
	// Loads the Customer Report template
	require_once dirname( __FILE__ ) . '/inc/admin-kpi-report.php';
}

function captaincore_quicksaves_report_callback() {
	// Loads the Quicksaves Report template
	require_once dirname( __FILE__ ) . '/inc/admin-report-quicksaves.php';
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

// Insert text below the Featured Products title
function add_toggle_to_woocommerce_after_account_navigation() {
	// Echo out content
	echo '<div class="toggle_woocommerce_my_account open"><span class="open"><a href="#"><i class="fas fa-long-arrow-alt-left"></i></a></span><span class="close"><a href="#"><i class="fas fa-long-arrow-alt-right"></i></a></span></div>';
}
add_action( 'woocommerce_before_account_navigation' , 'add_toggle_to_woocommerce_after_account_navigation' );

function my_acf_input_admin_footer() {

?>
<script type="text/javascript">
	acf.add_action('ready', function( $el ){

	// $el will be equivalent to $('body')

	// find a specific field
	staging_address = jQuery('#acf-field_57b7a2532cc5f');

	if(staging_address) {

		function sync_button() {
			// Copy production install name to staging field
			jQuery('#acf-field_57b7a2532cc5f').val(jQuery('#acf-field_561fa4ab910ff').val());

			// Copy production address to staging field
			jQuery('#acf-field_57b7a25d2cc60').val(jQuery('#acf-field_5619c94518f1c').val());

			// Copy production username to staging field
			if (jQuery('#acf-field_5619c94518f1c').val().includes(".kinsta.com") ) {
				jQuery('#acf-field_57b7a2642cc61').val(jQuery('#acf-field_5619c97c18f1d').val() );
			} else {
				jQuery('#acf-field_57b7a2642cc61').val(jQuery('#acf-field_5619c97c18f1d').val() + "-staging");
			}

			// Copy production password to staging field (If Kinsta address)
			if (jQuery('#acf-field_5619c94518f1c').val().includes(".kinsta.com") ) {
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

		jQuery('.acf-field.acf-field-text.acf-field-57b7a2532cc5f').before('<div class="sync-button acf-field acf-field-text"><a href="#">Preload from Production</a></div>');
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

// Handle redirections of /my-account/manage/ and /my-account/handbook/ endpoints
function captaincore_template_redirect() {
	global $wp_query;

	if ( isset( $wp_query->query['handbook'] ) ) {
			wp_redirect( home_url( '/company-handbook/' ) );
			die;
	}

}
add_action( 'template_redirect', 'captaincore_template_redirect' );

// Adds ACF Option page
if( function_exists('acf_add_options_page') ) {
	acf_add_options_page();
}
