<?php

namespace CaptainCore;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * MyKinsta access to one site: who has it, inviting someone, and asking for
 * someone to be removed.
 *
 * Kinsta's key belongs to the whole company. It can invite anyone to any site,
 * so nothing here trusts Kinsta to scope a request: the caller names one
 * CaptainCore site, one email address and one of the two site roles, and the
 * company and Kinsta site ids are always looked up here.
 *
 * Kinsta's API cannot cancel an invite or take access away, so removal is a
 * request to the team, who do it in MyKinsta.
 */
class KinstaAccess {

	const ROLES       = [ 'site-admin', 'site-developer' ];
	const DAILY_LIMIT = 20;
	const UUID        = '/^[0-9a-f]{8}(-[0-9a-f]{4}){3}-[0-9a-f]{12}$/i';

	/** Whether the current user can manage MyKinsta access to a site. No calls to Kinsta. */
	public static function available( $site_id ) {
		return ! is_wp_error( self::check( $site_id ) );
	}

	/**
	 * Everything that can be decided without Kinsta: the caller reaches the
	 * site, may share it, and the site is one whose MyKinsta access covers
	 * nothing the caller cannot already reach.
	 *
	 * @return object|\WP_Error The site row.
	 */
	public static function check( $site_id ) {
		if ( ! captaincore_verify_permissions( $site_id ) ) {
			return new \WP_Error( 'permission_denied', 'Permission denied.', [ 'status' => 403 ] );
		}
		$site = Sites::get( (int) $site_id );
		if ( empty( $site ) ) {
			return new \WP_Error( 'not_found', 'Site not found.', [ 'status' => 404 ] );
		}
		if ( ! captaincore_can_share_site( $site ) ) {
			return new \WP_Error( 'permission_denied', 'Your access level does not allow sending invites.', [ 'status' => 403 ] );
		}
		$details = json_decode( (string) $site->details );
		if ( $site->status !== 'active' || ! empty( $details->removed ) ) {
			return new \WP_Error( 'not_active', 'This site is not active.', [ 'status' => 400 ] );
		}
		if ( $site->provider !== 'kinsta' || ! preg_match( self::UUID, (string) $site->provider_site_id ) ) {
			return new \WP_Error( 'not_linked', 'This site is not linked to a Kinsta site.', [ 'status' => 400 ] );
		}
		// A customer's own Kinsta company is theirs to manage in MyKinsta.
		if ( ! self::is_house( $site ) ) {
			return new \WP_Error( 'not_house', 'This site is in its own Kinsta account. Invite people in MyKinsta.', [ 'status' => 400 ] );
		}
		// MyKinsta access covers the whole Kinsta site, so the caller has to
		// reach every other site that lives on it (WP Freighter tenants, a
		// second record of the same Kinsta site).
		$shared = self::site_ids_sharing( $site );
		if ( $shared && ! captaincore_verify_permissions( $shared ) ) {
			return new \WP_Error( 'shared_server', 'This site shares its Kinsta site with others, so MyKinsta access has to be arranged with us.', [ 'status' => 400 ] );
		}
		return $site;
	}

	private static function is_house( $site ) {
		if ( empty( $site->provider_id ) ) {
			return true;
		}
		$row = Providers::get( (int) $site->provider_id );
		return ! empty( $row ) && $row->provider === 'kinsta' && (int) $row->user_id === 0;
	}

	/**
	 * Other sites on the same Kinsta site: the same Kinsta id, or the same SSH
	 * user, which is the Kinsta site's name. Inactive ones count, since their
	 * files may still be there. Address and port are left out: staging has its
	 * own port, and a stale record can hold an old address or port.
	 */
	private static function site_ids_sharing( $site ) {
		global $wpdb;
		$b   = $wpdb->base_prefix;
		$ids = $wpdb->get_col( $wpdb->prepare(
			"SELECT site_id FROM {$b}captaincore_sites WHERE provider_site_id = %s AND site_id <> %d
			 UNION
			 SELECT o.site_id FROM {$b}captaincore_environments e
			 JOIN {$b}captaincore_environments o ON o.username = e.username AND o.site_id <> e.site_id
			 WHERE e.site_id = %d AND e.username <> ''
			   AND ( e.server_id IS NULL OR e.server_id <= 0 ) AND ( o.server_id IS NULL OR o.server_id <= 0 )",
			$site->provider_site_id, $site->site_id, $site->site_id
		) );
		return array_values( array_unique( array_map( 'intval', $ids ) ) );
	}

