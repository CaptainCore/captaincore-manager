<?php

namespace CaptainCore;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Sign-in for the Claude connector (the MCP endpoint in Mcp.php).
 *
 * Claude's hosted apps (claude.ai, Desktop, mobile, Cowork) only reach a custom
 * connector through OAuth, so this is the smallest OAuth 2.1 authorization
 * server that satisfies them: discovery metadata, an approve page behind the
 * normal CaptainCore login, PKCE, and stateless client registration. The token
 * Claude ends up holding is an ordinary WordPress application password minted
 * for the user, tagged with APP_ID, so a connection shows up (and is revoked)
 * in the profile's application password list. No token table, no refresh
 * tokens: application passwords do not expire, and a revoked one sends Claude
 * back through sign-in.
 *
 * A Claude password is accepted only by the connector endpoint. restrict()
 * refuses it everywhere else (Basic auth on other REST routes, XML-RPC).
 */
class McpAuth {

	/** Marks the application passwords minted for Claude connections. */
	const APP_ID = '6f1c2a54-7c1e-4d0b-9a57-c1a0de4d3c01';

	const SCOPE        = 'captaincore';
	const TOKEN_PREFIX = 'ccmcp_';

	/** True only while the connector endpoint is checking its bearer token. */
	private static $authenticating = false;

	/** The application password item the last successful check matched. */
	private static $matched_item = null;

	public static function boot() {
		add_action( 'parse_request', [ __CLASS__, 'route' ], 0 );
		add_action( 'wp_authenticate_application_password_errors', [ __CLASS__, 'restrict' ], 10, 4 );
		add_action( 'application_password_did_authenticate', [ __CLASS__, 'remember_item' ], 10, 2 );
		// The approve page lives outside the app, so the login page has to be
		// told it may send people back there.
		add_filter( 'captaincore_login_redirect_paths', function ( $paths ) {
			$paths[] = '/oauth/';
			return $paths;
		} );
	}

	public static function resource_url() {
		return rest_url( 'captaincore/v1/mcp' );
	}

	public static function issuer() {
		return untrailingslashit( home_url() );
	}

	public static function resource_metadata_url() {
		$path = (string) wp_parse_url( self::resource_url(), PHP_URL_PATH );
		return home_url( '/.well-known/oauth-protected-resource' . untrailingslashit( $path ) );
	}

	public static function authorize_url() {
		return home_url( '/oauth/authorize' );
	}

	public static function resource_metadata() {
		$configurations = Configurations::get();
		return [
			'resource'                 => self::resource_url(),
			'authorization_servers'    => [ self::issuer() ],
			'scopes_supported'         => [ self::SCOPE ],
			'bearer_methods_supported' => [ 'header' ],
			'resource_name'            => ! empty( $configurations->name ) ? $configurations->name : 'CaptainCore',
		];
	}

	public static function server_metadata() {
		return [
			'issuer'                                => self::issuer(),
			'authorization_endpoint'                => self::authorize_url(),
			'token_endpoint'                        => rest_url( 'captaincore/v1/oauth/token' ),
			'registration_endpoint'                 => rest_url( 'captaincore/v1/oauth/register' ),
			'scopes_supported'                      => [ self::SCOPE ],
			'response_types_supported'              => [ 'code' ],
			'grant_types_supported'                 => [ 'authorization_code' ],
			'token_endpoint_auth_methods_supported' => [ 'none' ],
			'code_challenge_methods_supported'      => [ 'S256' ],
			'client_id_metadata_document_supported' => true,
		];
	}

	/**
	 * Serves the discovery documents and the approve page. Runs on
	 * parse_request so it answers before WordPress 404s or canonical-redirects
	 * these paths (a bare /.well-known/… was getting a trailing-slash 301).
	 */
	public static function route( $wp ) {
		$path = untrailingslashit( (string) wp_parse_url( $_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH ) );

		if ( $path === '/.well-known/oauth-protected-resource' || strpos( $path, '/.well-known/oauth-protected-resource/' ) === 0 ) {
			self::send_json( self::resource_metadata() );
		}
		if ( $path === '/.well-known/oauth-authorization-server' || strpos( $path, '/.well-known/oauth-authorization-server/' ) === 0 ) {
			self::send_json( self::server_metadata() );
		}
		if ( $path === untrailingslashit( (string) wp_parse_url( self::authorize_url(), PHP_URL_PATH ) ) ) {
			self::authorize();
		}
	}

