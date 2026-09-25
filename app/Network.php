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
	 * Is this site a WP Freighter tenant? True when its details carry a
	 * STACKED_SITE_ID environment var (every tenant, manual or auto-created)
	 * or the resolved network shape says so. Tenants are never billed; their
	 * host is.
	 *
	 * @param object|string $details Site details (decoded or JSON).
	 */
	public static function is_tenant( $details ) {
		if ( is_string( $details ) ) {
			$details = json_decode( $details );
		}
		if ( ! is_object( $details ) ) {
			return false;
		}
		if ( ( $details->network->type ?? '' ) === 'tenant' ) {
			return true;
		}
		foreach ( (array) ( $details->environment_vars ?? [] ) as $var ) {
			$var = (object) $var;
			if ( in_array( $var->key ?? '', [ 'STACKED_SITE_ID', 'STACKED_ID' ], true ) && ( $var->value ?? '' ) !== '' ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Give every tenant of a Freighter host a site record, and keep the
	 * tenants' connection in step with the host. Runs after each host sync,
	 * so a tenant added in WP Freighter is managed (backups, updates, stats)
	 * by the next day without anyone adding it by hand. On for every host
	 * unless its site details set `tenants_auto` to false.
	 *
	 * New tenant sites take the host's account, customer, provider and SSH
	 * key, the host's production connection, and STACKED_SITE_ID; a mapped
	 * tenant is named after its domain, an unmapped one
	 * `tenant-<id>.<host domain>`. Each is handed to the CLI's normal
	 * onboarding (site sync --update-extras: keys, helper, sync-data,
	 * Fathom tracker, defaults, capture).
	 *
	 * @return array{created: array, updated: array}
	 */
	public static function provision( $host_site_id, $dry_run = false ) {
		$out  = [ 'created' => [], 'updated' => [] ];
		$host = Sites::get( $host_site_id );
		if ( ! $host || $host->status !== 'active' ) {
			return $out;
		}
		$host_details = json_decode( $host->details );
		if ( ( $host_details->network->type ?? '' ) !== 'host' || ( isset( $host_details->tenants_auto ) && ! $host_details->tenants_auto ) ) {
			return $out;
		}
		$host_env = ( new Environments )->where( [ 'site_id' => $host->site_id, 'environment' => 'Production' ] );
		$host_env = $host_env[0] ?? null;
		if ( ! $host_env ) {
			return $out;
		}
		$freighter = json_decode( $host_env->details ?? '' )->freighter ?? null;
		if ( ! $freighter || ( $freighter->role ?? '' ) !== 'host' ) {
			return $out;
		}

		// Connection fields a tenant shares with its host. Copied, then kept
		// equal on every host sync, so a migrated host carries its tenants.
		$connection = [];
		foreach ( [ 'address', 'username', 'password', 'protocol', 'port', 'home_directory', 'database_username', 'database_password' ] as $field ) {
			$connection[ $field ] = $host_env->$field;
		}

		$linked = [];
		foreach ( (array) ( $host_details->network->tenant_sites ?? [] ) as $t ) {
			$linked[ (int) $t->tenant_id ] = (int) $t->site_id;
		}

		// Existing tenants: follow the host's connection.
		foreach ( $linked as $tenant_id => $site_id ) {
			foreach ( ( new Environments )->where( [ 'site_id' => $site_id, 'environment' => 'Production' ] ) as $env ) {
				$changes = [];
				foreach ( $connection as $field => $value ) {
					if ( (string) $env->$field !== (string) $value ) {
						$changes[ $field ] = $value;
					}
				}
				if ( ! $changes ) {
					continue;
				}
				$out['updated'][] = [ 'site_id' => $site_id, 'tenant_id' => $tenant_id, 'fields' => array_keys( $changes ) ];
				if ( ! $dry_run ) {
					( new Environments )->update( $changes, [ 'environment_id' => $env->environment_id ] );
					Run::CLI( [ 'site', 'sync', (string) $site_id ], true );
				}
			}
		}

		// New tenants: create and onboard.
		$host_domain = preg_replace( '#^https?://#', '', untrailingslashit( (string) ( $freighter->main_url ?: $host_env->home_url ) ) );
		foreach ( (array) ( $freighter->tenants ?? [] ) as $t ) {
			$tenant_id = (int) ( $t->id ?? 0 );
			if ( ! $tenant_id || isset( $linked[ $tenant_id ] ) ) {
				continue;
			}
			$mapped = ! empty( $t->url ) && ! empty( $t->domain );
			$name   = $mapped ? strtolower( trim( $t->domain ) ) : "tenant-{$tenant_id}.{$host_domain}";
			$slug   = self::unique_slug( preg_replace( '/[^a-z0-9]/', '', strtolower( $host->site ) ) . 't' . $tenant_id );
			$row    = [ 'tenant_id' => $tenant_id, 'name' => $name, 'site' => $slug, 'mapped' => $mapped ];
			if ( $dry_run ) {
				$out['created'][] = $row;
				continue;
			}
			$now     = current_time( 'mysql' );
			$site_id = Sites::insert( [
				'account_id'       => $host->account_id,
				'customer_id'      => $host->customer_id,
				'name'             => $name,
				'site'             => $slug,
				'provider'         => $host->provider,
				'provider_id'      => $host->provider_id,
				'provider_site_id' => null,
				'created_at'       => $now,
				'updated_at'       => $now,
				'details'          => wp_json_encode( [
					'key'              => $host_details->key ?? '',
					'environment_vars' => [ [ 'key' => 'STACKED_SITE_ID', 'value' => (string) $tenant_id ] ],
					'subsites'         => '',
					'storage'          => '',
					'visits'           => '',
					'mailgun'          => '',
					'core'             => '',
					'home_url'         => $mapped ? $t->url : '',
					'tenant_of'        => (int) $host->site_id,
					'backup_settings'  => [ 'mode' => 'direct', 'interval' => 'daily', 'active' => true ],
				] ),
				'screenshot'       => '0',
				'status'           => 'active',
			] );
			if ( ! $site_id ) {
				continue;
			}
			( new Environments )->insert( array_merge( $connection, [
				'site_id'         => $site_id,
				'environment'     => 'Production',
				'home_url'        => $mapped ? $t->url : '',
				'created_at'      => $now,
				'updated_at'      => $now,
				// An unmapped tenant has no URL of its own to watch.
				'monitor_enabled' => $mapped ? $host_env->monitor_enabled : '0',
				'updates_enabled' => $host_env->updates_enabled,
			] ) );
			ActivityLog::log( 'created', 'site', $site_id, $name, "Added WP Freighter tenant {$tenant_id} of {$host->name}", [], $host->customer_id ?: null );
			Run::CLI( [ 'site', 'sync', (string) $site_id, '--update-extras' ], true );
			$out['created'][] = $row + [ 'site_id' => $site_id ];
		}

		if ( $out['created'] && ! $dry_run ) {
			self::refresh( $host->site_id );
		}
		return $out;
	}

	private static function unique_slug( $base ) {
		$slug = $base;
		for ( $n = 2; Sites::where( [ 'site' => $slug ] ); $n++ ) {
			$slug = $base . $n;
		}
		return $slug;
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
