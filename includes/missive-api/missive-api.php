<?php

/**
 * Missive API WordPress Wrapper
 *
 * See readme for setup and coding examples.
 *
 * @author   Austin Ginder <austin@anchor.host>
 */

function missive_api_get( $command ) {

	$args = [
		'timeout' => 120,
		'headers' => [
			'Content-type'       => 'application/json',
			'Authorization'      => 'Bearer ' . MISSIVE_API_KEY,
		],
	];

	$remote = wp_remote_get( "https://public.missiveapp.com/v1/$command", $args );

	if ( is_wp_error( $remote ) ) {
		return $remote->get_error_message();
	} else {
		return json_decode( $remote['body'] );
	}

}

function missive_api_post( $command, $post ) {

	$args = [
		'timeout' => 120,
		'headers' => [
			'Content-type'       => 'application/json',
			'Authorization'      => 'Bearer ' . MISSIVE_API_KEY,
		],
		'body'    => json_encode( $post ),
		'method'  => 'POST',
	];
	$remote    = wp_remote_post( "https://public.missiveapp.com/v1/$command/", $args );

	if ( is_wp_error( $remote ) ) {
		return $remote->get_error_message();
	} else {
		return json_decode( $remote['body'] );
	}

}