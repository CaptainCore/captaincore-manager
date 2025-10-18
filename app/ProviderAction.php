<?php

namespace CaptainCore;

class ProviderAction {

    protected $provider_action_id = "";

    public function __construct( $provider_action_id = "" ) {
        $this->provider_action_id = $provider_action_id;
    }

    public function check() {
        $user_id = get_current_user_id();
        $actions = ProviderActions::where( [ "status" => "started", "user_id" => $user_id ] );
        foreach( $actions as $provider_action ) {
            $provider = Providers::get( $provider_action->provider_id );
            $action   = json_decode( $provider_action->action );
            $class_name = "\CaptainCore\Providers\\" . ucfirst( $provider->provider );
            if ( $action->command == "deploy-to-staging" || $action->command == "deploy-to-production" || $action->command == "new-site" ) {
                if ( $action->command == "new-site" && ! empty( $action->intial_response->message ) && $action->intial_response->message == "Too many requests, please try again later." && empty( $provider_action->provider_key )) {
                    $site        = $action;
                    $user        = ( new \CaptainCore\User )->profile();
                    $token       = $class_name::credentials("token");
                    $company_id  = $class_name::credentials("company_id");
                    $username    = $class_name::credentials("username");

                    if ( ! empty( $site->provider_id ) ) {
                        $api_key     = $class_name::credentials("api", $site->provider_id);
                        $company_id  = $class_name::credentials("company_id", $site->provider_id);
                        $username    = $class_name::credentials("username", $site->provider_id);
                        \CaptainCore\Remote\Kinsta::setApiKey( $api_key );
                    }
                    $new_site    = [
                        "company"                => $company_id,
                        "display_name"           => $site->name,
                        "region"                 => $site->datacenter,
                        "is_subdomain_multisite" => false,
                        "install_mode"           => "new",
                        "admin_email"            => get_option( 'admin_email' ),
                        "admin_password"         => substr ( str_shuffle( "0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ" ), 0, 16 ),
                        "admin_user"             => $username,
                        "is_multisite"           => false,
                        "site_title"             => $site->name,
                        "woocommerce"            => false,
                        "wordpressseo"           => false,
                        "wp_language"            => "en_US"
                    ];
                    $response      = \CaptainCore\Remote\Kinsta::post( "sites", $new_site );
                    ProviderActions::update( [ "provider_key" => $response->operation_id ], [ "provider_action_id" => $provider_action->provider_action_id ] );
                    continue;
                }
                $api = \CaptainCore\Providers\Kinsta::credentials("api");
                \CaptainCore\Remote\Kinsta::setApiKey( $api );
                if ( ! empty( $action->provider_id ) ) {
                    $api = \CaptainCore\Providers\Kinsta::credentials("api", $provider_action->provider_id);
                    \CaptainCore\Remote\Kinsta::setApiKey( $api );
                }
                $response         = \CaptainCore\Remote\Kinsta::get( "operations/{$provider_action->provider_key}" );
                $action->response = $response;
                $status           = $response->status;
            }
            if ( empty( $status ) ) {
                $status = $class_name::action_check( $action->provider_action_id );
            }
            if ( $status == "200" ) {
                ProviderActions::update( [ "status" => "waiting" ], [ "provider_action_id" => $provider_action->provider_action_id ] );
                continue;
            }
            if ( empty ( $action->attempts ) ) {
                $action->attempts = 1;
                ProviderActions::update( [ "action" => json_encode( $action ) ], [ "provider_action_id" => $provider_action->provider_action_id ] );
                continue;
            }
            if ( $status == "404" || $status == "500" ) {
                $action->attempts++;
                $update = [
                    "action" => json_encode( $action ),
                ];
                if ( $action->attempts >= 10 ) {
                    $update["status"] = "failed";
                }
                ProviderActions::update( $update, [ "provider_action_id" => $provider_action->provider_action_id ] );
            }
        }
        return self::active();
    }

    public function active() {
        $user_id = get_current_user_id();
        $actions = ( new ProviderActions )->where( [ "status" => [ "'waiting'", "'started'" ], "user_id" => $user_id ] );
        foreach( $actions as $action ) {
            if ( ! empty( $action->action ) ) {
                $action->action = json_decode( $action->action );
            }
        }
        return $actions;
    }

    public function all() {
        $actions = ( new ProviderActions )->mine();
        foreach( $actions as $action ) {
            if ( ! empty( $action->action ) ) {
                $action->action = json_decode( $action->action );
            }
        }
        return $actions;
    }

