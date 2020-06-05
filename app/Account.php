<?php

namespace CaptainCore;

class Account {

    protected $account_id = "";

    public function __construct( $account_id = "", $admin = false ) {
        if ( ( new User )->verify_accounts( [ $account_id ] ) ) {
            $this->account_id = $account_id;
        }
        if ( $admin ) {
            $this->account_id = $account_id;
        }
    }

    public function get() {
        $account = ( new Accounts )->get( $this->account_id );
        $account->defaults = json_decode( $account->defaults );
        $account->plan     = json_decode( $account->plan );
        $account->metrics  = json_decode( $account->metrics );
        return $account;
    }

    
    public function get_raw() {

        self::calculate_totals();

        // Fetch site from database
        $account = ( new Accounts )->get( $this->account_id );

        // Shared with permissions
        $account_ids   = self::shared_with();
        $account_ids[] = $this->account_id;

        // Fetch relating data
        $account->users   = ( new AccountUser )->where( [ "account_id" => $this->account_id ] );
        $account->domains = ( new AccountDomain )->where( [ "account_id" => $this->account_id ] );
        $account->sites   = ( new AccountSite )->where( [ "account_id" => $this->account_id ] );

        return $account;

    }

    public function sync() {

        $command = "account sync {$this->account_id}";
        
        // Disable https when debug enabled
        if ( defined( 'CAPTAINCORE_DEBUG' ) ) {
            add_filter( 'https_ssl_verify', '__return_false' );
        }

        $data = [ 
            'timeout' => 45,
            'headers' => [
                'Content-Type' => 'application/json; charset=utf-8', 
                'token'        => CAPTAINCORE_CLI_TOKEN 
            ],
            'body'        => json_encode( [ "command" => $command ]), 
            'method'      => 'POST', 
            'data_format' => 'body'
        ];

        // Add command to dispatch server
        $response = wp_remote_post( CAPTAINCORE_CLI_ADDRESS . "/run/background", $data );
        if ( is_wp_error( $response ) ) {
            $error_message = $response->get_error_message();
            return "Something went wrong: $error_message";
        }
        
        return $response["body"];
    }

    public function assign_sites( $site_ids = [] ) {

        $accountsite = new AccountSite();

        // Fetch current records
        $current_site_ids = array_column ( $accountsite->where( [ "account_id" => $this->account_id ] ), "site_id" );

        // Removed current records not found new records.
        foreach ( array_diff( $current_site_ids, $site_ids ) as $site_id ) {
            $records = $accountsite->where( [ "account_id" => $this->account_id, "site_id" => $site_id ] );
            foreach ( $records as $record ) {
                $accountsite->delete( $record->account_site_id );
            }
        }
        
        // Add new records
        foreach ( array_diff( $site_ids, $current_site_ids ) as $site_id ) {
            $accountsite->insert( [ "account_id" => $this->account_id, "site_id" => $site_id ] );
        }

    }

    public function fetch() {
        if ( $this->account_id == "" ) {
            return [];
        }
        $user_id = get_current_user_id();
        $users   = $this->users();
        $record  = [
            "timeline" => $this->process_logs(),
            "account"  => $this->account(),
            "invites"  => $this->invites(),
            "users"    => $users,
            "domains"  => $this->domains(),
            "sites"    => $this->sites(),
            "owner"    => false,
        ];

        foreach ($users as $user) {
            if ($user['user_id'] === $user_id && $user['level'] == "Owner" ) {
                $record["owner"] = true;
            }
        }
        
        return $record;
    }

    public function account() {
        $account          = (new Accounts)->get( $this->account_id );
        $defaults         = json_decode( $account->defaults );
        $plan             = json_decode( $account->plan );
        if ( ! is_array( $defaults->users ) ) {
            $defaults->users = [];
        }
        return [
            "account_id" => $this->account_id,
            "name"       => html_entity_decode( $account->name ),
            "plan"       => $plan,
            "metrics"    => json_decode( $account->metrics ),
            "defaults"   => $defaults,
        ];
    }

