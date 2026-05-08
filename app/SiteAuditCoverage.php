<?php

namespace CaptainCore;

class SiteAuditCoverage {

	const KNOWN_SAFE_HASHES = [
		'bd48c24ce4500e60d2524571756787283f0d3fbfa50b116331309c09773f0cd1',
		'7aa373bd001f0bc70bfdfe37454bdc3c6796c567c3ace11930c474ed8a78e0a2',
		'e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855',
	];

	public static function summarize( $plugins_input, $themes_input, $details_input ) {
		$components = self::extract_components( $plugins_input, $themes_input, $details_input );
		$manifest   = self::lookup_hashes( $components );

		$counts = [
			'total'     => count( $components ),
			'audited'   => 0,
			'unaudited' => 0,
			'malware'   => 0,
			'critical'  => 0,
			'high'      => 0,
			'medium'    => 0,
			'low'       => 0,
			'clean'     => 0,
		];

		foreach ( $components as $c ) {
			$entry = self::resolve_entry( $c['hash'], $manifest );
			if ( $entry === null ) {
				$counts['unaudited']++;
				continue;
			}
			$counts['audited']++;
			if ( ! empty( $entry['malware'] ) ) {
				$counts['malware']++;
			}
			$status = $entry['status'] ?? 'clean';
			if ( isset( $counts[ $status ] ) ) {
				$counts[ $status ]++;
			}
		}

		$counts['coverage_pct'] = $counts['total'] > 0
			? (int) round( $counts['audited'] * 100 / $counts['total'] )
			: 0;
		$counts['generated_at'] = gmdate( 'c' );

		return $counts;
	}

	public static function components_for_environment( $environment ) {
		$components = self::extract_components(
			$environment->plugins ?? null,
			$environment->themes  ?? null,
			$environment->details ?? null
		);
		$manifest = self::lookup_hashes( $components );

		$rows = [];
		foreach ( $components as $c ) {
			$entry = self::resolve_entry( $c['hash'], $manifest );

			$row = [
				'kind'       => $c['kind'],
				'slug'       => $c['slug'],
				'name'       => $c['name'],
				'version'    => $c['version'],
				'hash'       => $c['hash'],
				'short_hash' => substr( $c['hash'], 0, 12 ),
			];

			if ( $entry === null ) {
				$row['status']  = 'unaudited';
			} else {
				$row['status']  = $entry['status'] ?? 'clean';
				if ( ! empty( $entry['malware'] ) ) {
					$row['malware'] = true;
				}
				if ( ! empty( $entry['key_issue'] ) ) {
					$row['key_issue'] = $entry['key_issue'];
				}
				if ( ! empty( $entry['findings'] ) ) {
					$row['findings_count'] = (int) $entry['findings'];
				}
			}

			$rows[] = $row;
		}

		usort( $rows, [ self::class, 'sort_by_severity' ] );
		return $rows;
	}

	public static function findings_by_hash( $hash ) {
		global $wpdb;

		$components_t = $wpdb->prefix . 'captaincore_sf_components';
		$findings_t   = $wpdb->prefix . 'captaincore_sf_findings';
		$audits_t     = $wpdb->prefix . 'captaincore_sf_audits';

		$component = $wpdb->get_row( $wpdb->prepare(
			"SELECT c.id, c.slug, c.display_name, c.version, c.component_type, c.status,
			        c.key_issue, c.malware,
			        a.id AS audit_id, a.audit_date, a.auditor, a.scope
			 FROM {$components_t} c
			 JOIN {$audits_t} a ON c.audit_id = a.id
			 WHERE c.content_hash = %s
			 ORDER BY c.id DESC
			 LIMIT 1",
			$hash
		), ARRAY_A );

		if ( ! $component ) {
			return null;
		}

		$findings = $wpdb->get_results( $wpdb->prepare(
			"SELECT severity, title, description, location_path, location_lines,
			        vuln_type, code_snippet, recommendation, cve, cvss_score,
			        elevated, source
			 FROM {$findings_t}
			 WHERE component_id = %d
			 ORDER BY FIELD(severity, 'critical', 'high', 'medium', 'low'), id ASC",
			$component['id']
		), ARRAY_A );

		return [
			'hash'           => $hash,
			'slug'           => $component['slug'],
			'display_name'   => $component['display_name'] ?: $component['slug'],
			'version'        => $component['version'],
			'component_type' => $component['component_type'],
			'status'         => $component['status'],
			'malware'        => (bool) $component['malware'],
			'key_issue'      => $component['key_issue'],
			'audit'          => [
				'id'      => (int) $component['audit_id'],
				'date'    => $component['audit_date'],
				'auditor' => $component['auditor'],
				'scope'   => $component['scope'],
			],
			'findings'       => $findings,
		];
	}

