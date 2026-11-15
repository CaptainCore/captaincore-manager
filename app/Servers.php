<?php

namespace CaptainCore;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Servers CaptainCore runs itself: the hosting nodes of the experimental
 * tier, as opposed to sites that live at a provider like Kinsta.
 */
class Servers extends DB {

	static $primary_key = 'server_id';

	const ROLES    = [ 'node', 'standby', 'spike' ];
	const STATUSES = [ 'provisioning', 'active', 'draining', 'maintenance', 'retired' ];

	/**
	 * Clean an add/edit payload into column values. Returns WP_Error when a
	 * required field is missing or a value is not allowed.
	 */
	public static function sanitize( $input, $existing = null ) {
		$input = (array) $input;
		$out   = [];

		$name = sanitize_text_field( $input['name'] ?? ( $existing->name ?? '' ) );
		if ( $name === '' ) {
			return new \WP_Error( 'invalid', 'Server name is required.', [ 'status' => 400 ] );
		}
		$out['name'] = substr( $name, 0, 255 );

		$role = strtolower( (string) ( $input['role'] ?? ( $existing->role ?? 'node' ) ) );
		if ( ! in_array( $role, self::ROLES, true ) ) {
			return new \WP_Error( 'invalid', 'Role must be node, standby or spike.', [ 'status' => 400 ] );
		}
		$out['role'] = $role;

		$status = strtolower( (string) ( $input['status'] ?? ( $existing->status ?? 'provisioning' ) ) );
		if ( ! in_array( $status, self::STATUSES, true ) ) {
			return new \WP_Error( 'invalid', 'Status is not valid.', [ 'status' => 400 ] );
		}
		$out['status'] = $status;

		$address = trim( (string) ( $input['address'] ?? ( $existing->address ?? '' ) ) );
		if ( $address !== '' && ! filter_var( $address, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 ) ) {
			return new \WP_Error( 'invalid', 'IPv4 address is not valid.', [ 'status' => 400 ] );
		}
		$out['address'] = $address;

		// IPv6 may be a single address or the server's /64 as OVH lists it.
		$v6 = trim( (string) ( $input['address_v6'] ?? ( $existing->address_v6 ?? '' ) ) );
		if ( $v6 !== '' && ! filter_var( preg_replace( '#/\d{1,3}$#', '', $v6 ), FILTER_VALIDATE_IP, FILTER_FLAG_IPV6 ) ) {
			return new \WP_Error( 'invalid', 'IPv6 address is not valid.', [ 'status' => 400 ] );
		}
		$out['address_v6'] = $v6;

		$port = (int) ( $input['ssh_port'] ?? ( $existing->ssh_port ?? 22 ) );
		if ( $port < 1 || $port > 65535 ) {
			return new \WP_Error( 'invalid', 'SSH port must be between 1 and 65535.', [ 'status' => 400 ] );
		}
		$out['ssh_port'] = $port;

		$user = (string) ( $input['ssh_user'] ?? ( $existing->ssh_user ?? 'root' ) );
		if ( ! preg_match( '/^[a-z_][a-z0-9_-]{0,31}$/', $user ) ) {
			return new \WP_Error( 'invalid', 'SSH user is not valid.', [ 'status' => 400 ] );
		}
		$out['ssh_user'] = $user;

		$provider_id = (int) ( $input['provider_id'] ?? ( $existing->provider_id ?? 0 ) );
		if ( $provider_id && ! Providers::get( $provider_id ) ) {
			return new \WP_Error( 'invalid', 'Provider connection not found.', [ 'status' => 400 ] );
		}
		$out['provider_id']        = $provider_id;
		$out['provider_server_id'] = substr( sanitize_text_field( $input['provider_server_id'] ?? ( $existing->provider_server_id ?? '' ) ), 0, 255 );
		$out['location']           = substr( sanitize_text_field( $input['location'] ?? ( $existing->location ?? '' ) ), 0, 255 );
		// Map position for the New site dialog: given, else looked up from the
		// location whenever it changes (or was never found).
		if ( isset( $input['latitude'], $input['longitude'] ) && is_numeric( $input['latitude'] ) && is_numeric( $input['longitude'] ) ) {
			$out['latitude']  = max( -90, min( 90, round( (float) $input['latitude'], 6 ) ) );
			$out['longitude'] = max( -180, min( 180, round( (float) $input['longitude'], 6 ) ) );
			$out['country']   = strtoupper( substr( preg_replace( '/[^A-Za-z]/', '', (string) ( $input['country'] ?? ( $existing->country ?? '' ) ) ), 0, 2 ) );
		} elseif ( ! $existing || $out['location'] !== (string) $existing->location || $existing->latitude === null ) {
			$geo = $out['location'] !== '' ? self::geocode( $out['location'] ) : null;
			$out['latitude']  = $geo ? $geo['lat'] : null;
			$out['longitude'] = $geo ? $geo['lng'] : null;
			$out['country']   = $geo ? $geo['country'] : '';
		}
		$out['max_sites']          = max( 0, (int) ( $input['max_sites'] ?? ( $existing->max_sites ?? 0 ) ) );
		$out['monthly_cost']       = max( 0, round( (float) ( $input['monthly_cost'] ?? ( $existing->monthly_cost ?? 0 ) ), 2 ) );
		$out['notes']              = sanitize_textarea_field( $input['notes'] ?? ( $existing->notes ?? '' ) );

		$specs = $input['specs'] ?? ( isset( $existing->specs ) ? json_decode( (string) $existing->specs, true ) : [] );
		$specs = is_array( $specs ) ? $specs : (array) $specs;
		$out['specs'] = wp_json_encode( [
			'cpu'    => substr( sanitize_text_field( $specs['cpu'] ?? '' ), 0, 120 ),
			'ram_gb' => max( 0, (int) ( $specs['ram_gb'] ?? 0 ) ),
			'disk'   => substr( sanitize_text_field( $specs['disk'] ?? '' ), 0, 120 ),
		] );

		return $out;
	}

