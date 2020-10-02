<?php 

namespace CaptainCore;

class Site {

    protected $site_id = "";

    public function __construct( $site_id = "" ) {
        $this->site_id = $site_id;
    }

    public function get() {

        if ( is_object( $this->site_id ) ) {
            $site = $this->site_id;
        }

        if ( ! isset( $site ) ) {
            $site = ( new Sites )->get( $this->site_id );
        }

        $upload_dir   = wp_upload_dir();

        // Fetch relating environments
        $environments = self::environments();
        $details      = json_decode ( $site->details );
        $domain       = $site->name;
        $customer     = $site->account_id;
        $mailgun      = $details->mailgun;
        $production_details  = $environments[0]->details;
        $visits              = $environments[0]->visits;
        $subsite_count       = $environments[0]->subsite_count;
        $production_address  = $environments[0]->address;
        $production_username = $environments[0]->username;
        $production_port     = $environments[0]->port;
        $database_username   = $environments[0]->database_username;
        $staging_address     = ( isset( $environments[1] ) ? $environments[1]->address : '' );
        $staging_username    = ( isset( $environments[1] ) ? $environments[1]->username : '' );
        $staging_port        = ( isset( $environments[1] ) ? $environments[1]->port : '' );
        $home_url            = $environments[0]->home_url;

        // Prepare site details to be returned
        $site_details                       = (object) [];
        $site_details->site_id              = $site->site_id;
        $site_details->account_id           = $site->account_id;
        $site_details->account              = self::account();
        $site_details->created_at           = $site->created_at;
        $site_details->updated_at           = $site->updated_at;
        $site_details->name                 = $site->name;
        $site_details->key                  = $details->key;
        $site_details->environment_vars     = $details->environment_vars;
        $site_details->site                 = $site->site;
        $site_details->provider             = $site->provider;
        $site_details->usage_breakdown      = [];
        $site_details->timeline             = [];
        $site_details->loading_plugins      = false;
        $site_details->loading_themes       = false;
        $site_details->environment_selected = 'Production';
        $site_details->mailgun              = $mailgun;
        $site_details->subsite_count        = $subsite_count;
        $site_details->tabs                 = 'tab-Site-Management';
        $site_details->tabs_management      = 'tab-Info';
        $site_details->core                 = $environments[0]->core;
        $site_details->storage              = $details->storage;
        $site_details->outdated				= false;
        if ( is_string( $visits ) ) {
            $site_details->visits = intval( $visits );
        }
        $site_details->update_logs            = [];
        $site_details->update_logs_pagination = [
            'descending' => true,
            'sortBy'     => 'date',
        ];
        $site_details->pagination             = [ 'sortBy' => 'roles' ];

        // Mark site as outdated if sync older then 48 hours
        if ( strtotime( $environments[0]->updated_at ) <= strtotime( "-48 hours" ) ) {
            $site_details->outdated           = true;
        }

        $site_details->errors                 = [];
        if ( ! empty( $production_details->console_errors ) ) {
            $site_details->errors = $production_details->console_errors;
        }

        if ( ! isset( $site_details->visits ) ) {
            $site_details->visits = '';
        }

        if ( $site_details->visits == 0 ) {
            $site_details->visits = '';
        }

        $site_details->users        = [];
        $site_details->update_logs  = [];
        $site_details->environments = $environments;
        $site_details->screenshot   = false;
        $site_details->screenshots  = [];
        if ( $site->screenshot == true ) {
            $screenshot_base           = $details->screenshot_base; 
            $screenshot_url_base       = "{$upload_uri}/{$site->site}_{$site->site_id}/production/screenshots/{$screenshot_base}";
            $site_details->screenshot  = true;
            $site_details->screenshots  = [
                'small' => "${screenshot_url_base}_thumb-100.jpg",
                'large' => "${screenshot_url_base}_thumb-800.jpg"
            ];
        }

        return $site_details;

    }

    public function get_raw() {
        // Fetch site from database
        $site = ( new Sites )->get( $this->site_id );
        
        // Fetch relating environments from database
        $site->environments = ( new Environments )->where( [ "site_id" => $this->site_id ] );
        $site->shared_with  = ( new AccountSite )->where( [ "site_id" => $this->site_id ] );

        return $site;
    }

