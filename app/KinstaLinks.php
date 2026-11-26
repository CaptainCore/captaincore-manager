<?php

namespace CaptainCore;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Audit and link CaptainCore Kinsta sites against live Kinsta accounts.
 *
 * Surfaces active non-stacked Kinsta sites whose provider_site_id is missing,
 * stored under the wrong Kinsta provider row, or absent from all connected
 * Kinsta accounts, and lets operators link them visually (by picking from the
 * live Kinsta site list or pasting a MyKinsta URL / site UUID) with an
 * immediate connection pull from Kinsta.
 */
class KinstaLinks {

	const UUID_REGEX = '/[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}/i';

	/**
	 * Extract Kinsta site UUID, optional environment UUID, and optional company id
	 * from a MyKinsta URL or raw UUID string.
	 *
	 * Example URL:
	 * https://my.kinsta.com/sites/details/<site_uuid>/<env_uuid>?idCompany=1577
	 *
	 * @param string $raw
	 * @return array{site_id:string,env_id:string,company_id:string}
	 */
	public static function parse_input( $raw ) {
		$raw        = trim( (string) $raw );
		$site_id    = '';
		$env_id     = '';
		$company_id = '';

		if ( preg_match_all( self::UUID_REGEX, $raw, $matches ) && ! empty( $matches[0] ) ) {
			$site_id = strtolower( $matches[0][0] );
			if ( isset( $matches[0][1] ) ) {
				$env_id = strtolower( $matches[0][1] );
			}
		}

		if ( preg_match( '/[?&]idCompany=([A-Za-z0-9_-]+)/', $raw, $cm ) ) {
			$company_id = $cm[1];
		}

		return [
			'site_id'    => $site_id,
			'env_id'     => $env_id,
			'company_id' => $company_id,
		];
	}

	/**
	 * Fetch live Kinsta site lists across all configured Kinsta provider rows.
	 *
	 * @return array{providers:array,remote_by_id:array,partial:bool}
	 */
	private static function fetch_all_remote_sites() {
		$rows = Providers::where( [ 'provider' => 'kinsta' ] );
		usort( $rows, function ( $a, $b ) {
			$ha = (int) ( $a->user_id ?? 0 ) === 0 ? 0 : 1;
			$hb = (int) ( $b->user_id ?? 0 ) === 0 ? 0 : 1;
			if ( $ha !== $hb ) {
				return $ha - $hb;
			}
			return (int) $a->provider_id - (int) $b->provider_id;
		} );

		$providers    = [];
		$remote_by_id = [];
		$partial      = false;

		foreach ( $rows as $row ) {
			$pid        = (int) $row->provider_id;
			$api_key    = Providers\Kinsta::credentials( 'api', $pid );
			$company_id = (string) Providers\Kinsta::credentials( 'company_id', $pid );
			$info       = [
				'provider_id' => $pid,
				'name'        => (string) $row->name,
				'company_id'  => $company_id,
				'house'       => (int) ( $row->user_id ?? 0 ) === 0,
				'site_count'  => 0,
				'ok'          => false,
				'error'       => '',
			];

			if ( empty( $api_key ) || empty( $company_id ) ) {
				$info['error'] = 'Missing API key or company_id';
				$partial       = true;
				$providers[]   = $info;
				continue;
			}

			Remote\Kinsta::setApiKey( $api_key );
			$response = Remote\Kinsta::get( "sites?company={$company_id}" );
			if ( empty( $response ) || ! isset( $response->company->sites ) || ! is_array( $response->company->sites ) ) {
				$info['error'] = is_object( $response ) && ! empty( $response->message )
					? (string) $response->message
					: 'Could not fetch sites from Kinsta';
				$partial       = true;
				$providers[]   = $info;
				continue;
			}

			$info['ok']         = true;
			$info['site_count'] = count( $response->company->sites );
			$providers[]        = $info;

			foreach ( $response->company->sites as $ks ) {
				$rid = strtolower( (string) ( $ks->id ?? '' ) );
				if ( $rid === '' ) {
					continue;
				}
				$remote_by_id[ $rid ] = [
					'remote_id'     => $rid,
					'name'          => (string) ( $ks->display_name ?? $ks->name ?? '' ),
					'slug'          => (string) ( $ks->name ?? '' ),
					'status'        => (string) ( $ks->status ?? 'live' ),
					'provider_id'   => $pid,
					'provider_name' => (string) $row->name,
				];
			}
		}

		return [
			'providers'    => $providers,
			'remote_by_id' => $remote_by_id,
			'partial'      => $partial,
		];
	}

