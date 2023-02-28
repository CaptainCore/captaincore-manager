<?php 

namespace CaptainCore\Providers;

class Fathom {

    public static function credentials( $record = "" ) {
        $credentials = ( new \CaptainCore\Provider( "fathom" ) )->credentials();
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

