<?php

namespace CaptainCore;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Fleet core-update probe/apply runs: one parent row plus per-site results.
 * Source of truth for agents. Email is a short recap only.
 */
class CoreUpdateRun {

	protected $core_update_run_id = 0;

	public function __construct( $core_update_run_id = 0 ) {
		$this->core_update_run_id = (int) $core_update_run_id;
	}

	public static function classify( $stage, $reason, $excerpt = '' ) {
		$blob  = strtolower( (string) $stage . ' ' . (string) $reason . ' ' . (string) $excerpt );
		$stage = strtolower( (string) $stage );

		if ( strpos( $blob, 'allowed memory size' ) !== false || $stage === 'memory' ) {
			return 'memory';
		}
		if ( strpos( $blob, 'not a wordpress root' ) !== false || $stage === 'root' ) {
			return 'not-wp-root';
		}
		if ( strpos( $blob, 'wp_widget::__construct' ) !== false || strpos( $blob, 'too few arguments to function wp_widget' ) !== false ) {
			return 'widget-factory';
		}
		if ( strpos( $blob, 'unknown named parameter' ) !== false ) {
			return 'named-parameter';
		}
		if ( strpos( $blob, 'must be compatible with' ) !== false ) {
			return 'signature-mismatch';
		}
		if ( strpos( $blob, 'failed opening required' ) !== false && strpos( $blob, 'captaincore-core-preview' ) !== false && strpos( $blob, 'wp-content/themes' ) !== false ) {
			return 'theme-abspath-require';
		}
		if ( strpos( $blob, 'undefined constant' ) !== false ) {
			return 'undefined-constant';
		}
		if ( $stage === 'http' || strpos( $blob, 'preview http' ) !== false ) {
			return 'http';
		}
		if ( $stage === 'version' ) {
			return 'version';
		}
		if ( $stage === 'ssh' ) {
			return 'ssh';
		}
		if (
			strpos( $blob, 'typeerror' ) !== false
			|| strpos( $blob, 'must be of type' ) !== false
			|| strpos( $blob, 'on null' ) !== false
			|| strpos( $blob, 'call to undefined' ) !== false
			|| strpos( $blob, 'call to a member' ) !== false
			|| strpos( $blob, 'argumentcounterror' ) !== false
			|| strpos( $blob, 'uncaught' ) !== false
		) {
			return 'php-fatal';
		}
		if ( $stage === 'boot' ) {
			return 'boot';
		}
		if ( $stage === 'render' ) {
			return 'render';
		}
		return $stage !== '' ? $stage : 'other';
	}

	public static function store( $data ) {
		$now     = gmdate( 'Y-m-d H:i:s' );
		$results = isset( $data['results'] ) && is_array( $data['results'] ) ? $data['results'] : [];
		$counts  = isset( $data['counts'] ) && is_array( $data['counts'] ) ? $data['counts'] : [];

		$run_id = ( new CoreUpdateRuns() )->insert(
			[
				'target'             => substr( (string) ( $data['target'] ?? '' ), 0, 255 ),
				'flags'              => (string) ( $data['flags'] ?? '' ),
				'version_requested'  => substr( (string) ( $data['version_requested'] ?? '' ), 0, 50 ),
				'version_resolved'   => substr( (string) ( $data['version_resolved'] ?? '' ), 0, 50 ),
				'parallel'           => (int) ( $data['parallel'] ?? 0 ),
				'duration_seconds'   => (int) ( $data['duration_seconds'] ?? 0 ),
				'total'              => (int) ( $counts['total'] ?? count( $results ) ),
				'updated_count'      => (int) ( $counts['updated'] ?? 0 ),
				'skipped_count'      => (int) ( $counts['skipped'] ?? 0 ),
				'failed_count'       => (int) ( $counts['failed'] ?? 0 ),
				'probed_count'       => (int) ( $counts['probed'] ?? 0 ),
				'status'             => 'completed',
				'created_at'         => $now,
			]
		);

		$inserted = 0;
		foreach ( $results as $row ) {
			if ( ! is_array( $row ) ) {
				$row = (array) $row;
			}
			$site_key = (string) ( $row['site'] ?? '' );
			$ids      = self::resolve_site_env( $site_key );
			$stage    = substr( (string) ( $row['stage'] ?? '' ), 0, 40 );
			$reason   = (string) ( $row['reason'] ?? '' );
			$excerpt  = (string) ( $row['excerpt'] ?? '' );
			if ( strlen( $excerpt ) > 4000 ) {
				$excerpt = substr( $excerpt, 0, 4000 );
			}
			if ( strlen( $reason ) > 2000 ) {
				$reason = substr( $reason, 0, 2000 );
			}
			( new CoreUpdateResults() )->insert(
				[
					'core_update_run_id' => $run_id,
					'site'               => substr( $site_key, 0, 191 ),
					'site_id'            => $ids['site_id'],
					'environment_id'     => $ids['environment_id'],
					'home_url'           => substr( (string) ( $row['url'] ?? $row['home_url'] ?? '' ), 0, 500 ),
					'result'             => substr( (string) ( $row['result'] ?? '' ), 0, 20 ),
					'action'             => substr( (string) ( $row['action'] ?? '' ), 0, 20 ),
					'stage'              => $stage,
					'core_before'        => substr( (string) ( $row['core_before'] ?? $row['from'] ?? '' ), 0, 50 ),
					'core_after'         => substr( (string) ( $row['core_after'] ?? $row['to'] ?? '' ), 0, 50 ),
					'reason'             => $reason,
					'excerpt'            => $excerpt,
					'exit_code'          => (int) ( $row['exit_code'] ?? 0 ),
					'error_class'        => ( ( $row['result'] ?? '' ) === 'fail' ) ? self::classify( $stage, $reason, $excerpt ) : '',
					'status'             => 'open',
					'notes'              => '',
					'created_at'         => $now,
					'updated_at'         => $now,
				]
			);
			$inserted++;
		}

		return [
			'run_id'   => (int) $run_id,
			'inserted' => $inserted,
		];
	}