	/**
	 * Load active non-stacked Kinsta sites alongside their Production environment.
	 *
	 * @return array
	 */
	private static function active_kinsta_sites() {
		global $wpdb;
		$sites_table = $wpdb->base_prefix . 'captaincore_sites';
		$envs_table  = $wpdb->base_prefix . 'captaincore_environments';

		$rows = $wpdb->get_results(
			"SELECT s.site_id, s.name, s.site, s.provider_id, s.provider_site_id, s.details,
			        e.username AS prod_username, e.address AS prod_address, e.port AS prod_port
			 FROM {$sites_table} s
			 LEFT JOIN {$envs_table} e
			   ON e.site_id = s.site_id AND e.environment = 'Production'
			 WHERE s.status = 'active' AND s.provider = 'kinsta'
			 ORDER BY s.name ASC"
		);

		$out = [];
		foreach ( (array) $rows as $row ) {
			$details = json_decode( (string) $row->details, true );
			if ( ! is_array( $details ) ) {
				$details = [];
			}
			// Skip stacked/multi-tenant sites and soft-removed sites.
			if ( ! empty( $details['environment_vars'] ) || ! empty( $details['removed'] ) ) {
				continue;
			}
			$row->_details = $details;
			$out[]         = $row;
		}
		return $out;
	}

