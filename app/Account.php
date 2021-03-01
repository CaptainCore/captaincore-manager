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
        
        return $record;
    }

    public function account() {
        $account               = ( new Accounts )->get( $this->account_id );
        $defaults              = json_decode( $account->defaults );
        $plan                  = json_decode( $account->plan );
        $plan->name            = empty( $plan->name ) ? "" : $plan->name;
        $plan->addons          = empty( $plan->addons ) ? [] : $plan->addons;
        $plan->charges         = empty( $plan->charges ) ? [] : $plan->charges;
        $plan->credits         = empty( $plan->credits ) ? [] : $plan->credits;
        $plan->limits          = empty( $plan->limits ) ? (object) [ "storage" => 0, "visits" => 0, "sites" => 0 ] : $plan->limits;
        $plan->interval        = empty( $plan->interval ) ? "12" : $plan->interval;
        $plan->billing_user_id = empty( $plan->billing_user_id ) ? 0 : (int) $plan->billing_user_id;
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
        $account_ids[] = $this->account_id;
        $results       = ( new AccountDomain )->fetch_domains( [ "account_id" => $account_ids ] );
        return $results;
    }

    public function billing_sites() {
        if ( $this->account_id == "" ) {
            return [];
        }
        // Fetch sites assigned as owners
        $all_site_ids = [];
        $site_ids = array_column( ( new Sites )->where( [ "account_id" => $this->account_id, "status" => "active" ] ), "site_id" );
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
		$addons            = empty( $account->plan->usage->addons ) ? [] : $account->plan->usage->addons;
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
            $website_for_customer_storage = $site->storage;
            $website_for_customer_visits  = $site->visits;
            $result_sites[] = [
                'name'    => $site->name,
                'storage' => $website_for_customer_storage,
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
            "sites"   => count( $this->billing_sites() ),
            "users"   => count( $this->users() ),
            "domains" => count( $this->domains() ),
        ];
        ( new Accounts )->update( [ "metrics" => json_encode( $metrics ) ], [ "account_id" => $this->account_id ] );
        return [ "message" => "Account metrics updated." ];
    }

    public function calculate_usage() {
        $account  = self::get();
        $sites    = $this->billing_sites();
        $account->plan->usage->storage = array_sum ( array_column( $sites, "storage" ) );
        $account->plan->usage->visits  = array_sum ( array_column( $sites, "visits" ) );
        $account->plan->usage->sites   = count( $sites );
        ( new Accounts )->update( [ "plan" => json_encode( $account->plan ) ], [ "account_id" => $this->account_id ] );
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

    public function generate_order() {
        $configurations = ( new Configurations )->get();
        $account        = ( new Accounts )->get( $this->account_id );
        $plan           = json_decode( $account->plan );
        if ( $plan->auto_switch == "true" ) {
            self::auto_switch_plan();
            $account = ( new Accounts )->get( $this->account_id );
            $plan    = json_decode( $account->plan );
        }
        $customer       = new \WC_Customer( $plan->billing_user_id );
        $address        = $customer->get_billing();
        $order          = wc_create_order(  [ 'customer_id' => $plan->billing_user_id ] );
        $order->update_meta_data( "captaincore_account_id", $this->account_id );

        $site_names     = array_column( self::billing_sites(), "name" );
        sort( $site_names );
        $site_names     = implode( ", ", $site_names );

        if ( ! empty( $address ) ) {
            $order->set_address( $address, 'billing' );
        }

        $line_item_id     = $order->add_product( get_product( $configurations->woocommerce->hosting_plan ), 1 );

        $order->get_items()[ $line_item_id ]->set_subtotal( $plan->price );
        $order->get_items()[ $line_item_id ]->set_total( $plan->price );
        $order->get_items()[ $line_item_id ]->add_meta_data( "Details", $plan->name . "\n\n" . $site_names );
        $order->get_items()[ $line_item_id ]->save_meta_data();
        $order->get_items()[ $line_item_id ]->save();
        $calculated_total = $plan->price;

        if ( $plan->addons && count( $plan->addons ) > 0 ) {
            foreach ( $plan->addons as $item ) {
                $line_item_id = $order->add_product( get_product( $configurations->woocommerce->addons ), $item->quantity );
                $order->get_items()[ $line_item_id ]->set_subtotal( $item->price * $item->quantity );
                $order->get_items()[ $line_item_id ]->set_total( $item->price * $item->quantity );
                $order->get_items()[ $line_item_id ]->add_meta_data( "Details", $item->name );
                $order->get_items()[ $line_item_id ]->save_meta_data();
                $order->get_items()[ $line_item_id ]->save();
                $calculated_total = $calculated_total + ( $item->price * $item->quantity );
            }
        }

        if ( $plan->charges && count( $plan->charges ) > 0 ) {
            foreach ( $plan->charges as $item ) {
                $line_item_id = $order->add_product( get_product( $configurations->woocommerce->charges ), $item->quantity );
                $order->get_items()[ $line_item_id ]->set_subtotal( $item->price * $item->quantity );
                $order->get_items()[ $line_item_id ]->set_total( $item->price * $item->quantity );
                $order->get_items()[ $line_item_id ]->add_meta_data( "Details", $item->name );
                $order->get_items()[ $line_item_id ]->save_meta_data();
                $order->get_items()[ $line_item_id ]->save();
                $calculated_total = $calculated_total + ( $item->price * $item->quantity );
            }
        }

        if ( $plan->credits && count( $plan->credits ) > 0 ) {
            foreach ( $plan->credits as $item ) {
                $line_item_id = $order->add_product( get_product( $configurations->woocommerce->credits ), $item->quantity );
                $order->get_items()[ $line_item_id ]->set_subtotal( -1 * abs( $item->price * $item->quantity ) );
                $order->get_items()[ $line_item_id ]->set_total( -1 * abs( $item->price * $item->quantity ) );
                $order->get_items()[ $line_item_id ]->add_meta_data( "Details", $item->name );
                $order->get_items()[ $line_item_id ]->save_meta_data();
                $order->get_items()[ $line_item_id ]->save();
                $calculated_total = $calculated_total + ( -1 * abs( $item->price * $item->quantity ) );
            }
        }

        if ( $plan->usage->sites > $plan->limits->sites ) {
            $price    = $configurations->usage_pricing->sites->cost;
            if ( $plan->interval != $configurations->usage_pricing->sites->interval ) {
                $price = $configurations->usage_pricing->sites->cost / ($configurations->usage_pricing->sites->interval / $plan->interval );
            }
            $quantity     = $plan->usage->sites - $plan->limits->sites;
            $total        = $price * $quantity;
            $line_item_id = $order->add_product( get_product( $configurations->woocommerce->usage ), $quantity );
            $order->get_items()[ $line_item_id ]->set_subtotal( $total );
            $order->get_items()[ $line_item_id ]->set_total( $total );
            $order->get_items()[ $line_item_id ]->add_meta_data( "Details", "Extra Sites" );
            $order->get_items()[ $line_item_id ]->save_meta_data();
            $order->get_items()[ $line_item_id ]->save();
            $calculated_total = $calculated_total + $total;
        }

        if ( $plan->usage->storage > ( $plan->limits->storage * 1024 * 1024 * 1024 ) ) {
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
            $order->get_items()[ $line_item_id ]->save_meta_data();
            $order->get_items()[ $line_item_id ]->save();
            $calculated_total = $calculated_total + $total;
        }

        if ( $plan->usage->visits > $plan->limits->visits ) {
            $price     = $configurations->usage_pricing->traffic->cost;
            if ( $plan->interval != $configurations->usage_pricing->traffic->interval ) {
                $price = $configurations->usage_pricing->traffic->cost / ( $configurations->usage_pricing->traffic->interval / $plan->interval );
            }
            $quantity     = ceil ( ( $plan->usage->visits - $plan->limits->visits ) / $configurations->usage_pricing->traffic->quantity );
            $total        = $price * $quantity;
            $line_item_id = $order->add_product( get_product( $configurations->woocommerce->usage ), $quantity );
            $order->get_items()[ $line_item_id ]->set_subtotal( $total );
            $order->get_items()[ $line_item_id ]->set_total( $total );
            $order->get_items()[ $line_item_id ]->add_meta_data( "Details", "Extra Visits" );
            $order->get_items()[ $line_item_id ]->save_meta_data();
            $order->get_items()[ $line_item_id ]->save();
            $calculated_total = $calculated_total + $total;
        }

        // Adjust credits if overpayment received
        if ( $calculated_total < 0 ) {
            $over_payment   = 1 * abs( $calculated_total );
            $line_item_id = $order->add_product( get_product( $configurations->woocommerce->charges ), 1 );
            $order->get_items()[ $line_item_id ]->set_subtotal( $over_payment );
            $order->get_items()[ $line_item_id ]->set_total( $over_payment );
            $order->get_items()[ $line_item_id ]->add_meta_data( "Details", "Overpayment will be applied to next invoice as credit." );
            $order->get_items()[ $line_item_id ]->save_meta_data();
            $order->get_items()[ $line_item_id ]->save();
            $plan->over_payment = $over_payment;
            ( new Accounts )->update( [ "plan" => json_encode( $plan ) ], [ "account_id" => $this->account_id ] );
        }

        $order->calculate_totals();
        $default_payment = ( new \WC_Payment_Tokens )->get_customer_default_token( $plan->billing_user_id );

        if ( $order->get_total() > 0 && $plan->auto_pay == "true" && ! empty( $default_payment ) ) {
            $payment_id = $default_payment->get_id();
            ( new User( $plan->billing_user_id, true ) )->pay_invoice( $order->get_id(), $payment_id );
            return;
        }

        if ( $order->get_total() == 0 ) {
            $order->update_status( 'completed' );
        }

        WC()->mailer()->customer_invoice( $order );
        $order->add_order_note( __( 'Order details sent to customer.', 'woocommerce' ), false, true );
        do_action( 'woocommerce_after_resend_order_email', $order, 'customer_invoice' );
    }

    public function auto_switch_plan() {
        $configurations = ( new Configurations )->get();
        $account        = ( new Accounts )->get( $this->account_id );
        $plan           = json_decode( $account->plan );
        $estimates      = [];

        foreach( $configurations->hosting_plans as $hosting_plan ) {

            $total = $hosting_plan->price;

            if ( $plan->interval != $hosting_plan->interval ) {
                $total = ( $hosting_plan->price / $hosting_plan->interval ) * $plan->interval;
                $hosting_plan->interval = $plan->interval;
                $hosting_plan->price    = $total;
            }

            
            if ( $plan->addons && count( $plan->addons ) > 0 ) {
                foreach ( $plan->addons as $addon ) {
                    $total += $addon->quantity * $addon->price;
                }
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
            $new_hosting_plan->auto_pay        = $plan->auto_pay;
            $new_hosting_plan->auto_switch     = $plan->auto_switch;
            $new_hosting_plan->addons          = $plan->addons;
            $new_hosting_plan->credits         = $plan->credits;
            $new_hosting_plan->charges         = $plan->charges;
            $new_hosting_plan->billing_user_id = $plan->billing_user_id;
            $new_hosting_plan->next_renewal    = $plan->next_renewal;
            echo "Switching to from {$plan->name} to {$cheapest_estimate->name}";
            ( new Accounts )->update_plan( (array) $new_hosting_plan, $this->account_id );
        }

        return $estimates;
    }

    public function delete() {
        ( new Accounts )->delete( [ "account_id" => $this->account_id ] );
    }

}