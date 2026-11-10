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
