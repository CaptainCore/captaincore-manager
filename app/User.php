<?php

namespace CaptainCore;

class User {

    protected $user_id = "";
    protected $roles   = "";

    public function __construct( $user_id = "", $admin = false ) {
        if ( $admin ) {
            $this->user_id = $user_id;
            $user_meta     = get_userdata( $this->user_id );
            $this->roles   = $user_meta->roles;
            return;
        }
        $this->user_id = get_current_user_id();
        $user_meta     = get_userdata( $this->user_id );
        $this->roles   = $user_meta->roles;
    }

    public function accounts() {
        $accountuser = new AccountUser();
        $accounts    = array_column( $accountuser->where( [ "user_id" => $this->user_id ] ), "account_id" );
        return $accounts;
    }

    public function verify_accounts( $account_ids = [] ) {
        if ( self::is_admin() ) {
            return true;
        }
        $ids = self::accounts();
        foreach( $account_ids as $account_id ) {
            if ( ! in_array( $account_id, $ids ) ) {
                return false;
            }
        }
        return true;
    }

    public function verify_account_owner( $account_id ) {

        if ( self::is_admin() ) {
            return true;
        }

        $users = ( new Account( $account_id, true ) )->users();

        foreach ($users as $user) {
            if ( $user['user_id'] === $this->user_id && $user['level'] == "Owner" ) {
                return true;
            }
        }
        return false;
    }

    public function roles() {
        return $this->roles;
    }

    public function role_check() {
        if ( ! is_array( $this->roles ) ) {
            return false;
        }
        $role_check = in_array( 'subscriber', $this->roles ) + in_array( 'customer', $this->roles ) + in_array( 'administrator', $this->roles ) + in_array( 'editor', $this->roles );
        return $role_check;
    }

    public function is_admin() {
        if ( is_array( $this->roles ) && in_array( 'administrator', $this->roles ) ) {
            return true;
        }
        return false;
    }

    public function insert_accounts( $account_ids = [] ) {

        $accountuser = new AccountUser();

        foreach( $account_ids as $account_id ) {

            // Fetch current records
            $lookup = $accountuser->where( [ "user_id" => $this->user_id, "account_id" => $account_id ] );

            // Add new record
            if ( count($lookup) == 0 ) {
                $accountuser->insert( [ "user_id" => $this->user_id, "account_id" => $account_id ] );
            }

        }

    }

    public function assign_accounts( $account_ids = [] ) {

        $accountuser = new AccountUser();

        // Fetch current records
        $current_account_ids = array_column ( $accountuser->where( [ "user_id" => $this->user_id ] ), "account_id" );

        // Removed current records not found new records.
        foreach ( array_diff( $current_account_ids, $account_ids ) as $account_id ) {
            $records = $accountuser->where( [ "user_id" => $this->user_id, "account_id" => $account_id ] );
            foreach ( $records as $record ) {
                $accountuser->delete( $record->account_user_id );
            }
        }

        // Add new records
        foreach ( array_diff( $account_ids, $current_account_ids ) as $account_id ) {
            $accountuser->insert( [ "user_id" => $this->user_id, "account_id" => $account_id ] );
        }

    }

    public function fetch() {
        $user     = get_user_by( "ID", $this->user_id );
        $record = [
            "user_id"     => $this->user_id,
            "account_ids" => $this->accounts(),
            "username"    => $user->user_login,
            "email"       => $user->user_email,
            "name"        => $user->display_name,
        ];
        return $record;
    }

    public function fetch_requested_sites() {
        $requested_sites = get_user_meta( $this->user_id, 'requested_sites', true );
        if ( self::is_admin() ) {
            $requested_sites = ( new Users )->requested_sites();
        }
        if ( empty( $requested_sites ) ) {
            $requested_sites = [];
        }
        foreach ( $requested_sites as $key => $requested_site ) {
            $requested_sites[ $key ] = (object) $requested_site;
            $requested_sites[ $key ]->show = false;
        }
        return $requested_sites;
    }