    public function create( $site ) {

        // Work with array as PHP object
        $site = (object) $site;

        // Prep for response to return
        $response = [ "errors" => [] ];

        // Pull in current user
        $current_user = wp_get_current_user();

        // Validate
        if ( $site->domain == '' ) {
            $response['errors'][] = "Error: Domain can't be empty.";
        }
        if ( $site->site == '' ) {
            $response['errors'][] = "Error: Site can't be empty.";
        }
        if ( ! ctype_alnum ( $site->site ) ) {
            $response['errors'][] = "Error: Site does not consist of all letters or digits.";
        }
        if ( strlen($site->site) < 3 ) {
            $response['errors'][] = "Error: Site length less then 3 characters.";
        }
        if ( $site->environments[0]['address'] == "" ) {
            $response['errors'][] = "Error: Production environment address can't be empty.";
        }
        if ( $site->environments[0]['username'] == "" ) {
            $response['errors'][] = "Error: Production environment username can't be empty.";
        }
        if ( $site->environments[0]['protocol'] == "" ) {
            $response['errors'][] = "Error: Production environment protocol can't be empty.";
        }
        if ( $site->environments[0]['port'] == "" ) {
            $response['errors'][] = "Error: Production environment port can't be empty.";
        }
        if ( $site->environments[0]['port'] != "" and ! ctype_digit( $site->environments[0]['port'] ) ) {
            $response['errors'][] = "Error: Production environment port can only be numbers.";
        }

        if ( $site->environments[1]['port'] and ! ctype_digit( $site->environments[1]['port'] ) ) {
            $response['errors'][] = "Error: Staging environment port can only be numbers.";
        }
        
        // Hunt for conflicting site names
        $site_check = ( new Sites )->where( [ "site" => $site->site ] );

        if ( count( $site_check ) > 0 ) {
            $response['errors'][] = "Error: Site name needs to be unique.";
        }

        if ( count($response['errors']) > 0 ) {
            return $response;
        }

        $time_now = date("Y-m-d H:i:s");
        $details  = (object) [
            "key"              => $site->key,
            "environment_vars" => $site->environment_vars,
            "subsites"         => "",
            "storage"          => "",
            "visits"           => "",
            "mailgun"          => "",
            "core"             => "",
        ];
        $new_site = [
            'account_id' => $site->account_id,
            'name'       => $site->domain,
            'site'       => $site->site,
            'provider'   => $site->provider,
            'created_at' => $time_now,
            'updated_at' => $time_now,
            'details'    => json_encode( $details ),
            'screenshot' => '0',
            'status'     => 'active',
        ];

        $site_id = ( new Sites )->insert( $new_site );

        if ( ! is_int( $site_id ) || $site_id == 0 ) {
            $response['response'] = json_encode( $new_site );
            $response['errors'][] = 'Failed to add new site';
            return $response;
        }

        $response['response'] = 'Successfully added new site';
        $response['site_id']  = $site_id;
        $this->site_id        = $site_id;

        self::assign_accounts( $site->shared_with );

        // Update environments
        $db_environments = new Environments();
        foreach ( $site->environments as $environment ) {
            $new_environment = [
                'site_id'                 => $site_id,
                'created_at'              => $time_now,
                'updated_at'              => $time_now,
                'environment'             => $environment['environment'],
                'address'                 => $environment['address'],
                'username'                => $environment['username'],
                'password'                => $environment['password'],
                'protocol'                => $environment['protocol'],
                'port'                    => $environment['port'],
                'home_directory'          => $environment['home_directory'],
                'database_username'       => $environment['database_username'],
                'database_password'       => $environment['database_password'],
                'offload_enabled'         => $environment['offload_enabled'],
                'offload_access_key'      => $environment['offload_access_key'],
                'offload_secret_key'      => $environment['offload_secret_key'],
                'offload_bucket'          => $environment['offload_bucket'],
                'offload_path'            => $environment['offload_path'],
                'updates_enabled'         => $environment['updates_enabled'],
                'updates_exclude_plugins' => $environment['updates_exclude_plugins'],
                'updates_exclude_themes'  => $environment['updates_exclude_themes'],
            ];
            $db_environments->insert( $new_environment );
        }

        // Generate new account if needed
        if ( $site->account_id == "" ) {
            $hosting_plans = json_decode( get_option('captaincore_hosting_plans') );
            if ( is_array( $hosting_plans ) ) {
                $plan        = $hosting_plans[0];
                $plan->usage = (object) [ "storage" => "0", "visits" => "", "sites" => "" ];
            }
            $new_account = [
                "name"       => $site->domain,
                'created_at' => $time_now,
                'updated_at' => $time_now,
                'defaults'   => json_encode( [ "email" => "", "timezone" => "", "recipes" => [], "users" => [] ] ),
                'plan'       => json_encode( $plan ),
                'metrics'    => json_encode( [ "sites" => "1", "users" => "0", "domains" => "0" ] ),
                'status'     => 'active',
            ];
            $site->account_id = ( new Accounts )->insert( $new_account );
            ( new Sites )->update( [ "account_id" => $site->account_id ], [ "site_id" => $site_id ] );
        }

        ( new Account( $site->account_id, true ) )->calculate_totals();
        return $response;
    }

