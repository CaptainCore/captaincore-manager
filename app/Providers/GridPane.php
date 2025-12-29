<?php 

namespace CaptainCore\Providers;

class GridPane {

    public static function credentials( $record = "", $provider_id = "" ) {
        // If a specific provider ID is passed (multi-provider support), use it.
        // Otherwise defaults to the first provider found with slug 'gridpane'.
        if ( ! empty( $provider_id ) ) {
            $provider    = \CaptainCore\Providers::get( $provider_id );
            $credentials = ! empty( $provider->credentials ) ? json_decode( $provider->credentials ) : [];
        } else {
            $credentials = ( new \CaptainCore\Provider( "gridpane" ) )->credentials();
        }

        if ( $record == "" ) {
            return $credentials;
        }

        foreach( $credentials as $credential ) {
            if ( $credential->name == $record ) {
                return $credential->value;
            }
        }
    }
}