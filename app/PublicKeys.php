<?php

namespace CaptainCore;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Operator SSH public keys, replicated by the CLI (`ssh-keys deploy`) into
 * a managed block of authorized_keys on every site on our own servers.
 * Removing a key removes it everywhere on the next deploy, since the block
 * is replaced whole. The CLI adds its own management key itself, so a
 * deploy can never lock CaptainCore out.
 */
class PublicKeys extends DB {

	static $primary_key = 'public_key_id';

	const TYPES = [ 'ssh-ed25519', 'ssh-rsa', 'ecdsa-sha2-nistp256', 'ecdsa-sha2-nistp384', 'ecdsa-sha2-nistp521', 'sk-ssh-ed25519@openssh.com', 'sk-ecdsa-sha2-nistp256@openssh.com' ];

	/**
	 * Parse one OpenSSH public key line. Returns [ type, blob, comment,
	 * fingerprint ] or WP_Error. Options (command=, from=) are refused:
	 * CaptainCore writes the line itself.
	 */
	public static function parse( $text ) {
		$text = trim( (string) $text );
		if ( $text === '' || strpos( $text, "\n" ) !== false || strpos( $text, "\r" ) !== false ) {
			return new \WP_Error( 'invalid', 'Paste one public key on a single line.', [ 'status' => 400 ] );
		}
		$parts = preg_split( '/\s+/', $text, 3 );
		if ( count( $parts ) < 2 || ! in_array( $parts[0], self::TYPES, true ) ) {
			return new \WP_Error( 'invalid', 'That is not a supported public key. Use an ssh-ed25519, ecdsa or ssh-rsa key (the .pub file).', [ 'status' => 400 ] );
		}
		[ $type, $b64 ] = $parts;
		if ( ! preg_match( '#^[A-Za-z0-9+/]+={0,3}$#', $b64 ) || ( $blob = base64_decode( $b64, true ) ) === false ) {
			return new \WP_Error( 'invalid', 'The key data is not valid base64.', [ 'status' => 400 ] );
		}
		$reader = self::reader( $blob );
		if ( $reader( 'string' ) !== $type ) {
			return new \WP_Error( 'invalid', 'The key data does not match its type.', [ 'status' => 400 ] );
		}
		if ( $type === 'ssh-rsa' ) {
			$reader( 'string' ); // exponent
			$n    = ltrim( (string) $reader( 'string' ), "\0" );
			$bits = strlen( $n ) * 8;
			if ( $bits < 2048 ) {
				return new \WP_Error( 'invalid', "RSA keys must be at least 2048 bits (this one is {$bits}).", [ 'status' => 400 ] );
			}
		}
		$fingerprint = 'SHA256:' . rtrim( base64_encode( hash( 'sha256', $blob, true ) ), '=' );
		return [ $type, $b64, isset( $parts[2] ) ? trim( $parts[2] ) : '', $fingerprint ];
	}

	/** Reads SSH wire-format strings from a key blob. */
	private static function reader( $blob ) {
		$pos = 0;
		return function () use ( $blob, &$pos ) {
			if ( $pos + 4 > strlen( $blob ) ) {
				return null;
			}
			$len  = unpack( 'N', substr( $blob, $pos, 4 ) )[1];
			$pos += 4;
			if ( $len > strlen( $blob ) - $pos ) {
				return null;
			}
			$out  = substr( $blob, $pos, $len );
			$pos += $len;
			return $out;
		};
	}

	public static function add( $title, $public_key, $user_id ) {
		$title = substr( sanitize_text_field( (string) $title ), 0, 100 );
		$parsed = self::parse( $public_key );
		if ( is_wp_error( $parsed ) ) {
			return $parsed;
		}
		[ $type, $b64, $comment, $fingerprint ] = $parsed;
		if ( $title === '' ) {
			$title = $comment !== '' ? substr( sanitize_text_field( $comment ), 0, 100 ) : $type;
		}
		if ( self::where( [ 'fingerprint' => $fingerprint ] ) ) {
			return new \WP_Error( 'duplicate', 'That key is already added.', [ 'status' => 409 ] );
		}
		$now = current_time( 'mysql' );
		$id  = self::insert( [
			'user_id'     => (int) $user_id,
			'title'       => $title,
			'key_type'    => $type,
			'public_key'  => $type . ' ' . $b64,
			'fingerprint' => $fingerprint,
			'created_at'  => $now,
			'updated_at'  => $now,
		] );
		return $id ? self::get( $id ) : new \WP_Error( 'db_error', 'Could not save the key.', [ 'status' => 500 ] );
	}

	/** The authorized_keys lines the Manager wants, comment = cc:<id>:<title>. */
	public static function authorized_lines() {
		$lines = [];
		foreach ( ( new self )->all( 'public_key_id', 'ASC' ) as $key ) {
			$label   = preg_replace( '/[^A-Za-z0-9._@-]+/', '-', $key->title );
			$lines[] = $key->public_key . ' cc:' . (int) $key->public_key_id . ':' . trim( $label, '-' );
		}
		return $lines;
	}

	/** Hash of the managed lines; an environment is current when it reports the same. */
	public static function lines_hash( $lines = null ) {
		$lines = $lines ?? self::authorized_lines();
		return hash( 'sha256', implode( "\n", $lines ) );
	}

	/** Environments on our own servers, with whether each has the current keys. */
	public static function deploy_status() {
		global $wpdb;
		$hash = self::lines_hash();
		$rows = $wpdb->get_results(
			"SELECT e.environment_id, e.environment, e.details, s.site, s.name
			 FROM {$wpdb->base_prefix}captaincore_environments e
			 JOIN {$wpdb->base_prefix}captaincore_sites s ON s.site_id = e.site_id
			 WHERE e.server_id > 0 AND s.status = 'active' ORDER BY s.name"
		);
		$out = [];
		foreach ( $rows as $row ) {
			$d     = json_decode( (string) $row->details );
			$out[] = [
				'environment_id' => (int) $row->environment_id,
				'site'           => $row->site,
				'name'           => $row->name,
				'environment'    => $row->environment,
				'current'        => ( $d->ssh_keys_hash ?? '' ) === $hash,
				'deployed_at'    => $d->ssh_keys_deployed_at ?? '',
			];
		}
		return $out;
	}

	/** Called by the CLI after it wrote the block (`authorized-keys-deployed`). */
	public static function record_deploy( $environment_id, $hash, $count ) {
		$env = Environments::get( (int) $environment_id );
		if ( ! $env ) {
			return false;
		}
		$details = json_decode( (string) $env->details ) ?: new \stdClass();
		$details->ssh_keys_hash        = preg_match( '/^[a-f0-9]{64}$/', (string) $hash ) ? $hash : '';
		$details->ssh_keys_count       = (int) $count;
		$details->ssh_keys_deployed_at = current_time( 'mysql' );
		Environments::update( [ 'details' => wp_json_encode( $details ) ], [ 'environment_id' => (int) $environment_id ] );
		return true;
	}

	/**
	 * Ask the CLI to redeploy every site on our servers. Runs in the
	 * background on the CLI server (a /tasks job would wait for a browser to
	 * attach). Returns the job token, or '' when the CLI server is unreachable.
	 */
	public static function deploy_all() {
		$res = Run::background_task( [ 'ssh-keys', 'deploy', '--all' ] );
		return is_wp_error( $res ) ? '' : ( $res['token'] ?? '' );
	}
}
