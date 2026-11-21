<?php
/**
 * Mailgun API WordPress Wrapper
 *
 * @author   Austin Ginder
 */

namespace CaptainCore\Remote;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Mailgun {

    /**
     * The Mailgun API key: the "key" credential of the Mailgun provider
     * (Settings → Providers), else the legacy MAILGUN_API_KEY constant.
     */
    public static function key() {
        static $key = null;
        if ( $key !== null ) {
            return $key;
        }
        $credentials = ( new \CaptainCore\Provider( "mailgun" ) )->credentials();
        foreach ( (array) $credentials as $credential ) {
            if ( isset( $credential->name, $credential->value ) && $credential->name == "key" && trim( (string) $credential->value ) !== "" ) {
                return $key = trim( (string) $credential->value );
            }
        }
        return $key = defined( 'MAILGUN_API_KEY' ) ? (string) MAILGUN_API_KEY : "";
    }
    public static function get( $command, $data = [] ) {

        if ( self::key() === '' ) {
            return (object) [ 'errors' => [ 'No Mailgun API key. Add one to the Mailgun provider.' ] ];
        }

        $args = [
            'timeout' => 120,
            'headers' => [
                'Content-type'       => 'application/json',
                'Authorization'      => 'Basic '. base64_encode( "api:". self::key() ),
            ]
        ];

        $query = ( empty( $data ) ) ? "" : "?". http_build_query( $data );

        $remote = wp_remote_get( "https://api.mailgun.net/$command$query", $args );

        if ( is_wp_error( $remote ) ) {
            return (object) [ 'errors' => [ $remote->get_error_message() ] ];
        } else {
            return json_decode( $remote['body'] );
        }

    }

    public static function page( $domain, $page ) {

        if ( self::key() === '' ) {
            return;
        }

        // Validate the host strictly — str_contains would accept an attacker
        // URL like https://evil.tld/?x=https://api.mailgun.net.
        $parts = wp_parse_url( $page );
        if ( empty( $parts['scheme'] ) || $parts['scheme'] !== 'https' || empty( $parts['host'] ) || $parts['host'] !== 'api.mailgun.net' ) {
            return;
        }

        // Scope the request to this zone's own event feed. A substring test over
        // the whole URL is not an authorization check - the zone name can sit in
        // an ignored query parameter while the path addresses another tenant's
        // resource, and the request carries the account-wide API key.
        $path = isset( $parts['path'] ) ? $parts['path'] : '';
        if ( false !== strpos( $path, '..' ) ) {
            return;
        }
        $prefix = '/v3/' . strtolower( (string) $domain ) . '/events';
        if ( 0 !== strpos( strtolower( $path ), $prefix ) ) {
            return;
        }

        $args = [
            'timeout' => 120,
            'headers' => [
                'Content-type'       => 'application/json',
                'Authorization'      => 'Basic '. base64_encode( "api:". self::key() ),
            ]
        ];

        $remote = wp_remote_get( "$page", $args );

        if ( is_wp_error( $remote ) ) {
            return $remote->get_error_message();
        } else {
            return json_decode( $remote['body'] );
        }

    }

    /**
     * Fetches a stored message (the storage.url on an event) for one zone.
     *
     * The URL comes from the browser, and the request carries the account-wide
     * API key, so it must be exactly this zone's message store:
     * https://storage[-region].api.mailgun.net/v3/domains/<zone>/messages/<key>.
     * Returns the decoded body, or a WP_Error.
     */
    public static function stored_message( $domain, $url ) {

        if ( self::key() === '' ) {
            return new \WP_Error( 'mailgun_not_configured', 'No Mailgun API key. Add one to the Mailgun provider.', [ 'status' => 500 ] );
        }

        $parts = wp_parse_url( (string) $url );
        $host  = isset( $parts['host'] ) ? strtolower( $parts['host'] ) : '';
        $path  = isset( $parts['path'] ) ? $parts['path'] : '';
        $valid_host = ( $host === 'api.mailgun.net' || preg_match( '/^storage(-[a-z0-9-]+)?\.api\.mailgun\.net$/', $host ) );
        $pattern    = '#^/v3/domains/' . preg_quote( strtolower( (string) $domain ), '#' ) . '/messages/[A-Za-z0-9_=-]+$#';
        if ( empty( $parts['scheme'] ) || $parts['scheme'] !== 'https' || ! $valid_host
            || isset( $parts['query'] ) || isset( $parts['user'] ) || isset( $parts['port'] )
            || ! preg_match( $pattern, strtolower( $path ) ) ) {
            return new \WP_Error( 'invalid_message_url', 'Not a stored message for this domain.', [ 'status' => 400 ] );
        }

        $remote = wp_remote_get( "https://$host$path", [
            'timeout' => 30,
            'headers' => [
                'Accept'        => 'application/json',
                'Authorization' => 'Basic ' . base64_encode( 'api:' . self::key() ),
            ],
        ] );

        if ( is_wp_error( $remote ) ) {
            return new \WP_Error( 'mailgun_unreachable', $remote->get_error_message(), [ 'status' => 502 ] );
        }
        $code = wp_remote_retrieve_response_code( $remote );
        if ( $code === 404 ) {
            return new \WP_Error( 'message_expired', 'Mailgun no longer has this message. Stored copies are kept for a few days only.', [ 'status' => 404 ] );
        }
        if ( $code !== 200 ) {
            return new \WP_Error( 'mailgun_error', "Mailgun answered HTTP $code.", [ 'status' => 502 ] );
        }
        $body = json_decode( wp_remote_retrieve_body( $remote ), true );
        if ( ! is_array( $body ) ) {
            return new \WP_Error( 'mailgun_error', 'Mailgun returned an unreadable message.', [ 'status' => 502 ] );
        }
        return $body;

    }

