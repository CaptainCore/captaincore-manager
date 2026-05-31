<?php

namespace CaptainCore;

class SecurityThreats {

	/**
	 * Collect unique plugin/theme inventory from all production environments.
	 *
	 * Returns array of {slug, version, type, site_count} representing
	 * every distinct component+version across the fleet.
	 */
	public static function inventory() {
		$filters = Environments::fetch_filters_for_admins();
		$counts  = [];

		foreach ( $filters as $row ) {
			// Process plugins
			if ( ! empty( $row->plugins ) ) {
				$plugins = json_decode( $row->plugins );
				if ( is_array( $plugins ) ) {
					foreach ( $plugins as $plugin ) {
						$hash = $plugin->hash ?? '';
						$key  = $hash ? "plugin|{$plugin->name}|{$plugin->version}|{$hash}" : "plugin|{$plugin->name}|{$plugin->version}";
						if ( ! isset( $counts[ $key ] ) ) {
							$counts[ $key ] = [
								'slug'       => $plugin->name,
								'version'    => $plugin->version,
								'type'       => 'plugin',
								'hash'       => $hash,
								'title'      => html_entity_decode( $plugin->title ),
								'site_count' => 0,
							];
						}
						$counts[ $key ]['site_count']++;
					}
				}
			}

			// Process themes
			if ( ! empty( $row->themes ) ) {
				$themes = json_decode( $row->themes );
				if ( is_array( $themes ) ) {
					foreach ( $themes as $theme ) {
						$hash = $theme->hash ?? '';
						$key  = $hash ? "theme|{$theme->name}|{$theme->version}|{$hash}" : "theme|{$theme->name}|{$theme->version}";
						if ( ! isset( $counts[ $key ] ) ) {
							$counts[ $key ] = [
								'slug'       => $theme->name,
								'version'    => $theme->version,
								'type'       => 'theme',
								'hash'       => $hash,
								'title'      => html_entity_decode( $theme->title ),
								'site_count' => 0,
							];
						}
						$counts[ $key ]['site_count']++;
					}
				}
			}

			// Process loose files (core extra/modified + wp-content PHP files)
			if ( ! empty( $row->details ) ) {
				$details = json_decode( $row->details );

				$file_hash_keys = [ 'core_file_hashes', 'loose_file_hashes' ];
				foreach ( $file_hash_keys as $hash_key ) {
					if ( empty( $details->$hash_key ) ) {
						continue;
					}
					foreach ( $details->$hash_key as $path => $hash ) {
						$key = "file|{$path}||{$hash}";
						if ( ! isset( $counts[ $key ] ) ) {
							$counts[ $key ] = [
								'slug'       => $path,
								'version'    => '',
								'type'       => 'file',
								'hash'       => $hash,
								'title'      => $path,
								'site_count' => 0,
							];
						}
						$counts[ $key ]['site_count']++;
					}
				}
			}
		}

		return array_values( $counts );
	}

	/**
	 * Call Security Finder's fleet-check API with the fleet inventory.
	 *
	 * @param array $inventory Array from self::inventory().
	 * @return array|WP_Error Threat data from Security Finder.
	 */
	public static function check( $inventory = null ) {
		if ( $inventory === null ) {
			$inventory = self::inventory();
		}

		// Try local MySQL tables first (same database as WordPress)
		if ( self::has_local_tables() ) {
			return self::check_local( $inventory );
		}

		// Fall back to HTTP API
		$api_url = self::api_url();
		if ( ! $api_url ) {
			return new \WP_Error( 'no_api_url', 'Security Finder tables not found and SECURITY_FINDER_API_URL is not configured.' );
		}

		$response = wp_remote_post( $api_url . '/api.php?action=fleet-check', [
			'headers'   => [ 'Content-Type' => 'application/json' ],
			'body'      => wp_json_encode( $inventory ),
			'timeout'   => 30,
			'sslverify' => false,
		] );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( json_last_error() !== JSON_ERROR_NONE ) {
			return new \WP_Error( 'json_error', 'Invalid JSON from Security Finder API.' );
		}

		return $body;
	}

