<?php 

namespace CaptainCore;

class Users {

    public function __construct( $users = [] ) {
        $user        = new User;

        // Bail if not an administrator
        if ( ! $user->is_admin() ) {
            return 'Error: Please log in.';
        }
        
    }

    public function list() {
        $users       = [];
        $fetch_users = get_users();
        foreach( $fetch_users as $user ) {
            $record = [
                "user_id"  => $user->ID,
                "name"     => $user->display_name,
                "email"    => $user->user_email,
                "username" => $user->user_login,
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