    public function update( $site ) {

        // Work with array as PHP object
        $site     = (object) $site;

        // Prep for response to return
        $response = [];

        // Validate site exists
        $current_site = ( new Sites )->get( $this->site_id );
        if ( $current_site == "" ) {
            $response['response'] = 'Error: Site ID not found.';
            return $response;
        }

        $account_id_previous = $current_site->account_id;

        $time_now                  = date("Y-m-d H:i:s");
        $details                   = json_decode( $current_site->details );
        $details->key              = $site->key;
        $details->environment_vars = $site->environment_vars;

        // Updates post
        $update_site = [
            'site_id'    => $this->site_id,
            'account_id' => $site->account_id,
            'name'       => $site->name,
            'site'       => $site->site,
            'provider'   => $site->provider,
            'updated_at' => $time_now,
            'details'    => json_encode( $details ),
        ];

        $update_response = ( new Sites )->update( $update_site, [ "site_id" => $this->site_id ] );

        if ( ! is_int( $update_response ) ) {
            $response['response'] = 'Failed updating site';
            return $response;
        }

        $response['response'] = 'Successfully updated site';
        $response['site_id']  = $this->site_id;
        $environment_ids      = self::environment_ids();

        if ( $site->shared_with ) {
            self::assign_accounts( $site->shared_with );
        } else {
            self::assign_accounts( [] );
        }

        // Update environments
        $db_environments = new Environments();
        foreach ( $site->environments as $environment ) {
            // Verify this environment ID belongs to this site.
            if ( ! in_array( $environment['environment_id'], $environment_ids )) {
                continue;
            }
            $update_environment = [
                'address'                 => $environment['address'],
                'username'                => $environment['username'],
                'password'                => $environment['password'],
                'protocol'                => $environment['protocol'],
                'port'                    => $environment['port'],
                'home_directory'          => $environment['home_directory'],
                'database_username'       => $environment['database_username'],
                'database_password'       => $environment['database_password'],
                'offload_enabled'         => $environment['offload_enabled'],
                'offload_access_key'      => $environment['offload_access_key'],
                'offload_secret_key'      => $environment['offload_secret_key'],
                'offload_bucket'          => $environment['offload_bucket'],
                'offload_path'            => $environment['offload_path'],
                'updates_enabled'         => $environment['updates_enabled'],
                'updates_exclude_plugins' => $environment['updates_exclude_plugins'],
                'updates_exclude_themes'  => $environment['updates_exclude_themes'],
            ];
            $db_environments->update( $update_environment, [ 'environment_id' => $environment['environment_id'] ] );
        }
        ( new Account( $account_id_previous, true ) )->calculate_totals();
        ( new Account( $site->account_id, true ) )->calculate_totals();
        return $response;
    }

    public function update_mailgun( $domain ) {
        $site = ( new Sites )->get( $this->site_id );
        if ( $site == "" ) {
            $response['response'] = 'Error: Site ID not found.';
            return $response;
        }
        $details          = json_decode( $site->details );
        $details->mailgun = $domain;
        ( new Sites )->update( [ "details" => json_encode( $details ) ], [ "site_id" => $site->site_id ] );
        self::sync();
    }

    public function sync() {

        $command = "site sync {$this->site_id}";
        
        // Disable https when debug enabled
        if ( defined( 'CAPTAINCORE_DEBUG' ) ) {
            add_filter( 'https_ssl_verify', '__return_false' );
        }

        $data = [ 
            'timeout' => 45,
            'headers' => [
                'Content-Type' => 'application/json; charset=utf-8', 
                'token'        => CAPTAINCORE_CLI_TOKEN 
            ],
            'body'        => json_encode( [ "command" => $command ]), 
            'method'      => 'POST', 
            'data_format' => 'body'
        ];

        // Add command to dispatch server
        $response = wp_remote_post( CAPTAINCORE_CLI_ADDRESS . "/run", $data );
        if ( is_wp_error( $response ) ) {
            $error_message = $response->get_error_message();
            return "Something went wrong: $error_message";
        }
        
        return $response["body"];
    }

