<?php
/**
 * Forward Email API WordPress Wrapper
 *
 * @author   CaptainCore
 * @link     https://forwardemail.net/api
 */

namespace CaptainCore\Remote;

class ForwardEmail {

    private static $base_url = 'https://api.forwardemail.net/v1';

    /**
     * Generates the Basic Auth header.
     * If username is not provided, it fetches the default API key from CaptainCore\Providers.
     *
     * @param string|null $username The API key (for ApiKeyAuth) or alias username (for AliasAuth).
     * @param string $password The password (empty for ApiKeyAuth, alias password for AliasAuth).
     * @return string The formatted Authorization header string.
     */
    private static function getAuthHeader($username = null, $password = "") {
        if (is_null($username)) {
            // Default to ApiKeyAuth, fetching key from CaptainCore Providers.
            // Assumes a provider named "forwardemail" with a credential "api_key" exists.
            $username = \CaptainCore\Providers\ForwardEmail::credentials("api_key");
            $password = ""; // Per API docs, password is empty for ApiKeyAuth
        }
        
        return 'Basic ' . base64_encode("{$username}:{$password}");
    }

    /**
     * Performs a GET request.
     *
     * @param string $endpoint The API endpoint (e.g., 'domains').
     * @param array $parameters Optional query parameters.
     * @param string|null $username Optional username for Basic Auth (defaults to provider API key).
     * @param string $password Optional password for Basic Auth.
     * @return mixed|WP_Error Decoded JSON response or WP_Error on failure.
     */
    public static function get($endpoint, $parameters = [], $username = null, $password = "") {
        $args = [
            'timeout' => 120,
            'headers' => [
                'Content-type'  => 'application/json',
                'Authorization' => self::getAuthHeader($username, $password),
            ]
        ];

        $url = self::$base_url . "/$endpoint";
        if (!empty($parameters)) {
            $url .= "?" . http_build_query($parameters);
        }

        $remote = wp_remote_get($url, $args);

        return self::handle_response($remote);
    }

    /**
     * Performs a POST request.
     *
     * @param string $endpoint The API endpoint.
     * @param array $body The request body to be JSON-encoded.
     * @param string|null $username Optional username for Basic Auth.
     * @param string $password Optional password for Basic Auth.
     * @return mixed|WP_Error Decoded JSON response or WP_Error on failure.
     */
    public static function post($endpoint, $body = [], $username = null, $password = "") {
        $args = [
            'timeout' => 120,
            'headers' => [
                'Content-type'  => 'application/json',
                'Authorization' => self::getAuthHeader($username, $password),
            ],
            'body'    => json_encode($body),
            'method'  => 'POST',
        ];

        $url = self::$base_url . "/$endpoint";
        $remote = wp_remote_post($url, $args);

        return self::handle_response($remote);
    }

    /**
     * Performs a PUT request.
     *
     * @param string $endpoint The API endpoint.
     * @param array $body The request body to be JSON-encoded.
     * @param string|null $username Optional username for Basic Auth.
     * @param string $password Optional password for Basic Auth.
     * @return mixed|WP_Error Decoded JSON response or WP_Error on failure.
     */
    public static function put($endpoint, $body = [], $username = null, $password = "") {
        $args = [
            'timeout' => 120,
            'headers' => [
                'Content-type'  => 'application/json',
                'Authorization' => self::getAuthHeader($username, $password),
            ],
            'body'    => json_encode($body),
            'method'  => 'PUT',
        ];
        
        $url = self::$base_url . "/$endpoint";
        $remote = wp_remote_post($url, $args);
        
        return self::handle_response($remote);
    }

    /**
     * Performs a DELETE request.
     *
     * @param string $endpoint The API endpoint.
     * @param array $body Optional request body to be JSON-encoded.
     * @param string|null $username Optional username for Basic Auth.
     * @param string $password Optional password for Basic Auth.
     * @return mixed|WP_Error Decoded JSON response or WP_Error on failure.
     */
    public static function delete($endpoint, $body = [], $username = null, $password = "") {
            $args = [
            'timeout' => 120,
            'headers' => [
                'Content-type'  => 'application/json',
                'Authorization' => self::getAuthHeader($username, $password),
            ],
            'method'  => 'DELETE',
        ];
        
        if (!empty($body)) {
                $args['body'] = json_encode($body);
        }
        
        $url = self::$base_url . "/$endpoint";
        $remote = wp_remote_request($url, $args);
        
        return self::handle_response($remote);
    }

    /**
     * Handles the response from wp_remote_* calls.
     *
     * @param array|WP_Error $remote The response from wp_remote_get, wp_remote_post, etc.
     * @return mixed|WP_Error Decoded JSON object on success, WP_Error on failure.
     */
    private static function handle_response($remote) {
        if (is_wp_error($remote)) {
            return $remote; // Return the full WP_Error object
        }

        $body = wp_remote_retrieve_body($remote);
        $http_code = wp_remote_retrieve_response_code($remote);
        $decoded = json_decode($body);

        // Handle JSON decoding errors
        if (json_last_error() !== JSON_ERROR_NONE) {
            return new \WP_Error('json_decode_error', json_last_error_msg(), ['body' => $body, 'status' => $http_code]);
        }

        // Handle API-level errors (ForwardEmail returns JSON with 'message' on error)
        if (isset($decoded->message) && $http_code >= 400) {
                return new \WP_Error('forwardemail_api_error', $decoded->message, ['status' => $http_code, 'details' => $decoded]);
        }
        
        return $decoded; // Success
    }
}