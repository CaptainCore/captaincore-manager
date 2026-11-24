<?php

namespace CaptainCore;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Kinsta's bot protection for one environment: the protection level, AI
 * crawlers, typical WordPress automations and exception rules.
 *
 * Gated by access to the site, like the other site settings. A change also
 * needs access to every other active site on the same Kinsta site, since they
 * share the setting. Every change is a Kinsta operation, kept and reported the
 * same way as a PHP switch (captaincore_kinsta_op_*).
 *
 * Any level above the baseline turns automated clients away, and our uptime
 * monitor is one: raising the level first adds an exception for its address,
 * so a protected site is not reported as down. The address is learned from the
 * CLI server's own monitor-state calls (remember_monitor()).
 */
class KinstaBots {

	// Lowest to highest.
	const LEVELS = [
		'block-malicious-traffic' => 'Block malicious traffic',
		'block-automations'       => 'Block automations',
		'challenge-bots'          => 'Challenge bots',
		'challenge-everyone'      => 'Challenge everyone',
	];
	const TYPES       = [ 'ip' => 'IP address', 'path' => 'path', 'user_agent' => 'user agent', 'country' => 'country' ];
	const ACTIONS     = [ 'skip' => 'Allow', 'challenge' => 'Challenge', 'block' => 'Block' ];
	const MAX_RULES   = 50; // Kinsta's limit per environment.
	const MONITOR_IPS = 'captaincore_monitor_ips';

	/** The site, environment and Kinsta's own environment id, or a WP_Error. */
	private static function target( $request ) {
		$t = captaincore_site_settings_target( $request );
		if ( is_wp_error( $t ) ) {
			return $t;
		}
		if ( $t['host'] !== 'kinsta' ) {
			return new \WP_Error( 'not_kinsta', 'Bot protection is only for environments at Kinsta.', [ 'status' => 400 ] );
		}
		return $t;
	}

	private static function environment_id( $t ) {
		$id = Providers\Kinsta::environment_id( (int) $t['site']->site_id, $t['env']->environment );
		if ( is_wp_error( $id ) ) {
			return $id;
		}
		return preg_match( KinstaAccess::UUID, (string) $id ) ? (string) $id : new \WP_Error( 'kinsta_env', 'Kinsta did not return this environment.', [ 'status' => 502 ] );
	}

	private static function can_edit( $site ) {
		$shared = KinstaAccess::site_ids_sharing( $site, true );
		return ! $shared || captaincore_verify_permissions( $shared );
	}

	private static function fetch( $env_id, $what ) {
		$res = Remote\Kinsta::get( "sites/environments/{$env_id}/bot-protection/{$what}" );
		if ( ! is_object( $res ) || ! isset( $res->bot_protection ) || ! is_object( $res->bot_protection ) ) {
			return new \WP_Error( 'kinsta_bots', 'Kinsta did not return the bot protection settings: ' . Providers\Kinsta::error_message( $res, 'nothing is set for this environment.' ), [ 'status' => 502 ] );
		}
		return $res->bot_protection;
	}

	private static function rules( $env_id ) {
		$res = self::fetch( $env_id, 'exception-rules' );
		if ( is_wp_error( $res ) ) {
			return $res;
		}
		$rules = [];
		foreach ( (array) ( $res->exception_rules ?? [] ) as $r ) {
			$rules[] = [ 'id' => (string) ( $r->id ?? '' ), 'type' => (string) ( $r->type ?? '' ), 'action' => (string) ( $r->action ?? '' ), 'value' => (string) ( $r->value ?? '' ) ];
		}
		return [ 'rules' => $rules, 'custom_waf' => ! empty( $res->has_custom_waf_rules ) ];
	}

