<?php

/**
 * The plugin bootstrap file
 *
 * This file is read by WordPress to generate the plugin information in the plugin
 * admin area. This file also includes all of the dependencies used by the plugin,
 * registers the activation and deactivation functions, and defines a function
 * that starts the plugin.
 *
 * @link              https://anchor.host
 * @since             0.1.0
 * @package           Captaincore
 *
 * @wordpress-plugin
 * Plugin Name:       CaptainCore Server
 * Plugin URI:        https://captaincore.io
 * Description:       Toolkit for running your own WordPress hosting business
 * Version:           0.1.5
 * Author:            Anchor Hosting
 * Author URI:        https://anchor.host
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

include "inc/woocommerce-my-account-endpoints.php";
include "inc/constellix-api/constellix-api.php";
include "inc/woocommerce-custom-password-fields.php";
include "inc/mailgun-api.php";
include "inc/process-functions.php";
require "inc/bulk-actions.php"; // Custom bulk actions.

function captaincore_rewrite() {
	add_rewrite_rule('^captaincore-api/([^/]*)/?','index.php?pagename=captaincore-api&callback=$matches[1]','top');
	add_rewrite_rule('^checkout-express/([^/]*)/?','index.php?pagename=checkout-express&callback=$matches[1]','top');
	add_rewrite_tag('%site%','([^&]+)');
	add_rewrite_tag('%sitetoken%','([^&]+)');
	add_rewrite_tag('%callback%','([^&]+)');

	register_taxonomy("process_role", array("captcore_process"), array("hierarchical" => true, "label" => "Roles", "singular_label" => "Role", "rewrite" => true));
}

add_action( 'init', 'captaincore_rewrite' );

function anchor_disable_gutenberg( $can_edit, $post_type ) {
	$disabled_post_types = array("captcore_website", "captcore_domain", "captcore_customer", "captcore_changelog");
  if ( in_array($post_type, $disabled_post_types ) ) {
		return false;
	}
	return $can_edit;
}
add_filter( 'gutenberg_can_edit_post_type','anchor_disable_gutenberg', 10, 2 );

// Modify WooCommerce Menu: wc_get_account_menu_items() ;
function anchor_my_account_order( $current_menu ) {


	unset($current_menu["websites"]);
	unset($current_menu["edit-account"]);
	$current_menu["edit-account"] = "Account";
	unset($current_menu["subscriptions"]);
	$current_menu["subscriptions"] = "Billing";
	unset($current_menu["customer-logout"]);
	$current_menu['payment-methods'] = "Payment methods"; // Payment Methods
	$current_menu["customer-logout"] = "Logout";

	$user = wp_get_current_user();

	$role_check_admin = in_array( 'administrator', $user->roles );
	$role_check_partner = in_array( 'partner', $user->roles ) + in_array( 'administrator', $user->roles );

	if (!$role_check_admin) {
		unset($current_menu["handbook"]);
		unset($current_menu["licenses"]);
	}
	if (!$role_check_partner) {
		unset($current_menu["configs"]);
		unset($current_menu["dns"]);
		unset($current_menu["logs"]);
	}
	return $current_menu;
}
// Need to run later to allow time for new items to be added to WooCommerce Menu
add_filter( 'woocommerce_account_menu_items', 'anchor_my_account_order', 50 );

// Register Custom Post Type
function contact_post_type() {

	$labels = array(
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
	  'edit_post'             => 'website_edit_post',
	  'read_post'             => 'website_read_post',
	  'delete_post'           => 'website_delete_post',
	  'edit_posts'            => 'website_edit_posts',
	  'edit_others_posts'     => 'website_edit_others_posts',
	  'publish_posts'         => 'website_publish_posts',
	  'read_private_posts'    => 'website_read_private_posts',
	);
	$args = array(
		'label'                 => __( 'Contact', 'captaincore' ),
		'description'           => __( 'Contact Description', 'captaincore' ),
		'labels'                => $labels,
		'supports'              => array( 'author', 'revisions', ),
		'hierarchical'          => false,
		'public'                => true,
		'show_ui'               => true,
		'show_in_menu'          => false,
		'menu_position'         => 5,
		'menu_icon'             => 'dashicons-admin-users',
		'show_in_admin_bar'     => true,
		'show_in_nav_menus'     => true,
		'can_export'            => true,
		'has_archive'           => false,
		'exclude_from_search'   => false,
		'publicly_queryable'    => true,
		'capability_type'       => 'page',
		'capabilities'        => $capabilities,
		'map_meta_cap' 		  => true
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

	$labels = array(
		'name'                => _x( 'Customers', 'Post Type General Name', 'captaincore' ),
		'singular_name'       => _x( 'Customer', 'Post Type Singular Name', 'captaincore' ),
		'menu_name'           => __( 'Customers', 'captaincore' ),
		'name_admin_bar'      => __( 'Customer', 'captaincore' ),
		'parent_item_colon'   => __( 'Parent Customer:', 'captaincore' ),
		'all_items'           => __( 'Customers', 'captaincore' ),
		'add_new_item'        => __( 'Add New Customer', 'captaincore' ),
		'add_new'             => __( 'Add New', 'captaincore' ),
		'new_item'            => __( 'New Customer', 'captaincore' ),
		'edit_item'           => __( 'Edit Customer', 'captaincore' ),
		'update_item'         => __( 'Update Customer', 'captaincore' ),
		'view_item'           => __( 'View Item', 'captaincore' ),
		'search_items'        => __( 'Search Customers', 'captaincore' ),
		'not_found'           => __( 'Not found', 'captaincore' ),
		'not_found_in_trash'  => __( 'Not found in Trash', 'captaincore' ),
	);
	$capabilities = array(
	  'edit_post'             => 'website_edit_post',
	  'read_post'             => 'website_read_post',
	  'delete_post'           => 'website_delete_post',
	  'edit_posts'            => 'website_edit_posts',
	  'edit_others_posts'     => 'website_edit_others_posts',
	  'publish_posts'         => 'website_publish_posts',
	  'read_private_posts'    => 'website_read_private_posts',
	);
	$args = array(
		'label'               => __( 'Customer', 'captaincore' ),
		'description'         => __( 'Customer Description', 'captaincore' ),
		'labels'              => $labels,
		'supports'            => array( ),
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
		'show_in_rest'		  => true,
		'capabilities'        => $capabilities,
		'map_meta_cap' 		  => true
	);
	register_post_type( 'captcore_customer', $args );

}
add_action( 'init', 'customer_post_type', 0 );

// Register Custom Post Type
function website_post_type() {

	$labels = array(
		'name'                => _x( 'Websites', 'Post Type General Name', 'captaincore' ),
		'singular_name'       => _x( 'Website', 'Post Type Singular Name', 'captaincore' ),
		'menu_name'           => __( 'Websites', 'captaincore' ),
		'parent_item_colon'   => __( 'Parent Website:', 'captaincore' ),
		'all_items'           => __( 'Websites', 'captaincore' ),
		'view_item'           => __( 'View Website', 'captaincore' ),
		'add_new_item'        => __( 'Add New Websites', 'captaincore' ),
		'add_new'             => __( 'New Websites', 'captaincore' ),
		'edit_item'           => __( 'Edit Website', 'captaincore' ),
		'update_item'         => __( 'Update Website', 'captaincore' ),
		'search_items'        => __( 'Search websites', 'captaincore' ),
		'not_found'           => __( 'No websites found', 'captaincore' ),
		'not_found_in_trash'  => __( 'No websites found in Trash', 'captaincore' ),
	);
	$capabilities = array(
	  'edit_post'             => 'website_edit_post',
	  'read_post'             => 'website_read_post',
	  'delete_post'           => 'website_delete_post',
	  'edit_posts'            => 'website_edit_posts',
	  'edit_others_posts'     => 'website_edit_others_posts',
	  'publish_posts'         => 'website_publish_posts',
	  'read_private_posts'    => 'website_read_private_posts',
	);
	$args = array(
		'label'               => __( 'website', 'captaincore' ),
		'description'         => __( 'Website information pages', 'captaincore' ),
		'labels'              => $labels,
		'supports'            => array( ),
		'hierarchical'        => false,
		'public'              => false,
		'show_ui'             => true,
		'show_in_menu'        => 'captaincore',
		'show_in_nav_menus'   => true,
		'show_in_admin_bar'   => true,
		'menu_position'       => 5,
		'menu_icon'						=> 'dashicons-admin-multisite',
		'can_export'          => true,
		'has_archive'         => false,
		'exclude_from_search' => true,
		'publicly_queryable'  => false,
		'capability_type'     => 'page',
		'show_in_rest'				=> true,
		'capabilities'        => $capabilities,
		'map_meta_cap'				=> true
	);
	register_post_type( 'captcore_website', $args );
}

// Hook into the 'init' action
add_action( 'init', 'website_post_type', 0 );

// Register Custom Post Type
function domain_post_type() {

	$labels = array(
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
	  'edit_post'             => 'website_edit_post',
	  'read_post'             => 'website_read_post',
	  'delete_post'           => 'website_delete_post',
	  'edit_posts'            => 'website_edit_posts',
	  'edit_others_posts'     => 'website_edit_others_posts',
	  'publish_posts'         => 'website_publish_posts',
	  'read_private_posts'    => 'website_read_private_posts',
	);
	$args = array(
		'label'                 => __( 'Domain', 'captaincore' ),
		'description'           => __( 'Domain Description', 'captaincore' ),
		'labels'                => $labels,
		'supports'              => array( 'title', 'editor' ),
		'hierarchical'          => false,
		'public'                => true,
		'show_ui'               => true,
		'show_in_menu'          => false,
		'menu_position'         => 5,
		'menu_icon'             => 'dashicons-welcome-widgets-menus',
		'show_in_admin_bar'     => true,
		'show_in_nav_menus'     => true,
		'can_export'            => true,
		'has_archive'           => false,
		'exclude_from_search'   => true,
		'publicly_queryable'    => false,
		'capability_type'       => 'page',
		'show_in_rest'				=> true,
		'capabilities'        => $capabilities,
		'map_meta_cap'				=> true,
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
add_action( 'admin_init', 'add_theme_caps');

// Register Custom Post Type
function changelog_post_type() {

	$labels = array(
		'name'                => _x( 'Website Logs', 'Post Type General Name', 'captaincore' ),
		'singular_name'       => _x( 'Website Log', 'Post Type Singular Name', 'captaincore' ),
		'menu_name'           => __( 'Website Logs', 'captaincore' ),
		'parent_item_colon'   => __( 'Parent Changelog:', 'captaincore' ),
		'all_items'           => __( 'Changelogs', 'captaincore' ),
		'view_item'           => __( 'View Changelog', 'captaincore' ),
		'add_new_item'        => __( 'Add New Changelog', 'captaincore' ),
		'add_new'             => __( 'New Changelog', 'captaincore' ),
		'edit_item'           => __( 'Edit Changelog', 'captaincore' ),
		'update_item'         => __( 'Update Changelog', 'captaincore' ),
		'search_items'        => __( 'Search changelogs', 'captaincore' ),
		'not_found'           => __( 'No changelogs found', 'captaincore' ),
		'not_found_in_trash'  => __( 'No changelogs found in Trash', 'captaincore' ),
	);
	$capabilities = array(
	  'edit_post'             => 'website_edit_post',
	  'read_post'             => 'website_read_post',
	  'delete_post'           => 'website_delete_post',
	  'edit_posts'            => 'website_edit_posts',
	  'edit_others_posts'     => 'website_edit_others_posts',
	  'publish_posts'         => 'website_publish_posts',
	  'read_private_posts'    => 'website_read_private_posts',
	);
	$args = array(
		'label'               => __( 'changelog', 'captaincore' ),
		'description'         => __( 'Changelog information pages', 'captaincore' ),
		'labels'              => $labels,
		'supports'            => array( ),
		'hierarchical'        => false,
		'public'              => false,
		'show_ui'             => true,
		'show_in_menu'        => false,
		'show_in_nav_menus'   => true,
		'show_in_admin_bar'   => true,
		'menu_position'       => 5,
		'menu_icon'						=> 'dashicons-media-spreadsheet',
		'can_export'          => true,
		'has_archive'         => false,
		'exclude_from_search' => true,
		'publicly_queryable'  => false,
		'capability_type'     => 'page',
		'show_in_rest'				=> true,
		'capabilities'        => $capabilities,
		'map_meta_cap'				=> true
	);
	register_post_type( 'captcore_changelog', $args );

}

// Hook into the 'init' action
add_action( 'init', 'changelog_post_type', 0 );

// Register Custom Post Type
function process_post_type() {

	$labels = array(
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
	  'edit_post'             => 'website_edit_post',
	  'read_post'             => 'website_read_post',
	  'delete_post'           => 'website_delete_post',
	  'edit_posts'            => 'website_edit_posts',
	  'edit_others_posts'     => 'website_edit_others_posts',
	  'publish_posts'         => 'website_publish_posts',
	  'read_private_posts'    => 'website_read_private_posts',
	);
	$args = array(
		'label'                 => __( 'Process', 'captaincore' ),
		'description'           => __( 'Process Description', 'captaincore' ),
		'labels'                => $labels,
		'supports'              => array( 'title', 'editor', 'author', 'thumbnail', 'revisions', 'wpcom-markdown' ),
		'hierarchical'          => false,
		'public'                => true,
		'show_ui'               => true,
		'show_in_menu'          => false,
		'menu_position'         => 5,
		'menu_icon'             => 'dashicons-controls-repeat',
		'show_in_admin_bar'     => true,
		'show_in_nav_menus'     => true,
		'can_export'            => true,
		'has_archive'           => false,
		'exclude_from_search'   => true,
		'publicly_queryable'    => true,
		'capability_type'       => 'page',
		'capabilities'        => $capabilities,
		'map_meta_cap' 		  => true
	);
	register_post_type( 'captcore_process', $args );

}
add_action( 'init', 'process_post_type', 0 );

// Register Custom Post Type
function process_log_post_type() {

	$labels = array(
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
	  'edit_post'             => 'website_edit_post',
	  'read_post'             => 'website_read_post',
	  'delete_post'           => 'website_delete_post',
	  'edit_posts'            => 'website_edit_posts',
	  'edit_others_posts'     => 'website_edit_others_posts',
	  'publish_posts'         => 'website_publish_posts',
	  'read_private_posts'    => 'website_read_private_posts',
	);
	$args = array(
		'label'                 => __( 'Process Log', 'captaincore' ),
		'description'           => __( 'Process Log Description', 'captaincore' ),
		'labels'                => $labels,
		'supports'              => array( 'author', 'thumbnail', 'revisions', ),
		'hierarchical'          => false,
		'public'                => false,
		'show_ui'               => true,
		'show_in_menu'          => false,
		'menu_position'         => 5,
		'menu_icon'             => 'dashicons-media-spreadsheet',
		'show_in_admin_bar'     => true,
		'show_in_nav_menus'     => true,
		'can_export'            => true,
		'has_archive'           => true,
		'exclude_from_search'   => true,
		'publicly_queryable'    => false,
		'capability_type'       => 'page',
		'show_in_rest'		  => true,
		'capabilities'        => $capabilities,
		'map_meta_cap' 		  => true
	);
	register_post_type( 'captcore_processlog', $args );

}
add_action( 'init', 'process_log_post_type', 0 );

// Register Custom Post Type
function process_item_log_post_type() {

	$labels = array(
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
	  'edit_post'             => 'website_edit_post',
	  'read_post'             => 'website_read_post',
	  'delete_post'           => 'website_delete_post',
	  'edit_posts'            => 'website_edit_posts',
	  'edit_others_posts'     => 'website_edit_others_posts',
	  'publish_posts'         => 'website_publish_posts',
	  'read_private_posts'    => 'website_read_private_posts',
	);
	$args = array(
		'label'                 => __( 'Process Item Log', 'captaincore' ),
		'description'           => __( 'Process Item Log Description', 'captaincore' ),
		'labels'                => $labels,
		'supports'              => array( 'author', 'thumbnail', 'revisions', ),
		'hierarchical'          => false,
		'public'                => false,
		'show_ui'               => true,
		'show_in_menu'          => false,
		'menu_position'         => 5,
		'menu_icon'             => 'dashicons-media-spreadsheet',
		'show_in_admin_bar'     => true,
		'show_in_nav_menus'     => true,
		'can_export'            => true,
		'has_archive'           => true,
		'exclude_from_search'   => true,
		'publicly_queryable'    => false,
		'capability_type'       => 'page',
		'show_in_rest'		  => true,
		'capabilities'        => $capabilities,
		'map_meta_cap' 		  => true
	);
	register_post_type( 'captcore_processitem', $args );

}
// add_action( 'init', 'process_item_log_post_type', 0 );

// Register Custom Post Type
function server_post_type() {

	$labels = array(
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
	  'edit_post'             => 'website_edit_post',
	  'read_post'             => 'website_read_post',
	  'delete_post'           => 'website_delete_post',
	  'edit_posts'            => 'website_edit_posts',
	  'edit_others_posts'     => 'website_edit_others_posts',
	  'publish_posts'         => 'website_publish_posts',
	  'read_private_posts'    => 'website_read_private_posts',
	);
	$args = array(
		'label'                 => __( 'Server', 'captaincore' ),
		'description'           => __( 'Server Description', 'captaincore' ),
		'labels'                => $labels,
		'supports'              => array( ),
		'hierarchical'          => false,
		'public'                => false,
		'show_ui'               => true,
		'show_in_menu'          => false,
		'menu_position'         => 10,
		'menu_icon'             => 'dashicons-building',
		'show_in_admin_bar'     => true,
		'show_in_nav_menus'     => true,
		'can_export'            => true,
		'has_archive'           => true,
		'exclude_from_search'   => true,
		'publicly_queryable'    => false,
		'capability_type'       => 'page',
		'capabilities'        => $capabilities,
		'map_meta_cap' 		  => true
	);
	register_post_type( 'captcore_server', $args );

}
add_action( 'init', 'server_post_type', 0 );

// Register Custom Post Type
function snapshot_post_type() {

	$labels = array(
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
	  'edit_post'             => 'website_edit_post',
	  'read_post'             => 'website_read_post',
	  'delete_post'           => 'website_delete_post',
	  'edit_posts'            => 'website_edit_posts',
	  'edit_others_posts'     => 'website_edit_others_posts',
	  'publish_posts'         => 'website_publish_posts',
	  'read_private_posts'    => 'website_read_private_posts',
	);
	$args = array(
		'label'                 => __( 'Snapshot', 'captaincore' ),
		'description'           => __( 'Snapshot Description', 'captaincore' ),
		'labels'                => $labels,
		'supports'              => array( ),
		'hierarchical'          => false,
		'public'                => true,
		'show_ui'               => true,
		'show_in_menu'          => false,
		'menu_position'         => 5,
		'menu_icon'             => 'dashicons-backup',
		'show_in_admin_bar'     => true,
		'show_in_nav_menus'     => true,
		'can_export'            => true,
		'has_archive'           => true,
		'exclude_from_search'   => false,
		'publicly_queryable'    => true,
		'capability_type'       => 'page',
		'capabilities'        => $capabilities,
		'map_meta_cap' 		  => true
	);
	register_post_type( 'captcore_snapshot', $args );

}
add_action( 'init', 'snapshot_post_type', 0 );

// Register Custom Post Type
function captaincore_quicksaves_post_type() {

	$labels = array(
		'name'                  => _x( 'Quicksaves', 'Post Type General Name', 'captaincore' ),
		'singular_name'         => _x( 'Quicksave', 'Post Type Singular Name', 'captaincore' ),
		'menu_name'             => __( 'Quicksave', 'captaincore' ),
		'name_admin_bar'        => __( 'Quicksave', 'captaincore' ),
		'archives'              => __( 'Quicksave Archives', 'captaincore' ),
		'attributes'            => __( 'Quicksave Attributes', 'captaincore' ),
		'parent_item_colon'     => __( 'Parent Quicksave:', 'captaincore' ),
		'all_items'             => __( 'All Quicksaves', 'captaincore' ),
		'add_new_item'          => __( 'Add New Quicksave', 'captaincore' ),
		'add_new'               => __( 'Add New', 'captaincore' ),
		'new_item'              => __( 'New Quicksave', 'captaincore' ),
		'edit_item'             => __( 'Edit Quicksave', 'captaincore' ),
		'update_item'           => __( 'Update Quicksave', 'captaincore' ),
		'view_item'             => __( 'View Quicksave', 'captaincore' ),
		'view_items'            => __( 'View Quicksaves', 'captaincore' ),
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
	  'edit_post'             => 'website_edit_post',
	  'read_post'             => 'website_read_post',
	  'delete_post'           => 'website_delete_post',
	  'edit_posts'            => 'website_edit_posts',
	  'edit_others_posts'     => 'website_edit_others_posts',
	  'publish_posts'         => 'website_publish_posts',
	  'read_private_posts'    => 'website_read_private_posts',
	);
	$args = array(
		'label'                 => __( 'Quicksave', 'captaincore' ),
		'description'           => __( 'Quicksave Description', 'captaincore' ),
		'labels'                => $labels,
		'supports'              => array( ),
		'hierarchical'          => false,
		'public'                => true,
		'show_ui'               => true,
		'show_in_menu'          => false,
		'menu_position'         => 5,
		'menu_icon'             => 'dashicons-backup',
		'show_in_admin_bar'     => true,
		'show_in_nav_menus'     => true,
		'can_export'            => true,
		'has_archive'           => true,
		'exclude_from_search'   => false,
		'publicly_queryable'    => true,
		'capability_type'       => 'page',
		'capabilities'          => $capabilities,
	);
	register_post_type( 'captcore_quicksave', $args );

}
add_action( 'init', 'captaincore_quicksaves_post_type', 0 );

function captaincore_website_tabs() {

	$screen = get_current_screen();

	// Only edit post screen:
	$pages = array('captcore_website','captcore_customer','captcore_contact','captcore_domain','captcore_changelog','captcore_process','captcore_processlog','captcore_server','captcore_snapshot','captcore_quicksave');
	if( in_array($screen->post_type, $pages) ) {
	    // Before:
	    add_action( 'all_admin_notices', function(){
					include "inc/admin-website-tabs.php";
	        echo '';
	    });

	    // After:
	    add_action( 'in_admin_footer', function(){
	        echo '';
	    });
	}
};

add_action( 'load-post-new.php', 'captaincore_website_tabs' );
add_action( 'load-edit.php', 'captaincore_website_tabs' );
add_action( 'load-post.php', 'captaincore_website_tabs' );

function my_remove_extra_product_data( $data, $post, $context ) {
    // make sure you've got the right custom post type
    if ( 'captcore_website' !== $data[ 'type' ] ) {
        return $data;
    }
    // now proceed as you saw in the other examples
    if ( $context !== 'view' || is_wp_error( $data ) ) {
        return $data;
    }
    // unset unwanted fields
    unset( $data[ 'link' ] );

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
		if ($post_id) {
			$post_title = get_the_title($post_id);
		}
    return $post_title;
}
function slug_update_post_meta_cb( $value, $object, $field_name ) {
		if ( is_object($object) ) {
			$object_id = $object->ID;
		} else {
			$object_id = $object['id'];
		}
    return update_post_meta( $object_id, $field_name, $value );
}

function slug_get_paid_by_me( $object, $field_name, $request ) {

	$paid_by_me = array();
	$post_id = $object['id'];

	$websites = get_posts(array(
		'post_type' => 'captcore_customer',
	    'posts_per_page'         => '-1',
	    'meta_query' => array(
        	'relation'		=> 'AND',
				array(
					'key' => 'paid_by', // name of custom field
					'value' => '"' . $post_id . '"', // matches exaclty "123", not just 123. This prevents a match for "1234"
					'compare' => 'LIKE'
				),
				array(
					'key'	  	=> 'status',
					'value'	  	=> 'cancelled',
					'compare' 	=> '!=',
				),
		)
	));

	if( $websites ):

		foreach( $websites as $website ):
			$domain = get_the_title( $website->ID );
	        $data = array (
	        	"id" => $website->ID,
	        	"website" => $domain,
	        	"addons" => get_field("addons", $website->ID),
	        	"price" => get_field("hosting_price", $website->ID),
	        	"views" => get_field("views", $website->ID),
	        	"storage" => get_field("storage", $website->ID),
	        	"total_price" => get_field("total_price", $website->ID),
	        	"hosting_plan" => get_field("hosting_plan", $website->ID)
	        );

	        array_push($paid_by_me, $data);

		endforeach;
	endif;

	return $paid_by_me;

}

function slug_get_process_description( $object, $field_name, $request ) {
	jetpack_require_lib( 'markdown' );

	$description = get_post_meta( $object['id'], $field_name );

	if ($description[0]) {
		$description = WPCom_Markdown::get_instance()->transform( $description[0], array('id'=>false,'unslash'=>false));
	} else {
		// ACF field should be in an array if not then return nothing via API.
		$description = "";
	}

	return $description;
}

function slug_get_process( $object, $field_name, $request ) {
	$process_id = get_post_meta( $object['id'], $field_name );
	$process_id = $process_id[0][0];

	return get_the_title($process_id);
}

function slug_get_server( $object, $field_name, $request ) {
	$server_id = get_post_meta( $object['id'], $field_name );
	$server_id = $server_id[0][0];

	$provider_field = get_field_object('field_5803a848814c7');
	$provider_value = get_field('provider', $server_id);

	if ($server_id) {

	$server = array(
		"address" => get_field('address', $server_id),
		"provider" => $provider_field['choices'][ $provider_value ],
	);

	} else {
		$server = "";
	}

	return $server;
}

function my_relationship_query( $args, $field, $post ) {
    // increase the posts per page
    $args['posts_per_page'] = 25;
    $args['meta_query'] = array(
    		array(
    			'key'     => 'partner',
    			'value'   => true,
    			'compare' => '=',
    		),
    	);

    return $args;
}

// filter for a specific field based on it's key
add_filter('acf/fields/relationship/query/key=field_56181a38cf6e3', 'my_relationship_query', 10, 3);

function anchor_website_relationship_result( $title, $post, $field, $post_id ) {

	// load a custom field from this $object and show it in the $result
	$status = get_field('status', $post->ID);

	if ($status == "closed") {

		// append to title
		$title .= ' (closed)';

	}

	// return
	return $title;

}

// filter for a specific field based on it's name
add_filter('acf/fields/relationship/result/name=website', 'anchor_website_relationship_result', 10, 4);

function anchor_subscription_relationship_result( $title, $post, $field, $post_id ) {

	// load a custom field from this $object and show it in the $result
	$subscription = wcs_get_subscription($post->ID);
	$user = $subscription->get_user();

	// append to title
	$title = 'Subscription #'.$post->ID.' - ('.$subscription->get_formatted_order_total(). ") $user->first_name $user->last_name $user->user_email";

	// return
	return $title;

}

// filter for a specific field based on it's name
add_filter('acf/fields/relationship/result/name=subscription', 'anchor_subscription_relationship_result', 10, 4);

function anchor_subscription_relationship_query( $args, $field, $post ) {

		// Current search term
		$search_term = $args['s'];
		$found_user_ids = array();

		// Search users
		$search_users = get_users( array( 'search' => "*".$search_term."*" ) );

		foreach ($search_users as $found_user) {
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
					)
			);
		} else {
			$args['posts_per_page'] = 0;
		}
		// Remove standard search
		unset($args['s']);

		//print_r( $args ); wp_die();

    return $args;
}

// filter for a specific field based on it's key
add_filter('acf/fields/relationship/query/name=subscription', 'anchor_subscription_relationship_query', 10, 3);

// Validate domain is unique
add_action('acf/validate_save_post', 'my_acf_validate_save_post', 10, 0);

function my_acf_validate_save_post() {

	// Runs only when creating a new domain post.
	if( $_POST['post_type'] == "domain" ) {

		$post_id = $_POST['post_ID'];
		$domain = $_POST['post_title'];

		// Check for duplicate domain.
		$domain_exists = get_posts( array(
			'title'						=> $domain,
			'post_type' 			=> 'captcore_domain',
			'posts_per_page'	=> '-1',
			'post_status'			=> 'publish',
			'fields'          => 'ids',
		 ));

		 // Remove current ID from results
		 if (($key = array_search($post_id, $domain_exists)) !== false) {
		    unset($domain_exists[$key]);
		 }

		 // If results still exists then give an error
		 if ( count($domain_exists) > 0 ) {
				 acf_add_validation_error( '', 'Domain has already been added.' );
		 }

	}

}


// run before ACF saves the $_POST['acf'] data
add_action('acf/save_post', 'anchor_acf_save_post_before', 1);
function anchor_acf_save_post_before( $post_id ){

	if ( get_post_type( $post_id ) == 'captcore_website' ) {

		if ( get_field('launch_date') == "" and $_POST['acf']['field_52d167f4ac39e'] == "") {
			// No date was entered for Launch Date, assign to today.
			$_POST['acf']['field_52d167f4ac39e'] = date("Ymd");
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

    	if ($product->get_type() == "variable-subscription" or $product->get_type() == "variable") {
    		$variations = $product->get_available_variations();

    		foreach ( $variations as $variation ) {
    			//print_r($variation);
    			$id = $variation["id"];
    			$name = $variation["name"];
    			$attributes =  $variation["attributes"];
    			$data = array ( "id" => $id, "name" => $name,  "attributes" => $attributes );

    			array_push($choices, $data);

    		}


    	} else {
    		$id = $product->get_id();
    		$name = $product->get_title();
    		$data = array ( "id" => $id, "name" => $name );
    		array_push($choices, $data);
    	}

    	//echo "<pre>";
    	//print_r();
    	//echo "</pre>";

    }

    // loop through array and add to field 'choices'
    if( is_array($choices) ) {

        foreach( $choices as $choice ) {

        	$attributes = $choice["attributes"];
        	$formatted_attributes = "";
        	foreach ($attributes as $attribute) {
        		$formatted_attributes .= " - " . $attribute;
        	}

            $field['choices'][ $choice["id"] ] = $choice["name"] . $formatted_attributes;

        }

    }


    // return the field
    return $field;

}

add_filter('acf/load_field/key=field_590681f3c0775', 'acf_load_color_field_choices');

// run after ACF saves
add_action('acf/save_post', 'anchor_acf_save_post_after', 20);
function anchor_acf_save_post_after( $post_id ){

	if ( get_post_type( $post_id ) == 'captcore_website' ) {
	    $custom = get_post_custom($post_id);
	    $hosting_plan = $custom["hosting_plan"][0];
	    $hosting_price = $custom["hosting_price"][0];
	    $addons = get_field('addons', $post_id);
	    $customer = get_field('customer', $post_id);
	    $views = get_field('views', $post_id);
	    $status = $custom["status"][0];
	    $total = 0;
	    $addon_total = 0;

	    if ( $customer == "" ) {
	    	// no customer found, generate and assign the customer
	    	if (get_field('billing_date', $post_id)) {
	    		$website_billing_date = date('Ymd', strtotime(get_field('billing_date', $post_id)));
	    	}
	    	$website_hosting_plan = get_field('hosting_plan', $post_id);
	    	$website_hosting_price = get_field('hosting_price', $post_id);
	    	$website_addons = get_field('addons', $post_id);
	    	$website_billing_method = get_field('billing_method', $post_id);
	    	$website_billing_email = get_field('billing_email', $post_id);

	    	// Create customer object
	    	$my_post = array(
	    	  'post_title'    => get_the_title( $post_id ),
	    	  'post_type'     => 'captcore_customer',
	    	  'post_status'   => 'publish',
	    	  'post_author'   => 1
	    	);

	    	// Insert the post into the database
	    	$customer_post_id = wp_insert_post( $my_post );

	    	// Add data to customer
	    	if ($website_hosting_plan) {
	    		update_field( "field_549d42b57c687", $website_hosting_plan, $customer_post_id );
	    	} else {
	    		update_field( "field_549d42b57c687", "basic", $customer_post_id );
	    	}
	    	if ($website_hosting_price) {
	    		// assign hosting plan
		    	update_field( "field_549d42d07c688", $website_hosting_price, $customer_post_id );

		    	// calculate and assign new total price
	    		$hosting_price = get_field('hosting_price', $post_id);
	    		$addons = get_field('addons', $post_id);

	    	    // check if the repeater field has rows of data
	    	    if( have_rows('addons', $post_id) ):

	    	     	// loop through the rows of data
	    	        while ( have_rows('addons', $post_id) ) : the_row();
	    	            // vars
	    	    		$name = get_sub_field('name');
	    	    		$price = get_sub_field('price');
	    	    		$addon_total = $price + $addon_total;
	    	        endwhile;

	    	    else :
	    	    // no rows found
	    	    endif;
	    	    $total_price = $hosting_price + $addon_total;
		    	update_field( "field_56181aaed39a9", $total_price, $customer_post_id );
		    } else {
		    	update_field( "field_549d42d07c688", "240", $customer_post_id ); 	// Hosting Price
		    	update_field( "field_56181aaed39a9", "240", $customer_post_id ); 	// Total Price
		    	update_field( "field_56252d8051ee2", "year", $customer_post_id ); 	// Billing Terms
		    }
		    if ($website_billing_date) {
	    		update_field( "field_549d430d7c68c", $website_billing_date, $customer_post_id );
		    } else {
		    	// No date so assign the first day of the next month
		    	$first_day_next_month = date("Ymd", strtotime(date('m', strtotime('+1 month')).'/01/'.date('Y', strtotime('+1 month')).' 00:00:00'));
		    	update_field( "field_549d430d7c68c", $first_day_next_month, $customer_post_id );
		    }
		    if ($website_addons) {
		    	update_field( "field_549ed77808354", $website_addons, $customer_post_id );
		    }
		    if ($website_billing_method) {
		    	update_field( "field_549d42d37c689", $website_billing_method, $customer_post_id );
		    }
		    if ($website_billing_email) {
		    	update_field( "field_549d43087c68b", $website_billing_email, $customer_post_id );
		    }
	    	update_field( "field_561936147136b", "active", $customer_post_id );

	    	// Link website to customer
	    	update_field( "field_56181a1fcf6e2", $customer_post_id, $post_id);


	    } else {

	    	// Load customer data

	    	$customer_id = $customer[0];

	    	$billing_terms = get_field('billing_terms', $customer_id);
	    	$billing_date = date('Y-m-d', strtotime(get_field('billing_date', $customer_id)));
	    	$billing_date_month = date('m', strtotime(get_field('billing_date', $customer_id)));
	    	$current_month = date('m');


			// If yearly then calculate date of beginning of current pay period
			if ($billing_terms == "year") {
				$billing_period = "";
			}
			// If monthly then calculate date of beginning of current pay period
			if ($billing_terms == "month") {
				$billing_period = "";
			}
			// If quarterly then calculate date of beginning of current pay period
			if ($billing_terms == "quarter") {
				$billing_period = "";
			}

	    }

	    // Update customer usage
	    if (isset( $views ) and is_array( $customer )) {

	    	$views = 0;
	    	$storage = 0;
	    	$customer_id = $customer[0];

	    	/*
	    	*  Query posts for a relationship value.
	    	*  This method uses the meta_query LIKE to match the string "123" to the database value a:1:{i:0;s:3:"123";} (serialized array)
	    	*/

	    	$websites = get_posts(array(
	    		'post_type' 			=> 'captcore_website',
	    	  'posts_per_page'  => '-1',
	    	  'meta_query' 			=> array(
					 'relation'		=> 'AND',
						array(
	    				'key' => 'status', // name of custom field
	    				'value' => 'active', // matches exaclty "123", not just 123. This prevents a match for "1234"
	    				'compare' => '='
	    			),
	    			array(
	    				'key' => 'customer', // name of custom field
	    				'value' => '"' . $customer_id . '"', // matches exaclty "123", not just 123. This prevents a match for "1234"
	    				'compare' => 'LIKE'
	    			)
	    		)
	    	));

	    	if( $websites ):
	    		foreach( $websites as $website ):

	    			$storage = $storage + get_field('storage', $website->ID);
	    			$views = $views + get_field('views', $website->ID);

	    		 endforeach;
	    	endif;

	    	update_field( "field_59089b37bd588", $storage , $customer_id );
	    	update_field( "field_59089b3ebd589", $views , $customer_id );

	    }

	}
	if ( get_post_type( $post_id ) == 'captcore_customer' ) {
		$custom = get_post_custom($post_id);
		$hosting_price = $custom["hosting_price"][0];
		$addons = get_field('addons', $post_id);

	    // check if the repeater field has rows of data
	    if( have_rows('addons') ):

	     	// loop through the rows of data
	        while ( have_rows('addons') ) : the_row();

	            // vars
	    		$name = get_sub_field('name');
	    		$price = get_sub_field('price');
	    		$addon_total = $price + $addon_total;

	        endwhile;

	    else :

	        // no rows found

	    endif;
	    $total_price = $hosting_price + $addon_total;
	    update_field('field_56181aaed39a9', $total_price, $post_id);
	}

	if ( get_post_type( $post_id ) == 'captcore_domain' ) {

		if ( get_field('domain_id', $post_id ) == "" ) {

			$domainname = get_the_title( $post_id );

			// Load domains from transient
			$constellix_all_domains = get_transient( 'constellix_all_domains' );

			// If empty then update transient with large remote call
		  if( empty( $constellix_all_domains ) ) {

				$constellix_all_domains = constellix_api_get("domains");

				// Save the API response so we don't have to call again until tomorrow.
    		set_transient( 'constellix_all_domains', $constellix_all_domains, HOUR_IN_SECONDS );

			}

			// Search API for domain ID
			foreach($constellix_all_domains as $domain) {
				if( $domainname == $domain->name ) {
					$domain_id = $domain->id;
				}
			}

			if ( $domain_id ) {
				// Found domain ID from API so update post
				update_field("domain_id", $domain_id, $post_id);
			} else {
				// Generate a new domain zone and adds the new domain ID to the post
				$post = array( "names" => array( $domainname ) );

				$response = constellix_api_post("domains", $post);

				foreach($response as $domain) {
				  // Capture new domain IDs from $response
				  $domain_id = $domain->id;
				}
				update_field("domain_id", $domain_id, $post_id);
			}
			// Assign domain to customers
			$args = array(
				'title' => $domainname,
				'post_type' => 'captcore_website'
			);
			$website = get_posts( $args );
			$website_id = $website[0]->ID;
			$customer = get_field('customer', $website_id );
			$customer_id = $customer[0];
			$domains = get_field("domains", $customer_id);

			// Add domains to customer if not already assigned
			if ( !in_array($post_id, $domains) ) {
				$domains[] = $post_id;
				update_field("domains", $domains, $customer_id);
			}

		}

	}

	if ( get_post_type( $post_id ) == 'captcore_processlog' ) {
		$custom = get_post_custom($post_id);
		$process_id = get_field('process', $post_id);
		$process_id = $process_id[0];
		$roles = has_term("maintenance","process_role",$process_id) + has_term("growth","process_role",$process_id) + has_term("support","process_role",$process_id);
		// Check if process is under the maintenance, growth or support role.
		if ($roles > 0) {
			// Making log public which will be viewable over WP REST API
			update_field('field_584dc76e7eec2', "1", $post_id);
		} else {
			// Make it private
			update_field('field_584dc76e7eec2', "", $post_id);
		}

	}

	if ( get_post_type( $post_id ) == 'captcore_contact' ) {

		$first_name = get_field('first_name', $post_id);
		$last_name = get_field('last_name', $post_id);
		$email = get_field('email', $post_id);

		$new_title = "";

		if ($first_name and $last_name) {
			$new_title = $first_name . " " . $last_name . " (" . $email . ")";
		} else {
			$new_title = $email;
		}

		// Update post
		  $my_post = array(
		      'ID'           => $post_id,
		      'post_title'   => $new_title
		  );

		// Update the post title
		wp_update_post( $my_post );

	}
}

