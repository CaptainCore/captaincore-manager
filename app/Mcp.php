<?php

namespace CaptainCore;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The Claude connector: a remote MCP server at /captaincore/v1/mcp.
 *
 * Streamable HTTP without the optional SSE stream. Each POST carries one
 * JSON-RPC message (or a batch) and gets a plain JSON reply, so no session
 * state is kept between requests. Sign-in is McpAuth; every tool acts as the
 * signed-in user, so an agency login sees its whole roster and a customer sees
 * their own sites, exactly as in the dashboard.
 */
class Mcp {

	const PROTOCOL_VERSIONS = [ '2025-11-25', '2025-06-18', '2025-03-26', '2024-11-05' ];
	const LIST_LIMIT        = 200;
	const OUTPUT_LIMIT      = 100000;

	public static function handle( \WP_REST_Request $request ) {
		$user = McpAuth::authenticate( $request );
		if ( ! $user ) {
			return McpAuth::challenge( $user === false );
		}

		if ( $request->get_method() !== 'POST' ) {
			$response = new \WP_REST_Response( null, 405 );
			$response->header( 'Allow', 'POST' );
			return $response;
		}

		$body = json_decode( $request->get_body(), true );
		if ( ! is_array( $body ) ) {
			return new \WP_REST_Response( self::error( null, -32700, 'Parse error' ), 400 );
		}

		$batch    = array_is_list( $body );
		$messages = $batch ? $body : [ $body ];
		$replies  = [];
		foreach ( $messages as $message ) {
			$reply = self::dispatch( $message );
			if ( $reply !== null ) {
				$replies[] = $reply;
			}
		}

		// Notifications and client responses get 202 with no body.
		if ( empty( $replies ) ) {
			return new \WP_REST_Response( null, 202 );
		}
		return new \WP_REST_Response( $batch ? $replies : $replies[0], 200 );
	}

	private static function dispatch( $message ) {
		if ( ! is_array( $message ) || ! isset( $message['method'] ) ) {
			return null;
		}
		$has_id = array_key_exists( 'id', $message );
		$id     = $has_id ? $message['id'] : null;
		$params = isset( $message['params'] ) && is_array( $message['params'] ) ? $message['params'] : [];

		switch ( $message['method'] ) {
			case 'initialize':
				$result = self::initialize( $params );
				break;
			case 'ping':
				$result = new \stdClass();
				break;
			case 'tools/list':
				$result = [ 'tools' => self::tools() ];
				break;
			case 'tools/call':
				$result = self::call_tool( (string) ( $params['name'] ?? '' ), isset( $params['arguments'] ) && is_array( $params['arguments'] ) ? $params['arguments'] : [] );
				break;
			default:
				if ( ! $has_id ) {
					return null;
				}
				return self::error( $id, -32601, 'Method not found' );
		}

		if ( ! $has_id ) {
			return null;
		}
		if ( is_wp_error( $result ) ) {
			return self::error( $id, -32602, $result->get_error_message() );
		}
		return [ 'jsonrpc' => '2.0', 'id' => $id, 'result' => $result ];
	}

	private static function error( $id, $code, $message ) {
		return [ 'jsonrpc' => '2.0', 'id' => $id, 'error' => [ 'code' => $code, 'message' => $message ] ];
	}

	private static function initialize( $params ) {
		$requested = (string) ( $params['protocolVersion'] ?? '' );
		$version   = in_array( $requested, self::PROTOCOL_VERSIONS, true ) ? $requested : self::PROTOCOL_VERSIONS[0];
		$name      = self::brand();
		return [
			'protocolVersion' => $version,
			'capabilities'    => [ 'tools' => [ 'listChanged' => false ] ],
			'serverInfo'      => [ 'name' => 'captaincore', 'title' => $name, 'version' => CAPTAINCORE_VERSION ],
			'instructions'    => "{$name} hosts and manages the user's WordPress sites. Call list_sites to find a site, then run_command to run WP-CLI (`wp …`) or shell commands in that site's WordPress directory. "
				. 'Read the current state before changing anything, and prefer changes WordPress can undo (drafts, post revisions). '
				. 'Confirm with the user before destructive work such as deleting content, plugins or users, or a search-replace. '
				. 'Use the staging environment when the user wants to try something safely. Every command is recorded in the account activity log.',
		];
	}

