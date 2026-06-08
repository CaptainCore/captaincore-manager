<?php

namespace CaptainCore;

/**
 * Manage status labels on a site.
 *
 * Labels are stored as a structured list under the site's `details.labels`
 * JSON. They are display-only badges (e.g. "Moved to Squarespace",
 * "DNS at Cloudflare", "Domain expiring") and do NOT change the site's
 * lifecycle `status` column. The `/monitor-check` skill proposes labels for
 * review and writes approved ones here with `--source=monitor-check`.
 */
class SiteLabelCLI {

	/**
	 * Default color + icon per label type. The frontend maps `color` to a
	 * badge style and `icon` to a Material Design Icon class.
	 */
	private static function type_defaults( $type ) {
		$map = [
			'moved'           => [ 'color' => 'orange', 'icon' => 'mdi-swap-horizontal' ],
			'dns-elsewhere'   => [ 'color' => 'blue',   'icon' => 'mdi-dns' ],
			'not-wordpress'   => [ 'color' => 'grey',   'icon' => 'mdi-cancel' ],
			'down'            => [ 'color' => 'red',    'icon' => 'mdi-alert' ],
			'domain-expiring' => [ 'color' => 'amber',  'icon' => 'mdi-clock-alert' ],
			'domain-expired'  => [ 'color' => 'red',    'icon' => 'mdi-calendar-remove' ],
			'note'            => [ 'color' => 'grey',   'icon' => 'mdi-tag' ],
		];
		return $map[ $type ] ?? [ 'color' => 'grey', 'icon' => 'mdi-tag' ];
	}

	/**
	 * Resolve a numeric site_id or a domain name to a site row.
	 *
	 * @return object|null
	 */
	private static function resolve_site( $identifier ) {
		if ( is_numeric( $identifier ) ) {
			return Sites::get( (int) $identifier );
		}
		// Try the friendly slug column first, then the display name (domain).
		foreach ( [ 'site', 'name' ] as $column ) {
			$matches = ( new Sites )->where( [ $column => $identifier ] );
			if ( count( $matches ) === 1 ) {
				return $matches[0];
			}
		}
		return null;
	}

	private static function decode_labels( $site ) {
		$details = empty( $site->details ) ? (object) [] : json_decode( $site->details );
		$labels  = ( isset( $details->labels ) && is_array( $details->labels ) ) ? $details->labels : [];
		return [ $details, $labels ];
	}

	private static function persist( $site, $details, $labels ) {
		$details->labels = array_values( $labels );
		return Sites::update( [ 'details' => json_encode( $details ) ], [ 'site_id' => $site->site_id ] );
	}

	/**
	 * List a site's labels.
	 *
	 * ## OPTIONS
	 *
	 * <site>
	 * : Site ID or domain name.
	 *
	 * [--format=<format>]
	 * : Output format. Accepts table, json, csv. Default table.
	 *
	 * ## EXAMPLES
	 *
	 *     wp captaincore site-label list thorsenconsulting.com
	 *     wp captaincore site-label list 2456 --format=json
	 *
	 * @subcommand list
	 * @when after_wp_load
	 */
	public function list_( $args, $assoc_args ) {
		$format = $assoc_args['format'] ?? 'table';
		$site   = self::resolve_site( $args[0] );
		if ( ! $site ) {
			\WP_CLI::error( "No single site found matching '{$args[0]}'." );
		}

		list( , $labels ) = self::decode_labels( $site );
		if ( empty( $labels ) ) {
			\WP_CLI::log( 'No labels.' );
			return;
		}

		$rows = [];
		foreach ( $labels as $label ) {
			$rows[] = [
				'ID'          => $label->id ?? '',
				'Type'        => $label->type ?? '',
				'Text'        => $label->text ?? '',
				'Source'      => $label->source ?? '',
				'Detected'    => $label->detected_at ?? '',
				'Evidence'    => $format === 'table' ? mb_substr( $label->evidence ?? '', 0, 60 ) : ( $label->evidence ?? '' ),
			];
		}
		\WP_CLI\Utils\format_items( $format, $rows, [ 'ID', 'Type', 'Text', 'Source', 'Detected', 'Evidence' ] );
	}