    public function insert_accounts( $account_ids = [] ) {
        $accountsite = new AccountSite();
        foreach( $account_ids as $account_id ) {

            // Fetch current records
            $lookup = $accountsite->where( [ "site_id" => $this->site_id, "account_id" => $account_id ] );

            // Add new record
            if ( count($lookup) == 0 ) {
                $accountsite->insert( [ "site_id" => $this->site_id, "account_id" => $account_id ] );
            }
        }
    }

    public function assign_accounts( $account_ids = [] ) {

        $accountsite = new AccountSite();

        // Fetch current records
        $current_account_ids = array_column ( $accountsite->where( [ "site_id" => $this->site_id ] ), "account_id" );

        // Removed current records not found new records.
        foreach ( array_diff( $current_account_ids, $account_ids ) as $account_id ) {
            $records = $accountsite->where( [ "site_id" => $this->site_id, "account_id" => $account_id ] );
            foreach ( $records as $record ) {
                $accountsite->delete( $record->account_site_id );
            }
        }

        // Add new records
        foreach ( array_diff( $account_ids, $current_account_ids ) as $account_id ) {
            $accountsite->insert( [ "site_id" => $this->site_id, "account_id" => $account_id ] );
        }

        // Calculate new totals
        $all_account_ids = array_unique( array_merge ( $account_ids, $current_account_ids ) );
        foreach ( $all_account_ids as $account_id ) {
            ( new Account( $account_id, true ) )->calculate_totals();
        }

    }

    public function delete() {
        $time_now = date("Y-m-d H:i:s");

       ( new Sites )->update( [ 
            "status"     => "inactive",
            "updated_at" => $time_now,
        ],[ 
            "site_id" => $this->site_id 
        ] );

    }

    public function captures( $environment = "production" ) {

        $environment_id = self::fetch_environment_id( $environment );
        $captures       = new Captures();
        $results        = $captures->where( [ "site_id" => $this->site_id, "environment_id" => $environment_id ] );

        foreach ( $results as $result ) {
            $created_at_friendly = new \DateTime( $result->created_at );
            $created_at_friendly->setTimezone( new \DateTimeZone( get_option( 'gmt_offset' ) ) );
            $created_at_friendly = date_format( $created_at_friendly, 'D, M jS Y g:i a');
            $result->created_at_friendly =  $created_at_friendly;
            $result->pages = json_decode( $result->pages );
        }

        return $results;

    }

    public function backup_get( $backup_id, $environment = "production" ) {

        $command = "site backup get {$this->site_id}-$environment $backup_id";
        
        // Disable https when debug enabled
        if ( defined( 'CAPTAINCORE_DEBUG' ) ) {
            add_filter( 'https_ssl_verify', '__return_false' );
        }

        $data = [ 
            'timeout' => 45,
            'headers' => [
                'Content-Type' => 'application/json; charset=utf-8', 
                'token'        => CAPTAINCORE_CLI_TOKEN 
            ],
            'body'        => json_encode( [ "command" => $command ]),
            'method'      => 'POST',
            'data_format' => 'body'
        ];

        // Add command to dispatch server
        $response = wp_remote_post( CAPTAINCORE_CLI_ADDRESS . "/run", $data );
        if ( is_wp_error( $response ) ) {
            $error_message = $response->get_error_message();
            return "Something went wrong: $error_message";
        }

        $response["body"] = explode( PHP_EOL, $response["body"] );
        $response["body"] = "[" . implode(",", $response["body"] ) . "]";

        $json    = json_decode( $response["body"] );

        if ( json_last_error() != JSON_ERROR_NONE ) {
            return [];
        }

        function buildTree( $branches ) {
            // Create a hierarchy where keys are the labels
            $rootChildren = [];
            foreach($branches as $branch) {
                $children =& $rootChildren;
                $paths = explode( "/", $branch->path );
                foreach( $paths as $label ) {
                    if ( $label == "" ) { 
                        continue;
                    };
                    $ext = "";
                    if ( strpos( $label, "." ) !== false ) { 
                        $ext = substr( $label, strpos( $label, "." ) + 1 );
                    }
                    if (!isset($children[$label])) $children[$label] = [ "//path" => $branch->path, "//type" => $branch->type, "//size" => $branch->size, "//ext" => $ext ];
                    $children =& $children[$label];
                }
            }
            // Create target structure from that hierarchy
            function recur($children) {
                $result = [];
                foreach( $children as $label => $grandchildren ) {
                    $node = [ 
                        "name" => $label,
                        "path" => $grandchildren["//path"],
                        "type" => $grandchildren["//type"],
                        "size" => $grandchildren["//size"],
                        "ext" => $grandchildren["//ext"]
                    ];
                    unset( $grandchildren["//path"] );
                    unset( $grandchildren["//type"] );
                    unset( $grandchildren["//size"] );
                    unset( $grandchildren["//ext"] );
                    if ( count($grandchildren) ) { 
                        $node["children"] = recur( $grandchildren );
                    };
                    $result[] = $node;
                }
                return $result;
            }
            return recur($rootChildren);
        }
        
        $results = buildTree( $json );
        
        function sortRecurse(&$array) {
            usort($array, function($a, $b) {
                $retval = $a['name'] <=> $b['name'];
                return $retval;
            });
            foreach ($array as &$subarray) {
                if ( isset( $subarray['children']) ) {
                    sortRecurse($subarray['children']);
                }
            }
            return $array;
        }
        
        return sortRecurse( $results );

    }

