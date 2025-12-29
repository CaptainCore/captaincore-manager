<?php 

namespace CaptainCore;

class Mailer {

    static function prepare() {
        if ( ! defined( 'CAPTAINCORE_CUSTOM_DOMAIN' ) ) {
            return;
        }

        $account_portal = AccountPortals::current();
        $configurations = empty( $account_portal->configurations ) ? (object) [] : json_decode( $account_portal->configurations );
        if ( empty( $configurations->emails ) ) {
            return;
        }

        define( 'GRAVITYSMTP_GENERIC_ENCRYPTION_TYPE', $configurations->emails->encryption_type );
        define( 'GRAVITYSMTP_GENERIC_HOST', $configurations->emails->host );
        define( 'GRAVITYSMTP_GENERIC_PORT', $configurations->emails->port );
        define( 'GRAVITYSMTP_GENERIC_AUTH', true );
        define( 'GRAVITYSMTP_GENERIC_AUTH_TLS', $configurations->emails->auth_tls );
        define( 'GRAVITYSMTP_GENERIC_USERNAME', $configurations->emails->username );
        define( 'GRAVITYSMTP_GENERIC_PASSWORD', $configurations->emails->password );
        define( 'GRAVITYSMTP_GENERIC_FROM_EMAIL', $configurations->emails->from_email );
        define( 'GRAVITYSMTP_GENERIC_FORCE_FROM_EMAIL', true );
        define( 'GRAVITYSMTP_GENERIC_FROM_NAME', $configurations->emails->from_name );
        define( 'GRAVITYSMTP_GENERIC_FORCE_FROM_NAME', true );
        define( 'GRAVITYSMTP_GENERIC_ENABLED', true );
    }

    static public function send( $email = "", $subject = "", $content = "", $extra_headers = [] ) {
        self::prepare();
        $headers = array_merge( ["Content-Type: text/html; charset=UTF-8"], $extra_headers );
        wp_mail( $email, $subject, $content, $headers );
    }

    /* -------------------------------------------------------------------------
     *  HELPER: Generate Billing Address HTML
     * ------------------------------------------------------------------------- */
    private static function get_billing_address_html( $order ) {
        $address = $order->get_address( 'billing' );
        
        // Construct City/State/Zip line conditionally
        $city_state_zip = '';
        if ( ! empty( $address['city'] ) ) {
            $city_state_zip .= $address['city'];
            if ( ! empty( $address['state'] ) ) {
                $city_state_zip .= ', ' . $address['state'];
            }
            if ( ! empty( $address['postcode'] ) ) {
                $city_state_zip .= ' ' . $address['postcode'];
            }
        } else {
            // Fallback if city is empty but we have state/zip
            if ( ! empty( $address['state'] ) || ! empty( $address['postcode'] ) ) {
                $city_state_zip .= trim( $address['state'] . ' ' . $address['postcode'] );
            }
        }

        // Build the full address array, filtering out empty values
        $lines = array_filter([
            ( $address['company'] ) ? $address['company'] : '',
            ( $address['first_name'] || $address['last_name'] ) ? $address['first_name'] . ' ' . $address['last_name'] : '',
            $address['address_1'],
            $address['address_2'],
            trim( $city_state_zip ), // Ensure we don't have a line with just whitespace
            WC()->countries->countries[ $address['country'] ] ?? $address['country']
        ]);

        $formatted_address = implode( '<br>', $lines );

        return "
            <div style='text-align: left; margin-top: 30px; border-top: 1px solid #edf2f7; padding-top: 20px;'>
                <h4 style='margin: 0 0 10px; font-size: 11px; text-transform: uppercase; color: #a0aec0; letter-spacing: 0.05em;'>Billing Address</h4>
                <p style='margin: 0; font-size: 14px; color: #4a5568; line-height: 1.5;'>{$formatted_address}</p>
            </div>
        ";
    }