	public static function resolve_site_env( $site_key ) {
		$out = [ 'site_id' => 0, 'environment_id' => 0 ];
		if ( ! preg_match( '/^(.*)-(production|staging)$/i', (string) $site_key, $m ) ) {
			return $out;
		}
		$name = $m[1];
		$env  = ucfirst( strtolower( $m[2] ) );
		$sites = ( new Sites() )->where( [ 'site' => $name ] );
		if ( empty( $sites[0]->site_id ) ) {
			return $out;
		}
		$out['site_id'] = (int) $sites[0]->site_id;
		$envs = ( new Environments() )->where(
			[
				'site_id'     => $out['site_id'],
				'environment' => $env,
			]
		);
		if ( ! empty( $envs[0]->environment_id ) ) {
			$out['environment_id'] = (int) $envs[0]->environment_id;
		}
		return $out;
	}

	public function get( $with_results = false ) {
		$run = ( new CoreUpdateRuns() )->get( $this->core_update_run_id );
		if ( ! $run ) {
			return null;
		}
		$run->groups = $this->groups();
		if ( $with_results ) {
			$run->results = $this->results();
		}
		return $run;
	}

	public function results( $filters = [] ) {
		$conditions = [ 'core_update_run_id' => $this->core_update_run_id ];
		foreach ( [ 'result', 'stage', 'error_class', 'status', 'action' ] as $key ) {
			if ( ! empty( $filters[ $key ] ) ) {
				$conditions[ $key ] = $filters[ $key ];
			}
		}
		return ( new CoreUpdateResults() )->where( $conditions );
	}

	public function groups() {
		global $wpdb;
		$table = $wpdb->prefix . 'captaincore_core_update_results';
		$sql   = $wpdb->prepare(
			"SELECT error_class, result, COUNT(*) AS n
			 FROM {$table}
			 WHERE core_update_run_id = %d
			 GROUP BY error_class, result
			 ORDER BY n DESC",
			$this->core_update_run_id
		);
		return $wpdb->get_results( $sql );
	}

	public static function rest_list( $request ) {
		global $wpdb;
		$limit = min( 50, max( 1, (int) $request->get_param( 'per_page' ) ?: 20 ) );
		$table = $wpdb->prefix . 'captaincore_core_update_runs';
		return $wpdb->get_results( $wpdb->prepare(
			"SELECT * FROM {$table} ORDER BY core_update_run_id DESC LIMIT %d",
			$limit
		) );
	}

	public static function rest_get( $request ) {
		$run = ( new self( (int) $request['id'] ) )->get( false );
		if ( ! $run ) {
			return new \WP_Error( 'not_found', 'Core update run not found', [ 'status' => 404 ] );
		}
		return $run;
	}

	public static function rest_results( $request ) {
		$obj = new self( (int) $request['id'] );
		if ( ! ( new CoreUpdateRuns() )->get( $obj->core_update_run_id ) ) {
			return new \WP_Error( 'not_found', 'Core update run not found', [ 'status' => 404 ] );
		}
		$filters = [];
		foreach ( [ 'result', 'stage', 'error_class', 'status', 'action' ] as $key ) {
			$val = $request->get_param( $key );
			if ( $val ) {
				$filters[ $key ] = $val;
			}
		}
		return $obj->results( $filters );
	}

	public static function rest_groups( $request ) {
		$obj = new self( (int) $request['id'] );
		if ( ! ( new CoreUpdateRuns() )->get( $obj->core_update_run_id ) ) {
			return new \WP_Error( 'not_found', 'Core update run not found', [ 'status' => 404 ] );
		}
		return $obj->groups();
	}

	public static function rest_update_result( $request ) {
		$id  = (int) $request['result_id'];
		$row = ( new CoreUpdateResults() )->get( $id );
		if ( ! $row ) {
			return new \WP_Error( 'not_found', 'Result not found', [ 'status' => 404 ] );
		}
		$update = [ 'updated_at' => gmdate( 'Y-m-d H:i:s' ) ];
		$status = $request->get_param( 'status' );
		$notes  = $request->get_param( 'notes' );
		if ( $status !== null && $status !== '' ) {
			$allowed = [ 'open', 'triaged', 'resolved', 'ignored' ];
			if ( ! in_array( $status, $allowed, true ) ) {
				return new \WP_Error( 'invalid', 'Invalid status', [ 'status' => 400 ] );
			}
			$update['status'] = $status;
		}
		if ( $notes !== null ) {
			$update['notes'] = (string) $notes;
		}
		( new CoreUpdateResults() )->update( $update, [ 'core_update_result_id' => $id ] );
		return ( new CoreUpdateResults() )->get( $id );
	}
}
