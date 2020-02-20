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

        $account_ids = [];
        // Loop through sites and pull out account ids
        $site_ids = ( new Sites )->site_ids_all();
        foreach( $site_ids as $site_id ) {
            $site = ( new Sites )->get( $site_id );
            $account_ids[] = $site->account_id;
        }

        // Loop through each account for current user and fetch SiteIDs
        foreach ( $account_ids as $account_id ) {
            
             // Fetch sites assigned as shared access
             $domain_ids = ( new AccountDomain )->select_domains_for_account( $account_ids );
             foreach ( $domain_ids as $domain_id ) {
                 $this->domains[] = $domain_id;
             }
        }

        $this->domains = array_unique( $this->domains );
        
    }

    public function list() {
        $domains        = [];
        foreach( $this->domains as $domain_id ) {
            $domain = self::get( $domain_id );
            $domains[] = [
                "domain_id" => $domain_id,
                "remote_id" => $domain->remote_id,
                "name"      => $domain->name,
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

}