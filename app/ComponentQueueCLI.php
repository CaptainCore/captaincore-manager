<?php

namespace CaptainCore;

class ComponentQueueCLI {

	/**
	 * Generate a prioritized queue of un-audited component hashes across the fleet.
	 *
	 * Unlike scan-queue (site-centric), this command returns unique component
	 * builds that need auditing, deduplicated by content hash. For each hash,
	 * it picks one source site to download from.
	 *
	 * ## OPTIONS
	 *
	 * [--limit=<number>]
	 * : Number of components to return. Default 20.
	 *
	 * [--format=<format>]
	 * : Output format. Accepts table, json. Default table.
	 *
	 * [--all]
	 * : Show all un-audited hashes, not just the top N.
	 *
	 * [--model=<id>]
	 * : Canonical auditor ID (e.g. claude-opus-4-7). When set, hashes audited only by
	 * other models are still returned — supports layered multi-model audits.
	 *
	 * ## EXAMPLES
	 *
	 *     wp captaincore component-queue
	 *     wp captaincore component-queue --limit=50
	 *     wp captaincore component-queue --format=json
	 *     wp captaincore component-queue --model=claude-opus-4-7
	 *
	 * @when after_wp_load
	 */
	public function __invoke( $args = [], $assoc_args = [] ) {
		$limit    = isset( $assoc_args['limit'] ) ? (int) $assoc_args['limit'] : 20;
		$format   = $assoc_args['format'] ?? 'table';
		$show_all = isset( $assoc_args['all'] );
		$model    = isset( $assoc_args['model'] ) ? (string) $assoc_args['model'] : '';

		$quiet = $format !== 'table';
		$log   = function( $msg ) use ( $quiet ) {
			if ( ! $quiet ) { \WP_CLI::log( $msg ); }
		};

		$log( 'Gathering fleet component inventory...' );
		$sites_data = self::gather_sites();
		$log( sprintf( 'Found %d active production sites.', count( $sites_data ) ) );

		// Build a map of unique hashes → component info + source sites
		$hash_map    = []; // hash → {slug, version, type, sites, source_ssh}
		$no_hash     = []; // slug|version|type → {slug, version, type, sites, source_ssh} (legacy, no hash)

		foreach ( $sites_data as $site ) {
			$ssh = '';
			if ( ! empty( $site->address ) && ! empty( $site->username ) ) {
				$ssh = "{$site->username}@{$site->address}";
				if ( ! empty( $site->port ) && $site->port !== '22' ) {
					$ssh .= " -p {$site->port}";
				}
			}

			$components = self::extract_components( $site );

			foreach ( $components as $comp ) {
				$hash = $comp['hash'] ?? '';

				if ( $hash ) {
					if ( ! isset( $hash_map[ $hash ] ) ) {
						$hash_map[ $hash ] = [
							'hash'       => $hash,
							'slug'       => $comp['slug'],
							'version'    => $comp['version'],
							'type'       => $comp['type'],
							'title'      => $comp['title'] ?? $comp['slug'],
							'sites'      => 0,
							'source_ssh' => $ssh,
						];
					}
					$hash_map[ $hash ]['sites']++;
				} else {
					$key = "{$comp['type']}|{$comp['slug']}|{$comp['version']}";
					if ( ! isset( $no_hash[ $key ] ) ) {
						$no_hash[ $key ] = [
							'hash'       => '',
							'slug'       => $comp['slug'],
							'version'    => $comp['version'],
							'type'       => $comp['type'],
							'title'      => $comp['title'] ?? $comp['slug'],
							'sites'      => 0,
							'source_ssh' => $ssh,
						];
					}
					$no_hash[ $key ]['sites']++;
				}
			}
		}

		$all_components = array_merge( array_values( $hash_map ), array_values( $no_hash ) );
		$log( sprintf( 'Found %d unique component builds (%d with hashes, %d without).', count( $all_components ), count( $hash_map ), count( $no_hash ) ) );

		// Check which hashes/components have been audited
		$log( 'Checking audit coverage...' );
		$unaudited = self::filter_unaudited( $all_components, $model );

		if ( empty( $unaudited ) ) {
			\WP_CLI::success( 'All component builds are audited! 100% coverage.' );
			return;
		}

		// Sort by site count descending (most fleet exposure first)
		usort( $unaudited, function ( $a, $b ) {
			return $b['sites'] - $a['sites'];
		} );

		if ( ! $show_all ) {
			$unaudited = array_slice( $unaudited, 0, $limit );
		}

		$log( '' );
		$log( sprintf( 'Un-audited components: %d (showing %s)', count( $unaudited ), $show_all ? 'all' : "top $limit" ) );

		if ( $format === 'json' ) {
			echo wp_json_encode( $unaudited, JSON_PRETTY_PRINT ) . "\n";
			return;
		}

		// Table output — truncate hash for display
		$table_output = array_map( function( $item ) {
			return [
				'hash'    => $item['hash'] ? substr( $item['hash'], 0, 12 ) . '...' : 'none',
				'slug'    => $item['slug'],
				'version' => $item['version'] ?: '-',
				'type'    => $item['type'],
				'sites'   => $item['sites'],
				'source'  => $item['source_ssh'] ?: 'N/A',
			];
		}, $unaudited );

		\WP_CLI\Utils\format_items( $format, $table_output, [ 'hash', 'slug', 'version', 'type', 'sites', 'source' ] );
	}

