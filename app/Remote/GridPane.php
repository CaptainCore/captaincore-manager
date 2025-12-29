<?php

namespace CaptainCore\Remote;

class GridPane {

    private static $api_url = "https://my.gridpane.com/oauth/api/v1";

    /**
     * Helper to get the API token from the provider credentials.
     * Assumes there is a provider entry with the slug 'gridpane' and a credential named 'token'.
     */
    private static function getToken( $provider_id = "" ) {
        if ( ! empty( $provider_id ) ) {
             // Fetch specific provider credential if ID is passed
             return \CaptainCore\Providers\GridPane::credentials( "token", $provider_id );
        }
        // Fetch default credential
        return \CaptainCore\Providers\GridPane::credentials( "token" );
    }

    /**
     * Standard GET request wrapper.
     */
    public static function get( $endpoint, $parameters = [], $provider_id = "" ) {
        $token = self::getToken( $provider_id );
        
        $args = [
            'timeout' => 45,
            'headers' => [
                'Content-Type'  => 'application/json',
                'Authorization' => "Bearer $token",
            ],
        ];

        $url = self::$api_url . "/$endpoint";

        if ( ! empty( $parameters ) ) {
            $url .= "?" . http_build_query( $parameters );
        }

        $response = wp_remote_get( $url, $args );

        if ( is_wp_error( $response ) ) {
            return $response->get_error_message();
        }

        return json_decode( $response['body'] );
    }

    /**
     * Standard POST request wrapper.
     */
    public static function post( $endpoint, $body = [], $provider_id = "" ) {
        $token = self::getToken( $provider_id );

        $args = [
            'timeout' => 45,
            'headers' => [
                'Content-Type'  => 'application/json',
                'Authorization' => "Bearer $token",
            ],
            'body'    => json_encode( $body ),
            'method'  => 'POST',
        ];

        $response = wp_remote_post( self::$api_url . "/$endpoint", $args );

        if ( is_wp_error( $response ) ) {
            return $response->get_error_message();
        }

        return json_decode( $response['body'] );
    }

    /**
     * Standard PUT request wrapper.
     */
    public static function put( $endpoint, $body = [], $provider_id = "" ) {
        $token = self::getToken( $provider_id );

        $args = [
            'timeout' => 45,
            'headers' => [
                'Content-Type'  => 'application/json',
                'Authorization' => "Bearer $token",
            ],
            'body'    => json_encode( $body ),
            'method'  => 'PUT',
        ];

        $response = wp_remote_request( self::$api_url . "/$endpoint", $args );

        if ( is_wp_error( $response ) ) {
            return $response->get_error_message();
        }

        return json_decode( $response['body'] );
    }

    /**
     * Standard DELETE request wrapper.
     */
    public static function delete( $endpoint, $body = [], $provider_id = "" ) {
        $token = self::getToken( $provider_id );

        $args = [
            'timeout' => 45,
            'headers' => [
                'Content-Type'  => 'application/json',
                'Authorization' => "Bearer $token",
            ],
            'method'  => 'DELETE',
        ];

        if ( ! empty( $body ) ) {
            $args['body'] = json_encode( $body );
        }

        $response = wp_remote_request( self::$api_url . "/$endpoint", $args );

        if ( is_wp_error( $response ) ) {
            return $response->get_error_message();
        }

        return json_decode( $response['body'] );
    }

    /* ----------------------------------------------
     * Specific Endpoint Wrappers
     * ---------------------------------------------- */

    public static function get_servers( $provider_id = "" ) {
        return self::get( "server", [], $provider_id );
    }

    public static function get_server( $server_id, $provider_id = "" ) {
        return self::get( "server/$server_id", [], $provider_id );
    }

    public static function get_sites( $provider_id = "" ) {
        return self::get( "site", [], $provider_id );
    }

    public static function get_site( $site_id, $provider_id = "" ) {
        return self::get( "site/$site_id", [], $provider_id );
    }

    public static function get_user( $provider_id = "" ) {
        return self::get( "user", [], $provider_id );
    }

}