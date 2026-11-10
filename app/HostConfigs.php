<?php

namespace CaptainCore;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Hosting configuration for environments on our own servers. Every change is
 * a new revision row (never edited in place); the CLI's `host apply` renders
 * the latest one onto the server (edge Caddy site block, PHP settings, memory)
 * and reports back whether it applied. Restoring an old revision copies it
 * into a new one, so the history only ever grows.
 */
class HostConfigs extends DB {

	static $primary_key = 'host_config_id';

	/**
	 * PHP versions and the site image each runs from (built on a server with
	 * `captaincore node image build <server-id> --php=8.x`). A site's PHP
	 * version is its container's image: `host apply` rebuilds the container on
	 * the other image when the version changes, keeping its files, database,
	 * address and limits.
	 */
	const PHP_IMAGES = [
		'8.3' => 'cc-wp-php83',
		'8.4' => 'cc-wp-php84',
		'8.5' => 'cc-wp-php85',
	];

	const LIMITS = [
		'workers'             => [ 1, 8 ],
		'max_workers'         => [ 1, 16 ],
		'memory_limit'        => [ 64, 1024 ],   // MB, PHP memory_limit
		'upload_max_filesize' => [ 2, 512 ],     // MB
		'max_execution_time'  => [ 30, 300 ],    // seconds
		'memory_mb'           => [ 512, 4096 ],  // container memory limit
	];

	/** Starting config for an environment that has no revision yet. */
	public static function default_for( $environment ) {
		$site    = Sites::get( (int) $environment->site_id );
		$details = json_decode( (string) $environment->details ) ?: new \stdClass();
		$home    = (string) ( $environment->home_url ?? '' );
		$host    = $home ? (string) wp_parse_url( $home, PHP_URL_HOST ) : '';
		if ( $host === '' && $site ) {
			$host = strtolower( (string) $site->name );
		}
		return [
			'domains'             => $host ? [ [ 'name' => $host, 'primary' => true, 'www' => false ] ] : [],
			'force_https'         => true,
			'redirect_to_primary' => false,
			'php'                 => [
				'version'             => $details->php_version_image ?? '8.4',
				'workers'             => 2,
				'max_workers'         => 4,
				'memory_limit'        => 256,
				'upload_max_filesize' => 128,
				'max_execution_time'  => 120,
			],
			'memory_mb'           => 1024,
			'redirects'           => [],
			'ip_deny'             => [],
			'password_protect'    => [ 'enabled' => false, 'username' => '', 'password_hash' => '' ],
			'debug'               => false,
			'noindex'             => true,
			'mail'                => 'relay',
			'suspended'           => false,
			'page_cache'          => [ 'enabled' => true, 'ttl' => 60, 'exclude' => [] ],
			'apps'                => [],
		];
	}

	public static function latest( $environment_id ) {
		global $wpdb;
		return $wpdb->get_row( $wpdb->prepare(
			"SELECT * FROM {$wpdb->base_prefix}captaincore_host_configs WHERE environment_id = %d ORDER BY revision DESC LIMIT 1",
			(int) $environment_id ) );
	}

	public static function revision( $environment_id, $revision ) {
		global $wpdb;
		return $wpdb->get_row( $wpdb->prepare(
			"SELECT * FROM {$wpdb->base_prefix}captaincore_host_configs WHERE environment_id = %d AND revision = %d",
			(int) $environment_id, (int) $revision ) );
	}

	public static function history( $environment_id, $limit = 30 ) {
		global $wpdb;
		$rows = $wpdb->get_results( $wpdb->prepare(
			"SELECT host_config_id, revision, user_id, agent, note, status, created_at, applied_at
			 FROM {$wpdb->base_prefix}captaincore_host_configs WHERE environment_id = %d ORDER BY revision DESC LIMIT %d",
			(int) $environment_id, (int) $limit ) );
		foreach ( $rows as $row ) {
			$user          = $row->user_id ? get_userdata( (int) $row->user_id ) : null;
			$row->revision = (int) $row->revision;
			$row->by       = $row->agent ?: ( $user ? $user->display_name : 'system' );
		}
		return $rows;
	}

	/** The config currently in force (latest revision, or the default). */
	public static function current( $environment ) {
		$row = self::latest( $environment->environment_id );
		return $row ? json_decode( $row->config, true ) : self::default_for( $environment );
	}

	private static function hostname( $name ) {
		$name = strtolower( trim( (string) $name ) );
		$name = preg_replace( '#^https?://#', '', $name );
		$name = rtrim( preg_replace( '#/.*$#', '', $name ), '.' );
		if ( strlen( $name ) > 253 || ! preg_match( '/^(?=.{1,253}$)([a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,63}$/', $name ) ) {
			return '';
		}
		return $name;
	}

