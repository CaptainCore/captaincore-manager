<?php

namespace CaptainCore;

class Router {

    public function __construct() {
        add_action( 'init', [ $this, 'add_rewrite_rules' ] );
        add_filter( 'query_vars', [ $this, 'add_query_vars' ] );
        add_filter( 'template_include', [ $this, 'load_template' ] );
        add_action( 'template_redirect', [ $this, 'handle_checkout_express' ] );
    }

    /**
     * register rewrite rules
     */
    public function add_rewrite_rules() {
        $configurations = Configurations::fetch();
        $path           = isset( $configurations->path ) ? trim( $configurations->path, '/' ) : 'account';

        // 1. Checkout Express Rule
        add_rewrite_rule( 
            '^checkout-express/([^/]*)/?', 
            'index.php?pagename=checkout-express&captaincore_callback=$matches[1]', 
            'top' 
        );

        // 2. Main App Rules
        if ( empty( $path ) ) {
            // Scenario: App is running on root (e.g. /sites, /billing)
            // We must be specific to avoid breaking normal WP pages
            $pages = ['accounts', 'billing', 'cookbook', 'configurations', 'connect', 'defaults', 'domains', 'handbook', 'health', 'keys', 'login', 'welcome', 'profile', 'sites', 'subscriptions', 'users', 'vulnerability-scans'];
            $regex = '^(' . implode( '|', $pages ) . ')(/.*)?$';
            
            add_rewrite_rule( 
                $regex, 
                'index.php?captaincore_app=1&captaincore_route=$matches[1]', 
                'top' 
            );
        } else {
            // Scenario: App is running in sub-path (e.g. /account/sites)
            // 1. Exact match for the base path
            add_rewrite_rule( 
                '^' . $path . '/?$', 
                'index.php?captaincore_app=1', 
                'top' 
            );

            // 2. Catch-all for sub-routes
            add_rewrite_rule( 
                '^' . $path . '/(.+?)/?$', 
                'index.php?captaincore_app=1&captaincore_route=$matches[1]', 
                'top' 
            );
        }
    }

    /**
     * Register custom query variables so WP recognizes them
     */
    public function add_query_vars( $vars ) {
        $vars[] = 'captaincore_app';
        $vars[] = 'captaincore_route';
        $vars[] = 'captaincore_callback';
        return $vars;
    }

    /**
     * Load the Vue app template if the query var is present
     */
    public function load_template( $template ) {
        if ( get_query_var( 'captaincore_app' ) ) {
            global $wp_query;
            
            // Force 200 OK status
            status_header( 200 );
            $wp_query->is_404 = false;
            
            // Prevent search engines from indexing the app shell if needed
            if ( ! is_user_logged_in() ) {
                header( 'X-Robots-Tag: noindex, nofollow' );
            }
            
            // Security header
            header( 'X-Frame-Options: SAMEORIGIN' );

            // Return path to your Vue app shell
            $core_template = plugin_dir_path( dirname( __FILE__ ) ) . 'templates/core.php';
            
            if ( file_exists( $core_template ) ) {
                return $core_template;
            }
        }

        return $template;
    }

    /**
     * Handle Checkout Express Logic (Ported from page template logic)
     */
    public function handle_checkout_express() {
        if ( get_query_var( 'pagename' ) === 'checkout-express' ) {
            $order_id = get_query_var( 'captaincore_callback' );
            $key      = isset( $_GET['key'] ) ? $_GET['key'] : '';

            if ( $order_id && $key ) {
                // Ensure WooCommerce is active
                if ( class_exists( 'WC_Order' ) ) {
                    $order = wc_get_order( $order_id );

                    if ( $order ) {
                        $customer_id = $order->get_customer_id();
                        $order_key   = $order->get_order_key();

                        // Validate Key
                        if ( $order_key === $key && $customer_id ) {
                            $user = get_user_by( 'id', $customer_id );
                            if ( $user ) {
                                // Login User
                                wp_set_current_user( $user->ID, $user->user_login );
                                wp_set_auth_cookie( $user->ID );
                                
                                // Fetch configurations to get the correct billing path
                                $config = Configurations::fetch();
                                $path   = isset( $config->path ) ? '/' . trim( $config->path, '/' ) . '/' : '/account/';

                                // Redirect to billing
                                wp_redirect( home_url( $path . "billing/" . $order_id ) );
                                exit;
                            }
                        }
                    }
                }
            }
            
            // Fallback redirect
            wp_redirect( home_url() );
            exit;
        }
    }
}