	private static function send_json( $data, $status = 200 ) {
		status_header( $status );
		header( 'Content-Type: application/json; charset=utf-8' );
		header( 'Access-Control-Allow-Origin: *' );
		header( 'Cache-Control: no-store' );
		echo wp_json_encode( $data, JSON_UNESCAPED_SLASHES );
		exit;
	}

	/**
	 * Where Claude may receive an authorization code: the hosted apps' fixed
	 * callback, and Claude Code's loopback redirect on any port.
	 */
	public static function redirect_allowed( $uri ) {
		if ( ! is_string( $uri ) || $uri === '' || strlen( $uri ) > 2048 ) {
			return false;
		}
		$allowed = (array) apply_filters( 'captaincore_mcp_redirect_uris', [ 'https://claude.ai/api/mcp/auth_callback' ] );
		if ( in_array( $uri, $allowed, true ) ) {
			return true;
		}
		return (bool) preg_match( '#^http://(localhost|127\.0\.0\.1)(:\d{1,5})?/callback$#', $uri );
	}

	public static function is_loopback( $uri ) {
		return (bool) preg_match( '#^http://(localhost|127\.0\.0\.1)[:/]#', (string) $uri );
	}

	public static function client_label( $redirect_uri ) {
		return self::is_loopback( $redirect_uri ) ? 'Claude Code' : 'Claude';
	}

	/**
	 * GET  /oauth/authorize?response_type=code&client_id=…  validate, stash, sign in, approve page
	 * GET  /oauth/authorize?request=<id>                    back from the login page
	 * POST /oauth/authorize                                 Allow / Cancel
	 */
	private static function authorize() {
		nocache_headers();
		header( 'X-Frame-Options: DENY' );
		header( "Content-Security-Policy: frame-ancestors 'none'" );

		if ( ( $_SERVER['REQUEST_METHOD'] ?? 'GET' ) === 'POST' ) {
			self::authorize_decision();
		}

		$request_id = isset( $_GET['request'] ) ? (string) wp_unslash( $_GET['request'] ) : '';
		if ( $request_id !== '' ) {
			$pending = preg_match( '/^[a-f0-9]{32}$/', $request_id ) ? get_transient( 'captaincore_mcp_req_' . $request_id ) : false;
			if ( ! $pending ) {
				self::render_error( 'This sign-in request has expired. Start again from Claude.' );
			}
		} else {
			$pending    = self::validate_authorize_request( wp_unslash( $_GET ) );
			$request_id = bin2hex( random_bytes( 16 ) );
			set_transient( 'captaincore_mcp_req_' . $request_id, $pending, 15 * MINUTE_IN_SECONDS );
		}

		$back = wp_parse_url( self::authorize_url(), PHP_URL_PATH ) . '?request=' . $request_id;
		if ( ! is_user_logged_in() ) {
			wp_safe_redirect( wp_login_url( $back ) );
			exit;
		}

		self::render_consent( $pending, $request_id, $back );
	}