	private static function brand() {
		$configurations = Configurations::get();
		return ! empty( $configurations->name ) ? $configurations->name : 'CaptainCore';
	}

	private static function tools() {
		return [
			[
				'name'        => 'list_sites',
				'title'       => 'List sites',
				'description' => 'Lists the WordPress sites you can manage, with each environment\'s URL and WordPress version.',
				'inputSchema' => [
					'type'       => 'object',
					'properties' => [
						'search' => [ 'type' => 'string', 'description' => 'Only sites whose name or URL contains this text.' ],
					],
				],
				'annotations' => [ 'readOnlyHint' => true, 'openWorldHint' => false ],
			],
			[
				'name'        => 'run_command',
				'title'       => 'Run a command on a site',
				'description' => 'Runs WP-CLI or shell commands on a site over SSH, in its WordPress directory, and returns the output. '
					. 'Multi-line scripts are fine. Example: `wp post list --post_type=page --fields=ID,post_title,post_status`.',
				'inputSchema' => [
					'type'       => 'object',
					'properties' => [
						'site'        => [ 'type' => 'string', 'description' => 'Site id, name or domain, as returned by list_sites.' ],
						'environment' => [ 'type' => 'string', 'enum' => [ 'production', 'staging' ], 'description' => 'Defaults to production.' ],
						'command'     => [ 'type' => 'string', 'description' => 'The WP-CLI or bash commands to run.' ],
					],
					'required'   => [ 'site', 'command' ],
				],
				'annotations' => [ 'destructiveHint' => true, 'openWorldHint' => false ],
			],
		];
	}

	private static function call_tool( $name, $arguments ) {
		if ( $name === 'list_sites' ) {
			return self::list_sites( $arguments );
		}
		if ( $name === 'run_command' ) {
			return self::run_command( $arguments );
		}
		return new \WP_Error( 'unknown_tool', "Unknown tool: {$name}" );
	}

	private static function text_result( $text, $is_error = false ) {
		return [ 'content' => [ [ 'type' => 'text', 'text' => (string) $text ] ], 'isError' => $is_error ];
	}

	private static function list_sites( $arguments ) {
		global $wpdb;
		$site_ids = array_values( array_filter( array_map( 'intval', (array) ( new Sites )->site_ids() ) ) );
		if ( empty( $site_ids ) ) {
			return self::text_result( 'You do not have access to any sites.' );
		}

		$in    = implode( ',', $site_ids );
		$sites = $wpdb->get_results( "SELECT site_id, name FROM {$wpdb->prefix}captaincore_sites WHERE status = 'active' AND site_id IN ($in) ORDER BY name" );
		$envs  = $wpdb->get_results( "SELECT site_id, environment, home_url, core FROM {$wpdb->prefix}captaincore_environments WHERE site_id IN ($in) ORDER BY environment = 'Production' DESC, environment" );

		$by_site = [];
		foreach ( $envs as $env ) {
			$by_site[ $env->site_id ][] = [ 'environment' => strtolower( $env->environment ), 'url' => $env->home_url, 'wordpress' => $env->core ];
		}

		$search = strtolower( trim( (string) ( $arguments['search'] ?? '' ) ) );
		$rows   = [];
		foreach ( $sites as $site ) {
			$environments = $by_site[ $site->site_id ] ?? [];
			if ( $search !== '' ) {
				$haystack = strtolower( $site->name . ' ' . implode( ' ', array_column( $environments, 'url' ) ) );
				if ( strpos( $haystack, $search ) === false ) {
					continue;
				}
			}
			$rows[] = [ 'site_id' => (int) $site->site_id, 'name' => $site->name, 'environments' => $environments ];
		}

		$total = count( $rows );
		$out   = [ 'sites' => array_slice( $rows, 0, self::LIST_LIMIT ), 'total' => $total ];
		if ( $total > self::LIST_LIMIT ) {
			$out['note'] = 'Showing the first ' . self::LIST_LIMIT . ". Pass search to narrow the list.";
		}
		return self::text_result( wp_json_encode( $out, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT ) );
	}

