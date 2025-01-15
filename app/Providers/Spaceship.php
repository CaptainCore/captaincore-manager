<?php 

namespace CaptainCore\Providers;

class Spaceship {

    public static function credentials( $record = "" ) {
        $credentials = ( new \CaptainCore\Provider( "spaceship" ) )->credentials();
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