    public function backups( $environment = "production" ) {

        $command = "site backup list {$this->site_id}-$environment";
        
        // Disable https when debug enabled
        if ( defined( 'CAPTAINCORE_DEBUG' ) ) {
            add_filter( 'https_ssl_verify', '__return_false' );
        }

        $data = [ 
            'timeout' => 45,
            'headers' => [
                'Content-Type' => 'application/json; charset=utf-8',
                'token'        => CAPTAINCORE_CLI_TOKEN
            ],
            'body'        => json_encode( [ "command" => $command ]), 
            'method'      => 'POST', 
            'data_format' => 'body'
        ];

        // Add command to dispatch server
        $response = wp_remote_post( CAPTAINCORE_CLI_ADDRESS . "/run", $data );
        if ( is_wp_error( $response ) ) {
            $error_message = $response->get_error_message();
            return "Something went wrong: $error_message";
        }

        $result = json_decode( $response["body"] );

        if ( json_last_error() != JSON_ERROR_NONE ) {
            return [];
        }

        foreach( $result as $item ) {
            $item->loading = true;
            $item->files   = [];
            $item->tree    = [];
        }

        usort( $result, function ($a, $b) { return ( $a->time < $b->time ); });

        return $result;

    }

    public function generate_screenshot() {
        $site         = ( new Sites )->get( $this->site_id );
        $environments = self::environments();
        
        foreach( $environments as $environment ) {
            $capture = ( new Captures )->latest_capture( [ "site_id" => $this->site_id, "environment_id" => $environment->environment_id ] );
            if ( empty( $capture ) ) {
                continue;
            }
            $created_at               = strtotime( $capture->created_at );
            $git_commit_short         = substr( $capture->git_commit, 0, 7 );
            $details                  = ( isset( $environment->details ) ? json_decode( $environment->details ) : (object) [] );
            $details->screenshot_base = "{$created_at}_${git_commit_short}";
            ( new Environments )->update( [ "screenshot" => true, "details" => json_encode( $details ) ], [ "environment_id" => $environment->environment_id ] );

            // Update sites if needed
            if ( $environment->environment == "Production" ) {
                $details                  = json_decode( $site->details );
                $details->screenshot_base = "{$created_at}_${git_commit_short}";
                ( new Sites )->update( [ "screenshot" => true, "details" => json_encode( $details ) ], [ "site_id" => $site->site_id ] );
            }
        }
        self::sync();
    }