	/**
	 * Add (or replace) a label on a site.
	 *
	 * Idempotent for automated callers: when a label of the same `--type` and
	 * `--source` already exists it is replaced, so re-running a scan updates
	 * rather than duplicates.
	 *
	 * ## OPTIONS
	 *
	 * <site>
	 * : Site ID or domain name.
	 *
	 * --type=<type>
	 * : Label type. One of: moved, dns-elsewhere, not-wordpress, down,
	 *   domain-expiring, domain-expired, note.
	 *
	 * --text=<text>
	 * : Human-readable badge text (e.g. "Moved to Squarespace").
	 *
	 * [--evidence=<evidence>]
	 * : Supporting evidence string recorded with the label.
	 *
	 * [--source=<source>]
	 * : What set the label. Accepts manual, monitor-check. Default manual.
	 *
	 * [--color=<color>]
	 * : Override the default badge color for the type.
	 *
	 * [--icon=<icon>]
	 * : Override the default mdi icon for the type.
	 *
	 * [--detected-at=<datetime>]
	 * : ISO8601 timestamp the condition was observed. Default now.
	 *
	 * [--allow-duplicate]
	 * : Keep any existing same-type/same-source label instead of replacing it.
	 *
	 * ## EXAMPLES
	 *
	 *     wp captaincore site-label add thorsenconsulting.com --type=moved \
	 *       --text="Moved to Squarespace" --source=monitor-check \
	 *       --evidence="NS=domaincontrol.com; www CNAME ext-sq.squarespace.com; wp-json 404"
	 *
	 * @subcommand add
	 * @when after_wp_load
	 */
	public function add( $args, $assoc_args ) {
		$site = self::resolve_site( $args[0] );
		if ( ! $site ) {
			\WP_CLI::error( "No single site found matching '{$args[0]}'." );
		}

		$allowed_types = [ 'moved', 'dns-elsewhere', 'not-wordpress', 'down', 'domain-expiring', 'domain-expired', 'note' ];
		$type          = $assoc_args['type'] ?? '';
		if ( ! in_array( $type, $allowed_types, true ) ) {
			\WP_CLI::error( "Invalid --type. Allowed: " . implode( ', ', $allowed_types ) );
		}

		$text = trim( (string) ( $assoc_args['text'] ?? '' ) );
		if ( $text === '' ) {
			\WP_CLI::error( '--text is required.' );
		}

		$source   = $assoc_args['source'] ?? 'manual';
		$defaults = self::type_defaults( $type );

		$label = [
			'id'          => 'lbl_' . uniqid(),
			'type'        => $type,
			'text'        => $text,
			'color'       => $assoc_args['color'] ?? $defaults['color'],
			'icon'        => $assoc_args['icon'] ?? $defaults['icon'],
			'source'      => $source,
			'evidence'    => (string) ( $assoc_args['evidence'] ?? '' ),
			'detected_at' => $assoc_args['detected-at'] ?? gmdate( 'Y-m-d\TH:i:s\Z' ),
		];

		list( $details, $labels ) = self::decode_labels( $site );

		// Replace an existing same-type/same-source label unless told otherwise.
		if ( ! isset( $assoc_args['allow-duplicate'] ) ) {
			$labels = array_filter( $labels, function ( $existing ) use ( $type, $source ) {
				return ! ( ( $existing->type ?? '' ) === $type && ( $existing->source ?? '' ) === $source );
			} );
		}

		$labels[] = (object) $label;
		self::persist( $site, $details, $labels );

		ActivityLog::log(
			'added_label',
			'site',
			$site->site_id,
			$site->name,
			"Added '{$type}' label to {$site->name}: {$text}",
			[ 'label' => $label ],
			$site->customer_id ?? null
		);

		\WP_CLI::success( "Added label {$label['id']} ({$type}) to {$site->name}." );
	}

	/**
	 * Remove a label from a site by its ID.
	 *
	 * ## OPTIONS
	 *
	 * <site>
	 * : Site ID or domain name.
	 *
	 * <label_id>
	 * : Label ID (from `site-label list`), or "all" to clear every label.
	 *
	 * ## EXAMPLES
	 *
	 *     wp captaincore site-label remove thorsenconsulting.com lbl_66a1f2c9b1d3e
	 *     wp captaincore site-label remove thorsenconsulting.com all
	 *
	 * @subcommand remove
	 * @when after_wp_load
	 */
	public function remove( $args, $assoc_args ) {
		$site = self::resolve_site( $args[0] );
		if ( ! $site ) {
			\WP_CLI::error( "No single site found matching '{$args[0]}'." );
		}
		$label_id = $args[1] ?? '';
		if ( $label_id === '' ) {
			\WP_CLI::error( 'A <label_id> (or "all") is required.' );
		}

		list( $details, $labels ) = self::decode_labels( $site );
		$before = count( $labels );

		if ( $label_id === 'all' ) {
			$labels = [];
		} else {
			$labels = array_filter( $labels, function ( $existing ) use ( $label_id ) {
				return ( $existing->id ?? '' ) !== $label_id;
			} );
		}

		if ( count( $labels ) === $before && $label_id !== 'all' ) {
			\WP_CLI::error( "No label '{$label_id}' found on {$site->name}." );
		}

		self::persist( $site, $details, $labels );

		ActivityLog::log(
			'removed_label',
			'site',
			$site->site_id,
			$site->name,
			$label_id === 'all' ? "Cleared all labels on {$site->name}" : "Removed label {$label_id} from {$site->name}",
			[ 'label_id' => $label_id ],
			$site->customer_id ?? null
		);

		\WP_CLI::success( $label_id === 'all' ? "Cleared {$before} label(s) on {$site->name}." : "Removed label {$label_id} from {$site->name}." );
	}
}