	/**
	 * Check if Security Finder MySQL tables exist in the WordPress database.
	 */
	private static function has_local_tables() {
		global $wpdb;
		$table  = "{$wpdb->prefix}captaincore_sf_audits";
		$result = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
		return ! empty( $result );
	}

	/**
	 * Query the Security Finder tables in the WordPress MySQL database directly.
	 *
	 * @param array $inventory Fleet inventory from self::inventory().
	 * @return array Fleet-check results in the same format as the API.
	 */
	private static function check_local( $inventory ) {
		global $wpdb;

		$components_t  = "{$wpdb->prefix}captaincore_sf_components";
		$audits_t      = "{$wpdb->prefix}captaincore_sf_audits";
		$findings_t    = "{$wpdb->prefix}captaincore_sf_findings";
		$severity_rank = [ 'critical' => 4, 'high' => 3, 'medium' => 2, 'low' => 1, 'clean' => 0 ];
		$threats       = [];

		foreach ( $inventory as $item ) {
			$slug    = $item['slug'] ?? '';
			$version = $item['version'] ?? '';
			$type    = $item['type'] ?? '';
			$hash    = $item['hash'] ?? '';

			if ( ! $slug ) {
				continue;
			}

			$component = null;

			// Hash-first lookup (most precise — exact code match)
			if ( $hash ) {
				$component = $wpdb->get_row( $wpdb->prepare(
					"SELECT c.*, a.audit_date, a.id AS audit_id
					 FROM {$components_t} c
					 JOIN {$audits_t} a ON c.audit_id = a.id
					 WHERE c.content_hash = %s
					 ORDER BY a.audit_date DESC LIMIT 1",
					$hash
				), ARRAY_A );
			}

			// Fall back to slug+version lookup
			if ( ! $component ) {
				if ( $type === 'mu-plugin' && ( $version === '' || $version === null ) ) {
					$component = $wpdb->get_row( $wpdb->prepare(
						"SELECT c.*, a.audit_date, a.id AS audit_id
						 FROM {$components_t} c
						 JOIN {$audits_t} a ON c.audit_id = a.id
						 WHERE c.slug = %s AND c.component_type = %s
						 ORDER BY a.audit_date DESC LIMIT 1",
						$slug, $type
					), ARRAY_A );
				} elseif ( $type ) {
					$component = $wpdb->get_row( $wpdb->prepare(
						"SELECT c.*, a.audit_date, a.id AS audit_id
						 FROM {$components_t} c
						 JOIN {$audits_t} a ON c.audit_id = a.id
						 WHERE c.slug = %s AND c.version = %s AND c.component_type = %s
						 ORDER BY a.audit_date DESC LIMIT 1",
						$slug, $version, $type
					), ARRAY_A );
				} else {
					$component = $wpdb->get_row( $wpdb->prepare(
						"SELECT c.*, a.audit_date, a.id AS audit_id
						 FROM {$components_t} c
						 JOIN {$audits_t} a ON c.audit_id = a.id
						 WHERE c.slug = %s AND c.version = %s
						 ORDER BY a.audit_date DESC LIMIT 1",
						$slug, $version
					), ARRAY_A );
				}
			}

			if ( ! $component || $component['status'] === 'clean' ) {
				continue;
			}

			// Fetch findings for this component
			$findings = $wpdb->get_results( $wpdb->prepare(
				"SELECT f.finding_code, f.severity, f.title, f.description,
				        f.vuln_type, f.cve, f.cvss_score, f.recommendation,
				        f.source, f.wordfence_link
				 FROM {$findings_t} f
				 WHERE f.component_id = %d
				   AND (f.elevated IS NULL OR f.elevated = 1)
				 ORDER BY FIELD(f.severity, 'critical', 'high', 'medium', 'low'),
				          f.finding_code",
				$component['id']
			), ARRAY_A );

			if ( empty( $findings ) ) {
				continue;
			}

			$threats[] = [
				'slug'                => $slug,
				'version'             => $version,
				'type'                => $type ?: $component['component_type'],
				'display_name'        => $component['display_name'],
				'content_hash'        => $component['content_hash'] ?? null,
				'status'              => $component['status'],
				'key_issue'           => $component['key_issue'],
				'flagged_for_removal' => (bool) $component['flagged_for_removal'],
				'removal_reason'      => $component['removal_reason'],
				'audit_date'          => $component['audit_date'],
				'audit_id'            => $component['audit_id'],
				'findings'            => $findings,
				'fleet_count'         => (int) ( $item['site_count'] ?? 0 ),
			];
		}

		// Sort by severity (worst first), then by fleet exposure
		usort( $threats, function ( $a, $b ) use ( $severity_rank ) {
			$sev_diff = ( $severity_rank[ $b['status'] ] ?? 0 ) - ( $severity_rank[ $a['status'] ] ?? 0 );
			if ( $sev_diff !== 0 ) {
				return $sev_diff;
			}
			return $b['fleet_count'] - $a['fleet_count'];
		} );

		// Build summary counts
		$summary = [ 'critical' => 0, 'high' => 0, 'medium' => 0, 'low' => 0 ];
		foreach ( $threats as $threat ) {
			if ( isset( $summary[ $threat['status'] ] ) ) {
				$summary[ $threat['status'] ]++;
			}
		}

		return [
			'threats'          => $threats,
			'total_threats'    => count( $threats ),
			'severity_summary' => $summary,
		];
	}