	/**
	 * Checks an incoming authorization request. A bad client_id or
	 * redirect_uri ends on an error page - never redirect to an address that
	 * is not on the allowlist. Everything else is reported back to Claude.
	 */
	private static function validate_authorize_request( $params ) {
		$client_id    = isset( $params['client_id'] ) ? (string) $params['client_id'] : '';
		$redirect_uri = isset( $params['redirect_uri'] ) ? (string) $params['redirect_uri'] : '';
		$state        = isset( $params['state'] ) ? (string) $params['state'] : '';

		if ( $client_id === '' || strlen( $client_id ) > 512 ) {
			self::render_error( 'This sign-in link is missing its client. Start again from Claude.' );
		}
		if ( ! self::redirect_allowed( $redirect_uri ) ) {
			self::render_error( 'This sign-in link points somewhere other than Claude, so it was stopped.' );
		}
		if ( strlen( $state ) > 1024 ) {
			$state = '';
		}
		if ( ( $params['response_type'] ?? '' ) !== 'code' ) {
			self::redirect_back( $redirect_uri, [ 'error' => 'unsupported_response_type', 'state' => $state ] );
		}
		$challenge = isset( $params['code_challenge'] ) ? (string) $params['code_challenge'] : '';
		if ( ( $params['code_challenge_method'] ?? '' ) !== 'S256' || ! preg_match( '/^[A-Za-z0-9_-]{43,128}$/', $challenge ) ) {
			self::redirect_back( $redirect_uri, [ 'error' => 'invalid_request', 'error_description' => 'PKCE with S256 is required.', 'state' => $state ] );
		}

		return [
			'client_id'      => $client_id,
			'redirect_uri'   => $redirect_uri,
			'state'          => $state,
			'code_challenge' => $challenge,
		];
	}

	private static function authorize_decision() {
		$request_id = isset( $_POST['request'] ) ? (string) wp_unslash( $_POST['request'] ) : '';
		if ( ! preg_match( '/^[a-f0-9]{32}$/', $request_id ) || ! is_user_logged_in() ) {
			self::render_error( 'This sign-in request has expired. Start again from Claude.' );
		}
		if ( ! wp_verify_nonce( $_POST['_wpnonce'] ?? '', 'captaincore_mcp_authorize_' . $request_id ) ) {
			self::render_error( 'This approval form has expired. Start again from Claude.' );
		}
		$pending = get_transient( 'captaincore_mcp_req_' . $request_id );
		delete_transient( 'captaincore_mcp_req_' . $request_id );
		if ( ! $pending ) {
			self::render_error( 'This sign-in request has expired. Start again from Claude.' );
		}

		if ( ( $_POST['decision'] ?? '' ) !== 'allow' ) {
			self::redirect_back( $pending['redirect_uri'], [ 'error' => 'access_denied', 'state' => $pending['state'] ] );
		}

		$allowed = ( new User )->can_create_application_password();
		if ( is_wp_error( $allowed ) ) {
			self::render_error( $allowed->get_error_message() );
		}

		$code = bin2hex( random_bytes( 32 ) );
		set_transient( 'captaincore_mcp_code_' . hash( 'sha256', $code ), [
			'user_id'        => get_current_user_id(),
			'client_id'      => $pending['client_id'],
			'redirect_uri'   => $pending['redirect_uri'],
			'code_challenge' => $pending['code_challenge'],
		], 2 * MINUTE_IN_SECONDS );

		self::redirect_back( $pending['redirect_uri'], [ 'code' => $code, 'state' => $pending['state'] ] );
	}

	/** Redirects to an allowlisted redirect_uri with the given query args. */
	private static function redirect_back( $redirect_uri, $args ) {
		$args = array_filter( $args, function ( $v ) { return $v !== '' && $v !== null; } );
		$sep  = strpos( $redirect_uri, '?' ) === false ? '?' : '&';
		wp_redirect( $redirect_uri . $sep . http_build_query( $args, '', '&', PHP_QUERY_RFC3986 ) );
		exit;
	}

	private static function render_consent( $pending, $request_id, $back ) {
		$user       = wp_get_current_user();
		$is_admin   = ( new User )->is_admin();
		$site_count = count( (array) ( new Sites )->site_ids() );
		$view       = [
			'title'         => 'Connect ' . self::client_label( $pending['redirect_uri'] ),
			'client_label'  => self::client_label( $pending['redirect_uri'] ),
			'redirect_host' => (string) wp_parse_url( $pending['redirect_uri'], PHP_URL_HOST ),
			'loopback'      => self::is_loopback( $pending['redirect_uri'] ),
			'user_name'     => $user->display_name,
			'user_email'    => $user->user_email,
			'is_admin'      => $is_admin,
			'site_count'    => $site_count,
			'request_id'    => $request_id,
			'nonce'         => wp_create_nonce( 'captaincore_mcp_authorize_' . $request_id ),
			'action'        => self::authorize_url(),
			'switch_url'    => wp_logout_url( home_url( $back ) ),
			'error'         => '',
		];
		self::render( $view );
	}

