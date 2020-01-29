<?php 

namespace CaptainCore;

class AccountsCPT {

    protected $accounts = [];

    public function __construct( $accounts = [] ) {

        $user        = wp_get_current_user();
        $role_check  = in_array( 'subscriber', $user->roles ) + in_array( 'customer', $user->roles ) + in_array( 'administrator', $user->roles ) + in_array( 'editor', $user->roles );

        // Bail if not assigned a role
        if ( ! $role_check ) {
            return [];
        }

        $account_ids = get_field( 'partner', 'user_' . get_current_user_id() );
        if ( in_array( 'administrator', $user->roles ) ) {
            $account_ids = get_posts([
                'post_type'   => 'captcore_customer',
                'fields'      => 'ids',
                'numberposts' => '-1' 
            ]);
        }

        $accounts = [];

        if ( $account_ids ) {
            foreach ( $account_ids as $account_id ) {
                if ( get_field( 'partner', $account_id ) ) {
                    $developer = true;
                } else {
                    $developer = false;
                }
                $domains = (array) get_field( "domains", $account_id );
                $accounts[] = (object) [
                    'id'            => $account_id,
                    'name'          => html_entity_decode( get_the_title( $account_id ) ),
                    'website_count' => get_field( "website_count", $account_id ),
                    'user_count'    => get_field( "user_count", $account_id ),
                    'domain_count'  => count( $domains ),
                    'developer'		=> $developer
                ];
            }
        }
        usort($accounts, function($a, $b) {
            return strcmp( ucfirst($a->name), ucfirst($b->name));
        });

        $this->accounts = $accounts;

    }

    public function all() {
        return $this->accounts;
    }
}