<?php 
/**
 * Spaceship API WordPress Wrapper
 *
 * @author   Austin Ginder
 */

namespace CaptainCore\Remote;

class Spaceship {

    private static $base_url = 'https://spaceship.dev/api/v1';

    public static function get( $endpoint, $parameters = [] ) {
        $args      = [
            'timeout' => 120,
            'headers' => [
                'X-Api-Secret' => \CaptainCore\Providers\Spaceship::credentials("api_secret"),
                'X-Api-Key'    => \CaptainCore\Providers\Spaceship::credentials("api_key"),
            ],
        ];
        $url    = self::$base_url . "/$endpoint";
        if ( ! empty( $parameters ) ) {
            $url .= "?" . http_build_query( $parameters );
        }
        $remote = wp_remote_get( $url, $args );
    
        if ( is_wp_error( $remote ) ) {
            return $remote->get_error_message();
        } else {
            return json_decode( $remote['body'] );
        }
    }

    public static function put( $endpoint, $parameters = [] ) {
        $args      = [
            'timeout' => 120,
            'headers' => [
                'Content-type' => 'application/json',
                'X-Api-Secret' => \CaptainCore\Providers\Spaceship::credentials("api_secret"),
                'X-Api-Key'    => \CaptainCore\Providers\Spaceship::credentials("api_key"),
            ],
            'body'    => json_encode( $parameters ),
            'method'  => 'PUT',
        ];
        $url    = self::$base_url . "/$endpoint";
        $remote = wp_remote_post( $url, $args );
    
        if ( is_wp_error( $remote ) ) {
            return $remote->get_error_message();
        } else {
            return json_decode( $remote['body'] );
        }
    }

}