	/**
	 * Confirm with Kinsta that the stored id is this site: our company holds it
	 * and its name (also its SSH user) is the one our record connects with.
	 *
	 * @return string|\WP_Error The company id.
	 */
	private static function verify_link( $site ) {
		Providers\Kinsta::use_site_provider( $site );
		$company = (string) Providers\Kinsta::credentials( 'company_id', $site->provider_id, $site );
		// Older Kinsta companies have a short id instead of a UUID.
		if ( ! preg_match( '/^[A-Za-z0-9-]{1,64}$/', $company ) ) {
			return new \WP_Error( 'kinsta_company', 'The Kinsta connection has no company id.', [ 'status' => 502 ] );
		}
		$remote = Remote\Kinsta::get( "sites/{$site->provider_site_id}" );
		if ( empty( $remote->site ) ) {
			return new \WP_Error( 'kinsta_site', 'Kinsta did not find this site.', [ 'status' => 502 ] );
		}
		$usernames = array_map( 'strtolower', array_column( Environments::where( [ 'site_id' => $site->site_id ] ), 'username' ) );
		if ( (string) ( $remote->site->id ?? '' ) !== (string) $site->provider_site_id
			|| (string) ( $remote->site->company_id ?? '' ) !== $company
			|| ! in_array( strtolower( (string) ( $remote->site->name ?? '' ) ), $usernames, true ) ) {
			return new \WP_Error( 'kinsta_mismatch', 'This site does not match its Kinsta record. Please contact us.', [ 'status' => 409 ] );
		}
		return $company;
	}

	/**
	 * Everyone in the company and every pending invite. Never returned to a
	 * caller: for_site() keeps what belongs to one site.
	 *
	 * @return array|\WP_Error
	 */
	private static function company( $site, $company ) {
		Providers\Kinsta::use_site_provider( $site );
		$users   = Remote\Kinsta::get( "company/{$company}/users" );
		$pending = Remote\Kinsta::get( "company/{$company}/pending-invites" );
		if ( ! isset( $users->company->users ) || ! isset( $pending->company->pending_invitations ) ) {
			return new \WP_Error( 'kinsta_users', 'Kinsta did not return its user list.', [ 'status' => 502 ] );
		}
		return [ 'users' => (array) $users->company->users, 'pending' => (array) $pending->company->pending_invitations ];
	}

	/** The role someone holds on one Kinsta site, or ''. */
	private static function role_on( $capabilities, $kinsta_site_id ) {
		foreach ( (array) $capabilities as $c ) {
			if ( ! empty( $c->site_id ) && strtolower( (string) $c->site_id ) === strtolower( (string) $kinsta_site_id ) ) {
				return (string) ( $c->role ?? '' );
			}
		}
		return '';
	}

	private static function for_site( $site, $company ) {
		$out = [ 'members' => [], 'pending' => [] ];
		foreach ( $company['users'] as $u ) {
			$role = self::role_on( $u->capabilities ?? [], $site->provider_site_id );
			if ( $role !== '' ) {
				$out['members'][] = [ 'email' => (string) ( $u->user->email ?? '' ), 'name' => (string) ( $u->user->full_name ?? '' ), 'role' => $role ];
			}
		}
		foreach ( $company['pending'] as $i ) {
			$role = self::role_on( $i->capabilities ?? [], $site->provider_site_id );
			if ( $role !== '' ) {
				$out['pending'][] = [ 'email' => (string) ( $i->email ?? '' ), 'name' => '', 'role' => $role ];
			}
		}
		return $out;
	}

	/** Members and pending invites of one site. */
	public static function people( $site_id ) {
		$site = self::check( $site_id );
		if ( is_wp_error( $site ) ) {
			return $site;
		}
		$id = self::verify_link( $site );
		if ( is_wp_error( $id ) ) {
			return $id;
		}
		$company = self::company( $site, $id );
		if ( is_wp_error( $company ) ) {
			return $company;
		}
		return self::for_site( $site, $company ) + [ 'also_covers' => self::names( self::site_ids_sharing( $site ) ) ];
	}

