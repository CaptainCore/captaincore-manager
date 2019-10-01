<?php 

namespace CaptainCore;

class Domains {

    protected $domains = [];

    public function __construct( $domains = [] ) {

        $user        = wp_get_current_user();
        $role_check  = in_array( 'subscriber', $user->roles ) + in_array( 'customer', $user->roles ) + in_array( 'administrator', $user->roles ) + in_array( 'editor', $user->roles );
        $partner     = get_field( 'partner', 'user_' . get_current_user_id() );
        $all_domains = [];

        // Bail if not assigned a role
        if ( ! $role_check ) {
            return 'Error: Please log in.';
        }

        // Administrators return all sites
        if ( in_array( 'administrator', $user->roles ) ) {
            
            $domains = get_posts(
                array(
                    'post_type'      => 'captcore_domain',
                    'posts_per_page' => '-1',
                )
            );

            foreach ( $domains as $domain ) :
                $domain_name = get_the_title( $domain );
                $domain_id = get_field( "domain_id", $domain );
                if ( $domain_name ) {
                    $all_domains[ $domain_name ] = [ "name" => $domain_name, "id" => $domain_id, "post_id" => $domain->ID ];
                }
            endforeach;

            usort( $all_domains, "sort_by_name" );

            $this->domains = $all_domains;
        }

        if ( in_array( 'subscriber', $user->roles ) or in_array( 'customer', $user->roles ) or in_array( 'editor', $user->roles ) ) {

            $customers = [];

            $user_id = get_current_user_id();
            $partner = get_field( 'partner', 'user_' . get_current_user_id() );
            if ( $partner ) {
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
                                    'key'     => 'partner', // name of custom field
                                    'value'   => '"' . $partner_id . '"', // matches exactly "123", not just 123. This prevents a match for "1234"
                                    'compare' => 'LIKE',
                                ),
                            ),
                        )
                    );
                    foreach ( $websites_for_partner as $website ) :
                        $customers[] = get_field( 'customer', $website );
                    endforeach;
                }
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
                    foreach ( $websites_for_partner as $website ) :
                        $customers[] = get_field( 'customer', $website );
                    endforeach;
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
            $this->domains = $all_domains;
        }
    }

    public function all() {
        return $this->domains;
    }

}