add_action( 'rest_api_init', function () {
  register_rest_route( 'captaincore/v1', '/client', array(
    'methods' => 'GET',
    'callback' => 'captaincore_client_options_func',
  ) );
} );

function captaincore_client_options_func( WP_REST_Request $request ) {

	$data = array(
		"profile_image" => get_field("profile_image","option"),
		"description" => get_field("description","option"),
		"contact_info" => get_field("contact_info","option"),
		"business_name" => get_field("business_name","option"),
		"business_tagline" => get_field("business_tagline","option"),
		"business_link" => get_field("business_link","option"),
		"business_logo" => get_field("business_logo","option"),
		"hosting_dashboard_link" => get_field("hosting_dashboard_link","option"),
	);

  return $data;

}

// Add meta fields to API
add_action( 'rest_api_init','slug_register_ah_fields' );

function slug_register_ah_fields() {
	register_rest_field( 'captcore_website', 'launch_date',
		array(
		   'get_callback'    => 'slug_get_post_meta_cb',
		   'update_callback' => 'slug_update_post_meta_cb',
		   'schema'          => null,
	));
	register_rest_field( 'captcore_website', 'closed_date',
		array(
			 'get_callback'    => 'slug_get_post_meta_cb',
			 'update_callback' => 'slug_update_post_meta_cb',
			 'schema'          => null,
	));
	register_rest_field( 'captcore_website', 'storage',
		array(
		   'get_callback'    => 'slug_get_post_meta_cb',
		   'update_callback' => 'slug_update_post_meta_cb',
		   'schema'          => null,
	));
	register_rest_field( 'captcore_website', 'address',
		array(
		   'get_callback'    => 'slug_get_post_meta_cb',
		   'update_callback' => 'slug_update_post_meta_cb',
		   'schema'          => null,
	));
	register_rest_field( 'captcore_website', 'server',
		array(
		   'get_callback'    => 'slug_get_server',
		   'update_callback' => 'slug_update_server',
		   'schema'          => null,
	));
	register_rest_field( 'captcore_website', 'views',
		array(
		   'get_callback'    => 'slug_get_post_meta_cb',
		   'update_callback' => 'slug_update_post_meta_cb',
		   'schema'          => null,
	));
	register_rest_field( 'captcore_customer', 'billing_terms',
		array(
		   'get_callback'    => 'slug_get_post_meta_array',
		   'update_callback' => 'slug_update_post_meta_cb',
		   'schema'          => null,
	));
	register_rest_field( 'captcore_customer', 'addons',
		array(
		   'get_callback'    => 'slug_get_post_meta_array',
		   'update_callback' => 'slug_update_post_meta_cb',
		   'schema'          => null,
	));
	register_rest_field( 'captcore_customer', 'preloaded_email',
		array(
		   'get_callback'    => 'slug_get_post_meta_array',
		   'update_callback' => 'slug_update_post_meta_cb',
		   'schema'          => null,
	));
	register_rest_field( 'captcore_customer', 'preloaded_users',
		array(
		   'get_callback'    => 'slug_get_post_meta_array',
		   'update_callback' => 'slug_update_post_meta_cb',
		   'schema'          => null,
	));
	register_rest_field( 'captcore_customer', 'preloaded_plugins',
		array(
		   'get_callback'    => 'slug_get_post_meta_array',
		   'update_callback' => 'slug_update_post_meta_cb',
		   'schema'          => null,
	));
	register_rest_field( 'captcore_customer', 'billing_method',
		array(
		   'get_callback'    => 'slug_get_post_meta_cb',
		   'update_callback' => 'slug_update_post_meta_cb',
		   'schema'          => null,
	));
	register_rest_field( 'captcore_customer', 'billing_email',
		array(
		   'get_callback'    => 'slug_get_post_meta_cb',
		   'update_callback' => 'slug_update_post_meta_cb',
		   'schema'          => null,
	));
	register_rest_field( 'captcore_customer', 'billing_date',
		array(
		   'get_callback'    => 'slug_get_post_meta_cb',
		   'update_callback' => 'slug_update_post_meta_cb',
		   'schema'          => null,
	));
	register_rest_field( 'captcore_customer', 'storage',
		array(
		   'get_callback'    => 'slug_get_post_meta_cb',
		   'update_callback' => 'slug_update_post_meta_cb',
		   'schema'          => null,
	));
	register_rest_field( 'captcore_customer', 'views',
		array(
		   'get_callback'    => 'slug_get_post_meta_cb',
		   'update_callback' => 'slug_update_post_meta_cb',
		   'schema'          => null,
	));
	register_rest_field( 'captcore_customer', 'paid_by',
		array(
		   'get_callback'    => 'slug_get_paid_by',
		   'update_callback' => 'slug_update_post_meta_cb',
		   'schema'          => null,
	));
	register_rest_field( 'captcore_customer', 'paid_by_me',
		array(
		   'get_callback'    => 'slug_get_paid_by_me',
		   'update_callback' => 'slug_update_post_meta_cb',
		   'schema'          => null,
	));
	register_rest_field( 'captcore_customer', 'hosting_price',
		array(
		   'get_callback'    => 'slug_get_post_meta_cb',
		   'update_callback' => 'slug_update_post_meta_cb',
		   'schema'          => null,
	));
	register_rest_field( 'captcore_website', 'status',
		array(
		   'get_callback'    => 'slug_get_post_meta_cb',
		   'update_callback' => 'slug_update_post_meta_cb',
		   'schema'          => null,
	));
	register_rest_field( 'captcore_customer', 'hosting_plan',
		array(
		   'get_callback'    => 'slug_get_post_meta_cb',
		   'update_callback' => 'slug_update_post_meta_cb',
		   'schema'          => null,
	));
	register_rest_field( 'captcore_customer', 'total_price',
		array(
		   'get_callback'    => 'slug_get_post_meta_cb',
		   'update_callback' => 'slug_update_post_meta_cb',
		   'schema'          => null,
	));
	register_rest_field( 'captcore_website', 'customer',
		array(
		   'get_callback'    => 'slug_get_post_meta_cb',
		   'update_callback' => 'slug_update_post_meta_cb',
		   'schema'          => null,
	));
	register_rest_field( 'captcore_processlog', 'description',
		array(
		   'get_callback'    => 'slug_get_process_description',
		   'update_callback' => 'slug_update_post_meta_cb',
		   'schema'          => null,
	));
	register_rest_field( 'captcore_processlog', 'process',
		array(
		   'get_callback'    => 'slug_get_process',
		   'update_callback' => 'slug_update_post_meta_cb',
		   'schema'          => null,
	));

 };