	/**
	 * Find which sites run a specific plugin or theme version.
	 *
	 * @param string $slug    Component slug.
	 * @param string $version Component version.
	 * @param string $type    'plugin' or 'theme'.
	 * @return array Array of {site_id, name, environment_id}.
	 */
	public static function affected_sites( $slug, $version, $type = 'plugin' ) {
		global $wpdb;

		$sites_table = "{$wpdb->prefix}captaincore_sites";
		$env_table   = "{$wpdb->prefix}captaincore_environments";
		$column      = $type === 'theme' ? 'themes' : 'plugins';

		// Build REGEXP pattern matching CaptainCore's JSON format
		$pattern = '[{]"name":"' . esc_sql( $slug ) . '","title":"[^"]*","status":"[^"]*","version":"' . esc_sql( $version ) . '"[}]';

		$sql = $wpdb->prepare(
			"SELECT s.site_id, s.name, e.environment_id, e.environment, e.home_url,
			        e.address, e.username, e.port, e.home_directory
			 FROM {$sites_table} s
			 INNER JOIN {$env_table} e ON s.site_id = e.site_id
			 WHERE s.status = 'active'
			   AND e.{$column} REGEXP %s
			 ORDER BY s.name ASC, e.environment ASC",
			$pattern
		);

		return $wpdb->get_results( $sql );
	}

	/**
	 * Upsert a tracking record for a specific threat.
	 */
	public static function track( $slug, $version, $type, $status ) {
		$tracking = new SecurityThreatTracking();
		$existing = $tracking->where( [
			'slug'    => $slug,
			'version' => $version,
			'type'    => $type,
		] );

		$time_now = date( 'Y-m-d H:i:s' );

		if ( ! empty( $existing ) ) {
			$data = [
				'status'     => $status,
				'updated_at' => $time_now,
			];
			if ( $status === 'resolved' ) {
				$data['resolved_at'] = $time_now;
			}
			$tracking->update(
				$data,
				[ 'security_threat_tracking_id' => $existing[0]->security_threat_tracking_id ]
			);
			return $tracking->get( $existing[0]->security_threat_tracking_id );
		}

		$id = $tracking->insert( [
			'slug'       => $slug,
			'version'    => $version,
			'type'       => $type,
			'status'     => $status,
			'notes'      => '[]',
			'created_at' => $time_now,
			'updated_at' => $time_now,
		] );
		return $tracking->get( $id );
	}

