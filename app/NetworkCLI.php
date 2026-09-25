<?php

namespace CaptainCore;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Rebuild the cached network shape (multisite, WP Freighter host or tenant)
 * on site details. sync-data refreshes it per site; this is the backfill.
 */
class NetworkCLI {

	/**
	 * Recompute network shapes and report the fleet's networks.
	 *
	 * ## OPTIONS
	 *
	 * [<site>]
	 * : Site ID to refresh along with every site sharing its SSH endpoint. Omit for all active sites.
	 *
	 * [--format=<format>]
	 * : Output format for the summary.
	 * ---
	 * default: table
	 * options:
	 *   - table
	 *   - json
	 *   - count
	 * ---
	 *
	 * ## EXAMPLES
	 *
	 *     wp captaincore network refresh
	 *     wp captaincore network refresh 1663
	 *
	 * @when after_wp_load
	 */
	public function refresh( $args, $assoc_args ) {
		$changed = empty( $args[0] ) ? Network::refresh_all() : Network::refresh( (int) $args[0] );
		\WP_CLI::log( "Updated {$changed} site(s)." );
		$this->report( $assoc_args );
	}

	/**
	 * Create site records for WP Freighter tenants that have none, and move
	 * existing tenants onto their host's current connection. sync-data does
	 * this after every host sync; this runs it by hand.
	 *
	 * ## OPTIONS
	 *
	 * [<host>]
	 * : Host site ID. Omit for every Freighter host.
	 *
	 * [--dry-run]
	 * : Report what would be created or updated without changing anything.
	 *
	 * ## EXAMPLES
	 *
	 *     wp captaincore network provision --dry-run
	 *     wp captaincore network provision 388
	 *
	 * @when after_wp_load
	 */
	public function provision( $args, $assoc_args ) {
		global $wpdb;
		$dry   = ! empty( $assoc_args['dry-run'] );
		$hosts = empty( $args[0] ) ? [] : [ (int) $args[0] ];
		if ( ! $hosts ) {
			foreach ( $wpdb->get_results( "SELECT site_id, details FROM {$wpdb->prefix}captaincore_sites WHERE status = 'active'" ) as $s ) {
				if ( ( json_decode( $s->details )->network->type ?? '' ) === 'host' ) {
					$hosts[] = (int) $s->site_id;
				}
			}
		}
		$rows = [];
		foreach ( $hosts as $host_id ) {
			$r = Network::provision( $host_id, $dry );
			foreach ( $r['created'] as $c ) {
				$rows[] = [ 'host' => $host_id, 'action' => $dry ? 'would create' : 'created', 'tenant' => $c['tenant_id'], 'site' => $c['site'], 'name' => $c['name'] ];
			}
			foreach ( $r['updated'] as $u ) {
				$rows[] = [ 'host' => $host_id, 'action' => ( $dry ? 'would update ' : 'updated ' ) . implode( ',', $u['fields'] ), 'tenant' => $u['tenant_id'], 'site' => $u['site_id'], 'name' => '' ];
			}
		}
		if ( ! $rows ) {
			\WP_CLI::success( 'Every tenant already has a site record on its host\'s connection.' );
			return;
		}
		\WP_CLI\Utils\format_items( 'table', $rows, [ 'host', 'action', 'tenant', 'site', 'name' ] );
	}

	/**
	 * List sites that are multisite networks, Freighter hosts or tenants.
	 *
	 * ## OPTIONS
	 *
	 * [--type=<type>]
	 * : Only this type: multisite, host or tenant.
	 *
	 * [--format=<format>]
	 * : Output format.
	 * ---
	 * default: table
	 * options:
	 *   - table
	 *   - json
	 *   - count
	 * ---
	 *
	 * @when after_wp_load
	 */
	public function list( $args, $assoc_args ) {
		$this->report( $assoc_args );
	}

	private function report( $assoc_args ) {
		global $wpdb;
		$sites = $wpdb->prefix . 'captaincore_sites';
		$type  = $assoc_args['type'] ?? '';
		$rows  = [];
		foreach ( $wpdb->get_results( "SELECT site_id, name, details FROM {$sites} WHERE status = 'active'" ) as $s ) {
			$n = json_decode( $s->details )->network ?? null;
			if ( ! $n || ( $type && $n->type !== $type ) ) {
				continue;
			}
			$detail = '';
			if ( $n->type === 'multisite' ) {
				$detail = trim( ( $n->mode ?: '' ) . ' ' . $n->subsites . ' subsites' );
			} elseif ( $n->type === 'host' ) {
				$detail = "{$n->tracked} of {$n->tenants} tenants tracked" . ( $n->inferred ? ' (inferred)' : '' );
			} elseif ( $n->type === 'tenant' ) {
				$detail = "tenant {$n->tenant_id} of " . ( $n->host_site_id ? "site {$n->host_site_id}" : 'unknown host' );
			}
			$rows[] = [ 'site_id' => $s->site_id, 'name' => $s->name, 'type' => $n->type, 'detail' => $detail ];
		}
		\WP_CLI\Utils\format_items( $assoc_args['format'] ?? 'table', $rows, [ 'site_id', 'name', 'type', 'detail' ] );
	}
}