add_action("manage_posts_custom_column",  "customer_custom_columns");
add_filter("manage_edit-captcore_website_columns", "website_edit_columns");
add_filter("manage_edit-captcore_customer_columns", "customer_edit_columns");
add_filter("manage_edit-captcore_customer_sortable_columns", 'customer_sortable_columns' );
add_filter("manage_edit-captcore_changelog_columns", "changelog_edit_columns");

function customer_sortable_columns( $columns ) {
	$columns['hosting_plan'] = 'hosting_plan';
	$columns['renewal'] = 'renewal';
	$columns['url'] = 'url';
	$columns['total'] = 'total';

	return $columns;
}

function changelog_edit_columns($columns){
  $columns = array(
  	"cb" => '<input type="checkbox" />',
    "title" => "Title",
    "client" => "Client",
    "date" => "Date",
  );

  return $columns;
}

function customer_edit_columns($columns){
  $columns = array(
  	"cb" => '<input type="checkbox" />',
    "title" => "Title",
    "hosting_plan" => "Plan",
    "renewal" => "Renews",
    "addons" => "Addons",
    "total" => "Total",
    "status" => "Status"

  );

  return $columns;
}
function website_edit_columns($columns){
  $columns = array(
  	"cb" => '<input type="checkbox" />',
    "title" => "Title",
    "customer" => "Customer",
    "partner" => "Partner",
    "launched" => "Launched",
    "status" => "Status"

  );

  return $columns;
}