	/**
	 * Validate and normalize a config. $environment_id excludes the
	 * environment's own domains from the "already used" check.
	 */
	public static function sanitize( $input, $environment_id ) {
		$in  = is_array( $input ) ? $input : json_decode( wp_json_encode( $input ), true );
		$out = [];

		$domains = [];
		$seen    = [];
		$primary = 0;
		foreach ( (array) ( $in['domains'] ?? [] ) as $d ) {
			$name = self::hostname( $d['name'] ?? '' );
			if ( $name === '' ) {
				return new \WP_Error( 'invalid', 'A domain is not a valid hostname: ' . substr( sanitize_text_field( $d['name'] ?? '' ), 0, 80 ), [ 'status' => 400 ] );
			}
			$www = ! empty( $d['www'] ) && strpos( $name, 'www.' ) !== 0;
			foreach ( array_filter( [ $name, $www ? 'www.' . $name : '' ] ) as $h ) {
				if ( isset( $seen[ $h ] ) ) {
					return new \WP_Error( 'invalid', "{$h} is listed twice.", [ 'status' => 400 ] );
				}
				$seen[ $h ] = true;
			}
			$is_primary = ! empty( $d['primary'] );
			$primary   += $is_primary ? 1 : 0;
			$entry      = [ 'name' => $name, 'primary' => $is_primary, 'www' => $www ];
			if ( ! empty( $d['temporary'] ) ) {
				$entry['temporary'] = true;
			}
			$domains[]  = $entry;
		}
		if ( count( $domains ) === 0 ) {
			return new \WP_Error( 'invalid', 'Add at least one domain.', [ 'status' => 400 ] );
		}
		if ( count( $domains ) > 50 ) {
			return new \WP_Error( 'invalid', 'A site can have at most 50 domains.', [ 'status' => 400 ] );
		}
		if ( $primary !== 1 ) {
			return new \WP_Error( 'invalid', 'Exactly one domain must be primary.', [ 'status' => 400 ] );
		}
		$taken = self::domains_in_use( $environment_id );
		foreach ( array_keys( $seen ) as $h ) {
			if ( isset( $taken[ $h ] ) ) {
				return new \WP_Error( 'invalid', "{$h} is already used by {$taken[ $h ]}.", [ 'status' => 409 ] );
			}
		}
		$out['domains']             = $domains;
		$out['force_https']         = ! isset( $in['force_https'] ) || ! empty( $in['force_https'] );
		$out['redirect_to_primary'] = ! empty( $in['redirect_to_primary'] );

		$php     = (array) ( $in['php'] ?? [] );
		$version = (string) ( $php['version'] ?? '8.4' );
		if ( empty( self::PHP_IMAGES[ $version ] ) ) {
			return new \WP_Error( 'invalid', "PHP {$version} is not available on our servers yet.", [ 'status' => 400 ] );
		}
		$num = function ( $key, $value ) {
			[ $min, $max ] = self::LIMITS[ $key ];
			$v = (int) $value;
			if ( $v < $min || $v > $max ) {
				return new \WP_Error( 'invalid', str_replace( '_', ' ', $key ) . " must be between {$min} and {$max}.", [ 'status' => 400 ] );
			}
			return $v;
		};
		$out['php'] = [ 'version' => $version ];
		foreach ( [ 'workers', 'max_workers', 'memory_limit', 'upload_max_filesize', 'max_execution_time' ] as $k ) {
			$v = $num( $k, $php[ $k ] ?? 0 );
			if ( is_wp_error( $v ) ) {
				return $v;
			}
			$out['php'][ $k ] = $v;
		}
		if ( $out['php']['max_workers'] < $out['php']['workers'] ) {
			return new \WP_Error( 'invalid', 'Burst workers can not be lower than workers.', [ 'status' => 400 ] );
		}
		// PHP's shell commands (exec, shell_exec, system, passthru, proc_open,
		// popen) are off for web requests unless a site asks for them.
		$out['php']['shell'] = ! empty( $php['shell'] );
		$mem = $num( 'memory_mb', $in['memory_mb'] ?? 0 );
		if ( is_wp_error( $mem ) ) {
			return $mem;
		}
		$out['memory_mb'] = $mem;

		// Redirects: path (exact, or ending in * for a prefix) to a path or URL.
		$out['redirects'] = [];
		foreach ( (array) ( $in['redirects'] ?? [] ) as $r ) {
			$from = trim( (string) ( $r['from'] ?? '' ) );
			$to   = trim( (string) ( $r['to'] ?? '' ) );
			$code = (int) ( $r['code'] ?? 301 );
			if ( ! preg_match( '@^/[A-Za-z0-9._~%/+-]*\*?$@', $from ) ) {
				return new \WP_Error( 'invalid', "Redirect from \"{$from}\" must be a path like /old-page or /blog/*.", [ 'status' => 400 ] );
			}
			if ( ! preg_match( '@^(/[A-Za-z0-9._~%/+?=&#-]*|https?://[A-Za-z0-9.-]+(:\d+)?(/[A-Za-z0-9._~%/+?=&#-]*)?)$@', $to ) ) {
				return new \WP_Error( 'invalid', "Redirect to \"{$to}\" must be a path or an http(s) URL.", [ 'status' => 400 ] );
			}
			if ( ! in_array( $code, [ 301, 302, 307, 308 ], true ) ) {
				return new \WP_Error( 'invalid', 'Redirect code must be 301, 302, 307 or 308.', [ 'status' => 400 ] );
			}
			$out['redirects'][] = [ 'from' => $from, 'to' => $to, 'code' => $code ];
		}
		if ( count( $out['redirects'] ) > 500 ) {
			return new \WP_Error( 'invalid', 'At most 500 redirects.', [ 'status' => 400 ] );
		}

		// IP deny: addresses or CIDR ranges, answered with 403 at the edge.
		$out['ip_deny'] = [];
		foreach ( (array) ( $in['ip_deny'] ?? [] ) as $entry ) {
			$entry = trim( (string) $entry );
			if ( $entry === '' ) {
				continue;
			}
			[ $addr, $bits ] = array_pad( explode( '/', $entry, 2 ), 2, null );
			$v4 = filter_var( $addr, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 );
			$v6 = filter_var( $addr, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6 );
			if ( ( ! $v4 && ! $v6 ) || ( $bits !== null && ( ! ctype_digit( $bits ) || (int) $bits > ( $v4 ? 32 : 128 ) ) ) ) {
				return new \WP_Error( 'invalid', "\"{$entry}\" is not an IP address or CIDR range.", [ 'status' => 400 ] );
			}
			$out['ip_deny'][] = $entry;
		}
		$out['ip_deny'] = array_values( array_unique( $out['ip_deny'] ) );
		if ( count( $out['ip_deny'] ) > 500 ) {
			return new \WP_Error( 'invalid', 'At most 500 denied addresses.', [ 'status' => 400 ] );
		}

		// Password protection (HTTP basic auth at the edge). A new password
		// arrives as `password` and is stored only as a bcrypt hash.
		$pp       = (array) ( $in['password_protect'] ?? [] );
		$previous = self::latest( $environment_id );
		$prev_pp  = $previous ? ( json_decode( $previous->config, true )['password_protect'] ?? [] ) : [];
		$enabled  = ! empty( $pp['enabled'] );
		$user     = trim( (string) ( $pp['username'] ?? '' ) );
		$hash     = (string) ( $pp['password_hash'] ?? '' );
		if ( ! empty( $pp['password'] ) ) {
			if ( strlen( (string) $pp['password'] ) < 8 ) {
				return new \WP_Error( 'invalid', 'The password must be at least 8 characters.', [ 'status' => 400 ] );
			}
			$hash = password_hash( (string) $pp['password'], PASSWORD_BCRYPT );
		} elseif ( $hash !== ( $prev_pp['password_hash'] ?? '' ) ) {
			$hash = (string) ( $prev_pp['password_hash'] ?? '' ); // never accept a hash from the browser
		}
		if ( $enabled ) {
			if ( ! preg_match( '/^[A-Za-z0-9._-]{1,64}$/', $user ) ) {
				return new \WP_Error( 'invalid', 'The username may use letters, digits, dot, dash and underscore.', [ 'status' => 400 ] );
			}
			if ( $hash === '' ) {
				return new \WP_Error( 'invalid', 'Set a password for password protection.', [ 'status' => 400 ] );
			}
		}
		$out['password_protect'] = [ 'enabled' => $enabled, 'username' => $user, 'password_hash' => $hash ];
		$out['debug']            = ! empty( $in['debug'] );
		// Hidden from search engines unless turned off (new sites start on a
		// temporary domain): X-Robots-Tag at the edge + blog_public 0.
		$out['noindex']          = ! array_key_exists( 'noindex', $in ) || ! empty( $in['noindex'] );
		// Outgoing mail through the server's relay (Mailgun, mg.<hosting zone>),
		// or off: the relay refuses mail from this site's container.
		$out['mail']             = ( $in['mail'] ?? 'relay' ) === 'off' ? 'off' : 'relay';
		// Suspended: the edge answers every request with a holding page (503).
		$out['suspended']        = ! empty( $in['suspended'] );
		$cache_errors            = [];
		$out['page_cache']       = self::sanitize_page_cache( $in['page_cache'] ?? null, $cache_errors );
		if ( $cache_errors ) {
			return new \WP_Error( 'invalid', $cache_errors[0], [ 'status' => 400 ] );
		}

		// Apps: long-running programs beside the site (a chat or realtime
		// server), each a systemd service in the container that runs as the
		// site user, reached at a path of the site or a hostname of its own.
		$out['apps'] = [];
		$names = $ports = [];
		foreach ( (array) ( $in['apps'] ?? [] ) as $a ) {
			$name = strtolower( trim( (string) ( $a['name'] ?? '' ) ) );
			if ( ! preg_match( '/^[a-z][a-z0-9-]{0,19}$/', $name ) || isset( $names[ $name ] ) ) {
				return new \WP_Error( 'invalid', "App name \"{$name}\" must be unique, start with a letter and use a-z, 0-9 and dashes (20 at most).", [ 'status' => 400 ] );
			}
			$command = trim( (string) ( $a['command'] ?? '' ) );
			if ( $command === '' || strlen( $command ) > 500 || preg_match( '/[\x00-\x1f\x7f]/', $command ) ) {
				return new \WP_Error( 'invalid', "The {$name} app needs a start command on one line (500 characters at most).", [ 'status' => 400 ] );
			}
			$port = (int) ( $a['port'] ?? 0 );
			if ( $port < 3000 || $port > 3999 || isset( $ports[ $port ] ) ) {
				return new \WP_Error( 'invalid', "The {$name} app's port must be unique and between 3000 and 3999.", [ 'status' => 400 ] );
			}
			$route = ( $a['route'] ?? 'path' ) === 'hostname' ? 'hostname' : 'path';
			// Each app has a memory cap of its own inside the site's, so a leak
			// stops at the app and leaves room for PHP and MariaDB (512 MB).
			$mem_max = max( 64, (int) ( $out['memory_mb'] ?? 1024 ) - 512 );
			$mem     = isset( $a['memory_mb'] ) && $a['memory_mb'] !== '' ? (int) $a['memory_mb'] : min( 256, $mem_max );
			if ( $mem < 32 || $mem > $mem_max ) {
				return new \WP_Error( 'invalid', "The {$name} app's memory must be between 32 and {$mem_max} MB (the site's memory less 512 MB for PHP and MariaDB).", [ 'status' => 400 ] );
			}
			$app   = [ 'name' => $name, 'command' => $command, 'port' => $port, 'route' => $route, 'memory_mb' => $mem, 'enabled' => ! isset( $a['enabled'] ) || ! empty( $a['enabled'] ) ];
			if ( $route === 'path' ) {
				$path = '/' . trim( (string) ( $a['path'] ?? '' ), '/' );
				if ( $path === '/' || ! preg_match( '@^(/[A-Za-z0-9._~-]+)+$@', $path ) || preg_match( '@^/wp-@', $path ) ) {
					return new \WP_Error( 'invalid', "The {$name} app needs a path like /ws or /chat (not / or /wp-…).", [ 'status' => 400 ] );
				}
				$app['path']         = $path;
				$app['strip_prefix'] = ! empty( $a['strip_prefix'] );
			} else {
				$host = self::hostname( $a['hostname'] ?? '' );
				if ( $host === '' || isset( $seen[ $host ] ) ) {
					return new \WP_Error( 'invalid', "The {$name} app needs a hostname of its own, not one of the site's domains.", [ 'status' => 400 ] );
				}
				if ( isset( $taken[ $host ] ) ) {
					return new \WP_Error( 'invalid', "{$host} is already used by {$taken[ $host ]}.", [ 'status' => 409 ] );
				}
				$seen[ $host ]   = true;
				$app['hostname'] = $host;
			}
			$env = [];
			foreach ( (array) ( $a['env'] ?? [] ) as $k => $v ) {
				$k = (string) $k;
				$v = (string) $v;
				if ( ! preg_match( '/^[A-Z_][A-Z0-9_]{0,63}$/', $k ) || preg_match( '/[\x00-\x1f]/', $v ) || strlen( $v ) > 1000 ) {
					return new \WP_Error( 'invalid', "The {$name} app's variable {$k} needs an UPPER_CASE name and a one-line value.", [ 'status' => 400 ] );
				}
				$env[ $k ] = $v;
			}
			if ( count( $env ) > 30 ) {
				return new \WP_Error( 'invalid', "The {$name} app has more than 30 variables.", [ 'status' => 400 ] );
			}
			$app['env']      = (object) $env;
			$names[ $name ]  = true;
			$ports[ $port ]  = true;
			$out['apps'][]   = $app;
		}
		if ( count( $out['apps'] ) > 5 ) {
			return new \WP_Error( 'invalid', 'A site can run at most 5 apps.', [ 'status' => 400 ] );
		}
		return $out;
	}