	/**
	 * Add a timestamped note to a tracked threat.
	 */
	public static function add_note( $slug, $version, $type, $note ) {
		$tracking = new SecurityThreatTracking();
		$existing = $tracking->where( [
			'slug'    => $slug,
			'version' => $version,
			'type'    => $type,
		] );

		$time_now  = date( 'Y-m-d H:i:s' );
		$new_note  = [ 'note' => $note, 'date' => $time_now ];

		if ( ! empty( $existing ) ) {
			$record = $existing[0];
			$notes  = json_decode( $record->notes, true ) ?: [];
			$notes[] = $new_note;
			$tracking->update(
				[ 'notes' => wp_json_encode( $notes ), 'updated_at' => $time_now ],
				[ 'security_threat_tracking_id' => $record->security_threat_tracking_id ]
			);
			return $tracking->get( $record->security_threat_tracking_id );
		}

		// Auto-create tracking record if none exists
		$id = $tracking->insert( [
			'slug'       => $slug,
			'version'    => $version,
			'type'       => $type,
			'status'     => 'investigating',
			'notes'      => wp_json_encode( [ $new_note ] ),
			'created_at' => $time_now,
			'updated_at' => $time_now,
		] );
		return $tracking->get( $id );
	}

	/**
	 * Mark a threat as resolved and create ProcessLog entries on each affected site.
	 */
	public static function resolve( $slug, $version, $type, $note = '' ) {
		self::track( $slug, $version, $type, 'resolved' );

		if ( ! empty( $note ) ) {
			self::add_note( $slug, $version, $type, $note );
		}

		$sites    = self::affected_sites( $slug, $version, $type );
		$site_ids = array_map( function( $site ) { return (int) $site->site_id; }, $sites );

		if ( ! empty( $site_ids ) ) {
			$label   = ucfirst( $type );
			$message = "**Security threat resolved:** {$label} `{$slug}` v{$version}" . ( $note ? " — {$note}" : '' );
			ProcessLog::insert( $message, $site_ids );
		}

		return [
			'status'         => 'resolved',
			'affected_sites' => count( $site_ids ),
		];
	}

	/**
	 * Fetch all tracking records as a keyed lookup.
	 */
	public static function get_tracking() {
		$tracking = new SecurityThreatTracking();
		$records  = $tracking->all();
		$lookup   = [];

		foreach ( $records as $record ) {
			$key = "{$record->type}|{$record->slug}|{$record->version}";
			$lookup[ $key ] = [
				'status'      => $record->status,
				'notes'       => json_decode( $record->notes, true ) ?: [],
				'created_at'  => $record->created_at,
				'updated_at'  => $record->updated_at,
				'resolved_at' => $record->resolved_at,
			];
		}

		return $lookup;
	}