	protected static function resolve_entry( $hash, $manifest ) {
		if ( isset( $manifest[ $hash ] ) ) {
			return $manifest[ $hash ];
		}
		if ( in_array( $hash, self::KNOWN_SAFE_HASHES, true ) ) {
			return [ 'status' => 'clean' ];
		}
		return null;
	}

	protected static function extract_components( $plugins_input, $themes_input, $details_input ) {
		$components = [];

		$plugins = is_string( $plugins_input ) ? json_decode( $plugins_input ) : $plugins_input;
		$themes  = is_string( $themes_input )  ? json_decode( $themes_input )  : $themes_input;
		$details = is_string( $details_input ) ? json_decode( $details_input ) : $details_input;

		if ( is_array( $plugins ) ) {
			foreach ( $plugins as $p ) {
				if ( empty( $p->hash ) ) continue;
				$components[] = [
					'kind'    => 'plugin',
					'slug'    => $p->name,
					'name'    => isset( $p->title ) && $p->title !== '' ? html_entity_decode( $p->title ) : $p->name,
					'version' => $p->version ?? '',
					'hash'    => $p->hash,
				];
			}
		}

		if ( is_array( $themes ) ) {
			foreach ( $themes as $t ) {
				if ( empty( $t->hash ) ) continue;
				$components[] = [
					'kind'    => 'theme',
					'slug'    => $t->name,
					'name'    => isset( $t->title ) && $t->title !== '' ? html_entity_decode( $t->title ) : $t->name,
					'version' => $t->version ?? '',
					'hash'    => $t->hash,
				];
			}
		}

		if ( is_object( $details ) ) {
			foreach ( [ 'loose_file_hashes', 'core_file_hashes' ] as $key ) {
				if ( empty( $details->$key ) || ! is_object( $details->$key ) ) continue;
				foreach ( $details->$key as $path => $hash ) {
					if ( empty( $hash ) ) continue;
					$components[] = [
						'kind'    => 'file',
						'slug'    => $path,
						'name'    => $path,
						'version' => '',
						'hash'    => $hash,
					];
				}
			}
		}

		return $components;
	}

	protected static function lookup_hashes( $components ) {
		global $wpdb;

		$hashes = [];
		foreach ( $components as $c ) {
			if ( ! empty( $c['hash'] ) ) {
				$hashes[ $c['hash'] ] = true;
			}
		}
		$hashes = array_keys( $hashes );
		if ( empty( $hashes ) ) {
			return [];
		}

		$components_t = $wpdb->prefix . 'captaincore_sf_components';
		$findings_t   = $wpdb->prefix . 'captaincore_sf_findings';

		$placeholders = implode( ',', array_fill( 0, count( $hashes ), '%s' ) );

		$rows = $wpdb->get_results( $wpdb->prepare(
			"SELECT c.content_hash, c.slug, c.version, c.status, c.malware, c.key_issue,
			        (SELECT COUNT(*) FROM {$findings_t} f WHERE f.component_id = c.id) AS findings_count
			 FROM {$components_t} c
			 WHERE c.content_hash IN ({$placeholders})
			   AND c.content_hash IS NOT NULL
			   AND c.content_hash != ''
			 ORDER BY c.id DESC",
			...$hashes
		), ARRAY_A );

		$map = [];
		foreach ( $rows as $row ) {
			$h = $row['content_hash'];
			if ( isset( $map[ $h ] ) ) continue; // first (newest) wins
			$entry = [
				'slug'   => $row['slug'],
				'status' => $row['status'],
			];
			if ( $row['version'] ) $entry['version'] = $row['version'];
			if ( (bool) $row['malware'] ) $entry['malware'] = true;
			if ( $row['key_issue'] ) $entry['key_issue'] = $row['key_issue'];
			if ( (int) $row['findings_count'] > 0 ) $entry['findings'] = (int) $row['findings_count'];
			$map[ $h ] = $entry;
		}

		return $map;
	}

	protected static function sort_by_severity( $a, $b ) {
		$order = [
			'malware'   => 0,
			'critical'  => 1,
			'high'      => 2,
			'medium'    => 3,
			'low'       => 4,
			'clean'     => 5,
			'unaudited' => 6,
		];
		$sa = ! empty( $a['malware'] ) ? 'malware' : ( $a['status'] ?? 'unaudited' );
		$sb = ! empty( $b['malware'] ) ? 'malware' : ( $b['status'] ?? 'unaudited' );
		$ra = $order[ $sa ] ?? 99;
		$rb = $order[ $sb ] ?? 99;
		if ( $ra !== $rb ) return $ra - $rb;
		return strcmp( $a['name'] ?? '', $b['name'] ?? '' );
	}
}