	/**
	 * Extract active plugins/themes from a site row, including hashes.
	 */
	public static function extract_components( $site ) {
		$components = [];

		if ( ! empty( $site->plugins ) ) {
			$plugins = json_decode( $site->plugins );
			if ( is_array( $plugins ) ) {
				foreach ( $plugins as $p ) {
					if ( $p->status === 'active' || $p->status === 'must-use' ) {
						$components[] = [
							'slug'    => $p->name,
							'version' => $p->version,
							'type'    => $p->status === 'must-use' ? 'mu-plugin' : 'plugin',
							'hash'    => $p->hash ?? '',
							'title'   => html_entity_decode( $p->title ?? $p->name ),
						];
					}
				}
			}
		}

		if ( ! empty( $site->themes ) ) {
			$themes = json_decode( $site->themes );
			if ( is_array( $themes ) ) {
				foreach ( $themes as $t ) {
					if ( $t->status === 'active' ) {
						$components[] = [
							'slug'    => $t->name,
							'version' => $t->version,
							'type'    => 'theme',
							'hash'    => $t->hash ?? '',
							'title'   => html_entity_decode( $t->title ?? $t->name ),
						];
					}
				}
			}
		}

		// Loose files and mu-plugin metadata from environment details
		if ( ! empty( $site->details ) ) {
			$details = json_decode( $site->details );

			// Note: _mu_plugins directory hash is intentionally excluded from the queue.
			// MU-plugins are tracked via the manifest-based approach (individual slugs),
			// not whole-directory hashes. The hash is still stored for drift detection.

			// Core extra/modified file hashes
			if ( ! empty( $details->core_file_hashes ) ) {
				foreach ( $details->core_file_hashes as $path => $hash ) {
					$components[] = [
						'slug'    => $path,
						'version' => '',
						'type'    => 'file',
						'hash'    => $hash,
						'title'   => "core: $path",
					];
				}
			}

			// Loose wp-content PHP file hashes
			if ( ! empty( $details->loose_file_hashes ) ) {
				foreach ( $details->loose_file_hashes as $path => $hash ) {
					$components[] = [
						'slug'    => $path,
						'version' => '',
						'type'    => 'file',
						'hash'    => $hash,
						'title'   => "wp-content: $path",
					];
				}
			}
		}

		return $components;
	}