	/** The config as the browser sees it: the password hash is replaced by a flag. */
	public static function for_browser( $config ) {
		$config['password_protect'] = $config['password_protect'] ?? [ 'enabled' => false, 'username' => '', 'password_hash' => '' ];
		$config['password_protect']['has_password'] = ! empty( $config['password_protect']['password_hash'] );
		unset( $config['password_protect']['password_hash'] );
		$config['redirects'] = $config['redirects'] ?? [];
		$config['ip_deny']   = $config['ip_deny'] ?? [];
		$config['debug']     = ! empty( $config['debug'] );
		$config['noindex']   = ! array_key_exists( 'noindex', $config ) || ! empty( $config['noindex'] );
		$config['mail']      = ( $config['mail'] ?? 'relay' ) === 'off' ? 'off' : 'relay';
		$config['suspended'] = ! empty( $config['suspended'] );
		if ( isset( $config['php'] ) && is_array( $config['php'] ) ) {
			$config['php']['shell'] = ! empty( $config['php']['shell'] );
		}
		$none                 = [];
		$config['page_cache'] = self::sanitize_page_cache( $config['page_cache'] ?? null, $none );
		$config['apps']      = $config['apps'] ?? [];
		return $config;
	}

	/** Hostnames claimed by other environments on our servers => site name. */
	public static function domains_in_use( $except_environment_id ) {
		global $wpdb;
		$rows = $wpdb->get_results( $wpdb->prepare(
			"SELECT h.environment_id, h.config, s.name FROM {$wpdb->base_prefix}captaincore_host_configs h
			 JOIN ( SELECT environment_id, MAX( revision ) AS rev FROM {$wpdb->base_prefix}captaincore_host_configs GROUP BY environment_id ) last
			   ON last.environment_id = h.environment_id AND last.rev = h.revision
			 JOIN {$wpdb->base_prefix}captaincore_environments e ON e.environment_id = h.environment_id
			 JOIN {$wpdb->base_prefix}captaincore_sites s ON s.site_id = e.site_id
			 WHERE h.environment_id != %d AND s.status = 'active'", (int) $except_environment_id ) );
		$taken = [];
		foreach ( $rows as $row ) {
			foreach ( self::hostnames( json_decode( $row->config, true ) ) as $h ) {
				$taken[ $h ] = $row->name;
			}
		}
		return $taken;
	}

	/** Every hostname a config serves (domains plus their www aliases). */
	public static function hostnames( $config ) {
		$out = [];
		foreach ( (array) ( $config['domains'] ?? [] ) as $d ) {
			$out[] = $d['name'];
			if ( ! empty( $d['www'] ) ) {
				$out[] = 'www.' . $d['name'];
			}
		}
		foreach ( (array) ( $config['apps'] ?? [] ) as $a ) {
			if ( ( $a['route'] ?? '' ) === 'hostname' && ! empty( $a['hostname'] ) ) {
				$out[] = $a['hostname'];
			}
		}
		return $out;
	}

	/** Save a new revision. Returns the row or WP_Error. */
	public static function save( $environment, $config, $user_id, $note = '', $agent = '' ) {
		$clean = self::sanitize( $config, $environment->environment_id );
		if ( is_wp_error( $clean ) ) {
			return $clean;
		}
		$last = self::latest( $environment->environment_id );
		if ( $last && json_decode( $last->config, true ) == json_decode( wp_json_encode( $clean ), true ) && $last->status === 'applied' ) {
			return new \WP_Error( 'unchanged', 'Nothing changed.', [ 'status' => 400 ] );
		}
		global $wpdb;
		if ( $last ) {
			$wpdb->query( $wpdb->prepare(
				"UPDATE {$wpdb->base_prefix}captaincore_host_configs SET status = 'superseded' WHERE environment_id = %d AND status = 'pending'",
				(int) $environment->environment_id ) );
		}
		$id = self::insert( [
			'environment_id' => (int) $environment->environment_id,
			'revision'       => $last ? (int) $last->revision + 1 : 1,
			'config'         => wp_json_encode( $clean ),
			'user_id'        => (int) $user_id,
			'agent'          => substr( preg_replace( '/[^a-z0-9-]/', '', strtolower( (string) $agent ) ), 0, 20 ),
			'note'           => substr( sanitize_text_field( (string) $note ), 0, 255 ),
			'status'         => 'pending',
			'created_at'     => current_time( 'mysql' ),
		] );
		return $id ? self::get( $id ) : new \WP_Error( 'db_error', 'Could not save the configuration.', [ 'status' => 500 ] );
	}

	/** Start `host apply` for one environment. Returns the job token or ''. */
	public static function apply( $environment ) {
		$site = Sites::get( (int) $environment->site_id );
		if ( ! $site ) {
			return '';
		}
		$target = $site->site . ( strtolower( $environment->environment ) === 'production' ? '' : '-' . strtolower( $environment->environment ) );
		$res    = Run::background_task( [ 'host', 'apply', $target ] );
		return is_wp_error( $res ) ? '' : ( $res['token'] ?? '' );
	}

	/** Called by the CLI after an apply (`host-config-applied`). */
	public static function record_apply( $environment_id, $revision, $status, $result ) {
		$row = self::revision( $environment_id, $revision );
		if ( ! $row ) {
			return false;
		}
		$status = in_array( $status, [ 'applied', 'failed' ], true ) ? $status : 'failed';
		self::update( [
			'status'     => $status,
			'result'     => wp_json_encode( $result ),
			'applied_at' => current_time( 'mysql' ),
		], [ 'host_config_id' => (int) $row->host_config_id ] );
		return true;
	}

	/** The zone temporary domains live in (Manager domain id), anchorhost.xyz by default. */
	public static function temporary_zone() {
		$id = (int) get_site_option( 'captaincore_hosting_temp_domain_id', 0 );
		if ( ! $id ) {
			$found = Domains::where( [ 'name' => 'anchorhost.xyz' ] );
			$id    = $found ? (int) $found[0]->domain_id : 0;
		}
		$domain = $id ? Domains::get( $id ) : null;
		return ( $domain && $domain->remote_id ) ? $domain : null;
	}

	/**
	 * Page cache: pages anonymous visitors request are saved as static files
	 * in the container and served without PHP (`host apply` installs it; no
	 * plugin in WordPress). `ttl` is minutes a saved page is kept; `exclude`
	 * lists paths that are never saved, with everything under them.
	 */
	const PAGE_CACHE_TTLS = [ 15, 60, 360, 1440, 10080 ];

	public static function sanitize_page_cache( $in, &$errors ) {
		// No setting at all (a site from before the page cache, or a caller that left it out) means off.
		$in  = is_array( $in ) ? $in : ( is_object( $in ) ? (array) $in : [ 'enabled' => false ] );
		$ttl = (int) ( $in['ttl'] ?? 60 );
		$out = [ 'enabled' => ! empty( $in['enabled'] ), 'ttl' => in_array( $ttl, self::PAGE_CACHE_TTLS, true ) ? $ttl : 60, 'exclude' => [] ];
		foreach ( (array) ( $in['exclude'] ?? [] ) as $path ) {
			$path = '/' . trim( strtolower( trim( (string) $path ) ), '/' );
			if ( $path === '/' ) {
				continue;
			}
			if ( ! preg_match( '@^(/[a-z0-9._~-]+)+$@', $path ) || strlen( $path ) > 200 ) {
				$errors[] = "Page cache: \"{$path}\" is not a path like /members or /shop/sale.";
				continue;
			}
			$out['exclude'][ $path ] = $path;
		}
		$out['exclude'] = array_slice( array_values( $out['exclude'] ), 0, 50 );
		return $out;
	}

	/**
	 * After the node parked an environment's container: drop its records in
	 * the hosting zone (temporary domains and app hostnames there), clear its
	 * server, keep the parked name, and retire it (production: the site goes
	 * inactive; staging: the environment row goes).
	 */
	public static function remove_environment( $environment, $parked ) {
		$config  = self::current( $environment );
		$zone    = self::temporary_zone();
		$dropped = [];
		if ( $zone ) {
			$labels = [];
			foreach ( self::hostnames( $config ) as $host ) {
				if ( substr( $host, -strlen( '.' . $zone->name ) ) === '.' . $zone->name ) {
					$labels[ strtolower( substr( $host, 0, -strlen( '.' . $zone->name ) ) ) ] = true;
				}
			}
			if ( $labels ) {
				$page = 1;
				do {
					$res = Remote\Constellix::get( "domains/{$zone->remote_id}/records", [ 'perPage' => 100, 'page' => $page ] );
					foreach ( (array) ( $res->data ?? [] ) as $r ) {
						if ( isset( $labels[ strtolower( $r->name ) ] ) && in_array( strtolower( $r->type ), [ 'cname', 'a' ], true ) ) {
							Remote\Constellix::delete( "domains/{$zone->remote_id}/records/{$r->id}" );
							$dropped[] = $r->name . '.' . $zone->name;
						}
					}
					$page++;
				} while ( ! empty( $res->data ) && count( $res->data ) === 100 && $page < 30 );
			}
		}
		$details = json_decode( (string) $environment->details ) ?: new \stdClass();
		$details->removed_container = sanitize_text_field( (string) $parked );
		$details->removed_at        = current_time( 'mysql' );
		unset( $details->container );
		Environments::update( [ 'server_id' => 0, 'details' => wp_json_encode( $details ) ], [ 'environment_id' => (int) $environment->environment_id ] );
		$site = Sites::get( (int) $environment->site_id );
		if ( strtolower( $environment->environment ) === 'production' ) {
			( new Site( (int) $environment->site_id ) )->mark_inactive();
		} else {
			Environments::delete( (int) $environment->environment_id );
		}
		ProcessLog::insert( 'Removed ' . $environment->environment . ' from its server (container kept 7 days as ' . sanitize_text_field( (string) $parked ) . ')', (int) $environment->site_id );
		return [ 'dns_removed' => $dropped, 'site' => $site ? $site->name : '' ];
	}

	/** The relay's Mailgun sending domain: mg.<hosting zone> (mg.anchorhost.xyz). */
	public static function mail_domain() {
		$zone = self::temporary_zone();
		return $zone ? 'mg.' . $zone->name : '';
	}

	/** DNS label for a server's own record: cc-vin1 → vin1.nodes */
	public static function server_label( $server ) {
		$label = preg_replace( '/^cc-/', '', strtolower( $server->name ) );
		$label = preg_replace( '/[^a-z0-9-]/', '-', $label );
		return trim( $label, '-' ) . '.nodes';
	}

	/**
	 * Give an environment a temporary domain, <slug>-<4 hex>.<zone>, as a
	 * CNAME to its server's record (created on first use), so moving the
	 * site to another server is one record change. Adds it to the hosting
	 * config (primary when $primary) and returns the hostname or WP_Error.
	 */
	public static function add_temporary_domain( $environment, $user_id, $primary = false ) {
		$zone   = self::temporary_zone();
		$server = Servers::get( (int) $environment->server_id );
		$site   = Sites::get( (int) $environment->site_id );
		if ( ! $zone || ! $server || ! $server->address || ! $site ) {
			return new \WP_Error( 'unavailable', 'No temporary domain zone or server address is set up.', [ 'status' => 400 ] );
		}
		$config = self::current( $environment );
		foreach ( $config['domains'] as $d ) {
			if ( ! empty( $d['temporary'] ) ) {
				return new \WP_Error( 'exists', "This site already has a temporary domain ({$d['name']}).", [ 'status' => 409 ] );
			}
		}
		$host = self::temporary_dns( $site, $server, $site->site );
		if ( is_wp_error( $host ) ) {
			return $host;
		}
		if ( $primary ) {
			foreach ( $config['domains'] as &$d ) {
				$d['primary'] = false;
			}
			unset( $d );
		}
		$config['domains'][] = [ 'name' => $host, 'primary' => (bool) $primary, 'www' => false, 'temporary' => true ];
		$row = self::save( $environment, $config, $user_id, "Added temporary domain {$host}" );
		return is_wp_error( $row ) ? $row : $host;
	}

	/**
	 * Create <prefix>-<4 hex>.<zone> as a CNAME to the server's record
	 * (made on first use). Returns the hostname or WP_Error.
	 */
	public static function temporary_dns( $site, $server, $prefix ) {
		$zone = self::temporary_zone();
		if ( ! $zone || ! $server || ! $server->address ) {
			return new \WP_Error( 'unavailable', 'No temporary domain zone or server address is set up.', [ 'status' => 400 ] );
		}
		$remote = $zone->remote_id;
		$names  = [];
		$page   = 1;
		do {
			$res = Remote\Constellix::get( "domains/{$remote}/records", [ 'perPage' => 100, 'page' => $page ] );
			foreach ( (array) ( $res->data ?? [] ) as $r ) {
				$names[ strtolower( $r->name ) ][] = strtolower( $r->type );
			}
			$page++;
		} while ( ! empty( $res->data ) && count( $res->data ) === 100 && $page < 50 );

		$node = self::server_label( $server );
		if ( ! in_array( 'a', $names[ $node ] ?? [], true ) ) {
			$made = Remote\Constellix::post( "domains/{$remote}/records", captaincore_format_dns_record_for_api( 'a', $node, [ [ 'value' => $server->address ] ], 300 ) );
			if ( ! empty( $made->errors ) ) {
				return new \WP_Error( 'dns', 'Could not create the server record: ' . wp_json_encode( $made->errors ), [ 'status' => 502 ] );
			}
		}
		$slug = substr( preg_replace( '/[^a-z0-9-]/', '', strtolower( $prefix ) ), 0, 40 ) ?: 'site';
		for ( $i = 0; $i < 10; $i++ ) {
			$label = $slug . '-' . bin2hex( random_bytes( 2 ) );
			if ( ! isset( $names[ $label ] ) ) {
				break;
			}
		}
		$made = Remote\Constellix::post( "domains/{$remote}/records", captaincore_format_dns_record_for_api( 'cname', $label, [ [ 'value' => "{$node}.{$zone->name}." ] ], 300 ) );
		if ( ! empty( $made->errors ) ) {
			return new \WP_Error( 'dns', 'Could not create the DNS record: ' . wp_json_encode( $made->errors ), [ 'status' => 502 ] );
		}
		return "{$label}.{$zone->name}";
	}

	/**
	 * A new site on one of our servers (Servers → Add site…): a CaptainCore
	 * site whose Production environment runs in container s<site_id> on the
	 * server, with the next free IP and SSH port, a temporary domain and
	 * revision 1 of its hosting config. The CLI (`host site create`) builds
	 * the container. $start is 'wordpress' (a fresh install) or 'empty'.
	 */
	public static function create_site( $server, $label, $start, $user_id, $account_id = 0 ) {
		$slug = substr( preg_replace( '/[^a-z0-9]/', '', strtolower( (string) $label ) ), 0, 30 );
		if ( strlen( $slug ) < 3 ) {
			return new \WP_Error( 'invalid', 'Give the site a name of at least 3 letters or digits.', [ 'status' => 400 ] );
		}
		$base = $slug;
		for ( $i = 2; Sites::where( [ 'site' => $slug ] ); $i++ ) {
			$slug = $base . $i;
		}
		$slot = Servers::allocate( $server->server_id );
		if ( is_wp_error( $slot ) ) {
			return $slot;
		}
		$host = self::temporary_dns( (object) [ 'site' => $slug ], $server, $slug );
		if ( is_wp_error( $host ) ) {
			return $host;
		}
		$user    = get_userdata( (int) $user_id );
		$created = ( new Site )->create( [
			'name' => $host, 'site' => $slug, 'account_id' => (int) $account_id, 'customer_id' => 0,
			'provider' => 'captaincore', 'key' => '', 'environment_vars' => [], 'remote_key' => '',
			'environments' => [ [ 'environment' => 'Production', 'address' => $server->address, 'username' => 'site', 'password' => '',
				'protocol' => 'sftp', 'port' => (string) $slot['port'], 'home_directory' => '/srv/site/public', 'database_username' => '', 'database_password' => '',
				'monitor_enabled' => 0, 'updates_enabled' => 0 ] ],
		] );
		if ( empty( $created['site_id'] ) ) {
			return new \WP_Error( 'site_create', 'Could not create the site: ' . implode( ' ', (array) ( $created['errors'] ?? [] ) ), [ 'status' => 400 ] );
		}
		$site_id = (int) $created['site_id'];
		$env     = Environments::where( [ 'site_id' => $site_id, 'environment' => 'Production' ] )[0];
		Environments::update( [
			'server_id' => (int) $server->server_id,
			'home_url'  => "https://{$host}",
			'details'   => wp_json_encode( [
				'container' => 's' . $site_id, 'container_ip' => $slot['ip'],
				'start'     => $start === 'empty' ? 'empty' : 'wordpress',
				'wp_admin'  => [ 'user' => $user ? $user->user_login : 'admin', 'email' => $user ? $user->user_email : get_option( 'admin_email' ) ],
			] ),
		], [ 'environment_id' => (int) $env->environment_id ] );
		$env    = Environments::get( (int) $env->environment_id );
		$config = self::default_for( $env );
		$config['domains'] = [ [ 'name' => $host, 'primary' => true, 'www' => false, 'temporary' => true ] ];
		$row = self::save( $env, $config, $user_id, 'Site created on ' . $server->name );
		if ( is_wp_error( $row ) ) {
			return $row;
		}
		return [ 'site_id' => $site_id, 'site' => $slug, 'domain' => $host, 'environment_id' => (int) $env->environment_id ];
	}

	/** What the CLI needs to build a new site's container (`host-site-prepare`). */
	public static function site_build_info( $site_id ) {
		$env = Environments::where( [ 'site_id' => (int) $site_id, 'environment' => 'Production' ] );
		$env = $env ? $env[0] : null;
		if ( ! $env || ! (int) $env->server_id ) {
			return new \WP_Error( 'not_own_server', 'Production is not on one of our servers.', [ 'status' => 400 ] );
		}
		$d      = json_decode( (string) $env->details ) ?: new \stdClass();
		$config = self::current( $env );
		$primary = '';
		foreach ( $config['domains'] as $dm ) {
			if ( ! empty( $dm['primary'] ) ) {
				$primary = $dm['name'];
			}
		}
		$site = Sites::get( (int) $site_id );
		return [
			'container' => $d->container ?? '', 'ip' => $d->container_ip ?? '', 'ssh_port' => (int) $env->port,
			'image'     => self::PHP_IMAGES[ $config['php']['version'] ] ?? '', 'memory_mib' => (int) $config['memory_mb'], 'disk_gib' => 10,
			'primary'   => $primary, 'start' => $d->start ?? 'wordpress', 'title' => $site ? $site->site : 'New site',
			'admin_user' => $d->wp_admin->user ?? 'admin', 'admin_email' => $d->wp_admin->email ?? '',
		];
	}

	/**
	 * The Staging environment for a site on our servers, created if missing:
	 * environment row on production's server (next free container IP and SSH
	 * port), a temporary domain, and revision 1 of its hosting config copied
	 * from production's (domains, redirects, password and debug reset).
	 * Returns what the CLI needs to build the container, or WP_Error.
	 */
	public static function prepare_staging( $site_id, $user_id = 0 ) {
		$site = Sites::get( (int) $site_id );
		$prod = Environments::where( [ 'site_id' => (int) $site_id, 'environment' => 'Production' ] );
		$prod = $prod ? $prod[0] : null;
		if ( ! $site || ! $prod || ! (int) $prod->server_id ) {
			return new \WP_Error( 'not_own_server', 'Production is not on one of our servers.', [ 'status' => 400 ] );
		}
		$pd     = json_decode( (string) $prod->details ) ?: new \stdClass();
		$server = Servers::get( (int) $prod->server_id );
		$stag   = Environments::where( [ 'site_id' => (int) $site_id, 'environment' => 'Staging' ] );
		$stag   = $stag ? $stag[0] : null;
		if ( $stag && ! (int) $stag->server_id ) {
			return new \WP_Error( 'exists', 'This site already has a staging environment somewhere else.', [ 'status' => 409 ] );
		}
		if ( ! $stag ) {
			$slot = Servers::allocate( $server->server_id );
			if ( is_wp_error( $slot ) ) {
				return $slot;
			}
			$host = self::temporary_dns( $site, $server, $site->site . '-staging' );
			if ( is_wp_error( $host ) ) {
				return $host;
			}
			$now = current_time( 'mysql' );
			$id  = Environments::insert( [
				'site_id'         => (int) $site_id,
				'environment'     => 'Staging',
				'address'         => $server->address,
				'username'        => $prod->username,
				'protocol'        => $prod->protocol,
				'port'            => (string) $slot['port'],
				'home_directory'  => $prod->home_directory,
				'home_url'        => "https://{$host}",
				'server_id'       => (int) $server->server_id,
				'monitor_enabled' => 0,
				'updates_enabled' => 0,
				'details'         => wp_json_encode( [ 'container' => substr( ( $pd->container ?? $site->site ) . '-staging', 0, 63 ), 'container_ip' => $slot['ip'] ] ),
				'created_at'      => $now,
				'updated_at'      => $now,
			] );
			$stag   = Environments::get( $id );
			$config = self::current( $prod );
			$config['domains']          = [ [ 'name' => $host, 'primary' => true, 'www' => false, 'temporary' => true ] ];
			$config['redirects']        = [];
			$config['password_protect'] = [ 'enabled' => false, 'username' => '', 'password_hash' => '' ];
			$config['debug']            = false;
			$config['noindex']          = true;
			$row = self::save( $stag, $config, $user_id, 'Staging created from production' );
			if ( is_wp_error( $row ) ) {
				return $row;
			}
		}
		$sd = json_decode( (string) $stag->details ) ?: new \stdClass();
		$primary = function ( $env ) {
			foreach ( self::current( $env )['domains'] as $d ) {
				if ( ! empty( $d['primary'] ) ) {
					return $d['name'];
				}
			}
			return '';
		};
		return [
			'environment_id'       => (int) $stag->environment_id,
			'container'            => $sd->container ?? '',
			'ip'                   => $sd->container_ip ?? '',
			'ssh_port'             => (int) $stag->port,
			'primary'              => $primary( $stag ),
			'production_container' => $pd->container ?? '',
			'production_primary'   => $primary( $prod ),
		];
	}

	/**
	 * Where each hostname points right now, compared with the server: the
	 * Domains card's "Pointed / Not pointed yet" and the records to add.
	 */
	public static function dns_status( $config, $server_address ) {
		$out = [];
		foreach ( self::hostnames( $config ) as $h ) {
			$cache = get_transient( 'cc_dns_' . md5( $h ) );
			if ( $cache === false ) {
				$a     = @dns_get_record( $h, DNS_A ) ?: [];
				$cache = array_values( array_unique( array_map( function ( $r ) { return $r['ip'] ?? ''; }, $a ) ) );
				set_transient( 'cc_dns_' . md5( $h ), $cache, 60 );
			}
			$out[] = [
				'name'    => $h,
				'a'       => $cache,
				'pointed' => $server_address !== '' && in_array( $server_address, $cache, true ),
			];
		}
		return $out;
	}
}
