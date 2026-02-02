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

    public function set_as_primary( $token_id ) {
        // Check if this is an ACH token (stored in user meta)
        if ( is_string( $token_id ) && strpos( $token_id, 'ach_' ) === 0 ) {
            // Set ACH as primary - first clear all other defaults
            $this->clear_all_payment_defaults();
            
            // Set this ACH method as default
            $methods = $this->get_ach_payment_methods();
            foreach ( $methods as $index => $method ) {
                $methods[ $index ]['is_default'] = ( $method['token_id'] === $token_id );
            }
            $this->save_ach_payment_methods( $methods );
        } else {
            // Set card as primary - first clear ACH defaults
            $this->clear_ach_defaults();
            
            // Set the WC token as default
            \WC_Payment_Tokens::set_users_default( $this->user_id, intval( $token_id ) );
        }
        
        // Update subscriptions with the new payment method
        $billing = self::billing();
        foreach ( $billing->subscriptions as $item ) {
            $subscription = (object) $item;
            if ( $subscription->type == "woocommerce" ) {
                // Note: For ACH tokens, we store the token_id string
                // The update_payment_method may need adjustment for ACH
                if ( is_string( $token_id ) && strpos( $token_id, 'ach_' ) === 0 ) {
                    // For now, skip updating WC subscriptions with ACH tokens
                    // This may need custom handling depending on how payments are processed
                } else {
                    self::update_payment_method( $subscription->account_id, intval( $token_id ) );
                }
            }
        }
    }

    /**
     * Clear default flag from all ACH payment methods.
     */
    private function clear_ach_defaults() {
        $methods = $this->get_ach_payment_methods();
        foreach ( $methods as $index => $method ) {
            $methods[ $index ]['is_default'] = false;
        }
        $this->save_ach_payment_methods( $methods );
    }

    /**
     * Clear default flag from all payment methods (both cards and ACH).
     */
    private function clear_all_payment_defaults() {
        // Clear WC token defaults using the data store directly
        // Note: $token->set_default(false) + save() doesn't work because 
        // 'is_default' is not in WC's core_props array for the update method
        $data_store = \WC_Data_Store::load( 'payment-token' );
        $tokens = \WC_Payment_Tokens::get_customer_tokens( $this->user_id );
        foreach ( $tokens as $token ) {
            if ( $token->is_default() ) {
                $data_store->set_default_status( $token->get_id(), false );
            }
        }
        
        // Clear ACH defaults
        $this->clear_ach_defaults();
    }

    /**
     * Get the user's default payment method (card or ACH).
     * Returns an object with type, token, and payment details.
     * 
     * @return object|null Payment method object or null if none found.
     */
    public function get_default_payment_method() {
        // Check for default ACH payment method first (stored in user meta)
        $ach_methods = $this->get_ach_payment_methods();
        foreach ( $ach_methods as $ach_method ) {
            if ( ! empty( $ach_method['is_default'] ) && ! empty( $ach_method['verified'] ) ) {
                return (object) [
                    'type'                     => 'ach',
                    'token'                    => $ach_method['token_id'],
                    'stripe_payment_method_id' => $ach_method['stripe_payment_method_id'],
                    'bank_name'                => $ach_method['bank_name'] ?? '',
                    'last4'                    => $ach_method['last4'] ?? '',
                ];
            }
        }
        
        // Check for default WooCommerce card token
        $default_token = \WC_Payment_Tokens::get_customer_default_token( $this->user_id );
        if ( $default_token ) {
            return (object) [
                'type'      => 'card',
                'token'     => $default_token->get_id(),
                'source_id' => $default_token->get_token(),
                'last4'     => method_exists( $default_token, 'get_last4' ) ? $default_token->get_last4() : '',
            ];
        }
        
        return null;
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
        // Check if this is an ACH token stored in user meta
        if ( is_string( $token_id ) && strpos( $token_id, 'ach_' ) === 0 ) {
            return $this->delete_ach_payment_method( $token_id );
        }
        
        // Handle WooCommerce tokens (cards)
        $token = \WC_Payment_Tokens::get( $token_id );
        
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

    /**
     * Delete an ACH payment method from user meta.
     */
    public function delete_ach_payment_method( $token_id ) {
        $methods = $this->get_ach_payment_methods();
        $was_default = false;
        $deleted_method = null;
        
        foreach ( $methods as $index => $method ) {
            if ( $method['token_id'] === $token_id ) {
                $was_default = $method['is_default'] ?? false;
                $deleted_method = $method;
                unset( $methods[ $index ] );
                break;
            }
        }
        
        if ( $deleted_method === null ) {
            return "Error: ACH payment method not found";
        }
        
        // Re-index array
        $methods = array_values( $methods );
        
        // Don't auto-set another ACH as default - let user choose
        // This prevents conflicts with card payment methods
        
        $this->save_ach_payment_methods( $methods );
        
        // Optionally detach the payment method from Stripe customer
        if ( ! empty( $deleted_method['stripe_payment_method_id'] ) ) {
            try {
                \WC_Stripe_API::request( [], "payment_methods/{$deleted_method['stripe_payment_method_id']}/detach", 'POST' );
            } catch ( \Exception $e ) {
                // Log but don't fail - the local record is already deleted
                error_log( "Failed to detach Stripe payment method: " . $e->getMessage() );
            }
        }
        
        return true;
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
        
        // Get card tokens from WooCommerce (skip ACH types to avoid WC Stripe sync issues)
        $payment_tokens = \WC_Payment_Tokens::get_customer_tokens( $this->user_id );
        foreach ( $payment_tokens as $payment_token ) {
            $token_type = $payment_token->get_type();
            $gateway_id = $payment_token->get_gateway_id();
            
            // Skip ACH tokens from WooCommerce - we handle those separately via user meta
            $is_ach = ( $token_type === 'us_bank_account' ) 
                || ( class_exists( 'WC_Payment_Token_ACH' ) && $payment_token instanceof \WC_Payment_Token_ACH )
                || ( $gateway_id === 'stripe_ach' )
                || ( $gateway_id === 'stripe_us_bank_account' );
            
            if ( $is_ach ) {
                continue; // Skip - ACH is handled via user meta below
            }
            
            // Handle card tokens (existing logic)
            $card_type = method_exists( $payment_token, 'get_card_type' ) ? $payment_token->get_card_type() : '';
            $payment_methods[] = [
                'type'       => 'card',
                'method'     => [
                    'brand'   => ( ! empty( $card_type ) ? ucfirst( $card_type ) : esc_html__( 'Credit card', 'woocommerce' ) ),
                    'gateway' => $payment_token->get_gateway_id(),
                    'last4'   => $payment_token->get_last4(),
                ],
                'expires'    => method_exists( $payment_token, 'get_expiry_month' ) ? $payment_token->get_expiry_month() . '/' . substr( $payment_token->get_expiry_year(), -2 ) : null,
                'is_default' => $payment_token->is_default(),
                'token'      => $payment_token->get_id(),
                'verified'   => true, // Cards are always verified
            ];
        }
        
        // Get ACH payment methods from user meta (avoids WC Stripe sync issues)
        $ach_methods = $this->get_ach_payment_methods();
        foreach ( $ach_methods as $ach_method ) {
            $payment_methods[] = [
                'type'       => 'ach',
                'method'     => [
                    'brand'        => 'Bank Account',
                    'bank_name'    => $ach_method['bank_name'] ?? '',
                    'account_type' => $ach_method['account_type'] ?? '',
                    'gateway'      => 'stripe_ach',
                    'last4'        => $ach_method['last4'] ?? '',
                ],
                'expires'    => null,
                'is_default' => $ach_method['is_default'] ?? false,
                'token'      => $ach_method['token_id'],
                'verified'   => $ach_method['verified'] ?? false,
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

    /**
     * Check if an ACH payment token is verified.
     * Stripe ACH accounts need micro-deposit verification unless they used instant verification.
     */
    private function is_ach_verified( $payment_token ) {
        // Check if we have stored verification status in token meta
        $verified = $payment_token->get_meta( 'verified' );
        if ( $verified !== '' ) {
            return (bool) $verified;
        }

        // If no meta, check with Stripe API
        $stripe_pm_id = $payment_token->get_token();
        if ( empty( $stripe_pm_id ) ) {
            return false;
        }

        try {
            $payment_method = \WC_Stripe_API::request( [], "payment_methods/{$stripe_pm_id}", 'GET' );
            
            if ( ! empty( $payment_method->error ) ) {
                return false;
            }

            // Check the verification status from Stripe
            if ( isset( $payment_method->us_bank_account->status_details->blocked ) ) {
                return false;
            }

            // If we got here without errors, the payment method is usable
            // Store the result for future lookups
            $payment_token->add_meta_data( 'verified', '1', true );
            $payment_token->save_meta_data();
            
            return true;
        } catch ( \Exception $e ) {
            return false;
        }
    }

    /**
     * Create a SetupIntent for ACH bank account collection.
     * Uses Financial Connections for instant verification with microdeposit fallback.
     */
    public function create_ach_setup_intent() {
        try {
            $customer    = new \WC_Stripe_Customer( $this->user_id );
            $customer_id = $customer->get_id();
            
            if ( ! $customer_id ) {
                $customer->set_id( $customer->create_customer() );
                $customer_id = $customer->get_id();
            }

            $user    = get_user_by( 'ID', $this->user_id );
            $billing = ( new \WC_Customer( $this->user_id ) )->get_billing();

            $request_params = [
                'customer'             => $customer_id,
                'payment_method_types' => [ 'us_bank_account' ],
                'payment_method_options' => [
                    'us_bank_account' => [
                        'financial_connections' => [
                            'permissions' => [ 'payment_method' ],
                        ],
                        'verification_method' => 'automatic', // Allows instant or microdeposits
                    ],
                ],
                'metadata' => [
                    'user_id' => $this->user_id,
                ],
            ];

            $setup_intent = \WC_Stripe_API::request( $request_params, 'setup_intents' );

            if ( ! empty( $setup_intent->error ) ) {
                return (object) [ 'error' => $setup_intent->error->message ];
            }

            return (object) [
                'client_secret' => $setup_intent->client_secret,
                'setup_intent_id' => $setup_intent->id,
            ];
        } catch ( \Exception $e ) {
            return (object) [ 'error' => $e->getMessage() ];
        }
    }

    /**
     * Get ACH payment methods stored in user meta.
     * Returns array of ACH payment methods for the user.
     */
    public function get_ach_payment_methods() {
        $ach_methods = get_user_meta( $this->user_id, '_captaincore_ach_payment_methods', true );
        return is_array( $ach_methods ) ? $ach_methods : [];
    }

    /**
     * Save ACH payment methods to user meta.
     */
    private function save_ach_payment_methods( $ach_methods ) {
        update_user_meta( $this->user_id, '_captaincore_ach_payment_methods', $ach_methods );
    }

    /**
     * Generate a unique token ID for ACH payment methods.
     */
    private function generate_ach_token_id() {
        return 'ach_' . bin2hex( random_bytes( 8 ) );
    }

    /**
     * Add an ACH payment method after SetupIntent is confirmed.
     * Stores in user meta to avoid WooCommerce Stripe plugin conflicts.
     */
    public function add_ach_payment_method( $setup_intent_id ) {
        try {
            // Retrieve the SetupIntent to get the payment method
            $setup_intent = \WC_Stripe_API::request( [], "setup_intents/{$setup_intent_id}", 'GET' );

            if ( ! empty( $setup_intent->error ) ) {
                return (object) [ 'error' => $setup_intent->error->message ];
            }

            if ( $setup_intent->status !== 'succeeded' && $setup_intent->status !== 'requires_action' ) {
                return (object) [ 'error' => 'SetupIntent is not in a valid state: ' . $setup_intent->status ];
            }

            $payment_method_id = $setup_intent->payment_method;
            if ( empty( $payment_method_id ) ) {
                return (object) [ 'error' => 'No payment method found on SetupIntent' ];
            }

            // Retrieve the payment method details from Stripe
            $payment_method = \WC_Stripe_API::request( [], "payment_methods/{$payment_method_id}", 'GET' );

            if ( ! empty( $payment_method->error ) ) {
                return (object) [ 'error' => $payment_method->error->message ];
            }

            if ( $payment_method->type !== 'us_bank_account' ) {
                return (object) [ 'error' => 'Payment method is not a US bank account' ];
            }

            // Check if this payment method already exists
            $existing_methods = $this->get_ach_payment_methods();
            foreach ( $existing_methods as $method ) {
                if ( $method['stripe_payment_method_id'] === $payment_method_id ) {
                    return (object) [ 'error' => 'This bank account is already saved' ];
                }
            }

            // Determine verification status
            // If SetupIntent succeeded, it means instant verification worked
            // If requires_action, microdeposits are pending
            $is_verified = ( $setup_intent->status === 'succeeded' );

            // Create ACH payment method record
            // New ACH methods are NOT set as default - user must explicitly set them
            $token_id = $this->generate_ach_token_id();
            $ach_method = [
                'token_id'                  => $token_id,
                'stripe_payment_method_id'  => $payment_method_id,
                'setup_intent_id'           => $setup_intent_id,
                'bank_name'                 => $payment_method->us_bank_account->bank_name ?? '',
                'account_type'              => $payment_method->us_bank_account->account_type ?? '',
                'last4'                     => $payment_method->us_bank_account->last4 ?? '',
                'fingerprint'               => $payment_method->us_bank_account->fingerprint ?? '',
                'verified'                  => $is_verified,
                'is_default'                => false,
                'created_at'                => current_time( 'mysql' ),
            ];

            // Add to existing methods and save
            $existing_methods[] = $ach_method;
            $this->save_ach_payment_methods( $existing_methods );

            // Send notification email if verification is pending
            if ( ! $is_verified ) {
                $this->send_ach_pending_notification_for_method( $ach_method );
            }

            return (object) [
                'success'  => true,
                'token_id' => $token_id,
                'verified' => $is_verified,
                'message'  => $is_verified 
                    ? 'Bank account added and verified successfully' 
                    : 'Bank account added. Micro-deposits will be sent within 1-2 business days for verification.',
            ];
        } catch ( \Exception $e ) {
            return (object) [ 'error' => $e->getMessage() ];
        }
    }

    /**
     * Send notification for pending ACH verification (user meta version).
     */
    private function send_ach_pending_notification_for_method( $ach_method ) {
        $user = get_userdata( $this->user_id );
        $admin_email = get_option( 'admin_email' );
        
        $subject = 'ACH Bank Account Pending Verification';
        $message = sprintf(
            "A new bank account has been added and is pending micro-deposit verification.\n\n" .
            "User: %s (%s)\n" .
            "Bank: %s\n" .
            "Account Type: %s\n" .
            "Last 4: %s\n\n" .
            "The customer will need to verify using the micro-deposit amounts once received.",
            $user->display_name,
            $user->user_email,
            $ach_method['bank_name'],
            $ach_method['account_type'],
            $ach_method['last4']
        );
        
        wp_mail( $admin_email, $subject, $message );
    }

    /**
     * Find an ACH payment method by token ID.
     */
    private function find_ach_method_by_token( $token_id ) {
        $methods = $this->get_ach_payment_methods();
        foreach ( $methods as $index => $method ) {
            if ( $method['token_id'] === $token_id ) {
                return [ 'index' => $index, 'method' => $method ];
            }
        }
        return null;
    }

    /**
     * Verify a bank account using micro-deposit amounts.
     * Can be called by customer (self-service) or admin.
     */
    public function verify_bank_account( $token_id, $amounts ) {
        try {
            // Check if this is an ACH token stored in user meta
            $ach_data = $this->find_ach_method_by_token( $token_id );
            
            if ( $ach_data ) {
                // Handle user meta ACH verification
                $method = $ach_data['method'];
                
                if ( $method['verified'] ) {
                    return (object) [ 'error' => 'Bank account is already verified' ];
                }
                
                $setup_intent_id = $method['setup_intent_id'];
                if ( empty( $setup_intent_id ) ) {
                    return (object) [ 'error' => 'No SetupIntent found for this payment method' ];
                }
                
                // Verify with Stripe - amounts must be in cents as separate array items
                $amounts_int = array_map( 'intval', $amounts );
                error_log( "ACH Verify: Sending amounts to Stripe: " . print_r( $amounts_int, true ) );
                error_log( "ACH Verify: Setup Intent ID: " . $setup_intent_id );
                
                $verify_result = \WC_Stripe_API::request(
                    [ 
                        'amounts[0]' => $amounts_int[0],
                        'amounts[1]' => $amounts_int[1],
                    ],
                    "setup_intents/{$setup_intent_id}/verify_microdeposits"
                );
                
                error_log( "ACH Verify: Stripe response: " . print_r( $verify_result, true ) );
                
                if ( ! empty( $verify_result->error ) ) {
                    return (object) [ 'error' => $verify_result->error->message ];
                }
                
                // Update verification status in user meta
                $methods = $this->get_ach_payment_methods();
                $methods[ $ach_data['index'] ]['verified'] = true;
                $this->save_ach_payment_methods( $methods );
                
                return (object) [
                    'success' => true,
                    'message' => 'Bank account verified successfully',
                ];
            }
            
            // Fallback: Check WooCommerce tokens (for legacy support)
            $token = \WC_Payment_Tokens::get( $token_id );

            if ( is_null( $token ) ) {
                return (object) [ 'error' => 'Payment token not found' ];
            }

            // Check ownership (unless admin)
            if ( ! self::is_admin() && $this->user_id !== $token->get_user_id() ) {
                return (object) [ 'error' => 'Permission denied' ];
            }

            // Check if already verified
            if ( $token->get_meta( 'verified' ) === '1' ) {
                return (object) [ 'error' => 'Bank account is already verified' ];
            }

            $setup_intent_id = $token->get_meta( 'setup_intent_id' );
            if ( empty( $setup_intent_id ) ) {
                return (object) [ 'error' => 'No SetupIntent found for this token' ];
            }

            // Validate amounts (should be two integers representing cents)
            if ( ! is_array( $amounts ) || count( $amounts ) !== 2 ) {
                return (object) [ 'error' => 'Please provide exactly two deposit amounts' ];
            }

            $amount1 = intval( $amounts[0] );
            $amount2 = intval( $amounts[1] );

            if ( $amount1 <= 0 || $amount2 <= 0 ) {
                return (object) [ 'error' => 'Invalid deposit amounts' ];
            }

            // Call Stripe to verify microdeposits - amounts must be sent as separate array items
            $verify_params = [
                'amounts[0]' => $amount1,
                'amounts[1]' => $amount2,
            ];

            $result = \WC_Stripe_API::request( 
                $verify_params, 
                "setup_intents/{$setup_intent_id}/verify_microdeposits" 
            );

            if ( ! empty( $result->error ) ) {
                // Handle specific error cases
                if ( strpos( $result->error->message, 'incorrect' ) !== false ) {
                    return (object) [ 'error' => 'The amounts entered are incorrect. Please check your bank statement and try again.' ];
                }
                if ( strpos( $result->error->message, 'exceeded' ) !== false ) {
                    return (object) [ 'error' => 'Too many verification attempts. Please contact support.' ];
                }
                return (object) [ 'error' => $result->error->message ];
            }

            // Update token as verified
            $token->update_meta_data( 'verified', '1' );
            $token->save_meta_data();

            return (object) [
                'success' => true,
                'message' => 'Bank account verified successfully! You can now use it for payments.',
            ];
        } catch ( \Exception $e ) {
            return (object) [ 'error' => $e->getMessage() ];
        }
    }

    /**
     * Get all users with pending ACH verifications (admin only).
     * Checks user meta storage for ACH payment methods.
     */
    public static function get_pending_ach_verifications() {
        global $wpdb;

        $pending = [];
        
        // Get all users with ACH payment methods stored in user meta
        $users_with_ach = $wpdb->get_results( "
            SELECT user_id, meta_value 
            FROM {$wpdb->usermeta} 
            WHERE meta_key = '_captaincore_ach_payment_methods'
        " );
        
        foreach ( $users_with_ach as $row ) {
            $user = get_user_by( 'ID', $row->user_id );
            if ( ! $user ) {
                continue;
            }
            
            $ach_methods = maybe_unserialize( $row->meta_value );
            if ( ! is_array( $ach_methods ) ) {
                continue;
            }
            
            foreach ( $ach_methods as $method ) {
                // Only include unverified methods
                if ( ! empty( $method['verified'] ) ) {
                    continue;
                }
                
                $pending[] = [
                    'token_id'     => $method['token_id'],
                    'user_id'      => $row->user_id,
                    'user_email'   => $user->user_email,
                    'user_name'    => $user->display_name,
                    'bank_name'    => $method['bank_name'] ?? '',
                    'account_type' => $method['account_type'] ?? '',
                    'last4'        => $method['last4'] ?? '',
                    'added_date'   => $method['created_at'] ?? 'Unknown',
                ];
            }
        }

        return $pending;
    }

    /**
     * Send email notification to admins about pending ACH verification.
     */
    private function send_ach_pending_notification( $token ) {
        $user = get_user_by( 'ID', $this->user_id );
        if ( ! $user ) {
            return;
        }

        $bank_name    = $token->get_meta( 'bank_name' );
        $account_type = $token->get_meta( 'account_type' );
        $last4        = $token->get_last4();

        $admin_email = get_option( 'admin_email' );
        $site_name   = get_bloginfo( 'name' );
        $site_url    = home_url();

        $subject = "[{$site_name}] New ACH Bank Account Pending Verification";

        $message = "A customer has added a new bank account that requires micro-deposit verification.\n\n";
        $message .= "Customer: {$user->display_name} ({$user->user_email})\n";
        $message .= "Bank: {$bank_name}\n";
        $message .= "Account Type: " . ucfirst( $account_type ) . "\n";
        $message .= "Last 4 Digits: {$last4}\n\n";
        $message .= "The customer will receive micro-deposits within 1-2 business days. ";
        $message .= "They can verify the account themselves, or you can verify it on their behalf.\n\n";
        $message .= "View pending verifications: {$site_url}/wp-admin/\n";

        wp_mail( $admin_email, $subject, $message );
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
        // Check if this is an ACH token (string starting with 'ach_')
        if ( is_string( $payment_id ) && strpos( $payment_id, 'ach_' ) === 0 ) {
            return $this->pay_invoice_with_ach( $invoice_id, $payment_id );
        }
        
        // Otherwise, process as a card payment (existing logic)
        return $this->pay_invoice_with_card( $invoice_id, $payment_id );
    }

    /**
     * Pay an invoice using a saved ACH bank account.
     */
    private function pay_invoice_with_ach( $invoice_id, $ach_token_id ) {
        try {
            // Get the ACH payment method details from user meta
            $ach_methods = $this->get_ach_payment_methods();
            $ach_method  = null;
            foreach ( $ach_methods as $method ) {
                if ( $method['token_id'] === $ach_token_id ) {
                    $ach_method = $method;
                    break;
                }
            }
            
            if ( ! $ach_method ) {
                return [
                    'result'  => 'fail',
                    'message' => 'ACH payment method not found.',
                ];
            }
            
            if ( empty( $ach_method['verified'] ) ) {
                return [
                    'result'  => 'fail',
                    'message' => 'ACH payment method has not been verified.',
                ];
            }
            
            $order = wc_get_order( $invoice_id );
            if ( ! $order ) {
                return [
                    'result'  => 'fail',
                    'message' => 'Order not found.',
                ];
            }
            
            // Get or create Stripe customer
            $customer    = new \WC_Stripe_Customer( $this->user_id );
            $customer_id = $customer->get_id();
            if ( ! $customer_id ) {
                $customer->set_id( $customer->create_customer() );
                $customer_id = $customer->get_id();
            }
            
            // Create PaymentIntent for ACH
            $amount   = $order->get_total();
            $currency = strtolower( $order->get_currency() );
            
            $intent_data = [
                'amount'               => \WC_Stripe_Helper::get_stripe_amount( $amount, $currency ),
                'currency'             => $currency,
                'customer'             => $customer_id,
                'payment_method'       => $ach_method['stripe_payment_method_id'],
                'payment_method_types' => [ 'us_bank_account' ],
                'confirm'              => 'true',
                'off_session'          => 'true', // This is an automated renewal, not customer-initiated
                'description'          => sprintf( 'Order #%s', $order->get_order_number() ),
                'metadata'             => [
                    'order_id'     => $order->get_id(),
                    'site_url'     => get_site_url(),
                    'payment_type' => 'ach_debit',
                ],
            ];
            
            $intent = \WC_Stripe_API::request( $intent_data, 'payment_intents' );
            
            if ( ! empty( $intent->error ) ) {
                \WC_Stripe_Logger::log( 'ACH PaymentIntent Error: ' . print_r( $intent->error, true ) );
                
                $order->update_status( 'failed', sprintf( 'ACH payment failed: %s', $intent->error->message ) );
                
                return [
                    'result'  => 'fail',
                    'message' => $intent->error->message,
                ];
            }
            
            // ACH payments are typically 'processing' initially (not immediately succeeded)
            // They take 4-5 business days to complete
            if ( in_array( $intent->status, [ 'succeeded', 'processing' ], true ) ) {
                // Store payment intent ID on the order
                $order->update_meta_data( '_stripe_intent_id', $intent->id );
                $order->update_meta_data( '_stripe_charge_captured', 'yes' );
                $order->update_meta_data( '_transaction_id', $intent->id );
                $order->set_payment_method( 'stripe' );
                $order->set_payment_method_title( 'ACH Direct Debit' );
                $order->save();
                
                if ( $intent->status === 'processing' ) {
                    // ACH is processing - will complete in a few days
                    $order->update_status( 'on-hold', 'ACH payment initiated. Funds typically clear in 4-5 business days.' );
                    $order->add_order_note( sprintf( 'Stripe ACH PaymentIntent initiated (ID: %s). Status: processing.', $intent->id ) );
                } else {
                    // Immediate success (rare for ACH but possible)
                    $order->update_status( 'completed' );
                    $order->add_order_note( sprintf( 'Stripe ACH payment complete (PaymentIntent ID: %s).', $intent->id ) );
                }
                
                return [
                    'result'   => 'success',
                    'redirect' => $order->get_checkout_order_received_url(),
                ];
            }
            
            // Handle other statuses
            $order->update_status( 'failed', sprintf( 'ACH payment status: %s', $intent->status ) );
            
            return [
                'result'  => 'fail',
                'message' => sprintf( 'Payment not completed. Status: %s', $intent->status ),
            ];
            
        } catch ( \Exception $e ) {
            \WC_Stripe_Logger::log( 'ACH Payment Error: ' . $e->getMessage() );
            
            if ( isset( $order ) && $order ) {
                $order->update_status( 'failed', $e->getMessage() );
            }
            
            return [
                'result'  => 'fail',
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * Pay an invoice using a saved card (original logic).
     */
    private function pay_invoice_with_card( $invoice_id, $payment_id ) {
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
        $user = get_user_by( "ID", $this->user_id );
        if ( ! $user ) {
            return [
                "user_id"     => $this->user_id,
                "account_ids" => [],
                "username"    => "",
                "email"       => "",
                "name"        => "Unknown User",
            ];
        }
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

        // Check if user has email_subscriber role
        $is_email_subscriber = in_array( 'email_subscriber', $user->roles );

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
            "pinned_environments" => $pinned,
            "email_subscriber"    => $is_email_subscriber
        ];
    }

}