	/**
	 * Annotate each component with audit-state flags WITHOUT dropping anything.
	 * Used by slug-latest mode so it can see the full fleet (including audited
	 * versions) when picking each slug's actual latest version — then decide
	 * surfaceability per-slug instead of per-hash.
	 *
	 * Annotated keys:
	 *   _audited_by_anyone  — bool, hash exists in any-model manifest
	 *   _audited_by_me      — bool, hash exists in $model's manifest (false when $model is '')
	 *   _audit_tier         — 0 if not audited by anyone OR audited only by me;
	 *                         1 if audited by someone else but not by $model
	 *                         (the existing tier semantics callers already use)
	 *
	 * Fail-open: if the registry isn't configured or the fetch fails, every
	 * component is annotated as tier-0 / un-audited so the queue still works.
	 */
	public static function annotate_tiers( array $components, string $model = '' ): array {
		if ( ! RegistryClient::ready() ) {
			foreach ( $components as &$c ) {
				$c['_audit_tier']         = 0;
				$c['_audited_by_anyone']  = false;
				$c['_audited_by_me']      = false;
			}
			return $components;
		}

		$type_to_endpoint = [
			'plugin'    => 'plugins',
			'theme'     => 'themes',
			'mu-plugin' => 'mu-plugins',
			'file'      => 'files',
		];

		$any  = [];
		$mine = [];
		$needed_types = [];
		foreach ( $components as $c ) {
			$t = $c['type'] ?? '';
			if ( isset( $type_to_endpoint[ $t ] ) ) {
				$needed_types[ $t ] = true;
			}
		}
		foreach ( array_keys( $needed_types ) as $t ) {
			$endpoint = $type_to_endpoint[ $t ];
			$any[ $t ]  = RegistryClient::manifest( $endpoint, '' );
			$mine[ $t ] = $model !== '' ? RegistryClient::manifest( $endpoint, $model ) : [];
		}

		foreach ( $components as &$comp ) {
			$hash = $comp['hash'] ?? '';
			$type = $comp['type'] ?? '';

			$any_manifest  = $any[ $type ]  ?? null;
			$mine_manifest = $mine[ $type ] ?? [];

			if ( $hash === '' || ! is_array( $any_manifest ) ) {
				$comp['_audited_by_anyone'] = false;
				$comp['_audited_by_me']     = false;
				$comp['_audit_tier']        = 0;
				continue;
			}

			$by_anyone = isset( $any_manifest[ $hash ] );
			$by_me     = $model !== '' && isset( $mine_manifest[ $hash ] );
			$comp['_audited_by_anyone'] = $by_anyone;
			$comp['_audited_by_me']     = $by_me;
			$comp['_audit_tier']        = ( $by_anyone && ! $by_me ) ? 1 : 0;
		}
		return $components;
	}

	/**
	 * Filter components to only those that haven't been audited (by the given
	 * model, if scoped) and tag each with an audit-state tier the caller can
	 * use for ordering. Reads from the WP Registry plugin's manifest via
	 * RegistryClient (direct REST, app-password auth, transient-cached).
	 *
	 * Returned items are annotated with a `_audit_tier` key:
	 *   0 = NEVER audited by anyone (highest priority — completely fresh)
	 *   1 = audited by another model, NOT by $model (older audit, layered re-audit eligible)
	 *
	 * When $model is empty, every kept item is tier 0 (any-model-unaudited).
	 *
	 * Fail-open: if the registry isn't configured or the fetch fails, every
	 * component is treated as tier-0 unaudited. That's strictly more work but
	 * never silently skips a real vulnerability.
	 */
	public static function filter_unaudited( array $components, string $model = '' ) {
		$annotated = self::annotate_tiers( $components, $model );

		$out = [];
		foreach ( $annotated as $comp ) {
			if ( ! empty( $comp['_audited_by_me'] ) ) {
				continue; // covered by this model — skip entirely
			}
			if ( ! empty( $comp['_audited_by_anyone'] ) && $model === '' ) {
				continue; // default queue: hide everything anyone has audited
			}
			$out[] = $comp;
		}
		return $out;
	}

