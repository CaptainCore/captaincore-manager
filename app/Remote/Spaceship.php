<?php 
/**
 * Spaceship API WordPress Wrapper
 *
 * @author   Austin Ginder
 */

namespace CaptainCore\Remote;

class Spaceship {

    private static $base_url = 'https://spaceship.dev/api/v1';

    /**
     * Endpoints are built by interpolating a stored value - usually a domain
     * name - into an API path, and get() adds its own query string, so no
     * legitimate endpoint here contains one. A value carrying path or query
     * syntax would steer the request to a different registrar resource while
     * still sending the account's key.
     *
     * @param string $endpoint
     * @return bool
     */
    private static function endpoint_is_safe( $endpoint ) {
        $endpoint = (string) $endpoint;
        if ( $endpoint === '' ) {
            return false;
        }
        return ! preg_match( '~[?#\\\\\s]|(^|/)\.\.(/|$)~', $endpoint );
    }

    public static function get( $endpoint, $parameters = [] ) {

        if ( ! self::endpoint_is_safe( $endpoint ) ) {
            return null;
        }

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
        return self::write( 'PUT', $endpoint, $parameters );
    }

    public static function post( $endpoint, $parameters = [] ) {
        return self::write( 'POST', $endpoint, $parameters );
    }

    public static function delete( $endpoint, $parameters = [] ) {
        return self::write( 'DELETE', $endpoint, $parameters );
    }

    /**
     * Shared writer for PUT/POST/DELETE. Sends a JSON body and decodes the response.
     */
    private static function write( $method, $endpoint, $parameters = [] ) {

        if ( ! self::endpoint_is_safe( $endpoint ) ) {
            return null;
        }

        $args = [
            'timeout' => 120,
            'headers' => [
                'Content-type' => 'application/json',
                'X-Api-Secret' => \CaptainCore\Providers\Spaceship::credentials("api_secret"),
                'X-Api-Key'    => \CaptainCore\Providers\Spaceship::credentials("api_key"),
            ],
            'method'  => $method,
        ];
        // Only attach a body when there is one; some endpoints (e.g. DELETE) take none.
        if ( ! empty( $parameters ) ) {
            $args['body'] = json_encode( $parameters );
        }
        $url    = self::$base_url . "/$endpoint";
        $remote = wp_remote_request( $url, $args );

        if ( is_wp_error( $remote ) ) {
            return $remote->get_error_message();
        }
        // Async operations (202) return no body; surface the operation id header instead.
        $body = json_decode( $remote['body'] );
        if ( $body === null && '' === trim( (string) $remote['body'] ) ) {
            $operation_id = wp_remote_retrieve_header( $remote, 'spaceship-async-operationid' );
            return (object) [
                'status'       => wp_remote_retrieve_response_code( $remote ),
                'operation_id' => $operation_id ?: null,
            ];
        }
        return $body;
    }

}
