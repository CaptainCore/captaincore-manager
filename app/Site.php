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
        $upload_uri   = get_option( 'options_remote_upload_uri' );
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
        foreach( $site->environments as $key => $environment ) {
            $site->environments[ $key ] = (object) $environment;
        }

        // Prep for response to return
        $response = [ "errors" => [] ];

        // Pull in current user
        $current_user = wp_get_current_user();

        // Validate
        if ( $site->name == '' ) {
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
        if ( $site->environments[0]->address == "" ) {
            $response['errors'][] = "Error: Production environment address can't be empty.";
        }
        if ( $site->environments[0]->username == "" ) {
            $response['errors'][] = "Error: Production environment username can't be empty.";
        }
        if ( $site->environments[0]->protocol == "" ) {
            $response['errors'][] = "Error: Production environment protocol can't be empty.";
        }
        if ( $site->environments[0]->port == "" ) {
            $response['errors'][] = "Error: Production environment port can't be empty.";
        }
        if ( $site->environments[0]->port != "" and ! ctype_digit( $site->environments[0]->port ) ) {
            $response['errors'][] = "Error: Production environment port can only be numbers.";
        }
        if ( ! empty ( $site->environments[1] ) and $site->environments[1]->port and ! ctype_digit( $site->environments[1]->port ) ) {
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

        // Remove staging if empty
        if ( empty( $site->environments[1]->address ) ) {
            unset( $site->environments[1] );
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
            'account_id'  => $site->account_id,
            'customer_id' => empty( $site->customer_id ) ? "" : $site->customer_id,
            'name'        => $site->name,
            'site'        => $site->site,
            'provider'    => $site->provider,
            'created_at'  => $time_now,
            'updated_at'  => $time_now,
            'details'     => json_encode( $details ),
            'screenshot'  => '0',
            'status'      => 'active',
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
        $shared_with_ids      = [];
        foreach( $site->shared_with as $account_id ) {
            if ( $site->customer_id == $account_id or $site->account_id == $account_id ) {
                continue;
            }
            $shared_with_ids[] = $account_id;
        }

        self::assign_accounts( $shared_with_ids );

        // Update environments
        foreach ( $site->environments as $environment ) {
            $new_environment = [
                'site_id'                 => $site_id,
                'created_at'              => $time_now,
                'updated_at'              => $time_now,
                'environment'             => $environment->environment,
                'address'                 => $environment->address,
                'username'                => $environment->username,
                'password'                => $environment->password,
                'protocol'                => $environment->protocol,
                'port'                    => $environment->port,
                'home_directory'          => $environment->home_directory,
                'database_username'       => $environment->database_username,
                'database_password'       => $environment->database_password,
                'offload_enabled'         => $environment->offload_enabled,
                'offload_access_key'      => $environment->offload_access_key,
                'offload_secret_key'      => $environment->offload_secret_key,
                'offload_bucket'          => $environment->offload_bucket,
                'offload_path'            => $environment->offload_path,
                'monitor_enabled'         => $environment->monitor_enabled,
                'updates_enabled'         => $environment->updates_enabled,
                'updates_exclude_plugins' => $environment->updates_exclude_plugins,
                'updates_exclude_themes'  => $environment->updates_exclude_themes,
            ];
            ( new Environments )->insert( $new_environment );
        }

        // Generate new customer if needed
        if ( empty( $site->customer_id ) ) {
            $hosting_plans = json_decode( get_option('captaincore_hosting_plans') );
            if ( is_array( $hosting_plans ) ) {
                $plan        = $hosting_plans[0];
                $plan->usage = (object) [ "storage" => "0", "visits" => "", "sites" => "" ];
            }
            $new_account = [
                "name"       => $site->name,
                'created_at' => $time_now,
                'updated_at' => $time_now,
                'defaults'   => json_encode( [ "email" => "", "timezone" => "", "recipes" => [], "users" => [] ] ),
                'plan'       => json_encode( $plan ),
                'metrics'    => json_encode( [ "sites" => "1", "users" => "0", "domains" => "0" ] ),
                'status'     => 'active',
            ];
            $site->customer_id = ( new Accounts )->insert( $new_account );
            ( new Sites )->update( [ "customer_id" => $site->customer_id ], [ "site_id" => $site_id ] );
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

        $account_id_previous       = $current_site->account_id;
        $time_now                  = date("Y-m-d H:i:s");
        $details                   = json_decode( $current_site->details );
        $details->key              = $site->key;
        $details->environment_vars = $site->environment_vars;

        // Updates post
        $update_site = [
            'site_id'     => $this->site_id,
            'account_id'  => $site->account_id,
            'customer_id' => $site->customer_id,
            'name'        => $site->name,
            'site'        => $site->site,
            'provider'    => $site->provider,
            'updated_at'  => $time_now,
            'details'     => json_encode( $details ),
        ];

        $update_response = ( new Sites )->update( $update_site, [ "site_id" => $this->site_id ] );

        if ( ! is_int( $update_response ) ) {
            $response['response'] = 'Failed updating site';
            return $response;
        }

        $response['response'] = 'Successfully updated site';
        $response['site_id']  = $this->site_id;
        $environment_ids      = self::environment_ids();
        $shared_with_ids      = [];
        foreach( $site->shared_with as $account_id ) {
            if ( $site->customer_id == $account_id or $site->account_id == $account_id ) {
                continue;
            }
            $shared_with_ids[] = $account_id;
        }

        self::assign_accounts( $shared_with_ids );

        $new_environment_ids = array_column( $site->environments, "environment_id" );
        foreach( $environment_ids as $environment_id ) {
            if ( ! in_array( $environment_id, $new_environment_ids ) ) {
                ( new Environments )->delete( $environment_id );
            }
        }

        // Update environments
        $db_environments = new Environments();
        foreach ( $site->environments as $environment ) {
            // Add as new environment
            if ( empty( $environment['environment_id'] ) ) {
                $new_environment = [
                    'site_id'                 => $this->site_id,
                    'environment'             => "Staging",
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
                    'monitor_enabled'         => $environment['monitor_enabled'],
                    'updates_enabled'         => $environment['updates_enabled'],
                    'updates_exclude_plugins' => $environment['updates_exclude_plugins'],
                    'updates_exclude_themes'  => $environment['updates_exclude_themes'],
                ];
                $environment_id = ( new Environments )->insert( $new_environment );
                continue;

            }
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
                'monitor_enabled'         => $environment['monitor_enabled'],
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

    public function mark_inactive() {
        $site     = self::get();
        $time_now = date("Y-m-d H:i:s");
        ( new Sites )->update( [ "status" => "inactive", "updated_at" => $time_now ], [ "site_id" => $this->site_id ] );
        ( new Account( $site->account_id ) )->calculate_usage();
    }

    public function delete() {
        ( new Sites )->delete( $this->site_id );
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

    public function quicksave_get( $hash, $environment = "production" ) {

        $command = "quicksave get {$this->site_id}-$environment $hash";
        
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
            return [];
        }

        $json    = json_decode( $response["body"] );

        if ( json_last_error() != JSON_ERROR_NONE ) {
            return [];
        }

        return $json;

    }

    public function backup_show_file( $backup_id, $file_id, $environment = "production" ) {

        $file    = base64_encode( $file_id );
        $command = "backup show {$this->site_id}-$environment $backup_id $file";
    
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
            return [];
        }
    
        return $response["body"];
    }

    public function backup_get( $backup_id, $environment = "production" ) {

        $command = "backup get {$this->site_id}-$environment $backup_id";
        
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
            return [];
        }
        return $response["body"];

    }

    public function backups( $environment = "production" ) {

        $command = "backup list {$this->site_id}-$environment";
        
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
            $item->omitted = false;
            $item->files   = [];
            $item->tree    = [];
            $item->active  = [];
            $item->preview = "";
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
        $site          = ( new Sites )->get( $this->site_id );
        $accounts      = [];
        $account_ids   = ( new AccountSite )->where( [ "site_id" => $this->site_id ] );
        $account_ids   = array_column( $account_ids, "account_id" );
        $account_ids[] = $site->account_id;
        $account_ids[] = $site->customer_id;
        $account_ids   = array_filter( array_unique( $account_ids ) );
        foreach ( $account_ids as $account_id ) {
            $account    = ( new Accounts )->get( $account_id );
            $accounts[] = (object) [ 
                "account_id" => $account->account_id,
                "name"       => $account->name,
            ];
        }
        return $accounts;
    }

    public function shared_with_ids() {
        return array_column( self::shared_with(), 'account_id' );
    }

    public function fetch() {
        $site                   = ( new Sites )->get( $this->site_id );
        $details                = json_decode( $site->details );
        $site->filtered         = true;
        $site->loading          = false;
        $site->key              = $details->key;
        $site->core             = $details->core;
        $site->mailgun          = $details->mailgun;
        $site->console_errors   = isset( $details->console_errors ) ? $details->console_errors : "";
        $site->environment_vars = isset( $details->environment_vars ) ? $details->environment_vars : [];
        $site->backup_settings  = isset( $details->backup_settings ) ? $details->backup_settings : (object) [ "mode" => "local", "interval" => "daily", "active" => true ];
        $site->subsites         = $details->subsites;
        $site->storage          = $details->storage;
        $site->visits           = $details->visits;
        $site->outdated         = false;
        $site->screenshot_base  = $details->screenshot_base;
        
        // Mark site as outdated if sync older then 48 hours
        if ( strtotime( $site->updated_at ) <= strtotime( "-48 hours" ) ) {
            $site->outdated = true;
        }

        unset( $site->token );
        unset( $site->created_at );
        unset( $site->details );
        unset( $site->status );
        unset( $site->site_usage );
        return $site;
    }

    public function environment_ids() {
        $environment_ids = ( new Environments )->where( [ "site_id" => $this->site_id ] );
        return array_column( $environment_ids, "environment_id" );
    }

    public function fetch_environment_id( $environment ) {
        $environment_id = ( new Environments )->where( [ "site_id" => $this->site_id, "environment" => $environment ] );
        return array_column( $environment_id, "environment_id" )[0];
    }

    public function fetch_phpmyadmin() {

        $site = ( new Sites )->get( $this->site_id );
        if ( $site->provider == "rocketdotnet" ) {
            $api_request = "https://api.rocket.net/v1/sites/{$site->provider_id}/pma/login";
            $response    = wp_remote_get( $api_request  , [
                'headers'     => [
                    'Authorization' => 'Bearer ' . \CaptainCore\Providers\Rocketdotnet::credentials("token"),
                    'accept'        => 'application/json',
                ]
            ]);

            if ( is_wp_error( $response ) ) {
                return $response->get_error_message();
            }

            $response = json_decode( $response['body'] );
            if ( ! empty( $response->result ) ) {
                return $response->result->phpmyadmin_sign_on_url;
            }
        }
        
    }

    public function environments() {
        // Fetch relating environments
        $site         = ( new Sites )->get( $this->site_id );
        $environments = ( new Environments )->fetch_environments( $this->site_id );
        $upload_uri   = get_option( 'options_remote_upload_uri' );
       
        foreach ( $environments as $environment ) {
            $environment_name         = strtolower( $environment->environment );
            $details                  = ( isset( $environment->details ) ? json_decode( $environment->details ) : (object) [] );
            $environment->captures    = count ( self::captures( $environment_name ) );
            $environment->screenshots = [];
            if ( intval( $environment->screenshot ) ) {
                $screenshot_url_base       = "{$upload_uri}{$site->site}_{$site->site_id}/$environment_name/screenshots/{$details->screenshot_base}";
                $environment->screenshots  = [
                    'small' => "{$screenshot_url_base}_thumb-100.jpg",
                    'large' => "{$screenshot_url_base}_thumb-800.jpg"
                ];
            }
            $environment->fathom_analytics = ( ! empty( $details->fathom ) ? $details->fathom : [] );
            if ( $site->provider == 'kinsta' || $site->provider == 'rocketdotnet' ) {
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
                if ( preg_match('/.+\.temp.+?\.kinsta.cloud/', $environment->address, $matches ) ) {
                    $environment->database          = "https://mysqleditor-{$environment->address}";
                }
                if ( preg_match('/.+\.temp.+?\.kinsta.cloud/', $environment->address, $matches ) && $environment->environment == "Staging" ) {
                    $environment->database          = "https://mysqleditor-staging-{$environment->address}";
                }
            }
            $environment->environment_id   = $environment->environment_id;
            $environment->details          = json_decode( $environment->details );
            $environment->link             = $environment->home_url;
            $environment->fathom           = json_decode( $environment->fathom );
            $environment->plugins          = json_decode( $environment->plugins );
            $environment->themes           = json_decode( $environment->themes );
            $environment->stats            = 'Loading';
            $environment->stats_password   = '';
            $environment->users            = 'Loading';
            $environment->users_search     =  '';
            $environment->backups          = 'Loading';
            $environment->quicksaves       = 'Loading';
            $environment->snapshots        = 'Loading';
            $environment->update_logs      = 'Loading';
            $environment->quicksave_panel  = [];
            $environment->quicksave_search = '';
            $environment->capture_pages    = json_decode ( $environment->capture_pages );
            $environment->monitor_enabled  = intval( $environment->monitor_enabled );
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

    public function stats_sharing(  $fathom_id = "", $sharing = "", $share_password = "" ) {
        $environments     = self::environments();
        $fathom_ids       = [];
        foreach( $environments as $environment ) {
            $environment_fathom_ids = array_column( $environment->fathom_analytics, "code" );
            foreach ( $environment_fathom_ids as $id ) {
                $fathom_ids[] = strtolower( $id );
            } 
        }
        if ( ! in_array( strtolower( $fathom_id ), $fathom_ids ) ) {
            return;
        }
       
        $url      = "https://api.usefathom.com/v1/sites/$fathom_id";
        $response = wp_remote_post( $url, [ 
            "headers" => [ "Authorization" => "Bearer " . \CaptainCore\Providers\Fathom::credentials("api_key") ],
            'body'    => [ "sharing" => $sharing, "share_password" => $share_password ],
        ] );

        return json_decode( $response['body'] );

    }

    public function stats( $environment = "production", $before = "", $after = "", $grouping = "month", $fathom_id = "" ) {

        if ( empty( $after ) ) {
            $after = date( 'Y-m-d H:i:s' );
        }

        if ( empty( $before ) ) {
            $date = strtotime("$after -1 year" );
            $before = date('Y-m-d H:i:s', $date);
        }

        $before = date( 'Y-m-d H:i:s', $before );
        $after  = date( 'Y-m-d H:i:s', $after );

        $environments     = self::environments();
        foreach( $environments as $e ) {
            if ( strtolower( $e->environment ) == strtolower( $environment ) ) {
                $selected_environment = $e;
            }
        }
        if ( empty( $selected_environment ) ) {
            return;
        }
        $fathom_ids = array_column( $selected_environment->fathom_analytics, "code" );

        if ( empty( $fathom_ids ) ) {
            return [ "Error" => "There was a problem retrieving stats." ];
        }

        if ( empty( $fathom_id ) ) {
            $fathom_id = $fathom_ids[0];
        }
    
        $url      = "https://api.usefathom.com/v1/aggregations?entity=pageview&entity_id=$fathom_id&aggregates=visits,pageviews,avg_duration,bounce_rate&date_from=$before&date_to=$after&date_grouping=$grouping&sort_by=timestamp:asc";
        $response = wp_remote_get( $url, [ 
            "headers" => [ "Authorization" => "Bearer " . \CaptainCore\Providers\Fathom::credentials("api_key") ],
        ] );

        $stats    = json_decode( $response['body'] );
        if ( $grouping == "hour" ) {
            foreach ( $stats as $stat ) {
                $stat->date = date('M d Y ga', strtotime( $stat->date ) );
            }
        }
        if ( $grouping == "day" ) {
            foreach ( $stats as $stat ) {
                $stat->date = date('M d Y', strtotime( $stat->date ) );
            }
        }
        if ( $grouping == "month" ) {
            foreach ( $stats as $stat ) {
                $stat->date = date('M Y', strtotime( $stat->date ) );
            }
        }
        if ( $grouping == "year" ) {
            foreach ( $stats as $stat ) {
                $stat->date = date('Y', strtotime( $stat->date ) );
            }
        }

        $url      = "https://api.usefathom.com/v1/sites/$fathom_id";
        $response = wp_remote_get( $url, [ 
            "headers" => [ "Authorization" => "Bearer " . \CaptainCore\Providers\Fathom::credentials("api_key") ],
        ] );

        $site     = json_decode( $response['body'] );
        $response = [
            "fathom_id" => $fathom_id,
            "site"      => $site,
            "summary"   => [
                "pageviews"    => array_sum( array_column( $stats, "pageviews" ) ),
                "visits"       => array_sum( array_column( $stats, "visits" ) ),
                "bounce_rate"  => array_sum( array_column( $stats, "bounce_rate" ) ) / count ( $stats ),
                "avg_duration" => array_sum( array_column( $stats, "avg_duration" ) ) / count ( $stats ),
            ],
            "items"     => $stats
        ];
        return $response;
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
            $users = empty( $environment->users ) ? [] : json_decode( $environment->users );
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

    public function quicksaves( $environment = "both" ) {

        $command = "quicksave list {$this->site_id}-$environment";
        
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
            'body'        => json_encode( [ "command" => $command ] ),
            'method'      => 'POST', 
            'data_format' => 'body'
        ];

        // Add command to dispatch server
        $response = wp_remote_post( CAPTAINCORE_CLI_ADDRESS . "/run", $data );
        if ( is_wp_error( $response ) ) {
            $error_message = $response->get_error_message();
            return "Something went wrong: $error_message";
        }

        $quicksaves = json_decode( $response["body"] );

        if ( json_last_error() != JSON_ERROR_NONE ) {
            return [];
        }

        foreach( $quicksaves as $key => $quicksave ) {
            $quicksaves[ $key ]->plugins        = [];
            $quicksaves[ $key ]->themes         = [];
            $quicksaves[ $key ]->core           = $quicksave->core;
            $quicksaves[ $key ]->status         = "";
            $quicksaves[ $key ]->view_changes   = false;
            $quicksaves[ $key ]->view_files     = [];
            $quicksaves[ $key ]->filtered_files = [];
            $quicksaves[ $key ]->loading        = true;
            $quicksaves[ $key ]->search         = "";
        }

        return $quicksaves;

        // Fetch relating environments
        $site            = ( new Sites )->get( $this->site_id );
        $db_environments = new Environments();
        $db_quicksaves   = new Quicksaves();
        $environments    = $db_environments->fetch_environments( $this->site_id );
        $results = (object) [];

        if ( $environment != "both" ) {
            $environment_id = self::fetch_environment_id( $environment );
            $quicksaves = $db_quicksaves->fetch_environment( $this->site_id, $environment_id );
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
            return $quicksaves;
        }
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
            $item->author_avatar   = "https://www.gravatar.com/avatar/" . md5( get_the_author_meta( 'email', $item->user_id ) ) . "?s=80&d=mp";
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
        $details->username = $environments[0]->username;
        ( new Sites )->update( [ "details" => json_encode( $details ) ], [ "site_id" => $site->site_id ] );
    }

}