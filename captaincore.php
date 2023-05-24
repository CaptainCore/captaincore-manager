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
 * Plugin Name:       CaptainCore Manager
 * Plugin URI:        https://captaincore.io
 * Description:       WordPress management toolkit for geeky maintenance professionals.
 * Version:           0.17.0
 * Author:            Austin Ginder
 * Author URI:        https://austinginder.com
 * License:           MIT License
 * License URI:       https://opensource.org/licenses/MIT
 * Text Domain:       captaincore
 * Domain Path:       /languages
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

function activate_captaincore() {
	require_once plugin_dir_path( __FILE__ ) . 'includes/class-captaincore-activator.php';
	Captaincore_Activator::activate();
}

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

require plugin_dir_path( __FILE__ ) . 'vendor/autoload.php';
require 'includes/register-custom-fields.php';
require 'includes/constellix-api/constellix-api.php';
require 'includes/missive-api/missive-api.php';
require 'includes/fathom-api/fathom-api.php';
require 'includes/mailgun-api.php';
require 'includes/process-functions.php';
require 'includes/bulk-actions.php';
require 'includes/Parsedown.php';

function captaincore_cron_run() {
    ( new CaptainCore\Accounts )->process_renewals();
}
add_action( 'captaincore_cron', 'captaincore_cron_run' );

function captaincore_failed_notify( $order_id, $old_status, $new_status ){
	echo "Woocommerce  $order_id, $old_status, $new_status ";
    if ( $new_status == 'failed' and $old_status != "failed" ){
		$order      = wc_get_order( $order_id );
		$account_id = $order->get_meta( "captaincore_account_id" );
		( new CaptainCore\Account( $account_id, true ) )->failed_notify();
    }
}
add_action( 'woocommerce_order_status_changed', 'captaincore_failed_notify', 10, 3);

function captaincore_disable_gutenberg( $can_edit, $post_type ) {
	$disabled_post_types = [ 'captcore_website', 'captcore_domain', 'captcore_customer', 'captcore_changelog' ];
	if ( in_array( $post_type, $disabled_post_types ) ) {
		return false;
	}
	return $can_edit;
}
add_filter( 'use_block_editor_for_post_type', 'captaincore_disable_gutenberg', 10, 2 );

// Register Custom Post Type
function contact_post_type() {

	$labels       = [
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
	];
	$capabilities = [
		'edit_post'          => 'website_edit_post',
		'read_post'          => 'website_read_post',
		'delete_post'        => 'website_delete_post',
		'edit_posts'         => 'website_edit_posts',
		'edit_others_posts'  => 'website_edit_others_posts',
		'publish_posts'      => 'website_publish_posts',
		'read_private_posts' => 'website_read_private_posts',
	];
	$args         = [
		'label'               => __( 'Contact', 'captaincore' ),
		'description'         => __( 'Contact Description', 'captaincore' ),
		'labels'              => $labels,
		'supports'            => [ 'author', 'revisions' ],
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
	];
	register_post_type( 'captcore_contact', $args );

}
add_action( 'init', 'contact_post_type', 0 );

// Register Custom Post Type
function customer_post_type() {

	$labels       = [
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
	];
	$capabilities = [
		'edit_post'          => 'website_edit_post',
		'read_post'          => 'website_read_post',
		'delete_post'        => 'website_delete_post',
		'edit_posts'         => 'website_edit_posts',
		'edit_others_posts'  => 'website_edit_others_posts',
		'publish_posts'      => 'website_publish_posts',
		'read_private_posts' => 'website_read_private_posts',
	];
	$args         = [
		'label'               => __( 'Customer', 'captaincore' ),
		'description'         => __( 'Customer Description', 'captaincore' ),
		'labels'              => $labels,
		'supports'            => [],
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
	];
	register_post_type( 'captcore_customer', $args );

}
add_action( 'init', 'customer_post_type', 0 );

// Register Custom Post Type
function website_post_type() {

	$labels       = [
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
	];
	$capabilities = [
		'edit_post'          => 'website_edit_post',
		'read_post'          => 'website_read_post',
		'delete_post'        => 'website_delete_post',
		'edit_posts'         => 'website_edit_posts',
		'edit_others_posts'  => 'website_edit_others_posts',
		'publish_posts'      => 'website_publish_posts',
		'read_private_posts' => 'website_read_private_posts',
	];
	$args         = [
		'label'               => __( 'website', 'captaincore' ),
		'description'         => __( 'Website information pages', 'captaincore' ),
		'labels'              => $labels,
		'supports'            => [],
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
	];
	register_post_type( 'captcore_website', $args );
}

// Hook into the 'init' action
add_action( 'init', 'website_post_type', 0 );

// Register Custom Post Type
function domain_post_type() {

	$labels       = [
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
	];
	$capabilities = [
		'edit_post'          => 'website_edit_post',
		'read_post'          => 'website_read_post',
		'delete_post'        => 'website_delete_post',
		'edit_posts'         => 'website_edit_posts',
		'edit_others_posts'  => 'website_edit_others_posts',
		'publish_posts'      => 'website_publish_posts',
		'read_private_posts' => 'website_read_private_posts',
	];
	$args         = [
		'label'               => __( 'Domain', 'captaincore' ),
		'description'         => __( 'Domain Description', 'captaincore' ),
		'labels'              => $labels,
		'supports'            => [ 'title', 'editor' ],
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
	];
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

	$labels       = [
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
	];
	$capabilities = [
		'edit_post'          => 'website_edit_post',
		'read_post'          => 'website_read_post',
		'delete_post'        => 'website_delete_post',
		'edit_posts'         => 'website_edit_posts',
		'edit_others_posts'  => 'website_edit_others_posts',
		'publish_posts'      => 'website_publish_posts',
		'read_private_posts' => 'website_read_private_posts',
	];
	$args         = [
		'label'               => __( 'changelog', 'captaincore' ),
		'description'         => __( 'Changelog information pages', 'captaincore' ),
		'labels'              => $labels,
		'supports'            => [],
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
	];
	register_post_type( 'captcore_changelog', $args );

}

// Hook into the 'init' action
add_action( 'init', 'changelog_post_type', 0 );

// Register Custom Post Type
function process_post_type() {

	$labels       = [
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
	];
	$capabilities = [
		'edit_post'          => 'website_edit_post',
		'read_post'          => 'website_read_post',
		'delete_post'        => 'website_delete_post',
		'edit_posts'         => 'website_edit_posts',
		'edit_others_posts'  => 'website_edit_others_posts',
		'publish_posts'      => 'website_publish_posts',
		'read_private_posts' => 'website_read_private_posts',
	];
	$args         = [
		'label'               => __( 'Process', 'captaincore' ),
		'description'         => __( 'Process Description', 'captaincore' ),
		'labels'              => $labels,
		'supports'            => [ 'title', 'editor', 'author', 'thumbnail', 'revisions' ],
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
	];
	register_post_type( 'captcore_process', $args );

}
add_action( 'init', 'process_post_type', 0 );

// Register Custom Post Type
function process_log_post_type() {

	$labels       = [
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
	];
	$capabilities = [
		'edit_post'          => 'website_edit_post',
		'read_post'          => 'website_read_post',
		'delete_post'        => 'website_delete_post',
		'edit_posts'         => 'website_edit_posts',
		'edit_others_posts'  => 'website_edit_others_posts',
		'publish_posts'      => 'website_publish_posts',
		'read_private_posts' => 'website_read_private_posts',
	];
	$args         = [
		'label'               => __( 'Process Log', 'captaincore' ),
		'description'         => __( 'Process Log Description', 'captaincore' ),
		'labels'              => $labels,
		'supports'            => [ 'author', 'thumbnail', 'revisions' ],
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
	];
	register_post_type( 'captcore_processlog', $args );

}
add_action( 'init', 'process_log_post_type', 0 );

// Register Custom Post Type
function process_item_log_post_type() {

	$labels       = [
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
	];
	$capabilities = [
		'edit_post'          => 'website_edit_post',
		'read_post'          => 'website_read_post',
		'delete_post'        => 'website_delete_post',
		'edit_posts'         => 'website_edit_posts',
		'edit_others_posts'  => 'website_edit_others_posts',
		'publish_posts'      => 'website_publish_posts',
		'read_private_posts' => 'website_read_private_posts',
	];
	$args         = [
		'label'               => __( 'Process Item Log', 'captaincore' ),
		'description'         => __( 'Process Item Log Description', 'captaincore' ),
		'labels'              => $labels,
		'supports'            => [ 'author', 'thumbnail', 'revisions' ],
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
	];
	register_post_type( 'captcore_processitem', $args );

}
// add_action( 'init', 'process_item_log_post_type', 0 );

// Register Custom Post Type
function snapshot_post_type() {

	$labels       = [
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
	];
	$capabilities = [
		'edit_post'          => 'website_edit_post',
		'read_post'          => 'website_read_post',
		'delete_post'        => 'website_delete_post',
		'edit_posts'         => 'website_edit_posts',
		'edit_others_posts'  => 'website_edit_others_posts',
		'publish_posts'      => 'website_publish_posts',
		'read_private_posts' => 'website_read_private_posts',
	];
	$args         = [
		'label'               => __( 'Snapshot', 'captaincore' ),
		'description'         => __( 'Snapshot Description', 'captaincore' ),
		'labels'              => $labels,
		'supports'            => [],
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
	];
	register_post_type( 'captcore_snapshot', $args );

}
add_action( 'init', 'snapshot_post_type', 0 );

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

	$paid_by_me = [];
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
		$found_user_ids = [];

		// Search users
		$search_users = get_users( [ 'search' => '*' . $search_term . '*' ] );

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

function acf_load_color_field_choices( $field ) {

	global $woocommerce;

	// reset choices
	$field['choices'] = [];

	// Args
	$args = [
		'status'         => [ 'draft', 'pending', 'private', 'publish' ],
		'type'           => array_merge( array_keys( wc_get_product_types() ) ),
		'parent'         => null,
		'sku'            => '',
		'category'       => [],
		'tag'            => [],
		'limit'          => get_option( 'posts_per_page' ),
		'offset'         => null,
		'page'           => 1,
		'include'        => [],
		'exclude'        => [],
		'orderby'        => 'date',
		'order'          => 'DESC',
		'return'         => 'objects',
		'paginate'       => false,
		'shipping_class' => [],
	];

	// List all products
	$products = wc_get_products( $args );

	$choices = [];
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
			update_field( 'status', "active", $customer_post_id );
			
			// Link website to customer
			update_field( 'customer', [ $customer_post_id ], $post_id );

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

		$db_environments = new CaptainCore\Environments();

			if ( $websites ) :
				foreach ( $websites as $website ) :

				$environments = $db_environments->fetch_environments( $website->ID );

				$storage = $environments[0]->storage;
				$visits  = $environments[0]->visits;

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

		$account = new CaptainCore\Account( $post_id );
		$account->calculate_totals();
	
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

	$data = [
		'profile_image'          => get_field( 'profile_image', 'option' ),
		'description'            => get_field( 'description', 'option' ),
		'contact_info'           => get_field( 'contact_info', 'option' ),
		'business_name'          => get_field( 'business_name', 'option' ),
		'business_tagline'       => get_field( 'business_tagline', 'option' ),
		'business_link'          => get_field( 'business_link', 'option' ),
		'business_logo'          => get_field( 'business_logo', 'option' ),
		'hosting_dashboard_link' => get_field( 'hosting_dashboard_link', 'option' ),
		'preinstall_plugins'     => get_field( 'preinstall_plugins', 'option' ),
	];

	return $data;

}

function captaincore_missive_func( WP_REST_Request $request ) {

	$key        = $request->get_header('X-Hook-Signature');
	
	if ( empty( $key ) ) {
		return "Bad Request";
	}

	$computed_signature = 'sha256=' . hash_hmac( "sha256", $request->get_body(), CAPTAINCORE_MISSIVE_API );
	if ( ! hash_equals( $computed_signature, $key ) ) {
		return "Bad Request";
	}

	$errors     = [];
	$missive    = json_decode( $request->get_body() );
	$message_id = $missive->latest_message->id;
	$message    = missive_api_get( "messages/$message_id")->messages->body;

	preg_match('/TXT record for (.+) in MyKinsta/', $message, $matches );
	$domain     = $matches[1];
	$response   = ( new CaptainCore\Domains )->add_verification_record( $domain );
	$errors     = implode( ", ", $errors );

	missive_api_post( "posts", [ "posts" => [ 
		"conversation"  => $missive->conversation->id,
		"notification"  => [ "title" => "", "body" => "" ],
		"username"      => "CaptainCore Bot", 
		"username_icon" => "https://captaincore.io/logo.png",
		"markdown"      => $response
	] ] );

	return;
}