	// The caller reaches all of these (check()).
	private static function names( $site_ids ) {
		$names = [];
		foreach ( $site_ids as $id ) {
			$row = Sites::get( $id );
			if ( $row && $row->status === 'active' ) {
				$names[] = $row->name;
			}
		}
		return $names;
	}

	private static function clean_email( $email ) {
		if ( ! is_string( $email ) ) {
			return '';
		}
		$email = strtolower( trim( $email ) );
		return strlen( $email ) <= 190 && is_email( $email ) ? $email : '';
	}

	/** Attempts by the current user in the last day, whatever their outcome. */
	private static function recent_attempts( $actions, $email = '', $site_id = 0 ) {
		global $wpdb;
		$in     = implode( ',', array_fill( 0, count( $actions ), '%s' ) );
		$values = $actions;
		$sql    = "SELECT COUNT(*) FROM {$wpdb->prefix}captaincore_activity_logs WHERE action IN ($in) AND user_id = %d AND created_at > %s";
		array_push( $values, get_current_user_id(), date( 'Y-m-d H:i:s', time() - DAY_IN_SECONDS ) );
		if ( $email !== '' ) {
			$sql     .= ' AND entity_id = %d AND context LIKE %s';
			$values[] = (int) $site_id;
			$values[] = '%' . $wpdb->esc_like( wp_json_encode( $email ) ) . '%';
		}
		return (int) $wpdb->get_var( $wpdb->prepare( $sql, $values ) );
	}

	private static function log( $action, $site, $description, $context ) {
		ActivityLog::log( $action, 'site', $site->site_id, $site->name, $description, $context, $site->customer_id ? $site->customer_id : $site->account_id );
	}

