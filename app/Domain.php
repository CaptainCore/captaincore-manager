<?php

namespace CaptainCore;

class Domain {

    protected $domain_id = "";

    public function __construct( $domain_id = "" ) {
        $this->domain_id = $domain_id;
    }

    public function get_legacy() {
        $post        = get_post( $this->domain_id );
        $arguments   = [ 'fields' => 'ids', 'post_type' => 'captcore_customer', 'posts_per_page' => '-1','meta_query'=> [['key' => 'domains', 'value'  => '"'.  $this->domain_id .'"', 'compare' => 'LIKE' ]]];
        $account_ids = get_posts($arguments);
        $domain = (object) [
            'created_at'  => get_the_date( 'Y-m-d H:i:s', $post->ID ),
            'name'        => $post->post_title,
            'remote_id'   => get_post_meta( $post->ID, 'domain_id', true ),
            'permissions' => $account_ids,
        ];
        return $domain;
    }

    public function insert_accounts( $account_ids = [] ) {

        $accountdomain = new AccountDomain();

        foreach( $account_ids as $account_id ) {

            // Fetch current records
            $lookup = $accountdomain->where( [ "domain_id" => $this->domain_id, "account_id" => $account_id ] );

            // Add new record
            if ( count($lookup) == 0 ) {
                $accountdomain->insert( [ "domain_id" => $this->domain_id, "account_id" => $account_id ] );
            }

        }

    }

    public function assign_accounts( $account_ids = [] ) {

        $accountdomain = new AccountDomain();

        // Fetch current records
        $current_account_ids = array_column ( $accountdomain->where( [ "domain_id" => $this->domain_id ] ), "account_id" );

        // Removed current records not found new records.
        foreach ( array_diff( $current_account_ids, $account_ids ) as $account_id ) {
            $records = $accountdomain->where( [ "domain_id" => $this->domain_id, "account_id" => $account_id ] );
            foreach ( $records as $record ) {
                $accountdomain->delete( $record->account_domain_id );
            }
        }

        // Add new records
        foreach ( array_diff( $account_ids, $current_account_ids ) as $account_id ) {
            $accountdomain->insert( [ "domain_id" => $this->domain_id, "account_id" => $account_id ] );
        }

    }

    public function fetch_remote_id() {

        $domain = ( new Domains )->get( $this->domain_id );
        
        // Load domains from transient
		$constellix_all_domains = get_transient( 'constellix_all_domains' );

		// If empty then update transient with large remote call
		if ( empty( $constellix_all_domains ) ) {

			$constellix_all_domains = constellix_api_get( 'domains' );

			// Save the API response so we don't have to call again until tomorrow.
			set_transient( 'constellix_all_domains', $constellix_all_domains, HOUR_IN_SECONDS );

		}

		// Search API for domain ID
		foreach ( $constellix_all_domains as $item ) {
			if ( $domain->name == $item->name ) {
                $remote_id = $item->id;
                break;
			}
        }
        
        // Generate a new domain zone and add the new domain
        if ( empty( $remote_id ) ) {
			$response = constellix_api_post( 'domains', [ 'names' => [ $domain->name ] ] );
			foreach ( $response as $domain ) {
				// Capture new domain IDs from $response
                $remote_id = $domain->id;
                if ( defined( 'CAPTAINCORE_CONSTELLIX_VANITY_ID' ) && defined( 'CAPTAINCORE_CONSTELLIX_SOA_NAME' ) ) {
                    $response = constellix_api_put( "domains/$remote_id", [ 
                        "vanityNameServer" => CAPTAINCORE_CONSTELLIX_VANITY_ID,
                        "soa"              => [ 
                            "primaryNameserver" => CAPTAINCORE_CONSTELLIX_SOA_NAME,
                            "ttl"               => "86400",
                            "refresh"           => "43200",
                            "retry"             => "3600",
                            "expire"            => "1209600",
                            "negCache"          => "180",
                        ]
                    ] );
                }
			}
        }

        if ( ! empty( $response->errors ) ) {
            return [ "errors" => $response->errors ];
        }
        
        ( new Domains )->update( [ "remote_id" => $remote_id ], [ "domain_id" => $this->domain_id ] );

		return $remote_id;
    }

}