	/**
	 * Audit all active Kinsta sites against live Kinsta accounts.
	 *
	 * @return array
	 */
	public static function audit() {
		$fetched      = self::fetch_all_remote_sites();
		$providers    = $fetched['providers'];
		$remote_by_id = $fetched['remote_by_id'];
		$partial      = $fetched['partial'];

		$prov_names = [];
		foreach ( $providers as $p ) {
			$prov_names[ $p['provider_id'] ] = $p['name'];
		}

		$local_sites = self::active_kinsta_sites();

		// Build claimed map (remote_id => local site) and index remote sites by SFTP username (slug).
		$claimed = [];
		foreach ( $local_sites as $s ) {
			$rid = strtolower( trim( (string) ( $s->provider_site_id ?? '' ) ) );
			if ( $rid !== '' && isset( $remote_by_id[ $rid ] ) && ! isset( $claimed[ $rid ] ) ) {
				$claimed[ $rid ] = [
					'site_id' => (int) $s->site_id,
					'name'    => (string) $s->name,
				];
			}
		}

		$unclaimed_by_slug = [];
		foreach ( $remote_by_id as $rid => $remote ) {
			if ( isset( $claimed[ $rid ] ) ) {
				continue;
			}
			$slug = strtolower( trim( (string) $remote['slug'] ) );
			if ( $slug !== '' ) {
				$unclaimed_by_slug[ $slug ][] = $remote;
			}
		}

		$summary = [
			'total'            => count( $local_sites ),
			'valid'            => 0,
			'missing'          => 0,
			'wrong_account'    => 0,
			'invalid'          => 0,
			'unchecked'        => 0,
			'unclaimed_remote' => count( $remote_by_id ) - count( $claimed ),
		];

		$issues = [];
		foreach ( $local_sites as $s ) {
			$rid     = strtolower( trim( (string) ( $s->provider_site_id ?? '' ) ) );
			$pid     = (int) ( $s->provider_id ?? 0 );
			$details = $s->_details;

			$issue  = '';
			$actual = null;

			if ( $rid === '' ) {
				$issue = 'missing';
				$summary['missing']++;
			} elseif ( isset( $remote_by_id[ $rid ] ) ) {
				$remote = $remote_by_id[ $rid ];
				if ( (int) $remote['provider_id'] !== $pid ) {
					$issue  = 'wrong_account';
					$actual = $remote;
					$summary['wrong_account']++;
				} else {
					$summary['valid']++;
					continue;
				}
			} elseif ( $partial ) {
				$summary['unchecked']++;
				continue;
			} else {
				$issue = 'invalid';
				$summary['invalid']++;
			}

			// Optional visual suggestion: only when an UNCLAIMED Kinsta site's immutable
			// SFTP username (`slug`) uniquely matches this site's Production SFTP username.
			$suggestion = null;
			if ( $issue === 'missing' || $issue === 'invalid' ) {
				$prod_user = strtolower( trim( (string) ( $s->prod_username ?? '' ) ) );
				if ( $prod_user !== '' && ! empty( $unclaimed_by_slug[ $prod_user ] ) && count( $unclaimed_by_slug[ $prod_user ] ) === 1 ) {
					$suggestion = $unclaimed_by_slug[ $prod_user ][0];
				}
			}

			$issues[] = [
				'site_id'              => (int) $s->site_id,
				'name'                 => (string) $s->name,
				'site'                 => (string) $s->site,
				'provider_id'          => $pid,
				'provider_name'        => $prov_names[ $pid ] ?? '',
				'provider_site_id'     => $rid,
				'issue'                => $issue,
				'prod_username'        => (string) ( $s->prod_username ?? '' ),
				'prod_address'         => (string) ( $s->prod_address ?? '' ),
				'prod_port'            => (string) ( $s->prod_port ?? '' ),
				'connection_error'     => (string) ( $details['connection_errors'] ?? '' ),
				'actual_provider_id'   => $actual ? (int) $actual['provider_id'] : null,
				'actual_provider_name' => $actual ? (string) $actual['provider_name'] : '',
				'actual_remote'        => $actual,
				'suggestion'           => $suggestion,
			];
		}

		// Order: missing first, then wrong_account, then invalid; alphabetical by name inside each.
		$rank = [ 'missing' => 0, 'wrong_account' => 1, 'invalid' => 2 ];
		usort( $issues, function ( $a, $b ) use ( $rank ) {
			$ra = $rank[ $a['issue'] ] ?? 9;
			$rb = $rank[ $b['issue'] ] ?? 9;
			if ( $ra !== $rb ) {
				return $ra - $rb;
			}
			return strcasecmp( $a['name'], $b['name'] );
		} );

		// Build remote_sites list for the visual picker (unclaimed first, then alphabetical).
		$remote_sites = [];
		foreach ( $remote_by_id as $rid => $remote ) {
			$claim          = $claimed[ $rid ] ?? null;
			$remote_sites[] = [
				'remote_id'        => $rid,
				'name'             => $remote['name'],
				'slug'             => $remote['slug'],
				'status'           => $remote['status'],
				'provider_id'      => $remote['provider_id'],
				'provider_name'    => $remote['provider_name'],
				'linked_site_id'   => $claim ? $claim['site_id'] : 0,
				'linked_site_name' => $claim ? $claim['name'] : '',
			];
		}
		usort( $remote_sites, function ( $a, $b ) {
			$ca = $a['linked_site_id'] ? 1 : 0;
			$cb = $b['linked_site_id'] ? 1 : 0;
			if ( $ca !== $cb ) {
				return $ca - $cb;
			}
			return strcasecmp( $a['name'] ?: $a['slug'], $b['name'] ?: $b['slug'] );
		} );

		return [
			'checked_at'   => gmdate( 'c' ),
			'partial'      => $partial,
			'providers'    => $providers,
			'summary'      => $summary,
			'sites'        => $issues,
			'remote_sites' => $remote_sites,
		];
	}

