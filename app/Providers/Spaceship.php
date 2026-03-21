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

    public static function fetch_domains() {
        $results = [];
        $skip    = 0;
        $take    = 100;
        do {
            $response = \CaptainCore\Remote\Spaceship::get( "domains", [ "take" => $take, "skip" => $skip ] );
            if ( empty( $response->items ) ) {
                break;
            }
            foreach ( $response->items as $item ) {
                $results[] = (object) [
                    "name"   => $item->name,
                    "status" => $item->lifecycleStatus,
                ];
            }
            $skip += $take;
        } while ( count( $response->items ) == $take );
        return $results;
    }

}