    public function invites() {
        $invites = new Invites();
        return $invites->where( [ "account_id" => $this->account_id, "accepted_at" => "0000-00-00 00:00:00" ] );
    }

    public function domains() {
        $account_ids   = self::shared_with();
        $account_ids[] = $this->account_id;
        $results       = ( new AccountDomain )->fetch_domains( [ "account_id" => $account_ids ] );
        return $results;
    }

    public function sites() {
        if ( $this->account_id == "" ) {
            return [];
        }
        // Fetch sites assigned as owners
        $all_site_ids = [];
        $site_ids = array_column( ( new Sites )->where( [ "account_id" => $this->account_id, "status" => "active" ] ), "site_id" );
        foreach ( $site_ids as $site_id ) {
            $all_site_ids[] = $site_id;
        }
        // Fetch sites assigned as shared access
        $site_ids = ( new AccountSite )->select_active_sites( 'site_id', [ "account_id" => $this->account_id ] );
        foreach ( $site_ids as $site_id ) {
            $all_site_ids[] = $site_id;
        }

        $results  = [];
        $all_site_ids = array_unique( $all_site_ids );

        foreach ($all_site_ids as $site_id) {
            $site      = ( new Sites )->get( $site_id );
            $details   = json_decode( $site->details );
            $results[] = [
                "site_id" => $site_id,
                "name"    => $site->name,
                "visits"  => $details->visits,
                "storage" => $details->storage_raw,
            ];
        }
        usort( $results, "sort_by_name" );
        return $results;
    }

    public function process_logs() {
        $Parsedown          = new \Parsedown();
        $site_ids           = array_column( self::sites(), "site_id" );
        $fetch_process_logs = ( new ProcessLogSite )->fetch_process_logs( [ "site_id" => $site_ids ] );
        $process_logs       = [];
        foreach ( $fetch_process_logs as $result ) {
            $sites_for_process     = ( new ProcessLogSite )->fetch_sites_for_process_log( [ "process_log_id" => $result->process_log_id ] );
            // Filter out sites which account doesn't have access to.
            foreach ($sites_for_process as $key => $site) {
                if ( in_array( $site->site_id, $site_ids ) ) {
                    continue;
                }
                unset( $sites_for_process[$key] );
            }
            $websites              = [];
            foreach ($sites_for_process as $site_for_process) {
                $websites[]        = $site_for_process;
            }
            $item                  = ( new ProcessLogs )->get( $result->process_log_id );
            $item->created_at      = strtotime( $item->created_at );
            $item->name            = $result->name;
            $item->description_raw = $item->description;
            $item->description     = $Parsedown->text( $item->description );
            $item->author          = get_the_author_meta( 'display_name', $item->user_id );
            $item->websites        = $websites;
            $process_logs[]        = $item;
        }
        return $process_logs;
    }

    public function shared_with() {
        // Fetch sites assigned as owners
        $all_site_ids = [];
        $site_ids = array_column( ( new Sites )->where( [ "account_id" => $this->account_id, "status" => "active" ] ), "site_id" );
        foreach ( $site_ids as $site_id ) {
            $all_site_ids[] = $site_id;
        }
        // Fetch sites assigned as shared access
        $site_ids = ( new AccountSite )->select_active_sites( 'site_id', [ "account_id" => $this->account_id ] );
        foreach ( $site_ids as $site_id ) {
            $all_site_ids[] = $site_id;
        }

        $all_site_ids = array_unique( $all_site_ids );
        $account_ids  = [];

        foreach ($all_site_ids as $site_id) {
            $account_ids[] = ( new Sites )->get( $site_id )->account_id;
        }
        return array_unique( $account_ids );
    }

    public function users() {
        $permissions = ( new AccountUser )->where( [ "account_id" => $this->account_id ] );
        $results = [];
        foreach( $permissions as $permission ) {
            $user      = get_userdata( $permission->user_id );
            $results[] = [
                "user_id" => $user->ID,
                "name"    => $user->display_name, 
                "email"   => $user->user_email,
                "level"   => ucfirst( $permission->level ),
            ];
        }
        return $results;
    }

