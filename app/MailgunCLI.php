<?php

namespace CaptainCore;

class MailgunCLI {

	/**
	 * Rotate the SMTP sending password for a Mailgun zone, optionally redeploying to a site.
	 *
	 * Regenerates the per-zone SMTP password at Mailgun (PUT v4/domains/{zone}) and stores
	 * it on the matching CaptainCore domain. With --deploy, runs the deploy-mailgun script
	 * against an explicit site so it picks up the new credentials.
	 *
	 * ## OPTIONS
	 *
	 * <zone>
	 * : The Mailgun sending zone (e.g. mg.jackfaulknerorchestra.com).
	 *
	 * [--deploy]
	 * : After rotating, deploy the new SMTP config to a site. Requires --site and --from-name.
	 *
	 * [--site=<slug>]
	 * : Site slug to deploy to (required with --deploy). No auto-resolution is performed.
	 *
	 * [--environment=<env>]
	 * : Environment to deploy to. Non-production values are appended as a slug suffix. Default production.
	 *
	 * [--from-name=<name>]
	 * : "Send From" display name passed to the deploy script (required with --deploy).
	 *
	 * [--show-password]
	 * : Print the newly generated SMTP password to stdout.
	 *
	 * ## EXAMPLES
	 *
	 *     wp captaincore mailgun rotate mg.jackfaulknerorchestra.com
	 *     wp captaincore mailgun rotate mg.jackfaulknerorchestra.com --deploy --site=jackfaulknerorchestra --from-name="Jack Faulkner Orchestra"
	 *     wp captaincore mailgun rotate mg.example.com --deploy --site=example --environment=staging --from-name="Example"
	 *
	 * @subcommand rotate
	 * @when after_wp_load
	 */
	public function rotate( $args, $assoc_args ) {
		$zone   = strtolower( trim( $args[0] ) );
		$deploy = isset( $assoc_args['deploy'] );

		$domain = $this->resolve_domain_by_zone( $zone );
		if ( ! $domain ) {
			\WP_CLI::error( "No CaptainCore domain found for zone '{$zone}'. Checked stored mailgun_zone and the root domain." );
			return;
		}
		$domain_id = $domain->domain_id;
		$details   = empty( $domain->details ) ? (object) [] : json_decode( $domain->details );

		// Confirm the zone actually exists in Mailgun before rotating.
		$mg = \CaptainCore\Remote\Mailgun::get( "v4/domains/{$zone}" );
		if ( ! empty( $mg->errors ) ) {
			\WP_CLI::error( "Mailgun API error: " . implode( '; ', $mg->errors ) );
			return;
		}
		if ( isset( $mg->message ) && stripos( $mg->message, 'not found' ) !== false ) {
			\WP_CLI::error( "Zone '{$zone}' not found in Mailgun. Run setup before rotating." );
			return;
		}

		// Backfill the link for domains that were never wired to a Mailgun zone locally.
		$changed = false;
		if ( empty( $details->mailgun_zone ) ) {
			$details->mailgun_zone = $zone;
			$changed = true;
		}
		if ( empty( $details->mailgun_id ) && isset( $mg->domain->id ) ) {
			$details->mailgun_id = $mg->domain->id;
			$changed = true;
		}
		if ( $changed ) {
			Domains::update( [ "details" => json_encode( $details ) ], [ "domain_id" => $domain_id ] );
			\WP_CLI::log( "Linked zone '{$zone}' to domain '{$domain->name}'." );
		}

		\WP_CLI::log( "Rotating SMTP password for {$zone} ..." );
		$result = \CaptainCore\Providers\Mailgun::rotate_smtp_password( $domain_id );
		if ( is_object( $result ) && ! empty( $result->error ) ) {
			\WP_CLI::error( $result->message );
			return;
		}
		\WP_CLI::success( "SMTP password rotated for {$zone}." );
		if ( isset( $assoc_args['show-password'] ) ) {
			\WP_CLI::log( "New SMTP password: {$result->password}" );
		}

		if ( ! $deploy ) {
			return;
		}

		// --deploy requires explicit targeting; never guess the site.
		if ( empty( $assoc_args['site'] ) ) {
			\WP_CLI::error( "--site=<slug> is required when using --deploy." );
			return;
		}
		if ( empty( $assoc_args['from-name'] ) ) {
			\WP_CLI::error( "--from-name=<name> is required when using --deploy." );
			return;
		}

		$environment = strtolower( $assoc_args['environment'] ?? 'production' );
		$site_slug   = $this->resolve_site_slug( $assoc_args['site'], $environment );

		\WP_CLI::log( "Deploying Mailgun config to {$site_slug} ..." );
		$output = \CaptainCore\Providers\Mailgun::deploy( $domain_id, $site_slug, $assoc_args['from-name'] );
		if ( is_object( $output ) && ! empty( $output->error ) ) {
			\WP_CLI::error( $output->message );
			return;
		}
		\WP_CLI::log( is_string( $output ) ? $output : json_encode( $output ) );
		\WP_CLI::success( "Deployed Mailgun config to {$site_slug}." );
	}