	/**
	 * Get all distinct hashes for a given slug+version+type across the fleet,
	 * with source SSH info and site count per hash.
	 */
	public static function get_hashes_for_version( string $slug, string $version, string $type = 'plugin' ): array {
		$sites_data = self::gather_sites();
		$hashes     = []; // hash → { hash, sites, source_ssh, home_directory }

		foreach ( $sites_data as $site ) {
			$ssh = '';
			if ( ! empty( $site->address ) && ! empty( $site->username ) ) {
				$ssh = "{$site->username}@{$site->address}";
				if ( ! empty( $site->port ) && $site->port !== '22' ) {
					$ssh .= " -p {$site->port}";
				}
			}
			$home_directory = $site->home_directory ?? '';

			$components = self::extract_components( $site );

			foreach ( $components as $comp ) {
				$h = $comp['hash'] ?? '';
				if ( ! $h ) {
					continue;
				}
				if ( $comp['slug'] !== $slug || $comp['version'] !== $version || $comp['type'] !== $type ) {
					continue;
				}
				if ( ! isset( $hashes[ $h ] ) ) {
					$hashes[ $h ] = [
						'hash'           => $h,
						'sites'          => 0,
						'source_ssh'     => $ssh,
						'home_directory' => $home_directory,
					];
				}
				$hashes[ $h ]['sites']++;
			}
		}

		// Sort by site count descending
		usort( $hashes, function ( $a, $b ) {
			return $b['sites'] - $a['sites'];
		} );

		return array_values( $hashes );
	}

	/**
	 * Build a version-grouped queue: aggregate by slug+version instead of individual hash.
	 * Each entry includes total sites, number of distinct hashes, and the most common hash with source_ssh.
	 */
	public static function build_version_queue( array $hash_map ): array {
		$version_map = []; // "type|slug|version" → aggregated data

		foreach ( $hash_map as $hash => $comp ) {
			$key = "{$comp['type']}|{$comp['slug']}|{$comp['version']}";
			if ( ! isset( $version_map[ $key ] ) ) {
				$version_map[ $key ] = [
					'slug'           => $comp['slug'],
					'version'        => $comp['version'],
					'type'           => $comp['type'],
					'title'          => $comp['title'] ?? $comp['slug'],
					'sites'          => 0,
					'hashes_count'   => 0,
					'hash'           => '',
					'source_ssh'     => '',
					'home_directory' => '',
					'audit_tier'     => $comp['_audit_tier'] ?? 0,
					'_max_sites'     => -1,
					'_primary_tier'  => PHP_INT_MAX, // tier of the currently-selected primary hash
				];
			}
			$version_map[ $key ]['sites'] += $comp['sites'];
			$version_map[ $key ]['hashes_count']++;

			// Worst-case tier wins: if any hash for this slug+version is fully
			// unaudited (tier 0), the version-group is treated as unaudited.
			$tier = (int) ( $comp['_audit_tier'] ?? 0 );
			if ( $tier < $version_map[ $key ]['audit_tier'] ) {
				$version_map[ $key ]['audit_tier'] = $tier;
			}

			// Pick the primary hash to surface to clients. Prefer a hash whose
			// audit_tier is lowest (i.e. genuinely needs work), then within that
			// tier prefer the most common hash. Otherwise the queue would point
			// crews at an already-audited hash even though other variants for
			// the same slug+version are still unaudited.
			$current_tier = (int) $version_map[ $key ]['_primary_tier'];
			$current_max  = (int) $version_map[ $key ]['_max_sites'];
			$is_better    = $tier < $current_tier
				|| ( $tier === $current_tier && $comp['sites'] > $current_max );

			if ( $is_better ) {
				$version_map[ $key ]['_primary_tier']  = $tier;
				$version_map[ $key ]['_max_sites']     = $comp['sites'];
				$version_map[ $key ]['hash']           = $comp['hash'];
				$version_map[ $key ]['source_ssh']     = $comp['source_ssh'] ?? '';
				$version_map[ $key ]['home_directory'] = $comp['home_directory'] ?? '';
			}
		}

		// Remove internal tracking fields
		$result = array_values( $version_map );
		foreach ( $result as &$item ) {
			unset( $item['_max_sites'], $item['_primary_tier'] );
		}

		return $result;
	}

