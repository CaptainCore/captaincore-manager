<?php

/**
 * Template Name: Backup
 */

$callback = get_query_var('callback');
$link = $_GET['link'];
$token = $_GET['token'];
$auth = $_GET['auth'];

$args = array (
	'post_type'              => 'website',
	's'         			 => $callback ,
	'posts_per_page' 		 => 1
);

$query = new WP_Query( $args );

// The Loop
if ( $query->have_posts() ) {
	while ( $query->have_posts() ) {
		$query->the_post();
		// do something
		$site_id = get_the_id();
	}
} else {
	// no posts found
}

$result_count = $query->post_count;

// Restore original Post Data
wp_reset_postdata(); 

// No website found. Generate a new record. 
if ($result_count == 0) {
	// Create post object
	$my_post = array(
	  'post_title'    => $callback,
	  'post_type'     => 'website',
	  'post_status'   => 'publish',
	  'post_author'   => 2,
	);

	// Insert the post into the database
	$site_id = wp_insert_post( $my_post );

	// Update the post into the database
	anchor_acf_save_post_after($site_id);
	echo "Generated new website $callback. ";
}

if ($auth == "***REMOVED***") {

	// defines the ACF keys to use
	$link_key = "field_560849a5eea95";
	$token_key = "field_52d16819ac39f";

	// update the repeater
	update_field($link_key,$link,$site_id);
	update_field($token_key,$token,$site_id);
	echo "Updating fields. \n";

 } else { 
 	echo "Token Error";

 }