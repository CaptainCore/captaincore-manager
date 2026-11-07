<?php

namespace CaptainCore;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The link between the Manager and the captaincore-chat relay.
 *
 * The relay stores nothing. The Manager signs two things with one Ed25519
 * key, and the relay holds only the public half:
 *   - identity tokens, which browsers present on the websocket;
 *   - publish calls, made after every change (the `captaincore_chat_event`
 *     action), which the relay fans out to the right connections.
 * Formats: captaincore-chat docs/protocol.md.
 *
 * Settings (a constant wins over the site option):
 *   CAPTAINCORE_CHAT_RELAY_URL / captaincore_chat_relay_url
 *       The relay's public URL, e.g. https://chat.anchor.host. Browsers
 *       connect to <url>/v1/ws and tokens carry it as the audience.
 *   CAPTAINCORE_CHAT_PUBLISH_URL / captaincore_chat_publish_url
 *       Optional server-to-server URL for publish calls. Defaults to the
 *       relay URL.
 *   CAPTAINCORE_CHAT_SECRET_KEY / captaincore_chat_secret_key
 *       Base64 64-byte sodium secret key. Generated on first use when unset.
 *       Never sent to a browser.
 */
class ChatRelay {

	const TOKEN_TTL       = 600;
	const TOKEN_CONTEXT   = 'captaincore-chat.v1.';
	const PUBLISH_CONTEXT = 'captaincore-chat.publish.v1.';

	/**
	 * How many of the customer's own connections the last publish reached:
	 * null when unknown (no relay, or it failed), else a count.
	 */
	private static $last_customer = null;

	public static function last_customer_delivery() {
		return self::$last_customer;
	}

	/** Operators the relay says are watching the inbox, from the last publish ([] when unknown). */
	private static $last_watching = [];

	public static function last_watching() {
		return self::$last_watching;
	}

	public static function relay_url() {
		$url = defined( 'CAPTAINCORE_CHAT_RELAY_URL' ) ? CAPTAINCORE_CHAT_RELAY_URL : get_site_option( 'captaincore_chat_relay_url', '' );
		return self::normalize_url( (string) $url );
	}

	public static function publish_url() {
		$url = defined( 'CAPTAINCORE_CHAT_PUBLISH_URL' ) ? CAPTAINCORE_CHAT_PUBLISH_URL : get_site_option( 'captaincore_chat_publish_url', '' );
		$url = self::normalize_url( (string) $url );
		return $url !== '' ? $url : self::relay_url();
	}

	/** Whether live chat can run: a relay URL and a usable key. */
	public static function configured() {
		return self::relay_url() !== '' && self::secret_key() !== '';
	}

	/** The issuer as the relay normalizes it: lowercase scheme and host, no trailing slash. */
	public static function issuer() {
		return self::normalize_url( home_url() );
	}

	/** The browser origin of this Manager, for the relay's allowlist. */
	public static function origin() {
		$p = wp_parse_url( home_url() );
		return strtolower( $p['scheme'] . '://' . $p['host'] ) . ( isset( $p['port'] ) ? ':' . $p['port'] : '' );
	}

	/** The websocket URL browsers connect to. */
	public static function websocket_url() {
		$url = self::relay_url();
		return $url === '' ? '' : preg_replace( '#^http#', 'ws', $url ) . '/v1/ws';
	}

	private static function normalize_url( $url ) {
		$url = untrailingslashit( trim( $url ) );
		$p   = $url !== '' ? wp_parse_url( $url ) : false;
		if ( ! $p || empty( $p['scheme'] ) || empty( $p['host'] ) || ! in_array( strtolower( $p['scheme'] ), [ 'http', 'https' ], true ) ) {
			return '';
		}
		return strtolower( $p['scheme'] . '://' . $p['host'] ) . ( isset( $p['port'] ) ? ':' . $p['port'] : '' ) . ( $p['path'] ?? '' );
	}

	// ── keys ────────────────────────────────────────────────────────────

	/** The raw 64-byte secret key, generated and stored on first use. '' if sodium is missing. */
	private static function secret_key() {
		if ( ! function_exists( 'sodium_crypto_sign_keypair' ) ) {
			return '';
		}
		if ( defined( 'CAPTAINCORE_CHAT_SECRET_KEY' ) ) {
			$raw = base64_decode( (string) CAPTAINCORE_CHAT_SECRET_KEY, true );
			return is_string( $raw ) && strlen( $raw ) === SODIUM_CRYPTO_SIGN_SECRETKEYBYTES ? $raw : '';
		}
		$stored = (string) get_site_option( 'captaincore_chat_secret_key', '' );
		if ( $stored === '' ) {
			$fresh = base64_encode( sodium_crypto_sign_secretkey( sodium_crypto_sign_keypair() ) );
			// add_site_option() refuses to overwrite, so two first requests agree on one key.
			add_site_option( 'captaincore_chat_secret_key', $fresh );
			$stored = (string) get_site_option( 'captaincore_chat_secret_key', '' );
		}
		$raw = base64_decode( $stored, true );
		return is_string( $raw ) && strlen( $raw ) === SODIUM_CRYPTO_SIGN_SECRETKEYBYTES ? $raw : '';
	}