	/**
	 * List CaptainCore domains and their Mailgun link status.
	 *
	 * By default shows only domains linked to a Mailgun zone. Use --unlinked to find
	 * domains that have no Mailgun zone configured, or --all to show every domain.
	 *
	 * ## OPTIONS
	 *
	 * [--unlinked]
	 * : Show only domains that have no Mailgun zone configured.
	 *
	 * [--all]
	 * : Show all domains regardless of link status.
	 *
	 * [--check]
	 * : Query Mailgun live for each linked zone's verification state (slower).
	 *
	 * [--format=<format>]
	 * : Output format. Accepts table, json, csv, count. Default table.
	 *
	 * ## EXAMPLES
	 *
	 *     wp captaincore mailgun list
	 *     wp captaincore mailgun list --unlinked
	 *     wp captaincore mailgun list --check
	 *     wp captaincore mailgun list --all --format=json
	 *
	 * @subcommand list
	 * @when after_wp_load
	 */
	public function list_( $args, $assoc_args ) {
		global $wpdb;
		$format   = $assoc_args['format'] ?? 'table';
		$check    = isset( $assoc_args['check'] );
		$unlinked = isset( $assoc_args['unlinked'] );
		$all      = isset( $assoc_args['all'] );

		$table   = $wpdb->prefix . 'captaincore_domains';
		$domains = $wpdb->get_results( "SELECT domain_id, name, details FROM {$table} ORDER BY name ASC" );

		$rows = [];
		foreach ( $domains as $d ) {
			$details = empty( $d->details ) ? (object) [] : json_decode( $d->details );
			$zone    = $details->mailgun_zone ?? '';
			$has_pw  = ! empty( $details->mailgun_smtp_password );

			// Filter to the requested set.
			if ( ! $all ) {
				if ( $unlinked && $zone ) {
					continue;
				}
				if ( ! $unlinked && ! $zone ) {
					continue;
				}
			}

			$row = [
				'Domain'        => $d->name,
				'Mailgun Zone'  => $zone ?: '—',
				'SMTP Password' => $has_pw ? 'set' : '—',
			];

			if ( $check ) {
				if ( $zone ) {
					$mg = \CaptainCore\Remote\Mailgun::get( "v4/domains/{$zone}" );
					$row['Mailgun State'] = $mg->domain->state ?? ( $mg->message ?? 'unknown' );
				} else {
					$row['Mailgun State'] = '—';
				}
			}

			$rows[] = $row;
		}

		if ( empty( $rows ) ) {
			\WP_CLI::log( 'No matching domains found.' );
			return;
		}

		$columns = [ 'Domain', 'Mailgun Zone', 'SMTP Password' ];
		if ( $check ) {
			$columns[] = 'Mailgun State';
		}

		\WP_CLI\Utils\format_items( $format, $rows, $columns );
	}

	/**
	 * Resolve a CaptainCore domain row from a Mailgun zone.
	 *
	 * Prefers an exact match on the stored mailgun_zone in the domain's details JSON,
	 * then falls back to the root domain (zone with its left-most label stripped).
	 */
	private function resolve_domain_by_zone( $zone ) {
		global $wpdb;
		$zone  = strtolower( trim( $zone ) );
		$table = $wpdb->prefix . 'captaincore_domains';

		// 1. Exact match on stored mailgun_zone.
		$row = $wpdb->get_row( $wpdb->prepare(
			"SELECT * FROM {$table} WHERE details LIKE %s",
			'%"mailgun_zone":"' . $wpdb->esc_like( $zone ) . '"%'
		) );
		if ( $row ) {
			return $row;
		}

		// 2. Fall back to the root domain (strip the left-most label).
		$root = preg_replace( '/^[^.]+\./', '', $zone );
		return $wpdb->get_row( $wpdb->prepare(
			"SELECT * FROM {$table} WHERE name = %s",
			$root
		) );
	}

	/**
	 * Verify a site slug exists and apply the environment suffix used by deploy-mailgun.
	 */
	private function resolve_site_slug( $slug, $environment ) {
		$slug    = strtolower( trim( $slug ) );
		$matches = Sites::where( [ "site" => $slug ] );
		if ( empty( $matches ) ) {
			\WP_CLI::error( "Site '{$slug}' not found." );
		}
		if ( $environment && $environment !== 'production' ) {
			$slug = "{$slug}-{$environment}";
		}
		return $slug;
	}

}
