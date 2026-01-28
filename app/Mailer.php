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
        $account_id = $order->get_meta( 'captaincore_account_id' );

        foreach ( $order->get_items() as $item_id => $item ) {
            $product_name = $item->get_name();
            $qty          = $item->get_quantity();
            $total_price  = wc_price( $item->get_total(), array( 'currency' => $order->get_currency() ) );
            
            if ( $qty > 1 ) {
                $product_name .= " x {$qty}";
            }

            $meta_data             = $item->get_meta_data();
            $details               = '';
            $is_managed_sites_item = false;

            // Check if Product Name indicates this is the managed sites item
            if ( strpos( $product_name, "Managed WordPress sites" ) !== false ) {
                $is_managed_sites_item = true;
            }

            foreach ( $meta_data as $meta ) {
                if ( $meta->key === 'Details' ) {
                    $details = '<div style="font-size: 12px; color: #718096; margin-top: 4px;">' . nl2br( $meta->value ) . '</div>';
                    
                    // Check if the Details meta indicates this is the managed sites item
                    if ( strpos( $meta->value, "Managed WordPress sites" ) !== false ) {
                        $is_managed_sites_item = true;
                    }
                }
            }

            // Expand Managed WordPress sites details if flag was set by Name or Meta
            if ( $is_managed_sites_item && $account_id ) {
                $sites = \CaptainCore\Sites::where( [ "account_id" => $account_id, "status" => "active" ] );
                $maintenance_sites = [];
                foreach ( $sites as $site ) {
                    // Filter for sites that are not using the default provider (ID 1)
                    if ( ! empty( $site->provider_id ) && ( $site->provider_id != "1" ) ) {
                        $maintenance_sites[] = $site->name;
                    }
                }
                if ( ! empty( $maintenance_sites ) ) {
                    sort($maintenance_sites);
                    $site_list_html = implode(", ", $maintenance_sites);
                    $count = count($maintenance_sites);
                    $details .= "<div style='font-size: 12px; color: #718096; margin-top: 8px; line-height: 1.5em;'><strong>{$count} Sites Included:</strong><br/>{$site_list_html}</div>";
                }
            }

            $items_html .= "
            <tr>
                <td style='padding: 12px 0; border-bottom: 1px solid #edf2f7; text-align: left;'>
                    <div style='font-weight: 600; color: #2d3748;'>{$product_name}</div>
                    {$details}
                </td>
                <td style='padding: 12px 0; border-bottom: 1px solid #edf2f7; text-align: right; vertical-align: top; color: #2d3748; width: 1%; white-space: nowrap;'>
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
                <td style='padding-top: 15px; padding-right: 15px; font-weight: 700; color: #2d3748; text-align: right;'>Total</td>
                <td style='padding-top: 15px; font-weight: 700; color: {$brand_color}; text-align: right; font-size: 16px; white-space: nowrap;'>{$total}</td>
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
     *  STANDARD INVOICE (Success/Pending)
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
     *  PAYMENT FAILED NOTICE
     * ------------------------------------------------------------------------- */
    static public function send_failed_payment_notice( $account_id, $orders ) {
        if ( empty( $orders ) ) return;

        $config      = Configurations::get();
        $brand_color = $config->colors->primary ?? '#0D47A1';
        $site_name   = get_bloginfo( 'name' );
        $admin_email = get_option( 'admin_email' );
        
        $headers = [ "Bcc: $admin_email", "Reply-To: $admin_email" ];

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
     *  ACCOUNT OUTSTANDING NOTICE (Summary)
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
     *  CUSTOMER RECEIPT (Order Completed)
     * ------------------------------------------------------------------------- */
    static public function send_customer_receipt( $order_id ) {
        $order = wc_get_order( $order_id );
        if ( ! $order ) return;

        $config      = Configurations::get();
        $brand_color = $config->colors->primary ?? '#0D47A1';
        $admin_email = get_option( 'admin_email' );

        // Add Admin as BCC
        $headers = [ "Bcc: $admin_email" ];

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
            $headers
        );
    }
    
    /* -------------------------------------------------------------------------
     *  NEW USER NOTIFICATION
     * ------------------------------------------------------------------------- */
    static public function notify_new_user( $user_id = "" ) {
        $user = get_userdata( $user_id );
        if ( ! $user ) return;

        // 1. Prepare Data
        $config        = Configurations::get();
        $brand_color   = $config->colors->primary ?? '#0D47A1';
        $site_name     = get_bloginfo( 'name' ); // e.g., "Anchor Hosting"
        $admin_email   = get_option( 'admin_email' );
        
        // Handle First Name or fallback to Login
        $first_name = ! empty( $user->first_name ) ? $user->first_name : $user->user_login;

        // 2. Generate One-time Password Set-up Link
        $key = get_password_reset_key( $user );
        if ( is_wp_error( $key ) ) {
            return;
        }
        $action_link = network_site_url( "wp-login.php?action=rp&key=$key&login=" . rawurlencode( $user->user_login ), 'login' );

        // 3. Construct Body HTML
        $content_html = "
            <div style='text-align: left; font-size: 16px; line-height: 1.6; color: #4a5568;'>
                <p>Welcome to {$site_name}. With the following, you can sign into your {$site_name} account in order to manage WordPress hosting services. Let me know at <a href='mailto:{$admin_email}' style='color: {$brand_color}; text-decoration: none;'>{$admin_email}</a> if you have any questions.</p>
                
                <div style='background-color: #ffffff; border: 1px solid #e2e8f0; border-radius: 6px; padding: 20px; margin: 25px 0; text-align: center;'>
                    <p style='margin-bottom: 5px; font-size: 14px; color: #718096; text-transform: uppercase; letter-spacing: 0.05em;'>Your Login</p>
                    <div style='font-size: 20px; font-weight: 700; color: #2d3748; margin-bottom: 20px;'>{$user->user_login}</div>
                    
                    <table role='presentation' border='0' cellpadding='0' cellspacing='0' style='margin: 0 auto;'>
                        <tr>
                            <td style='border-radius: 4px; background-color: {$brand_color};'>
                                <a href='{$action_link}' target='_blank' style='border: 1px solid {$brand_color}; border-radius: 4px; color: #ffffff; display: inline-block; font-size: 16px; font-weight: 600; padding: 12px 30px; text-decoration: none;'>One time set-up password link &rarr;</a>
                            </td>
                        </tr>
                    </table>
                </div>

                <p style='margin-top: 30px;'>
                    Austin Ginder
                </p>
            </div>
        ";

        // 4. Send using the established layout
        self::send_email_with_layout( 
            $user->user_email, 
            "Welcome to {$site_name}", 
            "Hey {$first_name},", 
            "Account Created", 
            $content_html 
        );
    }

    /* -------------------------------------------------------------------------
     *  PASSWORD RESET NOTIFICATION
     * ------------------------------------------------------------------------- */
    static public function send_password_reset( $user, $key ) {
        if ( ! $user ) return;

        // 1. Prepare Data
        $config      = Configurations::get();
        $brand_color = $config->colors->primary ?? '#0D47A1';
        $site_name   = get_bloginfo( 'name' );
        $login       = $user->user_login;

        // 2. Generate Reset Link (Points to wp-login.php?action=rp)
        $reset_link = network_site_url( "wp-login.php?action=rp&key=$key&login=" . rawurlencode( $login ), 'login' );

        // 3. Construct Body HTML
        $content_html = "
            <div style='text-align: center; font-size: 16px; line-height: 1.6; color: #4a5568;'>
                <p>Someone has requested a password reset for the following account:</p>
                
                <div style='background-color: #ffffff; border: 1px solid #e2e8f0; border-radius: 6px; padding: 15px; margin: 20px 0; display: inline-block;'>
                    <strong style='color: #2d3748;'>{$login}</strong>
                </div>

                <p>If this was a mistake, just ignore this email and nothing will happen.</p>
                <p>To reset your password, click the button below:</p>

                <table role='presentation' border='0' cellpadding='0' cellspacing='0' style='margin: 30px auto;'>
                    <tr>
                        <td style='border-radius: 4px; background-color: {$brand_color};'>
                            <a href='{$reset_link}' target='_blank' style='border: 1px solid {$brand_color}; border-radius: 4px; color: #ffffff; display: inline-block; font-size: 16px; font-weight: 600; padding: 12px 30px; text-decoration: none;'>Reset Password &rarr;</a>
                        </td>
                    </tr>
                </table>
            </div>
        ";

        // 4. Send email
        self::send_email_with_layout( 
            $user->user_email, 
            "Password Reset Request", 
            "Reset Password", 
            $site_name, 
            $content_html 
        );
    }

    /* -------------------------------------------------------------------------
     *  ACCESS GRANTED NOTIFICATION (Existing User)
     * ------------------------------------------------------------------------- */
    static public function send_access_granted_notification( $to_email, $account_name, $sites = [], $domains = [] ) {
        $config      = Configurations::get();
        $brand_color = $config->colors->primary ?? '#0D47A1';
        $login_url   = home_url() . ( $config->path ?? '/account/' );

        // 1. Build Site List Preview
        $site_list_html = "";
        if ( ! empty( $sites ) ) {
            $site_list_html = "<div style='background-color: #f7fafc; border: 1px solid #e2e8f0; border-radius: 6px; padding: 15px; margin-top: 20px; text-align: left;'>
                <h4 style='margin: 0 0 10px; font-size: 11px; text-transform: uppercase; color: #a0aec0; letter-spacing: 0.05em;'>Included Sites</h4>
                <ul style='margin: 0; padding-left: 20px; color: #4a5568; font-size: 14px;'>";
            
            $count = 0;
            foreach ( $sites as $s ) {
                if ( $count >= 5 ) {
                    $remaining = count( $sites ) - 5;
                    $site_list_html .= "<li style='margin-bottom: 4px; font-style: italic; color: #718096;'>...and $remaining more.</li>";
                    break;
                }
                // Handle array format from Account::sites()
                $name = is_array($s) ? $s['name'] : $s->name;
                $site_list_html .= "<li style='margin-bottom: 4px;'>{$name}</li>";
                $count++;
            }
            $site_list_html .= "</ul></div>";
        }

        $domain_text = "";
        $domain_count = count($domains);
        if ( $domain_count > 0 ) {
            $domain_text = " and {$domain_count} domain" . ($domain_count !== 1 ? 's' : '');
        }

        $intro_text = "<p style='margin-bottom: 25px; line-height: 1.6;'>You have been granted access to the account <strong>{$account_name}</strong>.</p>";
        $intro_text .= "<p style='margin-bottom: 0; line-height: 1.6;'>This includes access to " . count($sites) . " website(s){$domain_text}.</p>";

        $action_button = "
            <div style='text-align: center; margin: 35px 0;'>
                <table role='presentation' border='0' cellpadding='0' cellspacing='0' style='margin: 0 auto;'>
                    <tr>
                        <td style='border-radius: 4px; background-color: {$brand_color};'>
                            <a href='{$login_url}' target='_blank' style='border: 1px solid {$brand_color}; border-radius: 4px; color: #ffffff; display: inline-block; font-size: 16px; font-weight: 600; padding: 12px 30px; text-decoration: none;'>Log in to Dashboard &rarr;</a>
                        </td>
                    </tr>
                </table>
            </div>
        ";

        self::send_email_with_layout( 
            $to_email, 
            "Access granted to {$account_name}", 
            "Access Granted", 
            $account_name, 
            $intro_text . $site_list_html . $action_button
        );
    }

    /* -------------------------------------------------------------------------
     *  NEW USER INVITE (Account Creation)
     * ------------------------------------------------------------------------- */
    static public function send_invite_new_user( $to_email, $account_name, $invite_url ) {
        $config      = Configurations::get();
        $brand_color = $config->colors->primary ?? '#0D47A1';
        $site_name   = get_bloginfo( 'name' );

        $intro_text = "<p style='margin-bottom: 25px; line-height: 1.6;'>You have been granted access to the account <strong>{$account_name}</strong>.</p>";
        $intro_text .= "<p style='margin-bottom: 0; line-height: 1.6;'>Please click the button below to accept the invitation and set up your login.</p>";

        $action_button = "
            <div style='text-align: center; margin: 35px 0;'>
                <table role='presentation' border='0' cellpadding='0' cellspacing='0' style='margin: 0 auto;'>
                    <tr>
                        <td style='border-radius: 4px; background-color: {$brand_color};'>
                            <a href='{$invite_url}' target='_blank' style='border: 1px solid {$brand_color}; border-radius: 4px; color: #ffffff; display: inline-block; font-size: 16px; font-weight: 600; padding: 12px 30px; text-decoration: none;'>Accept Invitation &rarr;</a>
                        </td>
                    </tr>
                </table>
            </div>
            <p style='text-align: center; font-size: 12px; color: #a0aec0;'>If the button doesn't work, copy and paste this link:<br><a href='{$invite_url}' style='color: {$brand_color};'>{$invite_url}</a></p>
        ";

        self::send_email_with_layout( 
            $to_email, 
            "Hosting account invite: {$account_name}", 
            "You're Invited", 
            "to {$site_name}", 
            $intro_text . $action_button
        );
    }

    /* -------------------------------------------------------------------------
     *  SNAPSHOT READY
     * ------------------------------------------------------------------------- */
    static public function send_snapshot_ready( $to_email, $site_name, $snapshot_id, $download_url ) {
        $config      = Configurations::get();
        $brand_color = $config->colors->primary ?? '#0D47A1';

        $intro_text = "<p style='margin-bottom: 25px; line-height: 1.6;'>The snapshot you requested for <strong>{$site_name}</strong> is ready.</p>";
        $intro_text .= "<div style='background-color: #f7fafc; padding: 15px; border-radius: 6px; border: 1px solid #e2e8f0; margin-bottom: 25px; text-align: center;'><strong>Snapshot #{$snapshot_id}</strong><br><small style='color: #718096;'>Link expires in 7 days.</small></div>";

        $action_button = "
            <div style='text-align: center; margin: 35px 0;'>
                <table role='presentation' border='0' cellpadding='0' cellspacing='0' style='margin: 0 auto;'>
                    <tr>
                        <td style='border-radius: 4px; background-color: {$brand_color};'>
                            <a href='{$download_url}' target='_blank' style='border: 1px solid {$brand_color}; border-radius: 4px; color: #ffffff; display: inline-block; font-size: 16px; font-weight: 600; padding: 12px 30px; text-decoration: none;'>Download Snapshot &rarr;</a>
                        </td>
                    </tr>
                </table>
            </div>
        ";

        self::send_email_with_layout( 
            $to_email, 
            "Snapshot #{$snapshot_id} Ready", 
            "Snapshot Ready", 
            $site_name, 
            $intro_text . $action_button
        );
    }

    /* -------------------------------------------------------------------------
     *  GENERIC PROCESS NOTIFICATION (Copy/Deploy)
     * ------------------------------------------------------------------------- */
    static public function send_process_completed( $to_email, $subject, $headline, $subheadline, $message, $link_url = '' ) {
        $config      = Configurations::get();
        $brand_color = $config->colors->primary ?? '#0D47A1';

        $content = "<p style='margin-bottom: 25px; line-height: 1.6;'>{$message}</p>";

        if ( ! empty( $link_url ) ) {
            $content .= "
                <div style='text-align: center; margin: 35px 0;'>
                    <table role='presentation' border='0' cellpadding='0' cellspacing='0' style='margin: 0 auto;'>
                        <tr>
                            <td style='border-radius: 4px; background-color: {$brand_color};'>
                                <a href='{$link_url}' target='_blank' style='border: 1px solid {$brand_color}; border-radius: 4px; color: #ffffff; display: inline-block; font-size: 16px; font-weight: 600; padding: 12px 30px; text-decoration: none;'>View Site &rarr;</a>
                            </td>
                        </tr>
                    </table>
                </div>
                <p style='text-align: center; font-size: 12px; color: #a0aec0;'><a href='{$link_url}' style='color: {$brand_color};'>{$link_url}</a></p>
            ";
        }

        self::send_email_with_layout( $to_email, $subject, $headline, $subheadline, $content );
    }

    /* -------------------------------------------------------------------------
     *  SITE REMOVAL REQUEST (Admin Notify)
     * ------------------------------------------------------------------------- */
    static public function send_site_removal_request( $site, $user, $is_removal ) {
        $config      = Configurations::get();
        $brand_color = $config->colors->primary ?? '#0D47A1';
        $site_name   = get_bloginfo( 'name' );
        $admin_email = get_option( 'admin_email' );

        // Determine content based on action (Remove vs Cancel)
        if ( $is_removal ) {
            $subject     = "{$site_name} - Site Removal Request";
            $headline    = "Removal Requested";
            $subheadline = $site->name;
            $intro_text  = "A request has been submitted to remove the following site.";
            $status_color = "#e53e3e"; // Red
            $status_text  = "Removal Pending";
        } else {
            $subject     = "{$site_name} - Cancel Site Removal Request";
            $headline    = "Removal Cancelled";
            $subheadline = $site->name;
            $intro_text  = "A request has been submitted to keep this site. Please disregard the previous removal request.";
            $status_color = "#38a169"; // Green
            $status_text  = "Active";
        }

        $content_html = "
            <div style='text-align: left; font-size: 16px; line-height: 1.6; color: #4a5568;'>
                <p style='margin-bottom: 25px;'>{$intro_text}</p>
                
                <div style='background-color: #ffffff; border: 1px solid #e2e8f0; border-radius: 6px; padding: 20px; margin-bottom: 25px;'>
                    <table width='100%' cellpadding='0' cellspacing='0'>
                        <tr>
                            <td style='padding-bottom: 10px; color: #718096; font-size: 14px;'>Site Name</td>
                            <td style='padding-bottom: 10px; color: #2d3748; font-weight: 600; text-align: right;'>{$site->name}</td>
                        </tr>
                        <tr>
                            <td style='padding-bottom: 10px; color: #718096; font-size: 14px;'>Site ID</td>
                            <td style='padding-bottom: 10px; color: #2d3748; font-weight: 600; text-align: right;'>#{$site->site_id}</td>
                        </tr>
                        <tr>
                            <td style='padding-top: 10px; border-top: 1px solid #edf2f7; color: #718096; font-size: 14px;'>Requested By</td>
                            <td style='padding-top: 10px; border-top: 1px solid #edf2f7; color: #2d3748; font-weight: 600; text-align: right;'>
                                {$user->name} <span style='color: #a0aec0; font-weight: 400;'>(#{$user->user_id})</span>
                            </td>
                        </tr>
                    </table>
                </div>

                <div style='text-align: center;'>
                    <div style='display: inline-block; background-color: {$status_color}; color: #ffffff; font-size: 12px; font-weight: 700; padding: 6px 12px; border-radius: 9999px; text-transform: uppercase; letter-spacing: 0.05em;'>
                        {$status_text}
                    </div>
                </div>
            </div>
        ";

        // Reply to the user requesting the action
        $reply_to = ! empty( $user->name ) ? "{$user->name} <{$user->email}>" : $user->email;
        $headers = [ "Reply-To: $reply_to" ];

        self::send_email_with_layout( 
            $admin_email, 
            $subject, 
            $headline, 
            $subheadline, 
            $content_html,
            $headers
        );
    }

    /* -------------------------------------------------------------------------
     *  CANCEL PLAN REQUEST (Admin Notify)
     * ------------------------------------------------------------------------- */
    static public function send_cancel_plan_request( $subscription, $user ) {
        $config      = Configurations::get();
        $brand_color = $config->colors->primary ?? '#0D47A1';
        $site_name   = get_bloginfo( 'name' );
        $admin_email = get_option( 'admin_email' );

        $subject     = "{$site_name} - Cancel Plan Request";
        $headline    = "Cancellation Requested";
        $subheadline = $subscription->name;

        $content_html = "
            <div style='text-align: left; font-size: 16px; line-height: 1.6; color: #4a5568;'>
                <p style='margin-bottom: 25px;'>A request has been submitted to cancel the following plan.</p>

                <div style='background-color: #ffffff; border: 1px solid #e2e8f0; border-radius: 6px; padding: 20px; margin-bottom: 25px;'>
                    <table width='100%' cellpadding='0' cellspacing='0'>
                        <tr>
                            <td style='padding-bottom: 10px; color: #718096; font-size: 14px;'>Plan Name</td>
                            <td style='padding-bottom: 10px; color: #2d3748; font-weight: 600; text-align: right;'>{$subscription->name}</td>
                        </tr>
                        <tr>
                            <td style='padding-bottom: 10px; color: #718096; font-size: 14px;'>Account ID</td>
                            <td style='padding-bottom: 10px; color: #2d3748; font-weight: 600; text-align: right;'>#{$subscription->account_id}</td>
                        </tr>
                        <tr>
                            <td style='padding-top: 10px; border-top: 1px solid #edf2f7; color: #718096; font-size: 14px;'>Requested By</td>
                            <td style='padding-top: 10px; border-top: 1px solid #edf2f7; color: #2d3748; font-weight: 600; text-align: right;'>
                                {$user['name']} <span style='color: #a0aec0; font-weight: 400;'>({$user['email']})</span>
                            </td>
                        </tr>
                    </table>
                </div>

                <div style='text-align: center;'>
                    <div style='display: inline-block; background-color: #e53e3e; color: #ffffff; font-size: 12px; font-weight: 700; padding: 6px 12px; border-radius: 9999px; text-transform: uppercase; letter-spacing: 0.05em;'>
                        Cancellation Pending
                    </div>
                </div>
            </div>
        ";

        // Reply to the user requesting the action
        $headers = [ "Reply-To: {$user['name']} <{$user['email']}>" ];

        self::send_email_with_layout(
            $admin_email,
            $subject,
            $headline,
            $subheadline,
            $content_html,
            $headers
        );
    }

    /* -------------------------------------------------------------------------
     *  NEW SITE REQUEST (Admin Notify)
     * ------------------------------------------------------------------------- */
    static public function send_site_request_notification( $site_name, $site_notes, $account_name, $user ) {
        $config      = Configurations::get();
        $brand_color = $config->colors->primary ?? '#0D47A1';
        $admin_email = get_option( 'admin_email' );

        $subject     = "New Site Request: {$site_name}";
        $headline    = "New Site Requested";
        $subheadline = $site_name;

        // Notes Section
        $notes_html = "";
        if ( ! empty( $site_notes ) ) {
            $notes_html = "
                <div style='margin-top: 25px; padding-top: 20px; border-top: 1px solid #edf2f7;'>
                    <h4 style='margin: 0 0 10px; font-size: 11px; text-transform: uppercase; color: #a0aec0; letter-spacing: 0.05em;'>Notes</h4>
                    <p style='margin: 0; font-style: italic; color: #4a5568;'>\"" . nl2br( esc_html( $site_notes ) ) . "\"</p>
                </div>
            ";
        }

        $content_html = "
            <div style='text-align: left; font-size: 16px; line-height: 1.6; color: #4a5568;'>
                <p style='margin-bottom: 25px;'>A new site request has been submitted.</p>
                
                <div style='background-color: #ffffff; border: 1px solid #e2e8f0; border-radius: 6px; padding: 20px;'>
                    <table width='100%' cellpadding='0' cellspacing='0'>
                        <tr>
                            <td style='padding-bottom: 10px; color: #718096; font-size: 14px;'>Site Name</td>
                            <td style='padding-bottom: 10px; color: #2d3748; font-weight: 600; text-align: right;'>{$site_name}</td>
                        </tr>
                        <tr>
                            <td style='padding-bottom: 10px; color: #718096; font-size: 14px;'>Account</td>
                            <td style='padding-bottom: 10px; color: #2d3748; font-weight: 600; text-align: right;'>{$account_name}</td>
                        </tr>
                        <tr>
                            <td style='padding-top: 10px; border-top: 1px solid #edf2f7; color: #718096; font-size: 14px;'>Requested By</td>
                            <td style='padding-top: 10px; border-top: 1px solid #edf2f7; color: #2d3748; font-weight: 600; text-align: right;'>
                                {$user->name} <span style='color: #a0aec0; font-weight: 400;'>(#{$user->user_id})</span>
                            </td>
                        </tr>
                    </table>
                </div>
                {$notes_html}
            </div>
        ";

        // Reply to the user requesting the action
        if ( ! empty( $user->name ) ) {
            $headers = [ "Reply-To: {$user->name} <{$user->email}>" ];
        } else {
            $headers = [ "Reply-To: <{$user->email}>" ];
        }

        self::send_email_with_layout( 
            $admin_email, 
            $subject, 
            $headline, 
            $subheadline, 
            $content_html,
            $headers
        );
    }

}