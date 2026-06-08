<?php

namespace CaptainCore;

/**
 * Generic passthrough to the CaptainCore\Remote\* provider wrappers.
 *
 * Each provider class (Spaceship, Kinsta, Mailgun, …) exposes static
 * get/post/put/delete( $endpoint, $parameters ) methods that handle auth and
 * base URL. This command dispatches to them so any endpoint can be hit from
 * the shell without writing a one-off `wp eval`.
 */
class RemoteCLI {

	/** HTTP verbs we allow dispatching to a provider. */
	private static $methods = [ 'get', 'post', 'put', 'delete' ];

	/**
	 * Call a CaptainCore\Remote\* provider wrapper and print the response.
	 *
	 * ## OPTIONS
	 *
	 * <provider>
	 * : Provider wrapper to call. Matched case-insensitively against the
	 *   classes in app/Remote (e.g. spaceship, kinsta, mailgun, gridpane,
	 *   forward-email, google-web-risk). Run without a known provider to list them.
	 *
	 * <method>
	 * : HTTP verb: get, post, put, or delete.
	 *
	 * <endpoint>
	 * : API endpoint path, relative to the provider's base URL
	 *   (e.g. "domains", "domains/example.com/renew").
	 *
	 * [--body=<json>]
	 * : Request body / parameters as a JSON string. For GET these become the
	 *   query string. Mutually exclusive with passing individual --key=value pairs.
	 *
	 * [--<field>=<value>]
	 * : Any other named flags are collected into the request parameters when
	 *   --body is not given. Repeat for multiple fields.
	 *
	 * [--format=<format>]
	 * : Output format. Accepts json (default), table, csv, yaml.
	 *
	 * ## EXAMPLES
	 *
	 *     # List Spaceship domains, ordered by expiration
	 *     wp captaincore remote spaceship get domains --take=100 --skip=0 --orderBy=expirationDate
	 *
	 *     # Toggle a domain's autorenew via JSON body
	 *     wp captaincore remote spaceship put domains/example.com/autorenew --body='{"isEnabled":true}'
	 *
	 *     # Request a renewal (POST with a body)
	 *     wp captaincore remote spaceship post domains/example.com/renew --body='{"years":1,"currentExpirationDate":"2026-06-15T00:00:00.000Z"}'
	 *
	 *     # Kinsta passthrough
	 *     wp captaincore remote kinsta get sites --company=<uuid>
	 *
	 * @when after_wp_load
	 */
	public function __invoke( $args, $assoc_args ) {
		list( $provider_arg, $method, $endpoint ) = array_pad( $args, 3, null );

		$class = $this->resolve_provider( $provider_arg );
		if ( ! $class ) {
			\WP_CLI::error( "Unknown provider '{$provider_arg}'. Available: " . implode( ', ', $this->available_providers() ) );
		}

		$method = strtolower( (string) $method );
		if ( ! in_array( $method, self::$methods, true ) ) {
			\WP_CLI::error( "Invalid method '{$method}'. Use one of: " . implode( ', ', self::$methods ) );
		}
		if ( ! method_exists( $class, $method ) ) {
			$short = ( new \ReflectionClass( $class ) )->getShortName();
			\WP_CLI::error( "Provider '{$short}' does not implement '{$method}()'." );
		}

		// Build the parameters: an explicit --body JSON wins; otherwise gather flags.
		$parameters = $this->build_parameters( $assoc_args );

		$response = call_user_func( [ $class, $method ], $endpoint, $parameters );

		// Wrapper errors come back as a plain string error message.
		if ( is_string( $response ) ) {
			\WP_CLI::error( $response );
		}

		$this->render( $response, $assoc_args['format'] ?? 'json' );
	}

	/**
	 * Resolve user input ("forward-email", "Spaceship", "googlewebrisk") to a
	 * fully-qualified Remote class, matching case- and separator-insensitively.
	 */
	private function resolve_provider( $input ) {
		if ( empty( $input ) ) {
			return null;
		}
		$needle = preg_replace( '/[^a-z0-9]/', '', strtolower( $input ) );
		foreach ( $this->available_providers() as $name ) {
			if ( preg_replace( '/[^a-z0-9]/', '', strtolower( $name ) ) === $needle ) {
				return "CaptainCore\\Remote\\{$name}";
			}
		}
		return null;
	}

	/** List the provider wrapper class names found in app/Remote. */
	private function available_providers() {
		$files = glob( __DIR__ . '/Remote/*.php' );
		$names = array_map( function ( $f ) {
			return basename( $f, '.php' );
		}, $files ?: [] );
		sort( $names );
		return $names;
	}

	/**
	 * Turn CLI flags into the parameters array passed to the wrapper.
	 * --body='<json>' takes precedence; otherwise all non-reserved flags are used.
	 */
	private function build_parameters( $assoc_args ) {
		if ( isset( $assoc_args['body'] ) ) {
			$decoded = json_decode( $assoc_args['body'], true );
			if ( null === $decoded && '' !== trim( $assoc_args['body'] ) ) {
				\WP_CLI::error( "--body is not valid JSON: " . json_last_error_msg() );
			}
			return $decoded ?? [];
		}
		$reserved = [ 'format', 'body' ];
		return array_diff_key( $assoc_args, array_flip( $reserved ) );
	}

	/** Print the decoded response in the requested format. */
	private function render( $response, $format ) {
		if ( 'json' === $format ) {
			\WP_CLI::log( json_encode( $response, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) );
			return;
		}

		// For tabular formats, locate the row set: a top-level list, or an
		// "items"/"data" collection, else wrap the single object as one row.
		$data = json_decode( json_encode( $response ), true );
		if ( is_array( $data ) && isset( $data['items'] ) && is_array( $data['items'] ) ) {
			$rows = $data['items'];
		} elseif ( is_array( $data ) && isset( $data['data'] ) && is_array( $data['data'] ) ) {
			$rows = $data['data'];
		} elseif ( is_array( $data ) && array_is_list( $data ) ) {
			$rows = $data;
		} else {
			$rows = [ $data ];
		}

		// Flatten each row to scalars so format_items doesn't choke on nested arrays.
		$rows = array_map( function ( $row ) {
			$flat = [];
			foreach ( (array) $row as $k => $v ) {
				$flat[ $k ] = is_scalar( $v ) || null === $v ? $v : json_encode( $v );
			}
			return $flat;
		}, $rows );

		if ( empty( $rows ) ) {
			\WP_CLI::log( '(empty response)' );
			return;
		}

		$columns = array_keys( $rows[0] );
		\WP_CLI\Utils\format_items( $format, $rows, $columns );
	}

}
