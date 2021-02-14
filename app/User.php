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
        $customer = new \WC_Stripe_Customer( $this->user_id );
        $response = $customer->add_source( $source_id );
        return $response;
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
        $invoices       = wc_get_orders( [ 'customer' => $this->user_id ] );
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
        // Fetch WooCommerce subscriptions
        $current_subscriptions = wcs_get_users_subscriptions( $this->user_id );
        foreach ( $current_subscriptions as $key => $subscription ) {
            $interval   = $subscription->get_billing_period();
            $line_items = $subscription->get_data()["line_items"];
            $line_item  = array_shift ( array_values ( $line_items ) );
            $details    = $line_item->get_meta_data();
            foreach ( $details as $detail ) {
                $item = $detail->get_data();
                if ( isset( $item["key"]) && $item["key"] == "Details" ) {
                    $name = $item["value"];
                }
            }

            if ( $interval == "month" ) {
                $interval_count = "1";
            } else {
                $interval_count = "12";
            }
            $subscriptions[] = [
                "account_id" => $subscription->id,
                "name"       => $name,
                "type"       => "woocommerce",
                "plan"       => (object) [
                    "name"         => $line_item->get_name(),
                    "next_renewal" => empty( $subscription->get_date( "next_payment" ) ) ? "" : $subscription->get_date( "next_payment" ),
                    "price"        => $subscription->get_total(),
                    "usage"        => (object) [],
                    "limits"       => (object) [],
                    "interval"     => $interval_count,
                    "addons"       => [],
                ],
                "payment_method"  => $subscription->get_payment_method_to_display( 'customer' ),
                "status"          => wcs_get_subscription_status_name( $subscription->get_status() ),
            ];
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

            $available_gateways = WC()->payment_gateways->get_available_payment_gateways();
            $payment_method     = isset( $available_gateways[ 'stripe' ] ) ? $available_gateways[ 'stripe' ] : false;
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
            $source_object = \WC_Stripe_API::retrieve( 'sources/' . $source_id );

            $prepared_source = (object) [
                'token_id'      => $payment_id,
                'customer'      => $customer_id,
                'source'        => $source_id,
                'source_object' => $source_object,
            ];

            $available_gateways = WC()->payment_gateways->get_available_payment_gateways();
            $payment_method     = isset( $available_gateways[ 'stripe' ] ) ? $available_gateways[ 'stripe' ] : false;
            $order              = wc_get_order( $invoice_id );
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
				// Use the last charge within the intent to proceed.
				$response = end( $intent->charges->data );
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

    public function user_id() {
        return $this->user_id;
    }

}