function captaincore_api_func( WP_REST_Request $request ) {

	$post          = json_decode( file_get_contents( 'php://input' ) );
	$archive       = $post->archive;
	$command       = $post->command;
	$environment   = $post->environment;
	$storage       = $post->storage;
	$visits        = $post->visits;
	$email         = $post->email;
	$server        = $post->server;
	$core          = $post->core;
	$plugins       = $post->plugins;
	$themes        = $post->themes;
	$users         = $post->users;
	$fathom        = $post->fathom;
	$home_url      = $post->home_url;
	$subsite_count = $post->subsite_count;
	$git_status    = trim( base64_decode( $post->git_status ) );
	$token_key     = $post->token_key;
	$data          = $post->data;
	$site_id       = $post->site_id;
	$user_id       = $post->user_id;
	$notes         = $post->notes;

	// Error if token not valid
	if ( $post->token != CAPTAINCORE_CLI_TOKEN ) {
		// Create the response object
		return new WP_Error( 'token_invalid', 'Invalid Token', [ 'status' => 404 ] );
	}

	// Error if site not valid
	$current_site = ( new CaptainCore\Sites )->get( $site_id );
	if ( $current_site == "" && $site_id != "" && $command != "default-get" && $command != "configuration-get" ) {
		return new WP_Error( 'command_invalid', 'Invalid Command', [ 'status' => 404 ] );
	}

	$site_name      = $current_site->site;
	$domain_name    = $current_site->name;
	$environment_id = ( new CaptainCore\Site( $site_id ) )->fetch_environment_id( $environment );

	// Copy site
	if ( $command == 'copy' and $email ) {

		$site_source      = get_the_title( $post->site_source_id );
		$site_destination = get_the_title( $post->site_destination_id );
		$business_name    = get_field('business_name', 'option');

		// Send out completed email notice
		$to      = $email;
		$subject = "$business_name - Copy site ($site_source) to ($site_destination) completed";
		$body    = "Completed copying $site_source to $site_destination.<br /><br /><a href=\"http://$site_destination\">$site_destination</a>";
		$headers = [ 'Content-Type: text/html; charset=UTF-8' ];

		wp_mail( $to, $subject, $body, $headers );

		echo 'copy-site email sent';

	}

	// Production deploy to staging
	if ( $command == 'production-to-staging' and $email ) {

		$business_name = get_field('business_name', 'option');
		$domain_name = get_the_title( $site_id );
		$db          = new CaptainCore\Site( $site_id );
		$site        = $db->get();
		$link        = $site->environments[1]["link"];

		// Send out completed email notice
		$to      = $email;
		$subject = "$business_name - Deploy to Staging ($domain_name)";
		$body    = 'Deploy to staging completed for ' . $domain_name . '.<br /><br /><a href="' . $link . '">' . $link . '</a>';
		$headers = [ 'Content-Type: text/html; charset=UTF-8' ];

		wp_mail( $to, $subject, $body, $headers );

		echo 'production-to-staging email sent';

	}

	// Kinsta staging deploy to production
	if ( $command == 'staging-to-production' and $email ) {

		$business_name = get_field('business_name', 'option');
		$domain_name = get_the_title( $site_id );
		$db          = new CaptainCore\Site( $site_id );
		$site        = $db->get();
		$link        = $site->environments[0]["link"];

		// Send out completed email notice
		$to      = $email;
		$subject = "$business_name - Deploy to Production ($domain_name)";
		$body    = 'Deploy to production completed for ' . $domain_name . '.<br /><br /><a href="' . $link . '">' . $link . '</a>';
		$headers =  [ 'Content-Type: text/html; charset=UTF-8' ];

		wp_mail( $to, $subject, $body, $headers );

		echo 'staging-to-production email sent';

	}

	// Generate a new snapshot.
	if ( $command == 'snapshot-add' ) {

		$snapshot_check = ( new CaptainCore\Snapshots )->get( $post->data->snapshot_id );
		// Insert new snapshot
		if ( empty( $snapshot_check ) ) {
			( new CaptainCore\Snapshots )->insert( (array) $post->data );
		} else {
			// Update existing quicksave
			( new CaptainCore\Snapshots )->update( (array) $post->data, [ "snapshot_id" => $post->data->snapshot_id ] );
		}
	
		$response = [
			"response"  => "Snapshot added for $site_id",
			"snapshot" => $post->data,
		];

		// Send out snapshot email
		captaincore_download_snapshot_email( $post->data->snapshot_id );

	}

	// Load Token Key
	if ( $command == 'token' and isset( $token_key ) ) {
		( new CaptainCore\Sites )->update( [ "token" => $token_key ], [ "site_id" => $site_id ] );
		echo "Adding token key. \n";
	}

	// Update Fathom
	if ( $command == 'update-fathom' and ! empty( $post->data ) ) {
		
		$current_environment = ( new CaptainCore\Environments )->get( $post->data->environment_id );
		$environment         = strtolower( $current_environment->environment );
		$details             = ( isset( $current_environment->details ) ? json_decode( $current_environment->details ) : (object) [] );
		$details->fathom     = $post->data->fathom;
		( new CaptainCore\Environments )->update( [ 
			 "details"    => json_encode( $details ),
			], [ "environment_id" => $post->data->environment_id ] );

		$response = [
			"response"        => "Completed update-fathom for $site_id",
			"environment"     => $post->data,
		];

	}

	if ( $command == 'update-site' and ! empty( $post->data ) ) {

		$current_site = ( new CaptainCore\Sites )->get( $post->data->site_id );
		( new CaptainCore\Sites )->update( (array) $post->data, [ "site_id" => $post->data->site_id ] );

		$response = [
			"response" => "Completed update-site for $site_id",
			"site"     => $post->data,
		];
		
	}


	if ( $command == 'update-environment' and ! empty( $post->data ) ) {
		
		$current_environment = ( new CaptainCore\Environments )->get( $post->data->environment_id );
		( new CaptainCore\Environments )->update( (array) $post->data, [ "environment_id" => $post->data->environment_id ] );

		$response = [
			"response"        => "Completed update-environment for $site_id",
			"environment"     => $post->data,
		];
		
		// Mark Site as updated
		( new CaptainCore\Sites )->update( [ "updated_at" => $post->data->updated_at ], [ "site_id" => $site_id ] );

	}

	// Sync site data
	if ( $command == 'sync-data' and ! empty( $post->data ) ) {
		
		$current_environment = ( new CaptainCore\Environments )->get( $post->data->environment_id );
		$environment         = strtolower( $current_environment->environment );
		$upload_dir          = wp_upload_dir();
		$screenshot_check    = $upload_dir['basedir'] . "/screenshots/{$site_name}_{$site_id}/$environment/screenshot-800.png";
		if ( file_exists( $screenshot_check ) ) {
			$environment_update['screenshot'] = true;
		} else {
			$environment_update['screenshot'] = false;
		}
		( new CaptainCore\Environments )->update( (array) $post->data, [ "environment_id" => $post->data->environment_id ] );

		$response = [
			"response"        => "Completed sync-data for $site_id",
			"environment"     => $post->data,
		];

		$current_site = ( new CaptainCore\Sites )->get( $site_id );
		$details      = json_decode( $current_site->details );

		unset( $details->connection_errors );

		if ( $current_environment->environment == "Production" ) {
			$details->core = $post->data->core;
		}

		// Mark Site as updated
		( new CaptainCore\Sites )->update( [ "updated_at" => $post->data->updated_at, "details" => json_encode( $details ) ], [ "site_id" => $site_id ] );

	}

	// Imports update log
	if ( $command == 'update-log-add' ) {

		$update_log_check = ( new CaptainCore\UpdateLogs )->get( $post->data->log_id );
		// Insert new quicksave
		if ( empty( $update_log_check ) ) {
			( new CaptainCore\UpdateLogs )->insert( (array) $post->data );
		} else {
			// Update existing quicksave
			( new CaptainCore\UpdateLogs )->update( (array) $post->data, [ "log_id" => $post->data->log_id ] );
		}
	
		$response = [
			"response"   => "Update log added for $site_id",
			"update_log" => $post->data,
		];
	}

	// Add capture
	if ( $command == 'new-capture' ) {

		$environment_id = ( new CaptainCore\Site( $site_id ) )->fetch_environment_id( $environment );
		$captures       = new CaptainCore\Captures();
		$capture_lookup = $captures->where( [ "site_id" => $site_id, "environment_id" => $environment_id ] );
		if ( count( $capture_lookup ) > 0 ) {
			$current_capture_pages = json_decode( $capture_lookup[0]->pages );
		}

		$git_commit_short = substr( $data->git_commit, 0, 7 );
		$image_ending     = "_{$data->created_at}_{$git_commit_short}.jpg";
		$capture_pages    = explode( ",", $data->capture_pages );
		$captured_pages   = explode( ",", $data->captured_pages );
		$pages = [];
		foreach( $capture_pages as $page ) {
			$page_name = str_replace( "/", "#", $page );

			// Add page with new screenshot
			if ( in_array( $page, $captured_pages ) ) {
				$pages[] = [
					"name"  => $page,
					"image" => "{$page_name}{$image_ending}",
				];
				continue;
			}

			// Lookup current image from DB
			$current_image = "";
			foreach($current_capture_pages as $current_capture_page) {
				if ($page == $current_capture_page->name) {
					$current_image = $current_capture_page->image;
					break;
				}
			}

			// Otherwise add image to current screenshot
			$pages[] = [
				"name"  => $page,
				"image" => $current_image,
			];
		}

		// Format for mysql timestamp format. Changes "1530817828" to "2018-06-20 09:15:20"
		$epoch      = $data->created_at;
		$created_at = new DateTime("@$epoch");  // convert UNIX timestamp to PHP DateTime
		$created_at = $created_at->format('Y-m-d H:i:s'); // output = 2017-01-01 00:00:00

		$new_capture = [
			'site_id'        => $site_id,
			'environment_id' => $environment_id,
			'created_at'     => $created_at,
			'git_commit'     => $data->git_commit,
			'pages'          => json_encode( $pages ),
		];

		( new CaptainCore\Captures )->insert( $new_capture );

		// Update pointer to new thumbnails for site
		if ( $environment == "production" ) {
			$site                     = ( new CaptainCore\Sites )->get( $site_id );
			$details                  = json_decode( $site->details );
			$details->screenshot_base = "{$data->created_at}_${git_commit_short}";
			( new CaptainCore\Sites )->update( [ "screenshot" => true, "details" => json_encode( $details ) ], [ "site_id" => $site_id ] );
		}
		// Update pointer to new thumbnails for environment
		$environment              = ( new CaptainCore\Environments )->get( $environment_id );
		$details                  = ( isset( $environment->details ) ? json_decode( $environment->details ) : (object) [] );
		$details->screenshot_base = "{$data->created_at}_${git_commit_short}";
		( new CaptainCore\Environments )->update( [ "screenshot" => true, "details" => json_encode( $details ) ], [ "environment_id" => $environment_id ] );

	}

	if ( $command == 'site-get-raw' ) {
		$site = new CaptainCore\Site( $post->site_id );
		$response = [
			"response" => "Fetching site {$post->site_id}",
			"site"     => $site->get_raw(),
		];
	}

	if ( $command == 'site-delete' ) {
		( new CaptainCore\Sites )->delete( $post->site_id );
		$response = [
			"response" => "Delete site {$post->site_id}"
		];
	}

	if ( $command == 'account-get-raw' ) {
		$account = new CaptainCore\Account( $post->account_id, true );
		$response = [
			"response" => "Fetching account {$post->account_id}",
			"account"  => $account->get_raw(),
		];
	}

	if ( $command == 'configuration-get' ) {
		$configurations = ( new CaptainCore\Configurations )->get();
		$response       = [
			"response"       => "Fetching configurations",
			"configurations" => $configurations,
		];
	}

	if ( $command == 'default-get' ) {
		$defaults = ( new CaptainCore\Defaults )->get();
		$response = [
			"response" => "Fetching global defaults",
			"defaults" => $defaults,
		];
	}

	// Updates visits and storage usage
	if ( $command == 'usage-update' ) {

		$current_environment = ( new CaptainCore\Environments )->get( $post->data->environment_id );
		( new CaptainCore\Environments )->update( (array) $post->data, [ "environment_id" => $post->data->environment_id ] );

		$response = [
			"response"    => "Completed usage-update for $site_id",
			"environment" => $post->data,
		];

		( new CaptainCore\Site( $current_environment->site_id ) )->update_details();

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

function captaincore_accounts_func( $request ) {
	return ( new CaptainCore\Accounts )->list();
}

function captaincore_configurations_func( $request ) {
	return ( new CaptainCore\Configurations )->get();
}

function captaincore_configurations_update_func( $request ) {
	$configurations = $request->get_param( "configurations" );
	return ( new CaptainCore\Configurations )->update( $configurations );
}

function captaincore_subscriptions_func( $request ) {
	return ( new CaptainCore\User )->subscriptions();
}

function captaincore_upcoming_subscriptions_func( $request ) {
	return ( new CaptainCore\User )->upcoming_subscriptions();
}

function captaincore_billing_func( $request ) {
	return ( new CaptainCore\User )->billing();
}

function captaincore_provider_new_func( $request ) {
	if ( ! ( new CaptainCore\User )->is_admin() ){
		return new WP_Error( 'token_invalid', "Invalid Token", [ 'status' => 403 ] );
	}
	$provider = $request->get_param( "provider" );
	return ( new CaptainCore\Provider )->create( $provider );
}

function captaincore_provider_update_func( $request ) {
	if ( ! ( new CaptainCore\User )->is_admin() ){
		return new WP_Error( 'token_invalid', "Invalid Token", [ 'status' => 403 ] );
	}
	$provider_id = $request->get_param( "id" );
	$provider    = $request->get_param( "provider" );
	unset( $provider["provider_id"] );
	unset( $provider["created_at"] );
	$provider["updated_at"]  = date("Y-m-d H:i:s");
	$provider["credentials"] = json_encode( $provider["credentials"] );
	return ( new CaptainCore\Providers )->update( $provider, [ "provider_id" => $provider_id ] );
}

function captaincore_provider_delete_func( $request ) {
	if ( ! ( new CaptainCore\User )->is_admin() ){
		return new WP_Error( 'token_invalid', "Invalid Token", [ 'status' => 403 ] );
	}
	$provider_id = $request->get_param( "id" );
	return ( new CaptainCore\Providers )->delete( $provider_id );
}

function captaincore_provider_func( $request ) {
	if ( ! ( new CaptainCore\User )->is_admin() ){
		return new WP_Error( 'token_invalid', "Invalid Token", [ 'status' => 403 ] );
	}
	return ( new CaptainCore\Provider )->all();
}

function captaincore_provider_verify_func( $request ) {
	if ( ! ( new CaptainCore\User )->is_admin() ){
		return new WP_Error( 'token_invalid', "Invalid Token", [ 'status' => 403 ] );
	}
	$provider = $request->get_param( "provider" );
	return ( new CaptainCore\Provider( $provider ) )->verify();
}

function captaincore_provider_themes_func( $request ) {
	if ( ! ( new CaptainCore\User )->role_check() ){
		return new WP_Error( 'token_invalid', "Invalid Token", [ 'status' => 403 ] );
	}
	$provider = "CaptainCore\Providers\\" . ucfirst( $request->get_param( "provider" ) );
	return $provider::themes();
}

function captaincore_provider_theme_download_func( $request ) {
	$theme_id = $request->get_param( "id" );
	if ( ! ( new CaptainCore\User )->role_check() ){
		return new WP_Error( 'token_invalid', "Invalid Token", [ 'status' => 403 ] );
	}
	$provider = "CaptainCore\Providers\\" . ucfirst( $request->get_param( "provider" ) );
	return $provider::download_theme( $theme_id );
}

function captaincore_provider_plugin_download_func( $request ) {
	$plugin_id = $request->get_param( "id" );
	if ( ! ( new CaptainCore\User )->role_check() ){
		return new WP_Error( 'token_invalid', "Invalid Token", [ 'status' => 403 ] );
	}
	$provider = "CaptainCore\Providers\\" . ucfirst( $request->get_param( "provider" ) );
	return $provider::download_plugin( $plugin_id );
}

function captaincore_provider_plugins_func( $request ) {
	if ( ! ( new CaptainCore\User )->role_check() ){
		return new WP_Error( 'token_invalid', "Invalid Token", [ 'status' => 403 ] );
	}
	$provider = "CaptainCore\Providers\\" . ucfirst( $request->get_param( "provider" ) );
	return $provider::plugins();
}

function captaincore_provider_connect_func( $request ) {
	if ( ! ( new CaptainCore\User )->is_admin() ){
		return new WP_Error( 'token_invalid', "Invalid Token", [ 'status' => 403 ] );
	}
	$provider = $request->get_param( "provider" );
	$token    = $request['token'];
	return ( new CaptainCore\Provider( $provider ) )->update_token( $token );
}

function captaincore_provider_new_site_func( $request ) {
	if ( ! ( new CaptainCore\User )->is_admin() ){
		return new WP_Error( 'token_invalid', "Invalid Token", [ 'status' => 403 ] );
	}
	$provider = $request->get_param( "provider" );
	$site     = $request['site'];
	return ( new CaptainCore\Provider( $provider ) )->new_site( $site );
}

function captaincore_provider_actions_check_func( $request ) {
	if ( ! ( new CaptainCore\User )->is_admin() ){
		return new WP_Error( 'token_invalid', "Invalid Token", [ 'status' => 403 ] );
	}
	return ( new CaptainCore\ProviderAction )->check();
}

function captaincore_provider_actions_run_func( $request ) {
	if ( ! ( new CaptainCore\User )->is_admin() ){
		return new WP_Error( 'token_invalid', "Invalid Token", [ 'status' => 403 ] );
	}
	return ( new CaptainCore\ProviderAction( $request['id'] ) )->run();
}

function captaincore_provider_actions_func( $request ) {
	if ( ! ( new CaptainCore\User )->is_admin() ){
		return new WP_Error( 'token_invalid', "Invalid Token", [ 'status' => 403 ] );
	}
	return ( new CaptainCore\ProviderAction )->active();
}

function captaincore_sites_func( $request ) {
	return ( new CaptainCore\Sites )->list();
}

function captaincore_site_func( $request ) {

	$site_id = $request['id'];

	if ( ! captaincore_verify_permissions( $site_id ) ) {
		return new WP_Error( 'token_invalid', 'Invalid Token', [ 'status' => 403 ] );
	}

	$site = new CaptainCore\Site( $site_id );
	return $site->get();

}

function captaincore_domain_func( $request ) {
	$domain_id = $request['id'];
	$verify    = ( new CaptainCore\Domains )->verify( $domain_id );
	if ( ! $verify ) {
		return new WP_Error( 'token_invalid', 'Invalid Token', [ 'status' => 403 ] );
	}
	return ( new CaptainCore\Domain( $domain_id ) )->fetch();
}

function captaincore_domain_privacy_func( $request ) {
	$domain_id = $request['id'];
	$status    = $request['status'];
	$verify    = ( new CaptainCore\Domains )->verify( $domain_id );
	if ( ! $verify ) {
		return new WP_Error( 'token_invalid', 'Invalid Token', [ 'status' => 403 ] );
	}
	if ( $status == "on" ) {
		return ( new CaptainCore\Domain( $domain_id ) )->privacy_on();
	}
	if ( $status == "off" ) {
		return ( new CaptainCore\Domain( $domain_id ) )->privacy_off();
	}
	return new WP_Error( 'request_invalid', 'Invalid Request', [ 'status' => 404 ] );
}

function captaincore_domain_lock_func( $request ) {
	$domain_id = $request['id'];
	$status    = $request['status'];
	$verify    = ( new CaptainCore\Domains )->verify( $domain_id );
	if ( ! $verify ) {
		return new WP_Error( 'token_invalid', 'Invalid Token', [ 'status' => 403 ] );
	}
	if ( $status == "on" ) {
		return ( new CaptainCore\Domain( $domain_id ) )->lock();
	}
	if ( $status == "off" ) {
		return ( new CaptainCore\Domain( $domain_id ) )->unlock();
	}
	return new WP_Error( 'request_invalid', 'Invalid Request', [ 'status' => 404 ] );
}

function captaincore_domain_update_contacts_func( $request ) {
	$domain_id = $request['id'];
	$verify    = ( new CaptainCore\Domains )->verify( $domain_id );
	if ( ! $verify ) {
		return new WP_Error( 'token_invalid', 'Invalid Token', [ 'status' => 403 ] );
	}
    return ( new CaptainCore\Domain( $domain_id ) )->set_contacts( $request['contacts'] );
}

function captaincore_domain_update_nameservers_func( $request ) {
	$domain_id = $request['id'];
	$verify    = ( new CaptainCore\Domains )->verify( $domain_id );
	if ( ! $verify ) {
		return new WP_Error( 'token_invalid', 'Invalid Token', [ 'status' => 403 ] );
	}
    return ( new CaptainCore\Domain( $domain_id ) )->set_nameservers( $request['nameservers'] );
}

function captaincore_domain_auth_code_func( $request ) {
	$domain_id = $request['id'];
	$verify    = ( new CaptainCore\Domains )->verify( $domain_id );
	if ( ! $verify ) {
		return new WP_Error( 'token_invalid', 'Invalid Token', [ 'status' => 403 ] );
	}
	$domain    = ( new CaptainCore\Domains )->get( $domain_id );

	if ( empty( $domain->provider_id ) ) {
		return new WP_Error( 'no_domain', 'No records', [ 'status' => 200 ] );
	}

	return ( new CaptainCore\Domain( $domain_id ) )->auth_code();
}

function captaincore_dns_func( $request ) {
	$domain_id = $request['id'];
	$verify    = ( new CaptainCore\Domains )->verify( $domain_id );
	if ( ! $verify ) {
		return new WP_Error( 'token_invalid', 'Invalid Token', [ 'status' => 403 ] );
	}
	$remote_id = ( new CaptainCore\Domains )->get( $domain_id )->remote_id;

	$domain    = constellix_api_get( "domains/$remote_id" );
	$response  = constellix_api_get( "domains/$remote_id/records" );
	if ( ! $response->errors ) {
		array_multisort( array_column( $response, 'type' ), SORT_ASC, array_column( $response, 'name' ), SORT_ASC, $response );
	}

	return $response;
}

function captaincore_domains_func( $request ) {
	return ( new CaptainCore\Domains() )->list();
}

function captaincore_recipes_func( $request ) {
	return ( new CaptainCore\Recipes() )->list();
}

function captaincore_running_func( $request ) {

	$current_user = wp_get_current_user();
	$role_check   = in_array( 'administrator', $current_user->roles );

	// Checks for a current user. If admin found pass
	if ( $current_user && $role_check ) {

		if ( defined( 'CAPTAINCORE_DEBUG' ) ) {
			add_filter( 'https_ssl_verify', '__return_false' );
		}

		$data = [ 
			'timeout' => 45,
			'headers' => [
				'Content-Type' => 'application/json; charset=utf-8', 
				'token'        => CAPTAINCORE_CLI_TOKEN 
			],
			'body'        => json_encode( [ "command" => "running list" ] ), 
			'method'      => 'POST', 
			'data_format' => 'body' 
		];

		// Add command to dispatch server
		$response  = wp_remote_post( CAPTAINCORE_CLI_ADDRESS . "/run", $data );
		$processes = json_decode( $response["body"]);

		usort( $processes, function($a, $b) { return strcmp($b->created_at, $a->created_at); });
		
		return $processes;

	} 

	return [];
}

function captaincore_site_phpmyadmin_func( $request ) {
	$site_id     = $request['id'];

	if ( ! captaincore_verify_permissions( $site_id ) ) {
		return new WP_Error( 'token_invalid', 'Invalid Token', [ 'status' => 403 ] );
	}

	$environment = $request['environment'];
	$site        = new CaptainCore\Site( $site_id );
	return $site->fetch_phpmyadmin();
}

function captaincore_site_magiclogin_func( $request ) {
	$site_id     = $request['id'];
	$environment = $request['environment'];
	$login       = $request['login'];

	if ( ! captaincore_verify_permissions( $site_id ) ) {
		return new WP_Error( 'token_invalid', 'Invalid Token', [ 'status' => 403 ] );
	}

	$environment_id = ( new CaptainCore\Site( $site_id ) )->fetch_environment_id( $environment );
	$environment    = ( new CaptainCore\Environments )->get( $environment_id );
	$current_email  = ( new CaptainCore\User )->fetch()["email"];

	// Attempt to match current user to WordPress user
	$users = json_decode( $environment->users );
	foreach ( $users as $user ) {
		if ( strpos( $user->roles, 'administrator') !== false && $user->user_email == $current_email ) {
			$user_login = $user->user_login;
			break;
		}
	}

	if ( empty( $login ) ) {
		$current_user_domain = array_pop(explode('@', $current_email));
		// Attempt to match current user to a similar WordPress user
		foreach ( $users as $user ) {
			$user_domain = array_pop(explode('@', $user->user_email));
			if ( strpos( $user->roles, 'administrator') !== false && $user_domain == $current_user_domain ) {
				$login = $user->user_login;
				break;
			}
		}

		// Select random WordPress admin
		if ( empty( $login ) ) { 
			foreach ( $users as $user ) {
				if ( strpos( $user->roles, 'administrator') !== false ) {
					$login = $user->user_login;
					break;
				}
			}
		}
	}
	$args     = [
		"body" => json_encode( [
				"command"    => "login",
				"user_login" => $login,
				"token"      => $environment->token,
			] ),
		"method"    => 'POST',
		"sslverify" => false,
	];
	$response  = wp_remote_post( "{$environment->home_url}/wp-admin/admin-ajax.php?action=captaincore_quick_login", $args );
	$login_url = $response["body"];
	return $login_url;
}

function captaincore_processes_func( $request ) {
	return ( new CaptainCore\Processes )->list();
}

function captaincore_users_func( $request ) {
	
	$current_user = wp_get_current_user();
	$role_check   = in_array( 'administrator', $current_user->roles );

	// Checks for a current user. If admin found pass
	if ( $current_user && $role_check ) {
		return ( new CaptainCore\Users() )->list();
	} 
	return [];

}

function captaincore_keys_func( $request ) {

	$current_user = wp_get_current_user();
	$role_check   = in_array( 'administrator', $current_user->roles );

	// Checks for a current user. If admin found pass
	if ( $current_user && $role_check ) {
		return ( new CaptainCore\Keys )->all( "title", "ASC" );
	} 
	return [];

}

function  captaincore_defaults_func( $request ) {

	$current_user = wp_get_current_user();
	$role_check   = in_array( 'administrator', $current_user->roles );

	// Checks for a current user. If admin found pass
	if ( $current_user && $role_check ) {
		return ( new CaptainCore\Defaults )->get();
	}
	return [];

}

function captaincore_site_snapshots_func( $request ) {
	$site_id = $request['id'];

	if ( ! captaincore_verify_permissions( $site_id ) ) {
		return new WP_Error( 'token_invalid', 'Invalid Token', [ 'status' => 403 ] );
	}

	$results = ( new CaptainCore\Site( $site_id ))->snapshots();
	return $results;
}

function captaincore_filter_versions_func( $request ) {
	$name     = str_replace( "%20", " ", $request['name'] );
	$filters  = explode( ",", $name );
	$response = ( new CaptainCore\Environments )->filters_for_versions( $filters );
	return $response;
}


function captaincore_filter_statuses_func( $request ) {
	$name     = str_replace( "%20", " ", $request['name'] );
	$filters  = explode( ",", $name );
	$response = ( new CaptainCore\Environments )->filters_for_statuses( $filters );
	return $response;
}


function captaincore_filter_sites_func( $request ) {
	$name     = str_replace( "%20", " ", $request['name'] );
	$statuses = $request['statuses'];
	$statuses = explode( ",", $statuses );
	$versions = $request['versions'];
	$versions = explode( ",", $versions );
	foreach ($statuses as $key => $value) {
		$value = explode( "+", $value );
		$statuses[ $key ] = [
			"type" => $value[2],
			"slug" => $value[1],
			"name" => $value[0],
		];
	}
	foreach ($versions as $key => $value) {
		$value = explode( "+", $value );
		$versions[ $key ] = [
			"type" => $value[2],
			"slug" => $value[1],
			"name" => $value[0],
		];
	}
	$sites = ( new CaptainCore\Sites )->fetch_sites_matching_versions_statuses( [
		"filter"   => $name,
		"versions" => $versions,
		"statuses" => $statuses,
	] );
	$response = $sites;
	return $response;
}

function captaincore_site_captures_new_func( $request ) {
	$site_id     = $request['id'];

	if ( ! captaincore_verify_permissions( $site_id ) ) {
		return new WP_Error( 'token_invalid', 'Invalid Token', [ 'status' => 403 ] );
	}

	$environment = strtolower( $request['environment'] );
	$site        = new CaptainCore\Site( $site_id );
	
	// Remote Sync
	captaincore_run_background_command( "capture $site_id-$environment" );
	return $site_id;
}

function captaincore_site_captures_update_func( $request ) {
	$site_id     = $request['id'];
	$auth        = empty( $request['auth'] ) ? "" : $request['auth'];

	if ( ! captaincore_verify_permissions( $site_id ) ) {
		return new WP_Error( 'token_invalid', 'Invalid Token', [ 'status' => 403 ] );
	}

	$environment = $request['environment'];
	$site        = new CaptainCore\Site( $site_id );
	$time_now    = date("Y-m-d H:i:s");
	$pages       = $request['pages'];

	// Make sure home page is added
	$home_found = false;
	foreach ( $pages as $page ) {
		if ( $page["page"] == "/" ) {
			$home_found = true;
		}
	}
	if ( ! $home_found ) {
		array_unshift( $pages, [ "page" => "/" ] );
	}

	$pages = json_encode( $pages );

	// Saves update settings for a site
	$environment_update = [
		'capture_pages' => $pages,
		'updated_at'    => $time_now,
	];

	$environment_id  = ( new CaptainCore\Site( $site_id ) )->fetch_environment_id( $environment );
	
	if ( ! empty( $auth['username'] ) ) {
		$fetch         = ( new CaptainCore\Environments )->get( $environment_id );
		$details       = ( isset( $fetch->details ) ? json_decode( $fetch->details ) : (object) [] );
		$details->auth = $auth;
		$environment_update['details'] = json_encode( $details );
	}

	( new CaptainCore\Environments )->update( $environment_update, [ "environment_id" => $environment_id ] );

	// Remote Sync
	captaincore_run_background_command( "site sync $site_id" );
	return $site->captures( $environment );
}

function captaincore_site_backup_update_func( $request ) {
	$site_id  = $request['id'];
	$settings = (object) $request->get_param( 'settings' );

	if ( ! captaincore_verify_permissions( $site_id ) ) {
		return new WP_Error( 'token_invalid', 'Invalid Token', [ 'status' => 403 ] );
	}

	$site     = ( new CaptainCore\Sites() )->get( $site_id );
	$time_now = date("Y-m-d H:i:s");
	$details  = ( empty( $site->details ) ) ? (object) [] : json_decode( $site->details );

	$details->backup_settings = [
		"active"   => $settings->active,
		"interval" => $settings->interval,
		"mode"     => $settings->mode
	];
	
	// Saves update settings for a site
	$site_update = [
		'details'    => json_encode( $details ),
		'updated_at' => $time_now,
	];

	( new CaptainCore\Sites )->update( $site_update, [ "site_id" => $site_id ] );

	// Remote Sync
	captaincore_run_background_command( "site sync $site_id" );
	return ( new CaptainCore\Site( $site_id ) )->fetch()->backup_settings;
}

function captaincore_site_snapshot_download_func( $request ) {
	$site_id       = $request['id'];
	$token         = $request['token'];
	$snapshot_id   = $request['snapshot_id'];
	$snapshot_name = $request['snapshot_name'] . ".zip";

	// Verify Snapshot link is valid
	$db = new CaptainCore\Snapshots();
	$snapshot = $db->get( $snapshot_id );

	if ( $snapshot->snapshot_name != $snapshot_name || $snapshot->site_id != $site_id || $snapshot->token != $token ) {
		return new WP_Error( 'token_invalid', 'Invalid Token', [ 'status' => 403 ] );
	}

	$snapshot_url = captaincore_snapshot_download_link( $snapshot_id  );
	header('Location: ' . $snapshot_url);
	exit;
}

function captaincore_site_quicksaves_get_func( $request ) {
	$site_id     = $request['id'];

	if ( ! captaincore_verify_permissions( $site_id ) ) {
		return new WP_Error( 'token_invalid', 'Invalid Token', [ 'status' => 403 ] );
	}

	$hash        = $request['hash'];
	$environment = $request['environment'];
	$site        = new CaptainCore\Site( $site_id );
	return $site->quicksave_get( $hash, $environment );
}

function captaincore_site_backups_func( $request ) {
	$site_id     = $request['id'];

	if ( ! captaincore_verify_permissions( $site_id ) ) {
		return new WP_Error( 'token_invalid', 'Invalid Token', [ 'status' => 403 ] );
	}

	$environment = $request['environment'];
	$site        = new CaptainCore\Site( $site_id );
	return $site->backups( $environment );
}

function captaincore_quicksaves_search_func( $request ) {
	$site_id     = $request->get_param( 'site_id' );
	$environment = $request->get_param( 'environment' );
	$search      = $request->get_param( 'search' );

	if ( ! captaincore_verify_permissions( $site_id ) ) {
		return new WP_Error( 'token_invalid', 'Invalid Token', [ 'status' => 403 ] );
	}

	return ( new CaptainCore\Quicksave( $site_id ) )->search( $search, $environment );
}

function captaincore_site_backups_get_func( $request ) {
	$site_id = $request['id'];
	$file    = $request->get_param( 'file' );

	if ( ! captaincore_verify_permissions( $site_id ) ) {
		return new WP_Error( 'token_invalid', 'Invalid Token', [ 'status' => 403 ] );
	}

	$backup_id   = $request['backup_id'];
	$environment = $request['environment'];
	$site        = new CaptainCore\Site( $site_id );
	if ( ! empty( $file ) ) {
		return $site->backup_show_file( $backup_id, $file, $environment );
	}
	return $site->backup_get( $backup_id, $environment );
}

function captaincore_site_captures_func( $request ) {
	$site_id     = $request['id'];

	if ( ! captaincore_verify_permissions( $site_id ) ) {
		return new WP_Error( 'token_invalid', 'Invalid Token', [ 'status' => 403 ] );
	}

	$environment = $request['environment'];
	$site        = new CaptainCore\Site( $site_id );
	return $site->captures( $environment );
}

function captaincore_site_quicksaves_func( $request ) {
	$site_id = $request['id'];

	if ( ! captaincore_verify_permissions( $site_id ) ) {
		return new WP_Error( 'token_invalid', 'Invalid Token', [ 'status' => 403 ] );
	}

	$results = ( new CaptainCore\Site( $site_id ))->quicksaves();
	return $results;
}

function captaincore_site_quicksaves_environment_func( $request ) {
	$site_id     = $request['id'];
	$environment = $request['environment'];

	if ( ! captaincore_verify_permissions( $site_id ) ) {
		return new WP_Error( 'token_invalid', 'Invalid Token', [ 'status' => 403 ] );
	}

	$results = ( new CaptainCore\Site( $site_id ))->quicksaves( $environment );
	return $results;
}

add_action( 'rest_api_init', 'captaincore_register_rest_endpoints' );

function captaincore_register_rest_endpoints() {

	// Custom endpoint for CaptainCore Client plugin
	register_rest_route(
		'captaincore/v1', '/client', [
			'methods'  => 'GET',
			'callback' => 'captaincore_client_options_func',
		]
	);

	// Custom endpoint for CaptainCore API
	register_rest_route(
		'captaincore/v1', '/api', [
			'methods'       => 'POST',
			'callback'      => 'captaincore_api_func',
			'show_in_index' => false
		]
	);

	// Custom endpoint for CaptainCore API
	register_rest_route(
		'captaincore/v1', '/missive', [
			'methods'       => 'POST',
			'callback'      => 'captaincore_missive_func',
			'show_in_index' => false
		]
	);

	// Custom endpoint for CaptainCore login
	register_rest_route(
		'captaincore/v1', '/login', [
			'methods'       => 'POST',
			'callback'      => 'captaincore_login_func',
			'show_in_index' => false
		]
	);

	register_rest_route(
		'captaincore/v1', '/quicksaves/search', [
			'methods'       => 'GET',
			'callback'      => 'captaincore_quicksaves_search_func',
			'show_in_index' => false
		]
	);
	register_rest_route(
		'captaincore/v1', '/site/(?P<id>[\d]+)/quicksaves', [
			'methods'       => 'GET',
			'callback'      => 'captaincore_site_quicksaves_func',
			'show_in_index' => false
		]
	);

	// Custom endpoint for CaptainCore site/<id>/quicksaves
	register_rest_route(
		'captaincore/v1', '/site/(?P<id>[\d]+)/quicksaves/(?P<environment>[a-zA-Z0-9-]+)', [
			'methods'       => 'GET',
			'callback'      => 'captaincore_site_quicksaves_environment_func',
			'show_in_index' => false
		]
	);

	// Custom endpoint for CaptainCore site/<site-id>/<environment>/quicksaves/<hash>
	register_rest_route(
		'captaincore/v1', '/site/(?P<id>[\d]+)/(?P<environment>[a-zA-Z0-9-]+)/quicksaves/(?P<hash>[a-zA-Z0-9-]+)', [
			'methods'       => 'GET',
			'callback'      => 'captaincore_site_quicksaves_get_func',
			'show_in_index' => false
		]
	);

	// Custom endpoint for CaptainCore site/<site-id>/<environment>/backups
	register_rest_route(
		'captaincore/v1', '/site/(?P<id>[\d]+)/(?P<environment>[a-zA-Z0-9-]+)/backups', [
			'methods'       => 'GET',
			'callback'      => 'captaincore_site_backups_func',
			'show_in_index' => false
		]
	);

	// Custom endpoint for CaptainCore site/<site-id>/<environment>/backups/<backup-id>
	register_rest_route(
		'captaincore/v1', '/sites/(?P<id>[\d]+)/(?P<environment>[a-zA-Z0-9-]+)/backups/(?P<backup_id>[a-zA-Z0-9-]+)', [
			'methods'       => 'GET',
			'callback'      => 'captaincore_site_backups_get_func',
			'show_in_index' => false
		]
	);

	// Custom endpoint for CaptainCore site/<id>/<environment>/captures
	register_rest_route(
		'captaincore/v1', '/sites/(?P<id>[\d]+)/(?P<environment>[a-zA-Z0-9-]+)/captures/new', [
			'methods'       => 'GET',
			'callback'      => 'captaincore_site_captures_new_func',
			'show_in_index' => false
		]
	);
	register_rest_route(
		'captaincore/v1', '/sites/(?P<id>[\d]+)/(?P<environment>[a-zA-Z0-9-]+)/captures', [
			'methods'       => 'POST',
			'callback'      => 'captaincore_site_captures_update_func',
			'show_in_index' => false
		]
	);
	register_rest_route(
		'captaincore/v1', '/sites/(?P<id>[\d]+)/backup', [
			'methods'       => 'POST',
			'callback'      => 'captaincore_site_backup_update_func',
			'show_in_index' => false
		]
	);
	register_rest_route(
		'captaincore/v1', '/site/(?P<id>[\d]+)/(?P<environment>[a-zA-Z0-9-]+)/captures', [
			'methods'       => 'GET',
			'callback'      => 'captaincore_site_captures_func',
			'show_in_index' => false
		]
	);

	// Custom endpoint for CaptainCore site/<id>/snapshots
	register_rest_route(
		'captaincore/v1', '/site/(?P<id>[\d]+)/snapshots', [
			'methods'       => 'GET',
			'callback'      => 'captaincore_site_snapshots_func',
			'show_in_index' => false
		]
	);

	// Custom endpoint for CaptainCore site
	register_rest_route(
		'captaincore/v1', '/site/(?P<id>[\d]+)/snapshots/(?P<snapshot_id>[\d]+)-(?P<token>[a-zA-Z0-9-]+)/(?P<snapshot_name>[a-zA-Z0-9-]+)', [
			'methods'       => 'GET',
			'callback'      => 'captaincore_site_snapshot_download_func',
			'show_in_index' => false
		]
	);

	// Custom endpoint for CaptainCore site
	register_rest_route(
		'captaincore/v1', '/site/(?P<id>[\d]+)', [
			'methods'       => 'GET',
			'callback'      => 'captaincore_site_func',
			'show_in_index' => false
		]
	);

	// Custom endpoint for CaptainCore site
	register_rest_route(
		'captaincore/v1', '/sites/', [
			'methods'       => 'GET',
			'callback'      => 'captaincore_sites_func',
			'show_in_index' => false
		]
	);
	register_rest_route(
		'captaincore/v1', '/site/(?P<id>[\d]+)/(?P<environment>[a-zA-Z0-9-]+)/phpmyadmin', [
			'methods'       => 'GET',
			'callback'      => 'captaincore_site_phpmyadmin_func',
			'show_in_index' => false
		]
	);
	register_rest_route(
		'captaincore/v1', '/sites/(?P<id>[\d]+)/(?P<environment>[a-zA-Z0-9-]+)/magiclogin', [
			'methods'       => 'GET',
			'callback'      => 'captaincore_site_magiclogin_func',
			'show_in_index' => false
		]
	);
	register_rest_route(
		'captaincore/v1', '/sites/(?P<id>[\d]+)/(?P<environment>[a-zA-Z0-9-]+)/magiclogin/(?P<login>[a-zA-Z0-9-\@.]+)', [
			'methods'       => 'GET',
			'callback'      => 'captaincore_site_magiclogin_func',
			'show_in_index' => false
		]
	);

	register_rest_route(
		'captaincore/v1', '/providers', [
			'methods'       => 'GET',
			'callback'      => 'captaincore_provider_func',
			'show_in_index' => false
		]
	);

	register_rest_route(
		'captaincore/v1', '/providers', [
			'methods'       => 'POST',
			'callback'      => 'captaincore_provider_new_func',
			'show_in_index' => false
		]
	);

	register_rest_route(
		'captaincore/v1', '/providers/(?P<id>[a-zA-Z0-9-]+)', [
			'methods'       => 'PUT',
			'callback'      => 'captaincore_provider_update_func',
			'show_in_index' => false
		]
	);

	register_rest_route(
		'captaincore/v1', '/providers/(?P<id>[a-zA-Z0-9-]+)', [
			'methods'       => 'DELETE',
			'callback'      => 'captaincore_provider_delete_func',
			'show_in_index' => false
		]
	);

	register_rest_route(
		'captaincore/v1', '/providers/(?P<provider>[a-zA-Z0-9-]+)/verify', [
			'methods'       => 'GET',
			'callback'      => 'captaincore_provider_verify_func',
			'show_in_index' => false
		]
	);

	register_rest_route(
		'captaincore/v1', '/providers/(?P<provider>[a-zA-Z0-9-]+)/themes', [
			'methods'       => 'GET',
			'callback'      => 'captaincore_provider_themes_func',
			'show_in_index' => false
		]
	);
	register_rest_route(
		'captaincore/v1', '/providers/(?P<provider>[a-zA-Z0-9-]+)/plugins', [
			'methods'       => 'GET',
			'callback'      => 'captaincore_provider_plugins_func',
			'show_in_index' => false
		]
	);
	register_rest_route(
		'captaincore/v1', '/providers/(?P<provider>[a-zA-Z0-9-]+)/theme/(?P<id>[a-zA-Z0-9-]+)/download', [
			'methods'       => 'GET',
			'callback'      => 'captaincore_provider_theme_download_func',
			'show_in_index' => false
		]
	);
	register_rest_route(
		'captaincore/v1', '/providers/(?P<provider>[a-zA-Z0-9-]+)/plugin/(?P<id>[a-zA-Z0-9-]+)/download', [
			'methods'       => 'GET',
			'callback'      => 'captaincore_provider_plugin_download_func',
			'show_in_index' => false
		]
	);

	register_rest_route(
		'captaincore/v1', '/providers/(?P<provider>[a-zA-Z0-9-]+)/connect', [
			'methods'       => 'POST',
			'callback'      => 'captaincore_provider_connect_func',
			'show_in_index' => false
		]
	);

	register_rest_route(
		'captaincore/v1', '/providers/(?P<provider>[a-zA-Z0-9-]+)/new-site', [
			'methods'       => 'POST',
			'callback'      => 'captaincore_provider_new_site_func',
			'show_in_index' => false
		]
	);

	register_rest_route(
		'captaincore/v1', '/provider-actions/check', [
			'methods'       => 'GET',
			'callback'      => 'captaincore_provider_actions_check_func',
			'show_in_index' => false
		]
	);

	register_rest_route(
		'captaincore/v1', '/provider-actions/(?P<id>[\d]+)/run', [
			'methods'       => 'GET',
			'callback'      => 'captaincore_provider_actions_run_func',
			'show_in_index' => false
		]
	);

	register_rest_route(
		'captaincore/v1', '/provider-actions', [
			'methods'       => 'GET',
			'callback'      => 'captaincore_provider_actions_func',
			'show_in_index' => false
		]
	);

	register_rest_route(
		'captaincore/v1', '/me/', [
			'methods'       => 'GET',
			'callback'      => function (WP_REST_Request $request) {
				return ( new CaptainCore\User )->fetch();
			},
			'show_in_index' => false
		]
	);

	register_rest_route(
		'captaincore/v1', '/me/tfa_activate', [
			'methods'       => 'GET',
			'callback'      => function (WP_REST_Request $request) {
				return ( new CaptainCore\User )->tfa_activate();
			},
			'show_in_index' => false
		]
	);

	register_rest_route(
		'captaincore/v1', '/me/tfa_validate', [
			'methods'       => 'POST',
			'callback'      => function (WP_REST_Request $request) {
				return ( new CaptainCore\User )->tfa_activate_verify( $request['token'] );
			},
			'show_in_index' => false
		]
	);

	register_rest_route(
		'captaincore/v1', '/me/tfa_deactivate', [
			'methods'       => 'GET',
			'callback'      => function (WP_REST_Request $request) {
				return ( new CaptainCore\User )->tfa_deactivate();
			},
			'show_in_index' => false
		]
	);

	register_rest_route(
		'captaincore/v1', '/filters/(?P<name>[a-zA-Z0-9-,|_%]+)/versions/', [
			'methods'       => 'GET',
			'callback'      => 'captaincore_filter_versions_func',
			'show_in_index' => false
		]
	);

	register_rest_route(
		'captaincore/v1', '/filters/(?P<name>[a-zA-Z0-9-,|_%]+)/statuses/', [
			'methods'       => 'GET',
			'callback'      => 'captaincore_filter_statuses_func',
			'show_in_index' => false
		]
	);

	register_rest_route(
		'captaincore/v1', '/filters/(?P<name>[a-zA-Z0-9-,+_%)]+)/sites/versions=(?:(?P<versions>[a-zA-Z0-9-,+\.|]+))?/statuses=(?:(?P<statuses>[a-zA-Z0-9-,+\.|]+))?', [
			'methods'       => 'GET',
			'callback'      => 'captaincore_filter_sites_func',
			'show_in_index' => false
		]
	);

	register_rest_route(
		'captaincore/v1', '/dns/(?P<id>[\d]+)', [
			'methods'       => 'GET',
			'callback'      => 'captaincore_dns_func',
			'show_in_index' => false
		]
	);

	register_rest_route(
		'captaincore/v1', '/domain/(?P<id>[\d]+)', [
			'methods'       => 'GET',
			'callback'      => 'captaincore_domain_func',
			'show_in_index' => false
		]
	);

	register_rest_route(
		'captaincore/v1', '/domain/(?P<id>[\d]+)/contacts', [
			'methods'       => 'POST',
			'callback'      => 'captaincore_domain_update_contacts_func',
			'show_in_index' => false
		]
	);

	register_rest_route(
		'captaincore/v1', '/domain/(?P<id>[\d]+)/nameservers', [
			'methods'       => 'POST',
			'callback'      => 'captaincore_domain_update_nameservers_func',
			'show_in_index' => false
		]
	);

	register_rest_route(
		'captaincore/v1', '/domain/(?P<id>[\d]+)/lock_(?P<status>[a-zA-Z0-9-,|_%]+)', [
			'methods'       => 'GET',
			'callback'      => 'captaincore_domain_lock_func',
			'show_in_index' => false
		]
	);

	register_rest_route(
		'captaincore/v1', '/domain/(?P<id>[\d]+)/privacy_(?P<status>[a-zA-Z0-9-,|_%]+)', [
			'methods'       => 'GET',
			'callback'      => 'captaincore_domain_privacy_func',
			'show_in_index' => false
		]
	);

	register_rest_route(
		'captaincore/v1', '/domain/(?P<id>[\d]+)/auth_code', [
			'methods'       => 'GET',
			'callback'      => 'captaincore_domain_auth_code_func',
			'show_in_index' => false
		]
	);

	register_rest_route(
		'captaincore/v1', '/recipes/', [
			'methods'       => 'GET',
			'callback'      => 'captaincore_recipes_func',
			'show_in_index' => false
		]
	);

	register_rest_route(
		'captaincore/v1', '/running/', [
			'methods'       => 'GET',
			'callback'      => 'captaincore_running_func',
			'show_in_index' => false
		]
	);

	// Custom endpoint for recipes
	register_rest_route(
		'captaincore/v1', '/processes/', [
			'methods'       => 'GET',
			'callback'      => 'captaincore_processes_func',
			'show_in_index' => false
		]
	);

	// Custom endpoint for domains
	register_rest_route(
		'captaincore/v1', '/domains/', [
			'methods'       => 'GET',
			'callback'      => 'captaincore_domains_func',
			'show_in_index' => false
		]
	);

	// Custom endpoint for domains
	register_rest_route(
		'captaincore/v1', '/users/', [
			'methods'       => 'GET',
			'callback'      => 'captaincore_users_func',
			'show_in_index' => false
		]
	);

	// Custom endpoint for CaptainCore accounts
	register_rest_route(
		'captaincore/v1', '/accounts/', [
			'methods'       => 'GET',
			'callback'      => 'captaincore_accounts_func',
			'show_in_index' => false
		]
	);

	// Custom endpoint for CaptainCore configurations
	register_rest_route(
		'captaincore/v1', '/configurations/', [
			'methods'       => 'GET',
			'callback'      => 'captaincore_configurations_func',
			'show_in_index' => false
		]
	);
	register_rest_route(
		'captaincore/v1', '/configurations/', [
			'methods'       => 'POST',
			'callback'      => 'captaincore_configurations_update_func',
			'show_in_index' => false
		]
	);

	// Custom endpoint for CaptainCore billing
	register_rest_route(
		'captaincore/v1', '/billing/', [
			'methods'       => 'GET',
			'callback'      => 'captaincore_billing_func',
			'show_in_index' => false
		]
	);

	register_rest_route(
		'captaincore/v1', '/subscriptions/', [
			'methods'       => 'GET',
			'callback'      => 'captaincore_subscriptions_func',
			'show_in_index' => false
		]
	);

	register_rest_route(
		'captaincore/v1', '/upcoming_subscriptions/', [
			'methods'       => 'GET',
			'callback'      => 'captaincore_upcoming_subscriptions_func',
			'show_in_index' => false
		]
	);

	// Custom endpoint for keys
	register_rest_route(
		'captaincore/v1', '/keys/', [
			'methods'       => 'GET',
			'callback'      => 'captaincore_keys_func',
			'show_in_index' => false
		]
	);

	// Custom endpoint for defaults
	register_rest_route(
		'captaincore/v1', '/defaults/', [
			'methods'       => 'GET',
			'callback'      => 'captaincore_defaults_func',
			'show_in_index' => false
		]
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

	if ( $post->command == "reset" ) {

		$user_data = get_user_by( 'login', $post->login->user_login );
		if ( ! $user_data ) {
			$user_data = get_user_by( 'email', $post->login->user_login );
		}
		if ( ! $user_data ) {
			return;
		}

		$user_login = $user_data->user_login;
		$user_email = $user_data->user_email;
	
		// Redefining user_login ensures we return the right case in the email.
		$key        = get_password_reset_key( $user_data );
	
		if ( is_wp_error( $key ) ) {
			return $key;
		}
	
		if ( is_multisite() ) {
			$site_name = get_network()->site_name;
		} else {
			/*
			 * The blogname option is escaped with esc_html on the way into the database
			 * in sanitize_option we want to reverse this for the plain text arena of emails.
			 */
			$site_name = wp_specialchars_decode( get_option( 'blogname' ), ENT_QUOTES );
		}
	
		$message = __( 'Someone has requested a password reset for the following account:' ) . "\r\n\r\n";
		/* translators: %s: site name */
		$message .= sprintf( __( 'Site Name: %s' ), $site_name ) . "\r\n\r\n";
		/* translators: %s: user login */
		$message .= sprintf( __( 'Username: %s' ), $user_login ) . "\r\n\r\n";
		$message .= __( 'If this was a mistake, just ignore this email and nothing will happen.' ) . "\r\n\r\n";
		$message .= __( 'To reset your password, visit the following address:' ) . "\r\n\r\n";
		$message .= '<' . network_site_url( "wp-login.php?action=rp&key=$key&login=" . rawurlencode( $user_login ), 'login' ) . ">\r\n";
	
		/* translators: Password reset notification email subject. %s: Site title */
		$title = sprintf( __( '[%s] Password Reset' ), $site_name );
	
		/**
		 * Filters the subject of the password reset email.
		 *
		 * @since 2.8.0
		 * @since 4.4.0 Added the `$user_login` and `$user_data` parameters.
		 *
		 * @param string  $title      Default email title.
		 * @param string  $user_login The username for the user.
		 * @param WP_User $user_data  WP_User object.
		 */
		$title = apply_filters( 'retrieve_password_title', $title, $user_login, $user_data );
	
		/**
		 * Filters the message body of the password reset mail.
		 *
		 * If the filtered message is empty, the password reset email will not be sent.
		 *
		 * @since 2.8.0
		 * @since 4.1.0 Added `$user_login` and `$user_data` parameters.
		 *
		 * @param string  $message    Default mail message.
		 * @param string  $key        The activation key.
		 * @param string  $user_login The username for the user.
		 * @param WP_User $user_data  WP_User object.
		 */
		$message = apply_filters( 'retrieve_password_message', $message, $key, $user_login, $user_data );
	
		if ( $message && ! wp_mail( $user_email, wp_specialchars_decode( $title ), $message ) ) {
			wp_die( __( 'The email could not be sent.' ) . "<br />\n" . __( 'Possible reason: your host may have disabled the mail() function.' ) );
		}

	}

	if ( $post->command == "signIn" ) {
		$credentials = [
			"user_login"    => $post->login->user_login,
			"user_password" => $post->login->user_password,
			"remember"      => true,
		];

		$current_user = wp_authenticate( $post->login->user_login, $post->login->user_password ); 

		if ( $current_user->ID === null ) {
			return [ "errors" => "Login failed." ];
		}

		$tfa_enabled = (bool) get_user_meta( $current_user->ID, 'captaincore_2fa_enabled', true );
		if ( $tfa_enabled && empty( $post->login->tfa_code ) ) {
			return [ "info" =>  "Enter one time password." ];
		}
		if ( $tfa_enabled ) {
			$tfa_enabled_check = ( new CaptainCore\User( $current_user->ID, true ) )->tfa_login( $post->login->tfa_code );
			if ( ! $tfa_enabled_check ) {
				return [ "errors" =>  "One time password is invalid." ];
			}
		}
		if ( function_exists( "wpgraphql_cors_signon" ) ) {
			wpgraphql_cors_signon( $credentials, true );
		} else {
			wp_signon( $credentials );
		} 
		return [ "message" =>  "Logged in." ];
	}

	if ( $post->command == "signOut" ) {
		wp_logout();
	}

	if ( $post->command == "createAccount" ) {

		$errors   = [];
		$password = $post->login->password;
		$invites  = new CaptainCore\Invites();
		$results  = $invites->where( [
			"account_id" => $post->invite->account,
			"token"      => $post->invite->token,
		 ] );
		if ( count( $results ) == "1" ) {
			$record = $results[0];

			if (strlen($password) < 8) {
				$errors[] = "Password too short!";
			}
		
			if (!preg_match("#[0-9]+#", $password)) {
				$errors[] = "Password must include at least one number!";
			}
		
			if (!preg_match("#[a-zA-Z]+#", $password)) {
				$errors[] = "Password must include at least one letter!";
			}     
		
			if ( count($errors) > 0 ) {
				return [ "errors" => $errors ];
			}

			// Add account ID to current user
			$userdata = array(
				'user_login' => $record->email,
				'user_email' => $record->email,
				'user_pass'  => $password,
			);
			
			// Generate new user
			$user_id = wp_insert_user( $userdata );

			// Assign permission to account
			( new CaptainCore\User( $user_id, true ) )->assign_accounts( [ $record->account_id ] );

			$account = new CaptainCore\Account( $record->account_id, true );
			$account->calculate_totals();

			$invite = new CaptainCore\Invite( $record->invite_id );
			$invite->mark_accepted();

			// Sign into new account
			$credentials = [
				"user_login"    => $record->email,
				"user_password" => $password,
				"remember"      => true,
			];
	
			$current_user = wp_signon( $credentials );

			return [ "message" => "New account created." ];
		}
		return  [ "error" => "Account already taken or invalid invite." ];
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
	$size   = [ 'B', 'kB', 'MB', 'GB', 'TB', 'PB', 'EB', 'ZB', 'YB' ];
	$factor = floor( ( strlen( $bytes ) - 1 ) / 3 );
	return sprintf( "%.{$decimals}f", $bytes / pow( 1024, $factor ) ) . @$size[ $factor ];
}

function my_relationship_result( $title, $post, $field, $post_id ) {

	// load a custom field from this $object and show it in the $result
	$process       = get_field( 'process', $post->ID );
	$process_title = $post->ID . ' - ' . get_the_title( $process[0] ) . ' - ' . get_the_author_meta( 'display_name', $post->post_author );

	// override title
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
	foreach ( [ 'project' ] as $post_type ) {
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
				return new WP_Error( 'rest_token_invalid', __( 'Token is invalid' ), [ 'status' => 403 ] );
			}
		}

		if ( $endpoint == 'captcore_customer' ) {

			$customer_id = $endpoint_all[1];

			if ( ! captaincore_verify_permissions_account( $customer_id ) ) {
				return new WP_Error( 'rest_token_invalid', __( 'Token is invalid' ), [ 'status' => 403 ] );
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
function captaincore_verify_permissions( $site_id ) {
	return ( new CaptainCore\Sites )->verify( $site_id );
}

// Checks current user for valid permissions
function captaincore_verify_permissions_account( $account_id ) {
	return ( new CaptainCore\User )->verify_accounts( [ $account_id ] );
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
	global $wpdb;

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
	global $wpdb;
	$cmd   = $_POST['command'];
	$value = $_POST['value'];
	
	if ( $cmd == "connect" ) { 
		$connect = (object) $_POST['connect'];
		// Disable https when debug enabled
		add_filter( 'https_ssl_verify', '__return_false' );
		
		$domain = get_option( "home" );
		$domain = str_replace( "http://www.", "", $domain );
		$domain = str_replace( "https://www.", "", $domain );
		$domain = str_replace( "http://", "", $domain );
		$domain = str_replace( "https://", "", $domain );
		$auth   = md5( AUTH_KEY );

		$data = [
			'timeout' => 45,
			'headers' => [
				'Content-Type' => 'application/json; charset=utf-8', 
				'token'        => $connect->token 
			], 
			'body'        => json_encode( [ "command" => "connection add $domain $auth {$connect->token}" ] ), 
			'method'      => 'POST', 
			'data_format' => 'body' 
		];

		// Add command to dispatch server
		$response = wp_remote_post( "https://{$connect->address}/tasks", $data );
		if ( is_wp_error( $response ) ) {
			$error_message = $response->get_error_message();
			echo "Something went wrong: $error_message";
		} else {
			echo $response["body"];
		}
		wp_die(); // this is required to terminate immediately and return a proper response

	}

	if ( $cmd == 'newUser' ) {
		$account  = (object) $value;
		$response = (object) [];
		$errors   = [];

		if ( $account->login == "" ) {
			$errors[] = "Username name can't be empty.";
		}

		if ( $account->login != "" && username_exists( $account->login ) ) {
			$errors[] = "Username is taken.";
		}
		
		if ( ! filter_var( $account->email, FILTER_VALIDATE_EMAIL ) ) {
			$errors[] = "Email address is not valid.";
		}

		if ( filter_var( $account->email, FILTER_VALIDATE_EMAIL ) && email_exists( $account->email ) ) {
			$errors[] = "Email address is taken.";
		}

		if ( count($errors) == 0 ) {
			$result = wp_insert_user( array(
				'first_name'   => $account->first_name,
				'last_name'    => $account->last_name,
				'user_email'   => $account->email,
				'user_login'   => $account->login,
				'role'         => 'subscriber'
			) );
			if ( is_wp_error( $result ) ) {
				$errors[] = $result->get_error_message();
			} else {
				( new CaptainCore\User( $result, true ) )->assign_accounts( $account->account_ids );
				wp_new_user_notification( $result, null, 'user' );
			}
		}

		if ( count($errors) > 0 ) {
			$response->errors = $errors;
		}

		echo json_encode( $response );
	}

	if ( $cmd == 'updateAccount' ) {
		$user_id  = get_current_user_id();
		$account  = (object) $value;
		$response = (object) [];
		$errors   = [];

		if ( $account->display_name == "" ) {
			$errors[] = "Display name can't be empty.";
		}

		if ( ! filter_var($account->email, FILTER_VALIDATE_EMAIL ) ) {
			$errors[] = "Email address is not valid.";
		}
		
		// If new password sent then valid it.
		if ( $account->new_password != "" ) {

			$password = $account->new_password;

			if (strlen($password) < 8) {
				$errors[] = "Password too short!";
			}
		
			if (!preg_match("#[0-9]+#", $password)) {
				$errors[] = "Password must include at least one number!";
			}
		
			if (!preg_match("#[a-zA-Z]+#", $password)) {
				$errors[] = "Password must include at least one letter!";
			}
			
		}

		if ( count($errors) == 0 ) {
			// Update user submitted info
			$result = wp_update_user( array( 
				'ID'           => $user_id, 
				'display_name' => $account->display_name,
				'user_email'   => $account->email,
			) );
			if ( is_wp_error( $result ) ) {
				$errors[] = $result->get_error_message();
			}
		}

		// Passed checks so update the password.
		if ( count($errors) == 0 && $account->new_password != "") {
			$result = wp_update_user( array( 
				'ID'        => $user_id, 
				'user_pass' => $account->new_password,
			) );
			if ( is_wp_error( $result ) ) {
				$errors[] = $result->get_error_message();
			}
		}

		if ( count($errors) > 0 ) {
			$response->errors = $errors;
		}

		$response->profile = $account;
		unset ( $response->profile->new_password );
		echo json_encode( $response );
	}

	if ( $cmd == 'downloadPDF' ) {
		$order            = wc_get_order( $value );
		$order_data       = (object) $order->get_data();
		$order_items      = $order->get_items( apply_filters( 'woocommerce_purchase_order_item_types', 'line_item' ) );
		$order_line_items = "";
		foreach ( $order_items as $item_id => $item ) {
			$subtotal          = str_replace( "<bdi>", "", $order->get_formatted_line_subtotal( $item ) );
			$subtotal          = str_replace( "</bdi>", "", $subtotal );
			$details           = $item->get_meta_data()[0]->get_data();
			if ( $details['key'] == "Details" ) {
				$description = $details['value'];
			}
			$order_line_items .= "<tr><td width=\"536\">{$item->get_quantity()}x {$item->get_name()}<br /><small>{$description}</small></td><td>{$subtotal}</td></tr>";
		}

		$refunds = $order->get_refunds();
		foreach ( $refunds as $item ) {
			$description       = $item->get_post_title();
			$subtotal          = str_replace( "<bdi>", "", "-".$item->get_formatted_refund_amount() );
			$subtotal          = str_replace( "</bdi>", "", $subtotal );
			$order_line_items .= "<tr><td width=\"536\">1x Refund<br /><small>{$description}</small></td><td>{$subtotal}</td></tr>";
			$order_data->total = $order_data->total - $item->get_amount();
		}

		$payment_gateways      = WC()->payment_gateways->payment_gateways();
		$payment_method        = $order->get_payment_method();
		$payment_method_string = sprintf(
			__( 'Payment via %s', 'woocommerce' ),
			esc_html( isset( $payment_gateways[ $payment_method ] ) ? $payment_gateways[ $payment_method ]->get_title() : "Check" )
		);

		if ( $order->get_date_paid() ) {
			$paid_on = sprintf(
				__( 'Paid on %1$s @ %2$s', 'woocommerce' ),
				wc_format_datetime( $order->get_date_paid() ),
				wc_format_datetime( $order->get_date_paid(), get_option( 'time_format' ) )
			);
		}

		$response = (object) [
			"order_id"       => $order_data->id,
			"created_at"     => $order_data->date_created->getTimestamp(),
			"status"         => $order_data->status,
			"line_items"     => $order_line_items,
			"payment_method" => $payment_method_string,
			"paid_on"        => $paid_on,
			"total"          => number_format( (float) $order_data->total, 2, '.', '' ),
		];

		$account_id = $order->get_meta( 'captaincore_account_id' );
		$account    = ( new CaptainCore\Accounts )->get( $account_id );
		$created_at = $order_data->date_created->date( 'M jS Y' );
		$html2pdf   = new \Spipu\Html2Pdf\Html2Pdf('P', 'A4', 'en');
		$html       = <<<HEREDOC
<style type="text/css">
p { font-size:16px; }
table { border-collapse: collapse; font-size:16px; }
img { margin-bottom: 1em; }
hr { height:1px;border-width:0;color: #59595b;background-color: #59595b; }
th, td { padding: 4px 16px; border-bottom: 1px solid #59595b; vertical-align: top; }
</style>
<page backtop="20px" backbottom="20px" backleft="20px" backright="20px">
<p><img width="224" src="https://anchor.host/wp-content/uploads/2015/01/logo.png" alt="Anchor Hosting"></p>
<hr />
<h2>Invoice #{$order_data->id} for {$account->name}</h2>
<p>Order was created on <strong>{$created_at}</strong> and is currently <strong>{$response->status} payment</strong>.</p>
<br /><br />
<table cellspacing="0">
<thead>
	<tr><th><span>Services</span></th><th><span>Amount</span></th></tr>
</thead>
<tbody>
	$order_line_items
	<tr><td style="text-align:right;">Total:</td><td>\${$response->total}</td></tr>
</tbody>
</table>
</page>
HEREDOC;
		$html2pdf->setTestTdInOnePage( false );
		$html2pdf->writeHTML( $html );
		$html2pdf->output();
		wp_die();
	}
	if ( $cmd == 'fetchInvoice' ) {
		$order            = wc_get_order( $value );
		$order_data       = (object) $order->get_data();
		$order_items      = $order->get_items( apply_filters( 'woocommerce_purchase_order_item_types', 'line_item' ) );
		$order_line_items = [];
		foreach ( $order_items as $item_id => $item ) {
			$order_line_items[] = [
				"name"        => $item->get_name(),
				"quantity"    => $item->get_quantity(),
				"description" => $item->get_meta_data(),
				"total"       => $order->get_formatted_line_subtotal( $item ),
			];
		}

		$refunds = $order->get_refunds();
		foreach ( $refunds as $item ) {
			$order_line_items[] = [
				"name"        => "Refund",
				"quantity"    => "1",
				"description" => $item->get_post_title(),
				"total"       => "-".$item->get_formatted_refund_amount(),
			];
			$order_data->total = $order_data->total - $item->get_amount();
		}

		$payment_gateways      = WC()->payment_gateways->payment_gateways();
		$payment_method        = $order->get_payment_method();
		$payment_method_string = sprintf(
			__( 'Payment via %s', 'woocommerce' ),
			esc_html( isset( $payment_gateways[ $payment_method ] ) ? $payment_gateways[ $payment_method ]->get_title() : "Check" )
		);

		if ( $order->get_date_paid() ) {
			$paid_on = sprintf(
				__( 'Paid on %1$s @ %2$s', 'woocommerce' ),
				wc_format_datetime( $order->get_date_paid() ),
				wc_format_datetime( $order->get_date_paid(), get_option( 'time_format' ) )
			);
		}

		$response = [
			"order_id"       => $order_data->id,
			"created_at"     => $order_data->date_created->getTimestamp(),
			"status"         => $order_data->status,
			"line_items"     => $order_line_items,
			"payment_method" => $payment_method_string,
			"paid_on"        => $paid_on,
			"total"          => number_format( (float) $order_data->total, 2, '.', '' ),
		];
		echo json_encode( $response );
	}
	if ( $cmd == 'fetchAccount' ) {
		$account = new CaptainCore\Account( $value );
		$account->calculate_usage();
		echo json_encode( $account->fetch() );
	}
	if ( $cmd == 'fetchUser' ) {
		$user = new CaptainCore\User( $value, true );
		echo json_encode( $user->fetch() );
	}
	if ( $cmd == 'saveUser' ) {
		$response = ( new CaptainCore\Users )->update( $value );
		echo json_encode( $response );
	}
	if ( $cmd == 'fetchInvite' ) {
		$invite = (object) $value;
		$invites = new CaptainCore\Invites();
		$results = $invites->where( array( 
			"account_id" => $invite->account,
			"token"      => $invite->token,
		) );
		if ( count( $results ) == "1" ) {
			$account = new CaptainCore\Account( $invite->account, true );
			echo json_encode( $account->fetch() );
		}
	}
	if ( $cmd == 'removeAccountAccess' ) {
		$user_id     = $value;
		$user        = ( new CaptainCore\User( $user_id, true ) );
		$account_id  = $_POST['account'];
		$account_ids = $user->accounts();
		if ( empty( $account_ids ) ) {
			$account_ids = [];
		}
		if ( ( $key = array_search( $account_id, $account_ids ) ) !== false ) {
			unset( $account_ids[$key] );
		}
		( new CaptainCore\User( $user_id, true ) )->assign_accounts( array_unique( $account_ids ) );

		$account = new CaptainCore\Account( $account_id );
		$account->calculate_totals();
	}
	if ( $cmd == 'acceptInvite' ) {
		$invite = (object) $value;
		$invites = new CaptainCore\Invites();
		$results = $invites->where( [
			"account_id" => $invite->account,
			"token"      => $invite->token,
		] );
		
		if ( count( $results ) == "1" ) {
			// Add account ID to current user
			$user       = new CaptainCore\User;
			$accounts   = $user->accounts();
			$accounts[] = $invite->account;
			$user->assign_accounts( array_unique( $accounts ) );

			$account = new CaptainCore\Account( $invite->account );
			$account->calculate_totals();

			$invite = new CaptainCore\Invite( $results[0]->invite_id );
			$invite->mark_accepted();
		}
	}

	if ( $cmd == 'saveDefaults' ) {
		$user     = new CaptainCore\User;
		$accounts = $user->accounts();
		$record   = (object) $value;
		if ( ! in_array( $record->account_id, $accounts ) && ! $user->is_admin() ) { 
			echo json_encode( "Permission denied" );
			wp_die();
		}
		
		if ( ! isset( $record->defaults["users"] ) ) {
			$record->defaults["users"] = [];
		}
		if ( ! isset( $record->defaults["recipes"] ) ) {
			$record->defaults["recipes"] = [];
		}
		$account = new CaptainCore\Accounts();
		$account->update( [ "defaults" => json_encode( $record->defaults ) ], [ "account_id" => $record->account_id ] );
		( new CaptainCore\Account( $record->account_id, true ) )->sync();
		echo json_encode( "Record updated." );
	}

	if ( $cmd == 'saveGlobalConfigurations' ) {
		$user = new CaptainCore\User;
		if ( ! $user->is_admin() ) { 
			echo json_encode( "Permission denied" );
			wp_die();
		}
		$value = (object) $value;
		if ( isset( $value->dns_introduction ) ) {
			$value->dns_introduction = str_replace( "\'", "'", $value->dns_introduction );
		}
		update_site_option( 'captaincore_configurations', json_encode( $value ) );
		( new CaptainCore\Configurations )->sync();
		echo json_encode( "Global configurations updated." );
	}

	if ( $cmd == 'saveGlobalDefaults' ) {
		$user = new CaptainCore\User;
		if ( ! $user->is_admin() ) { 
			echo json_encode( "Permission denied" );
			wp_die();
		}
		update_site_option( 'captaincore_defaults', json_encode( $value ) );
		( new CaptainCore\Defaults )->sync();
		echo json_encode( "Global defaults updated." );
	}

	wp_die();

}

add_action( 'wp_ajax_captaincore_user', 'captaincore_user_action_callback' );
function captaincore_user_action_callback() {
	global $wpdb;
	$user = new CaptainCore\User;
	$cmd  = $_POST['command'];
	$everyone_commands = [
		'fetchRequestedSites',
	];

	if ( ! $user->is_admin() && ! in_array( $cmd, $everyone_commands ) ) {
		echo "Permission denied";
		wp_die();
		return;
	}

	if ( $cmd == 'fetchRequestedSites' ) {;
		echo json_encode( $user->fetch_requested_sites() );
	};

	wp_die();

}

add_action( 'wp_ajax_captaincore_account', 'captaincore_account_action_callback' );
function captaincore_account_action_callback() {
	global $wpdb;
	$user = new CaptainCore\User;
	$cmd  = $_POST['command'];
	$everyone_commands = [
		'addDomain',
		'deleteDomain',
		'requestSite',
		'payInvoice',
		'setAsPrimary',
		'addPaymentMethod',
		'deletePaymentMethod',
		'deleteRequestSite',
		'cancelPlan',
		'updateBilling',
	];

	if ( $cmd == 'updateBilling' ) {
		$request  = (object) $_POST['value'];
		$customer = new WC_Customer(  $user->user_id() );
		$customer->set_billing_address_1( $request->address_1 );
		$customer->set_billing_address_2( $request->address_2 );
		$customer->set_billing_city( $request->city );
		$customer->set_billing_company( $request->company );
		$customer->set_billing_country( $request->country );
		$customer->set_billing_email( $request->email );
		$customer->set_billing_first_name( $request->first_name );
		$customer->set_billing_last_name( $request->last_name );
		$customer->set_billing_phone( $request->phone );
		$customer->set_billing_postcode( $request->postcode );
		$customer->set_billing_state( $request->state );
		$customer->save();
	};

	if ( $cmd == 'cancelPlan' ) {

		$current_subscription = (object) $_POST['value'];
		$current_user         = $user->fetch();
		$billing              = $user->billing();
		if ( $current_subscription->account_id == "" || $current_subscription->name == "" ) {
			wp_die();
		}
		foreach ( $billing->subscriptions as $subscription ) {
			if ( $subscription->account_id == $current_subscription->account_id && $subscription->name == $current_subscription->name  ) {
				
				// Build email
				$to      = get_option( 'admin_email' );
				$subject = "Request cancel plan '{$current_subscription->name}'";
				$body    = "Request cancel plan '{$current_subscription->name}' #{$current_subscription->account_id} from {$current_user['name']}, <a href='mailto:{$current_user['email']}'>{$current_user['email']}</a>.";
				$headers = [ 'Content-Type: text/html; charset=UTF-8' ];

				// Send email
				wp_mail( $to, $subject, $body, $headers );
			}
		}

	}

	if ( $cmd == 'requestPlanChanges' ) {
		$current_user = $user->fetch();
		$subscription = (object) $_POST['value'];
		
		// Build email
		$to      = get_option( 'admin_email' );
		$subject = "Request plan change from {$current_user['name']} <{$current_user['email']}>";
		$body    = "Change subscription '{$subscription->name}' to {$subscription->plan['name']} and {$subscription->plan['interval']} interval.";
		$headers = [ 'Content-Type: text/html; charset=UTF-8' ];

		// Send email
		wp_mail( $to, $subject, $body, $headers );
	}

	$account_id = intval( $_POST['account_id'] );

	// Only proceed if have permission to particular account id.
	if ( ! $user->is_admin() && isset( $account_id ) && ! captaincore_verify_permissions_account( $account_id ) && ! in_array( $_POST['command'], $everyone_commands ) ) {
		echo "Permission denied";
		wp_die();
		return;
	}

	if ( $cmd == 'deleteDomain' ) {
		$response = ( new CaptainCore\Domains )->delete_domain( $_POST['value'] );
		echo json_encode( $response );
	};

	if ( $cmd == 'addDomain' ) {

		$errors = [];
		$name   = $_POST['value'];

		// If results still exists then give an error
		if ( $name == "" ) {
			$errors[] = "Domain can't be empty.";
		}

		// Check for duplicate domain.
		$domain_exists = ( new CaptainCore\Domains )->where( [ "name" => $name ] );

		// If results still exists then give an error
		if ( count( $domain_exists ) > 0 ) {
			$errors[] = "Domain has already been added.";
		}

		if ( empty( $account_id ) ) { 
			$errors[] = "Account can't be empty.";
		}

		// If any errors then bail
		if ( count( $errors ) > 0 ) {
			echo json_encode( [ "errors" => $errors ] );
			wp_die();
		}

		$time_now = date("Y-m-d H:i:s");

		// Insert domain
		$domain_id = ( new CaptainCore\Domains )->insert( [
			"name"       => $name,
			'updated_at' => $time_now,
			'created_at' => $time_now,
		] );

		// Assign domain to account
		( new CaptainCore\Domain( $domain_id ) )->insert_accounts( [ $account_id ] );

		// Execute remote code
		$response = ( new CaptainCore\Domain( $domain_id ) )->fetch_remote_id();
		if ( is_array( $response ) ) {
			foreach ( $response["errors"] as $error ) {
				$errors[] = $error;
			}
			echo json_encode( [ "errors" => $errors ] );
			wp_die();
		}

		echo json_encode( [ "name" => $name, "domain_id" => $domain_id, "remote_id" => $response ] );

	}

	if ( $cmd == 'sendAccountInvite' ) {
		$account  = new CaptainCore\Account( $account_id );
		$response = $account->invite( $_POST['invite'] );
		echo json_encode( $response );
	}

	if ( $cmd == 'deleteInvite' ) {
		$account  = new CaptainCore\Account( $account_id );
		$response = $account->invite_delete( $_POST['value'] );
		echo "Invite deleted.";
	}

	if ( $cmd == 'payInvoice' ) {
		// Pay with new credit card
		if ( isset( $_POST['source_id'] ) ) {
			$response       = $user->add_payment_method( $_POST['source_id'] );
			$payment_tokens = WC_Payment_Tokens::get_customer_tokens( $user->user_id() );
			foreach ( $payment_tokens as $payment_token ) { 
				if( $payment_token->get_token() == $_POST['source_id'] ) {
					$user->pay_invoice( $_POST['value'], $payment_token->get_id() );
					$user->set_as_primary( $payment_token->get_id() );
				}
			}
			wp_die();
		}
		// Pay with existing credit card
		$user->pay_invoice( $_POST['value'], $_POST['payment_id'] );
		$user->set_as_primary( $_POST['payment_id'] );
	};

	if ( $cmd == 'setAsPrimary' ) {
		$user->set_as_primary( $_POST['value'] );
	};

	if ( $cmd == 'addPaymentMethod' ) {
		$response = $user->add_payment_method( $_POST['value'] );
		echo json_encode( $response );
	};

	if ( $cmd == 'deletePaymentMethod' ) {
		$user->delete_payment_method( $_POST['value'] );
	};

	if ( $cmd == 'requestSite' ) {
		$user->request_site( $_POST['value'] );
		echo json_encode( $user->fetch_requested_sites() );
	};

	if ( $cmd == 'backRequestSite' ) {
		$request = (object) $_POST['value'];
		$user->back_request_site( $request );
		echo json_encode( $user->fetch_requested_sites() );
	};

	if ( $cmd == 'continueRequestSite' ) {
		$request = (object) $_POST['value'];
		$user->continue_request_site( $request );
		echo json_encode( $user->fetch_requested_sites() );
	};
	
	if ( $cmd == 'updateRequestSite' ) {
		$request = (object) $_POST['value'];
		$user->update_request_site( $request );
		echo json_encode( $user->fetch_requested_sites() );
	};

	if ( $cmd == 'deleteRequestSite' ) {
		$request = (object) $_POST['value'];
		$user->delete_request_site( $request );
		echo json_encode( $user->fetch_requested_sites() );
	};

	wp_die(); // this is required to terminate immediately and return a proper response

}

add_action( 'wp_ajax_captaincore_ajax', 'captaincore_ajax_action_callback' );
function captaincore_ajax_action_callback() {
	global $wpdb;
	$user = new CaptainCore\User;

	$everyone_commands = [
		'newRecipe',
		'updateRecipe',
		'updateSiteAccount',
		'requestSite',
	];

	if ( is_array( $_POST['post_id'] ) ) {
		$post_ids       = [];
		$post_ids_array = $_POST['post_id'];
		foreach ( $post_ids_array as $id ) {
			$post_ids[] = intval( $id );
		}
	} else {
		$post_id = intval( $_POST['post_id'] );
	}

	// Only proceed if have permission to particular site id.
	if ( ! $user->is_admin() && isset( $post_id ) && ! captaincore_verify_permissions( $post_id ) && ! in_array( $_POST['command'], $everyone_commands ) ) {
		echo "Permission denied";
		wp_die();
		return;
	}

	// Only proceed if have permission to particular site id.
	if ( ! $user->is_admin() && isset( $post_ids ) && ! captaincore_verify_permissions( $post_ids ) && ! in_array( $_POST['command'], $everyone_commands ) ) {
		echo "Permission denied";
		wp_die();
		return;
	}

	// Only proceed if access to command 
	$admin_commands = [
		'fetchConfigs',
		'updateLogEntry',
		'newLogEntry',
		'newKey',
		'updateKey',
		'deleteKey',
		'setKeyAsPrimary',
		'newProcess',
		'saveProcess',
		'fetchProcess',
		'fetchProcessRaw',
		'fetchProcessLogs',
		'listenProcesses',
		'updateFathom',
		'updateMailgun',
		'updatePlan',
		'updateDomainAccount',
		'newSite',
		'createSiteAccount',
		'updateSite', 
		'deleteSite',
		'deleteAccount'
	];
	if ( ! $user->is_admin() && in_array( $_POST['command'], $admin_commands ) ) {
		echo "Permission denied";
		wp_die();
		return;
	}

	$cmd       = $_POST['command'];
	if ( isset($_POST['value']) ){
		$value = $_POST['value'];
	}
	
	$fetch          = (new CaptainCore\Site( $post_id ))->get();
	$site           = $fetch->site;
	$environment    = $_POST['environment'];
	$remote_command = false;

	if ( $cmd == 'mailgun' ) {
		$mailgun  = $fetch->mailgun;
		if ( isset( $_POST['page'] ) ) {
			$response = mailgun_events( $mailgun, $_POST['page'] );
		} else {
			$response = mailgun_events( $mailgun );
		}
		echo json_encode( $response );
	}

	if ( $cmd == 'updateCapturePages' ) {
		$value_json = json_encode($value);
		$time_now   = date("Y-m-d H:i:s");
		
		// Saves update settings for a site
		$environment_update = [
			'capture_pages' => $value_json,
			'updated_at'    => $time_now,
		];

		$environment_id = ( new CaptainCore\Site( $post_id ) )->fetch_environment_id( $environment );
		( new CaptainCore\Environments )->update( $environment_update, [ "environment_id" => $environment_id ] );
		
		// Remote Sync
		$remote_command = true;
		$command        = "site sync $post_id";

	}

	if ( $cmd == 'fetchLink' ) {
		// Fetch snapshot details
		$in_24hrs = date("Y-m-d H:i:s", strtotime ( date("Y-m-d H:i:s")."+24 hours" ) );

		// Generate new token
		$token = bin2hex( openssl_random_pseudo_bytes( 16 ) );
		( new CaptainCore\Snapshots )->update( [
			"token"       => $token,
			"expires_at"  => $in_24hrs 
		],[ 
			"snapshot_id" => $value 
		] );
		echo json_encode( [ 
			"token"       => $token,
			"expires_at"  => $in_24hrs
		] );
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

	if ( $cmd == 'shareStats' ) {
		$sharing        = $_POST['sharing'];
		$share_password = $_POST['share_password'];
		$fathom_id      = $_POST['fathom_id'];
		$response       = ( new CaptainCore\Site( $post_id ) )->stats_sharing( $fathom_id, $sharing, $share_password );
	}

	if ( $cmd == 'fetchStats' ) {
		$before    = strtotime( $_POST['from_at'] );
		$after     = strtotime( $_POST['to_at'] );
		$grouping  = strtolower( $_POST['grouping'] );
		$fathom_id = $_POST['fathom_id'];
		$response  = ( new CaptainCore\Site( $post_id ) )->stats( $environment, $before, $after, $grouping, $fathom_id );
		if ( is_wp_error( $response ) ) {
			$error_message = $response->get_error_message();
			echo json_encode( [ "error" => $error_message ] );
			wp_die();
			return;
		}
		echo json_encode( $response ); 
	}

	if ( $cmd == 'fetchConfigs' ) {
		$remote_command = true;
		$command = "configs fetch vars";
	};

	if ( $cmd == 'newKey' ) {
		$key      = (object) $value;
		$time_now = date("Y-m-d H:i:s");
		$user_id  = get_current_user_id();

		$new_key = [
			'user_id'    => $user_id,
			'title'      => $key->title,
			'updated_at' => $time_now,
			'created_at' => $time_now,
			'main'       => 0,
		];

		$look_for_default = ( new CaptainCore\Keys )->where( [ "user_id" => $user_id, "main" => "1" ] );
		if ( empty( $look_for_default ) ) {
			$new_key[ "main" ] = 1;
		}

		$key_id         = ( new CaptainCore\Keys )->insert( $new_key );
		$remote_command = true;
		$silence        = true;
		$ssh_key        = base64_encode( stripslashes_deep( $key->key ) );
		$command        = "key add $ssh_key --id=$key_id";
	}

	if ( $cmd == 'setKeyAsPrimary' ) {
		$key      = (object) $value;
		$key_id   = $key->key_id;
		$time_now = date("Y-m-d H:i:s");
		$user_id  = get_current_user_id();

		$look_for_default = ( new CaptainCore\Keys )->where( [ "user_id" => $user_id, "main" => "1" ] );
		if ( ! empty( $look_for_default ) ) {
			foreach( $look_for_default as $key_primary ) {
				( new CaptainCore\Keys )->update( [ 'main' => 0 ], [ "key_id" => $key_primary->key_id ] );
			}
		}

		$key_update = [
			'main'       => 1,
			'updated_at' => $time_now,
		];

		( new CaptainCore\Keys )->update( $key_update, [ "key_id" => $key_id ] );

		$configurations = ( new CaptainCore\Configurations )->get();
		$configurations->default_key = $key_id;
		update_site_option( 'captaincore_configurations', json_encode( $configurations ) );
		( new CaptainCore\Configurations )->sync();
	}

	if ( $cmd == 'updateKey' ) {
		$key      = (object) $value;
		$key_id   = $key->key_id;
		$time_now = date("Y-m-d H:i:s");
		$user_id  = get_current_user_id();

		$key_update = [
			'title'      => $key->title,
			'updated_at' => $time_now,
		];

		$look_for_default = ( new CaptainCore\Keys )->where( [ "user_id" => $user_id, "main" => "1" ] );
		if ( empty( $look_for_default ) ) {
			$key_update[ "main" ] = 1;
		}

		( new CaptainCore\Keys )->update( $key_update, [ "key_id" => $key_id ] );

		$remote_command = true;
		$silence        = true;
		$ssh_key        = base64_encode( stripslashes_deep( $key->key ) );
		$command        = "key add $ssh_key --id={$key_id}";
	}

	if ( $cmd == 'deleteKey' ) {
		$key_id   = $value;
		$time_now = date("Y-m-d H:i:s");

		( new CaptainCore\Keys )->delete( $key_id );

		$remote_command = true;
		$silence        = true;
		$ssh_key        = base64_encode( stripslashes_deep( $key->key ) );
		$command        = "key delete --id={$key_id}";
	}

	if ( $cmd == 'listenProcesses' ) {
		$run_in_background = true;
		$remote_command    = true;
		$command           = "running listen";
	}

	if ( $cmd == 'newProcess' ) {
		$timenow             = date( 'Y-m-d H:i:s' );
		$process             = (object) $value;
		$process->user_id    = get_current_user_id();
		$process->created_at = $timenow;
		$process->updated_at = $timenow;
		unset( $process->show );
		$process_id = ( new CaptainCore\Processes )->insert( (array) $process );
		$process_inserted = ( new CaptainCore\Processes )->get( $process_id );
		echo json_encode( $process_inserted );
	}

	if ( $cmd == 'saveProcess' ) {
		$process              = (object) $value;
		$process->name        = str_replace( "\'", "'", $process->name );
		$process->description = str_replace( "\'", "'", $process->description );
		$process->updated_at  = date( 'Y-m-d H:i:s' );
		( new CaptainCore\Processes )->update( (array) $process, [ "process_id" => $process->process_id ] );
		$process_updated = ( new CaptainCore\Processes )->get( $process->process_id );
		echo json_encode( $process_updated );
	}

	if ( $cmd == 'fetchProcess' ) {
		$process = ( new CaptainCore\Process( $post_id ) )->get();
		echo json_encode( $process );
	}

	if ( $cmd == 'fetchProcessRaw' ) {
		$process = ( new CaptainCore\Processes )->get( $post_id );
		$process->roles = (int) $process->roles;
		echo json_encode( $process );
	}

	if ( $cmd == 'fetchProcessLog' ) {
		$process_log = ( new CaptainCore\ProcessLog( $value ) )->get();
		echo json_encode( $process_log );
	}

	if ( $cmd == 'fetchProcessLogs' ) {
		$process_logs = ( new CaptainCore\ProcessLogs )->list();
		echo json_encode( $process_logs );
	}

	if ( $cmd == 'newLogEntry' ) {

		$process_id = $_POST['process_id'];
		$time_now   = date( 'Y-m-d H:i:s' );
		$value      = str_replace( "\'", "'", $value );
		$process_log_new = (object) [
			"process_id"   => $_POST['process_id'],
			'user_id'      => get_current_user_id(),
			'public'       => 1,
			'description'  => $value,
			'status'       => 'completed',
			'created_at'   => $time_now,
			'updated_at'   => $time_now,
			'completed_at' => $time_now
		];
		$process_log = new CaptainCore\ProcessLogs();
		$process_log_id_new = $process_log->insert( (array) $process_log_new );
		( new CaptainCore\ProcessLog( $process_log_id_new ) )->assign_sites( $post_ids );
		$process_logs = ( new CaptainCore\Site( $post_id ) )->process_logs();
		$timelines = [];
		foreach ( $post_ids as $post_id ) {
			$timelines[ $post_id ] = ( new CaptainCore\Site( $post_id ) )->process_logs();
		}
		echo json_encode( $timelines ) ;
	}

	if ( $cmd == 'updateLogEntry' ) {
		$process_log_update              = (object) $_POST['log'];
		$site_ids                        = array_column( $process_log_update->websites, 'site_id' );
		$process_log_update->user_id     = get_current_user_id();
		$process_log_update->description = str_replace( "\'", "'", $process_log_update->description_raw );
		$process_log_update->created_at  = $process_log_update->created_at_raw;
		$process_log_update->updated_at  = date( 'Y-m-d H:i:s' );
		unset( $process_log_update->created_at_raw );
		unset( $process_log_update->name );
		unset( $process_log_update->author );
		unset( $process_log_update->websites );
		unset( $process_log_update->description_raw );
		( new CaptainCore\ProcessLogs )->update( (array) $process_log_update, [ "process_log_id" => $process_log_update->process_log_id ] );
		( new CaptainCore\ProcessLog( $process_log_update->process_log_id) )->assign_sites( $site_ids );
		$timelines = [];
		foreach ( $site_ids as $site_id ) {
			$timelines[ $site_id ] = ( new CaptainCore\Site( $site_id ) )->process_logs();
		}
		echo json_encode( $timelines );
	}

	if ( $cmd == 'timeline' ) {
		$process_logs = ( new CaptainCore\Site( $post_id ) )->process_logs();
		echo json_encode( $process_logs ) ;
	}

	if ( $cmd == 'createSiteAccount' ) {
		$time_now = date("Y-m-d H:i:s");
		$defaults = [ 
			"email"    => "",
			"timezone" => "",
			"recipes"  => [],
			"users"    => [],
		];
		$account_id = ( new CaptainCore\Accounts )->insert( [ 
			"name"       => trim( $value ),
			"status"     => "active",
			"created_at" => $time_now,
			"updated_at" => $time_now,
			"defaults"   => json_encode( $defaults ),
		] );
		( new CaptainCore\Account( $account_id, true ) )->calculate_totals();
		( new CaptainCore\Account( $account_id, true ) )->sync();
		echo json_encode( $account_id );
	}

	if ( $cmd == 'updateSiteAccount' ) {
		$account = (object) $value;
		if ( ! $user->verify_account_owner( $account->account_id ) ) {
			echo "Permission denied";
			wp_die();
			return;
		}

		( new CaptainCore\Accounts )->update( [ "name" => trim( $account->name ), "billing_user_id" => $account->billing_user_id ], [ "account_id" => $account->account_id ] );
		( new CaptainCore\Account( $account->account_id ) )->sync();
		echo json_encode( $account ) ;
	}

	if ( $cmd == 'updateDomainAccount' ) {
		$domain_id = $_POST['domain_id'];
		( new CaptainCore\Domain( $domain_id ) )->assign_accounts( $value );
	}

	if ( $cmd == 'newRecipe' ) {

		$recipe   = (object) $value;
		$time_now = date("Y-m-d H:i:s");

		$new_recipe = [
			'user_id'        => get_current_user_id(),
			'title'          => $recipe->title,
			'updated_at'     => $time_now,
			'created_at'     => $time_now,
			'content'        => stripslashes_deep( $recipe->content ),
			'public'         => 0
		];

		if ( $user->is_admin() ) {
			$new_recipe["public"] = $recipe->public;
		}

		$db_recipes = new CaptainCore\Recipes();
		$recipe_id = $db_recipes->insert( $new_recipe );
		echo json_encode( $db_recipes->list() );

		$remote_command = true;
		$silence = true;
		$recipe = ( new CaptainCore\Recipes )->get( $recipe_id );
		$recipe = base64_encode( json_encode( $recipe ) );
		$command = "recipe add $recipe --format=base64";

	}

	if ( $cmd == 'updateRecipe' ) {

		$recipe   = (object) $value;
		$time_now = date("Y-m-d H:i:s");
		$user_id  = get_current_user_id();

		if ( ! $user->is_admin() && $recipe->user_id != $user_id ) {
			echo "Permission denied";
			wp_die();
			return;
		}

		$recipe_update = [
			'title'      => $recipe->title,
			'updated_at' => $time_now,
			'content'    => stripslashes_deep( $recipe->content ),
			'public'     => 0
		];

		if ( $user->is_admin() ) {
			$recipe_update["public"] = $recipe->public;
		}

		$db_recipes = new CaptainCore\Recipes();
		$db_recipes->update( $recipe_update, [ "recipe_id" => $recipe->recipe_id ] );

		echo json_encode( $db_recipes->list() );

		$remote_command = true;
		$silence = true;
		$recipe  = ( new CaptainCore\Recipes )->get( $recipe->recipe_id );
		$recipe  = base64_encode( json_encode( $recipe ) );
		$command = "recipe add $recipe --format=base64";

	}

	if ( $cmd == 'usage-breakdown' ) {
		$site            = ( new CaptainCore\Site( $post_id ) )->get();
		$account         = new CaptainCore\Account( $site->account_id, true );
		$usage_breakdown = $account->usage_breakdown();
		echo json_encode( $usage_breakdown ) ;
	}

	if ( $cmd == 'updateMailgun' ) {
		$site = new CaptainCore\Site( $post_id );
		$site->update_mailgun( $value );
	}

	if ( $cmd == 'updateFathom' ) {

		// Append environment if needed
		if ( $environment == "Staging" ) {
			$site = "{$site}-staging";
		}

		$time_now = date("Y-m-d H:i:s");
		$data     = (object) $value;

		$environment_id = ( new CaptainCore\Site( $post_id ) )->fetch_environment_id( $environment );
		$environment    = ( new CaptainCore\Environments )->get( $environment_id );
		( new CaptainCore\Environments )->update( [ 'fathom' => json_encode( $data->fathom_lite ) ], [ "environment_id" => $environment->environment_id ] );
		
		$details         = ( isset( $environment->details ) ? json_decode( $environment->details ) : (object) [] );
		$details->fathom = $data->fathom;
		( new CaptainCore\Environments )->update( [ 
			 "details"    => json_encode( $details ),
			 "updated_at" => $time_now,
			], [ "environment_id" => $environment->environment_id ] );

		( new CaptainCore\Site( $post_id ) )->sync();

		$run_in_background = true;
		$remote_command    = true;
		$command           = "stats-deploy $site";
	}

	if ( $cmd == 'updatePlan' ) {
		( new CaptainCore\Accounts )->update_plan( $value["plan"], $post_id );
	}

	if ( $cmd == 'updateSettings' ) {
		// Saves update settings for a site
		$environment_update = [
			'updates_enabled'         => $value["updates_enabled"],
			'updates_exclude_themes'  => implode(",", $value["updates_exclude_themes"]),
			'updates_exclude_plugins' => implode(",", $value["updates_exclude_plugins"]),
			'updated_at'              => date("Y-m-d H:i:s") 
		];
		$environment_id = ( new CaptainCore\Site( $post_id ) )->fetch_environment_id( $environment );
		( new CaptainCore\Environments )->update( $environment_update, [ "environment_id" => $environment_id ] );
		$command           = "site sync $post_id";
		$remote_command    = true;
		$run_in_background = true;
	}

	if ( $cmd == 'newSite' ) {
		// Create new site
		$site     = new CaptainCore\Site();
		$response = $site->create( $value );
		echo json_encode( $response );
	}

	if ( $cmd == 'updateSite' ) {
		// Updates site
		$site     = new CaptainCore\Site( $value["site_id"] );
		$response = $site->update( $value );
		echo json_encode( $response );
	}

	if ( $cmd == 'deleteSite' ) {
		// Delete site on CaptainCore CLI
		captaincore_run_background_command( "site delete $site" );

		// Delete site locally
		$site = new CaptainCore\Site( $post_id );
		$site->mark_inactive();
	}

	if ( $cmd == 'deleteAccount' ) {
		// Delete site on CaptainCore CLI
		captaincore_run_background_command( "account delete $post_id" );

		// Delete account locally
		$account = new CaptainCore\Account( $post_id, true );
		$account->delete();
	}

	if ( $cmd == 'fetch-site-environments' ) {
		$site         = new CaptainCore\Site( $post_id );
		$environments = $site->environments();
		echo json_encode( $environments );
	}
	if ( $cmd == 'fetch-site-details' ) {
		$site        = new CaptainCore\Site( $post_id );
		$account     = $site->account();
		$shared_with = $site->shared_with();
		$site        = $site->fetch();
		echo json_encode( [
			"site"        => $site,
			"account"     => $account,
			"shared_with" => $shared_with,
		] );
	}
	if ( $cmd == 'fetch-site' ) {
		$sites = [];
		if ( is_array( $post_ids ) && count( $post_ids ) > 0 ) {
			foreach( $post_ids as $id ) {
				$site    = new CaptainCore\Site( $id );
				$sites[] = $site->fetch();
			}
		} else {
			$site    = new CaptainCore\Site( $post_id );
			$sites[] = $site->fetch();
		}
		echo json_encode( $sites );
	}

	if ( $cmd == 'fetch-users' ) {
		$results = ( new CaptainCore\Site( $post_id ))->users();
		echo json_encode($results);
	}

	if ( $cmd == 'fetch-update-logs' ) {
		$results = ( new CaptainCore\Site( $post_id ))->update_logs();
		echo json_encode($results);
	}

	if ( $remote_command ) {

		// Disable https when debug enabled
		if ( defined( 'CAPTAINCORE_DEBUG' ) ) {
			add_filter( 'https_ssl_verify', '__return_false' );
		}

		$data = [
			'timeout' => 45,
			'headers' => array(
				'Content-Type' => 'application/json; charset=utf-8', 
				'token'        => CAPTAINCORE_CLI_TOKEN 
			), 
			'body'        => json_encode( [ "command" => $command ] ), 
			'method'      => 'POST', 
			'data_format' => 'body' 
		];

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

		// Store results in wp_options.captaincore_settings
		if ( $cmd == "fetchConfigs" ) {
			$captaincore_settings = json_decode( $response );
			unset($captaincore_settings->websites);
			update_option("captaincore_settings", $captaincore_settings );
		}

		// Store results in wp_options.captaincore_settings
		if ( $cmd == "newKey" ||  $cmd == "updateKey" ) {
			$key_update = [
				'fingerprint' => $response,
			];
	
			$db = new CaptainCore\Keys();
			$db->update( $key_update, [ "key_id" => $key_id ] );
			echo json_encode( $db->get( $key_id ) );
		}

		if ( $silence ) {
			wp_die(); // this is required to terminate immediately and return a proper response
		}

		echo $response;
		
		wp_die(); // this is required to terminate immediately and return a proper response

	}

	wp_die(); // this is required to terminate immediately and return a proper response
}

add_action( 'wp_ajax_captaincore_install', 'captaincore_install_action_callback' );
function captaincore_install_action_callback() {
	global $wpdb;

	// Assign post id
	$post_id = intval( $_POST['post_id'] );

	// Many sites found, check permissions
	if ( is_array( $_POST['post_id'] ) ) {
		$post_ids       = [];
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
	$version      = $_POST['version'];
	$commit       = $_POST['commit'];
	$arguments    = $_POST['arguments'];
	$filters      = $_POST['filters'];
	$addon_type   = $_POST['addon_type'];
	$date         = $_POST['date'];
	$name         = $_POST['name'];
	$environment  = $_POST['environment'];
	$backup_id    = $_POST['backup_id'];
	$link         = $_POST['link'];
	$background   = $_POST['background'];
	$job_id       = $_POST['job_id'];
	$notes        = $_POST['notes'];
	$fetch        = ( new CaptainCore\Site( $post_id ) )->get();
	$site         = $fetch->site;
	$provider     = $fetch->provider;
	$domain       = $fetch->name;

	$partners = get_field( 'partner', $post_id );
	if ( $partners && is_string( $partners ) ) {
		$preloadusers = implode( ',', $partners );
	}

	// Append environment if needed
	if ( $environment == "Staging" ) {
		$site = "{$site}-staging";
	}

	// Append provider if exists
	if ( $provider != '' ) {
		$site = $site . '@' . $provider;
	}

	// If many sites, fetch their names
	if ( is_array( $post_ids ) && count ( $post_ids ) > 0 ) {
		$site_names = [];
		foreach( $post_ids as $id ) {

			$fetch     = ( new CaptainCore\Site( $id ) );
			$site_name = $fetch->get()->site;

			if ( $environment == "Production" or $environment == "Both" ) {
				$site_names[] = $site_name;
			}

			$address_staging = $fetch->environments()[1]->address;

			// Add staging if needed
			if ( isset( $address_staging ) && $address_staging != "" ) {
				if ( $environment == "Staging" or $environment == "Both" ) {
					$site_names[] = "{$site_name}-staging";
				}
			}
		}
		$site = implode( " ", $site_names );
	}

	if ( $background ) {
		$run_in_background = true;
	}
	if ( $cmd == 'new' ) {
		$command = "site sync $post_id --update-extras";
		$run_in_background = true;
	}
	if ( $cmd == 'deploy-defaults' ) {
		$command = "site deploy-defaults $site";
		$run_in_background = true;
	}
	if ( $cmd == 'update' ) {
		$command = "site sync $post_id";
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
		$value = urlencode( $value );
		$command = "ssh $site --script=migrate -- --url=\"$value\"";
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
		$command = "ssh $site --script=deploy-mailgun -- --key=\"" . MAILGUN_API_KEY . "\" --domain=$domain";
	}
	if ( $cmd == 'launch' ) {
		$run_in_background = true;
		$command = "ssh $site --script=launch -- --domain=$value";
	}
	if ( $cmd == 'reset-permissions' ) {
		$run_in_background = true;
		$command = "ssh $site --script=reset-permissions";
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
			$command = "site copy-to-staging $site --email=$value";
		} else {
			$command = "site copy-to-staging $site";
		}
	}
	if ( $cmd == 'staging-to-production' ) {
		$run_in_background = true;
		if ( $value ) {
			$command = "site copy-to-production $site --email=$value";
		} else {
			$command = "site copy-to-production $site";
		}
	}
	if ( $cmd == 'scan-errors' ) {
		$run_in_background = true;
		$command = "scan-errors $site";
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
		$command   = "quicksave generate $site";
	}
	if ( $cmd == 'backup' ) {
		$run_in_background = true;
		$command = "backup $site";
	}
	if ( $cmd == 'snapshot' ) {
		$run_in_background = true;
		$user_id = get_current_user_id();
		if ( $date && $value ) {
			$command = "snapshot generate $site --email=$value --rollback=\"$date\" --user-id=$user_id --notes=\"$notes\"";
		} elseif ( $value ) {
			$command = "snapshot generate $site --email=$value --user-id=$user_id --notes=\"$notes\"";
		} else {
			$command = "snapshot generate $site --user-id=$user_id --notes=\"$notes\"";
		}
		if ( $filters ) {
			$filters = implode(",", $filters); 
			$command = $command . " --filter={$filters}";
		}
	}
	if ( $cmd == 'deactivate' ) {
		$run_in_background = true;
		$command           = "deactivate $site --name=\"$name\" --link=\"$link\"";
	}
	if ( $cmd == 'activate' ) {
		$run_in_background = true;
		$command           = "activate $site";
	}

	if ( $cmd == 'view_quicksave_changes' ) {
		$command = "quicksave show-changes $site $value";
	}

	if ( $cmd == 'run' ) {
		$code    = base64_encode( stripslashes_deep( $value ) );
		$command = "run $site --code=$code";
	}

	if ( $cmd == 'backup_download' ) {
		$run_in_background = true;
		$value             = (object) $value;
		$current_user      = wp_get_current_user();
		$email             = $current_user->user_email;
		$payload           = [
			"files"       => json_decode( stripslashes_deep( $value->files ) ),
			"directories" => json_decode ( stripslashes_deep( $value->directories ) ),
		];
		$payload           = base64_encode( json_encode( $payload ) );
		captaincore_run_background_command( "backup download $site $value->backup_id --email=$email --payload='$payload'" );
		echo "Generating downloadable zip.";
		wp_die();
	}

	if ( $cmd == 'manage' ) {
		$run_in_background = true;
		if ( is_int($post_id) ) {
			$command = "$value $site --" . $arguments['value'] . '="' . stripslashes($arguments['input']) . '"';
		}
	}

	if ( $cmd == 'quicksave_file_diff' ) {
		$command = "quicksave file-diff $site $commit $value --html";
	}

	if ( $cmd == 'rollback' ) {
		$run_in_background = true;
		$command           = "quicksave rollback $site $commit --version=$version --$addon_type=$value";
	}

	if ( $cmd == 'quicksave_rollback' ) {
		$run_in_background = true;
		$command           = "quicksave rollback $site $commit --version=$version --all";
	}

	if ( $cmd == 'quicksave_file_restore' ) {
		$run_in_background = true;
		$command           = "quicksave rollback $site $commit --version=$version --file=$value";
	}

	// Disable https when debug enabled
	if ( defined( 'CAPTAINCORE_DEBUG' ) ) {
		add_filter( 'https_ssl_verify', '__return_false' );
	}

	$data = [ 
		'timeout' => 45,
		'headers' => [
			'Content-Type' => 'application/json; charset=utf-8',
			'token'        => CAPTAINCORE_CLI_TOKEN
		],
		'body' => json_encode( [
			"command" => $command
		] ),
		'method'      => 'POST',
		'data_format' => 'body'
	];

	if ( $cmd == 'job-fetch' ) {

		$data['body'] = "";
		$data['method'] = "GET";

		// Add command to dispatch server
		$response = wp_remote_get( CAPTAINCORE_CLI_ADDRESS . "/task/${job_id}", $data );
		$response = json_decode( $response["body"] );
		
		// Response with task id
		if ( $response && $response->Status == "Completed" ) { 
			echo json_encode( [
				"response" => $response->Response,
				"status"   => "Completed",
				"job_id"   => $job_id
			] );
			wp_die(); // this is required to terminate immediately and return a proper response
		}

		echo "Job ID $job_id is still running.";

		wp_die(); // this is required to terminate immediately and return a proper response
	}

	if ( $run_in_background ) {

		// Add command to dispatch server
		$response = wp_remote_post( CAPTAINCORE_CLI_ADDRESS . "/tasks", $data );
		
		if ( is_wp_error( $response ) ) {
			// If the request has failed, show the error message
			echo $response->get_error_message();
			wp_die();
		}

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

function captaincore_run_background_command( $command ) {
        
	// Disable https when debug enabled
	if ( defined( 'CAPTAINCORE_DEBUG' ) ) {
		add_filter( 'https_ssl_verify', '__return_false' );
	}

	$data = [
		'timeout' => 45,
		'headers' => [
			'Content-Type' => 'application/json; charset=utf-8', 
			'token'        => CAPTAINCORE_CLI_TOKEN 
		],
		'body'        => json_encode( [ "command" => $command ]), 
		'method'      => 'POST', 
		'data_format' => 'body'
	];

	// Add command to dispatch server
	$response = wp_remote_post( CAPTAINCORE_CLI_ADDRESS . "/run/background", $data );
	if ( is_wp_error( $response ) ) {
		$error_message = $response->get_error_message();
		return "Something went wrong: $error_message";
	}

	return $response["body"];
}

add_filter( 'acf/update_value', 'captaincore_disregard_acf_fields', 10, 3 );
function captaincore_disregard_acf_fields( $value, $post_id, $field ) {

	$fields_to_disregard = [
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
	];

	// Disregard updating certain fields as they've already been stored in a custom table.
	if ( in_array( $field['key'], $fields_to_disregard ) ) {
		return null;
	}

	return $value;

}

add_filter( 'acf/load_value', 'captaincore_load_environments', 11, 3 );

function captaincore_load_environments( $value, $post_id, $field ) {

	$fields_table_map = [
		"field_5619c94518f1c" => [ "environment" => "Production", "field" => 'address'                ],
		"field_5619c97c18f1d" => [ "environment" => "Production", "field" => 'username'               ],
		"field_5619c98218f1e" => [ "environment" => "Production", "field" => 'password'               ],
		"field_5619c98918f1f" => [ "environment" => "Production", "field" => 'protocol'               ],
		"field_5619c99d18f20" => [ "environment" => "Production", "field" => 'port'                   ],
		"field_58422bd538c32" => [ "environment" => "Production", "field" => 'home_directory'         ],
		"field_5a69f0a6e9686" => [ "environment" => "Production", "field" => 'database_username'      ],
		"field_5a69f0cce9687" => [ "environment" => "Production", "field" => 'database_password'      ],
		"field_5b2a902585a78" => [ "environment" => "Production", "field" => 'updates_enabled'        ],
		"field_5b231746b9731" => [ "environment" => "Production", "field" => 'updates_exclude_themes' ],
		"field_5b231770b9732" => [ "environment" => "Production", "field" => 'updates_exclude_plugins'],
		"field_5b2a900c85a77" => [ "environment" => "Production", "field" => 'users'                  ],
		"field_5a9421b804ed4" => [ "environment" => "Production", "field" => 'themes'                 ],
		"field_5a9421b004ed3" => [ "environment" => "Production", "field" => 'plugins'                ],
		"field_5a944358bf146" => [ "environment" => "Production", "field" => 'home_url'               ],
		"field_5a9421bc04ed5" => [ "environment" => "Production", "field" => 'core'                   ],
		"field_58e14eee75e79" => [ "environment" => "Production", "field" => 'offload_enabled'        ],
		"field_5c67581c7ad15" => [ "environment" => "Production", "field" => 'offload_provider'       ],
		"field_58e14fc275e7a" => [ "environment" => "Production", "field" => 'offload_access_key'     ],
		"field_58e1500875e7b" => [ "environment" => "Production", "field" => 'offload_secret_key'     ],
		"field_58e1502475e7c" => [ "environment" => "Production", "field" => 'offload_bucket'         ],
		"field_58e1503075e7d" => [ "environment" => "Production", "field" => 'offload_path'           ],
		"field_57b7a25d2cc60" => [ "environment" => "Staging", "field" => 'address'                   ],
		"field_57b7a2642cc61" => [ "environment" => "Staging", "field" => 'username'                  ],
		"field_57b7a26b2cc62" => [ "environment" => "Staging", "field" => 'password'                  ],
		"field_57b7a2712cc63" => [ "environment" => "Staging", "field" => 'protocol'                  ],
		"field_57b7a2772cc64" => [ "environment" => "Staging", "field" => 'port'                      ],
		"field_5845da68fc2c9" => [ "environment" => "Staging", "field" => 'home_directory'            ],
		"field_5a90ba0c6c61a" => [ "environment" => "Staging", "field" => 'database_username'         ],
		"field_5a90ba1e6c61b" => [ "environment" => "Staging", "field" => 'database_password'         ],
		"field_5c6758987ad1a" => [ "environment" => "Staging", "field" => 'updates_enabled'           ],
		"field_5c6758a37ad1b" => [ "environment" => "Staging", "field" => 'updates_exclude_themes'    ],
		"field_5c6758b37ad1c" => [ "environment" => "Staging", "field" => 'updates_exclude_plugins'   ],
		"field_5c6758d67ad20" => [ "environment" => "Staging", "field" => 'users'                     ],
		"field_5c6758cc7ad1f" => [ "environment" => "Staging", "field" => 'themes'                    ],
		"field_5c6758c57ad1e" => [ "environment" => "Staging", "field" => 'plugins'                   ],
		"field_5c6758df7ad21" => [ "environment" => "Staging", "field" => 'home_url'                  ],
		"field_5c6758bb7ad1d" => [ "environment" => "Staging", "field" => 'core'                      ],
		"field_5c6757d97ad13" => [ "environment" => "Staging", "field" => 'offload_enabled'           ],
		"field_5c67584d7ad16" => [ "environment" => "Staging", "field" => 'offload_provider'          ],
		"field_5c6757e77ad14" => [ "environment" => "Staging", "field" => 'offload_access_key'        ],
		"field_5c6758667ad17" => [ "environment" => "Staging", "field" => 'offload_secret_key'        ],
		"field_5c6758797ad18" => [ "environment" => "Staging", "field" => 'offload_bucket'            ],
		"field_5c67588f7ad19" => [ "environment" => "Staging", "field" => 'offload_path'              ]
	];

	// Fetch certain records from custom table
	if ( in_array( $field['key'], array_keys( $fields_table_map ) ) ) {

		$db_environments = new CaptainCore\Environments();

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
	global $wpdb;

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
	global $wpdb;

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
	$snapshot = ( new CaptainCore\Snapshots )->get( $snapshot_id );
	$domain   = ( new CaptainCore\Sites )->get( $snapshot->site_id )->name;

	// Generate download url to snapshot
	$home_url     = home_url();
	$file_name    = substr($snapshot->snapshot_name, 0, -4);
	$download_url = "{$home_url}/wp-json/captaincore/v1/site/{$snapshot->site_id}/snapshots/{$snapshot->snapshot_id}-{$snapshot->token}/{$file_name}";

	// Build email
	$company = get_field( 'business_name', 'option' );
	$to      = $snapshot->email;
	$subject = "$company - Snapshot #$snapshot_id";
	$body    = "Snapshot #{$snapshot_id} for {$domain}. Expires after 1 week.<br /><br /><a href=\"{$download_url}\">Download Snapshot</a>";
	$headers = [ 'Content-Type: text/html; charset=UTF-8' ];

	// Send email
	wp_mail( $to, $subject, $body, $headers );

}

function captaincore_snapshot_download_link( $snapshot_id ) {
	$command = "snapshot fetch-link $snapshot_id";

	// Disable https when debug enabled
	if ( defined( 'CAPTAINCORE_DEBUG' ) ) {
		add_filter( 'https_ssl_verify', '__return_false' );
	}

	$data = [ 
		'timeout' => 45,
		'headers' => [
			'Content-Type' => 'application/json; charset=utf-8', 
			'token'        => CAPTAINCORE_CLI_TOKEN 
		],
		'body'        => json_encode( [ "command" => $command ] ), 
		'method'      => 'POST', 
		'data_format' => 'body' 
	];

	// Add command to dispatch server
	$response = wp_remote_post( CAPTAINCORE_CLI_ADDRESS . "/run", $data );

	return $response["body"];
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
	$home_url        = esc_url( home_url( '/' ) );
	$new_payment_url = str_replace( $home_url . 'checkout/order-pay/', $home_url . 'checkout-express/', $payment_url );

	return $new_payment_url;
}

// Checks subscription for additional emails
add_filter( 'woocommerce_email_recipient_customer_completed_order', 'woocommerce_email_customer_invoice_add_recipients', 10, 2 );
add_filter( 'woocommerce_email_recipient_customer_completed_renewal_order', 'woocommerce_email_customer_invoice_add_recipients', 10, 2 );
add_filter( 'woocommerce_email_recipient_customer_renewal_invoice', 'woocommerce_email_customer_invoice_add_recipients', 10, 2 );
add_filter( 'woocommerce_email_recipient_customer_invoice', 'woocommerce_email_customer_invoice_add_recipients', 10, 2 );

function woocommerce_email_customer_invoice_add_recipients( $recipient, $order ) {

	// Finds subscription for the order
	$subscription = wcs_get_subscriptions_for_order( $order, [ 'order_type' => [ 'parent', 'renewal' ] ] );

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

	// Finds CaptainCore account
	$account_id = $order->get_meta( 'captaincore_account_id' );
	$account    = ( new CaptainCore\Accounts )->get( $account_id );
	if ( $account ) {
		$plan   = json_decode( $account->plan );
		if ( ! empty( $plan->additional_emails ) ) {
			$recipient .= ", {$plan->additional_emails}";
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

		$this->templates = [];

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
				'theme_page_templates', [ $this, 'add_new_template' ]
			);

		}

		// Add a filter to the save post to inject out template into the page cache
		add_filter( 'wp_insert_post_data', array( $this, 'register_project_templates' ) );

		// Add a filter to the template include to determine if the page has our
		// template assigned and return it's path
		add_filter( 'template_include', array( $this, 'view_project_template' ) );

		// Add your templates to this array.
		$this->templates = [ 'templates/page-checkout-express.php' => 'Checkout Express' ];

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
			$templates = [];
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
add_action( 'plugins_loaded', [ 'PageTemplater', 'get_instance' ], 10 );

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
	$units = [ 'B', 'kB', 'MB', 'GB', 'TB', 'PB', 'EB', 'ZB', 'YB' ];
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

function sort_by_name($a, $b) {
    return strcmp($a["name"], $b["name"]);
}

function captaincore_fetch_socket_address() {
	$captaincore_cli_address = ( defined( "CAPTAINCORE_CLI_ADDRESS" ) ? CAPTAINCORE_CLI_ADDRESS : "" );
	$socket_address          = str_replace( "https://", "wss://", $captaincore_cli_address );
	if ( defined( 'CAPTAINCORE_CLI_SOCKET_ADDRESS' ) ) {
		$socket_address = "wss://" . CAPTAINCORE_CLI_SOCKET_ADDRESS;
	}
	return $socket_address;
}

// Load custom template for web requests going to "/account" or "/account/<..>/..."
add_filter( 'template_include', 'load_captaincore_template' );
function load_captaincore_template( $original_template ) {
  global $wp;
  $configurations    = CaptainCore\Configurations::fetch();
  $request           = explode( '/', $wp->request );
  $current_page      = current( $request );
  $captaincore_pages = ['accounts', 'billing', 'cookbook', 'configurations', 'connect', 'defaults', 'domains', 'handbook', 'health', 'keys', 'login', 'profile', 'sites', 'subscriptions', 'users'];
  if ( class_exists( 'WooCommerce' ) && is_account_page() && end( $request ) == 'my-account' ) {
	wp_redirect( $configurations->path );
  }
  if ( $configurations->path == "/" && in_array( $current_page, $captaincore_pages ) ) {
	header('X-Frame-Options: SAMEORIGIN'); 
    return plugin_dir_path( __FILE__ ) . 'templates/core.php';
  }

  $page = trim( $configurations->path, "/" );
  if ( ( is_page( $page ) || current( $request ) == $page ) && count( $request ) == 1 ) {
	header('X-Frame-Options: SAMEORIGIN'); 
    return plugin_dir_path( __FILE__ ) . 'templates/core.php';
  }
  if ( ( is_page( $page ) || current( $request ) == $page ) && count( $request ) > 1 && in_array( $request[1], $captaincore_pages ) ) {
	header('X-Frame-Options: SAMEORIGIN'); 
	return plugin_dir_path( __FILE__ ) . 'templates/core.php';
}
  return $original_template;
}

// Makes sure that any request going to CaptainCore pages will respond with a proper 200 http code
add_action('init', 'captaincore_rewrite');
function captaincore_rewrite() {
    global $wp_rewrite;
	add_rewrite_rule( '^checkout-express/([^/]*)/?', 'index.php?pagename=checkout-express&callback=$matches[1]', 'top' );
	add_rewrite_tag( '%site%', '([^&]+)' );
	add_rewrite_tag( '%sitetoken%', '([^&]+)' );
	add_rewrite_tag( '%callback%', '([^&]+)' );

	$configurations    = CaptainCore\Configurations::fetch();
	$captaincore_pages = ['accounts', 'billing', 'cookbook', 'configurations', 'connect', 'defaults', 'domains', 'handbook', 'health', 'keys', 'login', 'profile', 'sites', 'subscriptions', 'users'];
	if ( $configurations->path == "/" ) {
		foreach( $captaincore_pages as $captaincore_page ) {
			add_rewrite_rule( "^$captaincore_page/?",'index.php','top');
			add_rewrite_endpoint( $captaincore_page, EP_PERMALINK | EP_PAGES );
		}
	} else {
		$custom_path = trim( $configurations->path, '"' );
		add_rewrite_rule( "^$custom_path/?",'index.php','top');
		add_rewrite_endpoint( $custom_path, EP_PERMALINK | EP_PAGES );
	}
	$wp_rewrite->flush_rules();
}

// Disable 404 redirects when unknown request goes to "/account/<..>/..." which allows a custom template to load. See https://wordpress.stackexchange.com/questions/3326/301-redirect-instead-of-404-when-url-is-a-prefix-of-a-post-or-page-name
add_filter('redirect_canonical', 'disable_404_redirection_for_captaincore');
function disable_404_redirection_for_captaincore($redirect_url) {
	global $wp;
	$configurations    = CaptainCore\Configurations::fetch();
	$captaincore_pages = ['accounts', 'billing', 'cookbook', 'configurations', 'connect', 'defaults', 'domains', 'handbook', 'health', 'keys', 'login', 'profile', 'sites', 'subscriptions', 'users'];
	if ( $configurations->path == "/" ) {
		foreach( $captaincore_pages as $captaincore_page ) {
			if ( strpos( $wp->request, "{$current_page}/" ) !== false ) {
				return false;
			}
		}
	}
	if ( strpos( $wp->request, "checkout-express/" ) !== false ) {
		return false;
	}
	$custom_path = trim($configurations->path, '/'). "/";
	if ( strpos( $wp->request, $custom_path ) !== false ) {
		foreach( $captaincore_pages as $captaincore_page ) {
			if ( strpos( $wp->request, "{$custom_path}/{$current_page}/" ) !== false ) {
				return false;
			}
		}
	}
    return $redirect_url;
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
	if ( isset( $results ) && $results[0] ) {
		foreach( $results[0] as $match ) {
			$output = $output . $match . "\n";
		}
	}
	$output = $output . "</script>\n";
	preg_match_all('/(<link rel="(icon|apple-touch-icon).+)/', $head, $results );
	if ( isset( $results ) && $results[0] ) {
		foreach( $results[0] as $match ) {
			$output = $output . $match . "\n";
		}
	}
	echo $output;
}

function captaincore_footer_content() {
    ob_start();
    do_action( 'wp_footer' );
    return ob_get_clean();
}

function captaincore_footer_content_extracted() {
	$output = [];
	$footer = captaincore_footer_content();
	preg_match_all('/<p id="user_switching_switch_on" .+><a href="(.+?)">(.+)<\/a><\/p>/', $footer, $results );
	if ( isset( $results ) && $results[1] ) {
		foreach( $results[1] as $match ) {
			$output[] = $match;
		}
	}
	if ( isset( $results ) && $results[2] ) {
		foreach( $results[2] as $match ) {
			$output[] = $match;
		}
	}
	return json_encode( [
		"switch_to_link" => html_entity_decode( $output[0] ),
		"switch_to_text" => $output[1]
	] );
}