	/**
	 * Fetch the latest critical and high severity findings directly from the
	 * Security Finder tables, without needing a fleet inventory.
	 *
	 * @param int $limit Number of latest threats to fetch.
	 * @return array Array of threats with findings.
	 */
	public static function fetch_latest_threats( $limit = 100 ) {
		// latest_only=1 scopes the registry to the highest audited version
		// per (slug, component_type) so superseded versions are not surfaced
		// here. Older builds are still indexed by the registry but the fleet
		// security view treats the current build as the source of truth.
		$response = RegistryClient::findings( [
			'severity'    => 'critical,high',
			'latest_only' => 1,
			'per_page'    => $limit,
		] );

		if ( ! is_array( $response ) || ! isset( $response['findings'] ) ) {
			return [];
		}

		$grouped = [];
		foreach ( $response['findings'] as $f ) {
			$key = "{$f['component_type']}|{$f['slug']}|{$f['version']}";
			if ( ! isset( $grouped[ $key ] ) ) {
				$grouped[ $key ] = [
					'slug'                => $f['slug'],
					'version'             => $f['version'],
					'type'                => $f['component_type'],
					'title'               => $f['display_name'],
					'severity'            => $f['severity'],
					'audit_date'          => $f['discovered_at'] ? date( 'Y-m-d', strtotime( $f['discovered_at'] ) ) : ( $f['poc_scaffolded_at'] ? date( 'Y-m-d', strtotime( $f['poc_scaffolded_at'] ) ) : '' ),
					'findings'            => [],
				];
			}
			$grouped[ $key ]['findings'][] = [
				'finding_code'    => $f['finding_code'] ?? '',
				'severity'        => $f['severity'] ?? '',
				'title'           => $f['title'] ?? '',
				'description'     => $f['description'] ?? '',
				'vuln_type'       => $f['vuln_type'] ?? '',
				'cve'             => $f['cve'] ?? '',
				'cvss_score'      => $f['cvss_score'] ?? '',
				'recommendation'  => $f['recommendation'] ?? '',
				'source'          => $f['source'] ?? '',
				'wordfence_link'  => $f['wordfence_link'] ?? null,
			];
		}

		return array_values( $grouped );
	}

	/**
	 * Detect presence of a batch of threats across the fleet.
	 *
	 * @param array $threats Array of threat objects.
	 */
	public static function detect_in_fleet( &$threats ) {
		global $wpdb;

		if ( empty( $threats ) ) {
			return;
		}

		$sites_table = "{$wpdb->prefix}captaincore_sites";
		$env_table   = "{$wpdb->prefix}captaincore_environments";

		$plugin_slugs = [];
		$theme_slugs  = [];
		foreach ( $threats as $threat ) {
			if ( $threat['type'] === 'theme' ) {
				$theme_slugs[] = preg_quote( $threat['slug'] );
			} else {
				$plugin_slugs[] = preg_quote( $threat['slug'] );
			}
		}

		// esc_sql() the assembled REGEXP literal — preg_quote escapes regex
		// metacharacters but not the SQL single-quote, so a slug containing a
		// quote could otherwise break out of the string literal.
		$clauses = [];
		if ( ! empty( $plugin_slugs ) ) {
			$clauses[] = "e.plugins REGEXP '" . esc_sql( implode( '|', array_unique( $plugin_slugs ) ) ) . "'";
		}
		if ( ! empty( $theme_slugs ) ) {
			$clauses[] = "e.themes REGEXP '" . esc_sql( implode( '|', array_unique( $theme_slugs ) ) ) . "'";
		}

		if ( empty( $clauses ) ) {
			return;
		}

		$sql = "SELECT s.site_id, s.name, e.environment_id, e.environment, e.home_url,
				        e.address, e.username, e.port, e.home_directory, e.plugins, e.themes
				 FROM {$sites_table} s
				 INNER JOIN {$env_table} e ON s.site_id = e.site_id
				 WHERE s.status = 'active'
				   AND (" . implode( ' OR ', $clauses ) . ")
				 ORDER BY s.name ASC, e.environment ASC";

		$rows = $wpdb->get_results( $sql );

		// Now match each row to the threats in PHP.
		//
		// Matching rule: same slug AND installed_version <= flagged_version
		// (per version_compare). Older-but-vulnerable installs were being
		// missed by the prior exact-version match — e.g. the registry flags
		// revslider v7.0.14 critical but the fleet runs v6.7.41 and older,
		// so every site would have read as 0 affected. Newer-than-flagged
		// versions stay clear (already on a patched build).
		foreach ( $threats as &$threat ) {
			$threat['affected_sites'] = [];
			$slug    = $threat['slug'];
			$version = $threat['version'];
			$type    = $threat['type'];

			foreach ( $rows as $row ) {
				$json = $type === 'theme' ? $row->themes : $row->plugins;
				if ( empty( $json ) ) {
					continue;
				}
				// Cheap substring prefilter on slug — version may be older so we can't gate on it here.
				if ( strpos( $json, $slug ) === false ) {
					continue;
				}

				$components = json_decode( $json );
				if ( ! is_array( $components ) ) {
					continue;
				}

				foreach ( $components as $c ) {
					if ( $c->name !== $slug ) {
						continue;
					}
					$installed = (string) ( $c->version ?? '' );
					if ( $version !== '' ) {
						if ( $installed === '' || ! version_compare( $installed, $version, '<=' ) ) {
							continue;
						}
					}
					$entry = [
						'site_id'           => (int) $row->site_id,
						'name'              => $row->name,
						'environment_id'    => (int) $row->environment_id,
						'environment'       => $row->environment,
						'home_url'          => $row->home_url,
						'installed_version' => $installed,
					];
					if ( ! empty( $row->address ) ) {
						$entry['ssh']            = "{$row->username}@{$row->address} -p {$row->port}";
						$entry['home_directory'] = $row->home_directory ?: '';
					}
					$threat['affected_sites'][] = $entry;
					break;
				}
			}
			$threat['affected_count'] = count( $threat['affected_sites'] );
		}
	}