    public function customer() {
        $customer = (object) [];
        if ( $customer ) {
            foreach ( $customer as $customer_id ) {
                $customer_name = get_post_field( 'post_title', $customer_id, 'raw' );
                $addons        = get_field( 'addons', $customer_id );
                if ( $addons == '' ) {
                    $addons = [];
                }
                $site_details->customer = array(
                    'customer_id'    => $customer_id,
                    'name'           => $customer_name,
                    'hosting_addons' => $addons,
                    'hosting_plan'   => array(
                        'name'          => get_field( 'hosting_plan', $customer_id ),
                        'visits_limit'  => get_field( 'visits_limit', $customer_id ),
                        'storage_limit' => get_field( 'storage_limit', $customer_id ),
                        'sites_limit'   => get_field( 'sites_limit', $customer_id ),
                        'price'         => get_field( 'price', $customer_id ),
                    ),
                    'usage'          => array(
                        'storage' => get_field( 'storage', $customer_id ),
                        'visits'  => get_field( 'visits', $customer_id ),
                        'sites'   => get_field( 'sites', $customer_id ),
                    ),
                );
            }
        }

        if ( count( $site_details->customer ) == 0 ) {
            $site_details->customer = array(
                'customer_id'   => '',
                'name'          => '',
                'hosting_plan'  => '',
                'visits_limit'  => '',
                'storage_limit' => '',
                'sites_limit'   => '',
            );
        }

    }

    public function account() {
        $site    = ( new Sites )->get( $this->site_id );
        $account = ( new Accounts )->get( $site->account_id );
        $plan    = json_decode( $account->plan );
        $results = [
            'account_id' => $site->account_id,
            'name'       => $account->name,
            'plan'       => json_decode( $account->plan ),
            'defaults'   => json_decode( $account->defaults ),
        ];
        return $results;
    }

    public function shared_with() {
        $account_ids = ( new AccountSite )->where( [ "site_id" => $this->site_id ] );
        return array_column( $account_ids, "account_id" );
    }

    public function environment_ids() {
        $environment_ids = ( new Environments )->where( [ "site_id" => $this->site_id ] );
        return array_column( $environment_ids, "environment_id" );
    }

    public function fetch_environment_id( $environment ) {
        $environment_id = ( new Environments )->where( [ "site_id" => $this->site_id, "environment" => $environment ] );
        return array_column( $environment_id, "environment_id" )[0];
    }

    public function environments() {
        // Fetch relating environments
        $site         = ( new Sites )->get( $this->site_id );
        $environments = ( new Environments )->fetch_environments( $this->site_id );
        $upload_uri   = get_option( 'options_remote_upload_uri');
       
        foreach ( $environments as $environment ) {
            $environment_name         = strtolower( $environment->environment );
            $details                  = ( isset( $environment->details ) ? json_decode( $environment->details ) : (object) [] );
            $environment->screenshots = [];
            if ( intval( $environment->screenshot ) ) {
                $screenshot_url_base       = "{$upload_uri}{$site->site}_{$site->site_id}/$environment_name/screenshots/{$details->screenshot_base}";
                $environment->screenshots  = [
                    'small' => "{$screenshot_url_base}_thumb-100.jpg",
                    'large' => "{$screenshot_url_base}_thumb-800.jpg"
                ];
            }
            if ( $site->provider == 'kinsta' ) {
                $environment->ssh = "ssh {$environment->username}@{$environment->address} -p {$environment->port}";
            }
            if ( $site->provider == 'kinsta' and $environment->database_username ) {
                $address_array = explode( ".", $environment->address );
                $kinsta_ending = array_pop( $address_array );
                if ( $kinsta_ending != "com" && $kinsta_ending != "cloud" ) {
                    $kinsta_ending = "cloud";
                }
                $environment->database          = "https://mysqleditor-{$environment->database_username}.kinsta.{$kinsta_ending}";
                if ( $environment->environment == "Staging" ) {
                    $environment->database      = "https://mysqleditor-staging-{$environment->database_username}.kinsta.{$kinsta_ending}";
                }
            }
            $environment->details          = json_decode( $environment->details );
            $environment->link             = $environment->home_url;
            $environment->fathom           = json_decode( $environment->fathom );
            $environment->plugins          = json_decode( $environment->plugins );
            $environment->themes           = json_decode( $environment->themes );
            $environment->stats            = 'Loading';
            $environment->users            = 'Loading';
            $environment->users_search     =  '';
            $environment->backups          = 'Loading';
            $environment->quicksaves       = 'Loading';
            $environment->snapshots        = 'Loading';
            $environment->update_logs      = 'Loading';
            $environment->quicksave_panel  = [];
            $environment->quicksave_search = '';
            $environment->capture_pages    = json_decode ( $environment->capture_pages );
            $environment->updates_enabled  = intval( $environment->updates_enabled );
            $environment->updates_exclude_plugins = explode(",", $environment->updates_exclude_plugins );
            $environment->updates_exclude_themes = explode(",", $environment->updates_exclude_themes );
            $environment->themes_selected  = [];
            $environment->plugins_selected = [];
            $environment->users_selected   = [];
            if ( $environment->details == "" ) {
                $environment->details = [];
            }
            if ( $environment->themes == "" ) {
                $environment->themes = [];
            }
            if ( $environment->plugins == "" ) {
                $environment->plugins = [];
            }
            if ( $environment->fathom == "" ) {
                $environment->fathom = [ [ "domain" => "", "code" => ""] ];
            }
        }

        return $environments;
    }

