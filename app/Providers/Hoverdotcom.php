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

}