	public static function summary() {
		// New approach: Fetch latest threats first. Much faster than building inventory.
		$threats = self::fetch_latest_threats( 100 );

		if ( empty( $threats ) ) {
			return [
				'threats'          => [],
				'total_threats'    => 0,
				'severity_summary' => [ 'critical' => 0, 'high' => 0 ],
				'fleet_size'       => 0,
			];
		}

		// Detect presence in fleet, then drop threats with no fleet exposure
		// (plugin/theme isn't installed on any active environment). Keeps the
		// vulnerabilities tab focused on actionable rows rather than registry
		// noise about components we don't run.
		self::detect_in_fleet( $threats );
		$threats = array_values( array_filter( $threats, function ( $t ) {
			return ! empty( $t['affected_sites'] );
		} ) );

		if ( empty( $threats ) ) {
			return [
				'threats'          => [],
				'total_threats'    => 0,
				'severity_summary' => [ 'critical' => 0, 'high' => 0 ],
				'fleet_size'       => 0,
			];
		}

		$tracking_lookup = self::get_tracking();
		$patch_lookup    = SecurityPatches::get_lookup();

		// Enrich each threat with tracking and patch data
		foreach ( $threats as &$threat ) {
			// Merge tracking data
			$key = "{$threat['type']}|{$threat['slug']}|{$threat['version']}";
			if ( isset( $tracking_lookup[ $key ] ) ) {
				$threat['tracking'] = $tracking_lookup[ $key ];
			} else {
				$threat['tracking'] = [
					'status'      => 'new',
					'notes'       => [],
					'created_at'  => null,
					'updated_at'  => null,
					'resolved_at' => null,
				];
			}

			// Merge patch data
			$threat['patch'] = $patch_lookup[ $key ] ?? null;
		}
		unset( $threat );

		// Build severity summary
		$severity_summary = [ 'critical' => 0, 'high' => 0 ];
		foreach ( $threats as $threat ) {
			$sev = $threat['severity'] ?? '';
			if ( isset( $severity_summary[ $sev ] ) ) {
				$severity_summary[ $sev ]++;
			}
		}

		return [
			'threats'          => $threats,
			'total_threats'    => count( $threats ),
			'severity_summary' => $severity_summary,
			'fleet_size'       => 0, // Set to 0 since we skipped full inventory
		];
	}

	/**
	 * Get the Security Finder API base URL.
	 */
	private static function api_url() {
		if ( defined( 'SECURITY_FINDER_API_URL' ) ) {
			return rtrim( SECURITY_FINDER_API_URL, '/' );
		}
		return get_option( 'security_finder_api_url', '' );
	}

}
