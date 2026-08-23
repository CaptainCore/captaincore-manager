<?php
/**
 * Rocket.net API WordPress Wrapper
 *
 * API reference: https://rocketdotnet.readme.io/reference/introduction
 *
 * @author   Austin Ginder
 */

namespace CaptainCore\Remote;

class Rocketdotnet {

    private static $base_url = 'https://api.rocket.net/v1';

    private static function headers() {
        $token = \CaptainCore\Providers\Rocketdotnet::credentials("token");
        return [
            'Content-Type'  => 'application/json',
            'Accept'        => 'application/json',
            'Authorization' => "Bearer $token",
        ];
    }

    public static function get( $endpoint, $parameters = [] ) {
        $args = [
            'timeout' => 120,
            'headers' => self::headers(),
        ];
        $url = self::$base_url . "/$endpoint";
        if ( ! empty( $parameters ) ) {
            $url .= "?" . http_build_query( $parameters );
        }
        $remote = wp_remote_get( $url, $args );

        if ( is_wp_error( $remote ) ) {
            return $remote->get_error_message();
        }
        return json_decode( $remote['body'] );
    }

    public static function put( $endpoint, $parameters = [] ) {
        return self::write( 'PUT', $endpoint, $parameters );
    }

    public static function post( $endpoint, $parameters = [] ) {
        return self::write( 'POST', $endpoint, $parameters );
    }

    public static function patch( $endpoint, $parameters = [] ) {
        return self::write( 'PATCH', $endpoint, $parameters );
    }

    public static function delete( $endpoint, $parameters = [] ) {
        return self::write( 'DELETE', $endpoint, $parameters );
    }

    /**
     * Shared writer for PUT/POST/PATCH/DELETE. Sends a JSON body and decodes the response.
     */
    private static function write( $method, $endpoint, $parameters = [] ) {
        $args = [
            'timeout' => 120,
            'headers' => self::headers(),
            'method'  => $method,
        ];
        if ( ! empty( $parameters ) ) {
            $args['body'] = json_encode( $parameters );
        }
        $remote = wp_remote_request( self::$base_url . "/$endpoint", $args );

        if ( is_wp_error( $remote ) ) {
            return $remote->get_error_message();
        }
        return json_decode( $remote['body'] );
    }

}