	/** GET. While Kinsta works on a change only `pending` comes back. */
	public static function read( $request ) {
		$t = self::target( $request );
		if ( is_wp_error( $t ) ) {
			return $t;
		}
		$op = captaincore_kinsta_op_check( $t['env'] );
		if ( $op ) {
			return [ 'pending' => captaincore_kinsta_op_labels( $op )[0] ];
		}
		$env_id = self::environment_id( $t );
		if ( is_wp_error( $env_id ) ) {
			return $env_id;
		}
		$level = self::fetch( $env_id, 'level' );
		$ai    = is_wp_error( $level ) ? $level : self::fetch( $env_id, 'ai-crawlers' );
		$wp    = is_wp_error( $ai ) ? $ai : self::fetch( $env_id, 'wp-automations' );
		$rules = is_wp_error( $wp ) ? $wp : self::rules( $env_id );
		if ( is_wp_error( $rules ) ) {
			return $rules;
		}
		$failed = get_site_transient( captaincore_kinsta_op_key( $t['env']->environment_id ) . '_failed' );
		$ips    = self::monitor_ips();
		return [
			'pending'         => '',
			'level'           => (string) ( $level->level ?? '' ),
			'ai_blocked'      => ( $ai->ai_crawlers_blocking ?? '' ) === 'enabled',
			'wp_automations'  => ( $wp->wp_automations ?? '' ) === 'enabled',
			'rules'           => array_map( function ( $r ) use ( $ips ) {
				return $r + [ 'monitor' => $r['type'] === 'ip' && $r['action'] === 'skip' && in_array( $r['value'], $ips, true ) ];
			}, $rules['rules'] ),
			'custom_waf'      => $rules['custom_waf'],
			'max_rules'       => self::MAX_RULES,
			'monitor_known'   => (bool) $ips,
			'monitor_allowed' => ! self::missing_monitor( $rules['rules'] ),
			'can_edit'        => self::can_edit( $t['site'] ),
			'error'           => is_string( $failed ) ? $failed : '',
		];
	}

