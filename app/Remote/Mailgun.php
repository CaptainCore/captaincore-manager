<?php
/**
 * Mailgun API WordPress Wrapper
 *
 * @author   Austin Ginder
 */

namespace CaptainCore\Remote;

class Mailgun {
	
    public static function get( $command, $data = [] ) {

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
            return $remote->get_error_message();
        } else {
            return json_decode( $remote['body'] );
        }

    }

    public static function page( $domain, $page ) {

        if ( ! str_contains( $page, "https://api.mailgun.net" ) ) {
            return;
        }

        if ( ! str_contains( $page, $domain ) ) {
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

        $args = [
            'timeout' => 120,
            'headers' => [
                'Content-type'       => 'multipart/form-data',
                'Authorization'      => 'Basic '. base64_encode( "api:". MAILGUN_API_KEY ),
            ],
            'body'    => $post,
            'method'  => 'POST',
        ];
        $remote    = wp_remote_post( "https://api.mailgun.net/$command", $args );

        if ( is_wp_error( $remote ) ) {
            return $remote->get_error_message();
        } else {
            return json_decode( $remote['body'] );
        }

    }

    public static function put( $command, $post = [] ) {

        $args = [
            'timeout' => 120,
            'headers' => [
                'Content-type'  => 'application/json',
                'Authorization' => 'Basic '. base64_encode( "api:". MAILGUN_API_KEY ),
            ],
            'method'  => 'PUT',
        ];
        if ( ! empty( $post ) ) {
            $args['body'] = $post;
        }
        $remote    = wp_remote_post( "https://api.mailgun.net/$command", $args );

        if ( is_wp_error( $remote ) ) {
            return $remote->get_error_message();
        } else {
            return json_decode( $remote['body'] );
        }

    }

}