    public static function post( $command, $post = [] ) {

        if ( self::key() === '' ) {
            return (object) [ 'errors' => [ 'No Mailgun API key. Add one to the Mailgun provider.' ] ];
        }

        // Generate a boundary
        $boundary = wp_generate_password(24, false);
        $body = '';
        foreach ($post as $key => $value) {
            // Handle array values (e.g., action[] for routes)
            if ( is_array( $value ) ) {
                foreach ( $value as $array_value ) {
                    $body .= '--' . $boundary . "\r\n";
                    $body .= 'Content-Disposition: form-data; name="' . $key . "\"\r\n\r\n";
                    $body .= $array_value . "\r\n";
                }
            } else {
                $body .= '--' . $boundary . "\r\n";
                $body .= 'Content-Disposition: form-data; name="' . $key . "\"\r\n\r\n";
                $body .= $value . "\r\n";
            }
        }
        $body .= '--' . $boundary . '--';

        $args = [
            'timeout' => 120,
            'headers' => [
                'Content-type'  => "multipart/form-data; boundary=$boundary",
                'Authorization' => 'Basic '. base64_encode( "api:". self::key() ),
            ],
            'body'    => $body,
            'method'  => 'POST',
        ];
        $remote    = wp_remote_post( "https://api.mailgun.net/$command", $args );

        if ( is_wp_error( $remote ) ) {
            return (object) [ 'errors' => [ $remote->get_error_message() ] ];
        } else {
            return json_decode( $remote['body'] );
        }

    }

    public static function put( $command, $post = [] ) {

        if ( self::key() === '' ) {
            return (object) [ 'errors' => [ 'No Mailgun API key. Add one to the Mailgun provider.' ] ];
        }

         // Generate a boundary
         $boundary = wp_generate_password(24, false);
         $body = '';
         foreach ($post as $key => $value) {
             // Handle array values (e.g., action[] for routes)
             if ( is_array( $value ) ) {
                 foreach ( $value as $array_value ) {
                     $body .= '--' . $boundary . "\r\n";
                     $body .= 'Content-Disposition: form-data; name="' . $key . "\"\r\n\r\n";
                     $body .= $array_value . "\r\n";
                 }
             } else {
                 $body .= '--' . $boundary . "\r\n";
                 $body .= 'Content-Disposition: form-data; name="' . $key . "\"\r\n\r\n";
                 $body .= $value . "\r\n";
             }
         }
         $body .= '--' . $boundary . '--';

        $args = [
            'timeout' => 120,
            'headers' => [
                'Content-type'  => "application/json",
                'Authorization' => 'Basic '. base64_encode( "api:". self::key() ),
            ],
            'method'  => 'PUT',
        ];
        if ( ! empty( $post ) ) {
            $args['headers']['Content-type'] =  "multipart/form-data; boundary=$boundary";
            $args['body'] = $body;
        }
        $remote    = wp_remote_post( "https://api.mailgun.net/$command", $args );

        if ( is_wp_error( $remote ) ) {
            return (object) [ 'errors' => [ $remote->get_error_message() ] ];
        } else {
            return json_decode( $remote['body'] );
        }

    }

    public static function delete( $command, $data = [] ) {

        if ( self::key() === '' ) {
            return (object) [ 'errors' => [ 'No Mailgun API key. Add one to the Mailgun provider.' ] ];
        }

        $args = [
            'timeout' => 120,
            'headers' => [
                'Content-type'       => 'application/json',
                'Authorization'      => 'Basic '. base64_encode( "api:". self::key() ),
            ],
            'method'  => 'DELETE',
        ];

        $query = ( empty( $data ) ) ? "" : "?". http_build_query( $data );

        $remote = wp_remote_request( "https://api.mailgun.net/$command$query", $args );

        if ( is_wp_error( $remote ) ) {
            return (object) [ 'errors' => [ $remote->get_error_message() ] ];
        } else {
            return json_decode( $remote['body'] );
        }

    }

}