    /* -------------------------------------------------------------------------
     *  HELPER: Generate Line Items HTML
     * ------------------------------------------------------------------------- */
    private static function get_line_items_html( $order, $brand_color ) {
        $items_html = '';
        foreach ( $order->get_items() as $item_id => $item ) {
            $product_name = $item->get_name();
            $total_price  = wc_price( $item->get_total(), array( 'currency' => $order->get_currency() ) );
            
            $meta_data = $item->get_meta_data();
            $details = '';
            foreach ( $meta_data as $meta ) {
                if ( $meta->key === 'Details' ) {
                    $details = '<div style="font-size: 12px; color: #718096; margin-top: 4px;">' . nl2br( $meta->value ) . '</div>';
                }
            }

            $items_html .= "
            <tr>
                <td style='padding: 12px 0; border-bottom: 1px solid #edf2f7; text-align: left;'>
                    <div style='font-weight: 600; color: #2d3748;'>{$product_name}</div>
                    {$details}
                </td>
                <td style='padding: 12px 0; border-bottom: 1px solid #edf2f7; text-align: right; vertical-align: top; color: #2d3748;'>
                    {$total_price}
                </td>
            </tr>";
        }

        $total = $order->get_formatted_order_total();

        return "
        <h3 style='margin: 0 0 15px; font-size: 14px; text-transform: uppercase; letter-spacing: 0.05em; color: #a0aec0; text-align: left;'>Details</h3>
        <table role='presentation' border='0' cellpadding='0' cellspacing='0' width='100%' style='font-size: 14px;'>
            {$items_html}
            <tr>
                <td style='padding-top: 15px; font-weight: 700; color: #2d3748; text-align: right;'>Total</td>
                <td style='padding-top: 15px; font-weight: 700; color: {$brand_color}; text-align: right; font-size: 16px;'>{$total}</td>
            </tr>
        </table>";
    }

    /* -------------------------------------------------------------------------
     *  CORE TEMPLATE WRAPPER
     * ------------------------------------------------------------------------- */
    private static function send_email_with_layout( $to, $subject, $headline, $subheadline, $main_content_html, $extra_headers = [] ) {
        self::prepare();

        $config      = Configurations::get();
        $brand_color = $config->colors->primary ?? '#0D47A1';
        $logo_url    = $config->logo ?? '';
        $site_name   = get_bloginfo( 'name' );
        $site_url    = home_url();

        $message = "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='UTF-8'>
            <meta name='viewport' content='width=device-width, initial-scale=1.0'>
            <title>{$subject}</title>
        </head>
        <body style='margin: 0; padding: 0; background-color: #f7fafc; font-family: -apple-system, BlinkMacSystemFont, \"Segoe UI\", Roboto, Helvetica, Arial, sans-serif; color: #4a5568;'>
            <table role='presentation' border='0' cellpadding='0' cellspacing='0' width='100%'>
                <tr>
                    <td style='padding: 40px 20px; text-align: center;'>
                        
                        <div style='margin-bottom: 30px;'>
                            <img src='{$logo_url}' alt='{$site_name}' style='max-height: 50px; width: auto;'>
                        </div>

                        <table role='presentation' border='0' cellpadding='0' cellspacing='0' width='100%' style='max-width: 600px; margin: 0 auto; background-color: #ffffff; border-radius: 8px; box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05); overflow: hidden;'>
                            
                            <!-- Header Area -->
                            <tr>
                                <td style='padding: 40px; text-align: center; background-color: #ffffff; border-bottom: 1px solid #edf2f7;'>
                                    <h1 style='margin: 0 0 10px; font-size: 24px; font-weight: 800; color: #2d3748;'>{$headline}</h1>
                                    <p style='margin: 0; font-size: 16px; color: #718096;'>{$subheadline}</p>
                                </td>
                            </tr>

                            <!-- Main Content Area -->
                            <tr>
                                <td style='padding: 40px;'>
                                    {$main_content_html}
                                </td>
                            </tr>
                            
                            <!-- Internal Footer Area -->
                            <tr>
                                <td style='padding: 30px 40px; background-color: #f7fafc; border-top: 1px solid #edf2f7; text-align: center;'>
                                    <p style='margin: 0; font-size: 14px; color: #718096;'>
                                        Questions? <a href='mailto:" . get_option('admin_email') . "' style='color: {$brand_color}; text-decoration: none;'>Contact Support</a>
                                    </p>
                                </td>
                            </tr>
                        </table>

                        <div style='margin-top: 30px; font-size: 12px; color: #a0aec0;'>
                             <p style='margin: 0;'><a href='{$site_url}' style='color: #a0aec0; text-decoration: none;'>{$site_name}</a></p>
                        </div>

                    </td>
                </tr>
            </table>
        </body>
        </html>
        ";

        self::send( $to, $subject, $message, $extra_headers );
    }