	private static function render_error( $message ) {
		status_header( 400 );
		self::render( [ 'title' => 'Connect Claude', 'error' => $message ] );
	}

	private static function render( $view ) {
		header( 'Content-Type: text/html; charset=utf-8' );
		include plugin_dir_path( __DIR__ ) . 'templates/core-authorize.php';
		exit;
	}

	/**
	 * POST /captaincore/v1/oauth/token (form encoded). Exchanges a code for a
	 * freshly minted application password, presented as the bearer token.
	 */
	public static function token( \WP_REST_Request $request ) {
		$grant_type = (string) $request->get_param( 'grant_type' );
		if ( $grant_type === 'refresh_token' ) {
			return self::oauth_error( 'invalid_grant', 'Refresh tokens are not issued. Sign in again.' );
		}
		if ( $grant_type !== 'authorization_code' ) {
			return self::oauth_error( 'unsupported_grant_type', 'Only authorization_code is supported.' );
		}

		$code = (string) $request->get_param( 'code' );
		$key  = 'captaincore_mcp_code_' . hash( 'sha256', $code );
		$row  = $code !== '' ? get_transient( $key ) : false;
		delete_transient( $key );
		if ( ! $row ) {
			return self::oauth_error( 'invalid_grant', 'The authorization code is invalid or has expired.' );
		}
		if ( ! hash_equals( $row['client_id'], (string) $request->get_param( 'client_id' ) )
			|| $row['redirect_uri'] !== (string) $request->get_param( 'redirect_uri' ) ) {
			return self::oauth_error( 'invalid_grant', 'The authorization code was issued to a different client.' );
		}
		$verifier = (string) $request->get_param( 'code_verifier' );
		if ( ! preg_match( '/^[A-Za-z0-9._~-]{43,128}$/', $verifier )
			|| ! hash_equals( $row['code_challenge'], self::b64url( hash( 'sha256', $verifier, true ) ) ) ) {
			return self::oauth_error( 'invalid_grant', 'PKCE verification failed.' );
		}

		$user = get_userdata( (int) $row['user_id'] );
		if ( ! $user || ! wp_is_application_passwords_available_for_user( $user ) ) {
			return self::oauth_error( 'invalid_grant', 'Application passwords are not available for this account.' );
		}

		$name    = self::client_label( $row['redirect_uri'] ) . ' connector';
		$created = \WP_Application_Passwords::create_new_application_password( $user->ID, [ 'name' => $name, 'app_id' => self::APP_ID ] );
		if ( is_wp_error( $created ) ) {
			return self::oauth_error( 'server_error', $created->get_error_message(), 500 );
		}

		wp_set_current_user( $user->ID );
		ActivityLog::log( 'connected', 'user', $user->ID, $user->display_name, "Connected {$name}", [ 'uuid' => $created[1]['uuid'] ] );

		$response = new \WP_REST_Response( [
			'access_token' => self::TOKEN_PREFIX . self::b64url( $user->ID . ':' . $created[0] ),
			'token_type'   => 'Bearer',
			'scope'        => self::SCOPE,
		], 200 );
		$response->header( 'Cache-Control', 'no-store' );
		$response->header( 'Pragma', 'no-cache' );
		return $response;
	}