    public function requested_sites() {
        $requested_sites = get_user_meta( $this->user_id, 'requested_sites', true );
        if ( empty( $requested_sites ) ) {
            $requested_sites = [];
        }
        foreach ( $requested_sites as $key => $requested_site ) {
            $requested_sites[ $key ] = (object) $requested_site;
            $requested_sites[ $key ]->show = false;
        }
        return $requested_sites;
    }

    public function request_site( $site ) {
        $requested_sites   = self::requested_sites();
        $requested_sites[] = $site;
        update_user_meta( $this->user_id, 'requested_sites', $requested_sites );
        $site    = (object) $site;
        $user    = (object) self::fetch();
        $account = ( new Accounts )->get( $site->account_id );
        $body    = "{$user->name} is requesting a new site <strong>'{$site->name}'</strong> for account '{$account->name}'.";
        if ( $site->notes != "" ) {
            $body = "$body<br /><br />Message from {$user->name}:<br />{$site->notes}";
        }
        
        // Send out admin email notice
		$to      = get_option('admin_email');
		$subject = "Request new site '{$site->name}' from {$user->name}";
		$headers = [ 'Content-Type: text/html; charset=UTF-8' ];
		wp_mail( $to, $subject, $body, $headers );
    }

    public function update_request_site( $site ) {
        $site    = (object) $site;
        $user_id = get_current_user_id();
        if ( self::is_admin() ) {
            $user_id = $site->user_id;
        }

        $requested_sites   = ( new self( $user_id, true ) )->requested_sites();
        foreach( $requested_sites as $key => $request ) {
            if ( $request->created_at == $site->created_at ) {
                $requested_sites[ $key ] = $site;
            }
        }
        update_user_meta( $user_id, 'requested_sites', array_values( $requested_sites ) );
    }

    public function back_request_site( $site ) {

        $site       = (object) $site;
        $site->step = $site->step - 1;
        if ( $site->step == 1 ) {
            unset( $site->processing_at );
            unset( $site->ready_at );
        }
        if ( $site->step == 2 ) {
            $site->processing_at = time();
            unset( $site->ready_at );
        }
        if ( $site->step == 3 ) {
            $site->ready_at = time();
        }
        $user_id = get_current_user_id();
        if ( self::is_admin() ) {
            $user_id = $site->user_id;
        }

        $requested_sites   = ( new self( $user_id, true ) )->requested_sites();
        foreach( $requested_sites as $key => $request ) {
            if ( $request->created_at == $site->created_at ) {
                $requested_sites[ $key ] = $site;
            }
        }
        update_user_meta( $user_id, 'requested_sites', array_values( $requested_sites ) );
    }

    public function continue_request_site( $site ) {

        $site       = (object) $site;
        $site->step = $site->step + 1;
        if ( $site->step == 1 ) {
            unset( $site->processing_at );
            unset( $site->ready_at );
        }
        if ( $site->step == 2 ) {
            $site->processing_at = time();
            unset( $site->ready_at );
        }
        if ( $site->step == 3 ) {
            $site->ready_at = time();
        }
        $user_id = get_current_user_id();
        if ( self::is_admin() ) {
            $user_id = $site->user_id;
        }

        $requested_sites   = ( new self( $user_id, true ) )->requested_sites();
        foreach( $requested_sites as $key => $request ) {
            if ( $request->created_at == $site->created_at ) {
                $requested_sites[ $key ] = $site;
            }
        }
        update_user_meta( $user_id, 'requested_sites', array_values( $requested_sites ) );
    }

    public function delete_request_site( $site ) {

        $site    = (object) $site;
        $user_id = get_current_user_id();
        if ( self::is_admin() ) {
            $user_id = $site->user_id;
        }

        $requested_sites   = ( new self( $user_id, true ) )->requested_sites();
        foreach( $requested_sites as $key => $request ) {
            if ( $request->created_at == $site->created_at ) {
                unset( $requested_sites[ $key ] );
            }
        }
        update_user_meta( $user_id, 'requested_sites', array_values( $requested_sites ) );
    }

    public function delete_requested_sites() {
        delete_user_meta( $this->user_id, 'requested_sites' );
    }

}