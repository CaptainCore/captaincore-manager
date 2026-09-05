<?php 

namespace CaptainCore\Remote;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Kinsta {

    private static $api_key;

    public static function setApiKey( $key ) {
        // credentials() returns null to REFUSE a provider row that is neither
        // the caller's nor the shared house row. Storing that as-is was
        // indistinguishable from "never set", so getApiKey() fell through to
        // the house key and spent it anyway - the guard failed open. Keep the
        // refusal distinguishable.
        self::$api_key = ( $key === null || $key === '' ) ? false : $key;
    }

    private static function getApiKey() {
        // false means a provider row was explicitly refused - do not substitute
        // the house key for it. null still means "never set", which keeps the
        // default-provider path working.
        if ( self::$api_key === false ) {
            return false;
        }
        return self::$api_key ?? \CaptainCore\Providers\Kinsta::credentials("api");
    }

    /**
     * Endpoints are built by interpolating stored ids into an API path, so a
     * value carrying path or query syntax would steer the request to another
     * resource while still sending the account's bearer token. Dot segments and
     * fragments never appear in a Kinsta path, and no endpoint this client uses
     * has a slash after its query string.
     *
     * @param string $endpoint
     * @return bool
     */
    private static function endpoint_is_safe( $endpoint ) {
        $endpoint = (string) $endpoint;
        if ( false !== strpos( $endpoint, '..' ) || false !== strpos( $endpoint, '#' ) ) {
            return false;
        }
        $query = strpos( $endpoint, '?' );
        if ( false !== $query && false !== strpos( substr( $endpoint, $query ), '/' ) ) {
            return false;
        }
        return true;
    }

    public static function get( $endpoint, $parameters = [] ) {

        if ( ! self::endpoint_is_safe( $endpoint ) ) {
            return false;
        }
        $api_key = self::getApiKey();
        // Refused provider row - bail rather than send an empty Bearer header.
        if ( $api_key === false || $api_key === null || $api_key === '' ) {
            return false;
        }
        $data    = [
            'timeout' => 45,
            'headers' => [
                'Content-Type'  => 'application/json',
                'Authorization' => "Bearer $api_key",
            ],
        ];
        if ( ! empty( $parameters ) ) {
            $data['body'] = json_encode( $parameters );
        }

        // Kinsta's API rate-limits aggressively (e.g. ssh/password endpoints
        // start returning 429 after just 2 requests in the same second). Retry
        // with backoff so a small burst of internal calls survives.
        $max_attempts = 5;
        $attempt      = 0;
        while ( true ) {
            $response = wp_remote_get( "https://api.kinsta.com/v2/$endpoint", $data );
            if ( is_wp_error( $response ) ) {
                return false;
            }
            $code = wp_remote_retrieve_response_code( $response );
            if ( $code !== 429 || $attempt >= $max_attempts - 1 ) {
                break;
            }
            $retry_after = (int) wp_remote_retrieve_header( $response, 'retry-after' );
            $sleep_us    = $retry_after > 0
                ? $retry_after * 1000000
                : (int) ( pow( 2, $attempt ) * 500000 );
            usleep( $sleep_us );
            $attempt++;
        }

        $response = json_decode( $response['body'] );
        return $response;
    }

    public static function post( $endpoint, $parameters = [] ) {

        if ( ! self::endpoint_is_safe( $endpoint ) ) {
            return false;
        }
        $api_key = self::getApiKey();
        // Refused provider row - bail rather than send an empty Bearer header.
        if ( $api_key === false || $api_key === null || $api_key === '' ) {
            return false;
        }
        $data    = [
            'timeout' => 45,
            'headers' => [
                'Content-Type'  => 'application/json',
                'Authorization' => "Bearer $api_key",
            ],
        ];
        if ( ! empty( $parameters ) ) {
            $data['body'] = json_encode( $parameters );
        }

        // Same 429 backoff as get(): a burst of POSTs in one second (one
        // per environment when requesting final backups) trips the limiter,
        // and a 429 means nothing was accepted, so a retry is safe.
        $max_attempts = 5;
        $attempt      = 0;
        while ( true ) {
            $response = wp_remote_post( "https://api.kinsta.com/v2/$endpoint", $data );
            if ( is_wp_error( $response ) ) {
                return false;
            }
            $code = wp_remote_retrieve_response_code( $response );
            if ( $code !== 429 || $attempt >= $max_attempts - 1 ) {
                break;
            }
            $retry_after = (int) wp_remote_retrieve_header( $response, 'retry-after' );
            $sleep_us    = $retry_after > 0
                ? $retry_after * 1000000
                : (int) ( pow( 2, $attempt ) * 500000 );
            usleep( $sleep_us );
            $attempt++;
        }

        $response = json_decode( $response['body'] );
        return $response;
    }

    public static function put( $endpoint, $parameters = [] ) {

        if ( ! self::endpoint_is_safe( $endpoint ) ) {
            return false;
        }
        $api_key = self::getApiKey();
        // Refused provider row - bail rather than send an empty Bearer header.
        if ( $api_key === false || $api_key === null || $api_key === '' ) {
            return false;
        }
        $data    = [
            'timeout' => 45,
            'headers' => [
                'Content-Type'  => 'application/json',
                'Authorization' => "Bearer $api_key",
            ],
            'method'  => 'PUT',
        ];
        if ( ! empty( $parameters ) ) {
            $data['body'] = json_encode( $parameters );
        }
        $response = wp_remote_post( "https://api.kinsta.com/v2/$endpoint", $data );
        if ( is_wp_error( $response ) ) {
            return false;
        }
        $response = json_decode( $response['body'] );
        return $response;
    }

    public static function delete( $endpoint, $parameters = [] ) {

        if ( ! self::endpoint_is_safe( $endpoint ) ) {
            return false;
        }
        $api_key = self::getApiKey();
        // Refused provider row - bail rather than send an empty Bearer header.
        if ( $api_key === false || $api_key === null || $api_key === '' ) {
            return false;
        }
        $data    = [
            'timeout' => 45,
            'headers' => [
                'Content-Type'  => 'application/json',
                'Authorization' => "Bearer $api_key",
            ],
            'method'  => 'DELETE',
        ];
        if ( ! empty( $parameters ) ) {
            $data['body'] = json_encode( $parameters );
        }
        $response = wp_remote_request( "https://api.kinsta.com/v2/$endpoint", $data );
        if ( is_wp_error( $response ) ) {
            return false;
        }
        $response = json_decode( $response['body'] );
        return $response;
    }

}