	/**
	 * Finds one active site the user can access from an id, name or URL.
	 *
	 * @return object|\WP_Error Site row.
	 */
	private static function resolve_site( $ref ) {
		global $wpdb;
		$ref = trim( (string) $ref );
		if ( $ref === '' ) {
			return new \WP_Error( 'missing_site', 'site is required.' );
		}

		if ( ctype_digit( $ref ) ) {
			$ids = [ (int) $ref ];
		} else {
			$host = strtolower( (string) preg_replace( '#^[a-z]+://#i', '', $ref ) );
			$host = preg_replace( '#^www\.#', '', explode( '/', $host )[0] );
			$urls = [ "https://$host", "http://$host", "https://www.$host", "http://www.$host" ];
			$ids  = array_merge(
				$wpdb->get_col( $wpdb->prepare( "SELECT site_id FROM {$wpdb->prefix}captaincore_sites WHERE LOWER(name) IN (%s, %s) OR site = %s", $host, "www.$host", $host ) ),
				$wpdb->get_col( $wpdb->prepare( "SELECT site_id FROM {$wpdb->prefix}captaincore_environments WHERE TRIM(TRAILING '/' FROM home_url) IN (%s, %s, %s, %s)", $urls ) )
			);
		}

		$sites = new Sites;
		$found = [];
		foreach ( array_unique( array_map( 'intval', $ids ) ) as $site_id ) {
			$site = Sites::get( $site_id );
			if ( $site && $site->status === 'active' && $sites->verify( $site_id ) ) {
				$found[] = $site;
			}
		}

		if ( count( $found ) === 1 ) {
			return $found[0];
		}
		if ( empty( $found ) ) {
			return new \WP_Error( 'site_not_found', "No site you can manage matches \"{$ref}\". Call list_sites to see them." );
		}
		$names = implode( ', ', array_map( function ( $s ) { return "{$s->name} (site_id {$s->site_id})"; }, $found ) );
		return new \WP_Error( 'site_ambiguous', "More than one site matches \"{$ref}\": {$names}. Pass the site_id." );
	}

	private static function run_command( $arguments ) {
		$command = (string) ( $arguments['command'] ?? '' );
		if ( trim( $command ) === '' ) {
			return self::text_result( 'command is required.', true );
		}
		$site = self::resolve_site( $arguments['site'] ?? '' );
		if ( is_wp_error( $site ) ) {
			return self::text_result( $site->get_error_message(), true );
		}
		$environment = Run::safe_environment( $arguments['environment'] ?? 'production' );
		if ( empty( ( new Site( $site->site_id ) )->fetch_environment_id( ucfirst( $environment ) ) ) ) {
			return self::text_result( "{$site->name} has no {$environment} environment.", true );
		}

		ActivityLog::log( 'ran', 'site', $site->site_id, $site->name, 'Claude ran a command on ' . ucfirst( $environment ), [
			'command' => mb_substr( $command, 0, 2000 ),
			'via'     => 'claude',
		], $site->customer_id ? $site->customer_id : null );

		$result = Run::execute( [ 'run', "{$site->site}-{$environment}", '--code=' . base64_encode( $command ) ] );
		if ( is_wp_error( $result ) ) {
			return self::text_result( 'The command could not be dispatched: ' . $result->get_error_message(), true );
		}

		$output = trim( (string) ( $result['response'] ?? '' ) );
		if ( $output === '' ) {
			$output = '(no output)';
		}
		if ( strlen( $output ) > self::OUTPUT_LIMIT ) {
			$output = substr( $output, 0, self::OUTPUT_LIMIT ) . "\n…output truncated at " . self::OUTPUT_LIMIT . ' bytes. Narrow the command (fields, filters, head) for the rest.';
		}
		return self::text_result( $output );
	}
}
