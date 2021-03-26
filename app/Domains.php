<?php 

namespace CaptainCore;

class Domains extends DB {

    static $primary_key = 'domain_id';

    protected $domains = [];

    public function __construct( $domains = [] ) {
        $user        = new User;
        $account_ids = $user->accounts();
        
        // New array to collect IDs
        $domain_ids = [];

        // Bail if not assigned a role
        if ( ! $user->role_check() ) {
            return 'Error: Please log in.';
        }

        // Administrators return all domains
        if ( $user->is_admin() ) {
            $this->domains = self::select_domains();
            return;
        }

        // Bail if no accounts set.
        if ( ! is_array( $account_ids ) ) {
            return;
        }

        // Loop through each account for current user and fetch SiteIDs
        foreach ( $account_ids as $account_id ) {
             // Fetch sites assigned as shared access
             $domain_ids = ( new Account( $account_id, true ) )->domains();
             foreach ( $domain_ids as $domain ) {
                $this->domains[] = $domain->domain_id;
            }
        }

        $this->domains = array_unique( $this->domains );
        
    }

    public function list() {
        $domains        = [];
        foreach( $this->domains as $domain_id ) {
            $domain = self::get( $domain_id );
            $domains[] = [
                "domain_id"   => (int) $domain_id,
                "remote_id"   => $domain->remote_id,
                "provider_id" => ! empty( $domain->provider_id ) ? $domain->provider_id : "",
                "name"        => $domain->name,
                "status"      => $domain->status,
                "price"       => $domain->price,
            ];
        }
        return $domains;
    }

    public function verify( $domain_id = "" ) {
        // Check multiple site ids
        if ( is_array( $domain_id ) ) {
            $valid = true;
            foreach ( $domain_id as $id ) {
                if ( in_array( $id, $this->domains ) ) {
                    continue;
                }
                $valid = false;
            }
            return $valid;
        }
        // Check individual site id
        if ( in_array( $domain_id, $this->domains ) ) {
            return true;
        }
        return false;
    }

    public function delete_domain( $domain_id ) {

        if ( ! in_array( $domain_id, $this->domains ) ) {
            return [ "errors" => "Permission denied." ];
        }
        $domain = self::get( $domain_id );
		if ( $domain->remote_id  ) {
			constellix_api_delete( "domains/{$domain->remote_id}" );
		}
        if ( $domain->provider_id  ) {
			( new Domain( $domain_id ) )->renew_off();
		}
        self::delete( $domain_id );
        return [ "domain_id" => $domain_id, "message" => "Deleted domain {$domain->name}" ];
    }

    public function provider_login() {

        $data = [ 
            'timeout' => 45,
            'headers' => [
                'Content-Type' => 'application/json; charset=utf-8'
            ],
            'body'        => json_encode( [ 
                "username" => HOVERCOM_USERNAME, 
                'password' => HOVERCOM_PASSWORD 
            ] ), 
            'method'      => 'POST', 
            'data_format' => 'body'
        ];
        
        $response    = wp_remote_post( "https://www.hover.com/api/login", $data );
        
        // Save Hover.com cookies as transient login 
        $cookie_data = json_encode( $response["cookies"] );
        set_transient( 'captaincore_hovercom_auth', $cookie_data, HOUR_IN_SECONDS * 48 );
        
    }

    public function provider_sync() {

        if ( empty( get_transient( 'captaincore_hovercom_auth' ) ) ) {
            self::provider_login();
        }
        $cookie_data = json_decode( get_transient( 'captaincore_hovercom_auth' ) );
        $cookies     = [];
        foreach ( $cookie_data as $key => $cookie ) {
            $cookies[] = new \WP_Http_Cookie( [
                'name'    => $cookie->name,
                'value'   => $cookie->value,
                'expires' => $cookie->expires,
                'path'    => $cookie->path,
                'domain'  => $cookie->domain,
            ] );
        }

        $args = [
            'timeout' => 45,
            'cookies' => $cookies,
        ];

        $response = wp_remote_get( "https://www.hover.com/api/control_panel/domains", $args );
        if ( is_wp_error( $response ) ) {
            return json_decode( $response->get_error_message() );
        }

        $domains = json_decode( $response['body'] )->domains;
        foreach ( $domains as $domain ) {
            $lookup = self::where( ["name" => $domain->name ] );
            if ( count( $lookup ) == 1 ) {
                self::update( [
                    "status"      => $domain->status,
                    "provider_id" => $domain->id,
                ], [ "domain_id"  => $lookup[0]->domain_id ] );
            }
        }
        
    }

}