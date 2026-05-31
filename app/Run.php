<?php

namespace CaptainCore;

class Run {

    protected $account_id = "";

    /**
     * Build the JSON request body for the CLI server.
     *
     * Accepts either a legacy command string or the new argv array. When an
     * array is given the server skips its regex tokenizer and execs the argv
     * verbatim — no shell, no quoting, no injection surface. A payload blob is
     * sent as a structured field instead of an inline --payload='...'.
     *
     * @param string|array $command
     * @param string       $payload
     * @return array
     */
    private static function build_body( $command, $payload = "" ) {
        if ( is_array( $command ) ) {
            $body = [ "args" => array_values( $command ) ];
            if ( $payload !== "" ) {
                $body["payload"] = $payload;
            }
            return $body;
        }
        return [ "command" => $command ];
    }

    /** Human-readable form of a command for logging / JobTokens display. */
    private static function display( $command ) {
        return is_array( $command ) ? implode( " ", $command ) : $command;
    }

    /** POST a request body to the CLI server and return the WP HTTP response. */
    private static function post( $path, $body, $timeout = 45 ) {
        // Disable https when debug enabled
        if ( defined( 'CAPTAINCORE_DEBUG' ) ) {
            add_filter( 'https_ssl_verify', '__return_false' );
        }

        $data = [
            'timeout' => $timeout,
            'headers' => [
                'Content-Type' => 'application/json; charset=utf-8',
                'token'        => captaincore_get_cli_token(),
            ],
            'body'        => wp_json_encode( $body ),
            'method'      => 'POST',
            'data_format' => 'body',
        ];

        return wp_remote_post( CAPTAINCORE_CLI_ADDRESS . $path, $data );
    }

    public static function CLI( $command = "", $background = false, $payload = "" ) {

        if ( empty( $command ) ) {
            return;
        }

        $path     = $background ? "/run/background" : "/run";
        $response = self::post( $path, self::build_body( $command, $payload ) );
        if ( is_wp_error( $response ) ) {
            return [];
        }

        return $response["body"];
    }

    /**
     * Executes a remote CLI command and streams the raw output directly.
     * Ideal for binary data like images.
     *
     * @param string|array $command
     */
    public static function CLI_Stream( $command = "", $payload = "" ) {
        if ( empty( $command ) ) {
            return;
        }

        $url          = CAPTAINCORE_CLI_ADDRESS . "/run/stream";
        $payload_body = wp_json_encode( self::build_body( $command, $payload ) );
        $headers      = [
            'Content-Type: application/json; charset=utf-8',
            'token: ' . captaincore_get_cli_token(),
            'Content-Length: ' . strlen( $payload_body )
        ];

        $ch = curl_init();
        curl_setopt( $ch, CURLOPT_URL, $url );
        curl_setopt( $ch, CURLOPT_POST, true );
        curl_setopt( $ch, CURLOPT_POSTFIELDS, $payload_body );
        curl_setopt( $ch, CURLOPT_HTTPHEADER, $headers );
        curl_setopt( $ch, CURLOPT_TIMEOUT, 60 ); // Increased timeout for larger files

        // This is the key: disable the return transfer and set a write function.
        // This tells cURL to output data chunks directly as they are received.
        curl_setopt( $ch, CURLOPT_RETURNTRANSFER, false );
        curl_setopt( $ch, CURLOPT_WRITEFUNCTION, function( $curl, $data ) {
            echo $data; // Echo the raw data chunk
            return strlen( $data ); // Return bytes handled
        });

        // Disable SSL verification if debug is on
        if ( defined( 'CAPTAINCORE_DEBUG' ) ) {
            curl_setopt( $ch, CURLOPT_SSL_VERIFYPEER, false );
        }

        // Execute the request. The output is streamed directly.
        curl_exec( $ch );
        curl_close( $ch );
    }

    public static function background( $command = "", $payload = "" ) {

        $response = self::post( "/run/background", self::build_body( $command, $payload ) );
        if ( is_wp_error( $response ) ) {
            $error_message = $response->get_error_message();
            return "Something went wrong: $error_message";
        }

        return $response["body"];
    }

    public static function execute( $command = "", $payload = "" ) {

        $response = self::post( "/run", self::build_body( $command, $payload ), 300 );
        if ( is_wp_error( $response ) ) {
            return new \WP_Error( 'request_failed', $response->get_error_message(), [ 'status' => 500 ] );
        }

        return [ "status" => "completed", "response" => $response["body"] ];
    }

    public static function background_task( $command = "", $payload = "" ) {

        $response = self::post( "/run/background", self::build_body( $command, $payload ) );
        if ( is_wp_error( $response ) ) {
            return new \WP_Error( 'request_failed', $response->get_error_message(), [ 'status' => 500 ] );
        }

        $response = json_decode( $response["body"] );

        if ( $response && $response->token ) {
            ( new JobTokens )->insert( [
                'token'      => $response->token,
                'task_id'    => $response->task_id,
                'user_id'    => get_current_user_id(),
                'command'    => self::display( $command ),
                'created_at' => current_time( 'mysql' ),
            ] );
            return [ "status" => "queued", "token" => $response->token ];
        }

        return new \WP_Error( 'request_failed', 'No token returned from CLI server.', [ 'status' => 500 ] );
    }

    public static function task( $command = "", $payload = "" ) {

        $response = self::post( "/tasks", self::build_body( $command, $payload ) );
        if ( is_wp_error( $response ) ) {
            $error_message = $response->get_error_message();
            return "Something went wrong: $error_message";
        }

        $raw_body = $response["body"];
        $response = json_decode( $raw_body );

        // Response with task id
        if ( $response && isset( $response->token ) ) {
            ( new JobTokens )->insert( [
                'token'      => $response->token,
                'task_id'    => $response->task_id,
                'user_id'    => get_current_user_id(),
                'command'    => self::display( $command ),
                'created_at' => current_time( 'mysql' ),
            ] );
            return $response->token;
        }

        return $raw_body;
    }

}
