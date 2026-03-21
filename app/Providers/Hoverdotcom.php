<?php 

namespace CaptainCore\Providers;

class Hoverdotcom {

    public static function credentials( $record = "" ) {
        $credentials = ( new \CaptainCore\Provider( "hoverdotcom" ) )->credentials();
        if ( $record == "" ) {
            return $credentials;
        }
        foreach( $credentials as $credential ) {
            if ( $credential->name == $record ) {
                return $credential->value;
            }
        }
    }

    public static function login() {
        $data = [
            'timeout' => 45,
            'headers' => [
                'Content-Type' => 'application/json; charset=utf-8'
            ],
            'body'        => json_encode( [
                "username" => self::credentials("username"),
                'password' => self::credentials("password")
            ] ),
            'method'      => 'POST',
            'data_format' => 'body'
        ];

        $response    = wp_remote_post( "https://www.hover.com/api/login", $data );
        $cookie_data = json_encode( $response["cookies"] );
        set_transient( 'captaincore_hovercom_auth', $cookie_data, HOUR_IN_SECONDS * 48 );
    }

    public static function fetch_domains() {
        if ( empty( get_transient( 'captaincore_hovercom_auth' ) ) ) {
            self::login();
        }
        $auth = get_transient( 'captaincore_hovercom_auth' );
        $args = [
            'timeout' => 45,
            'headers' => [
                'Cookie' => 'hoverauth=' . $auth
            ]
        ];

        $response = wp_remote_get( "https://www.hover.com/api/control_panel/domains", $args );
        if ( is_wp_error( $response ) ) {
            return [];
        }

        $results = [];
        $domains = json_decode( $response['body'] )->domains ?? [];
        foreach ( $domains as $domain ) {
            $results[] = (object) [
                "name"   => $domain->name,
                "status" => $domain->status,
            ];
        }
        return $results;
    }

}