    /* -------------------------------------------------------------------------
     *  1. STANDARD INVOICE (Success/Pending)
     * ------------------------------------------------------------------------- */
    static public function send_order_invoice( $order_id ) {
        $order = wc_get_order( $order_id );
        if ( ! $order ) return;

        $config      = Configurations::get();
        $brand_color = $config->colors->primary ?? '#0D47A1';
        $site_name   = get_bloginfo( 'name' );

        $total        = $order->get_formatted_order_total();
        $date         = $order->get_date_created()->date( 'F j, Y' );
        $pay_link     = captaincore_get_checkout_payment_url( $order->get_checkout_payment_url() );
        $billing      = $order->get_address( 'billing' );
        $email        = $billing['email'];
        
        if ( empty( $email ) ) {
             $user  = get_user_by( 'id', $order->get_customer_id() );
             $email = $user->user_email;
        }

        $account_id = $order->get_meta( 'captaincore_account_id' );
        if ( $account_id ) {
            $account = ( new Accounts )->get( $account_id );
            if ( $account ) {
                $plan = json_decode( $account->plan );
                if ( ! empty( $plan->additional_emails ) ) {
                    $email .= ", {$plan->additional_emails}";
                }
            }
        }

        $items_html   = self::get_line_items_html( $order, $brand_color );
        $billing_html = self::get_billing_address_html( $order );

        $content_html = "
            <div style='text-align: center; margin-bottom: 40px;'>
                <div style='margin-bottom: 20px; font-size: 36px; font-weight: 700; color: {$brand_color};'>{$total}</div>
                <table role='presentation' border='0' cellpadding='0' cellspacing='0' style='margin: 0 auto;'>
                    <tr>
                        <td style='border-radius: 4px; background-color: {$brand_color};'>
                            <a href='{$pay_link}' target='_blank' style='border: 1px solid {$brand_color}; border-radius: 4px; color: #ffffff; display: inline-block; font-size: 16px; font-weight: 600; padding: 12px 30px; text-decoration: none;'>Pay Invoice &rarr;</a>
                        </td>
                    </tr>
                </table>
            </div>
            {$items_html}
            {$billing_html}
        ";

        self::send_email_with_layout( $email, "Invoice #{$order_id} from {$site_name}", "Invoice #{$order_id}", $date, $content_html );
    }

