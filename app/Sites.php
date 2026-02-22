<?php 

namespace CaptainCore;

class Sites extends DB {

	static $primary_key = 'site_id';

    protected $sites     = [];
    protected $sites_all = [];

    public function __construct( $sites = [] ) {
        $user        = new User;
        $account_ids = $user->accounts();

        // New array to collect IDs
        $site_ids = [];

        // Bail if not assigned a role
        if ( ! $user->role_check() ) {
            return 'Error: Please log in.';
        }

        // Administrators return all sites
        if ( $user->is_admin() ) {
            $this->sites     = self::select();
            $this->sites_all = self::select_all();
            return;
        }

        // Bail if no accounts set.
        if ( ! is_array( $account_ids ) ) {
            return;
        }

        // Loop through each account for current user and fetch SiteIDs
        foreach ( $account_ids as $account_id ) {
            // Skip accounts where user only has domains-only access
            $level = $user->account_level( $account_id );
            $perms = User::tier_permissions( $level );
            if ( ! $perms['sites'] ) {
                continue;
            }
            // Fetch sites assigned as owners
			$site_ids = array_column( self::where( [ "account_id" => $account_id, "status" => "active" ] ), "site_id" );
			foreach ( $site_ids as $site_id ) {
                $this->sites[]     = $site_id;
                $this->sites_all[] = $site_id;
            }
            // Fetch sites assigned as customer
			$site_ids = array_column( self::where( [ "customer_id" => $account_id, "status" => "active" ] ), "site_id" );
			foreach ( $site_ids as $site_id ) {
                $this->sites[]     = $site_id;
                $this->sites_all[] = $site_id;
            }
            // Patch in inactive sites
            $site_ids = array_column ( self::where( [ "account_id" => $account_id ] ), "site_id" );
            foreach ( $site_ids as $site_id ) {
                $this->sites_all[] = $site_id;
            }
            // Fetch sites assigned as shared access
            $site_ids = ( new AccountSite )->select_active_sites( 'site_id', [ "account_id" => $account_id ] );
			foreach ( $site_ids as $site_id ) {
                $this->sites[]     = $site_id;
                $this->sites_all[] = $site_id;
            }
        }

        // Remove duplicate siteIDs
        $this->sites     = array_unique($this->sites);
        $this->sites_all = array_unique($this->sites_all);
		
        return;

    }
    
    public function site_ids() {
        return $this->sites;
    }

    public function site_ids_all() {
        return $this->sites_all;
    }

    public function verify( $site_id = "" ) {
        // Check multiple site ids
        if ( is_array( $site_id ) ) {
            $valid = true;
            foreach ($site_id as $id) {
                if ( in_array( $id, $this->sites_all ) ) {
                    continue;
                }
                $valid = false;
            }
            return $valid;
        }
        // Check individual site id
        if ( in_array( $site_id, $this->sites_all ) ) {
            return true;
        }
        return false;
    }

    public static function update_environments_cache( $site_id = "" ) {
        $sites_to_process = [];

        // If specific ID provided, process just one.
        if ( ! empty( $site_id ) ) {
            $sites_to_process[] = self::get( $site_id );
        } else {
            // Otherwise process all.
            $site_ids = self::select_all();
            foreach ( $site_ids as $id ) {
                $sites_to_process[] = self::get( $id );
            }
        }

        foreach ( $sites_to_process as $site ) {
            if ( ! $site ) continue;

            // Fetch all environments for this site to build the cache
            $all_envs = ( new Environments )->where( [ "site_id" => $site->site_id ] );
            $env_cache = [];
            
            foreach ( $all_envs as $env ) {
                $env_details = empty( $env->details ) ? (object) [] : json_decode( $env->details );
                $env_cache[] = [
                    "environment_id"  => $env->environment_id,
                    "environment"     => $env->environment,
                    "home_url"        => $env->home_url,
                    "core"            => $env->core,
                    "subsites"        => $env->subsite_count,
                    "storage"         => $env->storage,
                    "visits"          => $env->visits,
                    "username"        => $env->username,
                    "address"         => $env->address,
                    "port"            => $env->port,
                    "screenshot"      => (bool) $env->screenshot,
                    "screenshot_base" => empty( $env_details->screenshot_base ) ? "" : $env_details->screenshot_base,
                ];
            }

            // Update site details
            $details = json_decode( $site->details );
            $details->environments = $env_cache;

            self::update( [ "details" => json_encode( $details ) ], [ "site_id" => $site->site_id ] );

            if ( defined( 'WP_CLI' ) && WP_CLI ) {
                \WP_CLI::log( "Updated environment cache for {$site->name} (#{$site->site_id})" );
            }
        }
    }
	
