<?php

namespace CaptainCore;

class Account {

    protected $account_id = "";

    public function __construct( $account_id = "", $admin = false ) {

        if ( captaincore_verify_permissions_account( $account_id ) ) {
            $this->account_id = $account_id;
        }

        if ( $admin ) {
            $this->account_id = $account_id;
        }

    }

    public function invite( $email ) {
        if ( email_exists( $email ) ) {
            $user = get_user_by( 'email', $email );
            // Add account ID to current user
            $accounts = get_field( 'partner', "user_{$user->ID}" );
            $accounts[] = $this->account_id;
            update_field( 'partner', array_unique( $accounts ), "user_{$user->ID}" );
            $this->calculate_totals();

            return [ "message" => "Account already exists. Adding permissions for existing user." ];
        }

        $time_now = date("Y-m-d H:i:s");
        $token    = bin2hex( openssl_random_pseudo_bytes( 24 ) );
        $new_invite = array(
            'email'          => $email,
            'account_id'     => $this->account_id,
            'created_at'     => $time_now,
            'updated_at'     => $time_now,
            'token'          => $token
        );
        $invite = new Invites();
        $invite_id = $invite->insert( $new_invite );

        // Send out invite email
        $invite_url = home_url() . "/account/?account={$this->account_id}&token={$token}";
        $account_name = get_the_title( $this->account_id );
        $subject = "Hosting account invite";
        $body    = "You've been granted access to account '$account_name'. Click here to accept:<br /><br /><a href=\"{$invite_url}\">$invite_url</a>";
        $headers = [ 'Content-Type: text/html; charset=UTF-8' ];

        wp_mail( $email, $subject, $body, $headers );

        return [ "message" => "Invite has been sent." ];
    }

    public function account() {
        
        return [
            "id"            => $this->account_id,
            "name"          => html_entity_decode( get_the_title( $this->account_id ) ),
            'website_count' => get_field( "website_count", $this->account_id ),
            'user_count'    => get_field( "user_count", $this->account_id ),
            'domain_count'  => count( get_field( "domains", $this->account_id ) ),
        ];
    }

    public function invites() {
        $invites = new Invites();
        return $invites->where( [ "account_id" => $this->account_id, "accepted_at" => "0000-00-00 00:00:00" ] );
    }

    public function domains() {

        $all_domains = [];
        $customers   = [];
        $partner     = [ $this->account_id ];

        $websites_for_partner = get_posts(
            array(
                'post_type'      => 'captcore_website',
                'posts_per_page' => '-1',
                'order'          => 'asc',
                'orderby'        => 'title',
                'fields'         => 'ids',
                'meta_query'     => array(
                    'relation' => 'AND',
                    array(
                        'key'     => 'partner', // name of custom field
                        'value'   => '"' . $this->account_id . '"', // matches exactly "123", not just 123. This prevents a match for "1234"
                        'compare' => 'LIKE',
                    ),
                ),
            )
        );

        foreach ( $websites_for_partner as $website ) {
            $customers[] = get_field( 'customer', $website );
        }

        if ( count( $customers ) == 0 and is_array( $partner ) ) {
            foreach ( $partner as $partner_id ) {
                $websites_for_partner = get_posts(
                    array(
                        'post_type'      => 'captcore_website',
                        'posts_per_page' => '-1',
                        'order'          => 'asc',
                        'orderby'        => 'title',
                        'fields'         => 'ids',
                        'meta_query'     => array(
                            'relation' => 'AND',
                            array(
                                'key'     => 'customer', // name of custom field
                                'value'   => '"' . $partner_id . '"', // matches exactly "123", not just 123. This prevents a match for "1234"
                                'compare' => 'LIKE',
                            ),
                        ),
                    )
                );
                foreach ( $websites_for_partner as $website ) {
                    $customers[] = get_field( 'customer', $website );
                }
            }
        }

        foreach ( $customers as $customer ) :

            if ( is_array( $customer ) ) {
                $customer = $customer[0];
            }

            $domains = get_field( 'domains', $customer );
            if ( $domains ) {
                foreach ( $domains as $domain ) :
                    $domain_name = get_the_title( $domain );
                    $domain_id = get_field( "domain_id", $domain );
                    if ( $domain_name ) {
                        $all_domains[ $domain_name ] = [ "name" => $domain_name, "id" => $domain_id ];
                    }
                endforeach;
            }

        endforeach;

        foreach ( $partner as $customer ) :
            $domains = get_field( 'domains', $customer );
            if ( $domains ) {
                foreach ( $domains as $domain ) :
                    $domain_name = get_the_title( $domain );
                    $domain_id = get_field( "domain_id", $domain );
                    if ( $domain_name ) {
                        $all_domains[ $domain_name ] = [ "name" => $domain_name, "id" => $domain_id ];
                    }
                endforeach;
            }
        endforeach;

        usort( $all_domains, "sort_by_name" );
        return $all_domains;

    }

