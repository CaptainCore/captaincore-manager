<?php

namespace CaptainCore;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * SSH public keys, replicated by the CLI (`ssh-keys deploy`) into a managed
 * block of authorized_keys on every environment on our own servers. Team
 * keys (operators, Servers → Team SSH keys) go everywhere; personal keys
 * (Profile → SSH keys) go to the environments of every site their owner can
 * access in the Manager, so access granted or taken away in the Manager is
 * access over SSH and SFTP too. Each environment's block is replaced whole
 * and its hash recorded; `ssh-keys deploy --stale` (after every change and
 * from core's cron) fixes the ones that differ. The CLI adds its own
 * management key itself, so a deploy can never lock CaptainCore out.
 *
 * Kinsta (option captaincore_ssh_keys_kinsta: off, a list of user ids while
 * testing, or 'all'): Kinsta's sshd also reads ~/.ssh/authorized_keys, so the
 * same block carries personal keys there (no team keys: operators reach
 * Kinsta through the company key). Environments that share one SSH endpoint
 * (a WP Freighter host and its tenants, one home folder) get a key only when
 * its owner can access every site on that endpoint, and the block is written
 * once, on the endpoint's first environment.
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

	/** Most personal keys one person may hold. */
	const PERSONAL_MAX = 10;

	/**
	 * Add a key. kind team: every site on our servers (operators, Servers →
	 * Team SSH keys). kind personal: a person's own key (Profile → SSH keys),
	 * on every site on our servers that person can access in the Manager.
	 */
	public static function add( $title, $public_key, $user_id, $kind = 'team' ) {
		$kind  = $kind === 'personal' ? 'personal' : 'team';
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
		if ( $kind === 'personal' && count( self::for_user( $user_id ) ) >= self::PERSONAL_MAX ) {
			return new \WP_Error( 'too_many', 'You can keep at most ' . self::PERSONAL_MAX . ' SSH keys. Remove one first.', [ 'status' => 400 ] );
		}
		$now = current_time( 'mysql' );
		$id  = self::insert( [
			'user_id'     => (int) $user_id,
			'kind'        => $kind,
			'title'       => $title,
			'key_type'    => $type,
			'public_key'  => $type . ' ' . $b64,
			'fingerprint' => $fingerprint,
			'created_at'  => $now,
			'updated_at'  => $now,
		] );
		return $id ? self::get( $id ) : new \WP_Error( 'db_error', 'Could not save the key.', [ 'status' => 500 ] );
	}

	/** Team keys (operators' shared keys). */
	public static function team() {
		return array_values( array_filter( ( new self )->all( 'public_key_id', 'ASC' ), function ( $k ) {
			return ( $k->kind ?? 'team' ) !== 'personal';
		} ) );
	}

	/** One person's own keys. */
	public static function for_user( $user_id ) {
		return self::where( [ 'user_id' => (int) $user_id, 'kind' => 'personal' ] );
	}

	private static function line( $key, $label ) {
		$label = trim( preg_replace( '/[^A-Za-z0-9._@-]+/', '-', $label ), '-' );
		return $key->public_key . ' cc:' . (int) $key->public_key_id . ':' . ( $label !== '' ? $label : 'key' );
	}

	/** The team lines: comment = cc:<id>:<title>. */
	public static function authorized_lines() {
		$lines = [];
		foreach ( self::team() as $key ) {
			$lines[] = self::line( $key, $key->title );
		}
		return $lines;
	}

	/** Site ids each person with personal keys can access (the Manager's own rules), per request. */
	private static $reach = null;

	private static function reach() {
		if ( self::$reach !== null ) {
			return self::$reach;
		}
		self::$reach = [];
		$owners = array_unique( array_map( function ( $k ) { return (int) $k->user_id; }, self::where( [ 'kind' => 'personal' ] ) ) );
		$was    = get_current_user_id();
		foreach ( $owners as $uid ) {
			if ( ! get_userdata( $uid ) ) {
				continue;
			}
			// Sites works out access for the current user (account levels,
			// domains-only members left out, active sites). Ask it as the owner.
			wp_set_current_user( $uid );
			$admin = ( new User )->is_admin();
			self::$reach[ $uid ] = $admin ? true : array_map( 'intval', (array) ( new Sites )->site_ids() );
		}
		wp_set_current_user( $was );
		return self::$reach;
	}

	/** Forget cached access (after keys or access changed in this request). */
	public static function reset_reach() {
		self::$reach     = null;
		self::$endpoints = null;
	}

	/**
	 * The lines one environment on our servers should hold: the team keys,
	 * then the personal keys of everyone who can access its site, labelled
	 * with the person's login so the file shows whose key it is.
	 */
	public static function lines_for( $environment ) {
		if ( $environment && (int) $environment->server_id <= 0 ) {
			return self::kinsta_lines_for( $environment );
		}
		$lines = self::authorized_lines();
		if ( ! $environment ) {
			return $lines;
		}
		$reach = self::reach();
		foreach ( self::where( [ 'kind' => 'personal' ] ) as $key ) {
			$sites = $reach[ (int) $key->user_id ] ?? [];
			if ( $sites === true || in_array( (int) $environment->site_id, (array) $sites, true ) ) {
				$user    = get_userdata( (int) $key->user_id );
				$lines[] = self::line( $key, ( $user ? $user->user_login : 'u' . (int) $key->user_id ) . '-' . $key->title );
			}
		}
		return $lines;
	}

	/** Hash of the managed lines; an environment is current when it reports the same. */
	public static function lines_hash( $lines = null ) {
		$lines = $lines ?? self::authorized_lines();
		return hash( 'sha256', implode( "\n", $lines ) );
	}

	/** Environments on our own servers (and Kinsta endpoints in scope), with whether each has the keys it should. */
	public static function deploy_status() {
		global $wpdb;
		$rows = $wpdb->get_results(
			"SELECT e.*, s.site, s.name
			 FROM {$wpdb->base_prefix}captaincore_environments e
			 JOIN {$wpdb->base_prefix}captaincore_sites s ON s.site_id = e.site_id
			 WHERE e.server_id > 0 AND s.status = 'active' ORDER BY s.name"
		);
		$out = [];
		foreach ( $rows as $row ) {
			$out[] = self::status_row( $row, self::lines_for( $row ), 'ours' );
		}
		foreach ( self::kinsta_endpoints() as $group ) {
			$rep  = $group['rep'];
			$want = self::kinsta_lines_for( $rep );
			$row  = self::status_row( $rep, $want, 'kinsta' );
			// Endpoints with nothing to hold and no block yet are left alone.
			if ( $want || ! $row['current'] ) {
				$out[] = $row + [ 'shared' => count( $group['site_ids'] ) ];
			}
		}
		return $out;
	}

	private static function status_row( $row, $want, $host ) {
		$d    = json_decode( (string) $row->details );
		// What the box holds: the daily sync's reading (ssh_keys_live), which
		// each deploy also updates, else the last deploy's own record.
		$have = $d->ssh_keys_live ?? ( $d->ssh_keys_hash ?? '' );
		return [
			'environment_id' => (int) $row->environment_id,
			'site'           => $row->site,
			'name'           => $row->name,
			'environment'    => $row->environment,
			'host'           => $host,
			'target'         => $row->site . ( strtolower( $row->environment ) === 'production' ? '' : '-' . strtolower( $row->environment ) ),
			'keys'           => count( $want ),
			'current'        => $have === self::lines_hash( $want ) || ( ! $want && in_array( $have, [ '', 'none' ], true ) ),
			'deployed_at'    => $d->ssh_keys_deployed_at ?? '',
			'drift'          => $d->ssh_keys_drift ?? [],
		];
	}

	/** Whether the CLI may write the block on this environment. */
	public static function managed( $environment ) {
		if ( ! $environment ) {
			return false;
		}
		if ( (int) $environment->server_id > 0 ) {
			return true;
		}
		return self::endpoint_for( $environment ) !== null;
	}

	/** Whose personal keys go to Kinsta: true (everyone), or a list of user ids (empty = off). */
	public static function kinsta_users() {
		$v = get_site_option( 'captaincore_ssh_keys_kinsta', '' );
		if ( $v === 'all' ) {
			return true;
		}
		$ids = is_array( $v ) ? $v : preg_split( '/[\s,]+/', (string) $v );
		return array_values( array_filter( array_map( 'intval', $ids ) ) );
	}

	/**
	 * Kinsta SSH endpoints (user@address:port) in scope, per request: each with
	 * its first environment (where the block is written and recorded), every
	 * environment on it and the ids of every site on it, inactive ones too.
	 */
	private static $endpoints = null;
	private static $endpoint_of = [];

	private static function kinsta_endpoints() {
		if ( self::$endpoints !== null ) {
			return self::$endpoints;
		}
		self::$endpoints   = [];
		self::$endpoint_of = [];
		$users = self::kinsta_users();
		if ( $users === [] ) {
			return self::$endpoints;
		}
		global $wpdb;
		$rows = $wpdb->get_results(
			"SELECT e.*, s.site, s.name, s.status AS site_status, s.provider
			 FROM {$wpdb->base_prefix}captaincore_environments e
			 JOIN {$wpdb->base_prefix}captaincore_sites s ON s.site_id = e.site_id
			 WHERE ( e.server_id IS NULL OR e.server_id <= 0 ) AND e.address <> '' AND e.port <> ''
			 ORDER BY e.environment_id"
		);
		$groups = [];
		foreach ( $rows as $row ) {
			$k = strtolower( trim( $row->username ) . '@' . trim( $row->address ) . ':' . trim( $row->port ) );
			$groups[ $k ][] = $row;
		}
		foreach ( $groups as $k => $envs ) {
			$active = array_values( array_filter( $envs, function ( $e ) { return $e->site_status === 'active'; } ) );
			// Kinsta only, and every site on the endpoint must be a Kinsta site.
			if ( ! $active || count( array_filter( $envs, function ( $e ) { return $e->provider !== 'kinsta'; } ) ) ) {
				continue;
			}
			self::$endpoints[ $k ] = [
				'rep'             => $active[0],
				'environment_ids' => array_map( function ( $e ) { return (int) $e->environment_id; }, $envs ),
				'site_ids'        => array_values( array_unique( array_map( function ( $e ) { return (int) $e->site_id; }, $envs ) ) ),
			];
			foreach ( $envs as $e ) {
				self::$endpoint_of[ (int) $e->environment_id ] = $k;
			}
		}
		return self::$endpoints;
	}

	/** The Kinsta endpoint in scope an environment is on, or null. */
	private static function endpoint_for( $environment ) {
		$all = self::kinsta_endpoints();
		$k   = self::$endpoint_of[ (int) $environment->environment_id ] ?? null;
		return $k !== null ? $all[ $k ] : null;
	}

	/** The personal lines a Kinsta environment's endpoint should hold. */
	public static function kinsta_lines_for( $environment ) {
		$users = self::kinsta_users();
		if ( $users === [] ) {
			return [];
		}
		$group = self::endpoint_for( $environment );
		if ( ! $group ) {
			return [];
		}
		$reach = self::reach();
		$lines = [];
		foreach ( self::where( [ 'kind' => 'personal' ] ) as $key ) {
			$uid = (int) $key->user_id;
			if ( $users !== true && ! in_array( $uid, $users, true ) ) {
				continue;
			}
			$sites = $reach[ $uid ] ?? [];
			if ( $sites !== true && array_diff( $group['site_ids'], (array) $sites ) ) {
				continue; // cannot access every site sharing this home folder
			}
			$user    = get_userdata( $uid );
			$lines[] = self::line( $key, ( $user ? $user->user_login : 'u' . $uid ) . '-' . $key->title );
		}
		return $lines;
	}

	/** Kinsta environments one person's keys reach (0 while Kinsta is off for them). */
	public static function user_kinsta_reach_count( $user_id ) {
		$users = self::kinsta_users();
		if ( $users === [] || ( $users !== true && ! in_array( (int) $user_id, $users, true ) ) ) {
			return 0;
		}
		$was = get_current_user_id();
		wp_set_current_user( (int) $user_id );
		$all   = ( new User )->is_admin();
		$sites = $all ? [] : array_map( 'intval', (array) ( new Sites )->site_ids() );
		wp_set_current_user( $was );
		$n = 0;
		foreach ( self::kinsta_endpoints() as $g ) {
			if ( $all || ! array_diff( $g['site_ids'], $sites ) ) {
				$n++;
			}
		}
		return $n;
	}

	/**
	 * The daily sync read the block (ssh_keys_live). When it no longer matches
	 * what the last deploy wrote, something on the box changed it: keep the
	 * last few times so a host that wipes the file shows up.
	 */
	public static function note_live( $details, $previous_live ) {
		$live = $details->ssh_keys_live ?? '';
		$sent = $details->ssh_keys_hash ?? '';
		if ( $live === '' || $sent === '' || $live === $sent || $live === $previous_live ) {
			return $details;
		}
		$drift   = array_slice( (array) ( $details->ssh_keys_drift ?? [] ), -9 );
		$drift[] = [ 'at' => current_time( 'mysql' ), 'found' => $live === 'none' ? 'no block' : 'changed block' ];
		$details->ssh_keys_drift = $drift;
		return $details;
	}

	/** CLI targets (<site>[-<env>]) whose keys are not what they should be. */
	public static function stale_targets() {
		return array_values( array_map( function ( $r ) { return $r['target']; },
			array_filter( self::deploy_status(), function ( $r ) { return ! $r['current']; } ) ) );
	}

	/** Environments on our servers one person's keys reach. */
	public static function user_reach_count( $user_id ) {
		global $wpdb;
		$was = get_current_user_id();
		wp_set_current_user( (int) $user_id );
		$all   = ( new User )->is_admin();
		$sites = $all ? [] : array_map( 'intval', (array) ( new Sites )->site_ids() );
		wp_set_current_user( $was );
		$rows = $wpdb->get_results( "SELECT e.site_id FROM {$wpdb->base_prefix}captaincore_environments e
			JOIN {$wpdb->base_prefix}captaincore_sites s ON s.site_id = e.site_id WHERE e.server_id > 0 AND s.status = 'active'" );
		return count( array_filter( $rows, function ( $r ) use ( $all, $sites ) { return $all || in_array( (int) $r->site_id, $sites, true ); } ) );
	}

	/** Called by the CLI after it wrote the block (`authorized-keys-deployed`). */
	public static function record_deploy( $environment_id, $hash, $count ) {
		$env = Environments::get( (int) $environment_id );
		if ( ! $env ) {
			return false;
		}
		$details = json_decode( (string) $env->details ) ?: new \stdClass();
		$details->ssh_keys_hash        = preg_match( '/^[a-f0-9]{64}$/', (string) $hash ) ? $hash : '';
		$details->ssh_keys_live        = $details->ssh_keys_hash; // what the block holds now
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

	/**
	 * Bring only the environments whose keys are out of date up to date (the
	 * CLI asks the Manager which). Core's cron runs the same every few
	 * minutes, which also catches access changes (a person added to an
	 * account, a site shared or unshared).
	 */
	public static function deploy_stale() {
		$res = Run::background_task( [ 'ssh-keys', 'deploy', '--stale' ] );
		return is_wp_error( $res ) ? '' : ( $res['token'] ?? '' );
	}
}
