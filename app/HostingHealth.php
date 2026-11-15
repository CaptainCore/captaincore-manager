<?php

namespace CaptainCore;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Health of our own servers, kept as security signals with source "hosting"
 * (the signals table, its attention list, acknowledgement and notes). The
 * CLI's `node health` asks a server for its current state (`cc-backups
 * health`) and posts it here (`/api hosting-health`):
 *   - server items (storage pool, system disk, memory, the edge, timers, the
 *     last local backup run, a reboot left waiting) are signals of site 0
 *     with environment "server:<name>";
 *   - container items (not running, PHP or MariaDB down, out of memory, a
 *     full volume, no recent local backup) are signals of the environment
 *     that runs in that container.
 * Each scope is synced as a whole (MalwareFindings::sync_state): reported
 * means open, missing from the next report means resolved. A server that
 * cannot be reached gets one "unreachable" signal and keeps the rest as they
 * were. Critical and high items are priority, so they go on the attention
 * list at once (MalwareFindings::is_priority).
 */
class HostingHealth {

	const SOURCE = 'hosting';

	public static function server_scope( $server ) {
		return substr( 'server:' . $server->name, 0, 40 );
	}

	/** Report items as findings for MalwareFindings::sync_state(). */
	public static function to_findings( $prefix, $items ) {
		$out = [];
		foreach ( (array) $items as $item ) {
			$item = (object) $item;
			$key  = preg_replace( '/[^a-z0-9-]/', '', strtolower( (string) ( $item->key ?? '' ) ) );
			$sev  = in_array( $item->severity ?? '', [ 'critical', 'high', 'medium', 'low' ], true ) ? $item->severity : 'medium';
			if ( $key === '' ) {
				continue;
			}
			$title = substr( sanitize_text_field( (string) ( $item->title ?? $key ) ), 0, 200 );
			$out[] = (object) [
				'filename'              => $prefix . '/' . $key,
				'signature_id'          => 'hosting-' . $key,
				'signature_name'        => $title,
				'signature_description' => sanitize_text_field( (string) ( $item->detail ?? '' ) ),
				'matched_text'          => substr( sanitize_text_field( (string) ( $item->value ?? '' ) ), 0, 200 ),
				'severity'              => $sev,
				'content_hash'          => hash( 'sha256', $sev . '|' . $title ),
			];
		}
		return $out;
	}

	/**
	 * File one server's report. $error set means the CLI could not reach it.
	 *
	 * @return array{server: int, environments: int, open: int, unreachable: bool}
	 */
	public static function ingest( $server, $report, $error = '' ) {
		global $wpdb;
		$scope = self::server_scope( $server );
		if ( $error !== '' ) {
			MalwareFindings::record( 0, 0, $scope, self::SOURCE, self::to_findings( $scope, [ [
				'key' => 'unreachable', 'severity' => 'critical', 'title' => 'Server cannot be reached',
				'detail' => 'CaptainCore could not reach ' . $server->name . ' (' . $server->address . ') to check it: ' . $error, 'value' => substr( $error, 0, 120 ),
			] ] ) );
			return [ 'server' => 0, 'environments' => 0, 'open' => 1, 'unreachable' => true ];
		}
		$report = (object) $report;
		$synced = MalwareFindings::sync_state( 0, 0, $scope, self::SOURCE, self::to_findings( $scope, $report->server ?? [] ) );
		$open   = count( (array) ( $report->server ?? [] ) );
		$cts    = (array) ( $report->containers ?? [] );
		$envs   = 0;
		foreach ( $wpdb->get_results( $wpdb->prepare(
			"SELECT * FROM {$wpdb->base_prefix}captaincore_environments WHERE server_id = %d", (int) $server->server_id ) ) as $env ) {
			$d  = json_decode( (string) $env->details ) ?: new \stdClass();
			$ct = (string) ( $d->container ?? '' );
			if ( $ct === '' ) {
				continue;
			}
			$items = array_key_exists( $ct, $cts ) ? (array) $cts[ $ct ] : [ [
				'key' => 'container', 'severity' => 'critical', 'title' => 'Container is missing',
				'detail' => "The Manager places this environment in {$ct} on {$server->name}, and the server has no such container.", 'value' => 'missing',
			] ];
			MalwareFindings::sync_state( (int) $env->site_id, (int) $env->environment_id, $env->environment, self::SOURCE, self::to_findings( 'ct:' . $ct, $items ) );
			$open += count( $items );
			$envs++;
		}
		return [ 'server' => count( (array) ( $report->server ?? [] ) ), 'environments' => $envs, 'open' => $open, 'unreachable' => false, 'cleared' => (int) ( $synced['cleared'] ?? 0 ) ];
	}
}