    /* -------------------------------------------------------------------------
     *  2. PAYMENT FAILED NOTICE (Mimics Invoice)
     * ------------------------------------------------------------------------- */
    static public function send_failed_payment_notice( $account_id, $orders ) {
        if ( empty( $orders ) ) return;

        $config      = Configurations::get();
        $brand_color = $config->colors->primary ?? '#0D47A1';
        $site_name   = get_bloginfo( 'name' );
        $admin_email = get_option( 'admin_email' );
        
        $headers = [ "Bcc: $admin_email" ];

        $account   = ( new Accounts )->get( $account_id );
        $plan      = json_decode( $account->plan );
        $email     = '';

        // SINGLE FAILED ORDER
        if ( count( $orders ) === 1 ) {
            $order        = $orders[0];
            $order_id     = $order->get_id();
            $pay_link     = captaincore_get_checkout_payment_url( $order->get_checkout_payment_url() );
            $total        = $order->get_formatted_order_total();
            $date         = $order->get_date_created()->date( 'F j, Y' );
            $billing      = $order->get_address( 'billing' );
            $email        = $billing['email'];

            if ( empty( $email ) ) {
                $user  = get_user_by( 'id', $plan->billing_user_id );
                $email = $user->user_email;
            }
            if ( ! empty( $plan->additional_emails ) ) {
                $email .= ", {$plan->additional_emails}";
            }

            $items_html   = self::get_line_items_html( $order, $brand_color );
            $billing_html = self::get_billing_address_html( $order );

            $intro_html = "<div style='text-align: center; margin-bottom: 20px; color: #e53e3e; font-weight: 600;'>Payment Failed</div>";

            $content_html = "
                {$intro_html}
                <div style='text-align: center; margin-bottom: 40px;'>
                    <div style='margin-bottom: 20px; font-size: 36px; font-weight: 700; color: {$brand_color};'>{$total}</div>
                    <table role='presentation' border='0' cellpadding='0' cellspacing='0' style='margin: 0 auto;'>
                        <tr>
                            <td style='border-radius: 4px; background-color: {$brand_color};'>
                                <a href='{$pay_link}' target='_blank' style='border: 1px solid {$brand_color}; border-radius: 4px; color: #ffffff; display: inline-block; font-size: 16px; font-weight: 600; padding: 12px 30px; text-decoration: none;'>Pay Invoice &rarr;</a>
                            </td>
                        </tr>
                    </table>
                </div>
                {$items_html}
                {$billing_html}
            ";

            self::send_email_with_layout( 
                $email, 
                "Payment Failed: Invoice #{$order_id}", 
                "Invoice #{$order_id}", 
                $date, 
                $content_html,
                $headers
            );

        } else {
            // MULTIPLE FAILED ORDERS
            $order    = $orders[0];
            $billing  = $order->get_address( 'billing' );
            $email    = $billing['email'];
            if ( empty( $email ) ) {
                $user  = get_user_by( 'id', $plan->billing_user_id );
                $email = $user->user_email;
            }
            if ( ! empty( $plan->additional_emails ) ) {
                $email .= ", {$plan->additional_emails}";
            }
            
            $billing_html = self::get_billing_address_html( $order );
            $order_list_html = "";

            foreach ( $orders as $o ) {
                $pay_link = captaincore_get_checkout_payment_url( $o->get_checkout_payment_url() );
                $total    = $o->get_formatted_order_total();
                $date     = $o->get_date_created()->date('F j, Y');
    
                $order_list_html .= "
                <div style='background-color: #ffffff; border: 1px solid #e2e8f0; border-radius: 6px; padding: 20px; margin-bottom: 15px;'>
                    <table width='100%'>
                        <tr>
                            <td style='vertical-align: middle; text-align: left;'>
                                <div style='font-weight: 700; color: #2d3748; font-size: 16px;'>Order #{$o->get_id()}</div>
                                <div style='color: #718096; font-size: 14px;'>{$date}</div>
                            </td>
                            <td style='vertical-align: middle; text-align: right;'>
                                <div style='font-weight: 700; color: {$brand_color}; font-size: 16px; margin-bottom: 8px;'>{$total}</div>
                                <a href='{$pay_link}' style='font-size: 14px; font-weight: 600; color: {$brand_color}; text-decoration: none;'>Pay Now &rarr;</a>
                            </td>
                        </tr>
                    </table>
                </div>";
            }

            $intro_text = "<p style='margin-bottom: 25px; line-height: 1.6;'>Multiple payments have failed for account <strong>{$account->name}</strong>. Please review below.</p>";

            self::send_email_with_layout( 
                $email, 
                "Action Required: Failed Payments", 
                "Payment Failed", 
                "Multiple Orders", 
                $intro_text . $order_list_html . $billing_html,
                $headers
            );
        }
    }