function captaincore_formatted_acf_value_storage( $value, $id, $column ) {

	if ( $column instanceof ACA_ACF_Column ) {
			$meta_key = $column->get_meta_key(); // This gets the ACF field key
			$acf_field = $column->get_acf_field(); // Gets an ACF object
			$acf_type =  $column->get_acf_field_option( 'type' ); // Get the ACF field type

			if( 'storage' == $meta_key and is_numeric($value) ){
				// Alter the display $value
				$value = human_filesize($value);
			}
		}

    return $value;
}
add_filter( 'ac/column/value', 'captaincore_formatted_acf_value_storage', 10, 3 );

function my_pre_get_posts( $query ) {

	// only modify queries for 'website' post type
	if( $query->query_vars['post_type'] == 'captcore_customer' ) {

		$orderby = $query->get( 'orderby');

		if ('hosting_plan' == $orderby) {
			$query->set('orderby', 'meta_value');
			$query->set('meta_key', 'hosting_plan');
		}

		if ('renewal' == $orderby) {
			$query->set('orderby', 'meta_value_num');
			$query->set('meta_key', 'billing_date');
		}

		if ('total' == $orderby) {
			$query->set('orderby', 'meta_value_num');
			$query->set('meta_key', 'total_price');
		}

	}

	// return
	return $query;

}

