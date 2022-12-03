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

}