    /* -------------------------------------------------------------------------
     *  3. ACCOUNT OUTSTANDING NOTICE (Summary)
     * ------------------------------------------------------------------------- */
    static public function send_outstanding_payment_notice( $account_id, $orders ) {
        if ( empty( $orders ) ) return;

        $config      = Configurations::get();
        $brand_color = $config->colors->primary ?? '#0D47A1';
        $site_name   = get_bloginfo( 'name' );

        $account   = ( new Accounts )->get( $account_id );
        $plan      = json_decode( $account->plan );
        $customer  = new \WC_Customer( $plan->billing_user_id );
        $address   = $customer->get_billing();
        $email     = $address["email"];
        
        if ( empty( $email ) ) {
            $user  = get_user_by( 'id', $plan->billing_user_id );
            $email = $user->user_email;
        }
        if ( ! empty( $plan->additional_emails ) ) {
            $email .= ", {$plan->additional_emails}";
        }

        // Fetch Sites for this account
        $sites = ( new Account( $account_id ) )->sites();
        $site_list_html = "";
        
        if ( ! empty( $sites ) ) {
            $site_list_html = "<div style='background-color: #f7fafc; border: 1px solid #e2e8f0; border-radius: 6px; padding: 15px; margin-bottom: 25px;'>
                <h4 style='margin: 0 0 10px; font-size: 11px; text-transform: uppercase; color: #a0aec0; letter-spacing: 0.05em;'>Active Sites</h4>
                <ul style='margin: 0; padding-left: 20px; color: #4a5568; font-size: 14px;'>";
            
            foreach ( $sites as $s ) {
                $site_list_html .= "<li style='margin-bottom: 4px;'>{$s['name']}</li>";
            }
            $site_list_html .= "</ul></div>";
        }

        // Build Invoice List
        $order_list_html = "";
        foreach ( $orders as $order ) {
            $pay_link = captaincore_get_checkout_payment_url( $order->get_checkout_payment_url() );
            $total    = $order->get_formatted_order_total();
            $date     = $order->get_date_created()->date('F j, Y');

            $order_list_html .= "
            <div style='background-color: #ffffff; border: 1px solid #e2e8f0; border-radius: 6px; padding: 20px; margin-bottom: 15px;'>
                <table width='100%'>
                    <tr>
                        <td style='vertical-align: middle; text-align: left;'>
                            <div style='font-weight: 700; color: #2d3748; font-size: 16px;'>Order #{$order->get_id()}</div>
                            <div style='color: #718096; font-size: 14px;'>{$date}</div>
                        </td>
                        <td style='vertical-align: middle; text-align: right;'>
                            <div style='font-weight: 700; color: {$brand_color}; font-size: 16px; margin-bottom: 8px;'>{$total}</div>
                            <a href='{$pay_link}' style='font-size: 14px; font-weight: 600; color: {$brand_color}; text-decoration: none;'>Pay Now &rarr;</a>
                        </td>
                    </tr>
                </table>
            </div>";
        }

        $billing_html = self::get_billing_address_html( $orders[0] );

        $intro_text = "<p style='margin-bottom: 25px; line-height: 1.6;'>There are outstanding payments relating to your hosting plan with {$site_name} for account <strong>{$account->name}</strong>. To keep hosting services active, please pay the outstanding invoice(s) below.</p>";

        self::send_email_with_layout( 
            $email, 
            "Action Required: Outstanding Invoices", 
            "Payment Overdue", 
            $account->name, 
            $intro_text . $site_list_html . $order_list_html . $billing_html
        );
    }

