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
        $url    = "https://api.dns.constellix.com/v4/$endpoint";
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

    /**
     * Performs a GET request and returns the raw response with headers and body.
     *
     * @param string $endpoint The API endpoint (e.g., 'domains').
     * @param array $parameters Optional query parameters.
     * @return object|WP_Error An object with 'headers' and 'body' properties, or WP_Error on failure.
     */
    public static function get_raw( $endpoint, $parameters = [] ) {
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
        $url    = "https://api.dns.constellix.com/v4/$endpoint";
        if ( ! empty( $parameters ) ) {
            $url .= "?" . http_build_query( $parameters );
        }
        
        $remote = wp_remote_get( $url, $args );
    
        if ( is_wp_error( $remote ) ) {
            return $remote; // Return the full WP_Error object for better debugging
        } else {
            // Success: return an object with headers and the decoded body
            return (object) [
                'headers' => wp_remote_retrieve_headers( $remote ),
                'body'    => json_decode( wp_remote_retrieve_body( $remote ) )
            ];
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

    /**
     * Performs a POST request and returns the raw response with headers and body.
     */
    public static function post_raw( $endpoint, $parameters = [] ) {
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
        
        $remote = wp_remote_post( "https://api.dns.constellix.com/v4/$endpoint", $args );
    
        if ( is_wp_error( $remote ) ) {
            return $remote;
        } else {
            return (object) [
                'headers' => wp_remote_retrieve_headers( $remote ),
                'body'    => json_decode( wp_remote_retrieve_body( $remote ) )
            ];
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

    /**
     * Performs a PUT request and returns the raw response with headers and body.
     */
    public static function put_raw( $endpoint, $parameters ) {
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

        $remote = wp_remote_post( "https://api.dns.constellix.com/v4/$endpoint", $args );
    
        if ( is_wp_error( $remote ) ) {
            return $remote;
        } else {
            return (object) [
                'headers' => wp_remote_retrieve_headers( $remote ),
                'body'    => json_decode( wp_remote_retrieve_body( $remote ) )
            ];
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

    /**
     * Performs a DELETE request and returns the raw response with headers and body.
     */
    public static function delete_raw( $endpoint ) {
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

        $remote = wp_remote_post( "https://api.dns.constellix.com/v4/$endpoint", $args );
    
        if ( is_wp_error( $remote ) ) {
            return $remote;
        } else {
            return (object) [
                'headers' => wp_remote_retrieve_headers( $remote ),
                'body'    => json_decode( wp_remote_retrieve_body( $remote ) )
            ];
        }
    }

    public static function all( $endpoint ) {
         // Load Constellix domains from transient
         $constellix_domains = get_transient( 'constellix_all_domains' );

         // If empty then update transient with large remote call
         if ( empty( $constellix_domains ) ) {

            $domains  = [];
            $response = self::get( $endpoint, [ "perPage" => 100 ] );
            // Check for rate limit error on first call
            if ( is_object($response) && isset($response->message) && $response->message == "You have made too many requests, please wait and try again later" ) {
                error_log("CaptainCore: Constellix::all rate-limited on initial pull.");
                return []; // Return empty array to avoid errors
            }
            if ( !is_object($response) || !isset($response->data) ) {
                error_log("CaptainCore: Constellix::all received invalid response on initial pull: " . json_encode($response));
                return []; // Return empty array
            }

            $domains  = array_merge( $domains, $response->data );
            $page     = $response->meta->pagination->currentPage;
            $total    = $response->meta->pagination->total;

            if ( ! empty( $response->meta->pagination ) ) {
                do {
                    $page++;
                    $response = self::get( $endpoint, [ "page" => $page, "perPage" => 100 ] );
                    if ( ! empty( $response->data ) ) {
                        $domains  = array_merge( $domains, $response->data );
                    }
                } while ( $total > ( $page * 100 ) );
            }
            set_transient( 'constellix_all_domains', $domains, HOUR_IN_SECONDS );
        }
        return get_transient( 'constellix_all_domains' );
    }

}