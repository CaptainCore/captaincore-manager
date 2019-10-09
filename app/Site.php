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
            $site = get_post( $this->site_id );
        }

        $upload_dir   = wp_upload_dir();

        // Fetch relating environments
        $db_environments = new Environments();
        $environments    = $db_environments->fetch_environments( $site->ID );

        $domain      = get_the_title( $site->ID );
        $customer    = get_field( 'customer', $site->ID );
        $shared_with = get_field( 'partner', $site->ID );
        $mailgun     = get_field( 'mailgun', $site->ID );
        $storage     = $environments[0]->storage;
        if ( $storage ) {
            $storage_gbs = round( $storage / 1024 / 1024 / 1024, 1 );
            $storage_gbs = $storage_gbs . 'GB';
        } else {
            $storage_gbs = '';
        }
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
        $site_details                       = new \stdClass();
        $site_details->id                   = $site->ID;
        $site_details->name                 = $domain;
        $site_details->site                 = get_field( 'site', $site->ID );
        $site_details->provider             = get_field( 'provider', $site->ID );
        $site_details->key                  = get_field( 'key', $site->ID );
        $site_details->filtered             = true;
        $site_details->usage_breakdown      = [];
        $site_details->timeline             = [];
        $site_details->selected             = false;
        $site_details->loading_plugins      = false;
        $site_details->loading_themes       = false;
        $site_details->environment_selected = 'Production';
        $site_details->mailgun              = $mailgun;
        $site_details->subsite_count        = $subsite_count;
        $site_details->tabs                 = 'tab-Site-Management';
        $site_details->tabs_management      = 'tab-Info';
        $site_details->storage_raw          = $environments[0]->storage;
        $site_details->storage              = $storage_gbs;
        $site_details->outdated				= false;
        if ( is_string( $visits ) ) {
            $site_details->visits = number_format( intval( $visits ) );
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

        if ( ! isset( $site_details->visits ) ) {
            $site_details->visits = '';
        }

        if ( $site_details->visits == 0 ) {
            $site_details->visits = '';
        }

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

        $site_details->users       = [];
        $site_details->update_logs = [];

        if ( $shared_with ) {
            foreach ( $shared_with as $customer_id ) {
                $site_details->shared_with[] = array(
                    'customer_id' => "$customer_id",
                    'name'        => get_post_field( 'post_title', $customer_id, 'raw' ),
                );
            }
        }

        $site_details->environments[0] = array(
            'id'                      => intval( $environments[0]->environment_id ),
            'link'                    => "http://$domain",
            'environment'             => 'Production',
            'updated_at'              => $environments[0]->updated_at,
            'address'                 => $environments[0]->address,
            'username'                => $environments[0]->username,
            'password'                => $environments[0]->password,
            'protocol'                => $environments[0]->protocol,
            'port'                    => $environments[0]->port,
            'home_directory'          => $environments[0]->home_directory,
            'fathom'                  => json_decode( $environments[0]->fathom ),
            'plugins'                 => json_decode( $environments[0]->plugins ),
            'themes'                  => json_decode( $environments[0]->themes ),
            'users'                   => 'Loading',
            'quicksaves'              => 'Loading',
            'snapshots'               => 'Loading',
            'update_logs'             => 'Loading',
            'quicksave_panel'         => [],
            'quicksave_search'        => '',
            'core'                    => $environments[0]->core,
            'home_url'                => $environments[0]->home_url,
            'updates_enabled'         => intval( $environments[0]->updates_enabled ),
            'updates_exclude_plugins' => $environments[0]->updates_exclude_plugins,
            'updates_exclude_themes'  => $environments[0]->updates_exclude_themes,
            'offload_enabled'         => $environments[0]->offload_enabled,
            'offload_provider'        => $environments[0]->offload_provider,
            'offload_access_key'      => $environments[0]->offload_access_key,
            'offload_secret_key'      => $environments[0]->offload_secret_key,
            'offload_bucket'          => $environments[0]->offload_bucket,
            'offload_path'            => $environments[0]->offload_path,
            'screenshot'              => intval( $environments[0]->screenshot ),
            'screenshot_small'        => '',
            'screenshot_large'        => '',
            'stats'                   => 'Loading',
            'themes_selected'         => [],
            'plugins_selected'        => [],
            'users_selected'          => [],
        );

        if ( $site_details->environments[0]['fathom'] == '' ) {
            $site_details->environments[0]['fathom'] = array(
                array(
                    'code'   => '',
                    'domain' => '',
                ),
            );
        }

        if ( intval( $environments[0]->screenshot ) ) {
            $site_details->environments[0]['screenshot_small'] = $upload_dir['baseurl'] . "/screenshots/{$site_details->site}_{$site_details->id}/production/screenshot-100.png";
            $site_details->environments[0]['screenshot_large'] = $upload_dir['baseurl'] . "/screenshots/{$site_details->site}_{$site_details->id}/production/screenshot-800.png";
        }

        if ( $site_details->environments[0]['updates_exclude_themes'] ) {
            $site_details->environments[0]['updates_exclude_themes'] = explode( ',', $site_details->environments[0]['updates_exclude_themes'] );
        } else {
            $site_details->environments[0]['updates_exclude_themes'] = [];
        }
        if ( $site_details->environments[0]['updates_exclude_plugins'] ) {
            $site_details->environments[0]['updates_exclude_plugins'] = explode( ',', $site_details->environments[0]['updates_exclude_plugins'] );
        } else {
            $site_details->environments[0]['updates_exclude_plugins'] = [];
        }

        if ( $site_details->environments[0]['themes'] == '' ) {
            $site_details->environments[0]['themes'] = [];
        }
        if ( $site_details->environments[0]['plugins'] == '' ) {
            $site_details->environments[0]['plugins'] = [];
        }

        if ( $site_details->provider == 'kinsta' ) {
            $site_details->environments[0]['ssh'] = "ssh ${production_username}@${production_address} -p ${production_port}";
        }
        if ( $site_details->provider == 'kinsta' and $environments[0]->database_username ) {
            $kinsta_ending = array_pop( explode(".", $site_details->environments[0]['address']) );
            if ( $kinsta_ending != "com" && $$kinsta_ending != "cloud" ) {
                $kinsta_ending = "cloud";
            }
            $site_details->environments[0]['database']          = "https://mysqleditor-${database_username}.kinsta.{$kinsta_ending}";
            $site_details->environments[0]['database_username'] = $environments[0]->database_username;
            $site_details->environments[0]['database_password'] = $environments[0]->database_password;
        }

        if ( $site_details->provider == 'kinsta' ) {
            $link_staging = $environments[1]->home_url;
        }

        if ( $site_details->provider == 'wpengine' ) {
            $link_staging = 'https://' . get_field( 'site', $site->ID ) . '.staging.wpengine.com';
        }

        $site_details->environments[1] = array(
            'id'                      => intval( $environments[1]->environment_id ),
            'link'                    => $link_staging,
            'environment'             => 'Staging',
            'updated_at'              => $environments[1]->updated_at,
            'address'                 => $environments[1]->address,
            'username'                => $environments[1]->username,
            'password'                => $environments[1]->password,
            'protocol'                => $environments[1]->protocol,
            'port'                    => $environments[1]->port,
            'home_directory'          => $environments[1]->home_directory,
            'fathom'                  => json_decode( $environments[1]->fathom ),
            'plugins'                 => json_decode( $environments[1]->plugins ),
            'themes'                  => json_decode( $environments[1]->themes ),
            'users'                   => 'Loading',
            'quicksaves'              => 'Loading',
            'snapshots'               => 'Loading',
            'update_logs'             => 'Loading',
            'quicksave_panel'         => [],
            'quicksave_search'        => '',
            'core'                    => $environments[1]->core,
            'home_url'                => $environments[1]->home_url,
            'updates_enabled'         => intval( $environments[1]->updates_enabled ),
            'updates_exclude_plugins' => $environments[1]->updates_exclude_plugins,
            'updates_exclude_themes'  => $environments[1]->updates_exclude_themes,
            'offload_enabled'         => $environments[1]->offload_enabled,
            'offload_provider'        => $environments[1]->offload_provider,
            'offload_access_key'      => $environments[1]->offload_access_key,
            'offload_secret_key'      => $environments[1]->offload_secret_key,
            'offload_bucket'          => $environments[1]->offload_bucket,
            'offload_path'            => $environments[1]->offload_path,
            'screenshot'              => intval( $environments[1]->screenshot ),
            'screenshot_small'        => '',
            'screenshot_large'        => '',
            'stats'                   => 'Loading',
            'themes_selected'         => [],
            'plugins_selected'        => [],
            'users_selected'          => [],
        );

        if ( $site_details->environments[1]['fathom'] == '' ) {
            $site_details->environments[1]['fathom'] = array(
                array(
                    'code'   => '',
                    'domain' => '',
                ),
            );
        }

        if ( intval( $environments[1]->screenshot ) == 1 ) {
            $site_details->environments[1]['screenshot_small'] = $upload_dir['baseurl'] . "/screenshots/{$site_details->site}_{$site_details->id}/staging/screenshot-100.png";
            $site_details->environments[1]['screenshot_large'] = $upload_dir['baseurl'] . "/screenshots/{$site_details->site}_{$site_details->id}/production/screenshot-800.png";
        }

        if ( $site_details->environments[1]['updates_exclude_themes'] ) {
            $site_details->environments[1]['updates_exclude_themes'] = explode( ',', $site_details->environments[1]['updates_exclude_themes'] );
        } else {
            $site_details->environments[1]['updates_exclude_themes'] = [];
        }
        if ( $site_details->environments[1]['updates_exclude_plugins'] ) {
            $site_details->environments[1]['updates_exclude_plugins'] = explode( ',', $site_details->environments[1]['updates_exclude_plugins'] );
        } else {
            $site_details->environments[1]['updates_exclude_plugins'] = [];
        }

        if ( $site_details->environments[1]['themes'] == '' ) {
            $site_details->environments[1]['themes'] = [];
        }
        if ( $site_details->environments[1]['plugins'] == '' ) {
            $site_details->environments[1]['plugins'] = [];
        }

        if ( $site_details->provider == 'kinsta' ) {
            $site_details->environments[1]['ssh'] = "ssh ${staging_username}@${staging_address} -p ${staging_port}";
        }
        if ( $site_details->provider == 'kinsta' and $environments[1]->database_username ) {
            $kinsta_ending = array_pop( explode(".", $site_details->environments[1]['address']) );
            if ( $kinsta_ending != "com" && $$kinsta_ending != "cloud" ) {
                $kinsta_ending = "cloud";
            }
            $site_details->environments[1]['database']          = "https://mysqleditor-staging-${database_username}.kinsta.{$kinsta_ending}";
            $site_details->environments[1]['database_username'] = $environments[1]->database_username;
            $site_details->environments[1]['database_password'] = $environments[1]->database_password;
        }

        return $site_details;

    }

    public function create() {

        // Work with array as PHP object
        $site = (object) $this->site_id;

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
        $arguments = array(
            'fields'         => 'ids',
            'post_type'      => 'captcore_website',
            'posts_per_page' => '-1',
            'meta_query'     => array(
                'relation' => 'AND',
                array(
                    'key'     => 'site',
                    'value'   => $site->site,
                    'compare' => '=',
                ),
                array(
                    'key'     => 'status',
                    'value'   => 'closed',
                    'compare' => '!=',
                ),
            ),
        );

        $site_check = get_posts( $arguments ); 

        if ( count( $site_check ) > 0 ) {
            $response['errors'][] = "Error: Site name needs to be unique.";
        }

        if ( count($response['errors']) > 0 ) {
            return $response;
        }

        // Create post object
        $new_site = array(
            'post_title'  => $site->domain,
            'post_author' => $current_user->ID,
            'post_type'   => 'captcore_website',
            'post_status' => 'publish',
        );

        // Insert the post into the database
        $site_id = wp_insert_post( $new_site );

        if ( $site_id ) {

            $response['response'] = 'Successfully added new site';
            $response['site_id']  = $site_id;

            // add in ACF fields
            update_field( 'site', $site->site, $site_id );
            update_field( 'provider', $site->provider, $site_id );
            update_field( 'customer', $site->customers, $site_id );
            update_field( 'key', $site->key, $site_id );
            update_field( 'partner', array_column( $site->shared_with, 'customer_id' ), $site_id );
            update_field( 'updates_enabled', $site->updates_enabled, $site_id );
            update_field( 'status', 'active', $site_id );

            if ( get_field( 'launch_date', $site_id ) == '' ) {

                // No date was entered for Launch Date, assign to today.
                update_field( 'launch_date', date( 'Ymd' ), $site_id );

            }

            $db_environments = new Environments();

            $environment = array(
                'site_id'                 => $site_id,
                'environment'             => 'Production',
                'address'                 => $site->environments[0]['address'],
                'username'                => $site->environments[0]['username'],
                'password'                => $site->environments[0]['password'],
                'protocol'                => $site->environments[0]['protocol'],
                'port'                    => $site->environments[0]['port'],
                'home_directory'          => $site->environments[0]['home_directory'],
                'database_username'       => $site->environments[0]['database_username'],
                'database_password'       => $site->environments[0]['database_password'],
                'updates_enabled'         => $site->environments[0]['updates_enabled'],
                'updates_exclude_plugins' => $site->environments[0]['updates_exclude_plugins'],
                'updates_exclude_themes'  => $site->environments[0]['updates_exclude_themes'],
                'offload_enabled'         => $site->environments[0]['offload_enabled'],
                'offload_provider'        => $site->environments[0]['offload_provider'],
                'offload_access_key'      => $site->environments[0]['offload_access_key'],
                'offload_secret_key'      => $site->environments[0]['offload_secret_key'],
                'offload_bucket'          => $site->environments[0]['offload_bucket'],
                'offload_path'            => $site->environments[0]['offload_path'],
            );

            $time_now                  = date( 'Y-m-d H:i:s' );
            $environment['created_at'] = $time_now;
            $environment['updated_at'] = $time_now;
            $environment_id            = $db_environments->insert( $environment );
            update_field( 'environment_production_id', $environment_id, $site_id );

            $environment = array(
                'site_id'                 => $site_id,
                'environment'             => 'Staging',
                'address'                 => $site->environments[1]['address'],
                'username'                => $site->environments[1]['username'],
                'password'                => $site->environments[1]['password'],
                'protocol'                => $site->environments[1]['protocol'],
                'port'                    => $site->environments[1]['port'],
                'home_directory'          => $site->environments[1]['home_directory'],
                'database_username'       => $site->environments[1]['database_username'],
                'database_password'       => $site->environments[1]['database_password'],
                'updates_enabled'         => $site->environments[1]['updates_enabled'],
                'updates_exclude_plugins' => $site->environments[1]['updates_exclude_plugins'],
                'updates_exclude_themes'  => $site->environments[1]['updates_exclude_themes'],
                'offload_enabled'         => $site->environments[1]['offload_enabled'],
                'offload_provider'        => $site->environments[1]['offload_provider'],
                'offload_access_key'      => $site->environments[1]['offload_access_key'],
                'offload_secret_key'      => $site->environments[1]['offload_secret_key'],
                'offload_bucket'          => $site->environments[1]['offload_bucket'],
                'offload_path'            => $site->environments[1]['offload_path'],
            );

            $time_now                  = date( 'Y-m-d H:i:s' );
            $environment['created_at'] = $time_now;
            $environment['updated_at'] = $time_now;
            $environment_id            = $db_environments->insert( $environment );
            update_field( 'environment_staging_id', $environment_id, $site_id );

            // Run ACF custom tasks afterward.
            captaincore_acf_save_post_after( $site_id );
        }
        if ( ! $site_id ) {
            $response['response'] = 'Failed to add new site';
        }

        return $response;
    }

    public function update() {

        // Work with array as PHP object
        $site = (object) $this->site_id;

        // Prep for response to return
        $response = [];

        // Pull in current user
        $current_user = wp_get_current_user();

        $site_id = $site->id;

        // Validate site exists
        if ( get_post_type( $site_id ) != 'captcore_website' ) {
            $response['response'] = 'Error: Site ID not found.';
            return $response;
        }

        // Updates post
        $update_site = array(
            'ID'          => $site_id,
            'post_title'  => $site->name,
            'post_author' => $current_user->ID,
            'post_type'   => 'captcore_website',
            'post_status' => 'publish',
        );

        wp_update_post( $update_site, true );

        if ( is_wp_error( $site_id ) ) {
            $errors                          = $site_id->get_error_messages();
                return $response['response'] = implode( ' ', $errors );
        }

        if ( $site_id ) {

            $response['response'] = 'Successfully updated site';
            $response['site_id']  = $site_id;

            // add in ACF fields
            update_field( 'customer', $site->customer["customer_id"], $site_id );
            update_field( 'partner', array_column( $site->shared_with, 'customer_id' ), $site_id );
            update_field( 'provider', $site->provider, $site_id );
            update_field( 'key', $site->key, $site_id );

            // update_field( 'status', 'active', $site_id );
            if ( get_field( 'launch_date', $site_id ) == '' ) {
                // No date was entered for Launch Date, assign to today.
                update_field( 'launch_date', date( 'Ymd' ), $site_id );
            }

            // Fetch relating environments
            $db_environments = new Environments();

            $environment = array(
                'address'                 => $site->environments[0]['address'],
                'username'                => $site->environments[0]['username'],
                'password'                => $site->environments[0]['password'],
                'protocol'                => $site->environments[0]['protocol'],
                'port'                    => $site->environments[0]['port'],
                'home_directory'          => $site->environments[0]['home_directory'],
                'database_username'       => $site->environments[0]['database_username'],
                'database_password'       => $site->environments[0]['database_password'],
                'offload_enabled'         => $site->environments[0]['offload_enabled'],
                'offload_access_key'      => $site->environments[0]['offload_access_key'],
                'offload_secret_key'      => $site->environments[0]['offload_secret_key'],
                'offload_bucket'          => $site->environments[0]['offload_bucket'],
                'offload_path'            => $site->environments[0]['offload_path'],
                'updates_enabled'         => $site->environments[0]['updates_enabled'],
                'updates_exclude_plugins' => $site->environments[0]['updates_exclude_plugins'],
                'updates_exclude_themes'  => $site->environments[0]['updates_exclude_themes'],
            );

            $environment_id = get_field( 'environment_production_id', $site_id );
            $db_environments->update( $environment, [ 'environment_id' => $environment_id ] );

            $environment = [
                'address'                 => $site->environments[1]['address'],
                'username'                => $site->environments[1]['username'],
                'password'                => $site->environments[1]['password'],
                'protocol'                => $site->environments[1]['protocol'],
                'port'                    => $site->environments[1]['port'],
                'home_directory'          => $site->environments[1]['home_directory'],
                'database_username'       => $site->environments[1]['database_username'],
                'database_password'       => $site->environments[1]['database_password'],
                'offload_enabled'         => $site->environments[1]['offload_enabled'],
                'offload_access_key'      => $site->environments[1]['offload_access_key'],
                'offload_secret_key'      => $site->environments[1]['offload_secret_key'],
                'offload_bucket'          => $site->environments[1]['offload_bucket'],
                'offload_path'            => $site->environments[1]['offload_path'],
                'updates_enabled'         => $site->environments[1]['updates_enabled'],
                'updates_exclude_plugins' => $site->environments[1]['updates_exclude_plugins'],
                'updates_exclude_themes'  => $site->environments[1]['updates_exclude_themes'],
            ];

            $environment_id = get_field( 'environment_staging_id', $site_id );
            $db_environments->update( $environment, [ 'environment_id' => $environment_id ] );

        }

        return $response;
    }

    public function delete() {

        // Remove environments attached to site
        // $db_environments = new Environments();
        // $environment_id  = get_field( 'environment_production_id', $site_id );
        // $db_environments->delete( $environment_id );
        // $environment_id = get_field( 'environment_staging_id', $site_id );
        // $db_environments->delete( $environment_id );

        // Mark site removed
        update_field( 'closed_date', date( 'Ymd' ), $this->site_id );
        update_field( 'status', 'closed', $this->site_id );

    }

}