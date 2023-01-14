<?php 

namespace CaptainCore\Providers;

class Rocketdotnet {

    public static function credentials( $record = "" ) {
        $credentials = ( new \CaptainCore\Provider( "rocketdotnet" ) )->credentials();
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