	/**
	 * Per-slug aggregation for customer-safety prioritization.
	 *
	 * Takes the FULL annotated fleet view (output of annotate_tiers — every
	 * component, audited or not) and:
	 *   1. Groups by slug, walking every known fleet version.
	 *   2. Computes the slug's "auditable set" — the set of versions worth
	 *      auditing for this slug:
	 *        - wp.org plugins/themes: every fleet version whose version_compare
	 *          is >= the version published on api.wordpress.org. Bogus stamps
	 *          higher than wp.org-latest are in the set; stale older versions
	 *          are excluded.
	 *        - Premium components (api.wordpress.org returns no data): the
	 *          fleet-effective-latest (highest version with at least
	 *          max(2, ceil(1% × total slug sites)) sites running it, with a
	 *          fallback to the absolute highest version when no version meets
	 *          the bar) plus every version higher than it.
	 *   3. Surfaces ONE row per slug for the lowest-priority item still in the
	 *      auditable set — bogus-first ordering, meaning highest-version first
	 *      so customer-pinned/malware-suspect stamps get the first audit pass.
	 *      The slug only drops out of the queue when EVERY hash in the
	 *      auditable set has been audited under the policy.
	 *
	 * That keeps audit coverage anchored to a vendor-relevant version
	 * boundary instead of just "the highest fleet version" — a single
	 * customer-pinned 9999 stamp won't satisfy the slug's coverage when the
	 * real wp.org-latest is still un-audited.
	 *
	 * Row shape:
	 *   slug                  — plugin/theme slug
	 *   latest_version        — version this row points at for audit (newest
	 *                           still-surfaceable version in the auditable set)
	 *   sites                 — total fleet sites running ANY version of this slug
	 *   versions_in_fleet     — distinct version count
	 *   hash                  — content hash for the audit target on that version
	 *   source_ssh, home_directory — for downloading the audit target
	 *   audit_tier            — tier of the surfaced primary hash (0 = fresh,
	 *                           1 = audited by another model, surfaced under
	 *                           model-mode for layered re-audit)
	 *   type, title           — passthroughs
	 *   sites_on_latest       — fleet sites running the surfaced version
	 */
	public static function build_slug_latest_queue( array $annotated_components, string $model = '' ): array {
		$slug_map = []; // "type|slug" → bucket

		foreach ( $annotated_components as $comp ) {
			$hash = $comp['hash'] ?? '';
			if ( $hash === '' ) {
				// Hash-less components don't make sense in a slug-latest view —
				// surface them via the default or version-grouped queues.
				continue;
			}

			$key = "{$comp['type']}|{$comp['slug']}";
			$ver = $comp['version'] ?? '';
			if ( ! isset( $slug_map[ $key ] ) ) {
				$slug_map[ $key ] = [
					'slug'        => $comp['slug'],
					'type'        => $comp['type'],
					'title'       => $comp['title'] ?? $comp['slug'],
					'total_sites' => 0,
					'versions'    => [],
				];
			}

			if ( ! isset( $slug_map[ $key ]['versions'][ $ver ] ) ) {
				$slug_map[ $key ]['versions'][ $ver ] = [
					'version' => $ver,
					'sites'   => 0,
					'hashes'  => [], // every annotated component for this slug+version
				];
			}

			$slug_map[ $key ]['total_sites']                  += $comp['sites'];
			$slug_map[ $key ]['versions'][ $ver ]['sites']    += $comp['sites'];
			$slug_map[ $key ]['versions'][ $ver ]['hashes'][]  = $comp;
		}

		$result = [];
		foreach ( $slug_map as $bucket ) {
			if ( empty( $bucket['versions'] ) ) {
				continue;
			}

			// Performance gate — skip wp.org calls for slugs where the caller's
			// policy would drop every hash anyway. Common case: every fleet
			// hash for this slug is already audited under model+policy.
			$any_surfaceable = false;
			foreach ( $bucket['versions'] as $vdata ) {
				foreach ( $vdata['hashes'] as $h ) {
					if ( ! empty( $h['_audited_by_me'] ) ) {
						continue;
					}
					if ( $model === '' && ! empty( $h['_audited_by_anyone'] ) ) {
						continue;
					}
					$any_surfaceable = true;
					break 2;
				}
			}
			if ( ! $any_surfaceable ) {
				continue;
			}

			$auditable = self::compute_auditable_versions( $bucket );
			if ( empty( $auditable ) ) {
				continue;
			}

			// Bogus-first: walk versions DESC. A customer-pinned "9999" stamp
			// (above wp.org-latest) gets audited before the real wp.org-latest
			// underneath it. After both are covered, the slug drops out.
			usort( $auditable, function ( $a, $b ) { return version_compare( $b, $a ); } );

			$picked_ver    = null;
			$picked_data   = null;
			$picked_hashes = null;
			foreach ( $auditable as $v ) {
				if ( ! isset( $bucket['versions'][ $v ] ) ) {
					continue;
				}
				$vdata       = $bucket['versions'][ $v ];
				$surfaceable = [];
				foreach ( $vdata['hashes'] as $h ) {
					if ( ! empty( $h['_audited_by_me'] ) ) {
						continue;
					}
					if ( $model === '' && ! empty( $h['_audited_by_anyone'] ) ) {
						continue;
					}
					$surfaceable[] = $h;
				}
				if ( ! empty( $surfaceable ) ) {
					$picked_ver    = $v;
					$picked_data   = $vdata;
					$picked_hashes = $surfaceable;
					break;
				}
			}
			if ( $picked_ver === null ) {
				continue;
			}

			// Primary within the chosen version: lowest tier first (0 beats 1),
			// then most sites. Mirrors build_version_queue.
			usort( $picked_hashes, function ( $a, $b ) {
				$at = (int) ( $a['_audit_tier'] ?? 0 );
				$bt = (int) ( $b['_audit_tier'] ?? 0 );
				if ( $at !== $bt ) {
					return $at - $bt;
				}
				return ( $b['sites'] ?? 0 ) - ( $a['sites'] ?? 0 );
			} );
			$primary = $picked_hashes[0];

			$result[] = [
				'slug'              => $bucket['slug'],
				'latest_version'    => $picked_ver,
				'sites'             => $bucket['total_sites'],
				'versions_in_fleet' => count( $bucket['versions'] ),
				'type'              => $bucket['type'],
				'title'             => $bucket['title'],
				'hash'              => $primary['hash'],
				'hashes_count'      => count( $picked_data['hashes'] ),
				'source_ssh'        => $primary['source_ssh'] ?? '',
				'home_directory'    => $primary['home_directory'] ?? '',
				'audit_tier'        => (int) ( $primary['_audit_tier'] ?? 0 ),
				'sites_on_latest'   => $picked_data['sites'],
			];
		}

		return $result;
	}