	/**
	 * A key for HMACs that never leave the Manager (reply-by-email
	 * addresses), derived from the signing key so there is one secret to
	 * keep. '' when no key is available.
	 */
	public static function derived_key( $purpose ) {
		$sk = self::secret_key();
		return $sk === '' ? '' : hash_hmac( 'sha256', 'captaincore-chat.' . $purpose, $sk, true );
	}

	/** Base64 public key for the relay's public_keys. */
	public static function public_key() {
		$sk = self::secret_key();
		return $sk === '' ? '' : base64_encode( sodium_crypto_sign_publickey_from_secretkey( $sk ) );
	}

	/** The relay's config.json for this Manager. */
	public static function relay_config() {
		return [
			'listen'          => '127.0.0.1:8490',
			'issuer'          => self::issuer(),
			'audience'        => self::relay_url(),
			'public_keys'     => [ self::public_key() ],
			'allowed_origins' => [ self::origin() ],
		];
	}

	private static function b64url( $bytes ) {
		return rtrim( strtr( base64_encode( $bytes ), '+/', '-_' ), '=' );
	}

	// ── tokens ──────────────────────────────────────────────────────────

	/**
	 * A short-lived identity for the websocket.
	 *
	 * @return array|\WP_Error token, websocket, expires
	 */
	public static function token( $user_id ) {
		$user = get_userdata( (int) $user_id );
		if ( ! $user || ! Chat::enabled_for( (int) $user_id ) ) {
			return new \WP_Error( 'forbidden', 'Chat is not available for this account.', [ 'status' => 403 ] );
		}
		if ( ! self::configured() ) {
			return new \WP_Error( 'chat_offline', 'Live chat is not configured.', [ 'status' => 503 ] );
		}
		$now     = time();
		$claims  = [
			'iss'      => self::issuer(),
			'aud'      => self::relay_url(),
			'sub'      => (int) $user->ID,
			'name'     => (string) $user->display_name,
			'email'    => (string) $user->user_email,
			'role'     => Chat::is_operator( (int) $user->ID ) ? 'operator' : 'customer',
			'accounts' => [],
			'sites'    => [],
			'iat'      => $now,
			'exp'      => $now + self::TOKEN_TTL,
			'jti'      => bin2hex( random_bytes( 8 ) ),
		];
		$payload = self::b64url( wp_json_encode( $claims, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) );
		$sig     = sodium_crypto_sign_detached( self::TOKEN_CONTEXT . $payload, self::secret_key() );
		return [
			'token'     => 'v1.' . $payload . '.' . self::b64url( $sig ),
			'websocket' => self::websocket_url(),
			'expires'   => $claims['exp'],
		];
	}

	// ── publish ─────────────────────────────────────────────────────────

	/**
	 * Hooked to `captaincore_chat_event`. Best effort: the change is already
	 * stored, so a relay that is down only costs live delivery. Clients
	 * refetch when their socket reconnects.
	 */
	public static function publish( $event, $customer_user_id, $internal ) {
		self::$last_customer = null;
		self::$last_watching = [];
		$url = self::publish_url();
		if ( $url === '' || ! self::configured() ) {
			return;
		}
		$body = wp_json_encode( [
			'event'            => $event,
			'customer_user_id' => max( 0, (int) $customer_user_id ),
			'internal'         => (bool) $internal,
		], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
		if ( ! $body ) {
			return;
		}
		$ts       = (string) time();
		$sig      = self::b64url( sodium_crypto_sign_detached( self::PUBLISH_CONTEXT . $ts . '.' . $body, self::secret_key() ) );
		$response = wp_remote_post( $url . '/internal/publish', [
			'body'        => $body,
			'timeout'     => 3,
			'redirection' => 0,
			'headers'     => [
				'Content-Type'     => 'application/json',
				'X-Chat-Timestamp' => $ts,
				'X-Chat-Signature' => $sig,
			],
		] );
		$code = is_wp_error( $response ) ? 0 : (int) wp_remote_retrieve_response_code( $response );
		if ( $code === 200 ) {
			$out = json_decode( (string) wp_remote_retrieve_body( $response ), true );
			if ( is_array( $out ) && isset( $out['customer'] ) ) {
				self::$last_customer = (int) $out['customer'];
			}
			if ( is_array( $out ) && isset( $out['watching'] ) && is_array( $out['watching'] ) ) {
				self::$last_watching = array_map( 'intval', $out['watching'] );
			}
		}
		if ( $code !== 200 && $code !== 409 && defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( 'CaptainCore Chat: publish failed (' . ( is_wp_error( $response ) ? $response->get_error_message() : 'HTTP ' . $code ) . ')' );
		}
	}
}