	/**
	 * Link (or unlink) one CaptainCore site to a Kinsta site ID / MyKinsta URL
	 * and optionally pull fresh connection details from Kinsta right away.
	 *
	 * @param int   $site_id
	 * @param array $params
	 * @return array|\WP_Error
	 */
	public static function link_site( $site_id, $params = [] ) {
		$site_id = (int) $site_id;
		$current = Sites::get( $site_id );
		if ( empty( $current ) ) {
			return new \WP_Error( 'not_found', 'Site not found.', [ 'status' => 404 ] );
		}

		$customer = ! empty( $current->customer_id ) ? $current->customer_id : null;

		if ( ! empty( $params['clear'] ) ) {
			( new Sites )->update(
				[
					'provider_site_id' => null,
					'updated_at'       => date( 'Y-m-d H:i:s' ),
				],
				[ 'site_id' => $site_id ]
			);
			ActivityLog::log( 'updated', 'site', $site_id, $current->name, "Cleared Kinsta site link for {$current->name}", [], $customer );
			Providers\Kinsta::push_site_to_cli( $site_id );
			return [
				'cleared'          => true,
				'provider_site_id' => '',
				'site'             => ( new Site( $site_id ) )->fetch(),
			];
		}

		$raw_input  = isset( $params['input'] ) ? (string) $params['input'] : '';
		$parsed     = self::parse_input( $raw_input !== '' ? $raw_input : (string) ( $params['provider_site_id'] ?? '' ) );
		$kinsta_id  = $parsed['site_id'];
		$env_hint   = ! empty( $params['kinsta_env_id'] ) ? strtolower( trim( (string) $params['kinsta_env_id'] ) ) : $parsed['env_id'];
		$company_id = $parsed['company_id'];

		if ( $kinsta_id === '' ) {
			return new \WP_Error( 'invalid_kinsta_id', 'Paste a MyKinsta site URL or a valid Kinsta site UUID.', [ 'status' => 400 ] );
		}

		$force = ! empty( $params['force'] );
		if ( ! $force ) {
			$existing = Sites::where( [ 'provider_site_id' => $kinsta_id, 'status' => 'active' ] );
			foreach ( (array) $existing as $other ) {
				if ( (int) $other->site_id === $site_id ) {
					continue;
				}
				$od = json_decode( (string) $other->details, true );
				if ( ! empty( $od['environment_vars'] ) || ! empty( $od['removed'] ) ) {
					continue;
				}
				return new \WP_Error(
					'already_linked',
					"That Kinsta site is already linked to {$other->name} (#{$other->site_id}).",
					[
						'status'             => 409,
						'conflict_site_id'   => (int) $other->site_id,
						'conflict_site_name' => (string) $other->name,
					]
				);
			}
		}

		// Order candidate Kinsta provider rows: explicit provider_id, URL idCompany match,
		// site's current provider_id, then every other Kinsta provider row.
		$all_kinsta  = Providers::where( [ 'provider' => 'kinsta' ] );
		$by_id       = [];
		$candidates  = [];
		$add_cand    = function ( $pid ) use ( &$candidates, &$by_id ) {
			$pid = (int) $pid;
			if ( $pid > 0 && isset( $by_id[ $pid ] ) && ! in_array( $pid, $candidates, true ) ) {
				$candidates[] = $pid;
			}
		};
		foreach ( $all_kinsta as $row ) {
			$by_id[ (int) $row->provider_id ] = $row;
		}
		if ( ! empty( $params['provider_id'] ) ) {
			$add_cand( (int) $params['provider_id'] );
		}
		if ( $company_id !== '' ) {
			foreach ( $all_kinsta as $row ) {
				if ( (string) Providers\Kinsta::credentials( 'company_id', (int) $row->provider_id ) === $company_id ) {
					$add_cand( (int) $row->provider_id );
				}
			}
		}
		if ( ! empty( $current->provider_id ) ) {
			$add_cand( (int) $current->provider_id );
		}
		foreach ( $all_kinsta as $row ) {
			$add_cand( (int) $row->provider_id );
		}

		$matched_provider = null;
		$remote_site      = null;
		foreach ( $candidates as $pid ) {
			$api_key = Providers\Kinsta::credentials( 'api', $pid );
			if ( empty( $api_key ) ) {
				continue;
			}
			Remote\Kinsta::setApiKey( $api_key );
			$found = Remote\Kinsta::get( "sites/{$kinsta_id}" );
			if ( ! empty( $found->site ) ) {
				$matched_provider = $by_id[ $pid ];
				$remote_site      = $found->site;
				break;
			}
		}

		if ( ! $matched_provider || ! $remote_site ) {
			return new \WP_Error(
				'kinsta_site_not_found',
				"None of the configured Kinsta accounts can see site {$kinsta_id}.",
				[ 'status' => 400 ]
			);
		}

		$matched_pid  = (int) $matched_provider->provider_id;
		$matched_name = (string) $matched_provider->name;

		( new Sites )->update(
			[
				'provider'         => 'kinsta',
				'provider_id'      => $matched_pid,
				'provider_site_id' => $kinsta_id,
				'updated_at'       => date( 'Y-m-d H:i:s' ),
			],
			[ 'site_id' => $site_id ]
		);

		ActivityLog::log(
			'updated',
			'site',
			$site_id,
			$current->name,
			"Linked Kinsta site {$kinsta_id} ({$matched_name})",
			[],
			$customer
		);

		// Record the live environment link on Production when available.
		$prod_env = Environments::for_site( $site_id, 'Production' );
		if ( $prod_env && ! empty( $remote_site->environments ) && is_array( $remote_site->environments ) ) {
			$live_id = '';
			foreach ( $remote_site->environments as $kenv ) {
				$kid = strtolower( (string) ( $kenv->id ?? '' ) );
				if ( $env_hint !== '' && $kid === $env_hint && strtolower( (string) ( $kenv->name ?? '' ) ) === 'live' ) {
					$live_id = $kid;
					break;
				}
				if ( $live_id === '' && strtolower( (string) ( $kenv->name ?? '' ) ) === 'live' ) {
					$live_id = $kid;
				}
			}
			if ( $live_id !== '' ) {
				Providers\Kinsta::remember_link( $prod_env, $live_id );
			}
		}

		$pull        = ! array_key_exists( 'pull', $params ) || filter_var( $params['pull'], FILTER_VALIDATE_BOOLEAN );
		$remote_sync = null;
		if ( $pull ) {
			$remote_sync = ( new Site( $site_id ) )->remote_sync();
			Sites::update_environments_cache( $site_id );
			// remote_sync() pushes to CLI only when fields changed; ensure the new
			// provider link is pushed even when address/port already matched.
			if ( empty( $remote_sync['changes'] ) ) {
				Providers\Kinsta::push_site_to_cli( $site_id );
			}
		} else {
			Providers\Kinsta::push_site_to_cli( $site_id );
		}

		$site_obj = new Site( $site_id );
		return [
			'provider_id'      => $matched_pid,
			'provider_name'    => $matched_name,
			'provider_site_id' => $kinsta_id,
			'remote_site'      => [
				'id'           => (string) ( $remote_site->id ?? $kinsta_id ),
				'name'         => (string) ( $remote_site->name ?? '' ),
				'display_name' => (string) ( $remote_site->display_name ?? '' ),
			],
			'remote_sync'      => $remote_sync,
			'site'             => $site_obj->fetch(),
			'environments'     => $site_obj->environments(),
		];
	}