    public function environments_bare() {
        // Fetch relating environments
        $db_environments = new Environments();
        $environments    = $db_environments->fetch_environments( $this->site_id );
        $results         = [];
        foreach ($environments as $environment) {
            $result = [
                "themes"  => json_decode( $environment->themes ),
                "plugins" => json_decode( $environment->plugins ),
                "details" => json_decode( $environment->details ),
            ];
            if ( $result["themes"] == "" ) {
                $result["themes"] = [];
            }
            if ( $result["plugins"] == "" ) {
                $result["plugins"] = [];
            }
            $results[] = $result;
        }

        if ( count( $results ) == 0 ) {
            return [[ "themes" => [], "plugins" => [] ]];
        }

        return $results;
    }

    public function update_logs() {
        // Fetch relating environments
        $site            = ( new Sites )->get( $this->site_id );
        $environments    = ( new Environments )->fetch_environments( $this->site_id );
        $results         = (object) [];
        foreach ($environments as $environment) {
            $results->{$environment->environment} = ( new UpdateLogs )->fetch_logs( $this->site_id, $environment->environment_id );
        }
        return $results;
    }

    public function users() {
        $db_environments = new Environments();
        $environments    = $db_environments->fetch_environments( $this->site_id );
        $results = (object) [];
        foreach( $environments as $environment ) {
            $users = json_decode( $environment->users );
            array_multisort(
                array_column($users, 'roles'), SORT_ASC,
                array_column($users, 'user_login'), SORT_ASC,
                $users
            );
            if ( $users != "" ) {
                $results->{$environment->environment} = $users;
            }
        }
        return $results;
    }

    public function snapshots() {

        $db_environments = new Environments();
        $environments    = $db_environments->fetch_environments( $this->site_id );
        $results         = (object) [];
        foreach ($environments as $environment) {
            $snapshots = ( new Snapshots )->fetch_environment( $this->site_id, $environment->environment_id );
            foreach( $snapshots as $snapshot ) {
                $snapshot->created_at = strtotime( $snapshot->created_at );
                if ( $snapshot->user_id == 0 ) {
                    $user_name = "System";
                } else {
                    $user_name = get_user_by( 'id', $snapshot->user_id )->display_name;
                }
                $snapshot->user = (object) [
                    "user_id" => $snapshot->user_id,
                    "name"    => $user_name
                ];
                unset( $snapshot->user_id );
            }
            $results->{$environment->environment} = $snapshots;
        }

        return $results;
    }

