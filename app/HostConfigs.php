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

	/** PHP versions and the Incus image each runs from. Only built images are selectable. */
	const PHP_IMAGES = [
		'8.3' => '',
		'8.4' => 'cc-wp-php84',
		'8.5' => '',
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
		$mem = $num( 'memory_mb', $in['memory_mb'] ?? 0 );
		if ( is_wp_error( $mem ) ) {
			return $mem;
		}
		$out['memory_mb'] = $mem;
		return $out;
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
		return $out;
	}

	/** Save a new revision. Returns the row or WP_Error. */
	public static function save( $environment, $config, $user_id, $note = '', $agent = '' ) {
		$clean = self::sanitize( $config, $environment->environment_id );
		if ( is_wp_error( $clean ) ) {
			return $clean;
		}
		$last = self::latest( $environment->environment_id );
		if ( $last && json_decode( $last->config, true ) == $clean && $last->status === 'applied' ) {
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
		$slug = substr( preg_replace( '/[^a-z0-9-]/', '', strtolower( $site->site ) ), 0, 40 ) ?: 'site';
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
		$host = "{$label}.{$zone->name}";
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
