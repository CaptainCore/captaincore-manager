<?php

namespace CaptainCore;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Web Push with no library: payload encryption (RFC 8291, aes128gcm) and
 * VAPID (RFC 8292), on PHP's openssl. Used for operators' phone
 * notifications (ChatPush).
 *
 * The VAPID key pair (P-256) is generated on first use and stored in the
 * captaincore_push_vapid site option, or comes from the
 * CAPTAINCORE_PUSH_VAPID_PRIVATE constant (PEM). Changing it invalidates
 * every subscription: browsers bind a subscription to the public key.
 */
class WebPush {

	// DER prefix of a P-256 SubjectPublicKeyInfo, followed by the 65-byte point.
	const SPKI_P256 = '3059301306072a8648ce3d020106082a8648ce3d030107034200';

	public static function b64url( $bytes ) {
		return rtrim( strtr( base64_encode( $bytes ), '+/', '-_' ), '=' );
	}

	public static function b64url_decode( $text ) {
		$text = strtr( preg_replace( '/\s+/', '', (string) $text ), '-_', '+/' );
		return (string) base64_decode( $text . str_repeat( '=', ( 4 - strlen( $text ) % 4 ) % 4 ), true );
	}

	// ── keys ────────────────────────────────────────────────────────────

	/** The VAPID private key PEM, generated and stored on first use. '' if openssl can't. */
	private static function vapid_pem() {
		if ( defined( 'CAPTAINCORE_PUSH_VAPID_PRIVATE' ) ) {
			return (string) CAPTAINCORE_PUSH_VAPID_PRIVATE;
		}
		$pem = (string) get_site_option( 'captaincore_push_vapid', '' );
		if ( $pem === '' ) {
			$key = openssl_pkey_new( [ 'curve_name' => 'prime256v1', 'private_key_type' => OPENSSL_KEYTYPE_EC ] );
			if ( ! $key || ! openssl_pkey_export( $key, $fresh ) ) {
				return '';
			}
			// add_site_option() refuses to overwrite, so two first requests agree.
			add_site_option( 'captaincore_push_vapid', $fresh );
			$pem = (string) get_site_option( 'captaincore_push_vapid', '' );
		}
		return $pem;
	}

	/** The 65-byte uncompressed public point of an EC key. */
	private static function public_point( $key ) {
		$d = openssl_pkey_get_details( $key );
		if ( ! $d || empty( $d['ec']['x'] ) ) {
			return '';
		}
		return "\x04" . str_pad( $d['ec']['x'], 32, "\0", STR_PAD_LEFT ) . str_pad( $d['ec']['y'], 32, "\0", STR_PAD_LEFT );
	}

	/** Base64url VAPID public key, for PushManager.subscribe({applicationServerKey}). */
	public static function public_key() {
		$pem = self::vapid_pem();
		$key = $pem !== '' ? openssl_pkey_get_private( $pem ) : false;
		return $key ? self::b64url( self::public_point( $key ) ) : '';
	}

	private static function public_key_resource( $point ) {
		$pem = "-----BEGIN PUBLIC KEY-----\n" . chunk_split( base64_encode( hex2bin( self::SPKI_P256 ) . $point ), 64, "\n" ) . "-----END PUBLIC KEY-----\n";
		return openssl_pkey_get_public( $pem );
	}

	/** A private key from its raw 32-byte scalar and public point (tests). */
	public static function private_key_resource( $d, $point ) {
		$der = hex2bin( '30770201010420' ) . $d . hex2bin( 'a00a06082a8648ce3d030107a144034200' ) . $point;
		return openssl_pkey_get_private( "-----BEGIN EC PRIVATE KEY-----\n" . chunk_split( base64_encode( $der ), 64, "\n" ) . "-----END EC PRIVATE KEY-----\n" );
	}

	// ── encryption (RFC 8291) ───────────────────────────────────────────

	/**
	 * Encrypt a payload for one subscription. $as_key and $salt are for
	 * tests only; normally both are fresh for every message.
	 *
	 * @return string|\WP_Error the aes128gcm body.
	 */
	public static function encrypt( $payload, $ua_public, $auth_secret, $as_key = null, $salt = null ) {
		if ( strlen( $ua_public ) !== 65 || $ua_public[0] !== "\x04" || strlen( $auth_secret ) !== 16 ) {
			return new \WP_Error( 'bad_subscription', 'Subscription keys are malformed.' );
		}
		$ua_key = self::public_key_resource( $ua_public );
		$as_key = $as_key ?: openssl_pkey_new( [ 'curve_name' => 'prime256v1', 'private_key_type' => OPENSSL_KEYTYPE_EC ] );
		$salt   = $salt ?? random_bytes( 16 );
		if ( ! $ua_key || ! $as_key ) {
			return new \WP_Error( 'bad_subscription', 'Subscription key is not a P-256 point.' );
		}
		$as_public = self::public_point( $as_key );
		$ecdh      = openssl_pkey_derive( $ua_key, $as_key, 32 );
		if ( ! $ecdh ) {
			return new \WP_Error( 'ecdh_failed', 'Key agreement failed.' );
		}
		$ikm   = hash_hkdf( 'sha256', $ecdh, 32, "WebPush: info\0" . $ua_public . $as_public, $auth_secret );
		$cek   = hash_hkdf( 'sha256', $ikm, 16, "Content-Encoding: aes128gcm\0", $salt );
		$nonce = hash_hkdf( 'sha256', $ikm, 12, "Content-Encoding: nonce\0", $salt );
		$tag   = '';
		$ct    = openssl_encrypt( $payload . "\x02", 'aes-128-gcm', $cek, OPENSSL_RAW_DATA, $nonce, $tag, '', 16 );
		if ( $ct === false ) {
			return new \WP_Error( 'encrypt_failed', 'Encryption failed.' );
		}
		return $salt . pack( 'N', 4096 ) . chr( 65 ) . $as_public . $ct . $tag;
	}