	/**
	 * Move every active Kinsta site whose provider_site_id is confirmed live in a
	 * different connected Kinsta account over to that account.
	 *
	 * @return array
	 */
	public static function fix_accounts() {
		$fetched      = self::fetch_all_remote_sites();
		$providers    = $fetched['providers'];
		$remote_by_id = $fetched['remote_by_id'];

		$prov_names = [];
		foreach ( $providers as $p ) {
			$prov_names[ $p['provider_id'] ] = $p['name'];
		}

		$fixed = [];
		foreach ( self::active_kinsta_sites() as $s ) {
			$rid = strtolower( trim( (string) ( $s->provider_site_id ?? '' ) ) );
			if ( $rid === '' || ! isset( $remote_by_id[ $rid ] ) ) {
				continue;
			}
			$remote  = $remote_by_id[ $rid ];
			$cur_pid = (int) ( $s->provider_id ?? 0 );
			$new_pid = (int) $remote['provider_id'];
			if ( $cur_pid === $new_pid ) {
				continue;
			}

			( new Sites )->update(
				[
					'provider_id' => $new_pid,
					'updated_at'  => date( 'Y-m-d H:i:s' ),
				],
				[ 'site_id' => (int) $s->site_id ]
			);

			$from = $prov_names[ $cur_pid ] ?? '';
			$to   = $remote['provider_name'];
			ActivityLog::log(
				'updated',
				'site',
				(int) $s->site_id,
				$s->name,
				"Moved {$s->name} to the {$to} Kinsta account" . ( $from !== '' ? " from {$from}" : '' )
			);
			Providers\Kinsta::push_site_to_cli( (int) $s->site_id );

			$fixed[] = [
				'site_id'       => (int) $s->site_id,
				'name'          => (string) $s->name,
				'provider_id'   => $new_pid,
				'provider_name' => $to,
			];
		}

		return [
			'fixed' => count( $fixed ),
			'sites' => $fixed,
		];
	}
}
