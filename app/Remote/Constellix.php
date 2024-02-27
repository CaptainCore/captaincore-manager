<?php 
/**
 * Constellix API WordPress Wrapper
 *
 * @author   Austin Ginder
 */

namespace CaptainCore\Remote;

class Constellix {

    public static function get( $endpoint, $parameters = [] ) {
        $timestamp = round( microtime( true ) * 1000 );
        $hmac      = base64_encode( hash_hmac( 'sha1', $timestamp, CONSTELLIX_SECRET_KEY, true ) );
        $args      = [
            'timeout' => 120,
            'headers' => [
                'Content-type'         => 'application/json',
                'x-cnsdns-apiKey'      => CONSTELLIX_API_KEY,
                'x-cnsdns-hmac'        => $hmac,
                'x-cnsdns-requestDate' => $timestamp,
            ],
        ];
        $remote    = wp_remote_get( "https://api.dns.constellix.com/v4/$endpoint", $args );
    
        if ( is_wp_error( $remote ) ) {
            return $remote->get_error_message();
        } else {
            return json_decode( $remote['body'] );
        }
    }

    public static function post( $endpoint, $parameters = [] ) {

        $timestamp = round( microtime( true ) * 1000 );
        $hmac      = base64_encode( hash_hmac( 'sha1', $timestamp, CONSTELLIX_SECRET_KEY, true ) );
        $args      = [
            'timeout' => 120,
            'headers' => [
                'Content-type'         => 'application/json',
                'x-cnsdns-apiKey'      => CONSTELLIX_API_KEY,
                'x-cnsdns-hmac'        => $hmac,
                'x-cnsdns-requestDate' => $timestamp,
            ],
            'body'    => json_encode( $parameters ),
            'method'  => 'POST',
        ];
        $response   = wp_remote_post( "https://api.dns.constellix.com/v4/$endpoint", $args );
    
        if ( is_wp_error( $response ) ) {
            return $response->get_error_message();
        } else {
            return json_decode( $response['body'] );
        }
    
    }
    
    public static function put( $endpoint, $parameters ) {
    
        $timestamp = round( microtime( true ) * 1000 );
        $hmac      = base64_encode( hash_hmac( 'sha1', $timestamp, CONSTELLIX_SECRET_KEY, true ) );
        $args      = [
            'timeout' => 120,
            'headers' => [
                'Content-type'         => 'application/json',
                'x-cnsdns-apiKey'      => CONSTELLIX_API_KEY,
                'x-cnsdns-hmac'        => $hmac,
                'x-cnsdns-requestDate' => $timestamp,
            ],
            'body'    => json_encode( $parameters ),
            'method'  => 'PUT',
        ];
        $response  = wp_remote_post( "https://api.dns.constellix.com/v4/$endpoint", $args );
    
        if ( is_wp_error( $response ) ) {
            return $response->get_error_message();
        } else {
            return json_decode( $response['body'] );
        }
    
    }
    
    public static function delete( $endpoint ) {
    
        $timestamp = round( microtime( true ) * 1000 );
        $hmac      = base64_encode( hash_hmac( 'sha1', $timestamp, CONSTELLIX_SECRET_KEY, true ) );
        $args      = [
            'timeout' => 120,
            'headers' => [
                'Content-type'         => 'application/json',
                'x-cnsdns-apiKey'      => CONSTELLIX_API_KEY,
                'x-cnsdns-hmac'        => $hmac,
                'x-cnsdns-requestDate' => $timestamp,
            ],
            'method'  => 'DELETE',
        ];
        $response  = wp_remote_post( "https://api.dns.constellix.com/v4/$endpoint", $args );
    
        if ( is_wp_error( $response ) ) {
            return $response->get_error_message();
        } else {
            $http_code = wp_remote_retrieve_response_code( $response );
            if ( $http_code == "204" ) {
                return (object)[ "message" => "Record deleted" ];
            }
            return json_decode( $response['body'] );
        }
    
    }

}