	/** A row shaped for the UI: JSON decoded, provider named. */
	public static function present( $row, $providers = null ) {
		if ( ! $row ) {
			return null;
		}
		$row->server_id    = (int) $row->server_id;
		$row->provider_id  = (int) $row->provider_id;
		$row->ssh_port     = (int) $row->ssh_port;
		$row->max_sites    = (int) $row->max_sites;
		$row->monthly_cost = (float) $row->monthly_cost;
		$row->specs        = json_decode( (string) $row->specs ) ?: (object) [];
		$row->details      = json_decode( (string) $row->details ) ?: (object) [];
		$provider          = $row->provider_id ? ( $providers[ $row->provider_id ] ?? Providers::get( $row->provider_id ) ) : null;
		$row->provider     = $provider ? [ 'provider_id' => (int) $provider->provider_id, 'name' => $provider->name, 'provider' => $provider->provider ] : null;
		$row->sites        = self::site_count( $row->server_id );
		return $row;
	}

	/** Active sites with an environment on this server. */
	public static function site_count( $server_id ) {
		global $wpdb;
		return (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT( DISTINCT e.site_id ) FROM {$wpdb->base_prefix}captaincore_environments e
			 JOIN {$wpdb->base_prefix}captaincore_sites s ON s.site_id = e.site_id
			 WHERE e.server_id = %d AND s.status = 'active'", (int) $server_id ) );
	}

	/** The sites on this server, for the card and the CLI. */
	public static function sites_on( $server_id ) {
		global $wpdb;
		return $wpdb->get_results( $wpdb->prepare(
			"SELECT s.site_id, s.site, s.name, e.environment_id, e.environment, e.address, e.port, e.username, e.home_url
			 FROM {$wpdb->base_prefix}captaincore_environments e
			 JOIN {$wpdb->base_prefix}captaincore_sites s ON s.site_id = e.site_id
			 WHERE e.server_id = %d AND s.status = 'active' ORDER BY s.name", (int) $server_id ) );
	}

	/**
	 * Next free container IP (10.77.0.10–250) and SSH port (2201–2999) on a
	 * server, from what its environments already use.
	 */
	public static function allocate( $server_id ) {
		global $wpdb;
		$rows  = $wpdb->get_results( $wpdb->prepare(
			"SELECT port, details FROM {$wpdb->base_prefix}captaincore_environments WHERE server_id = %d", (int) $server_id ) );
		$ips   = [];
		$ports = [];
		foreach ( $rows as $row ) {
			$d = json_decode( (string) $row->details );
			if ( ! empty( $d->container_ip ) ) {
				$ips[ $d->container_ip ] = true;
			}
			$ports[ (int) $row->port ] = true;
		}
		$ip = '';
		for ( $i = 10; $i <= 250; $i++ ) {
			if ( empty( $ips[ "10.77.0.{$i}" ] ) ) {
				$ip = "10.77.0.{$i}";
				break;
			}
		}
		$port = 0;
		for ( $p = 2201; $p <= 2999; $p++ ) {
			if ( empty( $ports[ $p ] ) ) {
				$port = $p;
				break;
			}
		}
		return ( $ip && $port ) ? [ 'ip' => $ip, 'port' => $port ] : new \WP_Error( 'full', 'No free container address or SSH port on this server.', [ 'status' => 409 ] );
	}

	/**
	 * A place name → [ lat, lng, country ] through Photon (komoot's keyless
	 * OpenStreetMap geocoder, the one the New site dialog's city search uses).
	 * Null when nothing matched or the lookup failed.
	 */
	public static function geocode( $place ) {
		$res = wp_remote_get( 'https://photon.komoot.io/api/?limit=1&lang=en&q=' . rawurlencode( (string) $place ), [ 'timeout' => 8, 'user-agent' => 'CaptainCore' ] );
		if ( is_wp_error( $res ) || (int) wp_remote_retrieve_response_code( $res ) !== 200 ) {
			return null;
		}
		$f = json_decode( wp_remote_retrieve_body( $res ) )->features[0] ?? null;
		if ( ! $f || ! isset( $f->geometry->coordinates[1] ) ) {
			return null;
		}
		return [
			'lat'     => round( (float) $f->geometry->coordinates[1], 6 ),
			'lng'     => round( (float) $f->geometry->coordinates[0], 6 ),
			'country' => strtoupper( substr( (string) ( $f->properties->countrycode ?? '' ), 0, 2 ) ),
		];
	}

	/** Fill in a server's map position from its location once (servers added before DB v82). */
	private static function ensure_geo( $row ) {
		if ( $row->latitude !== null || trim( (string) $row->location ) === '' ) {
			return $row;
		}
		$geo = self::geocode( $row->location );
		if ( $geo ) {
			self::update( [ 'latitude' => $geo['lat'], 'longitude' => $geo['lng'], 'country' => $geo['country'] ], [ 'server_id' => (int) $row->server_id ] );
			$row->latitude  = $geo['lat'];
			$row->longitude = $geo['lng'];
			$row->country   = $geo['country'];
		}
		return $row;
	}

	/** Roles that take new sites (a standby waits for failover). */
	const PLACEABLE_ROLES = [ 'node', 'spike' ];

	/** Disk use (pool capacity, %) at which a server stops taking new sites. */
	const DISK_FULL = 85;

	/**
	 * Where a new site can go: our servers grouped by location, each server
	 * with whether it can take a site and a score for how much room it has
	 * (the smaller of free site slots and free disk, 0 to 1). The best server
	 * per location is the one the New site dialog uses unless the operator
	 * picks another. Locations without a map position are left out.
	 */
	public static function placement() {
		$now  = current_time( 'timestamp' );
		$locs = [];
		foreach ( ( new self )->all( 'name', 'ASC' ) as $row ) {
			$row = self::ensure_geo( $row );
			if ( $row->latitude === null || $row->status === 'retired' ) {
				continue;
			}
			$srv  = self::present( $row );
			$disk = isset( $srv->details->pool_cap ) ? (float) $srv->details->pool_cap : 0.0;
			$free = $srv->max_sites > 0 ? max( 0, $srv->max_sites - $srv->sites ) : null;
			$seen = ! empty( $srv->details->last_seen ) ? strtotime( $srv->details->last_seen ) : 0;
			$why  = '';
			if ( $srv->status !== 'active' ) {
				$why = 'Marked ' . $srv->status;
			} elseif ( ! in_array( $srv->role, self::PLACEABLE_ROLES, true ) ) {
				$why = 'Standby server';
			} elseif ( $srv->address === '' ) {
				$why = 'No IP address';
			} elseif ( ! $seen || $now - $seen > DAY_IN_SECONDS ) {
				$why = $seen ? 'Not heard from since ' . wp_date( 'M j', $seen ) : 'Never checked in';
			} elseif ( $disk >= self::DISK_FULL ) {
				$why = 'Disk ' . round( $disk ) . '% full';
			} elseif ( $free === 0 ) {
				$why = 'All ' . $srv->max_sites . ' site slots used';
			}
			$score = $why ? 0 : min( $free === null ? 1 : $free / $srv->max_sites, 1 - $disk / 100 );
			$key   = sanitize_title( $srv->location ) ?: 'server-' . $srv->server_id;
			if ( ! isset( $locs[ $key ] ) ) {
				$city = trim( explode( ',', $srv->location )[0] );
				$locs[ $key ] = [ 'key' => $key, 'location' => $srv->location, 'city' => $city, 'country' => (string) $row->country,
					'lat' => (float) $row->latitude, 'lng' => (float) $row->longitude, 'servers' => [], 'best' => null ];
			}
			$locs[ $key ]['servers'][] = [
				'server_id' => $srv->server_id, 'name' => $srv->name, 'role' => $srv->role, 'sites' => $srv->sites, 'max_sites' => $srv->max_sites,
				'free' => $free, 'disk' => $disk, 'ok' => $why === '', 'why' => $why, 'score' => round( $score, 3 ),
			];
		}
		foreach ( $locs as &$loc ) {
			usort( $loc['servers'], function ( $a, $b ) { return [ ! $a['ok'], -$a['score'], $a['name'] ] <=> [ ! $b['ok'], -$b['score'], $b['name'] ]; } );
			$loc['best'] = $loc['servers'][0]['ok'] ? $loc['servers'][0]['server_id'] : null;
		}
		return array_values( $locs );
	}

	/** One line on why a server was picked: "most room: 75 of 76 slots free, disk 3% used". */
	public static function room_line( $s ) {
		$slots = $s['free'] === null ? 'no site limit' : $s['free'] . ' of ' . $s['max_sites'] . ' site slots free';
		return $slots . ', disk ' . round( $s['disk'] ) . '% used';
	}

	/**
	 * The server a new site goes on: the named one if it can take a site, else
	 * the one with the most room at the location. WP_Error when nothing fits.
	 */
	public static function pick( $location_key, $server_id = 0 ) {
		foreach ( self::placement() as $loc ) {
			foreach ( $loc['servers'] as $s ) {
				if ( $server_id && $s['server_id'] === (int) $server_id ) {
					return $s['ok'] ? self::get( $s['server_id'] ) : new \WP_Error( 'unavailable', $s['name'] . ' cannot take a new site: ' . $s['why'] . '.', [ 'status' => 409 ] );
				}
			}
			if ( ! $server_id && $loc['key'] === (string) $location_key ) {
				return $loc['best'] ? self::get( $loc['best'] ) : new \WP_Error( 'full', 'No server in ' . $loc['location'] . ' can take a new site right now.', [ 'status' => 409 ] );
			}
		}
		return new \WP_Error( 'not_found', 'Pick one of our server locations.', [ 'status' => 404 ] );
	}

	public static function list_all() {
		$providers = [];
		foreach ( ( new Providers )->all() as $p ) {
			$providers[ $p->provider_id ] = $p;
		}
		$rows = ( new self )->all( 'name', 'ASC' );
		return array_map( function ( $row ) use ( $providers ) {
			return self::present( $row, $providers );
		}, $rows );
	}
}