    public function quicksaves() {
        // Fetch relating environments
        $site            = ( new Sites )->get( $this->site_id );
        $db_environments = new Environments();
        $db_quicksaves   = new Quicksaves();
        $environments    = $db_environments->fetch_environments( $this->site_id );
        $results = (object) [];
        foreach ($environments as $environment) {
            $quicksaves = $db_quicksaves->fetch_environment( $this->site_id, $environment->environment_id );
            foreach ($quicksaves as $key => $quicksave) {
                $compare_key = $key + 1;
                $quicksaves[$key]->created_at     = strtotime( $quicksaves[$key]->created_at );
                $quicksaves[$key]->plugins        = json_decode($quicksaves[$key]->plugins);
                $quicksaves[$key]->themes         = json_decode($quicksaves[$key]->themes);
                $quicksaves[$key]->view_changes   = false;
                $quicksaves[$key]->view_files     = [];
                $quicksaves[$key]->filtered_files = [];
                $quicksaves[$key]->loading        = true;
                $quicksaves[$key]->search         = "";

                // Skips compare check on oldest quicksave or if not found.
                if ( !isset($quicksaves[$compare_key]) ) {
                    continue;
                }

                $compare_plugins       = json_decode( $quicksaves[$compare_key]->plugins );
                $compare_themes        = json_decode( $quicksaves[$compare_key]->themes );
                $plugins_names         = array_column( $quicksaves[$key]->plugins, 'name' );
                $themes_names          = array_column( $quicksaves[$key]->themes, 'name' );
                $compare_plugins_names = array_column( $compare_plugins, 'name' );
                $compare_themes_names  = array_column( $compare_themes, 'name' );
                $removed_plugins       = array_diff( $compare_plugins_names, $plugins_names );
                $removed_themes        = array_diff( $compare_themes_names, $themes_names );

                foreach( $quicksaves[$key]->plugins as $plugin ) {
                    $compare_plugin_key = null;

                    // Check if plugin exists in previous Quicksave
                    foreach( $compare_plugins as $compare_key => $compare_plugin ) {
                        if ( $compare_plugin->name == $plugin->name ) {
                            $compare_plugin_key = $compare_key;
                        }
                    }
                    // If not found then mark as newly added.
                    if ( is_null($compare_plugin_key) ) {
                        $plugin->compare = false;
                        $plugin->highlight = "new";
                        continue;
                    }

                    if ( $plugin->version != $compare_plugins[$compare_plugin_key]->version ) {
                        $plugin->compare = false;
                        $plugin->changed_version = true;
                    }

                    if ( $plugin->status != $compare_plugins[$compare_plugin_key]->status ) {
                        $plugin->compare = false;
                        $plugin->changed_status = true;
                    }

                    if( isset($plugin->changed_status) or isset($plugin->changed_version) ) {
                        continue;
                    }

                    // Plugin is the same
                    $plugin->compare = true;
                }

                foreach( $quicksaves[$key]->themes as $theme ) {
                    $compare_theme_key = null;

                    // Check if plugin exists in previous Quicksave
                    foreach( $compare_themes as $compare_key => $compare_theme ) {
                        if ( $compare_theme->name == $theme->name ) {
                            $compare_theme_key = $compare_key;
                        }
                    }
                    // If not found then mark as newly added.
                    if ( is_null($compare_theme_key) ) {
                        $theme->compare = false;
                        $theme->highlight = "new";
                        continue;
                    }

                    if ( $theme->version != $compare_themes[$compare_theme_key]->version ) {
                        $theme->compare = false;
                        $theme->changed_version = true;
                    }

                    if ( $theme->status != $compare_themes[$compare_theme_key]->status ) {
                        $theme->compare = false;
                        $theme->changed_status = true;
                    }

                    if( isset($theme->changed_status) or isset($theme->changed_version) ) {
                        continue;
                    }

                    // Theme is the same
                    $theme->compare = true;
                }

                // Attached removed themes
                foreach ($removed_themes as $removed_theme) {
                    $theme_key = array_search( $removed_theme, array_column( $compare_themes ,'name' ) );
                    $theme = $compare_themes[$theme_key];
                    $theme->compare = false;
                    $theme->deleted = true;
                    $quicksaves[$key]->deleted_themes[] = $theme;
                }

                // Attached removed plugins
                foreach ($removed_plugins as $removed_plugin) {
                    $plugin_key = array_search( $removed_plugin, array_column( $compare_plugins ,'name' ) );
                    $plugin = $compare_plugins[$plugin_key];
                    $plugin->compare = false;
                    $plugin->deleted = true;
                    $quicksaves[$key]->deleted_plugins[] = $plugin;
                }

            }
            $results->{$environment->environment} = $quicksaves;
        }
        return $results;
    }

    public function process_logs() {
        $Parsedown       = new \Parsedown();
        $process_log     = new ProcessLogs();
        $process_logs    = [];
        $results         = ( new ProcessLogSite )->fetch_process_logs( [ "site_id" => $this->site_id ] );
        foreach ( $results as $result ) {
            $item                  = $process_log->get( $result->process_log_id );
            $item->created_at      = strtotime( $item->created_at );
            $item->name            = $result->name;
            $item->description_raw = $item->description;
            $item->description     = $Parsedown->text( $item->description );
            $item->author          = get_the_author_meta( 'display_name', $item->user_id );
            $process_logs[]        = $item;
        }
        return $process_logs;
    }
    public function update_details() {
        $site = ( new Sites )->get( $this->site_id );
        if ( $site == "" ) {
            $response['response'] = 'Error: Site ID not found.';
            return $response;
        }
        $environments    = self::environments();
        $details         = json_decode( $site->details );
        $details->visits = array_sum( array_column( $environments, "visits" ) );
        $details->storage = array_sum( array_column( $environments, "storage" ) );
        ( new Sites )->update( [ "details" => json_encode( $details ) ], [ "site_id" => $site->site_id ] );
    }

}