// add_action('pre_get_posts', 'my_pre_get_posts');
function customer_custom_columns($column){
  global $post;

  switch ($column) {
	case "hosting_plan":
		$custom = get_post_custom();
		echo ucfirst($custom["hosting_plan"][0]);
		break;
  	case "client":
  		$clients = get_field('website', $post->ID);
		if( $clients ):
			foreach( $clients as $p ): // variable must NOT be called $post (IMPORTANT)
				echo edit_post_link(get_the_title( $p ), '<p>','</p>', $p);
			endforeach;
		endif;
		break;
	case "customer":
		$hosting_price = get_field('hosting_price', $post->ID);
		$customers = get_field('customer', $post->ID);
		if( $customers ):
			foreach( $customers as $customer): // variable must be called $post (IMPORTANT)
				edit_post_link(get_the_title($customer), '<p>', '</p>', $customer);
			endforeach;
			//wp_reset_postdata(); // IMPORTANT - reset the $post object so the rest of the page works correctly
		endif;
		//echo "<small>";
		//echo "Old Details<br />";
		//the_field('hosting_plan', $post->ID);
		//the_field('hosting_price', $post->ID);
		//$addons = get_field('addons', $post->ID);
		//print_r($addons);

	    // check if the repeater field has rows of data
	    //if( have_rows('addons', $post->ID) ):
//
	    // 	// loop through the rows of data
	    //    while ( have_rows('addons', $post->ID) ) : the_row();
//
	    //        // vars
	    //		$name = get_sub_field('name');
	    //		$price = get_sub_field('price');
	    //		$addon_total = $price + $addon_total;
//
	    //    endwhile;
//
	    //else :
//
	    //    // no rows found
//
	    //endif;
	    //$total_price = $hosting_price + $addon_total;
	    //echo $total_price;
		//the_field('addons', $post->ID);
		//the_field('billing_date', $post->ID);
		//the_field('billing_method', $post->ID);
		//the_field('billing_email', $post->ID);
		//echo "</small>";
		break;
	case "partner":
		$partners = get_field('partner', $post->ID);
		if( $partners ):
			foreach( $partners as $partner): // variable must be called $post (IMPORTANT)
				edit_post_link(get_the_title($partner), '<p>', '</p>',$partner);
			endforeach;
			//wp_reset_postdata(); // IMPORTANT - reset the $post object so the rest of the page works correctly
		endif;
		break;
	case "renewal":
		date_default_timezone_set('America/New_York');
		$date = get_field('billing_date', $post->ID);
		if ($date) {
			echo date('Y-m-d', strtotime($date));
		}
		break;
	case "launched":
		date_default_timezone_set('America/New_York');
		$date = get_field('launch_date', $post->ID);
		if ($date) {
			echo date('Y-m-d', strtotime($date));
		}
		break;
	case "total":
		$billing_terms = get_field('billing_terms', $post->ID);
		$total_price = get_field('total_price', $post->ID);
		echo "$".$total_price;
		if (isset($billing_terms)) {
			echo "/".$billing_terms;
		}
		break;
	case "addons":
		$custom = get_post_custom();
		$hosting_plan = $custom["hosting_plan"][0];
		$hosting_price = $custom["hosting_price"][0];
		$addons = get_field('addons', $post->ID);
		$addon_total = 0;

		$billing_info = "<p>";
		if ($addons) {
		 	$billing_info .= count($addons) ." addons";
	    }
	    $billing_info .= "</p>";

		echo $billing_info;
		break;
	case "status":
		$storage = get_field('storage', $post->ID);
		$status = get_field('status', $post->ID);
		echo ucfirst($status);
		break;
	case "storage":
		$storage = get_field('storage', $post->ID);
		if ($storage) {
			echo human_filesize($storage);
		}
		break;
  }
}

function human_filesize($bytes, $decimals = 2) {
    $size = array('B','kB','MB','GB','TB','PB','EB','ZB','YB');
    $factor = floor((strlen($bytes) - 1) / 3);
    return sprintf("%.{$decimals}f", $bytes / pow(1024, $factor)) . @$size[$factor];
}

function my_relationship_result( $title, $post, $field, $post_id ) {

	// load a custom field from this $object and show it in the $result
	$process = get_field('process', $post->ID);
	$process_title = $post->ID . " - " . get_the_title($process[0]) . " - " . get_the_author_meta( 'display_name', $post->post_author );


	// overide to title
	$title = $process_title;


	// return
	return $title;

}

// filter for every field
add_filter('acf/fields/relationship/result/name=captcore_processlog', 'my_relationship_result', 10, 4);

/**
 * Deregister matching post types.
 */
function custom_unregister_theme_post_types() {
    global $wp_post_types;
    foreach( array( 'project' ) as $post_type ) {
        if ( isset( $wp_post_types[ $post_type ] ) ) {
            unset( $wp_post_types[ $post_type ] );
        }
    }
}
add_action( 'init', 'custom_unregister_theme_post_types', 20 );

function checkApiAuth( $result ){

	if ( ! empty( $result ) ) {
			return $result;
	}

	global $wp;

	// Strips first part of endpoint
	$endpoint_all = str_replace("wp-json/wp/v2/","",$wp->request);
	if ( strpos($wp->request, "wp-json/captaincore/v1") !== false ) {
		return $result;
	}

	// Breaks apart endpoint into array
	$endpoint_all = explode('/', $endpoint_all);

	// Grabs only the first part of the endpoint
	$endpoint = $endpoint_all[0];

	// User not logged in so do custom token auth
	if ( ! is_user_logged_in() ) {

		if ( $endpoint == "posts" ) {
			return $result;
		}

		// custom auth on changelog endpoint, exlcuding global posts
		if ( $endpoint == "captcore_changelog" and !isset($_GET["global"]) ) {

			$token = $_GET["token"];
			$website = $_GET["website"];

			$token_lookup = get_field('token', $website);

			// Token lookup
			if ($token and $token == $token_lookup) {
				return $result;
			}

		} elseif (  $endpoint == "captcore_changelog" and isset($_GET["global"]) ) {
			// Return global changelogs for non logged in users
			return $result;
		}

		// custom auth on changelog endpoint, exlcuding global posts
		if ($endpoint == "captcore_processlog") {

			$token = $_GET["token"];
			$website = $_GET["website"];

			$token_lookup = get_field('token', $website);

			// Token lookup

			if ($token and $token == $token_lookup) {
				return $result;
			}

		}

		// custom auth on website endpoint, excluding global posts
		if ($endpoint == "captcore_website") {

			$website_id = $endpoint_all[1];

			$token = $_GET["token"];
			$domain = $_GET["search"];

			// Token lookup
			// WP_Query arguments
			$args = array (
				'post_type'         => array( 'captcore_website' ),
				'name'          		=> $domain,
				'exact'							=> true,
				'posts_per_page'    => '1',
			);

			// The Query
			$query = new WP_Query( $args );

			// The Loop
			if ( $query->have_posts() ) {
				while ( $query->have_posts() ) {
					$query->the_post();
					$token_lookup = get_field('token');
				}
			} else {
				// no posts found
			}

			// Restore original Post Data
			wp_reset_postdata();

			if ($token == $token_lookup and $token <> "" and $token_lookup <> "") {
				return $result;
			}

		}

		// custom auth on customer endpoint, exlcuding global posts
		if ($endpoint == "captcore_customer") {

			$token = $_GET["token"];
			$token_match = false;
			$id = $endpoint_all[1];

			/*
			*  Query posts for a relationship value.
			*  This method uses the meta_query LIKE to match the string "123" to the database value a:1:{i:0;s:3:"123";} (serialized array)
			*/

			// Token lookup. Find all websites attached to customer to find a token match.
			$websites = get_posts(array(
				'post_type'				=> 'captcore_website',
		    'posts_per_page'	=> '-1',
		    'meta_query' 			=> array(
			    'relation' => 'OR',
					array(
						'key' => 'customer', // name of custom field
						'value' => '"' . $id . '"', // matches exaclty "123", not just 123. This prevents a match for "1234"
						'compare' => 'LIKE'
					),
					array(
						'key' => 'partner', // name of custom field
						'value' => '"' . $id . '"', // matches exaclty "123", not just 123. This prevents a match for "1234"
						'compare' => 'LIKE'
					)
				)
			));

			if( $websites ):
				foreach( $websites as $website ):

					$token_lookup = get_field('token',$website->ID);
					if ($token_lookup == $token) {
						$token_match = true;
					}
				endforeach;
			endif;

			if ($token_match) {
				return $result;
			}
		}
		// User not logged in and no valid bypass token found
		return new WP_Error( "rest_not_logged_in", "You are not currently logged in.", array( "status" => 401 ) );

	} else {

		// User logged in so check anchor_verify_permissions

		if ($endpoint == "captcore_website") {

			$website_id = $endpoint_all[1];

			if ( !anchor_verify_permissions($website_id) ) {
				return new WP_Error( 'rest_token_invalid', __( 'Token is invalid'), array( 'status' => 403 ) );
			}

		}

		if ($endpoint == "captcore_customer") {

			$customer_id = $endpoint_all[1];

			if ( !anchor_verify_permissions_customer($customer_id) ) {
				return new WP_Error( 'rest_token_invalid', __( 'Token is invalid'), array( 'status' => 403 ) );
			}

		}

	}
	return $result;

}
add_filter( "rest_authentication_errors", "checkApiAuth");

