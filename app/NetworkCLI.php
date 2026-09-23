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
