<?php

namespace CaptainCore;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Premium plugin license signals. fetch-site-data reports each licensed plugin's
 * stored state (`licenses` in the environment details); failing entries become
 * `license` signals, and a change of site address becomes a `domain-change`
 * signal listing the licenses to re-check. Stateless, like SessionAnomalyDetector.
 */
class Licenses {

	const NAMES = [
		'elementor-pro'              => 'Elementor Pro',
		'advanced-custom-fields-pro' => 'ACF Pro',
		'perfmatters'                => 'Perfmatters',
		'crocoblock'                 => 'Crocoblock (JetEngine)',
	];

	// What fixes each state, for whoever picks the signal up.
	const FIXES = [
		'elementor-pro:site_inactive'           => 'The key was activated for another address. Run `wp elementor-pro license deactivate` then `wp elementor-pro license activate <key>` with the key already stored in elementor_pro_license_key.',
		'advanced-custom-fields-pro:url_mismatch' => 'The key was activated for another address. Reactivate the stored key from ACF > Updates (or ACF_PRO_LICENSE in wp-config).',
		'crocoblock:url_mismatch'               => 'The key was activated for another address. Activate it for this site_url through the Crocoblock API (see the fleet-management-fix-plugin license adapters).',
		'perfmatters:site_inactive'             => 'The key is stored but not active for this site. Call perfmatters_activate_license( <key> ) from wp eval.',
	];

	/**
	 * Comparable form of a site address: no protocol, leading www. or trailing slash.
	 */
	public static function address( $url ) {
		$url = strtolower( preg_replace( '#^https?://#i', '', trim( (string) $url ) ) );
		if ( strpos( $url, 'www.' ) === 0 ) {
			$url = substr( $url, 4 );
		}
		return rtrim( $url, '/' );
	}

	public static function name( $plugin ) {
		return self::NAMES[ $plugin ] ?? $plugin;
	}

	/**
	 * Failing licenses as findings for MalwareFindings::sync_state(). Entries whose
	 * state is unknown (ok null) report nothing either way.
	 */
	public static function to_findings( $licenses ) {
		$out = [];
		foreach ( (array) $licenses as $l ) {
			$l      = (array) $l;
			$plugin = preg_replace( '/[^a-z0-9_.-]/', '', strtolower( (string) ( $l['plugin'] ?? '' ) ) );
			$status = preg_replace( '/[^a-z0-9_-]/', '', strtolower( (string) ( $l['status'] ?? '' ) ) );
			if ( $plugin === '' || $status === '' || ( $l['ok'] ?? null ) !== false ) {
				continue;
			}
			$name  = self::name( $plugin );
			$facts = [ "status={$status}" ];
			foreach ( [ 'key', 'activated_for', 'expires', 'cached_until', 'new_version', 'checked' ] as $k ) {
				if ( ! empty( $l[ $k ] ) && is_scalar( $l[ $k ] ) ) {
					$facts[] = "{$k}=" . $l[ $k ];
				}
			}
			if ( $status === 'no_package' ) {
				$summary = "{$name} has an update to " . ( $l['new_version'] ?? 'a newer version' ) . ' with no download package, which usually means it is not licensed on this site.';
			} else {
				$summary = "{$name} reports its license as {$status}.";
			}
			$fix   = self::FIXES[ "{$plugin}:{$status}" ] ?? '';
			$out[] = (object) [
				'filename'              => "plugin:{$plugin}",
				'signature_id'          => 'license-' . str_replace( '_', '-', $status ),
				'signature_name'        => $status === 'no_package' ? "{$name}: update needs a license" : "{$name} license: " . str_replace( '_', ' ', $status ),
				'signature_description' => trim( $summary . ' ' . $fix ),
				'matched_text'          => implode( ', ', $facts ),
				'severity'              => $status === 'no_package' ? 'low' : 'medium',
				'content_hash'          => hash( 'sha256', $plugin . '|' . $status . '|' . ( $l['activated_for'] ?? '' ) ),
			];
		}
		return $out;
	}

	/**
	 * The domain-change finding, listing the licensed plugins the site reported.
	 * Elementor Pro's status is a cache from before the move until an administrator
	 * loads wp-admin, so a "valid" there still needs a fresh check.
	 */
	public static function domain_change_finding( $from, $to, $licenses ) {
		$listed = [];
		foreach ( (array) $licenses as $l ) {
			$l = (array) $l;
			if ( ( $l['status'] ?? '' ) === 'no_package' ) {
				continue;
			}
			$listed[] = self::name( (string) ( $l['plugin'] ?? '' ) ) . ' (' . ( $l['status'] ?? 'unknown' ) . ')';
		}
		$description = "The site address changed from {$from} to {$to}. Licenses activated for the old address stop validating.";
		$description .= $listed
			? ' Licensed plugins reported: ' . implode( ', ', $listed ) . '. Re-check each against the new address; Elementor Pro needs `wp eval "ElementorPro\\License\\API::get_license_data( true );"` because its stored status predates the move.'
			: ' No licensed plugins with a known adapter were reported; check any premium plugins by hand.';
		return (object) [
			'filename'              => 'home_url',
			'signature_id'          => 'domain-change',
			'signature_name'        => 'Site address changed',
			'signature_description' => $description,
			'matched_text'          => self::address( $from ) . ' -> ' . self::address( $to ),
			'severity'              => 'medium',
			'content_hash'          => hash( 'sha256', self::address( $from ) . '|' . self::address( $to ) ),
		];
	}
}
