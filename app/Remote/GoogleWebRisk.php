<?php
/**
 * Google Web Risk API WordPress Wrapper
 *
 * @author   Austin Ginder
 * @see      https://cloud.google.com/web-risk/docs/reference/rest
 */

namespace CaptainCore\Remote;

class GoogleWebRisk {

	/**
	 * API base URL for Google Web Risk
	 */
	const API_BASE_URL = 'https://webrisk.googleapis.com/v1';

	/**
	 * Available threat types for URI search
	 */
	const THREAT_TYPE_MALWARE                        = 'MALWARE';
	const THREAT_TYPE_SOCIAL_ENGINEERING             = 'SOCIAL_ENGINEERING';
	const THREAT_TYPE_UNWANTED_SOFTWARE              = 'UNWANTED_SOFTWARE';
	const THREAT_TYPE_SOCIAL_ENGINEERING_EXTENDED    = 'SOCIAL_ENGINEERING_EXTENDED_COVERAGE';

	/**
	 * Search a URI against the Web Risk threat lists.
	 *
	 * @param string       $uri          The URI to check for threats.
	 * @param array|string $threat_types The threat types to check against. Defaults to all types.
	 *
	 * @return object|WP_Error The API response on success, WP_Error on failure.
	 */
	public static function get( $uri, $threat_types = [] ) {
		if ( ! defined( 'GOOGLE_WEB_RISK_API_KEY' ) ) {
			return new \WP_Error(
				'missing_api_key',
				'GOOGLE_WEB_RISK_API_KEY constant is not defined'
			);
		}

		// Default to all threat types if none specified
		if ( empty( $threat_types ) ) {
			$threat_types = [
				self::THREAT_TYPE_MALWARE,
				self::THREAT_TYPE_SOCIAL_ENGINEERING,
				self::THREAT_TYPE_UNWANTED_SOFTWARE,
				self::THREAT_TYPE_SOCIAL_ENGINEERING_EXTENDED,
			];
		}

		// Ensure threat_types is an array
		if ( is_string( $threat_types ) ) {
			$threat_types = [ $threat_types ];
		}

		// Build query parameters
		$query_params = [
			'key' => GOOGLE_WEB_RISK_API_KEY,
			'uri' => $uri,
		];

		// Build the URL with threat types (they need to be added as repeated parameters)
		$url = self::API_BASE_URL . '/uris:search?' . http_build_query( $query_params );

		// Add threat types as repeated parameters
		foreach ( $threat_types as $threat_type ) {
			$url .= '&threatTypes=' . urlencode( $threat_type );
		}

		$response = wp_remote_get(
			$url,
			[
				'timeout' => 30,
				'headers' => [
					'Accept' => 'application/json',
				],
			]
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$response_code = wp_remote_retrieve_response_code( $response );
		$response_body = wp_remote_retrieve_body( $response );

		if ( 200 !== $response_code ) {
			$error_data = json_decode( $response_body, true );
			return new \WP_Error(
				'api_error',
				isset( $error_data['error']['message'] ) ? $error_data['error']['message'] : 'HTTP error: ' . $response_code,
				$error_data
			);
		}

		return json_decode( $response_body );
	}

	/**
	 * Check if a URI has any threats.
	 *
	 * @param string       $uri          The URI to check.
	 * @param array|string $threat_types The threat types to check against.
	 *
	 * @return bool True if the URI has threats, false otherwise.
	 */
	public static function has_threats( $uri, $threat_types = [] ) {
		$result = self::get( $uri, $threat_types );

		if ( is_wp_error( $result ) ) {
			return false;
		}

		return isset( $result->threat ) && ! empty( $result->threat );
	}

	/**
	 * Get the threat types for a URI.
	 *
	 * @param string       $uri          The URI to check.
	 * @param array|string $threat_types The threat types to check against.
	 *
	 * @return array Array of threat types found, or empty array if none.
	 */
	public static function get_threat_types( $uri, $threat_types = [] ) {
		$result = self::get( $uri, $threat_types );

		if ( is_wp_error( $result ) ) {
			return [];
		}

		if ( isset( $result->threat->threatTypes ) && is_array( $result->threat->threatTypes ) ) {
			return $result->threat->threatTypes;
		}

		return [];
	}

	/**
	 * Search by hash prefix (for local database lookups).
	 *
	 * @param string       $hash_prefix  The hash prefix to search for.
	 * @param array|string $threat_types The threat types to check against.
	 *
	 * @return object|WP_Error The API response on success, WP_Error on failure.
	 */
	public static function search_hashes( $hash_prefix, $threat_types = [] ) {
		if ( ! defined( 'GOOGLE_WEB_RISK_API_KEY' ) ) {
			return new \WP_Error(
				'missing_api_key',
				'GOOGLE_WEB_RISK_API_KEY constant is not defined'
			);
		}

		// Default to all threat types if none specified
		if ( empty( $threat_types ) ) {
			$threat_types = [
				self::THREAT_TYPE_MALWARE,
				self::THREAT_TYPE_SOCIAL_ENGINEERING,
				self::THREAT_TYPE_UNWANTED_SOFTWARE,
				self::THREAT_TYPE_SOCIAL_ENGINEERING_EXTENDED,
			];
		}

		// Ensure threat_types is an array
		if ( is_string( $threat_types ) ) {
			$threat_types = [ $threat_types ];
		}

		// Build query parameters
		$query_params = [
			'key'        => GOOGLE_WEB_RISK_API_KEY,
			'hashPrefix' => base64_encode( $hash_prefix ),
		];

		// Build the URL
		$url = self::API_BASE_URL . '/hashes:search?' . http_build_query( $query_params );

		// Add threat types as repeated parameters
		foreach ( $threat_types as $threat_type ) {
			$url .= '&threatTypes=' . urlencode( $threat_type );
		}

		$response = wp_remote_get(
			$url,
			[
				'timeout' => 30,
				'headers' => [
					'Accept' => 'application/json',
				],
			]
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$response_code = wp_remote_retrieve_response_code( $response );
		$response_body = wp_remote_retrieve_body( $response );

		if ( 200 !== $response_code ) {
			$error_data = json_decode( $response_body, true );
			return new \WP_Error(
				'api_error',
				isset( $error_data['error']['message'] ) ? $error_data['error']['message'] : 'HTTP error: ' . $response_code,
				$error_data
			);
		}

		return json_decode( $response_body );
	}

}