	public function list() {
        $sites        = [];
        foreach( $this->sites as $site_id ) {
            $site                    = self::get( $site_id );
            $details                 = json_decode( $site->details );
            $site->filtered          = true;
            $site->loading           = false;
            $site->key               = $details->key;
            $site->core              = $details->core;
            $site->username          = isset( $details->username ) ? $details->username : "";
            $site->home_url          = isset( $details->home_url ) ? $details->home_url : "";
            $site->console_errors    = isset( $details->console_errors ) ? $details->console_errors : "";
            $site->connection_errors = isset( $details->connection_errors ) ? $details->connection_errors : "";
            $site->subsites          = $details->subsites;
            $site->storage           = $details->storage;
            $site->visits            = $details->visits;
            $site->outdated          = false;
            $site->environments      = $details->environments ?? [];

            // Fallback for sites that haven't cached their environments yet
            if ( empty( $site->environments ) ) {
                $site->environments = [
                    (object) [
                        "environment_id"  => "",
                        "environment"     => "Production",
                        "home_url"        => $site->home_url,
                        "core"            => $site->core,
                        "subsites"        => $site->subsites,
                        "storage"         => $site->storage,
                        "visits"          => $site->visits,
                        "screenshot_base" => $site->screenshot_base ?? "",
                    ]
                ];
            }
            
            // Mark site as outdated if sync older then 48 hours
            if ( strtotime( $site->updated_at ) <= strtotime( "-48 hours" ) ) {
                $site->outdated = true;
            }

            unset( $site->token );
            unset( $site->created_at );
            unset( $site->details );
            unset( $site->status );
            unset( $site->site_usage );
            $sites[] = $site;
        }
        usort($sites, function($a, $b) { return strcmp($a->name, $b->name); });
        return $sites;
    }

    public function fetch_sites_matching_filters( $filters = [] ) {
        $allowed_site_ids = $this->sites;
        if ( empty( $allowed_site_ids ) ) {
            return [];
        }
        // Call the updated DB method with permissions and filters
        return self::fetch_sites_filtered( (array) $filters, $allowed_site_ids );
    }

    public function fathom_sites( $force = false ) {
        $fathom_sites = get_transient( 'fathom_sites' );

		if ( ! empty( $fathom_sites ) && ! $force ) {
			return $fathom_sites;
		}

        $last_key = "";
        $sites    = [];
        $results  = 100;
        do {
            if ( empty( $last_key ) ) {
                $response = Remote\Fathom::get( "sites", [ "limit" => 100 ] );
            } else {
                $response = Remote\Fathom::get( "sites", [ "limit" => 100, "starting_after" => $last_key ] );
            }
            $results  = count( $response->data );
            foreach( $response->data as $site ){
                $sites[] = $site;
            }
            $last_key = end ( $response->data )->id;
        } while ( $results == 100 );

        set_transient( 'fathom_sites', $sites, HOUR_IN_SECONDS * 24 );

        return $sites;
    }

    public function list_details() {
		$details = [];
        foreach( $this->sites as $site_id ) {
            $site      = self::get( $site_id );
            $details[] = json_decode( $site->details );
        }
        return $details;
    }

}