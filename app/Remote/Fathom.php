<?php
/**
 * Fathom API WordPress Wrapper
 *
 * @author   Austin Ginder
 */

namespace CaptainCore\Remote;

class Fathom {

    public static function get( $command, $parameters = [] ) {

        $args = [ 
            "headers" => [ "Authorization" => "Bearer " . \CaptainCore\Providers\Fathom::credentials("api_key") ],
        ];

        if ( count( $parameters ) > 0 ) {
            $command = "{$command}?" . http_build_query( $parameters );
        }

        $response = wp_remote_get( "https://api.usefathom.com/v1/$command", $args );

        if ( is_wp_error( $response ) ) {
            return $response->get_error_message();
        } else {
            return json_decode( $response['body'] );
        }

    }

    public static function post( $command, $parameters = [] ) {

        $args = [ 
            'timeout' => 120,
            "headers" => [
                "Authorization" => "Bearer " . \CaptainCore\Providers\Fathom::credentials("api_key")
            ],
            'body'    => $parameters,
            'method'  => 'POST',
        ];

        $response = wp_remote_post( "https://api.usefathom.com/v1/$command", $args );

        if ( is_wp_error( $response ) ) {
            return $response->get_error_message();
        } else {
            return json_decode( $response['body'] );
        }

    }

}