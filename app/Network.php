<?php

namespace CaptainCore;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Network shape of a site: a multisite network, a WP Freighter host, or a
 * WP Freighter tenant. The result is cached on the site's `details.network`
 * so the Sites list can filter on it without touching environment rows.
 *
 * Freighter tenants share their host's install, so the host and every tenant
 * sit behind the same SSH endpoint (address + port). Links are resolved per
 * endpoint group. The CLI's `freighter` detail says who is the host; before a
 * site has synced with that collector, a group holding STACKED_SITE_ID
 * tenants and exactly one other site marks that site as the host (inferred).
 *
 * Shapes written to details.network (absent when the site is none of these):
 *   multisite: { type, mode, subsites }
 *   host:      { type, tenants, tracked, tenant_sites:[{tenant_id,site_id}], files, domain_mapping, main_url, inferred }
 *   tenant:    { type, tenant_id, host_site_id, main_url }
 */
class Network {

	/**
	 * Recompute the network shape for one site and every site sharing its
	 * Production SSH endpoint. Called after each sync-data ingest.
	 */
	public static function refresh( $site_id ) {
		global $wpdb;
		$envs = $wpdb->prefix . 'captaincore_environments';
		$row  = $wpdb->get_row( $wpdb->prepare(
			"SELECT address, port FROM {$envs} WHERE site_id = %d AND environment = 'Production' LIMIT 1",
			$site_id
		) );
		if ( ! $row ) {
			return 0;
		}
		if ( $row->address === '' || $row->address === null ) {
			return self::apply( self::rows( 'AND s.site_id = %d', [ (int) $site_id ] ) );
		}
		return self::apply( self::rows( 'AND e.address = %s AND e.port = %s', [ $row->address, $row->port ] ) );
	}

	/**
	 * Recompute every active site. Returns the number of sites whose cached
	 * network shape changed.
	 */
	public static function refresh_all() {
		return self::apply( self::rows() );
	}

	/**
	 * Production rows with only the JSON paths this needs, so the large
	 * environment details (hashes, scans) never leave MySQL.
	 */
	private static function rows( $where = '', $args = [] ) {
		global $wpdb;
		$envs  = $wpdb->prefix . 'captaincore_environments';
		$sites = $wpdb->prefix . 'captaincore_sites';
		$sql   = "SELECT s.site_id, s.details AS site_details, e.address, e.port, e.subsite_count,
				JSON_EXTRACT( e.details, '$.freighter' ) AS freighter,
				JSON_UNQUOTE( JSON_EXTRACT( e.details, '$.network_sites.mode' ) ) AS ms_mode,
				JSON_EXTRACT( e.details, '$.network_sites.count' ) AS ms_count
			FROM {$envs} e JOIN {$sites} s ON s.site_id = e.site_id
			WHERE e.environment = 'Production' AND s.status = 'active' {$where}";
		return $wpdb->get_results( $args ? $wpdb->prepare( $sql, $args ) : $sql );
	}

	/**
	 * Compute shapes for a set of rows and write the ones that changed.
	 */
	private static function apply( $rows ) {
		$shapes = self::compute( $rows );
		$changed = 0;
		foreach ( $rows as $r ) {
			$details = json_decode( $r->site_details );
			if ( ! is_object( $details ) ) {
				continue;
			}
			$new = $shapes[ $r->site_id ] ?? null;
			$old = $details->network ?? null;
			if ( wp_json_encode( $old ) === wp_json_encode( $new ) ) {
				continue;
			}
			if ( $new === null ) {
				unset( $details->network );
			} else {
				$details->network = $new;
			}
			Sites::update( [ 'details' => wp_json_encode( $details ) ], [ 'site_id' => $r->site_id ] );
			$changed++;
		}
		return $changed;
	}

	/**
	 * Pure: rows in, [ site_id => shape|null ] out.
	 */
	public static function compute( $rows ) {
		$groups = [];
		$info   = [];
		foreach ( $rows as $r ) {
			$site_details = json_decode( $r->site_details );
			$freighter    = ( $r->freighter === null || $r->freighter === 'null' ) ? null : json_decode( $r->freighter );
			$stacked_id   = '';
			foreach ( (array) ( $site_details->environment_vars ?? [] ) as $var ) {
				$var = (object) $var;
				if ( in_array( $var->key ?? '', [ 'STACKED_SITE_ID', 'STACKED_ID' ], true ) && ( $var->value ?? '' ) !== '' ) {
					$stacked_id = (int) $var->value;
				}
			}
			if ( ! $stacked_id && is_object( $freighter ) && ( $freighter->role ?? '' ) === 'tenant' ) {
				$stacked_id = (int) $freighter->tenant_id;
			}
			$info[ $r->site_id ] = (object) [
				'site_id'    => (int) $r->site_id,
				'freighter'  => is_object( $freighter ) ? $freighter : null,
				'stacked_id' => $stacked_id,
				'ms_mode'    => $r->ms_mode && $r->ms_mode !== 'null' ? $r->ms_mode : '',
				'subsites'   => max( (int) $r->ms_count, (int) $r->subsite_count ),
			];
			$key = ( $r->address === '' || $r->address === null ) ? 'site:' . $r->site_id : $r->address . ':' . $r->port;
			$groups[ $key ][] = $r->site_id;
		}

		$shapes = [];
		foreach ( $groups as $ids ) {
			$tenants = [];
			$others  = [];
			$host    = null;
			foreach ( $ids as $id ) {
				$i = $info[ $id ];
				if ( $i->stacked_id ) {
					$tenants[] = $i;
				} else {
					$others[] = $i;
					if ( $i->freighter && ( $i->freighter->role ?? '' ) === 'host' ) {
						$host = $i;
					}
				}
			}
			$inferred = false;
			if ( ! $host && $tenants && count( $others ) === 1 ) {
				$host     = $others[0];
				$inferred = true;
			}

			if ( $host ) {
				$f            = $host->freighter;
				$tenant_sites = [];
				foreach ( $tenants as $t ) {
					$tenant_sites[] = [ 'tenant_id' => $t->stacked_id, 'site_id' => $t->site_id ];
				}
				usort( $tenant_sites, function ( $a, $b ) { return $a['tenant_id'] <=> $b['tenant_id']; } );
				$shapes[ $host->site_id ] = [
					'type'           => 'host',
					'tenants'        => $f ? (int) ( $f->count ?? 0 ) : count( $tenants ),
					'tracked'        => count( $tenants ),
					'tenant_sites'   => $tenant_sites,
					'files'          => $f ? (string) ( $f->files ?? '' ) : '',
					'domain_mapping' => $f ? (bool) ( $f->domain_mapping ?? false ) : false,
					'main_url'       => $f ? (string) ( $f->main_url ?? '' ) : '',
					'inferred'       => $inferred,
				];
			}
			foreach ( $tenants as $t ) {
				$shapes[ $t->site_id ] = [
					'type'         => 'tenant',
					'tenant_id'    => $t->stacked_id,
					'host_site_id' => $host ? $host->site_id : null,
					'main_url'     => $t->freighter ? (string) ( $t->freighter->main_url ?? '' ) : '',
				];
			}
			foreach ( $others as $o ) {
				if ( isset( $shapes[ $o->site_id ] ) || $o->subsites < 2 ) {
					continue;
				}
				$shapes[ $o->site_id ] = [
					'type'     => 'multisite',
					'mode'     => $o->ms_mode,
					'subsites' => $o->subsites,
				];
			}
		}
		return $shapes;
	}
}