// Loads all domains for partners
function get_domains_per_partner ( $partner_id ) {

	$all_domains = [];

	// Load websites assigned to partner
	$websites = get_posts(array(
		'post_type' 			=> 'captcore_website',
		'posts_per_page'	=> '-1',
		'order'						=> 'asc',
		'orderby'					=> 'title',
		'fields'          => 'ids',
		'meta_query'			=> array(
			'relation'			=> 'AND',
				array(
					'key' => 'partner', // name of custom field
					'value' => '"' . $partner_id . '"', // matches exaclty "123", not just 123. This prevents a match for "1234"
					'compare' => 'LIKE'
				),
		)
	));
	foreach( $websites as $website ):
		$customers = get_field('customer', $website);

		foreach( $customers as $customer ):

			$domains = get_field('domains', $customer);
			if ($domains) {
				foreach( $domains as $domain ):
					$domain_name = get_the_title( $domain );
					if($domain_name) {
						$all_domains[$domain_name] = $domain;
					}
				endforeach;
			}

		endforeach;

	endforeach;

	// Sort array by domain name
	ksort($all_domains);

	// None found, check for customer
	if ( count($all_domains) == 0 ) {

		// Load websites assigned to partner
		$websites = get_posts(array(
			'post_type' 			=> 'captcore_website',
			'posts_per_page'	=> '-1',
			'order'						=> 'asc',
			'orderby'					=> 'title',
			'fields'          => 'ids',
			'meta_query'			=> array(
				'relation'			=> 'AND',
					array(
						'key' => 'customer', // name of custom field
						'value' => '"' . $partner_id . '"', // matches exaclty "123", not just 123. This prevents a match for "1234"
						'compare' => 'LIKE'
					),
			)
		));
		foreach( $websites as $website ):
			$customers = get_field('customer', $website);

			foreach( $customers as $customer ):

				$domains = get_field('domains', $customer);
				if ($domains) {
					foreach( $domains as $domain ):
						$domain_name = get_the_title( $domain );
						$all_domains[$domain_name] = $domain;
					endforeach;
				}

			endforeach;

		endforeach;

		// Sort array by domain name
		ksort($all_domains);

	}

	return $all_domains;
}

