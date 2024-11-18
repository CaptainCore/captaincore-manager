<?php

namespace CaptainCore;

class Run {

    protected $account_id = "";

    public static function CLI( $command = "", $background = false ) {

        if ( empty( $command ) ) {
            return;
        }
        
        // Disable https when debug enabled
        if ( defined( 'CAPTAINCORE_DEBUG' ) ) {
            add_filter( 'https_ssl_verify', '__return_false' );
        }

        $data = [ 
            'timeout' => 45,
            'headers' => [
                'Content-Type' => 'application/json; charset=utf-8', 
                'token'        => CAPTAINCORE_CLI_TOKEN 
            ],
            'body'        => json_encode( [ "command" => $command ]),
            'method'      => 'POST',
            'data_format' => 'body'
        ];

        // Add command to dispatch server
        $url = CAPTAINCORE_CLI_ADDRESS . "/run";
        if ( $background ) {
            $url = CAPTAINCORE_CLI_ADDRESS . "/run/background";
        }
        $response = wp_remote_post( $url, $data );
        if ( is_wp_error( $response ) ) {
            $error_message = $response->get_error_message();
            return [];
        }

        return $response["body"];
    }

    public static function task( $command = "" ) {
        
        // Disable https when debug enabled
        if ( defined( 'CAPTAINCORE_DEBUG' ) ) {
            add_filter( 'https_ssl_verify', '__return_false' );
        }
    
        $data = [
            'timeout' => 45,
            'headers' => [
                'Content-Type' => 'application/json; charset=utf-8', 
                'token'        => CAPTAINCORE_CLI_TOKEN 
            ],
            'body'        => json_encode( [ "command" => $command ]), 
            'method'      => 'POST', 
            'data_format' => 'body'
        ];
    
        // Add command to dispatch server
        $response = wp_remote_post( CAPTAINCORE_CLI_ADDRESS . "/tasks", $data );
        if ( is_wp_error( $response ) ) {
            $error_message = $response->get_error_message();
            return "Something went wrong: $error_message";
        }

        $response = json_decode( $response["body"] );
        
        // Response with task id
        if ( $response && $response->token ) { 
            return $response->token; 
        }
    
        return $response["body"];
    }

}