	// ── VAPID (RFC 8292) ────────────────────────────────────────────────

	/** The Authorization header value for a push service origin. */
	public static function vapid_header( $endpoint ) {
		$pem = self::vapid_pem();
		$key = $pem !== '' ? openssl_pkey_get_private( $pem ) : false;
		$p   = wp_parse_url( $endpoint );
		if ( ! $key || empty( $p['scheme'] ) || empty( $p['host'] ) ) {
			return '';
		}
		$admin  = (string) get_option( 'admin_email' );
		$claims = [
			'aud' => $p['scheme'] . '://' . $p['host'] . ( isset( $p['port'] ) ? ':' . $p['port'] : '' ),
			'exp' => time() + 12 * HOUR_IN_SECONDS,
			'sub' => is_email( $admin ) ? 'mailto:' . $admin : home_url(),
		];
		$input = self::b64url( '{"typ":"JWT","alg":"ES256"}' ) . '.' . self::b64url( wp_json_encode( $claims, JSON_UNESCAPED_SLASHES ) );
		if ( ! openssl_sign( $input, $der, $key, OPENSSL_ALGO_SHA256 ) ) {
			return '';
		}
		return 'vapid t=' . $input . '.' . self::b64url( self::der_to_raw( $der ) ) . ', k=' . self::b64url( self::public_point( $key ) );
	}

	/** openssl's DER ECDSA signature to JOSE's raw r||s (64 bytes). */
	private static function der_to_raw( $der ) {
		$pos = 2 + ( ord( $der[1] ) & 0x80 ? ord( $der[1] ) & 0x7f : 0 );
		$out = '';
		for ( $i = 0; $i < 2; $i++ ) {
			$len  = ord( $der[ $pos + 1 ] );
			$int  = substr( $der, $pos + 2, $len );
			$out .= str_pad( ltrim( $int, "\0" ), 32, "\0", STR_PAD_LEFT );
			$pos += 2 + $len;
		}
		return $out;
	}

	// ── send ────────────────────────────────────────────────────────────

	/**
	 * Deliver one message.
	 *
	 * @param object $sub endpoint, p256dh, auth (base64url).
	 * @param array  $opts ttl (seconds), urgency, topic.
	 * @return int HTTP status (0 = network error). 404/410 mean the
	 *             subscription is gone and should be deleted.
	 */
	public static function send( $sub, $payload, $opts = [] ) {
		$endpoint = (string) $sub->endpoint;
		if ( strpos( $endpoint, 'https://' ) !== 0 ) {
			return 0;
		}
		$body = self::encrypt( $payload, self::b64url_decode( $sub->p256dh ), self::b64url_decode( $sub->auth ) );
		$auth = self::vapid_header( $endpoint );
		if ( is_wp_error( $body ) || $auth === '' ) {
			return is_wp_error( $body ) ? 410 : 0;
		}
		$headers = [
			'Authorization'    => $auth,
			'Content-Encoding' => 'aes128gcm',
			'Content-Type'     => 'application/octet-stream',
			'TTL'              => (string) (int) ( $opts['ttl'] ?? DAY_IN_SECONDS ),
			'Urgency'          => (string) ( $opts['urgency'] ?? 'high' ),
		];
		// Topic lets a newer push replace an undelivered one. Apple's web push
		// service refuses it (400 BadWebPushTopic), so iPhones and Safari go
		// without; the notification's tag still keeps one per conversation.
		$apple = (bool) preg_match( '/(^|\.)push\.apple\.com$/', strtolower( (string) wp_parse_url( $endpoint, PHP_URL_HOST ) ) );
		if ( ! empty( $opts['topic'] ) && ! $apple ) {
			$headers['Topic'] = substr( preg_replace( '/[^A-Za-z0-9_-]/', '', (string) $opts['topic'] ), 0, 32 );
		}
		$response = wp_remote_post( $endpoint, [ 'body' => $body, 'headers' => $headers, 'timeout' => 5, 'redirection' => 0 ] );
		return is_wp_error( $response ) ? 0 : (int) wp_remote_retrieve_response_code( $response );
	}
}