    public function usage_breakdown() {
        $account  = self::get();
        $site_ids = array_column( ( new Sites )->where( [ "account_id" => $this->account_id, "status" => "active" ] ), "site_id" );

        $hosting_plan      = $account->plan->name;
		$addons            = $account->plan->usage->addons;
		$storage           = $account->plan->usage->storage;
		$visits            = $account->plan->usage->visits;
		$visits_plan_limit = $account->plan->limits->visits;
		$storage_limit     = $account->plan->limits->storage;
        $sites_limit       = $account->plan->limits->sites;

        if ( isset( $visits ) ) {
			$visits_percent = round( $visits / $visits_plan_limit * 100, 0 );
		}
        
        $storage_gbs     = round( $storage / 1024 / 1024 / 1024, 1 );
		$storage_percent = round( $storage_gbs / $storage_limit * 100, 0 );

		$result_sites = [];

        foreach ( $site_ids as $site_id ) {
            $site                         = ( new Site( $site_id ))->get();
            $website_for_customer_storage = $site->storage_raw;
            $website_for_customer_visits  = $site->visits;
            $result_sites[] = [
                'name'    => $site->name,
                'storage' => round( $website_for_customer_storage / 1024 / 1024 / 1024, 1 ),
                'visits'  => $website_for_customer_visits
            ];
        }

        return [ 
            'sites' => $result_sites,
            'total' => [
                "{$storage_percent}% storage<br /><strong>{$storage_gbs}GB/{$storage_limit}GB</strong>",
                "{$visits_percent}% traffic<br /><strong>" . number_format( $visits ) . "</strong> <small>Yearly Estimate</small>"
            ]
        ];
        
    }

    public function invite( $email ) {

        // Add account ID to current user
        if ( email_exists( $email ) ) {
            $user        = get_user_by( 'email', $email );
            $accounts    = array_column( ( new AccountUser )->where( [ "user_id" => $user->ID ] ), "account_id" );
            $accounts[]  = $this->account_id;
            ( new User( $user->ID, true ) )->assign_accounts( array_unique( $accounts ) );
            $this->calculate_totals();
            return [ "message" => "Account already exists. Adding permissions for existing user." ];
        }

        $time_now   = date("Y-m-d H:i:s");
        $token      = bin2hex( openssl_random_pseudo_bytes( 24 ) );
        $new_invite = [
            'email'          => $email,
            'account_id'     => $this->account_id,
            'created_at'     => $time_now,
            'updated_at'     => $time_now,
            'token'          => $token
        ];
        $invite    = new Invites();
        $invite_id = $invite->insert( $new_invite );

        // Send out invite email
        $invite_url = home_url() . "/account/?account={$this->account_id}&token={$token}";
        $name    = ( new Accounts )->get( $this->account_id )->name;
        $subject = "Hosting account invite";
        $body    = "You've been granted access to account '$name'. Click here to accept:<br /><br /><a href=\"{$invite_url}\">$invite_url</a>";
        $headers = [ 'Content-Type: text/html; charset=UTF-8' ];

        wp_mail( $email, $subject, $body, $headers );

        return [ "message" => "Invite has been sent." ];
    }

    public function calculate_totals() {
        $metrics = [ 
            "sites"   => count( $this->sites() ),
            "users"   => count( $this->users() ),
            "domains" => count( $this->domains() ),
        ];
        ( new Accounts )->update( [ "metrics" => json_encode( $metrics ) ], [ "account_id" => $this->account_id ] );
        return [ "message" => "Account metrics updated." ];
    }

    public function calculate_usage() {
        $account  = self::get();
        $sites    = $this->sites();
        $account->plan->usage->storage = array_sum ( array_column( $sites, "storage" ) );
        $account->plan->usage->visits  = array_sum ( array_column( $sites, "visits" ) );
        $account->plan->usage->sites   = count( $sites );
        ( new Accounts )->update( [ "plan" => json_encode( $account->plan ) ], [ "account_id" => $this->account_id ] );
    }
}