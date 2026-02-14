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
        if ( empty( $this->account_id ) ) {
            return;
        }
        $account = ( new Accounts )->get( $this->account_id );
        $account->defaults = empty( $account->defaults ) ? (object) [] : json_decode( $account->defaults );
        $account->plan     = empty( $account->plan ) ? (object) [] : json_decode( $account->plan );
        $account->metrics  = empty( $account->metrics ) ? (object) [] : json_decode( $account->metrics );
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


    public function invoices() {
        $invoices       = wc_get_orders( [ 'meta_key' => 'captaincore_account_id', 'meta_value' => $this->account_id, 'posts_per_page' => "-1" ] );
        foreach ( $invoices as $key => $invoice ) {
            $order      = wc_get_order( $invoice );
            $item_count = $order->get_item_count();
            $invoices[$key]  = [
                "order_id" => $order->id,
                "name"     => get_the_author_meta( 'display_name', $order->user_id ),
                "date"     => wc_format_datetime( $order->get_date_created() ),
                "status"   => wc_get_order_status_name( $order->get_status() ),
                "total"    => $order->get_total(),
            ];
        }
        return $invoices;
    }

    public function fetch() {
        if ( $this->account_id == "" ) {
            return [];
        }
        $user_id = get_current_user_id();
        $account = $this->account();
        $record  = [
            "timeline"        => $this->process_logs(),
            "account"         => $account,
            "invites"         => $this->invites(),
            "users"           => $this->users(),
            "domains"         => $this->domains(),
            "sites"           => $this->sites(),
            "usage_breakdown" => $this->usage_breakdown(),
            "owner"           => false,
        ];

        if ( $user_id == $account["plan"]->billing_user_id ) {
            $record["owner"] = true;
        }

        if ( ( new User )->is_admin() ) {
            $record["invoices"] = $this->invoices();
        }
        
        return $record;
    }

    public function account() {
        $account               = Accounts::get( $this->account_id );
        $defaults              = json_decode( $account->defaults );
        $plan                  = empty( $account->plan ) ? (object) [] : json_decode( $account->plan );
        $plan->name            = empty( $plan->name ) ? "" : $plan->name;
        $plan->addons          = empty( $plan->addons ) ? [] : $plan->addons;
        $plan->charges         = empty( $plan->charges ) ? [] : $plan->charges;
        $plan->credits         = empty( $plan->credits ) ? [] : $plan->credits;
        $plan->limits          = empty( $plan->limits ) ? (object) [ "storage" => 0, "visits" => 0, "sites" => 0 ] : $plan->limits;
        $plan->interval        = empty( $plan->interval ) ? "12" : $plan->interval;
        $plan->billing_user_id = empty( $plan->billing_user_id ) ? 0 : (int) $plan->billing_user_id;
        // Patch in maintenance only sites
        $sites                 = Sites::where( [ "account_id" => $this->account_id, "status" => "active" ] );
        $maintenance_sites     = [];
        foreach ( $sites as $site ) {
            if ( ! empty( $site->provider_id ) && ( $site->provider_id != "1" ) ) {
                $maintenance_sites[] = $site;
            }
        }
        if ( ! empty( $maintenance_sites ) ) {
            $maintenance_sites_addons = (object) [
                "name"     => "Managed WordPress sites",
                "price"    => 2,
                "quantity" => count( $maintenance_sites ),
                "required" => true
            ];
            array_unshift($plan->addons, $maintenance_sites_addons );
        }
        if ( ! is_array( $defaults->users ) ) {
            $defaults->users = [];
        }
        return [
            "account_id"      => $this->account_id,
            "name"            => html_entity_decode( $account->name ),
            "plan"            => $plan,
            "metrics"         => json_decode( $account->metrics ),
            "defaults"        => $defaults,
        ];
    }

    public function invites() {
        $invites = new Invites();
        return $invites->where( [ "account_id" => $this->account_id, "accepted_at" => "0000-00-00 00:00:00" ] );
    }

    public function domains() {
        $account_ids   = self::shared_with();
        if ( ! empty ( $this->account_id ) ) {
            $account_ids[] = $this->account_id;
        }
        $results       = ( new AccountDomain )->fetch_domains( [ "account_id" => $account_ids ] );
        return $results;
    }

    public function billing_sites() {
        if ( $this->account_id == "" ) {
            return [];
        }
        // Fetch sites assigned as owners
        $site_ids            = [];
        $maintenance_site_id = [];
        $sites = Sites::where( [ "account_id" => $this->account_id, "status" => "active" ] );
        foreach ( $sites as $site ) {
            if ( empty ( $site->provider_id ) || $site->provider_id == "1" ) {
                $site_ids[] = $site->site_id;
                continue;
            }
            $maintenance_site_id[] = $site->site_id;
        }

        $results  = [];
        $site_ids = array_unique( $site_ids );

        foreach ($site_ids as $site_id) {
            $site      = Sites::get( $site_id );
            $details   = json_decode( $site->details );
            $results[] = [
                "site_id" => $site_id,
                "name"    => $site->name,
                "visits"  => $details->visits,
                "storage" => $details->storage,
            ];
        }
        usort( $results, "sort_by_name" );
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
        // Fetch customer sites
        $site_ids = array_column( ( new Sites )->where( [ "customer_id" => $this->account_id, "status" => "active" ] ), "site_id" );
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
                "storage" => $details->storage,
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
            $item->author_avatar   = "https://www.gravatar.com/avatar/" . md5( get_the_author_meta( 'email', $item->user_id ) ) . "?s=80&d=mp";
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
            $account_ids[] = ( new Sites )->get( $site_id )->customer_id;
        }
        return array_unique( $account_ids );
    }

    public function users() {
        $permissions = ( new AccountUser )->where( [ "account_id" => $this->account_id ] );
        $results = [];
        foreach( $permissions as $permission ) {
            $user = get_userdata( $permission->user_id );
            if ( ! $user ) {
                continue;
            }
            $results[] = [
                "user_id" => $user->ID,
                "name"    => $user->display_name,
                "email"   => $user->user_email,
                "level"   => empty( $permission->level ) ? "" : ucfirst( $permission->level ),
            ];
        }
        return $results;
    }

    public function usage_breakdown() {
        $account  = self::get();
        if ( ! $account ) {
            return [];
        }
        $site_ids = array_column( ( new Sites )->where( [ "account_id" => $this->account_id, "status" => "active" ] ), "site_id" );

        $plan              = empty( $account->plan ) ? (object) [] : ( is_string( $account->plan ) ? json_decode( $account->plan ) : $account->plan );
        $plan->name        = empty( $plan->name ) ? "" : $plan->name;
        $plan->limits      = empty( $plan->limits ) ? (object) [ "storage" => 0, "visits" => 0, "sites" => 0 ] : $plan->limits;
        $hosting_plan      = $plan->name;
		$addons            = empty( $plan->usage->addons ) ? [] : $plan->usage->addons;
		$storage           = empty( $plan->usage->storage ) ? 0 : $plan->usage->storage;
		$visits            = empty( $plan->usage->visits ) ? 0 : $plan->usage->visits;
		$visits_plan_limit = empty( $plan->limits->visits ) ? 0 : $plan->limits->visits;
		$storage_limit     = empty( $plan->limits->storage ) ? 0 : $plan->limits->storage;
        $sites_limit       = empty( $plan->limits->sites ) ? 0 : $plan->limits->sites;

        if ( ! empty( $visits ) && ! empty( $visits_plan_limit ) ) {
			$visits_percent = round( $visits / $visits_plan_limit * 100, 0 );
		}
        
        $storage_gbs     = round( $storage / 1024 / 1024 / 1024, 1 );
		$storage_percent = empty ( $storage_limit ) ? 0 : round( $storage_gbs / $storage_limit * 100, 0 );

		$result_sites             = [];
		$result_maintenance_sites = [];

        foreach ( $site_ids as $site_id ) {
            $site                         = ( new Site( $site_id ))->fetch();
            $website_for_customer_storage = empty( $site->storage ) ? 0 : $site->storage;
            $website_for_customer_visits  = empty( $site->visits ) ? 0 : $site->visits;
            if ( empty( $site->provider_id ) || $site->provider_id == "1" ) {
                $result_sites[] = [
                    'site_id' => $site->site_id,
                    'name'    => $site->name,
                    'storage' => $website_for_customer_storage,
                    'visits'  => $website_for_customer_visits
                ];
                continue;
            }
            $result_maintenance_sites[] = [
                'site_id' => $site->site_id,
                'name'    => $site->name,
                'storage' => $website_for_customer_storage,
                'visits'  => $website_for_customer_visits
            ];
        }

        if ( empty( $visits_percent ) ) {
			$visits_percent = 0;
		}

        return [ 
            'sites'             => $result_sites,
            'maintenance_sites' => $result_maintenance_sites,
            'total' => [
                "{$storage_percent}% storage<br /><strong>{$storage_gbs}GB/{$storage_limit}GB</strong>",
                "{$visits_percent}% traffic<br /><strong>" . number_format( $visits ) . "</strong> <small>Yearly Estimate</small>"
            ]
        ];
        
    }

    public function invite_delete( $invite_id ) {
        $invite  = ( new Invites )->get( $invite_id );
        if ( $invite->account_id == $this->account_id ) {
            ( new Invites )->delete( $invite_id );
        }
    }

    public function invite( $email ) {

        // Add account ID to current user
        if ( email_exists( $email ) ) {
            $user        = get_user_by( 'email', $email );
            $accounts    = array_column( ( new AccountUser )->where( [ "user_id" => $user->ID ] ), "account_id" );
            $accounts[]  = $this->account_id;
            ( new User( $user->ID, true ) )->assign_accounts( array_unique( $accounts ) );
            $this->calculate_totals();

            // --- Send Access Notification to Existing User ---
            $account_rec = ( new Accounts )->get( $this->account_id );
            $sites       = $this->sites();
            $domains     = $this->domains();
            
            \CaptainCore\Mailer::send_access_granted_notification( $email, $account_rec->name, $sites, $domains );

            ActivityLog::log( 'invited', 'account', $this->account_id, $account_rec->name ?? '', "Invited user to account" );

            return [ "message" => "Account already exists. Access granted and notification sent." ];
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
        $configurations = Configurations::get();
        $invite_url     = home_url() . $configurations->path . "?account={$this->account_id}&token={$token}";
        $account_name   = ( new Accounts )->get( $this->account_id )->name;
        
        \CaptainCore\Mailer::send_invite_new_user( $email, $account_name, $invite_url );

        ActivityLog::log( 'invited', 'account', $this->account_id, $account_name ?? '', "Invited user to account" );

        return [ "message" => "Invite has been sent." ];
    }

    public function calculate_totals() {
        $metrics = [ 
            "sites"                => count( $this->billing_sites() ),
            "users"                => count( $this->users() ),
            "domains"              => count( $this->domains() ),
            "outstanding_invoices" => count( $this->outstanding_invoices() )
        ];
        ( new Accounts )->update( [ "metrics" => json_encode( $metrics ) ], [ "account_id" => $this->account_id ] );
        return [ "message" => "Account metrics updated." ];
    }

    public function calculate_usage() {
        $account  = self::get();
        
        if ( empty( $account ) ) {
            return;
        }

        $sites    = $this->billing_sites();

        if ( empty( $account->plan ) || ( is_object( $account->plan ) && empty( get_object_vars( $account->plan ) ) ) ) {
            $account->plan = (object) [ "usage" => (object) [ "storage" => "", "visits" => "", "sites" => "" ] ];
        }

        // Initialize totals
        $total_storage = 0;
        $total_visits  = 0;
    
        // Loop through each site to sum up production usage
        foreach ( $sites as $site_data ) {
            $site_id = $site_data['site_id'];
            // Fetch all environments for the current site
            $environments = ( new Environments )->where( [ "site_id" => $site_id ] );
            
            // Loop through environments to find and sum production data
            foreach ( $environments as $environment ) {
                if ( $environment->environment == "Production" ) {
                    $total_storage += (int) $environment->storage;
                    $total_visits  += (int) $environment->visits;
                }
            }
        }
    
        // Update the plan object with the calculated totals.
        $account->plan->usage->storage = $total_storage;
        $account->plan->usage->visits  = $total_visits;
        $account->plan->usage->sites   = count( $sites );
        Accounts::update( [ "plan" => json_encode( $account->plan ) ], [ "account_id" => $this->account_id ] );
    }
        
    public function process_renewals() {
        $response = [];
		$accounts = ( new Accounts )->select( 'account_id' );
		foreach ( $accounts as $account_id ) {
			$account = ( new Accounts )->get( $account_id );
            $plan    = json_decode( $account->plan );
            if ( $plan->next_renewal != "" ) {
                $result  = (object) [
                    'account_id' => $account->account_id,
                    'renewal'    => $plan->next_renewal,
                    'plan'       => (int) $plan->billing_user_id,
                ];
            }
			$response[] = $result;
		}
		
		return $response;
	}

    public function failed_notify( $order_id = "" ) {
        // If we have a specific order ID, send the Failed Payment notice.
        if ( ! empty( $order_id ) ) {
            $order = wc_get_order( $order_id );
            $order->add_order_note( "Sent failed payment notice." );
            \CaptainCore\Mailer::send_failed_payment_notice( $this->account_id, [ $order ] );
            return;
        }

        // Fallback for legacy calls without ID (grab the latest failed order)
        $orders = wc_get_orders( [
            'limit'      => 1,
            'meta_key'   => 'captaincore_account_id',
            'meta_value' => $this->account_id,
            'orderby'    => 'date',
            'order'      => 'DESC',
            'status'     => 'failed'
        ] );

        if ( count( $orders ) > 0 ) {
            $order = $orders[0];
            $order->add_order_note( "Sent failed payment notice." );
            \CaptainCore\Mailer::send_failed_payment_notice( $this->account_id, [ $order ] );
        }
    }

    public function generate_order() {
        $configurations = ( new Configurations )->get();
        $account        = ( new Accounts )->get( $this->account_id );
        $plan           = json_decode( $account->plan );
        
        // 1. Handle Auto-Switch (adjust plan before billing if needed)
        if ( isset( $plan->auto_switch ) && $plan->auto_switch == "true" ) {
            self::auto_switch_plan();
            // Re-fetch account details after switch
            $account = ( new Accounts )->get( $this->account_id );
            $plan    = json_decode( $account->plan );
        }
        
        // 2. Validate billing user before creating order
        if ( empty( $plan->billing_user_id ) || ! get_user_by( 'ID', $plan->billing_user_id ) ) {
            Mailer::send_missing_billing_user_alert( $this->account_id, $account->name ?? "Account #{$this->account_id}" );
            return;
        }

        // 3. Formatting Helpers
        $units     = [ 1 => "monthly", 3 => "quarterly", 6 => "biannually", 12 => "yearly" ];
        $plan_interval = ! empty( $plan->interval ) ? $units[ $plan->interval ] : '';

        // 4. Create WooCommerce Order
        $customer = new \WC_Customer( $plan->billing_user_id );
        $address  = $customer->get_billing();
        $order    = wc_create_order( [ 'customer_id' => $plan->billing_user_id ] );
        $order->update_meta_data( "captaincore_account_id", $this->account_id );
        $order->save();

        // Helper for billing sites list
        $billing_sites = self::billing_sites();
        $site_names    = array_column( $billing_sites, "name" );
        sort( $site_names );
        $site_names_str = implode( ", ", $site_names );

        if ( ! empty( $address ) ) {
            $order->set_address( $address, 'billing' );
        }

        $calculated_total = 0;
        $billing_mode = isset( $plan->billing_mode ) ? $plan->billing_mode : 'standard';

        // 4. Add Line Items: Hosting Plan
        if ( $billing_mode === 'per_site' ) {
            // Per Site Billing
            $site_count     = count( $billing_sites );
            $price_per_site = (float) $plan->price;
            $plan_total     = $price_per_site * $site_count;

            $line_item_id = $order->add_product( get_product( $configurations->woocommerce->hosting_plan ), 1 );
            $order->get_items()[ $line_item_id ]->set_subtotal( $plan_total );
            $order->get_items()[ $line_item_id ]->set_total( $plan_total );
            
            $details_text = "Billing Per Site\n{$site_count} sites @ $" . number_format($price_per_site, 2) . " / {$plan_interval}\n\n{$site_names_str}";

            $order->get_items()[ $line_item_id ]->add_meta_data( "Details", $details_text );
            $order->get_items()[ $line_item_id ]->save();

            $calculated_total = $plan_total;

        } else {
            // Standard Billing
            $line_item_id = $order->add_product( get_product( $configurations->woocommerce->hosting_plan ), 1 );
            $order->get_items()[ $line_item_id ]->set_subtotal( $plan->price );
            $order->get_items()[ $line_item_id ]->set_total( $plan->price );
            
            $details_text = ! empty( $plan->interval ) ? "{$plan->name} - $plan_interval\n\n{$site_names_str}" : "{$plan->name}\n\n{$site_names_str}";
            $order->get_items()[ $line_item_id ]->add_meta_data( "Details", $details_text );
            $order->get_items()[ $line_item_id ]->save();
            
            $calculated_total = $plan->price;
        }

        // 5. Add Line Items: Maintenance Sites
        $sites = Sites::where( [ "account_id" => $this->account_id, "status" => "active" ] );
        $maintenance_sites = [];
        foreach ( $sites as $site ) {
            if ( ! empty( $site->provider_id ) && ( $site->provider_id != "1" ) ) {
                $maintenance_sites[] = $site;
            }
        }
        if ( ! empty( $maintenance_sites ) ) {
            $maintenance_sites_addons = (object) [
                "name"     => "Managed WordPress sites",
                "price"    => 2,
                "quantity" => count( $maintenance_sites ),
                "required" => true
            ];
            // Prepend to addons array for processing below
            if ( ! isset( $plan->addons ) ) $plan->addons = [];
            array_unshift( $plan->addons, $maintenance_sites_addons );
        }

        // 6. Add Line Items: Addons
        if ( ! empty( $plan->addons ) ) {
            foreach ( $plan->addons as $item ) {
                $line_item_id = $order->add_product( get_product( $configurations->woocommerce->addons ), $item->quantity );
                $order->get_items()[ $line_item_id ]->set_subtotal( $item->price * $item->quantity );
                $order->get_items()[ $line_item_id ]->set_total( $item->price * $item->quantity );
                $order->get_items()[ $line_item_id ]->add_meta_data( "Details", $item->name );
                $order->get_items()[ $line_item_id ]->save();
                $calculated_total += ( (float) $item->price * (int) $item->quantity );
            }
        }

        // 7. Add Line Items: Charges
        if ( ! empty( $plan->charges ) ) {
            foreach ( $plan->charges as $item ) {
                $line_item_id = $order->add_product( get_product( $configurations->woocommerce->charges ), $item->quantity );
                $order->get_items()[ $line_item_id ]->set_subtotal( $item->price * $item->quantity );
                $order->get_items()[ $line_item_id ]->set_total( $item->price * $item->quantity );
                $order->get_items()[ $line_item_id ]->add_meta_data( "Details", $item->name );
                $order->get_items()[ $line_item_id ]->save();
                $calculated_total += ( $item->price * $item->quantity );
            }
        }

        // 8. Add Line Items: Credits
        if ( ! empty( $plan->credits ) ) {
            foreach ( $plan->credits as $item ) {
                $line_item_id = $order->add_product( get_product( $configurations->woocommerce->credits ), $item->quantity );
                $credit_amt   = -1 * abs( $item->price * $item->quantity );
                $order->get_items()[ $line_item_id ]->set_subtotal( $credit_amt );
                $order->get_items()[ $line_item_id ]->set_total( $credit_amt );
                $order->get_items()[ $line_item_id ]->add_meta_data( "Details", $item->name );
                $order->get_items()[ $line_item_id ]->save();
                $calculated_total += $credit_amt;
            }
        }

        // 9. Add Line Items: Usage Overages (Standard Mode Only)
        if ( $billing_mode !== 'per_site' ) {
            // Sites Overage
            if ( $plan->usage->sites > $plan->limits->sites ) {
                $price    = $configurations->usage_pricing->sites->cost;
                if ( $plan->interval != $configurations->usage_pricing->sites->interval ) {
                    $price = $configurations->usage_pricing->sites->cost / ($configurations->usage_pricing->sites->interval / $plan->interval );
                }
                $quantity     = (int) $plan->usage->sites - (int) $plan->limits->sites;
                $total        = $price * $quantity;
                $line_item_id = $order->add_product( get_product( $configurations->woocommerce->usage ), $quantity );
                $order->get_items()[ $line_item_id ]->set_subtotal( $total );
                $order->get_items()[ $line_item_id ]->set_total( $total );
                $order->get_items()[ $line_item_id ]->add_meta_data( "Details", "Extra Sites" );
                $order->get_items()[ $line_item_id ]->save();
                $calculated_total += $total;
            }

            // Storage Overage
            if ( (int) $plan->usage->storage > ( (int) $plan->limits->storage * 1024 * 1024 * 1024 ) ) {
                $price    = $configurations->usage_pricing->storage->cost;
                if ( $plan->interval != $configurations->usage_pricing->storage->interval ) {
                    $price = $configurations->usage_pricing->storage->cost / ( $configurations->usage_pricing->storage->interval / $plan->interval );
                }
                $extra_storage = ( $plan->usage->storage / 1024 / 1024 / 1024 ) - $plan->limits->storage;
                $quantity      = ceil ( $extra_storage / $configurations->usage_pricing->storage->quantity );
                $total         = $price * $quantity;
                $line_item_id  = $order->add_product( get_product( $configurations->woocommerce->usage ), $quantity );
                $order->get_items()[ $line_item_id ]->set_subtotal( $total );
                $order->get_items()[ $line_item_id ]->set_total( $total );
                $order->get_items()[ $line_item_id ]->add_meta_data( "Details", "Extra Storage" );
                $order->get_items()[ $line_item_id ]->save();
                $calculated_total += $total;
            }

            // Visits Overage
            if ( $plan->usage->visits > $plan->limits->visits ) {
                $price     = $configurations->usage_pricing->traffic->cost;
                if ( $plan->interval != $configurations->usage_pricing->traffic->interval ) {
                    $price = $configurations->usage_pricing->traffic->cost / ( $configurations->usage_pricing->traffic->interval / $plan->interval );
                }
                $quantity     = ceil ( ( (int) $plan->usage->visits - (int) $plan->limits->visits ) / (int) $configurations->usage_pricing->traffic->quantity );
                $total        = $price * $quantity;
                $line_item_id = $order->add_product( get_product( $configurations->woocommerce->usage ), $quantity );
                $order->get_items()[ $line_item_id ]->set_subtotal( $total );
                $order->get_items()[ $line_item_id ]->set_total( $total );
                $order->get_items()[ $line_item_id ]->add_meta_data( "Details", "Extra Visits" );
                $order->get_items()[ $line_item_id ]->save();
                $calculated_total += $total;
            }
        }

        if ( $calculated_total < 0 ) {
            $over_payment = abs( $calculated_total );
            $line_item_id = $order->add_product( get_product( $configurations->woocommerce->charges ), 1 );
            $order->get_items()[ $line_item_id ]->set_subtotal( $over_payment );
            $order->get_items()[ $line_item_id ]->set_total( $over_payment );
            $order->get_items()[ $line_item_id ]->add_meta_data( "Details", "Overpayment will be applied to next invoice as credit." );
            $order->get_items()[ $line_item_id ]->save();
            $plan->over_payment = $over_payment;
            ( new Accounts )->update( [ "plan" => json_encode( $plan ) ], [ "account_id" => $this->account_id ] );
        }

        $order->calculate_totals();
        
        // Get default payment method (supports both cards and ACH)
        $user = new User( $plan->billing_user_id, true );
        $default_payment = $user->get_default_payment_method();

        // 14. Check for Outstanding Backlog (Moved up before Auto-Pay return)
        $thirty_days_ago = strtotime( '-30 days' );
        
        // Only look for orders that are older than 30 days and unpaid
        $old_outstanding = wc_get_orders( [
            'limit'        => 1, // We only need to know if at least one exists
            'meta_key'     => 'captaincore_account_id',
            'meta_value'   => $this->account_id,
            'status'       => [ 'wc-pending', 'failed' ],
            'date_created' => '<' . $thirty_days_ago, 
            'return'       => 'ids',
        ]);
        
        // If we found at least one old order
        if ( ! empty( $old_outstanding ) ) {
             // Fetch ALL outstanding orders to display in the email
             $all_outstanding = wc_get_orders( [
                'limit'      => -1,
                'meta_key'   => 'captaincore_account_id',
                'meta_value' => $this->account_id,
                'status'     => [ 'wc-pending', 'failed' ],
             ]);
             
             \CaptainCore\Mailer::send_outstanding_payment_notice( $this->account_id, $all_outstanding );
             $order->add_order_note( __( 'CaptainCore outstanding payment notice sent (Backlog > 30 days detected).', 'captaincore' ), false, true );
        }

        $auto_pay_success = false;

        // 11. Attempt Auto-Pay (supports both card and ACH payment methods)
        if ( $order->get_total() > 0 && isset($plan->auto_pay) && $plan->auto_pay == "true" && ! empty( $default_payment ) ) {
            // Get the appropriate payment ID based on type
            $payment_id = $default_payment->token;
            
            // Attempt Payment
            // If failed, hooks in captaincore.php -> captaincore_failed_notify -> failed_notify() triggers the Invoice Email.
            $payment_result = $user->pay_invoice( $order->get_id(), $payment_id );
            
            if ( isset( $payment_result['result'] ) && $payment_result['result'] === 'success' ) {
                $auto_pay_success = true;
            }
        }

        // 12. Handle $0 Total (Free) or Successful Auto-Pay
        if ( $order->get_total() == 0 || $auto_pay_success ) {
            if ( $order->get_total() == 0 ) {
                $order->update_status( 'completed' );
                // Admin notification hook will catch this status change automatically
            }
            return;
        }

        // Refresh order status to ensure we don't send duplicates if auto-pay failed (which triggers its own email via hooks)
        $order = wc_get_order( $order->get_id() );

        // 13. Send Customer Invoice (Manual Payment Needed)
        // If we are here, auto-pay was either not attempted, or it failed (and failed_notify sent an email).
        // If it's a fresh manual invoice (pending), we send the email now.
        if ( $order->get_status() === 'pending' ) {
            \CaptainCore\Mailer::send_order_invoice( $order->get_id() );
            $order->add_order_note( __( 'CaptainCore invoice sent to customer.', 'captaincore' ), false, true );
        }
    }

    public function outstanding_notify() {
        $orders = wc_get_orders( [
            'limit'      => -1,
            'meta_key'   => 'captaincore_account_id',
            'meta_value' => $this->account_id,
            'orderby'    => 'date',
            'order'      => 'DESC',
            'status'     => [ 'wc-pending', 'failed' ]
        ] );

        if ( count( $orders ) == 0 ) {
            return;
        }

        foreach( $orders as $order ) {
            $order->add_order_note( "Sent outstanding notice (CaptainCore)." );
        }

        \CaptainCore\Mailer::send_outstanding_payment_notice( $this->account_id, $orders );
    }

    public function outstanding_invoices() {
        $orders = wc_get_orders( [
            'limit'      => -1,
            'meta_key'   => 'captaincore_account_id',
            'meta_value' => $this->account_id,
            'orderby'    => 'date',
            'order'      => 'DESC',
            'status'     => [ 'wc-pending', 'failed' ]
        ] );
        if ( empty ( $orders ) ) {
            return [];
        }
        return $orders;
    }

    public function get_billing() {
        $account  = ( new Accounts )->get( $this->account_id );
        $plan     = json_decode( $account->plan );
        $customer = new \WC_Customer( $plan->billing_user_id );
        return (object) $customer->get_billing();
    }

    public function auto_switch_plan() {
        $configurations = ( new Configurations )->get();
        $account        = ( new Accounts )->get( $this->account_id );
        $plan           = json_decode( $account->plan );
        $estimates      = [];

        foreach( $configurations->hosting_plans as $hosting_plan ) {

            $total = $hosting_plan->price;

            if ( $plan->interval != $hosting_plan->interval ) {
                $total = ( (float) $hosting_plan->price / (int) $hosting_plan->interval ) * (int) $plan->interval;
                $hosting_plan->interval = $plan->interval;
                $hosting_plan->price    = $total;
            }
            
            if ( ! empty( $plan->addons ) && count( $plan->addons ) > 0 ) {
                foreach ( $plan->addons as $addon ) {
                    $total += (float) $addon->quantity * (float) $addon->price;
                }
            }
    
            if ( empty( $plan->usage ) ) {
                self::calculate_usage();
                $account = ( new Accounts )->get( $this->account_id );
                $plan    = json_decode( $account->plan );
            }
            if ( $plan->usage->sites > $hosting_plan->limits->sites ) {
                $price    = $configurations->usage_pricing->sites->cost;
                if ( $plan->interval != $configurations->usage_pricing->sites->interval ) {
                    $price = $configurations->usage_pricing->sites->cost / ($configurations->usage_pricing->sites->interval / $plan->interval );
                }
                $quantity     = $plan->usage->sites - $hosting_plan->limits->sites;
                $total        += $price * $quantity;
            }
    
            if ( $plan->usage->storage > ( $hosting_plan->limits->storage * 1024 * 1024 * 1024 ) ) {
                $price    = $configurations->usage_pricing->storage->cost;
                if ( $plan->interval != $configurations->usage_pricing->storage->interval ) {
                    $price = $configurations->usage_pricing->storage->cost / ( $configurations->usage_pricing->storage->interval / $plan->interval );
                }
                $extra_storage = ( $plan->usage->storage / 1024 / 1024 / 1024 ) - $hosting_plan->limits->storage;
                $quantity      = ceil ( $extra_storage / $configurations->usage_pricing->storage->quantity );
                $total         += $price * $quantity;
            }
    
            if ( $plan->usage->visits > $hosting_plan->limits->visits ) {
                $price     = $configurations->usage_pricing->traffic->cost;
                if ( $plan->interval != $configurations->usage_pricing->traffic->interval ) {
                    $price = $configurations->usage_pricing->traffic->cost / ( $configurations->usage_pricing->traffic->interval / $plan->interval );
                }
                $quantity     = ceil ( ( $plan->usage->visits - $hosting_plan->limits->visits ) / $configurations->usage_pricing->traffic->quantity );
                $total        += $price * $quantity;
            }

            $estimates[] = (object) [ "name" => $hosting_plan->name, "total" => $total ];

        }

        usort( $estimates, function($a, $b) {return $a->total - $b->total;});
        $cheapest_estimate = $estimates[0];

        if ( $plan->name != $cheapest_estimate->name ) {
            foreach( $configurations->hosting_plans as $hosting_plan ) {
                if ( $hosting_plan->name == $cheapest_estimate->name ) {
                    $new_hosting_plan = $hosting_plan;
                }
            }
            $new_hosting_plan->auto_pay        = empty( $plan->auto_pay ) ? "" : $plan->auto_pay;
            $new_hosting_plan->auto_switch     = empty( $plan->auto_switch ) ? "" : $plan->auto_switch;
            $new_hosting_plan->addons          = empty( $plan->addons ) ? "" : $plan->addons;
            $new_hosting_plan->credits         = empty( $plan->credits ) ? "" : $plan->credits;
            $new_hosting_plan->charges         = empty( $plan->charges ) ? "" : $plan->charges;
            $new_hosting_plan->billing_user_id = empty( $plan->billing_user_id ) ? "" : $plan->billing_user_id;
            $new_hosting_plan->next_renewal    = empty( $plan->next_renewal ) ? "" : $plan->next_renewal;
            ( new Accounts )->update_plan( (array) $new_hosting_plan, $this->account_id );
        }

        return $estimates;
    }

    public function delete() {
        ( new Accounts )->delete( [ "account_id" => $this->account_id ] );
    }

}