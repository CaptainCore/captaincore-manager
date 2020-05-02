<?php 

namespace CaptainCore;

class Configurations {

    protected $configurations = [];

    public function get() {
        $configurations = json_decode( get_site_option( 'captaincore_configurations' ) );
        if ( empty( $configurations ) ) {
            $configurations = (object) [];
        }
        if ( ! isset( $configurations->colors ) ) {
			$configurations->colors = [];
        }
        if ( ! isset( $configurations->logo ) ) {
			$configurations->logo = "";
        }
        if ( ! isset( $configurations->hosting_plans ) ) {
			$configurations->hosting_plans = json_decode( get_option('captaincore_hosting_plans') );
        }
        if ( $configurations->dns_introduction ) {
            $Parsedown = new \Parsedown();
			$configurations->dns_introduction_html = $Parsedown->text( $configurations->dns_introduction );
        }
        return $configurations;
    }

    public function colors() {
        $configurations = json_decode( get_site_option( 'captaincore_configurations' ) );
        if ( empty( $configurations ) ) {
            $configurations = (object) [];
        }
        if ( ! isset( $configurations->colors ) ) {
            $configurations->colors = [
                "primary"   => '#1976D2',
                "secondary" => '#424242',
                "accent"    => '#82B1FF',
                "error"     => '#FF5252',
                "info"      => '#2196F3',
                "success"   => '#4CAF50',
                "warning"   => '#FFC107',
            ];
        }
        return $configurations->colors;
    }

    public function hosting_plans() {
        $configurations = json_decode( get_site_option( 'captaincore_configurations' ) );
        if ( empty( $configurations ) ) {
            $configurations = (object) [];
        }
        $configurations->hosting_plans[] = [
            'name'          => 'Custom',
            'price'         => '',
            'limits'        => [
                'visits'  => '',
                'storage' => '',
                'sites'   => '',
            ],
        ];
        return $configurations->hosting_plans;
    }

    public function update( $field, $value ) {
        $configurations = json_decode( get_site_option( 'captaincore_configurations' ) );
        if ( empty( $configurations ) ) {
            $configurations = (object) [];
        }
        $configurations->{$field} = $value;
        update_site_option( 'captaincore_configurations', json_encode( $configurations ) );
    }

    public function sync() {

        $command = "configuration-sync";
        
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