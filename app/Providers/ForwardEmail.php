<?php 

namespace CaptainCore\Providers;

class ForwardEmail {

    /**
     * Retrieves credentials from the CaptainCore Providers database.
     *
     * @param string $record The specific credential to retrieve (e.g., "api_key").
     * @return mixed The credential value, or all credentials if $record is empty.
     */
    public static function credentials( $record = "" ) {
        // Assumes you have a provider entry with `provider` set to "forwardemail"
        $credentials = ( new \CaptainCore\Provider( "forwardemail" ) )->credentials();
        if ( $record == "" ) {
            return $credentials;
        }
        foreach( $credentials as $credential ) {
            if ( $credential->name == $record ) {
                return $credential->value;
            }
        }
        return null; // Return null if not found
    }

}