    public function run() {
        $user_id         = get_current_user_id();
        $provider_action = ( new ProviderActions )->get( $this->provider_action_id );
        if ( $user_id != $provider_action->user_id ) {
            return;
        }
    
        $provider       = Providers::get( $provider_action->provider_id );
        $class_name     = "\CaptainCore\Providers\\" . ucfirst( $provider->provider );
        $current_action = json_decode( $provider_action->action );
        $time_now       = date( 'Y-m-d H:i:s' );

        if ( $current_action->command == "new-site" && ! empty( $current_action->step ) && $provider->provider == "kinsta" ) {

            // Check if the "Disable Edge Caching" step just finished
            if ( $current_action->step == "disable_edge_caching" ) {
                
                // Now, call the "Set Image Optimization" endpoint
                $response = \CaptainCore\Remote\Kinsta::put( 
                    "sites/cdn/image-optimization", 
                    [ 
                        "environment_id" => $current_action->live_environment_id, 
                        "image_optimization_type" => "lossless" 
                    ] 
                );

                if ( ! empty( $response->operation_id ) ) {
                    // Update the action to track the *next* operation
                    $current_action->step = "set_image_optimization";
                    $action = [
                        'provider_key' => $response->operation_id,
                        'action'       => json_encode($current_action),
                        'updated_at'   => $time_now,
                        'status'       => "started", // Keep it "started" to be checked again
                    ];
                    ( new ProviderActions )->update( $action, [ "provider_action_id" => $this->provider_action_id ] );
                    return self::active();
                }
                // If API call failed, just proceed to sync
            }

            // Check if the "Set Image Optimization" step just finished
            if ( $current_action->step == "set_image_optimization" ) {
                // This was the last API step, now run the background sync
                \CaptainCore\Run::CLI("site sync {$current_action->site_id} --update-extras", true);
                
                // Now we can finally mark the whole chain as "done"
                $action = [
                    'action'     => json_encode($current_action), // Save the final state
                    'updated_at' => $time_now,
                    'status'     => "done",
                ];
                ( new ProviderActions )->update( $action, [ "provider_action_id" => $this->provider_action_id ] );
                return self::active();
            }
        }

        if ( $current_action->command == "deploy-to-production" ) {
            $action = [
                'action'     => json_encode ( $current_action ),
                'updated_at' => $time_now,
                'status'     => "done",
            ];
            ( new ProviderActions )->update( $action, [ "provider_action_id" => $this->provider_action_id ] );
            return self::active();
        }

        if ( $current_action->command == "deploy-to-staging" ) {
            // Manual snapshot of production environment completed, start restore process
            if ( $current_action->step == 1 ) {
                $api_key  = $class_name::credentials("api");
                $user_id  = $class_name::credentials("user_id");
                $response = \CaptainCore\Remote\Kinsta::get( "sites/environments/{$current_action->environment_production_id}/backups" );

                foreach( $response->environment->backups as $backup ) {
                    if ( $backup->type == "manual" ) {
                        $data = [
                            "backup_id"        => $backup->id,
                            "notified_user_id" => $user_id
                        ];
                        $response = \CaptainCore\Remote\Kinsta::post( "sites/environments/{$current_action->environment_staging_id}/backups/restore", $data );
                        break;
                    }
                }
                if ( empty( $response->operation_id ) ) {
                    return false;
                }
        
                $current_action->step = 2;
                $action   = [
                    'provider_key' => $response->operation_id,
                    'action'       => json_encode ( $current_action ),
                    'updated_at'   => $time_now,
                    'status'       => "started",
                ];
                ( new ProviderActions )->update( $action, [ "provider_action_id" => $this->provider_action_id ] );
                return self::active();
            }
            
            // Resync staging info if needed
            if ( $current_action->step == 2 ) {
                if ( empty( $current_action->environment_staging_id ) || ! empty( $current_action->connect_staging ) ) {
                    \CaptainCore\Providers\Kinsta::connect_staging( $current_action->site_id );
                }
                $current_action->step = 3;
                $action   = [
                    'action'     => json_encode ( $current_action ),
                    'updated_at' => $time_now,
                    'status'     => "done",
                ];
                ( new ProviderActions )->update( $action, [ "provider_action_id" => $this->provider_action_id ] );
                return self::active();
            }
        }

        if ( $current_action->command == "new-site" ) {
            if ( ! empty( $current_action->provider_id ) ) {
                $api_key = \CaptainCore\Providers\Kinsta::credentials("api", $current_action->provider_id);
                \CaptainCore\Remote\Kinsta::setApiKey( $api_key );
            }
            $result      = empty( $current_action->result ) ? \CaptainCore\Remote\Kinsta::get( "operations/{$provider_action->provider_key}" )->data : $current_action->result;
            $verify      = \CaptainCore\Providers\Kinsta::verify();
            $current_action->result = $result;
            $site_name   = $current_action->name;
            if ( ! empty( $current_action ) ) {
                $site        = \CaptainCore\Remote\Kinsta::get( "sites/{$result->idSite}" )->site;
                $environment = \CaptainCore\Remote\Kinsta::get( "sites/{$result->idSite}/environments" )->site->environments[0];
                $live_environment_id = $environment->id; // Get the environment ID
                $password    = $verify ? \CaptainCore\Providers\Kinsta::fetch_sftp_password( $result->idSite ) : "";

                // Check for CaptainCore site name conflicts and loop until a unique name is found
                $original_kinsta_name = $site->name;
                
                while ( count( ( new Sites )->where( [ "site" => $site->name ] ) ) > 0 ) {
                    // If a conflict exists, append a random 5-character string and check again
                    $random_suffix = substr( str_shuffle ( str_repeat( "0123456789abcdefghijklmnopqrstuvwxyz", 5 ) ), 0, 1 );
                    $site->name     = "{$original_kinsta_name}{$random_suffix}";
                }

                if ( empty ( $current_action->domain ) ) {
                    // Use the *final* unique name for the .kinsta.cloud domain
                    $current_action->domain = "{$site->name}.kinsta.cloud";
                }
                
                $current_action->shared_with = array_column( $current_action->shared_with, "account_id" );
                $address        = $environment->ssh_connection->ssh_ip->external_ip;
                $port           = $environment->ssh_connection->ssh_port;
                $home_directory = \CaptainCore\Run::CLI( "ssh-detect $site->name $address $port" );

                $response = ( new Site )->create( [
                    "name"             => $current_action->domain,
                    "site"             => $site->name,
                    "key"              => "",
                    "shared_with"      => $current_action->shared_with,
                    "account_id"       => $current_action->account_id,
                    "customer_id"      => empty( $current_action->customer_id ) ? "" : $current_action->customer_id,
                    "verify"           => $verify,
                    "provider"         => "kinsta",
                    "provider_id"      => $current_action->provider_id,
                    "provider_site_id" => $result->idSite,
                    "environments" => [
                        [
                            "address"           => $address,
                            "username"          => $site->name,
                            "password"          => $password,
                            "protocol"          => "sftp",
                            "port"              => $port,
                            "home_directory"    => $home_directory,
                            "database_username" => $site->name,
                            "environment"       => "Production",
                            "monitor_enabled"   => "1",
                            "updates_enabled"   => "1"
                        ]
                    ],
                ] );
    
                $site_id = $response["site_id"];
                $account = ( new Account ( $current_action->account_id, true ) );
                $account->calculate_totals();
    
                $account_ids = ( new Site( $site_id ) )->shared_with_ids();
                if ( $verify ) {
                    foreach( $account_ids as $account_id ) {
                        // Shared MyKinsta access if needed
                        $account       = ( new Account( $account_id, true ) );
                        $kinsta_emails = empty( $account->get()->defaults->kinsta_emails ) ? "" : $account->get()->defaults->kinsta_emails;
                        if ( ! empty( $kinsta_emails ) ) {
                            $kinsta_emails = array_map( 'trim', explode( ",", $kinsta_emails ) );
                            \CaptainCore\Providers\Kinsta::invite_emails( $kinsta_emails, $result->idSite );
                        }
                    }
                }
                \CaptainCore\ProcessLog::insert( "Created site", $site_id );
                
                if ($provider->provider == "kinsta") {
                    $response = \CaptainCore\Remote\Kinsta::put( 
                        "sites/edge-caching/status", 
                        [ 
                            "environment_id" => $live_environment_id, 
                            "enabled" => false 
                        ] 
                    );

                    if ( ! empty( $response->operation_id ) ) {
                        // Update the ProviderAction to track this new operation
                        $current_action->step = "disable_edge_caching";
                        $current_action->live_environment_id = $live_environment_id; // Pass env ID
                        $current_action->site_id = $site_id; // Pass CaptainCore site ID
                        
                        $action = [
                            'provider_key' => $response->operation_id, // New operation to track
                            'action'       => json_encode($current_action),
                            'updated_at'   => $time_now,
                            'status'       => "started", // Set back to "started"
                        ];
                        ( new ProviderActions )->update( $action, [ "provider_action_id" => $this->provider_action_id ] );
                        return self::active(); // Exit, so it gets checked again
                    } else {
                        // Kinsta API call failed, just run sync and finish
                        \CaptainCore\Run::CLI("site sync $site_id --update-extras", true);
                    }
                } else {
                    // Not Kinsta, just run sync and finish
                    \CaptainCore\Run::CLI("site sync $site_id --update-extras", true);
                }
            }
        }

        $action   = [
            'action'     => json_encode ( $current_action ),
            'updated_at' => $time_now,
            'status'     => "done",
        ];
        ( new ProviderActions )->update( $action, [ "provider_action_id" => $this->provider_action_id ] );

        return self::active();

    }

}