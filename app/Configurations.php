<?php 

namespace CaptainCore;

class Configurations {

    protected $configurations = [];

    public static function get() {
        $configurations = json_decode( get_site_option( 'captaincore_configurations' ) );
        if ( empty( $configurations ) ) {
            $configurations = (object) [];
        }
        if ( defined( 'CAPTAINCORE_CUSTOM_DOMAIN' ) ) {
            $account_portals = ( new AccountPortals )->where( [ 'domain' => CAPTAINCORE_CUSTOM_DOMAIN ] );
            foreach( $account_portals as $account_portal ) {
                $portal_config                    = json_decode( $account_portal->configurations );
                $configurations->path             = "/";
                $configurations->name             = $portal_config->name;
                $configurations->colors           = $portal_config->colors;
                $configurations->logo_only        = $portal_config->logo_only == "true" ? true : false;
                $configurations->logo             = $portal_config->logo;
                $configurations->logo_width       = $portal_config->logo_width;
                $configurations->dns_introduction = $portal_config->dns_introduction;
                $configurations->dns_nameservers  = $portal_config->dns_nameservers;
                $configurations->url              = $portal_config->url;
            }
        }
        if ( empty ( $configurations->colors ) ) {
			$configurations->colors = [
                "primary"   => '#2c3e50',
                "secondary" => '#424242',
                "accent"    => '#82B1FF',
                "error"     => '#FF5252',
                "info"      => '#2196F3',
                "success"   => '#4CAF50',
                "warning"   => '#FFC107',
            ];
        }
        if ( ! isset( $configurations->path ) ) {
			$configurations->path = "/account/";
        }
        if ( ! isset( $configurations->mode ) ) {
			$configurations->mode = "hosting";
        }
        if ( ! isset( $configurations->remote_upload_uri ) ) {
			$configurations->remote_upload_uri = get_option( 'options_remote_upload_uri' );
        }
        if ( ! isset( $configurations->logo_only ) ) {
			$configurations->logo_only = false;
        }
        if ( empty( $configurations->logo ) ) {
			$configurations->logo = "/wp-content/plugins/captaincore-manager/public/logo.webp";
        }
        if ( empty( $configurations->logo_width ) ) {
            $configurations->logo_width = "32";
        }
        if ( ! isset( $configurations->name ) ) {
			$configurations->name = "CaptainCore";
        }
        if ( ! isset( $configurations->scheduled_tasks ) ) {
			$configurations->scheduled_tasks = [];
        }
        if ( ! isset( $configurations->woocommerce ) ) {
			$configurations->woocommerce = (object) [ "hosting_plan" => "", "addons" => "", "usage" => "" ];
        }
        if ( ! isset( $configurations->hosting_plans ) ) {
			$configurations->hosting_plans = json_decode( get_option('captaincore_hosting_plans') );
        }
        if ( ! isset( $configurations->usage_pricing ) ) {
			$configurations->usage_pricing = (object) [ "sites" => [ "quantity" => "1", "cost" => "12.5" ], "storage" => [ "quantity" => "10", "cost" => "10" ], "traffic" => [ "quantity" => "1000000", "cost" => "100" ] ];
        }
        if ( ! isset( $configurations->maintenance_pricing ) ) {
			$configurations->maintenance_pricing = (object) [ "interval" => "1", "cost" => "2" ];
        }
        if ( ! isset( $configurations->intercom_embed_id ) ) {
			$configurations->intercom_embed_id = "";
        }
        if ( ! isset( $configurations->intercom_secret_key ) ) {
			$configurations->intercom_secret_key = "";
        }
        if ( isset( $configurations->dns_introduction ) ) {
            $Parsedown = new \Parsedown();
			$configurations->dns_introduction_html = $Parsedown->text( $configurations->dns_introduction );
        }
        if ( $configurations->scheduled_tasks ) {
			foreach ( $configurations->scheduled_tasks as $task ) {
                $task->date_selector = false;
            }
        }
        return $configurations;
    }

    public static function colors() {
        $configurations = json_decode( get_site_option( 'captaincore_configurations' ) );
        if ( empty( $configurations ) ) {
            $configurations = (object) [];
        }
        if ( defined( 'CAPTAINCORE_CUSTOM_DOMAIN' ) ) {
            $account_portals = ( new AccountPortals )->where( [ 'domain' => CAPTAINCORE_CUSTOM_DOMAIN ] );
            foreach( $account_portals as $account_portal ) {
                $portal_config = json_decode( $account_portal->configurations );
                return $portal_config->colors;
            }
        }
        if ( empty ( $configurations->colors ) ) {
            $configurations->colors = [
                "primary"   => '#2c3e50',
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

    public function update_field( $field, $value ) {
        $configurations = json_decode( get_site_option( 'captaincore_configurations' ) );
        if ( empty( $configurations ) ) {
            $configurations = (object) [];
        }
        $configurations->{$field} = $value;
        update_site_option( 'captaincore_configurations', json_encode( $configurations ) );
    }

    public function update( $items ) {
        
        $configurations = json_decode( get_site_option( 'captaincore_configurations' ) );
        if ( empty( $configurations ) ) {
            $configurations = (object) [];
        }
        foreach( $items as $key => $value ) {
            $configurations->{$key} = $value;
        }
		
        update_site_option( 'captaincore_configurations', json_encode( $configurations ) );
        self::sync();
        return $configurations;
    }

    public function sync() {
        
        $command = "configuration sync";
        
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

    public static function products() {
        $products = [];
        $products_IDs = new \WP_Query( [
            'post_type'      => 'product',
            'posts_per_page' => -1,
        ] );
    
        while ($products_IDs->have_posts() ) : $products_IDs->the_post();

            global $product;
            $products[] = [
                "id"   => "{$product->id}",
                "name" => $product->get_title(),
            ];
        endwhile;

        return $products;
    }

    public static function fetch() {
        return ( new Configurations )->get();
    }

    public static function get_json() {
        return json_encode( ( new Configurations )->get() );
    }

}