// Checks current user for valid permissions
function anchor_verify_permissions( $website_id ) {

	$current_user = wp_get_current_user();
	$role_check = in_array( 'administrator', $current_user->roles );

	// Checks for a current user. If admin found pass if not check permissions
	if ( $current_user && $role_check ) {
		return true;
	} else {
		// Checks for other roles
		$role_check = in_array( 'partner', $current_user->roles );

		// Checks current users permissions
		$partner = get_field('partner', 'user_'. get_current_user_id());

		if ($partner and $role_check) {
			foreach ($partner as $partner_id) {

				$websites = get_posts(array(
					'post_type' 			=> 'captcore_website',
	        'posts_per_page'	=> '-1',
					'order'						=> 'asc',
					'orderby'					=> 'title',
	        'meta_query'			=> array(
	        'relation'				=> 'AND',
						array(
							'key' => 'partner', // name of custom field
							'value' => '"' . $partner_id . '"', // matches exaclty "123", not just 123. This prevents a match for "1234"
							'compare' => 'LIKE'
						),
					)
				));

				if( $websites ):

					foreach( $websites as $website ):
						$customer_id = get_field('customer', $website->ID);

						if ($website_id == $website->ID) {
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
function anchor_verify_permissions_customer( $customer_id ) {

	$current_user = wp_get_current_user();
	$role_check = in_array( 'administrator', $current_user->roles );

	// Checks for a current user. If admin found pass if not check permissions
	if ( $current_user && $role_check ) {
		return true;
	} else {
		// Checks for other roles
		$role_check = in_array( 'partner', $current_user->roles );

		// Checks current users permissions
		$partner = get_field('partner', 'user_'. get_current_user_id());

		if ($partner and $role_check) {
			foreach ($partner as $partner_id) {

				$websites = get_posts(array(
					'post_type' 			=> 'captcore_website',
	        'posts_per_page'	=> '-1',
					'order'						=> 'asc',
					'orderby'					=> 'title',
	        'meta_query'			=> array(
		        'relation'			=> 'AND',
							array(
								'key' => 'partner', // name of custom field
								'value' => '"' . $partner_id . '"', // matches exaclty "123", not just 123. This prevents a match for "1234"
								'compare' => 'LIKE'
							),
							array(
								'key'	  		=> 'status',
								'value'	  	=> 'closed',
								'compare' 	=> '!=',
							),
						)
				));

				if( $websites ):

					foreach( $websites as $website ):
						$website_customer_id = get_field('customer', $website->ID);

						if ($customer_id == $website_customer_id[0]) {
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
function anchor_verify_permissions_domain( $domain_id ) {

	$domain_exists = get_posts( array(
		'post_type' 			=> 'captcore_domain',
		'posts_per_page'	=> '-1',
		'meta_query'			=> array(
			 array(
					'key' => 'domain_id',
					'value' => $domain_id,
					'compare' => '='
				)
			)
	 ));

	// Check if domain exists
	if ( $domain_exists ) {

		$current_user = wp_get_current_user();
		$role_check = in_array( 'administrator', $current_user->roles );

		// Checks for a current user. If admin found pass if not check permissions
		if ( $current_user && $role_check ) {
			return true;
		} elseif( $current_user ) {
			// Not an administrator so proceed with checking permissions

			// Checks current users permissions
			$partner = get_field( 'partner', 'user_'. get_current_user_id() );

			foreach ($partner as $partner_id) {

				$websites = get_posts(array(
					'post_type' 			=> 'captcore_website',
	        'posts_per_page'	=> '-1',
					'order'						=> 'asc',
					'orderby'					=> 'title',
	        'meta_query'			=> array(
		        'relation'			=> 'AND',
							array(
								'key' => 'partner',
								'value' => '"' . $partner_id . '"',
								'compare' => 'LIKE'
							),
						)
				));

				if( $websites ):

					foreach( $websites as $website ):
						$website_customer_id = get_field('customer', $website->ID);
						$domains = get_field( 'domains', $website_customer_id[0] );
						if ($domains) {
							foreach( $domains as $domain ) {
								if ( $domain_id == get_field('domain_id', $domain )) {
									return true;
								}
							}
						}

					endforeach;
				endif;

			}

			foreach ($partner as $partner_id) {

				$websites = get_posts(array(
					'post_type' 			=> 'captcore_website',
	        'posts_per_page'	=> '-1',
					'order'						=> 'asc',
					'orderby'					=> 'title',
	        'meta_query'			=> array(
		        'relation'			=> 'AND',
							array(
								'key' => 'customer',
								'value' => '"' . $partner_id . '"',
								'compare' => 'LIKE'
							),
						)
				));

				if( $websites ):

					foreach( $websites as $website ):
						$website_customer_id = get_field('customer', $website->ID);
						$domains = get_field( 'domains', $website_customer_id[0] );
						if ($domains) {
							foreach( $domains as $domain ) {
								if ( $domain_id == get_field('domain_id', $domain )) {
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
add_action( 'wp_ajax_anchor_dns', 'anchor_dns_action_callback' );

function anchor_dns_action_callback() {
	global $wpdb; // this is how you get access to the database

	$domain_id = intval( $_POST['domain_key'] );
	$record_updates = $_POST['record_updates'];

	$responses = "[";

	foreach ($record_updates as $record_update) {

		$record_id = $record_update["record_id"];
		$record_type = $record_update["record_type"];
		$record_name = $record_update["record_name"];
		$record_value = $record_update["record_value"];
		$record_ttl = $record_update["record_ttl"];
		$record_status = $record_update["record_status"];

		if ( $record_status == "new-record" ) {
			if ( $record_type == "mx" ) {

				// Formats MX records into array which API can read
				$mx_records = [];
				foreach($record_value as $mx_record) {
					$mx_records[] = array(
						"value" => $mx_record["value"],
						"level" => $mx_record["priority"],
						"disableFlag" => false
					);
				}

				$post = array(
				  "recordOption" => "roundRobin",
				  "name" => $record_name,
				  "ttl" => $record_ttl,
				  "roundRobin" => $mx_records,
				);

			} elseif ( $record_type == "cname" ) {

				$post = array(
				  "name" => $record_name,
				  "host" => $record_value,
					"ttl" => $record_ttl,
				);

			} elseif ($record_type == "httpredirection" ) {

				$post = array(
				  "name" => $record_name,
				  "ttl" => $record_ttl,
				  "url" => $record_value,
					"redirectTypeId" => "3",
				);

		  } else {
				$post = array(
				  "recordOption" => "roundRobin",
				  "name" => $record_name,
				  "ttl" => $record_ttl,
				  "roundRobin" => array(
				    array(
				      "value" => $record_value,
				      "disableFlag" => false,
				    ),
				   ),
				);

			}

			$response = constellix_api_post("domains/$domain_id/records/$record_type", $post);

			foreach( $response as $result ) {
				if( is_array($result) ) {
					$result["errors"] = $result[0];
					$responses = $responses . json_encode($result) . ",";
				} else {
					$responses = $responses . json_encode($result) . ",";
				}
			}

		}

		if ( $record_status == "edit-record" ) {
			if ( $record_type == "mx" ) {

				// Formats MX records into array which API can read
				$mx_records = [];
				foreach($record_value as $mx_record) {
					$mx_records[] = array(
						"value" => $mx_record["value"],
						"level" => $mx_record["priority"],
						"disableFlag" => false
					);
				}

				$post = array(
				  "recordOption" => "roundRobin",
				  "name" => $record_name,
				  "ttl" => $record_ttl,
				  "roundRobin" => $mx_records,
				);

			} elseif ( $record_type == "txt" or $record_type == "a" ) {

				// Formats A and TXT records into array which API can read
				$records = [];
				foreach($record_value as $record) {
					$records[] = array(
						"value" => stripslashes($record["value"]),
						"disableFlag" => false
					);
				}

				$post = array(
				  "recordOption" => "roundRobin",
				  "name" => "$record_name",
				  "ttl" => $record_ttl,
				  "roundRobin" => $records,
				);

			} elseif ( $record_type == "httpredirection" ) {

				$post = array(
				  "name" => $record_name,
				  "ttl" => $record_ttl,
				  "url" => $record_value,
					"redirectTypeId" => "3",
				);

			} elseif ( $record_type == "cname" ) {

				$post = array(
				  "name" => $record_name,
				  "host" => $record_value,
					"ttl" => $record_ttl,
				);

			} else {
				$post = array(
				  "recordOption" => "roundRobin",
				  "name" => $record_name,
				  "ttl" => $record_ttl,
				  "roundRobin" => array(
				    array(
				      "value" => stripslashes($record_value),
				      "disableFlag" => false,
				    ),
				   ),
				);

			}
			$response = constellix_api_put("domains/$domain_id/records/$record_type/$record_id", $post);
			$response->domain_id = $domain_id;
			$response->record_id = $record_id;
			$response->record_type = $record_type;
			$responses = $responses . json_encode($response) . ",";
		}

		if ( $record_status == "remove-record" ) {
			$response = constellix_api_delete("domains/$domain_id/records/$record_type/$record_id");
			$response->domain_id = $domain_id;
			$response->record_id = $record_id;
			$response->record_type = $record_type;
			$responses = $responses . json_encode($response) . ",";
		}

	}
	$responses = rtrim($responses,',') . "]";

	echo $responses;

	wp_die(); // this is required to terminate immediately and return a proper response
}

// Processes install events (new install, remove install, setup configs)
add_action( 'wp_ajax_anchor_install', 'anchor_install_action_callback' );

function anchor_install_action_callback() {
	global $wpdb; // this is how you get access to the database

	$post_id = intval( $_POST['post_id'] );
	$cmd = $_POST['command'];
	$value = $_POST['value'];
	$addon_type = $_POST['addon_type'];

	$install = get_field('install',$post_id);
	$domain = get_the_title($post_id);
	$address = get_field('address',$post_id);
	$username = get_field('username',$post_id);
	$password = get_field('password',$post_id);
	$protocol = get_field('protocol',$post_id);
	$port = get_field('port',$post_id);
	$homedir = get_field('homedir',$post_id);
	$staging_install = get_field('install_staging',$post_id);
	$staging_address = get_field('address_staging',$post_id);
	$staging_username = get_field('username_staging',$post_id);
	$staging_password = get_field('password_staging',$post_id);
	$staging_protocol = get_field('protocol_staging',$post_id);
	$staging_port = get_field('port_staging',$post_id);
	$staging_homedir  = get_field('homedir_staging',$post_id);
	$s3accesskey = get_field('s3_access_key',$post_id);
	$s3secretkey = get_field('s3_secret_key',$post_id);
	$s3bucket = get_field('s3_bucket',$post_id);
	$s3path = get_field('s3_path',$post_id);
	$token = "***REMOVED***";

	$partners = get_field('partner', $post_id);
	if ($partners) {
		$preloadusers = implode(",", $partners);
	}

	// Assume this is a subsite and reconfigure as such
	if ($install == "") {
		$install = $domain;
		$domain = "";
		$subsite = "true";
	}

	// Disable SSL verification due to self signed cert on other end
	$arrContextOptions=array(
	    "ssl"=>array(
      "verify_peer"=>false,
      "verify_peer_name"=>false,
	    ),
	);

	if ($cmd == "new") {
		$command = "captaincore site add".
		($install ? " $install" : "" ) .
		($post_id ? " --id=$post_id" : "" ) .
		($domain ? " --domain=$domain" : "" ) .
		($username ? " --username=$username" : "" ) .
		($password ? " --password=".rawurlencode(base64_encode($password)) : "" ) .
		($address ? " --address=$address" : "" ) .
		($protocol ? " --protocol=$protocol" : "" ) .
		($port ? " --port=$port" : "" ) .
		($homedir ? " --homedir=$homedir" : "" ) .
		($staging_install ? " --install_staging=$staging_install" : "" ) .
		($staging_username ? " --username_staging=$staging_username" : "" ) .
		($staging_password ? " --password_staging=".rawurlencode(base64_encode($staging_password)) : "" ) .
		($staging_address ? " --address_staging=$staging_address" : "" ) .
		($staging_protocol ? " --protocol_staging=$staging_protocol" : "" ) .
		($staging_port ? " --port_staging=$staging_port" : "" ) .
		($staging_homedir ? " --homedir_staging=$staging_homedir" : "" ) .
		($preloadusers ? " --preloadusers=$preloadusers" : "" ) .
		($subsite ? " --subsite=$subsite" : "" ) .
		($s3accesskey ? " --s3accesskey=$s3accesskey" : "" ) .
		($s3secretkey ? " --s3secretkey=$s3secretkey" : "" ) .
		($s3bucket ? " --s3bucket=$s3bucket" : "" ) .
		($s3path ? " --s3path=$s3path" : "" );
	}
	if ($cmd == "update") {
		$command = "captaincore site update".
		($install ? " $install" : "" ) .
		($post_id ? " --id=$post_id" : "" ) .
		($domain ? " --domain=$domain" : "" ) .
		($username ? " --username=$username" : "" ) .
		($password ? " --password=".rawurlencode(base64_encode($password)) : "" ) .
		($address ? " --address=$address" : "" ) .
		($protocol ? " --protocol=$protocol" : "" ) .
		($port ? " --port=$port" : "" ) .
		($staging_username ? " --staging_username=$staging_username" : "" ) .
		($staging_password ? " --staging_password=".rawurlencode(base64_encode($staging_password)) : "" ) .
		($staging_address ? " --staging_address=$staging_address" : "" ) .
		($staging_protocol ? " --staging_protocol=$staging_protocol" : "" ) .
		($staging_port ? " --staging_port=$staging_port" : "" ) .
		($preloadusers ? " --preloadusers=$preloadusers" : "" ) .
		($homedir ? " --homedir=$homedir" : "" ) .
		($subsite ? " --subsite=$subsite" : "" ) .
		($s3accesskey ? " --s3accesskey=$s3accesskey" : "" ) .
		($s3secretkey ? " --s3secretkey=$s3secretkey" : "" ) .
		($s3bucket ? " --s3bucket=$s3bucket" : "" ) .
		($s3path ? " --s3path=$s3path" : "" );
	}
	if ($cmd == "kinsta-deploy-to-staging") {
		date_default_timezone_set('America/New_York');
		$t=time();
		$timestamp = date("Y-m-d-hms",$t);
		if($value) {
			$command = "captaincore deploy production_to_staging_kinsta $install --email=$value > ~/Tmp/$timestamp-deploy_production_to_staging_kinsta_$install.txt 2>&1 & sleep 5; head ~/Tmp/$timestamp-deploy_production_to_staging_kinsta_$install.txt";
		} else {
			$command = "captaincore deploy production_to_staging_kinsta $install > ~/Tmp/$timestamp-deploy_production_to_staging_kinsta_$install.txt 2>&1 & sleep 5; head ~/Tmp/$timestamp-deploy_production_to_staging_kinsta_$install.txt";
		}
	}
	if ($cmd == "kinsta-deploy-to-production") {
		date_default_timezone_set('America/New_York');
		$t=time();
		$timestamp = date("Y-m-d-hms",$t);
		if($value) {
			$command = "captaincore deploy staging_to_production_kinsta $install --email=$value > ~/Tmp/$timestamp-deploy_staging_to_production_kinsta_$install.txt 2>&1 & sleep 5; head ~/Tmp/$timestamp-deploy_staging_to_production_kinsta_$install.txt";
		} else {
			$command = "captaincore deploy staging_to_production_kinsta $install > ~/Tmp/$timestamp-deploy_staging_to_production_kinsta_$install.txt 2>&1 & sleep 5; head ~/Tmp/$timestamp-deploy_staging_to_production_kinsta_$install.txt";
		}
	}
	if ($cmd == "remove") {
		$command = "captaincore site delete $install";
	}
	if ($cmd == "quick_backup") {
		date_default_timezone_set('America/New_York');
		$t=time();
		$timestamp = date("Y-m-d-hms",$t);
		$command = "captaincore backup $install > ~/Tmp/$timestamp-backup_$install.txt 2>&1";
	}
	if ($cmd == "backup") {
		date_default_timezone_set('America/New_York');
		$t=time();
		$timestamp = date("Y-m-d-hms",$t);
		$command = "captaincore backup $install > ~/Tmp/$timestamp-backup_$install.txt 2>&1 & sleep 5; head ~/Tmp/$timestamp-backup_$install.txt";
	}
	if ($cmd == "snapshot") {
		date_default_timezone_set('America/New_York');
		$t=time();
		$timestamp = date("Y-m-d-hms",$t);
		if($value) {
			$command = "captaincore snapshot $install --email=$value > ~/Tmp/$timestamp-snapshot_$install.txt 2>&1 & sleep 2; head ~/Tmp/$timestamp-snapshot_$install.txt";
		} else {
			$command = "captaincore snapshot $install > ~/Tmp/$timestamp-snapshot_$install.txt 2>&1 & sleep 2; head ~/Tmp/$timestamp-snapshot_$install.txt";
		}

	}
	if ($cmd == "deactivate") {
		$command = "captaincore site deactivate $install";
	}
	if ($cmd == "activate") {
		$command = "captaincore site activate $install";
	}

	if ($cmd == "view_quicksave_changes") {
		$website_id = get_field('website',$post_id);
		$install = get_field('install',$website_id[0]);
		$command = "captaincore get quicksave_changes $install $value";
		$post_id = $website_id;
	}

	if ($cmd == "quicksave_file_diff") {
		$website_id = get_field('website',$post_id);
		$quicksaves_for_website_ids = get_posts(array(
			'fields' => 'ids',
			'post_type' => 'captcore_quicksave',
			'posts_per_page' => '-1',
			'meta_query' => array(
				array(
				'key' => 'website',
				'value' => '"' . $website_id[0] . '"',
				'compare' => 'LIKE'
			))
		));
		foreach($quicksaves_for_website_ids as $key => $quicksave_for_website_id) {
			if ($quicksave_for_website_id == $post_id) { $mykey = $key; }
		}

		$quicksaves_previous_id = $quicksaves_for_website_ids[$mykey+1];
		$install = get_field('install',$website_id[0]);
		$commit = get_field('git_commit',$post_id);
		$commit_previous = get_field('git_commit', $quicksaves_previous_id );
		$command = "captaincore get quicksave_file_diff $install $commit_previous $commit \"$value\"";
		$post_id = $website_id;
	}

	if ($cmd == "rollback") {
		date_default_timezone_set('America/New_York');
		$t=time();
		$timestamp = date("Y-m-d-hms",$t);
		$git_commit = get_field('git_commit', $post_id);
		$website_id = get_field('website', $post_id);
		$install = get_field('install', $website_id[0]);
		$command = "captaincore rollback $install $git_commit --$addon_type=$value > ~/Tmp/$timestamp-rollback_$install.txt 2>&1 & sleep 1; head ~/Tmp/$timestamp-rollback_$install.txt";
		$post_id = $website_id;
	}

	if ($cmd == "quicksave_rollback") {
		date_default_timezone_set('America/New_York');
		$t=time();
		$timestamp = date("Y-m-d-hms",$t);
		$git_commit = get_field('git_commit', $post_id);
		$website_id = get_field('website', $post_id);
		$install = get_field('install', $website_id[0]);
		$command = "captaincore rollback $install $git_commit --all > ~/Tmp/$timestamp-rollback_$install.txt 2>&1 & sleep 1; head ~/Tmp/$timestamp-rollback_$install.txt";
		$post_id = $website_id;
	}

	if ( defined( 'CAPTAINCORE_DEBUG' ) ) {

		if ( anchor_verify_permissions( $post_id ) ) {

			echo $command; // Outputs command on development

		} else {

			echo "Permission denied";
		}

	} else {

		// Checks permissions
		if ( anchor_verify_permissions( $post_id ) ) {

			// Runs command on remote on production
			require_once( ABSPATH . '/vendor/autoload.php' );

			$ssh = new \phpseclib\Net\SSH2( CAPTAINCORE_CLI_ADDRESS, CAPTAINCORE_CLI_PORT );

			if ( !$ssh->login( CAPTAINCORE_CLI_USER, CAPTAINCORE_CLI_KEY ) ) {
	  		exit('Login Failed');
			}
			echo $ssh->exec( $command );

		} else {
			echo "Permission denied";
		}

	}

	wp_die(); // this is required to terminate immediately and return a proper response
}

// Logs a process completion
add_action( 'wp_ajax_log_process', 'process_log_action_callback' );

function process_log_action_callback() {
	global $wpdb; // this is how you get access to the database

	$post_id = intval( $_POST['post_id'] );

	// Create post object
	$my_post = array(
	  'post_status'   => 'publish',
	  'post_type'	  => 'captcore_processlog',
	  'post_author'   => get_current_user_id()
	);

	// Insert the post into the database
	$process_log_id = wp_insert_post( $my_post );

	// Assign process to ACF relationship field
	update_field( "field_57f862ec5b466", $post_id, $process_log_id );

	// Copies checklist from process and stores into new process log
	$process_checklist = get_field('checklist', $post_id );
	update_field('field_58e9288d66c07', $process_checklist, $process_log_id);

    // Debug output
    $redirect_url = get_permalink($post_id);
    echo "{ \"redirect_url\" : \"$redirect_url\", \"process_id\": \"$process_log_id\" }";

	wp_die(); // this is required to terminate immediately and return a proper response
}

// Sets process log status to completed
add_action( 'wp_ajax_log_process_completed', 'log_process_completed_callback' );

function log_process_completed_callback() {
	global $wpdb; // this is how you get access to the database

	$post_id = intval( $_POST['post_id'] );

	date_default_timezone_set('America/New_York');

	// Sets process log status to completed
	update_field( "field_588bb7bd3cab6", "completed", $post_id );			// Sets status field to completed
	update_field( "field_588bb8423cab7", date('Y-m-d H:i:s'), $post_id );   // Sets completed field to current timestamp

	if (get_field("status", $post_id) == "completed") {
		echo "1";
	}

	wp_die(); // this is required to terminate immediately and return a proper response
}

// Fetch Backblaze link for snapshot and sends email
add_action( 'wp_ajax_snapshot_email', 'snapshot_email_action_callback' );

function snapshot_email_action_callback() {
	global $wpdb; // this is how you get access to the database

	// Variables from JS request
	$snapshot_id = intval( $_POST['snapshot_id'] );
	anchor_download_snapshot_email( $snapshot_id );

	wp_die(); // this is required to terminate immediately and return a proper response
}

function anchor_download_snapshot_email( $snapshot_id ) {
	$email =  get_field('email',$snapshot_id);
	$name = get_field('name',$snapshot_id);
	$website = get_field('website',$snapshot_id);
	$website_id = $website[0];
	$domain = get_the_title($website_id);

	// Get new auth from B2
	$account_id = CAPTAINCORE_B2_ACCOUNT_ID; // Obtained from your B2 account page
	$application_key = CAPTAINCORE_B2_ACCOUNT_KEY; // Obtained from your B2 account page
	$credentials = base64_encode($account_id . ":" . $application_key);
	$url = "https://api.backblazeb2.com/b2api/v1/b2_authorize_account";

	$session = curl_init($url);

	// Add headers
	$headers = array();
	$headers[] = "Accept: application/json";
	$headers[] = "Authorization: Basic " . $credentials;
	curl_setopt($session, CURLOPT_HTTPHEADER, $headers);  // Add headers

	curl_setopt($session, CURLOPT_HTTPGET, true);  // HTTP GET
	curl_setopt($session, CURLOPT_RETURNTRANSFER, true); // Receive server response
	$server_output = curl_exec($session);
	curl_close ($session);
	$output = json_decode($server_output);

	// Variables for Backblaze
	$api_url = "https://api001.backblazeb2.com"; // From b2_authorize_account call
	$auth_token = $output->authorizationToken; // From b2_authorize_account call
	$bucket_id = CAPTAINCORE_B2_BUCKET_ID; // The file name prefix of files the download authorization will allow
	$valid_duration = 604800; // The number of seconds the authorization is valid for
	$file_name_prefix = "Snapshots/". $domain; // The file name prefix of files the download authorization will allow

	$session = curl_init($api_url .  "/b2api/v1/b2_get_download_authorization");

	// Add post fields
	$data = array("bucketId" => $bucket_id,
	              "validDurationInSeconds" => $valid_duration,
	              "fileNamePrefix" => $file_name_prefix);
	$post_fields = json_encode($data);
	curl_setopt($session, CURLOPT_POSTFIELDS, $post_fields);

	// Add headers
	$headers = array();
	$headers[] = "Authorization: " . $auth_token;
	curl_setopt($session, CURLOPT_HTTPHEADER, $headers);

	curl_setopt($session, CURLOPT_POST, true); // HTTP POST
	curl_setopt($session, CURLOPT_RETURNTRANSFER, true);  // Receive server response
	$server_output = curl_exec($session); // Let's do this!
	curl_close ($session); // Clean up
	// echo ($server_output); // Tell me about the rabbits, George!

	$server_output = json_decode($server_output);

	$auth = $server_output->authorizationToken;

	$url = "https://f001.backblazeb2.com/file/AnchorHostBackup/Snapshots/$domain/$name?Authorization=" . $auth;

	echo $url;

	$to = $email;
	$subject = "Anchor Hosting - Snapshot #$snapshot_id";
	$body = 'Snapshot #'.$snapshot_id.' for '.$domain.'. Expires after 1 week.<br /><br /><a href="'.$url.'">Download Snapshot</a>';
	$headers = array('Content-Type: text/html; charset=UTF-8');

	wp_mail( $to, $subject, $body, $headers );
}

// Add reports to customers
add_action('admin_menu' , 'anchor_custom_pages');

function anchor_custom_pages() {
    add_submenu_page('captaincore', 'Customers Report', 'Reports', 'manage_options', "anchor_report", 'anchor_customer_report_callback');
    add_submenu_page( null, 'Partners Report', 'Partners', 'manage_options', "anchor_partner", 'anchor_partner_report_callback');
    add_submenu_page( null, 'Installs', 'Installs', 'manage_options', "anchor_installs", 'anchor_installs_report_callback');
		add_submenu_page( null, 'Customers Timeline', 'Timeline', 'manage_options', "anchor_timeline", 'anchor_timeline_report_callback');
		add_submenu_page( null, 'KPI', 'KPI', 'manage_options', "anchor_kpi", 'anchor_kpi_report_callback');
		add_submenu_page( null, 'Quicksaves', 'Quicksaves', 'manage_options', "anchor_quicksaves", 'anchor_quicksaves_report_callback');
}

function anchor_customer_report_callback() {
	// Loads the Customer Report template
	require_once(dirname(__FILE__) . "/inc/admin-customer-report.php");
}

function anchor_partner_report_callback() {
	// Loads the Customer Report template
	require_once(dirname(__FILE__) . "/inc/admin-partner-report.php");
}

function anchor_installs_report_callback() {
	// Loads the Customer Report template
	require_once(dirname(__FILE__) . "/inc/admin-installs-report.php");
}

function anchor_timeline_report_callback() {
	// Loads the Customer Report template
	require_once(dirname(__FILE__) . "/inc/admin-timeline-report.php");
}

function anchor_kpi_report_callback() {
	// Loads the Customer Report template
	require_once(dirname(__FILE__) . "/inc/admin-kpi-report.php");
}

function anchor_quicksaves_report_callback() {
	// Loads the Quicksaves Report template
	require_once(dirname(__FILE__) . "/inc/admin-report-quicksaves.php");
}

// allow SVGs
function cc_mime_types($mimes) {
  $mimes['svg'] = 'image/svg+xml';
  return $mimes;
}
add_filter('upload_mimes', 'cc_mime_types');

// After payment received, connect up the Stripe info into the subscription.
function anchor_woocommerce_payment_complete( $order_id ) {

	$customer_id = get_field('_customer_user', $order_id);
	$payment_tokens = WC_Payment_Tokens::get_customer_tokens( $customer_id );

	foreach ( $payment_tokens as $payment_token ) {
		$token_id = $payment_token->get_token();
	}

	$payment_cus_id = get_field('_stripe_customer_id', "user_" . $customer_id);
	$payment_card_id = $token_id;

	// Find parent subscription id
	if (wcs_order_contains_subscription($order_id)) {
		$subscription = wcs_get_subscriptions_for_order( $order_id );
		$subscription_id = key($subscription);
	} else {
		$subscription_id = get_field('_subscription_renewal', $order_id);
	}

	update_post_meta( $subscription_id, '_stripe_customer_id', $payment_cus_id );
	update_post_meta( $subscription_id, '_stripe_card_id', $payment_card_id  );
	update_post_meta( $subscription_id, '_requires_manual_renewal', "false" );
	update_post_meta( $subscription_id, '_payment_method', "stripe" );
	update_post_meta( $subscription_id, '_payment_method_title', "Credit card" );

}
add_action( 'woocommerce_payment_complete', 'anchor_woocommerce_payment_complete' );


// Custom payment link for speedy checkout
function anchor_get_checkout_payment_url($payment_url) {

	// Current $payment_url is
	// https://anchor.host/checkout/order-pay/1918?pay_for_order=true&key=wc_order_576c79296c346&subscription_renewal=true

	// Replace with
	// https://anchor.host/checkout-express/1918/?pay_for_order=true&key=wc_order_576c79296c346&subscription_renewal=true

	$home_url = esc_url( home_url( '/' ) );

	$new_payment_url = str_replace($home_url ."checkout/order-pay/", $home_url . "checkout-express/", $payment_url);

	return $new_payment_url;
}

// Checks subscription for additional emails
add_filter( 'woocommerce_email_recipient_customer_completed_renewal_order', 'woocommerce_email_customer_invoice_add_recipients', 10, 2 );
add_filter( 'woocommerce_email_recipient_customer_renewal_invoice', 'woocommerce_email_customer_invoice_add_recipients', 10, 2 );
add_filter( 'woocommerce_email_recipient_customer_invoice', 'woocommerce_email_customer_invoice_add_recipients', 10, 2 );

function woocommerce_email_customer_invoice_add_recipients( $recipient, $order ) {

	// Finds subscription for the order
	$subscription = wcs_get_subscriptions_for_order( $order, array( 'order_type' => array( 'parent', 'renewal' ) ) );

	if ( $subscription and array_values($subscription)[0] ) {
		// Find first subscription ID
		$subscription_id = array_values($subscription)[0]->id;
		// Check ACF field for additional emails
		$additional_emails = get_field('additional_emails', $subscription_id);

		if ($additional_emails) {
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

add_action('acf/input/admin_footer', 'my_acf_input_admin_footer');

add_filter( 'query_vars', function( $vars ){
    $vars[] = 'tag__in';
    $vars[] = 'tag__not_in';
    return $vars;
});

add_filter( 'rest_query_vars', 'test_query_vars' );
function test_query_vars( $vars ){
    $vars[] = 'captcore_website';
    $vars[] = 'global';
    return $vars;
}

add_action( 'pre_get_posts', 'test_pre_get_posts' );
function test_pre_get_posts( $query ) {

	global $wp;

	if (isset($wp->query_vars["rest_route"])) {
		$rest_route = $wp->query_vars["rest_route"];
	}

	if (isset($rest_route) and $rest_route == "/wp/v2/captcore_processlog") {

			// Filter only logs attached to processes in Growth, Maintenance and Support roles.
			$meta_query = array(
		        array(
		            'key' => 'website',
		            'value' => '"' . $_GET["website"] . '"',
		            'compare' => 'like'
		        ),
		        array(
		            'key' => 'public',
		            'value' => '1',
		            'compare' => 'like'
		        )
		    );

			$query->set( 'meta_query', $meta_query );

			return $query;

	} else {

		if ( isset($_GET["website"]) ) {

			$meta_query = array(
		        array(
		            'key' => 'captcore_website',
		            'value' => '"' . $_GET["website"] . '"',
		            'compare' => 'like'
		        )
		    );

			$query->set( 'meta_query', $meta_query );

			return $query;
		}
		if ( isset($_GET["global"]) ) {

			$meta_query = array(
		        array(
		            'key' => 'global',
		            'value' => '1',
		            'compare' => '='
		        )
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

  if ( ! $template_path ) $template_path = $woocommerce->template_url;

  $plugin_path  = captaincore_plugin_path() . '/woocommerce/';

  // Look within passed path within the theme - this is priority
  $template = locate_template(

    array(
      $template_path . $template_name,
      $template_name
    )
  );

  // Modification: Get the template from this plugin, if it exists
  if ( ! $template && file_exists( $plugin_path . $template_name ) )
    $template = $plugin_path . $template_name;

  // Use default template
  if ( ! $template )
    $template = $_template;

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
			array( $this, 'view_project_template')
		);

		// Add your templates to this array.
		$this->templates = array(
			'templates/page-captaincore-api.php' => 'CaptainCore API',
			'templates/page-company-handbook.php' => 'Company Handbook',
			'templates/page-activities.php' => 'Activities',
			'templates/page-checkout-express.php' => 'Checkout Express',
			'templates/page-websites.php' => 'Website Recommendations'
		);

	}

	/**
	 * Adds our template to the page dropdown for v4.7+
	 *
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
		wp_cache_delete( $cache_key , 'themes');

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
		if ( ! isset( $this->templates[get_post_meta(
			$post->ID, '_wp_page_template', true
		)] ) ) {
			return $template;
		}

		$file = plugin_dir_path( __FILE__ ). get_post_meta(
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
add_filter('single_template', 'captaincore_custom_template');

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
function captaincore_human_filesize($size, $precision = 2) {
  $units = array('B','kB','MB','GB','TB','PB','EB','ZB','YB');
  $step = 1024;
  $i = 0;
  while (($size / $step) > 0.9) {
      $size = $size / $step;
      $i++;
  }
  return round($size, $precision).$units[$i];
}
