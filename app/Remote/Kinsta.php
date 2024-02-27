<?php 

namespace CaptainCore\Remote;

class Kinsta {

    public static function get( $endpoint, $parameters = [] ) {
        $api_key = \CaptainCore\Providers\Kinsta::credentials("api");
        $data    = [
            'timeout' => 45,
            'headers' => [
                'Content-Type'  => 'application/json',
                'Authorization' => "Bearer $api_key",
            ],
        ];
        if ( ! empty( $parameters ) ) {
            $data['body'] = json_encode( $parameters );
        }
        $response = wp_remote_get( "https://api.kinsta.com/v2/$endpoint", $data );
        if ( is_wp_error( $response ) ) {
            return false;
        }
        $response = json_decode( $response['body'] );
        return $response;
    }

    public static function post( $endpoint, $parameters = [] ) {
        $api_key = \CaptainCore\Providers\Kinsta::credentials("api");
        $data    = [
            'timeout' => 45,
            'headers' => [
                'Content-Type'  => 'application/json',
                'Authorization' => "Bearer $api_key",
            ],
        ];
        if ( ! empty( $parameters ) ) {
            $data['body'] = json_encode( $parameters );
        }
        $response = wp_remote_post( "https://api.kinsta.com/v2/$endpoint", $data );
        if ( is_wp_error( $response ) ) {
            return false;
        }
        $response = json_decode( $response['body'] );
        return $response;
    }

    public static function put( $endpoint, $parameters = [] ) {
        $api_key = \CaptainCore\Providers\Kinsta::credentials("api");
        $data    = [
            'timeout' => 45,
            'headers' => [
                'Content-Type'  => 'application/json',
                'Authorization' => "Bearer $api_key",
            ],
            'method'  => 'PUT',
        ];
        if ( ! empty( $parameters ) ) {
            $data['body'] = json_encode( $parameters );
        }
        $response = wp_remote_post( "https://api.kinsta.com/v2/$endpoint", $data );
        if ( is_wp_error( $response ) ) {
            return false;
        }
        $response = json_decode( $response['body'] );
        return $response;
    }

}