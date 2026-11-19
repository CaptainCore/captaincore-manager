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
				. 'Use the staging environment when the user wants to try something safely. Every command is recorded in the account activity log. '
				. "Everything else the {$name} dashboard does (new sites, backups, domains, DNS, staging deploys, PHP and cache, invites, billing history, support chat) is in its REST API: "
				. 'call api_docs with a topic to find the endpoint, then api_request to call it as the user.',
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
						'environment' => [ 'type' => 'string', 'description' => 'production (the default), staging, or another environment the site has, as list_sites shows it.' ],
						'command'     => [ 'type' => 'string', 'description' => 'The WP-CLI or bash commands to run.' ],
					],
					'required'   => [ 'site', 'command' ],
				],
				'annotations' => [ 'destructiveHint' => true, 'openWorldHint' => false ],
			],
			[
				'name'        => 'api_docs',
				'title'       => 'Read the API docs',
				'description' => 'Searches the dashboard\'s REST API reference. With no topic it lists every section and endpoint. '
					. 'With a topic (a word or a path such as "new site", "backups", "dns" or "/sites/{site_id}/environments") it returns the matching endpoints with their fields and examples. '
					. 'Paths in the docs are relative to /wp-json/captaincore/v1, which is what api_request expects. Ignore the curl and authentication notes: api_request is already signed in.',
				'inputSchema' => [
					'type'       => 'object',
					'properties' => [
						'topic' => [ 'type' => 'string', 'description' => 'A word, heading or path to look up.' ],
					],
				],
				'annotations' => [ 'readOnlyHint' => true, 'openWorldHint' => false ],
			],
			[
				'name'        => 'api_request',
				'title'       => 'Call the dashboard API',
				'description' => 'Calls one REST API endpoint as the signed-in user, with exactly the access they have in the dashboard. '
					. 'Look the endpoint up with api_docs first. Example: method POST, path /sites/12/production/cache/clear. '
					. 'Credentials, two-factor, payment methods, paying invoices and file downloads are not available here; the user does those in the dashboard. '
					. 'Every call that changes something is recorded in the activity log.',
				'inputSchema' => [
					'type'       => 'object',
					'properties' => [
						'method' => [ 'type' => 'string', 'enum' => [ 'GET', 'POST', 'PUT', 'PATCH', 'DELETE' ], 'description' => 'HTTP method from the docs.' ],
						'path'   => [ 'type' => 'string', 'description' => 'Endpoint path relative to /wp-json/captaincore/v1, with ids filled in, such as /sites or /sites/12/environments. A query string is allowed.' ],
						'params' => [ 'type' => 'object', 'description' => 'The JSON body for POST, PUT, PATCH and DELETE, or query parameters for GET.' ],
					],
					'required'   => [ 'method', 'path' ],
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
		if ( $name === 'api_docs' ) {
			return self::api_docs( $arguments );
		}
		if ( $name === 'api_request' ) {
			return self::api_request( $arguments );
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
		if ( ! Environments::for_site( $site->site_id, $environment ) ) {
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

	/**
	 * Routes the connector will not call for anyone, with the reason Claude is
	 * given. Credentials and payments stay with the person: a connector that
	 * could mint an application password would no longer be a narrower door
	 * than the dashboard. Downloads and streams write straight to the response
	 * and would break the JSON-RPC reply.
	 */
	const BLOCKED_ROUTES = [
		'#^/(mcp|oauth|api|cli|login)(/|$)#'                              => 'is not part of the dashboard API.',
		'#^/run/code$#'                                                   => 'runs code on a site. Use the run_command tool, which logs the command.',
		'#^/me/application-password#'                                     => 'manages credentials. Application passwords are created and revoked in Profile.',
		'#^/me/tfa_#'                                                     => 'manages two-factor authentication, which stays in Profile.',
		'#^/me/profile#'                                                  => 'changes sign-in details (email and password), which stay in Profile.',
		'#^/sessions#'                                                    => 'manages sign-in sessions, which stay in Profile.',
		'#^/billing/(pay-invoice|payment-methods|ach/)#'                  => 'moves money or payment details. Paying and payment methods stay in Billing.',
		'#^/me/api-docs#'                                                 => 'is a download. Use api_docs instead.',
		'#(^/my-jobs/[^/]+/stream|/download$|/pdf$|/artifact$|^/site/\d+/snapshots/|^/ai-relay/projects/\d+/files/|^/chat/attachments/)#' => 'is a file download or stream, which only works in the browser.',
	];

	private static function api_docs( $arguments ) {
		$file = dirname( __DIR__ ) . '/api-docs.md';
		$docs = is_readable( $file ) ? (string) file_get_contents( $file ) : '';
		if ( $docs === '' ) {
			return self::text_result( 'The API docs are not available.', true );
		}

		// Split into ### endpoint blocks, each remembering its ## section.
		$blocks  = [];
		$section = '';
		$current = null;
		foreach ( preg_split( '/\R/', $docs ) as $line ) {
			if ( preg_match( '/^## (?!#)(.+)$/', $line, $m ) ) {
				if ( $current ) {
					$blocks[] = $current;
				}
				$section = trim( $m[1] );
				$current = [ 'section' => $section, 'title' => $section, 'text' => $line . "\n" ];
				continue;
			}
			if ( preg_match( '/^### (.+)$/', $line, $m ) ) {
				if ( $current ) {
					$blocks[] = $current;
				}
				$current = [ 'section' => $section, 'title' => trim( $m[1] ), 'text' => $line . "\n" ];
				continue;
			}
			if ( $current ) {
				$current['text'] .= $line . "\n";
			}
		}
		if ( $current ) {
			$blocks[] = $current;
		}

		$topic = strtolower( trim( (string) ( $arguments['topic'] ?? '' ) ) );
		if ( $topic === '' ) {
			$out  = "API reference. Call api_docs with a topic to read an endpoint, then api_request to call it.\n";
			$last = null;
			foreach ( $blocks as $block ) {
				if ( $block['section'] !== $last ) {
					$out .= "\n## {$block['section']}\n";
					$last = $block['section'];
				}
				if ( $block['title'] !== $block['section'] ) {
					$route = preg_match( '/^(GET|POST|PUT|PATCH|DELETE)\s+\S+/m', $block['text'], $r ) ? "  `{$r[0]}`" : '';
					$out  .= "- {$block['title']}{$route}\n";
				}
			}
			return self::text_result( $out );
		}

		// Match on headings first, then on paths and body text.
		$words   = array_filter( preg_split( '/\s+/', $topic ) );
		$scored  = [];
		foreach ( $blocks as $i => $block ) {
			$head = strtolower( $block['section'] . ' ' . $block['title'] );
			$body = strtolower( $block['text'] );
			$score = 0;
			foreach ( $words as $word ) {
				if ( strpos( $head, $word ) !== false ) {
					$score += 3;
				} elseif ( strpos( $body, $word ) !== false ) {
					$score += 1;
				} else {
					$score = 0;
					break;
				}
			}
			if ( $score > 0 ) {
				$scored[ $i ] = $score;
			}
		}
		if ( empty( $scored ) ) {
			return self::text_result( "Nothing in the API docs matches \"{$topic}\". Call api_docs with no topic for the full list." );
		}
		arsort( $scored );
		$out = '';
		foreach ( array_keys( $scored ) as $i ) {
			$text = $blocks[ $i ]['text'];
			if ( strlen( $out ) + strlen( $text ) > self::OUTPUT_LIMIT ) {
				$out .= "\n…more matches. Use a narrower topic.";
				break;
			}
			$out .= "_Section: {$blocks[ $i ]['section']}_\n" . $text . "\n";
		}
		return self::text_result( trim( $out ) );
	}

	private static function api_request( $arguments ) {
		$method = strtoupper( trim( (string) ( $arguments['method'] ?? 'GET' ) ) );
		if ( ! in_array( $method, [ 'GET', 'POST', 'PUT', 'PATCH', 'DELETE' ], true ) ) {
			return self::text_result( 'method must be GET, POST, PUT, PATCH or DELETE.', true );
		}

		$path = trim( (string) ( $arguments['path'] ?? '' ) );
		$path = preg_replace( '#^https?://[^/]+#i', '', $path );
		$path = preg_replace( '#^/?wp-json#', '', $path );
		$path = preg_replace( '#^/?captaincore/v1#', '', $path );
		$query = [];
		if ( strpos( $path, '?' ) !== false ) {
			[ $path, $qs ] = explode( '?', $path, 2 );
			parse_str( $qs, $query );
		}
		$path = '/' . ltrim( $path, '/' );
		if ( $path === '/' || strpos( $path, '..' ) !== false || preg_match( '#[^A-Za-z0-9/_.,~=-]#', $path ) ) {
			return self::text_result( 'path must be an endpoint path such as /sites or /sites/12/environments.', true );
		}
		foreach ( self::BLOCKED_ROUTES as $pattern => $reason ) {
			if ( preg_match( $pattern, $path ) ) {
				return self::text_result( "{$method} {$path} {$reason}", true );
			}
		}

		$params  = isset( $arguments['params'] ) && is_array( $arguments['params'] ) ? $arguments['params'] : [];
		$request = new \WP_REST_Request( $method, '/captaincore/v1' . $path );
		$request->set_query_params( $method === 'GET' ? array_merge( $query, $params ) : $query );
		if ( $method !== 'GET' ) {
			$request->set_header( 'Content-Type', 'application/json' );
			$request->set_body( wp_json_encode( (object) $params ) );
		}

		$response = rest_do_request( $request );
		$server   = rest_get_server();
		$data     = $server->response_to_data( $response, false );
		$status   = $response->get_status();

		if ( $method !== 'GET' && ( $data['code'] ?? '' ) !== 'rest_no_route' ) {
			self::log_api_call( $method, $path, $params, $status );
		}

		$body = wp_json_encode( $data, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT | JSON_PARTIAL_OUTPUT_ON_ERROR );
		if ( strlen( (string) $body ) > self::OUTPUT_LIMIT ) {
			$body = substr( $body, 0, self::OUTPUT_LIMIT ) . "\n…response truncated at " . self::OUTPUT_LIMIT . ' bytes. Narrow it with query parameters where the docs offer them.';
		}
		$text = "HTTP {$status}\n" . $body;
		return self::text_result( $text, $status >= 400 );
	}

	/** Writes one activity log row for a connector call that can change something. */
	private static function log_api_call( $method, $path, $params, $status ) {
		$entity_type = 'user';
		$entity_id   = get_current_user_id();
		$entity_name = wp_get_current_user()->display_name;
		$account_id  = null;

		if ( preg_match( '#^/sites?/(\d+)#', $path, $m ) ) {
			$site = Sites::get( (int) $m[1] );
			if ( $site ) {
				$entity_type = 'site';
				$entity_id   = (int) $site->site_id;
				$entity_name = $site->name;
				$account_id  = $site->customer_id ? $site->customer_id : null;
			}
		} elseif ( preg_match( '#^/domains?/(\d+)#', $path, $m ) ) {
			$entity_type = 'domain';
			$entity_id   = (int) $m[1];
			$entity_name = null;
		} elseif ( preg_match( '#^/accounts?/(\d+)#', $path, $m ) ) {
			$entity_type = 'account';
			$entity_id   = (int) $m[1];
			$entity_name = null;
			$account_id  = (int) $m[1];
		}

		$summary = wp_json_encode( $params, JSON_UNESCAPED_SLASHES );
		ActivityLog::log( 'called', $entity_type, $entity_id, $entity_name, "Claude called {$method} {$path}", [
			'method' => $method,
			'path'   => $path,
			'params' => mb_substr( (string) $summary, 0, 2000 ),
			'status' => (int) $status,
			'via'    => 'claude',
		], $account_id );
	}
}
