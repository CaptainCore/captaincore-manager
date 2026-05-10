<?php

namespace CaptainCore;

/**
 * Security patches adapter.
 *
 * Reads patch data directly from the WP Registry plugin's REST API via
 * RegistryClient (app-password auth, no Cloudflare worker round-trip). The
 * legacy public shape — `all()`, `get_lookup()`, `check()` — is preserved so
 * consumers (the dashboard's Vue panel at /security-patches and
 * `SecurityThreats::merge_patch_data()`) keep working unchanged.
 *
 * Write methods (`register`, `remove`) are kept for back-compat but are
 * deprecated — they still touch the legacy local table. Use WP Registry's
 * `/wp-json/registry/v1/patches` REST API for new code; the local table no
 * longer feeds the read path.
 */
class SecurityPatches {

	/**
	 * Pull the patch manifest from WP Registry (transient-cached inside
	 * RegistryClient). Returns patches keyed by `type|slug|version`.
	 */
	private static function fetch_manifest(): array {
		return RegistryClient::patches_manifest();
	}

	/**
	 * Bust the registry-side cache so the next read hits the origin.
	 * Call after register/remove for read-after-write consistency.
	 */
	public static function flush_cache(): void {
		RegistryClient::flush_cache();
	}

	/**
	 * List all patches as objects (matches the prior $wpdb->get_results shape).
	 * The Vue dashboard at /wp-json/captaincore/v1/security-patches consumes this.
	 */
	public static function all(): array {
		$patches = self::fetch_manifest();
		$out     = [];
		$id      = 0; // synthetic — manifest doesn't expose registry IDs

		foreach ( $patches as $key => $patch ) {
			$id++;
			$out[] = (object) [
				'security_patch_id' => $id,
				'slug'              => $patch['slug']            ?? '',
				'version'           => $patch['version']         ?? '',
				'type'              => $patch['type']            ?? 'plugin',
				'title'             => $patch['title']           ?? '',
				'patched_version'   => $patch['patched_version'] ?? '',
				'download_url'      => $patch['download_url']    ?? '',
				'description'       => $patch['description']     ?? '',
				'severity'          => $patch['severity']        ?? '',
				'created_at'        => $patch['created_at']      ?? null,
				'updated_at'        => $patch['updated_at']      ?? null,
			];
		}

		return $out;
	}

	/**
	 * Patches keyed by `type|slug|version` for in-process joins.
	 */
	public static function get_lookup(): array {
		$patches = self::fetch_manifest();
		$lookup  = [];

		foreach ( $patches as $key => $patch ) {
			$type = $patch['type']    ?? 'plugin';
			$slug = $patch['slug']    ?? '';
			$ver  = $patch['version'] ?? '';
			$lookup[ "{$type}|{$slug}|{$ver}" ] = [
				'patched_version' => $patch['patched_version'] ?? '',
				'download_url'    => $patch['download_url']    ?? '',
				'severity'        => $patch['severity']        ?? '',
				'description'     => $patch['description']     ?? '',
				'created_at'      => $patch['created_at']      ?? null,
			];
		}

		return $lookup;
	}

	/**
	 * Match an array of {slug, version, type} components to available patches.
	 *
	 * @param array $components
	 * @return array
	 */
	public static function check( $components ): array {
		$lookup  = self::get_lookup();
		$matches = [];

		foreach ( $components as $component ) {
			$slug    = $component['slug']    ?? '';
			$version = $component['version'] ?? '';
			$type    = $component['type']    ?? 'plugin';
			$key     = "{$type}|{$slug}|{$version}";

			if ( isset( $lookup[ $key ] ) ) {
				$matches[] = [
					'slug'            => $slug,
					'version'         => $version,
					'type'            => $type,
					'patched_version' => $lookup[ $key ]['patched_version'],
					'download_url'    => $lookup[ $key ]['download_url'],
					'severity'        => $lookup[ $key ]['severity'],
				];
			}
		}

		return $matches;
	}

	/**
	 * Deprecated. Kept so any direct callers don't fatal-error. New code should
	 * POST to https://wpregistry.io's authenticated registry/v1/patches endpoint
	 * (or use the /security-patch-deploy skill).
	 *
	 * Still writes to the legacy local table for safety; reads ignore the local
	 * table and use the public manifest.
	 *
	 * @deprecated Use WP Registry REST API for patch authoring.
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
			$patch->update( $record, [ 'security_patch_id' => $existing[0]->security_patch_id ] );
			self::flush_cache();
			return $patch->get( $existing[0]->security_patch_id );
		}

		$record['created_at'] = $time_now;
		$id = $patch->insert( $record );
		self::flush_cache();
		return $patch->get( $id );
	}

	/**
	 * @deprecated Use WP Registry REST API.
	 */
	public static function remove( $id ) {
		$patch = new SecurityPatch();
		$ok    = $patch->delete( $id );
		self::flush_cache();
		return $ok;
	}

}