	/**
	 * POST /captaincore/v1/oauth/register (RFC 7591). Stateless: the client id
	 * is not stored, because the redirect allowlist is what binds a code to
	 * Claude. Rejects any redirect_uri outside that list up front.
	 */
	public static function register( \WP_REST_Request $request ) {
		$body = (array) $request->get_json_params();
		$uris = isset( $body['redirect_uris'] ) ? $body['redirect_uris'] : [];
		if ( ! is_array( $uris ) || empty( $uris ) ) {
			return self::oauth_error( 'invalid_redirect_uri', 'redirect_uris is required.' );
		}
		foreach ( $uris as $uri ) {
			if ( ! self::redirect_allowed( $uri ) ) {
				return self::oauth_error( 'invalid_redirect_uri', 'Only Claude redirect addresses are accepted.' );
			}
		}
		return new \WP_REST_Response( [
			'client_id'                  => 'ccmcp-' . bin2hex( random_bytes( 16 ) ),
			'client_id_issued_at'        => time(),
			'client_name'                => sanitize_text_field( (string) ( $body['client_name'] ?? 'Claude' ) ),
			'redirect_uris'              => array_values( $uris ),
			'grant_types'                => [ 'authorization_code' ],
			'response_types'             => [ 'code' ],
			'token_endpoint_auth_method' => 'none',
		], 201 );
	}

	private static function oauth_error( $error, $description, $status = 400 ) {
		$response = new \WP_REST_Response( [ 'error' => $error, 'error_description' => $description ], $status );
		$response->header( 'Cache-Control', 'no-store' );
		return $response;
	}

	/**
	 * Resolves the connector's bearer token to a user and signs them in for
	 * this request. Only passwords minted for Claude are accepted here.
	 *
	 * @return \WP_User|false|null User on success, null when no token was sent,
	 *                             false when one was sent but is not valid.
	 */
	public static function authenticate( \WP_REST_Request $request ) {
		$header = trim( (string) $request->get_header( 'authorization' ) );
		if ( $header === '' ) {
			return null;
		}
		if ( ! preg_match( '/^Bearer\s+' . self::TOKEN_PREFIX . '([A-Za-z0-9_-]{8,512})$/', $header, $m ) ) {
			return false;
		}
		$decoded = self::b64url_decode( $m[1] );
		if ( ! $decoded || ! preg_match( '/^(\d+):([A-Za-z0-9]{16,64})$/', $decoded, $parts ) ) {
			return false;
		}
		$user = get_userdata( (int) $parts[1] );
		if ( ! $user ) {
			return false;
		}

		self::$authenticating = true;
		self::$matched_item   = null;
		$result               = wp_authenticate_application_password( null, $user->user_login, $parts[2] );
		self::$authenticating = false;

		if ( ! ( $result instanceof \WP_User ) || ( self::$matched_item['app_id'] ?? '' ) !== self::APP_ID ) {
			return false;
		}
		wp_set_current_user( $result->ID );
		return $result;
	}

	/** 401 that points Claude at the discovery document. */
	public static function challenge( $token_sent = false ) {
		$response = new \WP_REST_Response( [ 'error' => 'invalid_token', 'error_description' => 'Sign in to connect.' ], 401 );
		$value    = 'Bearer ' . ( $token_sent ? 'error="invalid_token", ' : '' ) . 'resource_metadata="' . self::resource_metadata_url() . '"';
		$response->header( 'WWW-Authenticate', $value );
		return $response;
	}

	/** A Claude password authenticates the connector endpoint and nothing else. */
	public static function restrict( $error, $user, $item, $password ) {
		if ( ( $item['app_id'] ?? '' ) === self::APP_ID && ! self::$authenticating ) {
			$error->add( 'captaincore_mcp_only', 'This application password belongs to a Claude connection and only works with the connector.' );
		}
	}

	public static function remember_item( $user, $item ) {
		if ( self::$authenticating ) {
			self::$matched_item = $item;
		}
	}

	public static function b64url( $raw ) {
		return rtrim( strtr( base64_encode( $raw ), '+/', '-_' ), '=' );
	}

	private static function b64url_decode( $value ) {
		return base64_decode( strtr( $value, '-_', '+/' ), true );
	}
}