    /* -------------------------------------------------------------------------
     *  4. CUSTOMER RECEIPT (Order Completed)
     * ------------------------------------------------------------------------- */
    static public function send_customer_receipt( $order_id ) {
        $order = wc_get_order( $order_id );
        if ( ! $order ) return;

        $config      = Configurations::get();
        $brand_color = $config->colors->primary ?? '#0D47A1';
        $admin_email = get_option( 'admin_email' );
        
        $total   = $order->get_formatted_order_total();
        $date    = $order->get_date_created()->date( 'F j, Y' );
        $billing = $order->get_address( 'billing' );
        $email   = $billing['email'];

        if ( empty( $email ) ) {
             $user  = get_user_by( 'id', $order->get_customer_id() );
             $email = $user->user_email;
        }

        $account_id = $order->get_meta( 'captaincore_account_id' );
        if ( $account_id ) {
            $account = ( new Accounts )->get( $account_id );
            if ( $account ) {
                $plan = json_decode( $account->plan );
                if ( ! empty( $plan->additional_emails ) ) {
                    $email .= ", {$plan->additional_emails}";
                }
            }
        }

        $items_html   = self::get_line_items_html( $order, $brand_color );
        $billing_html = self::get_billing_address_html( $order );

        $intro_html = "
            <div style='text-align: center; margin-bottom: 20px;'>
                <div style='display: inline-block; background-color: #C6F6D5; color: #22543D; font-size: 12px; font-weight: 700; padding: 6px 12px; border-radius: 9999px; text-transform: uppercase; letter-spacing: 0.05em;'>
                    Paid in Full
                </div>
            </div>
            <div style='text-align: center; margin-bottom: 40px;'>
                <div style='margin-bottom: 10px; font-size: 36px; font-weight: 700; color: {$brand_color};'>{$total}</div>
                <div style='color: #718096; font-size: 14px;'>Thank you for your business.</div>
            </div>
        ";

        self::send_email_with_layout( 
            $email, 
            "Receipt for Order #{$order_id}", 
            "Receipt #{$order_id}", 
            $date, 
            $intro_html . $items_html . $billing_html,
            [ "Bcc: $admin_email" ]
        );
    }

    /* -------------------------------------------------------------------------
     *  5. ADMIN NEW ORDER NOTIFICATION
     * ------------------------------------------------------------------------- */
    static public function send_admin_new_order( $order_id ) {
        $order = wc_get_order( $order_id );
        if ( ! $order ) return;

        $config      = Configurations::get();
        $brand_color = $config->colors->primary ?? '#0D47A1';
        $admin_email = get_option( 'admin_email' );

        $total         = $order->get_formatted_order_total();
        $date          = $order->get_date_created()->date( 'F j, Y @ g:i a' );
        $customer_name = $order->get_formatted_billing_full_name();
        $edit_link     = admin_url( 'post.php?post=' . $order_id . '&action=edit' );

        if ( empty( trim( $customer_name ) ) ) {
            $user = get_user_by( 'id', $order->get_customer_id() );
            $customer_name = $user ? $user->display_name : 'Guest';
        }

        $items_html   = self::get_line_items_html( $order, $brand_color );
        $billing_html = self::get_billing_address_html( $order );

        $content_html = "
            <div style='text-align: center; margin-bottom: 30px;'>
                <h2 style='font-size: 20px; font-weight: 700; color: #2d3748; margin: 0 0 5px;'>{$customer_name}</h2>
                <div style='font-size: 24px; font-weight: 700; color: {$brand_color}; margin-bottom: 20px;'>{$total}</div>
                
                <table role='presentation' border='0' cellpadding='0' cellspacing='0' style='margin: 0 auto;'>
                    <tr>
                        <td style='border-radius: 4px; background-color: #2d3748;'>
                            <a href='{$edit_link}' target='_blank' style='border: 1px solid #2d3748; border-radius: 4px; color: #ffffff; display: inline-block; font-size: 14px; font-weight: 600; padding: 10px 20px; text-decoration: none;'>View in Admin</a>
                        </td>
                    </tr>
                </table>
            </div>
            {$items_html}
            {$billing_html}
        ";

        self::send_email_with_layout( 
            $admin_email, 
            "[New Order] #{$order_id} - {$customer_name} - {$total}", 
            "New Order #{$order_id}", 
            $date, 
            $content_html 
        );
    }
    
    static public function notify_new_user( $user_id = "" ) {
        self::prepare();
        wp_mail();
    }

}