    public function sites() {

        $results = [];
        $websites = get_posts(
            array(
                'post_type'      => 'captcore_website',
                'posts_per_page' => '-1',
                'meta_query'     => array(
                    array(
                        'key'     => 'customer', // name of custom field
                        'value'   => '"' . $this->account_id . '"', // matches exactly "123", not just 123. This prevents a match for "1234"
                        'compare' => 'LIKE',
                    ),
                ),
            )
        );
        if ( $websites ) {
            foreach ( $websites as $website ) {
                if ( get_field( 'status', $website->ID ) == 'active' ) {
                    $results[] = [
                        "name"    => get_the_title( $website->ID ), 
                        "site_id" => $website->ID,
                    ];
                }
            }
        }
        $websites = get_posts(
            array(
                'post_type'      => 'captcore_website',
                'posts_per_page' => '-1',
                'meta_query'     => array(
                    array(
                        'key'     => 'partner', // name of custom field
                        'value'   => '"' . $this->account_id . '"', // matches exactly "123", not just 123. This prevents a match for "1234"
                        'compare' => 'LIKE',
                    ),
                ),
            )
        );
        if ( $websites ) {
            foreach ( $websites as $website ) {
                if ( get_field( 'status', $website->ID ) == 'active' ) {
                    if ( in_array( $website->ID, array_column( $results, "site_id" ) ) ) {
                        continue;
                    }
                    $results[] = [ 
                        "name"    => get_the_title( $website->ID ), 
                        "site_id" => $website->ID,
                    ];
                }
            }
        }

        usort( $results, "sort_by_name" );

        return $results;

    }

    public function users() {

        $args = array (
            'order' => 'ASC',
            'orderby' => 'display_name',
            'meta_query' => array(
                array(
                    'key'     => 'partner',
                    'value'   => '"' . $this->account_id . '"',
                    'compare' => 'LIKE'
                ),
            )
        );

        // Create the WP_User_Query object
        $wp_user_query = new \WP_User_Query($args);
        $users = $wp_user_query->get_results();
        $results = [];

        foreach( $users as $user ) {
            $results[] = [
                "user_id" => $user->ID,
                "name"    => $user->display_name, 
                "email"   => $user->user_email,
                "level"   => ""
            ];
        }

        return $results;

    }

    public function calculate_totals() {

        // Calculate active website count
        $websites_by_customer = get_posts(
            array(
                'post_type'      => 'captcore_website',
                'fields'         => 'ids',
                'posts_per_page' => '-1',
                'meta_query'     => array(
                    'relation' => 'AND',
                    array(
                        'key'     => 'customer', // name of custom field
                        'value'   => '"' . $this->account_id . '"', // matches exactly "123", not just 123. This prevents a match for "1234"
                        'compare' => 'LIKE',
                    ),
                    array(
                        'key'     => 'status',
                        'value'   => 'active',
                        'compare' => 'LIKE',
                    ),
                ),
            )
        );
        $websites_by_partners = get_posts(
            array(
                'post_type'      => 'captcore_website',
                'fields'         => 'ids',
                'posts_per_page' => '-1',
                'meta_query'     => array(
                    'relation' => 'AND',
                    array(
                        'key'     => 'partner', // name of custom field
                        'value'   => '"' . $this->account_id . '"', // matches exactly "123", not just 123. This prevents a match for "1234"
                        'compare' => 'LIKE',
                    ),
                    array(
                        'key'     => 'status',
                        'value'   => 'active',
                        'compare' => 'LIKE',
                    ),
                ),
            )
        );
        $websites = array_unique(array_merge($websites_by_customer, $websites_by_partners));
        $args = array (
            'order' => 'ASC',
            'orderby' => 'display_name',
            'meta_query' => array(
                array(
                    'key'     => 'partner',
                    'value'   => '"' . $this->account_id . '"',
                    'compare' => 'LIKE'
                ),
            )
        );
        
        // Create the WP_User_Query object
        $wp_user_query = new \WP_User_Query($args);
        $users = $wp_user_query->get_results();
        update_field( 'website_count', count( $websites ), $this->account_id );
        update_field( 'user_count', count( $users ), $this->account_id );
    }

    public function fetch() {
        $record = array (
            "users"   => $this->users(),
            "invites" => $this->invites(),
            "domains" => $this->domains(),
            "sites"   => $this->sites(),
            "account" => $this->account(),
        );
        return $record;
    }

}