	/**
	 * Compute the slug's auditable version set. For wp.org plugins/themes we
	 * use api.wordpress.org as the authority — anything in the fleet >= the
	 * published version is in scope. For premium (or wp.org-unknown) slugs we
	 * fall back to fleet shape: the highest version meeting a deployment
	 * threshold plus every fleet version higher than it.
	 *
	 * Threshold for "real" premium version = max(2, ceil(1% × total slug sites)).
	 * A version with fewer sites than the threshold is either a one-off
	 * customer pin or a fresh release that hasn't propagated yet — either way
	 * it stays surfaced as an audit target (versions higher than effective
	 * latest), but doesn't get to ANCHOR the auditable set.
	 */
	private static function compute_auditable_versions( array $bucket ): array {
		$versions = array_keys( $bucket['versions'] );
		$versions = array_values( array_filter( $versions, function ( $v ) { return $v !== ''; } ) );
		if ( empty( $versions ) ) {
			return [];
		}

		$type         = $bucket['type'] ?? 'plugin';
		$wporg_latest = null;
		if ( $type === 'plugin' || $type === 'theme' ) {
			$wporg_latest = self::wporg_latest_version( $bucket['slug'], $type );
		}

		if ( $wporg_latest !== null ) {
			return array_values( array_filter( $versions, function ( $v ) use ( $wporg_latest ) {
				return version_compare( $v, $wporg_latest, '>=' );
			} ) );
		}

		// Premium / wp.org-unknown: fleet-shape rule.
		$total     = (int) ( $bucket['total_sites'] ?? 0 );
		$threshold = max( 2, (int) ceil( 0.01 * $total ) );

		$sorted_desc = $versions;
		usort( $sorted_desc, function ( $a, $b ) { return version_compare( $b, $a ); } );

		$effective_latest = null;
		foreach ( $sorted_desc as $v ) {
			$sites = (int) ( $bucket['versions'][ $v ]['sites'] ?? 0 );
			if ( $sites >= $threshold ) {
				$effective_latest = $v;
				break;
			}
		}
		if ( $effective_latest === null ) {
			// No version meets the threshold — typical for very small slugs
			// where 1% rounds to 1 and every version has a single site. Fall
			// back to the absolute highest version so we still pick something.
			$effective_latest = $sorted_desc[0];
		}

		return array_values( array_filter( $versions, function ( $v ) use ( $effective_latest ) {
			return version_compare( $v, $effective_latest, '>=' );
		} ) );
	}

