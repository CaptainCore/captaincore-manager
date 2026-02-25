<?php

namespace CaptainCore;

class SecurityPatches {

	/**
	 * Register (upsert) a security patch record.
	 *
	 * @param array $data {slug, version, type, title, patched_version, download_url, description, severity}
	 * @return object The created or updated record.
	 */
	public static function register( $data ) {
		$patch    = new SecurityPatch();
		$time_now = date( 'Y-m-d H:i:s' );

		$existing = $patch->where( [
			'slug'    => $data['slug'],
			'version' => $data['version'],
			'type'    => $data['type'] ?? 'plugin',
		] );

		$record = [
			'slug'            => $data['slug'],
			'version'         => $data['version'],
			'type'            => $data['type'] ?? 'plugin',
			'title'           => $data['title'] ?? '',
			'patched_version' => $data['patched_version'],
			'download_url'    => $data['download_url'],
			'description'     => $data['description'] ?? '',
			'severity'        => $data['severity'] ?? '',
			'updated_at'      => $time_now,
		];

		if ( ! empty( $existing ) ) {
			$patch->update(
				$record,
				[ 'security_patch_id' => $existing[0]->security_patch_id ]
			);
			return $patch->get( $existing[0]->security_patch_id );
		}

		$record['created_at'] = $time_now;
		$id = $patch->insert( $record );
		return $patch->get( $id );
	}

	/**
	 * List all patches, newest first.
	 *
	 * @return array
	 */
	public static function all() {
		return SecurityPatch::all();
	}

	/**
	 * Delete a patch by ID.
	 *
	 * @param int $id
	 * @return bool
	 */
	public static function remove( $id ) {
		$patch = new SecurityPatch();
		return $patch->delete( $id );
	}

	/**
	 * Check an array of components for available patches.
	 *
	 * @param array $components Array of {slug, version, type}.
	 * @return array Matching patches with download URLs.
	 */
	public static function check( $components ) {
		$lookup  = self::get_lookup();
		$matches = [];

		foreach ( $components as $component ) {
			$slug    = $component['slug'] ?? '';
			$version = $component['version'] ?? '';
			$type    = $component['type'] ?? 'plugin';
			$key     = "{$type}|{$slug}|{$version}";

			if ( isset( $lookup[ $key ] ) ) {
				$patch     = $lookup[ $key ];
				$matches[] = [
					'slug'            => $slug,
					'version'         => $version,
					'type'            => $type,
					'title'           => $patch['title'],
					'patched_version' => $patch['patched_version'],
					'download_url'    => $patch['download_url'],
					'severity'        => $patch['severity'],
				];
			}
		}

		return $matches;
	}

	/**
	 * Return all patches keyed by "type|slug|version" for merging into threat data.
	 *
	 * @return array
	 */
	public static function get_lookup() {
		$records = self::all();
		$lookup  = [];

		foreach ( $records as $record ) {
			$key = "{$record->type}|{$record->slug}|{$record->version}";
			$lookup[ $key ] = [
				'patched_version' => $record->patched_version,
				'download_url'    => $record->download_url,
				'severity'        => $record->severity,
				'description'     => $record->description,
				'created_at'      => $record->created_at,
			];
		}

		return $lookup;
	}

}
