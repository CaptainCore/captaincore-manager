<?php 

namespace CaptainCore;

class Sites {

    protected $sites = [];

    public function __construct( $sites = [] ) {
        $user       = wp_get_current_user();
        $role_check = in_array( 'subscriber', $user->roles ) + in_array( 'customer', $user->roles ) + in_array( 'administrator', $user->roles ) + in_array( 'editor', $user->roles );
        $partner    = get_field( 'partner', 'user_' . get_current_user_id() );

        // New array to collect IDs
        $site_ids = [];

        // Bail if not assigned a role
        if ( ! $role_check ) {
            return 'Error: Please log in.';
        }

        // Administrators return all sites
        if ( $partner && $role_check && in_array( 'administrator', $user->roles ) ) {
            $sites = get_posts(
                array(
                    'order'          => 'asc',
                    'orderby'        => 'title',
                    'posts_per_page' => '-1',
                    'post_type'      => 'captcore_website',
                    'meta_query'     => array(
                        'relation' => 'AND',
                        array(
                            'key'     => 'status',
                            'value'   => 'closed',
                            'compare' => '!=',
                        ),
                    ),
                )
            );

            $this->sites = $sites;
            return;
        }

        // Bail if no partner set.
        if ( ! is_array( $partner ) ) {
            return;
        }

        // Loop through each partner assigned to current user
        foreach ( $partner as $partner_id ) {

            // Load websites assigned to partner
            $arguments = array(
                'fields'         => 'ids',
                'post_type'      => 'captcore_website',
                'posts_per_page' => '-1',
                'meta_query'     => array(
                    'relation' => 'AND',
                    array(
                        'key'     => 'partner',
                        'value'   => '"' . $partner_id . '"',
                        'compare' => 'LIKE',
                    ),
                    array(
                        'key'     => 'status',
                        'value'   => 'closed',
                        'compare' => '!=',
                    ),
                ),
            );

            $sites = new \WP_Query( $arguments );

            foreach ( $sites->posts as $site_id ) {
                if ( ! in_array( $site_id, $site_ids ) ) {
                    $site_ids[] = $site_id;
                }
            }

            // Load websites assigned to partner
            $arguments = array(
                'fields'         => 'ids',
                'post_type'      => 'captcore_website',
                'posts_per_page' => '-1',
                'meta_query'     => array(
                    'relation' => 'AND',
                    array(
                        'key'     => 'customer',
                        'value'   => '"' . $partner_id . '"',
                        'compare' => 'LIKE',
                    ),
                    array(
                        'key'     => 'status',
                        'value'   => 'closed',
                        'compare' => '!=',
                    ),
                ),
            );

            $sites = new \WP_Query( $arguments );

            foreach ( $sites->posts as $site_id ) {
                if ( ! in_array( $site_id, $site_ids ) ) {
                    $site_ids[] = $site_id;
                }
            }
        }

        // Bail if no site ids found
        if ( count( $site_ids ) == 0 ) {
            return;
        }

        $sites       = get_posts(
            array(
                'order'          => 'asc',
                'orderby'        => 'title',
                'posts_per_page' => '-1',
                'post_type'      => 'captcore_website',
                'include'        => $site_ids,
                'meta_query'     => array(
                    'relation' => 'AND',
                    array(
                        'key'     => 'status',
                        'value'   => 'closed',
                        'compare' => '!=',
                    ),
                ),
            )
        );
        $this->sites = $sites;
        return;

    }

    public function all() {
        $sites = [];
        foreach( $this->sites as $site ) {
            $sites[] = ( new Site( $site ) )->get();
        }
        return $sites;
    }

}