	/**
	 * Authoritative latest version for a wp.org-hosted plugin/theme via
	 * api.wordpress.org. Returns null for premium / non-listed components
	 * (api.wordpress.org returns an error), or when the lookup fails.
	 *
	 * Transient-cached for 1 hour to keep queue endpoint latency bounded.
	 * First call on a cold cache for a 4k-slug queue makes ~hundreds of
	 * wp.org HTTP calls (gated by the surfaceability short-circuit in
	 * build_slug_latest_queue, so only slugs with unaudited hashes pay the
	 * cost). Subsequent calls within the cache window are instant.
	 *
	 * Fail-open: every failure path caches '__none__' so we don't hammer
	 * wp.org on the same slug repeatedly. Net effect = treat that slug as
	 * premium for the cache window, which is the safe degradation.
	 */
	public static function wporg_latest_version( string $slug, string $type = 'plugin' ): ?string {
		if ( $slug === '' ) {
			return null;
		}
		if ( $type !== 'plugin' && $type !== 'theme' ) {
			return null;
		}

		$cache_key = 'cc_wporg_latest_' . $type . '_' . md5( $slug );
		$cached    = get_transient( $cache_key );
		if ( $cached !== false ) {
			return $cached === '__none__' ? null : (string) $cached;
		}

		$endpoint = $type === 'theme' ? 'themes/info/1.2' : 'plugins/info/1.2';
		$action   = $type === 'theme' ? 'theme_information'  : 'plugin_information';
		$url      = "https://api.wordpress.org/{$endpoint}/?action={$action}&request[slug]=" . rawurlencode( $slug );

		$response = wp_remote_get( $url, [ 'timeout' => 3 ] );
		if ( is_wp_error( $response ) || (int) wp_remote_retrieve_response_code( $response ) !== 200 ) {
			set_transient( $cache_key, '__none__', HOUR_IN_SECONDS );
			return null;
		}

		$data = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( ! is_array( $data ) || isset( $data['error'] ) || empty( $data['version'] ) ) {
			set_transient( $cache_key, '__none__', HOUR_IN_SECONDS );
			return null;
		}

		set_transient( $cache_key, $data['version'], HOUR_IN_SECONDS );
		return $data['version'];
	}

	/**
	 * Gather all production environments with SSH + plugin/theme data + details.
	 */
	public static function gather_sites() {
		global $wpdb;

		$sites_table = $wpdb->prefix . 'captaincore_sites';
		$env_table   = $wpdb->prefix . 'captaincore_environments';

		return $wpdb->get_results( "
			SELECT s.site_id, s.name, s.provider,
			       e.address, e.username, e.port, e.home_url, e.home_directory,
			       e.plugins, e.themes, e.details
			FROM {$env_table} e
			JOIN {$sites_table} s ON e.site_id = s.site_id
			WHERE s.status = 'active'
			  AND s.provider IS NOT NULL
			  AND e.environment = 'Production'
			  AND e.home_url IS NOT NULL AND e.home_url != ''
			  AND e.plugins IS NOT NULL
			ORDER BY s.name ASC
		" );
	}

}
