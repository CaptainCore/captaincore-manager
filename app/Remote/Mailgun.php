<?php
/**
 * Mailgun API WordPress Wrapper
 *
 * @author   Austin Ginder
 */

namespace CaptainCore\Remote;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Mailgun {
	
    public static function get( $command, $data = [] ) {

        if ( ! defined( 'MAILGUN_API_KEY' ) ) {
            return (object) [ 'errors' => [ 'MAILGUN_API_KEY constant is not defined.' ] ];
        }

        $args = [
            'timeout' => 120,
            'headers' => [
                'Content-type'       => 'application/json',
                'Authorization'      => 'Basic '. base64_encode( "api:". MAILGUN_API_KEY ),
            ]
        ];

        $query = ( empty( $data ) ) ? "" : "?". http_build_query( $data );

        $remote = wp_remote_get( "https://api.mailgun.net/$command$query", $args );

        if ( is_wp_error( $remote ) ) {
            return (object) [ 'errors' => [ $remote->get_error_message() ] ];
        } else {
            return json_decode( $remote['body'] );
        }

    }

    public static function page( $domain, $page ) {

        if ( ! defined( 'MAILGUN_API_KEY' ) ) {
            return;
        }

        // Validate the host strictly — str_contains would accept an attacker
        // URL like https://evil.tld/?x=https://api.mailgun.net.
        $parts = wp_parse_url( $page );
        if ( empty( $parts['scheme'] ) || $parts['scheme'] !== 'https' || empty( $parts['host'] ) || $parts['host'] !== 'api.mailgun.net' ) {
            return;
        }

        // Scope the request to this zone's own event feed. A substring test over
        // the whole URL is not an authorization check - the zone name can sit in
        // an ignored query parameter while the path addresses another tenant's
        // resource, and the request carries the account-wide API key.
        $path = isset( $parts['path'] ) ? $parts['path'] : '';
        if ( false !== strpos( $path, '..' ) ) {
            return;
        }
        $prefix = '/v3/' . strtolower( (string) $domain ) . '/events';
        if ( 0 !== strpos( strtolower( $path ), $prefix ) ) {
            return;
        }

        $args = [
            'timeout' => 120,
            'headers' => [
                'Content-type'       => 'application/json',
                'Authorization'      => 'Basic '. base64_encode( "api:". MAILGUN_API_KEY ),
            ]
        ];

        $remote = wp_remote_get( "$page", $args );

        if ( is_wp_error( $remote ) ) {
            return $remote->get_error_message();
        } else {
            return json_decode( $remote['body'] );
        }

    }

    public static function post( $command, $post = [] ) {

        if ( ! defined( 'MAILGUN_API_KEY' ) ) {
            return (object) [ 'errors' => [ 'MAILGUN_API_KEY constant is not defined.' ] ];
        }

        // Generate a boundary
        $boundary = wp_generate_password(24, false);
        $body = '';
        foreach ($post as $key => $value) {
            // Handle array values (e.g., action[] for routes)
            if ( is_array( $value ) ) {
                foreach ( $value as $array_value ) {
                    $body .= '--' . $boundary . "\r\n";
                    $body .= 'Content-Disposition: form-data; name="' . $key . "\"\r\n\r\n";
                    $body .= $array_value . "\r\n";
                }
            } else {
                $body .= '--' . $boundary . "\r\n";
                $body .= 'Content-Disposition: form-data; name="' . $key . "\"\r\n\r\n";
                $body .= $value . "\r\n";
            }
        }
        $body .= '--' . $boundary . '--';

        $args = [
            'timeout' => 120,
            'headers' => [
                'Content-type'  => "multipart/form-data; boundary=$boundary",
                'Authorization' => 'Basic '. base64_encode( "api:". MAILGUN_API_KEY ),
            ],
            'body'    => $body,
            'method'  => 'POST',
        ];
        $remote    = wp_remote_post( "https://api.mailgun.net/$command", $args );

        if ( is_wp_error( $remote ) ) {
            return (object) [ 'errors' => [ $remote->get_error_message() ] ];
        } else {
            return json_decode( $remote['body'] );
        }

    }

    public static function put( $command, $post = [] ) {

        if ( ! defined( 'MAILGUN_API_KEY' ) ) {
            return (object) [ 'errors' => [ 'MAILGUN_API_KEY constant is not defined.' ] ];
        }

         // Generate a boundary
         $boundary = wp_generate_password(24, false);
         $body = '';
         foreach ($post as $key => $value) {
             // Handle array values (e.g., action[] for routes)
             if ( is_array( $value ) ) {
                 foreach ( $value as $array_value ) {
                     $body .= '--' . $boundary . "\r\n";
                     $body .= 'Content-Disposition: form-data; name="' . $key . "\"\r\n\r\n";
                     $body .= $array_value . "\r\n";
                 }
             } else {
                 $body .= '--' . $boundary . "\r\n";
                 $body .= 'Content-Disposition: form-data; name="' . $key . "\"\r\n\r\n";
                 $body .= $value . "\r\n";
             }
         }
         $body .= '--' . $boundary . '--';

        $args = [
            'timeout' => 120,
            'headers' => [
                'Content-type'  => "application/json",
                'Authorization' => 'Basic '. base64_encode( "api:". MAILGUN_API_KEY ),
            ],
            'method'  => 'PUT',
        ];
        if ( ! empty( $post ) ) {
            $args['headers']['Content-type'] =  "multipart/form-data; boundary=$boundary";
            $args['body'] = $body;
        }
        $remote    = wp_remote_post( "https://api.mailgun.net/$command", $args );

        if ( is_wp_error( $remote ) ) {
            return (object) [ 'errors' => [ $remote->get_error_message() ] ];
        } else {
            return json_decode( $remote['body'] );
        }

    }

    public static function delete( $command, $data = [] ) {

        if ( ! defined( 'MAILGUN_API_KEY' ) ) {
            return (object) [ 'errors' => [ 'MAILGUN_API_KEY constant is not defined.' ] ];
        }

        $args = [
            'timeout' => 120,
            'headers' => [
                'Content-type'       => 'application/json',
                'Authorization'      => 'Basic '. base64_encode( "api:". MAILGUN_API_KEY ),
            ],
            'method'  => 'DELETE',
        ];

        $query = ( empty( $data ) ) ? "" : "?". http_build_query( $data );

        $remote = wp_remote_request( "https://api.mailgun.net/$command$query", $args );

        if ( is_wp_error( $remote ) ) {
            return (object) [ 'errors' => [ $remote->get_error_message() ] ];
        } else {
            return json_decode( $remote['body'] );
        }

    }

}