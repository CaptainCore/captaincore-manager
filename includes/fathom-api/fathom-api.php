<?php

/**
 * Fathom API WordPress Wrapper
 *
 * @author   Austin Ginder
 */

function fathom_api_get( $command, $parameters = [] ) {

    $args = [ 
        "headers" => [ "Authorization" => "Bearer " . FATHOM_API_KEY ],
    ];

    if ( count( $parameters ) > 0 ) {
        $command = "{$command}?" . http_build_query( $parameters );
    }

    $remote = wp_remote_get( "https://api.usefathom.com/v1/$command", $args );

	if ( is_wp_error( $remote ) ) {
		return $remote->get_error_message();
	} else {
		return json_decode( $remote['body'] );
	}

}