	/**
	 * POST, one change: { level }, { ai_blocked }, { wp_automations },
	 * { add: { type, action, value } }, { remove: rule id } or
	 * { allow_monitor: true }.
	 */
	public static function change( $request ) {
		$t = self::target( $request );
		if ( is_wp_error( $t ) ) {
			return $t;
		}
		if ( ! self::can_edit( $t['site'] ) ) {
			return new \WP_Error( 'shared_server', 'This site shares its Kinsta site with others, so bot protection has to be arranged with us.', [ 'status' => 403 ] );
		}
		$params = array_intersect_key( (array) $request->get_json_params(), array_flip( [ 'level', 'ai_blocked', 'wp_automations', 'add', 'remove', 'allow_monitor' ] ) );
		if ( count( $params ) !== 1 ) {
			return new \WP_Error( 'invalid', 'Send one change at a time.', [ 'status' => 400 ] );
		}
		$what  = key( $params );
		$value = current( $params );
		$rule  = null;
		if ( $what === 'level' && ( ! is_string( $value ) || ! isset( self::LEVELS[ $value ] ) ) ) {
			return new \WP_Error( 'invalid_level', 'Choose one of the protection levels.', [ 'status' => 400 ] );
		}
		if ( in_array( $what, [ 'ai_blocked', 'wp_automations' ], true ) && ! is_bool( $value ) ) {
			return new \WP_Error( 'invalid', 'Send true or false.', [ 'status' => 400 ] );
		}
		if ( $what === 'remove' && ( ! is_string( $value ) || $value === '' ) ) {
			return new \WP_Error( 'invalid', 'Choose a rule to remove.', [ 'status' => 400 ] );
		}
		if ( $what === 'add' ) {
			$rule = self::clean_rule( $value );
			if ( is_wp_error( $rule ) ) {
				return $rule;
			}
		}
		if ( $busy = captaincore_kinsta_op_busy( $t['env'] ) ) {
			return $busy;
		}
		$env_id = self::environment_id( $t );
		if ( is_wp_error( $env_id ) ) {
			return $env_id;
		}
		$env  = $t['env']->environment;
		$base = "sites/environments/{$env_id}/bot-protection";
		switch ( $what ) {
			case 'level':
				if ( $value !== 'block-malicious-traffic' ) {
					$ready = self::allow_monitor( $t, $base, $env_id );
					if ( is_wp_error( $ready ) ) {
						return $ready;
					}
				}
				$name   = self::LEVELS[ $value ];
				$res    = Remote\Kinsta::put( "{$base}/level", [ 'level' => $value ] );
				$labels = [ "Setting bot protection to {$name}", "Set bot protection on {$env} to {$name}", "Kinsta could not set bot protection on {$env} to {$name}" ];
				break;
			case 'ai_blocked':
				$res    = Remote\Kinsta::put( "{$base}/ai-crawlers", [ 'status' => $value ? 'enabled' : 'disabled' ] );
				$labels = $value
					? [ 'Blocking AI crawlers', "Blocked AI crawlers on {$env}", "Kinsta could not block AI crawlers on {$env}" ]
					: [ 'Unblocking AI crawlers', "Unblocked AI crawlers on {$env}", "Kinsta could not unblock AI crawlers on {$env}" ];
				break;
			case 'wp_automations':
				$res    = Remote\Kinsta::put( "{$base}/wp-automations", [ 'status' => $value ? 'enabled' : 'disabled' ] );
				$labels = $value
					? [ 'Allowing typical WordPress automations', "Allowed typical WordPress automations on {$env}", "Kinsta could not allow WordPress automations on {$env}" ]
					: [ 'Turning off typical WordPress automations', "Stopped allowing typical WordPress automations on {$env}", "Kinsta could not change WordPress automations on {$env}" ];
				break;
			case 'allow_monitor':
				$ready = self::allow_monitor( $t, $base, $env_id );
				return is_wp_error( $ready ) ? $ready : [ 'pending' => '' ];
			case 'add':
				$now = self::rules( $env_id );
				if ( is_wp_error( $now ) ) {
					return $now;
				}
				foreach ( $now['rules'] as $r ) {
					if ( $r['type'] === $rule['type'] && strcasecmp( $r['value'], $rule['value'] ) === 0 && $r['action'] === $rule['action'] ) {
						return new \WP_Error( 'exists', 'That exception is already there.', [ 'status' => 409 ] );
					}
				}
				if ( count( $now['rules'] ) >= self::MAX_RULES ) {
					return new \WP_Error( 'too_many', 'Kinsta allows ' . self::MAX_RULES . ' exceptions per environment. Remove one first.', [ 'status' => 409 ] );
				}
				$text   = self::rule_text( $rule );
				$res    = Remote\Kinsta::post( "{$base}/exception-rules", $rule );
				$labels = [ "Adding an exception ({$text})", "Added a bot protection exception on {$env}: {$text}", "Kinsta could not add the exception {$text} on {$env}" ];
				break;
			case 'remove':
				$now = self::rules( $env_id );
				if ( is_wp_error( $now ) ) {
					return $now;
				}
				$match = array_values( array_filter( $now['rules'], function ( $r ) use ( $value ) { return $r['id'] === $value; } ) );
				if ( ! $match ) {
					return new \WP_Error( 'gone', 'That exception is no longer there.', [ 'status' => 404 ] );
				}
				$rule = $match[0];
				if ( $rule['type'] === 'ip' && $rule['action'] === 'skip' && in_array( $rule['value'], self::monitor_ips(), true ) ) {
					$level = self::fetch( $env_id, 'level' );
					if ( is_wp_error( $level ) ) {
						return $level;
					}
					if ( ( $level->level ?? '' ) !== 'block-malicious-traffic' ) {
						return new \WP_Error( 'monitor', 'Our uptime monitor needs this exception at this protection level. Lower the level first.', [ 'status' => 409 ] );
					}
				}
				$text   = self::rule_text( $rule );
				$res    = Remote\Kinsta::delete( "{$base}/exception-rules", [ 'exception_rule_id' => $value ] );
				$labels = [ "Removing an exception ({$text})", "Removed a bot protection exception on {$env}: {$text}", "Kinsta could not remove the exception {$text} on {$env}" ];
				break;
		}
		$op_id = self::operation_id( $res );
		if ( is_wp_error( $op_id ) ) {
			return $op_id;
		}
		captaincore_kinsta_op_start( (int) $t['site']->site_id, $t['env'], 'bots', $op_id, [ 'labels' => $labels ] );
		return [ 'pending' => $labels[0] ];
	}

