<?php 

namespace CaptainCore;

class Provider {

	protected $provider_id = "";

    public function __construct( $provider_id = "" ) {
        if ( ! is_numeric( $provider_id ) ) {
            $lookup = Providers::where( [ "provider" => $provider_id ] );
            if ( count( $lookup ) > 0 ) {
                $last_item_key = count($lookup) -1;
                $provider_id   = $lookup[ $last_item_key ]->provider_id;
            }
        }
        $this->provider_id = $provider_id;
    }

    public function get() {
        return ( new Providers )->get( $this->provider_id );
    }

    public function verify_ownership() {
        $user = new User();
        if ( $user->is_admin() ) {
            return true;
        }
        $provider = self::get();
        if ( ! $provider ) {
            return false;
        }
        if ( $provider->user_id == 0 ) {
            return false;
        }
        return $provider->user_id == get_current_user_id();
    }

    public function credentials() {
        $provider = ( new Providers )->get( $this->provider_id );
        if ( ! empty( $provider->credentials ) ) {
            return json_decode( $provider->credentials );
        }
    }

    public function verify() {
        $provider   = self::get();
        $class_name = "CaptainCore\Providers\\" . ucfirst( $provider->provider );
        return $class_name::verify( $this->provider_id );
    }

    public function deploy_to_staging( $site_id ) {
        $provider   = self::get();
        $class_name = "CaptainCore\Providers\\" . ucfirst( $provider->provider );
        return $class_name::deploy_to_staging( $site_id );
    }

    public function deploy_to_production( $site_id ) {
        $provider   = self::get();
        $class_name = "CaptainCore\Providers\\" . ucfirst( $provider->provider );
        return $class_name::deploy_to_production( $site_id );
    }

    public function update_token( $token = "" ) {
        $provider   = self::get();
        $class_name = "CaptainCore\Providers\\" . ucfirst( $provider->provider );
        return $class_name::update_token( $token, $this->provider_id );
    }

    public function new_site( $site = [] ) {
        $provider   = self::get();
        $class_name = "CaptainCore\Providers\\" . ucfirst( $provider->provider );
        return $class_name::new_site( $site );
    }

    public function get_push_targets( $source_site_id, $source_environment_id ) {
		return $this->call_static_method( 'get_push_targets', [ $source_site_id, $source_environment_id ] );
	}

	public function push_environment( $source_environment_id, $target_environment_id ) {
		return $this->call_static_method( 'push_environment', [ $source_environment_id, $target_environment_id ] );
	}

    public function all() {
        $user = new User();
        if ( $user->is_admin() ) {
            $providers = ( new Providers )->all();
        } else {
            $providers = Providers::where( [ "user_id" => get_current_user_id() ] );
        }
        foreach( $providers as $provider ) {
            if ( ! empty( $provider->credentials ) ) {
                $provider->credentials = json_decode( $provider->credentials );
            }
        }
        return $providers;
    }

    public function create( $provider ) {

        $provider    = (object) $provider;
        $credentials = []; 

        // Prep for response to return
        $response = [ "errors" => [] ];

        // Pull in current user
        $current_user = wp_get_current_user();

        // Validate
        if ( $provider->name == '' ) {
            $response['errors'][] = "Error: Provider name can't be empty.";
        }
        if ( $provider->provider == '' ) {
            $response['errors'][] = "Error: Provider can't be empty.";
        }

        if ( count($response['errors']) > 0 ) {
            return $response;
        }

        if ( is_array( $provider->credentials ) ) {
            foreach ( $provider->credentials as $credential ) {
                $credential = (object) $credential;
                if ( ! empty( $credential->name ) && ! empty( $credential->value ) ) {
                    $credentials[] = [ "name" => $credential->name, "value" => $credential->value ];
                }
            }
        }
        $time_now         = date("Y-m-d H:i:s");
        $new_provider     = ( new Providers )->insert( [
            "user_id"     => get_current_user_id(),
            "name"        => $provider->name,
            "provider"    => $provider->provider,
            "credentials" => json_encode( $credentials ),
            "created_at"  => $time_now,
            "updated_at"  => $time_now
        ] );
        return $new_provider;

    }

    private function get_provider_class_name() {
        $provider = self::get();
        if ( ! $provider || empty( $provider->provider ) ) {
            return null;
        }
        return "CaptainCore\Providers\\" . ucfirst( $provider->provider );
    }

    private function call_static_method( $method, $args = [] ) {
        $class_name = $this->get_provider_class_name();
        if ( ! $class_name || ! method_exists( $class_name, $method ) ) {
            return new \WP_Error( 'not_supported', "Provider '{$class_name}' does not support method '{$method}'." );
        }
        return call_user_func_array( [ $class_name, $method ], $args );
    }

    public function get_domains( $site_id, $env_name ) {
        return $this->call_static_method( 'get_domains', [ $site_id, $env_name ] ); // Changed from self::call_static_method()
    }

    public function add_domain( $site_id, $env_name, $params ) {
        return $this->call_static_method( 'add_domain', [ $site_id, $env_name, $params ] ); // Changed from self::call_static_method()
    }

    public function delete_domain( $site_id, $env_name, $params ) {
        return $this->call_static_method( 'delete_domain', [ $site_id, $env_name, $params ] ); // Changed from self::call_static_method()
    }

    public function set_primary_domain( $site_id, $env_name, $params ) {
        return $this->call_static_method( 'set_primary_domain', [ $site_id, $env_name, $params ] ); // Changed from self::call_static_method()
    }

}
