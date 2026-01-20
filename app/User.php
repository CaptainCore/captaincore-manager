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
        if ( ! empty( $this->user_id )) {
            $user_meta     = get_userdata( $this->user_id );
            $this->roles   = $user_meta->roles;
        }
    }

    public function accounts() {
        $accountuser = new AccountUser();
        $accounts    = array_column( $accountuser->where( [ "user_id" => $this->user_id ] ), "account_id" );
        return $accounts;
    }

    public function set_as_primary( $token ) {
        \WC_Payment_Tokens::set_users_default( $this->user_id, intval( $token ) );
        $billing = self::billing();
        foreach ( $billing->subscriptions as $item ) {
            $subscription = (object) $item;
            if ( $subscription->type == "woocommerce" ) {
                self::update_payment_method( $subscription->account_id, intval( $token ) );
            }
        }
    }

    public function add_payment_method( $source_id ) {
        try {
            $customer    = new \WC_Stripe_Customer( $this->user_id );
            $customer_id = $customer->get_id();
            if ( ! $customer_id ) {
                $customer->set_id( $customer->create_customer() );
                $customer_id = $customer->get_id();
            } else {
                $customer_id = $customer->update_customer();
            }
            $response = $customer->add_source( $source_id );
            
            if ( is_wp_error( $response ) ) {
                return (object) [ 'error' => $response->get_error_message() ];
            }
            if ( ! empty( $response->error ) ) {
                return (object) [ 'error' => $response->error->message ];
            }

            $customer->attach_source( $source_id );
            return $response;
        } catch ( \WC_Stripe_Exception $e ) {
            return (object) [ 'error' => $e->getMessage() ];
        }
    }

    public function delete_payment_method( $token_id ) {
        $token       = \WC_Payment_Tokens::get( $token_id );
        
        if ( is_null( $token ) || $this->user_id !== $token->get_user_id() ) {
            return "Error";
        }
        $was_default = $token->is_default();
        \WC_Payment_Tokens::delete( $token_id );

        if ( $was_default ) {
            $payment_tokens = \WC_Payment_Tokens::get_customer_tokens( $this->user_id );

            // Set default if another payment token found.
            if ( count( $payment_tokens ) > 0 ) {
                $other_token_id = array_keys( $payment_tokens )[0];
                self::set_as_primary( $other_token_id );
                $billing = self::billing();
                foreach ( $billing->subscriptions as $item ) {
                    $subscription = (object) $item;
                    if ( $subscription->type == "woocommerce" ) {
                        self::update_payment_method( $subscription->account_id, intval( $other_token_id ) );
                    }
                }
            }
        }
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

    private function prepare_plan_for_display( $account_id, $account_data ) {
        $plan    = $account_data->plan;
        $configs = Configurations::get();
        
        // Ensure arrays exist
        if ( ! isset( $plan->addons ) || ! is_array( $plan->addons ) ) { $plan->addons = []; }
        if ( ! isset( $plan->charges ) || ! is_array( $plan->charges ) ) { $plan->charges = []; }
        if ( ! isset( $plan->credits ) || ! is_array( $plan->credits ) ) { $plan->credits = []; }

        $billing_mode = isset( $plan->billing_mode ) ? $plan->billing_mode : 'standard';
        $total = 0;

        // 1. Base Price Calculation
        if ( $billing_mode === 'per_site' ) {
            $sites = Sites::where( [ "account_id" => $account_id, "status" => "active" ] );
            $billing_sites_count = 0;
            foreach ( $sites as $site ) {
                if ( empty( $site->provider_id ) || $site->provider_id == "1" ) {
                    $billing_sites_count++;
                }
            }
            $price_per_site = (float) $plan->price;
            $total = $price_per_site * $billing_sites_count;
        } else {
            $total = (float) $plan->price;
        }

        // 2. Addons Calculation
        foreach ( $plan->addons as $addon ) {
            if ( isset($addon->price) && isset($addon->quantity) ) {
                $total += ( (float) $addon->price * (int) $addon->quantity );
            }
        }

        // 3. Charges Calculation
        foreach ( $plan->charges as $charge ) {
            if ( isset($charge->price) && isset($charge->quantity) ) {
                $total += ( (float) $charge->price * (int) $charge->quantity );
            }
        }

        // 4. Credits Calculation
        foreach ( $plan->credits as $credit ) {
            if ( isset($credit->price) && isset($credit->quantity) ) {
                $total -= ( (float) $credit->price * (int) $credit->quantity );
            }
        }

        // 5. Dynamic Maintenance Sites (Managed WordPress sites)
        $all_sites = Sites::where( [ "account_id" => $account_id, "status" => "active" ] );
        $maintenance_count = 0;
        foreach ( $all_sites as $site ) {
            if ( ! empty( $site->provider_id ) && ( $site->provider_id != "1" ) ) {
                $maintenance_count++;
            }
        }
        
        if ( $maintenance_count > 0 ) {
            $maintenance_cost = 2; 
            $total += ( $maintenance_count * $maintenance_cost );
            array_unshift($plan->addons, (object)[
                "name" => "Managed WordPress sites",
                "price" => $maintenance_cost,
                "quantity" => $maintenance_count
            ]);
        }

        // 6. Usage Overages (Standard Mode Only)
        if ( $billing_mode !== 'per_site' && ! empty( $plan->usage ) ) {
            
            // Sites Overage
            if ( (int) $plan->usage->sites > (int) $plan->limits->sites ) {
                $price = $configs->usage_pricing->sites->cost;
                if ( $plan->interval != $configs->usage_pricing->sites->interval ) {
                    $price = $configs->usage_pricing->sites->cost / ( $configs->usage_pricing->sites->interval / $plan->interval );
                }
                $total += $price * ( (int) $plan->usage->sites - (int) $plan->limits->sites );
            }

            // Storage Overage
            $storage_bytes = (float) $plan->usage->storage;
            $limit_bytes   = (float) $plan->limits->storage * 1024 * 1024 * 1024;
            if ( $storage_bytes > $limit_bytes ) {
                $price = $configs->usage_pricing->storage->cost;
                if ( $plan->interval != $configs->usage_pricing->storage->interval ) {
                    $price = $configs->usage_pricing->storage->cost / ( $configs->usage_pricing->storage->interval / $plan->interval );
                }
                $extra_gb = ($storage_bytes / 1024 / 1024 / 1024) - (float) $plan->limits->storage;
                $quantity = ceil( $extra_gb / $configs->usage_pricing->storage->quantity );
                $total += $price * $quantity;
            }

            // Visits Overage
            if ( (int) $plan->usage->visits > (int) $plan->limits->visits ) {
                $price = $configs->usage_pricing->traffic->cost;
                if ( $plan->interval != $configs->usage_pricing->traffic->interval ) {
                    $price = $configs->usage_pricing->traffic->cost / ( $configs->usage_pricing->traffic->interval / $plan->interval );
                }
                $extra_visits = (int) $plan->usage->visits - (int) $plan->limits->visits;
                $quantity     = ceil( $extra_visits / (int) $configs->usage_pricing->traffic->quantity );
                $total += $price * $quantity;
            }
        }

        return [
            "plan"         => $plan,
            "billing_mode" => $billing_mode,
            "total"        => number_format( max(0, $total), 2, '.', ''),
        ];
    }

    public function subscriptions() {
        $plans         = [];
        $with_renewals = self::with_renewals();
        
        foreach ( $with_renewals as $account_row ) {
            $account_id   = $account_row->account_id;
            $account      = new Account( $account_id, true );
            $account_data = $account->get(); 
            $plan_raw     = $account_data->plan;

            // FILTER: Skip if next_renewal is empty (Inactive)
            if ( empty( $plan_raw->next_renewal ) ) {
                continue;
            }

            // Use the helper
            $calculated = $this->prepare_plan_for_display( $account_id, $account_data );
            $plan       = $calculated['plan'];

            $plans[] = [
                "account_id"      => $account_id,
                "name"            => $account_data->name,
                "next_renewal"    => $plan->next_renewal,
                "interval"        => $plan->interval,
                "billing_mode"    => $calculated['billing_mode'],
                "addons"          => $plan->addons,
                "base_price"      => $plan->price,
                "total"           => $calculated['total'],
                "billing_user_id" => $plan->billing_user_id,
                "status"          => isset($plan->status) ? $plan->status : 'active',
            ];
        }
        return $plans;
    }

    public function get_subscription_details( $account_id ) {
        if ( ! self::is_admin() ) {
            return new \WP_Error( 'forbidden', 'Permission denied', [ 'status' => 403 ] );
        }

        $account_obj = new Account( $account_id, true );
        $account     = $account_obj->get();
        
        // Use the helper to get calculated addons and totals
        $calculated = $this->prepare_plan_for_display( $account_id, $account );
        $plan       = $calculated['plan']; // This now includes the dynamic addons

        // Fetch Invoices (Orders)
        $orders = wc_get_orders( [
            'limit'      => -1,
            'meta_key'   => 'captaincore_account_id',
            'meta_value' => $account_id,
            'orderby'    => 'date',
            'order'      => 'DESC',
        ] );

        $invoices = [];
        $ltv      = 0;

        foreach ( $orders as $order ) {
            $total = $order->get_total();
            
            if ( $order->get_status() == 'completed' ) {
                $ltv += (float) $total;
            }

            $invoices[] = [
                "order_id"     => $order->get_id(),
                "order_number" => $order->get_order_number(),
                "date"         => $order->get_date_created()->date('Y-m-d H:i:s'),
                "status"       => $order->get_status(),
                "total"        => $total,
                "view_url"     => $order->get_edit_order_url(),
                "item_count"   => $order->get_item_count(),
            ];
        }

        $sites   = $account_obj->sites();
        $domains = $account_obj->domains();
        $usage   = $account_obj->usage_breakdown();

        return [
            "account" => [
                "id"   => $account->account_id,
                "name" => $account->name,
            ],
            "plan" => $plan, // Now safely contains arrays
            "stats" => [
                "sites_count"   => count($sites),
                "domains_count" => count($domains),
                "storage_usage" => $usage['total'][0],
                "visits_usage"  => $usage['total'][1],
                "ltv"           => $ltv
            ],
            "invoices" => $invoices
        ];
    }

    public function upcoming_subscriptions() {

        $with_renewals = self::with_renewals();
        $plans         = [];
        $month_count   = 1;
        $revenue       = (object) [];
        $transactions  = (object) [];

        for ($i = 1; $i <= 12; $i++) {
            $next_month = date( "M Y", strtotime( "first day of +$month_count month" ) );
            $revenue->{$next_month} = 0;
            $transactions->{$next_month} = 0;
            $month_count++;
        }

        foreach ( $with_renewals as $account ) {
            $plan         = json_decode ( $account->plan );
            if ( empty( $plan->next_renewal ) ) {
                continue;
            }
            $next_renewal = date( "M Y", strtotime( $plan->next_renewal ) );
            $renew_count  = 1;
            $plan_total   = $plan->price;
            if ( $plan->addons ) { 
                foreach ( $plan->addons as $addon ) {
                    $plan_total = (int) $plan_total + ( (int) $addon->price * (int) $addon->quantity );
                }
            }
            foreach( $revenue as $month => $amount ) {
                if ( $plan->interval == "1" ) {
                    $revenue->{$month} = $revenue->{$month} + $plan_total;
                    $transactions->{$month} = $transactions->{$month} + 1;
                }
                if ( $plan->interval == "6" && $month == $next_renewal ) {
                    $renew_modifier    = (int) $renew_count * 6;
                    $revenue->{$month} = $revenue->{$month} + $plan_total;
                    $next_renewal      = date( "M Y", strtotime("+$renew_modifier month", strtotime( $plan->next_renewal ) ) );
                    $renew_count++;
                    $transactions->{$month} = $transactions->{$month} + 1;
                }
                if ( $plan->interval == "3" && $month == $next_renewal ) {
                    $renew_modifier    = (int) $renew_count * 3;
                    $revenue->{$month} = $revenue->{$month} + $plan_total;
                    $next_renewal      = date( "M Y", strtotime("+$renew_modifier month", strtotime( $plan->next_renewal ) ) );
                    $renew_count++;
                    $transactions->{$month} = $transactions->{$month} + 1;
                }
                if ( $plan->interval == "12" && $month == $next_renewal ) {
                    $revenue->{$month} = $revenue->{$month} + $plan_total;
                    $next_renewal      = date( "M Y", strtotime("+12 month", strtotime( $plan->next_renewal ) ) );
                    $renew_count++;
                    $transactions->{$month} = $transactions->{$month} + 1;
                }
            }
        }
        return [ "revenue" => $revenue, "transactions" => $transactions ];
    }

    public function with_renewals() {
        if ( self::is_admin() ) {
            return ( new Accounts )->with_renewals();
        }
        return ( new Accounts )->renewals( $this->user_id );
    }

    public function billing() {
        $customer       = new \WC_Customer( $this->user_id );
        $address        = $customer->get_billing();
        if ( empty( $address["country"] ) ) {
            $address["country"] = "US";
        }
        if ( empty( $address["email"] ) ) {
            $user             = get_user_by( "ID", $this->user_id );
            $address["email"] =  $user->user_email;
        }
        $invoices       = wc_get_orders( [ 'customer' => $this->user_id, 'posts_per_page' => "-1" ] );
        foreach ( $invoices as $key => $invoice ) {
            $order      = wc_get_order( $invoice );
            $item_count = $order->get_item_count();
            $invoices[$key]  = [
                "order_id" => $order->id,
                "date"     => wc_format_datetime( $order->get_date_created() ),
                "status"   => wc_get_order_status_name( $order->get_status() ),
                "total"    => $order->get_total(),
            ];
        }
        // Fetch CaptainCore subscriptions
        $subscriptions = ( new Accounts )->renewals( $this->user_id );
        foreach ( $subscriptions as $key => $subscription ) {
            $plan                        = json_decode( $subscription->plan );
            $plan->addons                = ( empty( $plan->addons ) ) ? [] : $plan->addons;
            $subscriptions[ $key ]->plan = $plan;
        }
        $payment_methods = [];
        $payment_tokens  = \WC_Payment_Tokens::get_customer_tokens( $this->user_id );
        foreach ( $payment_tokens as $payment_token ) {
            $type            = strtolower( $payment_token->get_type() );
            $card_type       = $payment_token->get_card_type();
            $payment_methods[] = [
                'method'     => [
                    'brand'   => ( ! empty( $card_type ) ? ucfirst( $card_type ) : esc_html__( 'Credit card', 'woocommerce' ) ),
                    'gateway' => $payment_token->get_gateway_id(),
                    'last4'   => $payment_token->get_last4(),
                ],
                'expires'    => $payment_token->get_expiry_month() . '/' . substr( $payment_token->get_expiry_year(), -2 ),
                'is_default' => $payment_token->is_default(),
                'token'      => $payment_token->get_id(),
            ];
        }

        $billing = (object) [
            "valid"           => true,
            "rules"           => [ "firstname" => [], "lastname" => [], "address_1" => [], "city" => [], "state" => [], "zip" => [], "email" => [], "country" => [] ],
            "address"         => $address,
            "subscriptions"   => $subscriptions,
            "invoices"        => $invoices,
            "payment_methods" => $payment_methods,
        ];

        return $billing;
    }

    public function update_payment_method( $invoice_id, $payment_id ) {

        try {

            $wc_token      = \WC_Payment_Tokens::get( $payment_id );
            $source_id     = $wc_token->get_token();
            $customer      = new \WC_Stripe_Customer( $this->user_id );
            $customer_id   = $customer->get_id();
            $source_object = \WC_Stripe_API::retrieve( 'sources/' . $source_id );

            $prepared_source = (object) [
                'token_id'      => $payment_id,
                'customer'      => $customer_id,
                'source'        => $source_id,
                'source_object' => $source_object,
            ];

            $payment_method     = WC()->payment_gateways()->payment_gateways()['stripe'];
            $order              = wc_get_order( $invoice_id );
            $payment_method->save_source_to_order( $order, $prepared_source );
            
			return array(
				'result'   => 'success',
			);

		} catch ( \WC_Stripe_Exception $e ) {
			return array(
				'result'   => 'fail',
				'message' => $e->getMessage(),
			);
		}
    }

    public function pay_invoice( $invoice_id, $payment_id ) {

        try {

            $wc_token      = \WC_Payment_Tokens::get( $payment_id );
            $source_id     = $wc_token->get_token();
            $customer      = new \WC_Stripe_Customer( $this->user_id );
            $customer_id   = $customer->get_id();
            if ( ! $customer_id ) {
                $customer->set_id( $customer->create_customer() );
                $customer_id = $customer->get_id();
            } else {
                $customer_id = $customer->update_customer();
            }
            $source_object = \WC_Stripe_API::retrieve( 'sources/' . $source_id );

            $prepared_source = (object) [
                'token_id'      => $payment_id,
                'customer'      => $customer_id,
                'source'        => $source_id,
                'source_object' => $source_object,
            ];

            $payment_method     = WC()->payment_gateways()->payment_gateways()['stripe'];
            $order              = wc_get_order( $invoice_id );

            if ( ! empty( $source_object->error ) && $source_object->error->code == "resource_missing" ) {

                do_action( 'wc_gateway_stripe_process_payment_error', $source_object->error, $order );

                $order->update_status( 'failed' );

                return [
                    'result'   => 'fail',
                    'redirect' => '',
                    'message'  => $source_object->error->message,
                ];
            }

            $order->set_payment_method( 'stripe' );
            $payment_method->save_source_to_order( $order, $prepared_source );
            $intent = $payment_method->create_intent( $order, $prepared_source );
            // Confirm the intent after locking the order to make sure webhooks will not interfere.
            if ( empty( $intent->error ) ) {
                $payment_method->lock_order_payment( $order, $intent );
                $intent = $payment_method->confirm_intent( $intent, $order, $prepared_source );
            }

            if ( ! empty( $intent->error ) ) {
                $payment_method->maybe_remove_non_existent_customer( $intent->error, $order );

                // We want to retry.
                if ( $payment_method->is_retryable_error( $intent->error ) ) {
                    return $payment_method->retry_after_error( $intent, $order, $retry, $force_save_source, $previous_error, $use_order_source );
                }

                $payment_method->unlock_order_payment( $order );
                $payment_method->throw_localized_message( $intent, $order );
            }

            if ( ! empty( $intent ) ) {
                $response = null;
                // Use the last charge within the intent to proceed.
                if ( ! empty( $intent->charges->data ) ) {
                    $response = end( $intent->charges->data );
                } elseif ( ! empty( $intent->latest_charge ) ) {
                    $response = \WC_Stripe_API::request( [], 'charges/' . $intent->latest_charge, 'GET' );
                }
            }

            // Process valid response.
            $payment_method->process_response( $response, $order );

            // Remove cart.
            if ( isset( WC()->cart ) ) {
                WC()->cart->empty_cart();
            }

            // Unlock the order.
            $payment_method->unlock_order_payment( $order );

            $order->update_status( 'completed' );

            // Return thank you page redirect.
            return array(
                'result'   => 'success',
                'redirect' => $payment_method->get_return_url( $order ),
            );

        } catch ( \WC_Stripe_Exception $e ) {
            \WC_Stripe_Logger::log( 'Error: ' . $e->getMessage() );

            do_action( 'wc_gateway_stripe_process_payment_error', $e, $order );

            /* translators: error message */
            $order->update_status( 'failed' );

            return array(
                'result'   => 'fail',
                'redirect' => '',
                'message'  => $e->getLocalizedMessage(),
            );
        }

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

        \CaptainCore\Mailer::send_site_request_notification( 
            $site->name, 
            $site->notes, 
            $account->name, 
            $user 
        );
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

    public function user_id() {
        return $this->user_id;
    }

    public function tfa_activate() {
        $user    = (object) self::fetch();
        $otp     = \OTPHP\TOTP::generate();
        $token   = $otp->getSecret();
        $otp->setIssuer('Anchor Hosting');
        $otp->setLabel( $user->email );
        update_user_meta( $user->user_id , 'captaincore_2fa_token', $token );
        return $otp->getProvisioningUri();
    }

    public function tfa_deactivate() {
        $user    = (object) self::fetch();
        delete_user_meta( $user->user_id , 'captaincore_2fa_token' );
        delete_user_meta( $user->user_id , 'captaincore_2fa_enabled' );
        return "Deaactivated";
    }

    public function tfa_activate_verify( $token ) {
        $user   = (object) self::fetch();
        $secret = get_user_meta( $user->user_id , 'captaincore_2fa_token', true );
        $otp    = \OTPHP\TOTP::createFromSecret($secret); // create TOTP object from the secret.
        $verify = $otp->verify($token);

        if ( $verify ) {
            update_user_meta( $user->user_id , 'captaincore_2fa_enabled', true );
        }
        
        return $verify;
    }

    public function tfa_login( $token ) {
        $user   = (object) self::fetch();
        $secret = get_user_meta( $user->user_id , 'captaincore_2fa_token', true );
        $otp    = \OTPHP\TOTP::createFromSecret($secret); // create TOTP object from the secret.
        $verify = $otp->verify($token);
        
        return $verify;
    }

    public function profile() {

        $user = wp_get_current_user();

        if ( self::is_admin() ) {
            $role = "administrator";
        } else {
            $role = "user";
        }

        if ( defined( 'CAPTAINCORE_CUSTOM_DOMAIN' ) ) {
            $account_portals = ( new AccountPortals )->where( [ 'domain' => CAPTAINCORE_CUSTOM_DOMAIN ] );
            foreach( $account_portals as $account_portal ) {
                $account = ( new Account( $account_portal->account_id, true ) )->fetch();
                if ( $account["owner"] ) {
                    $role = "owner";
                }

            }
        }

        // Fetch Pinned Environments
        $pinned = get_user_meta( $user->ID, 'captaincore_pinned_environments', true );
        if ( empty( $pinned ) || ! is_array( $pinned ) ) {
            $pinned = [];
        }

        return (object) [
            "email"               => $user->user_email,
            "login"               => $user->user_login,
            "registered"          => strtotime( $user->user_registered ),
            "hash"                => hash_hmac( 'sha256', $user->user_email, ( new Configurations )->get()->intercom_secret_key ),
            "display_name"        => $user->display_name,
            "first_name"          => $user->first_name,
            "last_name"           => $user->last_name,
            "tfa_enabled"         => get_user_meta( $user->ID, 'captaincore_2fa_enabled', true ) ? get_user_meta( $user->ID, 'captaincore_2fa_enabled', true ) : 0,
            "role"                => $role,
            "pinned_environments" => $pinned
        ];
    }

}