	// An id is interpolated into the operations path when it is polled.
	private static function operation_id( $res ) {
		$id = is_object( $res ) ? (string) ( $res->operation_id ?? '' ) : '';
		if ( ! preg_match( '/^[A-Za-z0-9:_-]{1,200}$/', $id ) ) {
			return new \WP_Error( 'kinsta_refused', 'Kinsta did not accept the change: ' . Providers\Kinsta::error_message( $res ), [ 'status' => 502 ] );
		}
		return $id;
	}

	/** A rule's plain description for labels and the timeline. No markup gets through. */
	private static function rule_text( $rule ) {
		$value = trim( preg_replace( '/[^A-Za-z0-9 ._~%\/+@:=,;()-]/', '', $rule['value'] ) );
		return strtolower( self::ACTIONS[ $rule['action'] ] ?? 'match' ) . ' ' . ( self::TYPES[ $rule['type'] ] ?? 'traffic' ) . ' ' . substr( $value, 0, 120 );
	}

	/** @return array|\WP_Error type, action, value as Kinsta takes them. */
	private static function clean_rule( $rule ) {
		$rule   = is_array( $rule ) ? $rule : [];
		$type   = $rule['type'] ?? '';
		$action = $rule['action'] ?? '';
		$value  = $rule['value'] ?? '';
		if ( ! is_string( $type ) || ! isset( self::TYPES[ $type ] ) || ! is_string( $action ) || ! isset( self::ACTIONS[ $action ] ) || ! is_string( $value ) ) {
			return new \WP_Error( 'invalid_rule', 'Choose what to match and what to do.', [ 'status' => 400 ] );
		}
		$value = trim( $value );
		$bad   = function ( $message ) { return new \WP_Error( 'invalid_value', $message, [ 'status' => 400 ] ); };
		switch ( $type ) {
			case 'ip':
				[ $ip, $bits ] = array_pad( explode( '/', $value, 2 ), 2, null );
				$max = filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 ) ? 32 : ( filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6 ) ? 128 : 0 );
				if ( ! $max || ( $bits !== null && ( ! ctype_digit( $bits ) || (int) $bits > $max ) ) ) {
					return $bad( 'Enter an IP address or a range like 192.0.2.0/24.' );
				}
				// Half the internet is what the protection level is for.
				if ( $bits !== null && (int) $bits < ( $max === 32 ? 8 : 16 ) ) {
					return $bad( 'That range is too wide. Use the protection level for all traffic.' );
				}
				$value = strtolower( $value );
				if ( $action !== 'skip' ) {
					foreach ( self::monitor_ips() as $monitor ) {
						if ( self::ip_in( $monitor, $value ) ) {
							return $bad( 'That includes our uptime monitor, which would report the site as down.' );
						}
					}
					if ( self::ip_in( GeoIP::client_ip(), $value ) ) {
						return $bad( 'That includes your own address, so you would lock yourself out.' );
					}
				}
				break;
			case 'path':
				if ( ! preg_match( '#^/[A-Za-z0-9._~%/+@:=,;-]{1,254}$#', $value ) || trim( $value, '/' ) === '' ) {
					return $bad( 'Enter the start of a path, like /wp-json/. Wildcards are not supported, and / alone would match the whole site.' );
				}
				break;
			case 'user_agent':
				if ( ! preg_match( '#^[A-Za-z0-9 ._~/+@:=,;()-]{3,255}$#', $value ) ) {
					return $bad( 'Enter a user agent of 3 to 255 characters, like Slackbot.' );
				}
				break;
			case 'country':
				$value = strtoupper( $value );
				if ( ! preg_match( '/^[A-Z]{2}$/', $value ) ) {
					return $bad( 'Enter a two-letter country code, like DE.' );
				}
				break;
		}
		return [ 'type' => $type, 'action' => $action, 'value' => $value ];
	}

	/** Whether an address is in a range (or is the address itself). */
	private static function ip_in( $ip, $range ) {
		[ $net, $bits ] = array_pad( explode( '/', $range, 2 ), 2, null );
		$a = @inet_pton( (string) $ip );
		$b = @inet_pton( (string) $net );
		if ( $a === false || $b === false || strlen( $a ) !== strlen( $b ) ) {
			return false;
		}
		$bits  = $bits === null ? strlen( $a ) * 8 : (int) $bits;
		$bytes = intdiv( $bits, 8 );
		if ( $bytes && strncmp( $a, $b, $bytes ) !== 0 ) {
			return false;
		}
		$rest = $bits % 8;
		if ( ! $rest ) {
			return true;
		}
		$mask = ( 0xFF << ( 8 - $rest ) ) & 0xFF;
		return ( ord( $a[ $bytes ] ) & $mask ) === ( ord( $b[ $bytes ] ) & $mask );
	}

	// ── Our uptime monitor ──────────────────────────────────────────────────

	/** Remember where the CLI server's monitor calls come from (one per IP family). */
	public static function remember_monitor( $ip ) {
		if ( ! filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE ) ) {
			return;
		}
		$family = strpos( $ip, ':' ) === false ? 'v4' : 'v6';
		$now    = (array) get_site_option( self::MONITOR_IPS, [] );
		if ( ( $now[ $family ] ?? '' ) !== $ip ) {
			update_site_option( self::MONITOR_IPS, array_merge( $now, [ $family => $ip ] ) );
		}
	}

	private static function monitor_ips() {
		return array_values( array_filter( (array) get_site_option( self::MONITOR_IPS, [] ), function ( $ip ) {
			return is_string( $ip ) && filter_var( $ip, FILTER_VALIDATE_IP );
		} ) );
	}

	private static function missing_monitor( $rules ) {
		$allowed = [];
		foreach ( $rules as $r ) {
			if ( $r['type'] === 'ip' && $r['action'] === 'skip' ) {
				$allowed[] = $r['value'];
			}
		}
		return array_values( array_filter( self::monitor_ips(), function ( $ip ) use ( $allowed ) {
			foreach ( $allowed as $range ) {
				if ( self::ip_in( $ip, $range ) ) {
					return false;
				}
			}
			return true;
		} ) );
	}

	/** Add the monitor's exceptions and wait for Kinsta to finish each one. */
	private static function allow_monitor( $t, $base, $env_id ) {
		$now = self::rules( $env_id );
		if ( is_wp_error( $now ) ) {
			return $now;
		}
		$missing = self::missing_monitor( $now['rules'] );
		if ( count( $now['rules'] ) + count( $missing ) > self::MAX_RULES ) {
			return new \WP_Error( 'too_many', 'There is no room for our uptime monitor\'s exception. Remove one first.', [ 'status' => 409 ] );
		}
		$fail = function ( $why ) {
			return new \WP_Error( 'monitor_failed', "Kinsta did not add the exception for our uptime monitor, so nothing else was changed: {$why}", [ 'status' => 502 ] );
		};
		foreach ( $missing as $ip ) {
			$res   = Remote\Kinsta::post( "{$base}/exception-rules", [ 'type' => 'ip', 'action' => 'skip', 'value' => $ip ] );
			$op_id = self::operation_id( $res );
			if ( is_wp_error( $op_id ) ) {
				return $fail( Providers\Kinsta::error_message( $res ) );
			}
			for ( $i = 0; $i < 20; $i++ ) {
				sleep( 1 );
				$state = Remote\Kinsta::get( "operations/{$op_id}" );
				$code  = (int) ( $state->status ?? 0 );
				if ( $code === 200 ) {
					break;
				}
				if ( $code >= 500 ) {
					return $fail( Providers\Kinsta::error_message( $state ) );
				}
			}
			if ( ( $code ?? 0 ) !== 200 ) {
				return $fail( 'it is taking longer than usual. Try again in a minute.' );
			}
			ProcessLog::insert( "Allowed our uptime monitor through bot protection on {$t['env']->environment}", (int) $t['site']->site_id );
		}
		return true;
	}
}
