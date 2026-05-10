<?php

namespace CaptainCore;

/**
 * Direct HTTP client for the WP Registry plugin's REST API.
 *
 * Skips the public Cloudflare worker (which has long edge cache TTLs) and talks
 * straight to crew.wpregistry.io with an application password, so anchor.host
 * sees fresh audit + patch data without waiting for the worker cache to expire.
 *
 * Configuration (stored in wp_options so you can rotate without code edits):
 *   captaincore_registry_url   — e.g. https://crew.wpregistry.io  (no trailing slash)
 *   captaincore_registry_user  — WP user_login on the registry
 *   captaincore_registry_pass  — application password for that user
 *
 * Responses are transient-cached locally (5 min default) keyed by request path.
 * Call `flush_cache()` after a write so the next read hits the origin.
 */
class RegistryClient {

	const URL_OPTION       = 'captaincore_registry_url';
	const USER_OPTION      = 'captaincore_registry_user';
	const PASS_OPTION      = 'captaincore_registry_pass';
	const TRANSIENT_PREFIX = 'cc_registry_';
	const DEFAULT_TTL      = 300; // 5 minutes

	public static function ready(): bool {
		return self::url() !== '' && self::user() !== '' && self::pass() !== '';
	}

	private static function url(): string  { return rtrim( (string) get_option( self::URL_OPTION,  '' ), '/' ); }
	private static function user(): string { return (string) get_option( self::USER_OPTION, '' ); }
	private static function pass(): string { return (string) get_option( self::PASS_OPTION, '' ); }

	private static function auth_header(): string {
		return 'Basic ' . base64_encode( self::user() . ':' . self::pass() );
	}

	/**
	 * GET /wp-json/registry/v1/{path} with transient cache.
	 *
	 * @param string $path Without leading slash. Query string allowed.
	 * @param int    $ttl  Transient TTL in seconds. 0 = no caching.
	 * @return array|null  Decoded JSON, or null on error.
	 */
	public static function get( string $path, int $ttl = self::DEFAULT_TTL ): ?array {
		if ( ! self::ready() ) {
			return null;
		}

		$cache_key = self::TRANSIENT_PREFIX . md5( $path );
		if ( $ttl > 0 ) {
			$cached = get_transient( $cache_key );
			if ( $cached !== false ) {
				return $cached;
			}
		}

		$response = wp_remote_get( self::url() . '/wp-json/registry/v1/' . ltrim( $path, '/' ), [
			'timeout' => 20,
			'headers' => [ 'Authorization' => self::auth_header() ],
		] );

		if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
			return null;
		}

		$data = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( ! is_array( $data ) ) {
			return null;
		}

		if ( $ttl > 0 ) {
			set_transient( $cache_key, $data, $ttl );
		}
		return $data;
	}

	/**
	 * Hash manifest for a component type, returning the inner hashes lookup
	 * keyed by sha256. Empty array if not configured or on fetch error —
	 * callers should treat this as "registry unavailable" and fail open.
	 *
	 * @param string $type   plugins | themes | mu-plugins | files
	 * @param string $model  Optional auditor model ID; restricts to hashes
	 *                       audited by that specific model.
	 */
	public static function manifest( string $type, string $model = '' ): array {
		$path = 'manifest/' . rawurlencode( $type );
		if ( $model !== '' ) {
			$path .= '?model=' . rawurlencode( $model );
		}
		$data = self::get( $path );
		return is_array( $data ) && isset( $data['hashes'] ) && is_array( $data['hashes'] )
			? $data['hashes']
			: [];
	}

	/**
	 * Patches manifest, keyed by `type|slug|version`.
	 */
	public static function patches_manifest(): array {
		$data = self::get( 'patches/manifest' );
		return is_array( $data ) && isset( $data['patches'] ) && is_array( $data['patches'] )
			? $data['patches']
			: [];
	}

	/**
	 * All audit history for a slug (with severity rollups + auditor list per record).
	 */
	public static function lookup( string $slug, string $type = '' ): ?array {
		$path = 'lookup/' . rawurlencode( $slug );
		if ( $type !== '' ) {
			$path .= '?type=' . rawurlencode( $type );
		}
		return self::get( $path );
	}

	/**
	 * Per-hash findings detail (component metadata + audits + flat findings array).
	 */
	public static function findings_by_hash( string $hash ): ?array {
		if ( ! preg_match( '/^[a-f0-9]{64}$/i', $hash ) ) {
			return null;
		}
		return self::get( 'findings-by-hash/' . strtolower( $hash ) );
	}

	/**
	 * Bust every transient this client wrote.
	 */
	public static function flush_cache(): void {
		global $wpdb;
		$like_value   = $wpdb->esc_like( '_transient_'         . self::TRANSIENT_PREFIX ) . '%';
		$like_timeout = $wpdb->esc_like( '_transient_timeout_' . self::TRANSIENT_PREFIX ) . '%';
		$wpdb->query( $wpdb->prepare(
			"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
			$like_value,
			$like_timeout
		) );
	}
}
