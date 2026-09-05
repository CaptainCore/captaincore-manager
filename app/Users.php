<?php 

namespace CaptainCore;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Users {

    /** Whether the caller may read or write the fleet-wide user directory. */
    private $authorized = false;

    public function __construct( $users = [] ) {
        // A constructor's return value is discarded, so the old `return
        // 'Error: Please log in.'` here gated nothing at all - the object came
        // back fully usable and list() would hand every WordPress user's name,
        // email, login and roles to any caller. Sites and Accounts get away
        // with the same shape only because their early return leaves the
        // property their accessors read empty; list() reads nothing from $this.
        // Keep the decision as state and honour it in every method.
        $this->authorized = ( new User )->is_admin();
    }

    public function list() {
        if ( ! $this->authorized ) {
            return [];
        }
        $users       = [];
        $fetch_users = get_users();
        // One pass over the account-user pivot table — a per-user accounts()
        // lookup here would be an N+1 across the entire fleet user list.
        $account_map = [];
        foreach ( AccountUser::all() as $row ) {
            $account_map[ $row->user_id ][] = (int) $row->account_id;
        }
        foreach( $fetch_users as $user ) {
            $record = [
                "user_id"     => $user->ID,
                "name"        => $user->display_name,
                "first_name"  => $user->first_name,
                "last_name"   => $user->last_name,
                "email"       => $user->user_email,
                "username"    => $user->user_login,
                "roles"       => $user->roles,
                "created_at"  => $user->user_registered,
                "account_ids" => isset( $account_map[ $user->ID ] ) ? $account_map[ $user->ID ] : [],
            ];
            if ( class_exists( 'user_switching' ) ) {
                $wp_user = new \WP_User( $user->ID );
                $url     = \user_switching::maybe_switch_url( $wp_user );
                if ( $url ) {
                    $record["switch_to_url"] = html_entity_decode( $url );
                }
            }
            $users[] = $record;
        }
        return $users;
    }

    public function requested_sites() {
        if ( ! $this->authorized ) {
            return [];
        }
        $results     = [];
        $fetch_users = get_users();
        foreach( $fetch_users as $user ) {
            $requested_sites = ( new User( $user->ID, true ) )->requested_sites();
            foreach( $requested_sites as $requested_site ) {
                $requested_site = (object) $requested_site;
                $requested_site->user_id = $user->ID;
                $results[]               = $requested_site;
            }
        }
        return $results;
    }

    public function update( $user ) {
        if ( ! $this->authorized ) {
            return [];
        }
        $user         = (object) $user;
        $user->errors = [];

		if ( $user->name == "" ) {
			$user->errors[] = "Name can't be empty.";
		}

		if ( ! filter_var( $user->email, FILTER_VALIDATE_EMAIL ) ) {
			$user->errors[] = "Email address is not valid.";
        }

        $update_user = wp_update_user( [
            'ID'           => $user->user_id,
            'display_name' => $user->name,
            'first_name'   => $user->first_name,
            'last_name'    => $user->last_name,
            'user_email'   => $user->email,
        ] );

        if ( is_wp_error( $update_user ) ) {
            $user->errors[] = $update_user->get_error_message();;
        }
        
        if ( count( $user->errors ) > 0 ){
            return $user;
        }
        
        unset( $user->errors );

        if ( empty(  $user->account_ids ) ) {
            $user->account_ids = [];
        }

        // No errors, update account IDs.
        ( new User( $user->user_id, true ) )->assign_accounts( $user->account_ids );
		
        return $user;
    }

}
