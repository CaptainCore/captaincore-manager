<?php 

namespace CaptainCore;

class Provider {

	protected $provider_id = "";

    public function __construct( $provider_id = "" ) {
        if ( ! is_numeric( $provider_id ) ) {
            $lookup = ( new Providers )->where( [ "provider" => $provider_id ] );
            if ( count( $lookup ) > 0 ) {
                $provider_id = $lookup[0]->provider_id;
            }
        }
        $this->provider_id = $provider_id;
    }

    public function get() {
        return ( new Providers )->get( $this->provider_id );
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
        return $class_name::verify();
    }

    public function update_token( $token = "" ) {
        $provider = self::get();
        $class_name = "CaptainCore\Providers\\" . ucfirst( $provider->provider );
        return $class_name::update_token( $token );
    }

    public function new_site( $site = [] ) {
        $provider   = self::get();
        $class_name = "CaptainCore\Providers\\" . ucfirst( $provider->provider );
        return $class_name::new_site( $site );
    }

    public function all() {
        $providers = ( new Providers )->all();
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
            "name"        => $provider->name,
            "provider"    => $provider->provider,
            "credentials" => json_encode( $credentials ),
            "created_at"  => $time_now,
            "updated_at"  => $time_now
        ] );
        return $new_provider;

    }

}
