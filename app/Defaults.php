<?php 

namespace CaptainCore;

class Defaults {

    protected $defaults = [];

    public function __construct( $domain_id = "" ) {
        $this->domain_id = $domain_id;
    }

    public function get() {
        $defaults = json_decode( get_site_option( 'captaincore_defaults' ) );
        if ( empty( $defaults ) ) {
            $defaults = (object) [];
        }
        if ( ! isset( $defaults->users ) ) {
			$defaults->users = [];
		}
		if ( ! isset( $defaults->recipes ) ) {
			$defaults->recipes = [];
        }
        if ( ! isset( $defaults->email ) ) {
			$defaults->email = "";
        }
        if ( ! isset( $defaults->timezone ) ) {
			$defaults->timezone = "";
        }
        
        return $defaults;
    }

    public function update( $field, $value ) {
        $defaults = json_decode( get_site_option( 'captaincore_defaults' ) );
        if ( empty( $defaults ) ) {
            $defaults = (object) [];
        }
        $defaults->{$field} = $value;
        update_site_option( 'captaincore_defaults', json_encode( $defaults ) );
    }

    public function sync() {

        $command = "default-sync";
        
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
        $response = wp_remote_post( CAPTAINCORE_CLI_ADDRESS . "/run/background", $data );
        if ( is_wp_error( $response ) ) {
            $error_message = $response->get_error_message();
            return "Something went wrong: $error_message";
        }
        
        return $response["body"];
    }

}