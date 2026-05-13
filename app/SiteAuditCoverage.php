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
		if ( ! RegistryClient::ready() ) {
			return null;
		}

		$detail = RegistryClient::findings_by_hash( $hash );
		if ( ! is_array( $detail ) || empty( $detail['audited'] ) ) {
			return null;
		}

		// The registry returns an `audits` array (newest first). Project the
		// newest entry as the legacy singular `audit` so the findings dialog
		// renders unchanged.
		$audits = isset( $detail['audits'] ) && is_array( $detail['audits'] ) ? $detail['audits'] : [];
		$first  = $audits[0] ?? null;

		return [
			'hash'           => $detail['hash'] ?? $hash,
			'slug'           => $detail['slug'] ?? '',
			'display_name'   => $detail['display_name'] ?: ( $detail['slug'] ?? '' ),
			'version'        => $detail['version'] ?? '',
			'component_type' => $detail['component_type'] ?? '',
			'status'         => $detail['status'] ?? 'clean',
			'malware'        => ! empty( $detail['malware'] ),
			'key_issue'      => $detail['key_issue'] ?? '',
			'audit'          => $first ? [
				'id'      => (int) ( $first['audit_id'] ?? 0 ),
				'date'    => $first['audit_date'] ?? '',
				'auditor' => $first['auditor'] ?? '',
				'scope'   => $first['scope'] ?? '',
			] : null,
			'findings'       => isset( $detail['findings'] ) && is_array( $detail['findings'] ) ? $detail['findings'] : [],
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
		if ( empty( $components ) || ! RegistryClient::ready() ) {
			return [];
		}

		$needed = [];
		foreach ( $components as $c ) {
			if ( ! empty( $c['hash'] ) ) {
				$needed[ $c['hash'] ] = true;
			}
		}
		if ( empty( $needed ) ) {
			return [];
		}

		// Source of truth is findings.wpregistry.io. The registry partitions audits
		// by component_type, but a hash recorded on anchor.host as a "plugin"
		// can be audited on the registry as a "mu-plugin" (WP-CLI surfaces
		// mu-plugins inside the plugins array). Look across all four manifests
		// so cross-type matches don't appear as false-negative unaudited rows.
		$map = [];
		foreach ( [ 'plugins', 'themes', 'mu-plugins', 'files' ] as $endpoint ) {
			$manifest = RegistryClient::manifest( $endpoint );
			foreach ( $manifest as $hash => $entry ) {
				if ( isset( $needed[ $hash ] ) && ! isset( $map[ $hash ] ) ) {
					$map[ $hash ] = (array) $entry;
				}
			}
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
