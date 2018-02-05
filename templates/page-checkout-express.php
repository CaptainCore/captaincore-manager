<?php
/**
 * Template Name: Checkout Express
 */

$order_id = get_query_var( 'page' );
$key      = $_GET['key'];

// Loads user and key from order
$customer_id = get_field( '_customer_user', $order_id );
$order_key   = get_field( '_order_key', $order_id );

// Loads order
$order = new WC_Order( $order_id );

// Fetch payment url
$payment_url = $order->get_checkout_payment_url();

// Fetch user
$user = get_user_by( 'id', $customer_id );

if ( $user and $order_key == $key ) {

	// Login as new user
	wp_set_current_user( $user->ID, $user->user_login );
	wp_set_auth_cookie( $user->ID );

	// Redirect to payment url
	wp_redirect( $payment_url );

} else {

	// Redirect to homepage
	wp_redirect( get_home_url() );

}