	/** Invite one address to one site. */
	public static function invite( $site_id, $email, $role ) {
		$site = self::check( $site_id );
		if ( is_wp_error( $site ) ) {
			return $site;
		}
		$email = self::clean_email( $email );
		if ( $email === '' ) {
			return new \WP_Error( 'invalid_email', 'Enter one valid email address.', [ 'status' => 400 ] );
		}
		if ( ! is_string( $role ) || ! in_array( $role, self::ROLES, true ) ) {
			return new \WP_Error( 'invalid_role', 'Choose site administrator or site developer.', [ 'status' => 400 ] );
		}
		if ( ! ( new User )->is_admin() && self::recent_attempts( [ 'invited_mykinsta', 'failed_mykinsta_invite' ] ) >= self::DAILY_LIMIT ) {
			return new \WP_Error( 'too_many', 'You have sent a lot of MyKinsta invites today. Try again tomorrow or contact us.', [ 'status' => 429 ] );
		}
		// A double click must not send two invites.
		$lock = 'cc_mykinsta_' . md5( $site->site_id . '|' . $email );
		if ( get_transient( $lock ) ) {
			return new \WP_Error( 'busy', 'An invite to this address is already on its way.', [ 'status' => 429 ] );
		}
		set_transient( $lock, 1, MINUTE_IN_SECONDS );

		$id = self::verify_link( $site );
		if ( is_wp_error( $id ) ) {
			delete_transient( $lock );
			return $id;
		}
		$company = self::company( $site, $id );
		if ( is_wp_error( $company ) ) {
			delete_transient( $lock );
			return $company;
		}

		// Never send a role for someone who already has access: it could lower
		// a co-owner's role, or pull one of our own people down to one site.
		$people = self::for_site( $site, $company );
		foreach ( $company['users'] as $u ) {
			if ( strtolower( (string) ( $u->user->email ?? '' ) ) !== $email ) {
				continue;
			}
			$company_level = array_filter( (array) ( $u->capabilities ?? [] ), function ( $c ) { return empty( $c->site_id ); } );
			if ( $company_level || self::role_on( $u->capabilities ?? [], $site->provider_site_id ) !== '' ) {
				delete_transient( $lock );
				return [ 'status' => 'exists', 'message' => "{$email} already has MyKinsta access to {$site->name}." ] + $people;
			}
		}
		foreach ( $people['pending'] as $p ) {
			if ( strtolower( $p['email'] ) === $email ) {
				delete_transient( $lock );
				return [ 'status' => 'pending', 'message' => "{$email} already has an invite to {$site->name} waiting." ] + $people;
			}
		}

		Providers\Kinsta::use_site_provider( $site );
		$response = Remote\Kinsta::post( "company/{$id}/site-invites", [
			'emails'            => [ $email ],
			'site_capabilities' => [ [ 'site_id' => (string) $site->provider_site_id, 'role' => $role ] ],
		] );
		$accepted = is_object( $response ) && (int) ( $response->status ?? 0 ) >= 200 && (int) $response->status < 300;
		$error    = $accepted ? '' : Providers\Kinsta::error_message( $response );
		$op       = $accepted ? (string) ( $response->operation_id ?? '' ) : '';
		// Kinsta answers 202 and sends the invite a moment later.
		$op_safe = preg_match( '/^[A-Za-z0-9:_-]{1,200}$/', $op );
		for ( $i = 0; $accepted && $op_safe && $i < 4; $i++ ) {
			sleep( 2 );
			$state = Remote\Kinsta::get( "operations/{$op}" );
			$code  = (int) ( $state->status ?? 0 );
			if ( $code === 200 ) {
				break;
			}
			if ( $code >= 500 ) {
				$accepted = false;
				$error    = Providers\Kinsta::error_message( $state );
			}
		}

		$label   = $role === 'site-admin' ? 'site administrator' : 'site developer';
		$context = [ 'email' => $email, 'role' => $role, 'operation_id' => $op ];
		if ( ! $accepted ) {
			delete_transient( $lock );
			self::log( 'failed_mykinsta_invite', $site, "Could not invite {$email} to {$site->name} in MyKinsta", $context + [ 'error' => $error ] );
			return new \WP_Error( 'kinsta_invite_failed', "Kinsta did not send the invite: {$error}", [ 'status' => 502 ] );
		}
		self::log( 'invited_mykinsta', $site, "Invited {$email} to {$site->name} in MyKinsta as {$label}", $context );

		$fresh = self::company( $site, $id );
		$after = is_wp_error( $fresh ) ? $people : self::for_site( $site, $fresh );
		return [ 'status' => 'sent', 'message' => "MyKinsta invite sent to {$email}." ] + $after;
	}

	/** Ask the team to take someone off a site. Kinsta's API cannot. */
	public static function request_removal( $site_id, $email ) {
		$site = self::check( $site_id );
		if ( is_wp_error( $site ) ) {
			return $site;
		}
		$email = self::clean_email( $email );
		if ( $email === '' ) {
			return new \WP_Error( 'invalid_email', 'Enter one valid email address.', [ 'status' => 400 ] );
		}
		if ( self::recent_attempts( [ 'requested_mykinsta_removal' ], $email, $site->site_id ) ) {
			return [ 'message' => "We already have your request to remove {$email}." ];
		}
		if ( ! ( new User )->is_admin() && self::recent_attempts( [ 'requested_mykinsta_removal' ] ) >= self::DAILY_LIMIT ) {
			return new \WP_Error( 'too_many', 'You have sent a lot of requests today. Try again tomorrow or contact us.', [ 'status' => 429 ] );
		}
		$people = self::people( $site_id );
		if ( is_wp_error( $people ) ) {
			return $people;
		}
		$on_site = array_map( 'strtolower', array_column( array_merge( $people['members'], $people['pending'] ), 'email' ) );
		if ( ! in_array( $email, $on_site, true ) ) {
			return new \WP_Error( 'not_on_site', 'That address has no MyKinsta access to this site.', [ 'status' => 404 ] );
		}
		self::log( 'requested_mykinsta_removal', $site, "Requested removal of {$email} from {$site->name} in MyKinsta", [ 'email' => $email ] );
		Mailer::send_kinsta_removal_request( $site, $email, (object) ( new User )->fetch() );
		return [ 'message' => "Removal requested. We'll take {$email} off {$site->name} in MyKinsta." ];
	}
}
