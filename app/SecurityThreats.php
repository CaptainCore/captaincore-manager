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
						$key = "plugin|{$plugin->name}|{$plugin->version}";
						if ( ! isset( $counts[ $key ] ) ) {
							$counts[ $key ] = [
								'slug'       => $plugin->name,
								'version'    => $plugin->version,
								'type'       => 'plugin',
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
						$key = "theme|{$theme->name}|{$theme->version}";
						if ( ! isset( $counts[ $key ] ) ) {
							$counts[ $key ] = [
								'slug'       => $theme->name,
								'version'    => $theme->version,
								'type'       => 'theme',
								'title'      => html_entity_decode( $theme->title ),
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

			if ( ! $slug ) {
				continue;
			}

			// Find component record matching slug+version (most recent audit first)
			if ( $type === 'mu-plugin' && ( $version === '' || $version === null ) ) {
				$component = $wpdb->get_row( $wpdb->prepare(
					"SELECT c.*, a.audit_date, a.id AS audit_id
					 FROM {$components_t} c
					 JOIN {$audits_t} a ON c.audit_id = a.id
					 WHERE c.slug = %s AND c.component_type = %s
					 ORDER BY a.audit_date DESC LIMIT 1",
					$slug, $type
				), ARRAY_A );
			} else {
				if ( $type ) {
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
	 * Full security threats summary: fleet check + affected sites per threat.
	 * Merges tracking data and filters to critical+high only.
	 *
	 * @return array Complete threat report with affected site details.
	 */
	public static function summary() {
		$inventory = self::inventory();
		$result    = self::check( $inventory );

		if ( is_wp_error( $result ) ) {
			return [ 'error' => $result->get_error_message() ];
		}

		if ( empty( $result['threats'] ) ) {
			return [
				'threats'          => [],
				'total_threats'    => 0,
				'severity_summary' => [ 'critical' => 0, 'high' => 0, 'medium' => 0, 'low' => 0 ],
				'fleet_size'       => count( $inventory ),
			];
		}

		$tracking_lookup = self::get_tracking();

		// Normalize API field names: status→severity, display_name→title
		foreach ( $result['threats'] as &$threat ) {
			if ( isset( $threat['status'] ) && ! isset( $threat['severity'] ) ) {
				$threat['severity'] = $threat['status'];
			}
			if ( isset( $threat['display_name'] ) && ! isset( $threat['title'] ) ) {
				$threat['title'] = $threat['display_name'];
			}
		}
		unset( $threat );

		// Filter to critical and high severity only
		$result['threats'] = array_values( array_filter( $result['threats'], function( $threat ) {
			return in_array( $threat['severity'] ?? '', [ 'critical', 'high' ] );
		} ) );

		// Enrich each threat with the specific affected sites and tracking data
		foreach ( $result['threats'] as &$threat ) {
			$sites = self::affected_sites( $threat['slug'], $threat['version'], $threat['type'] );
			$threat['affected_sites'] = array_map( function( $site ) {
				$entry = [
					'site_id'        => (int) $site->site_id,
					'name'           => $site->name,
					'environment_id' => (int) $site->environment_id,
					'environment'    => $site->environment,
					'home_url'       => $site->home_url,
				];
				if ( ! empty( $site->address ) ) {
					$entry['ssh']            = "{$site->username}@{$site->address} -p {$site->port}";
					$entry['home_directory'] = $site->home_directory ?: '';
				}
				return $entry;
			}, $sites );
			$threat['affected_count'] = count( $sites );

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
		}
		unset( $threat );

		// Merge patch data
		$patch_lookup = SecurityPatches::get_lookup();
		foreach ( $result['threats'] as &$threat ) {
			$key = "{$threat['type']}|{$threat['slug']}|{$threat['version']}";
			$threat['patch'] = $patch_lookup[ $key ] ?? null;
		}
		unset( $threat );

		// Recount severity summary for filtered results
		$severity_summary = [ 'critical' => 0, 'high' => 0 ];
		foreach ( $result['threats'] as $threat ) {
			$sev = $threat['severity'] ?? '';
			if ( isset( $severity_summary[ $sev ] ) ) {
				$severity_summary[ $sev ]++;
			}
		}
		$result['severity_summary'] = $severity_summary;
		$result['total_threats']    = count( $result['threats'] );
		$result['fleet_size']       = count( $inventory );

		return $result;
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
