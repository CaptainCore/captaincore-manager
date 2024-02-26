<?php
/**
 * Missive API WordPress Wrapper
 *
 * @author   Austin Ginder
 */

namespace CaptainCore\Remote;

class Missive {

    public static function get( $command ) {

        $args = [
            'timeout' => 120,
            'headers' => [
                'Content-type'  => 'application/json',
                'Authorization' => 'Bearer ' . MISSIVE_API_KEY,
            ],
        ];
    
        $response = wp_remote_get( "https://public.missiveapp.com/v1/$command", $args );
    
        if ( is_wp_error( $response ) ) {
            return $response->get_error_message();
        } else {
            return json_decode( $response['body'] );
        }
    
    }
    
    public static function post( $command, $post ) {
    
        $args = [
            'timeout' => 120,
            'headers' => [
                'Content-type'  => 'application/json',
                'Authorization' => 'Bearer ' . MISSIVE_API_KEY,
            ],
            'body'   => json_encode( $post ),
            'method' => 'POST',
        ];
        $response    = wp_remote_post( "https://public.missiveapp.com/v1/$command", $args );
    
        if ( is_wp_error( $response ) ) {
            return $response->get_error_message();
        } else {
            return json_decode( $response['body'] );
        }
    
    }
}