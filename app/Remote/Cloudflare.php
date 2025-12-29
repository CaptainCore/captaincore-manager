<?php

namespace CaptainCore\Remote;

/**
 * Class Cloudflare
 *
 * Handles interactions with the Cloudflare DNS API.
 */
class Cloudflare {

	/**
	 * Fetches a DNS record for a given domain using Cloudflare's DNS over HTTPS.
	 *
	 * @param string $type   The DNS record type (e.g., 'TXT', 'A', 'MX').
	 * @param string $domain The domain to query.
	 *
	 * @return string|WP_Error The DNS record on success, WP_Error on failure.
	 */
	public static function get( $type, $domain ) {
		$url =
			'https://cloudflare-dns.com/dns-query?name=' .
			urlencode( $domain ) .
			'&type=' .
			urlencode( $type );

		$response = wp_remote_get(
			$url,
			array(
				'headers' => array(
					'accept' => 'application/dns-json',
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$response_code = wp_remote_retrieve_response_code( $response );
		$response_body = wp_remote_retrieve_body( $response );

		if ( 200 !== $response_code ) {
			return new \WP_Error(
				'http_error',
				'HTTP error: ' . $response_code,
				$response_body
			);
		}

		$data = json_decode( $response_body, true );

		if ( ! isset( $data['Answer'] ) || ! is_array( $data['Answer'] ) ) {
			return new \WP_Error(
				'no_answer',
				'No ' . $type . ' record found for ' . $domain,
				$data
			);
		}

		$records = array_map(
			function ( $answer ) {
				return trim( $answer['data'], '"' ); // Trim double quotes
			},
			$data['Answer']
		);

		return $records;
	}
}

// Example usage (outside the class):
// use CaptainCore\Remote\Cloudflare;

// $domain = 'pennmanor.net.kinstavalidation.app';
// $txt_record = Cloudflare::get( 'TXT', $domain );

// if ( is_wp_error( $txt_record ) ) {
// 	echo 'Error: ' . esc_html( $txt_record->get_error_message() );
// } else {
// 	echo 'TXT Record: ' . esc_html( $txt_record );
// }

// $a_record = Cloudflare::get( 'A', 'example.com' );  // Example for A record
