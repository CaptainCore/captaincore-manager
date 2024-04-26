<?php

namespace CaptainCore;

class ProviderAction {

    protected $provider_action_id = "";

    public function __construct( $provider_action_id = "" ) {
        $this->provider_action_id = $provider_action_id;
    }

    public function check() {
        $user_id = get_current_user_id();
        $actions = ( new ProviderActions )->where( [ "status" => "started", "user_id" => $user_id ] );
        foreach( $actions as $action ) {
            $provider   = ( new Providers )->get( $action->provider_id );
            $class_name = "\CaptainCore\Providers\\" . ucfirst( $provider->provider );
            $isDone     = $class_name::action_check( $action->provider_action_id );
            if ( $isDone == true ) {
                ( new ProviderActions )->update( [ "status" => "waiting" ], [ "provider_action_id" => $action->provider_action_id ] );
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
        $provider        = ( new Providers )->get( $provider_action->provider_id );
        $class_name      = "\CaptainCore\Providers\\" . ucfirst( $provider->provider );
        $current_action  = json_decode( $provider_action->action );
        $time_now        = date( 'Y-m-d H:i:s' );

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
            $result          = $class_name::action_result( $action->provider_key );
            // Save Kinsta result from background activity
            $current_action->result = $result;
            if ( ! empty( $current_action ) ) {
                // Generate new site. Kinsta site ID located within $result->idSite
    
                $site     = \CaptainCore\Providers\Kinsta::fetch_site_details( $result->idSite );
                $password = \CaptainCore\Providers\Kinsta::fetch_sftp_password( $result->idSite );
    
                // Should check for name colisions and autogenerate ending of name 
                $site_check = ( new Sites )->where( [ "site" => $site->usr ] );
                if ( count( $site_check ) > 0 ) {
                    $random_ending = substr( str_shuffle ( str_repeat( "0123456789abcdefghijklmnopqrstuvwxyz", 5 ) ), 0, 5 );
                    $site->name    = "{$site->name}_$random_ending";
                }
    
                if ( empty ( $current_action->domain ) ) {
                    $current_action->domain = "{$site->usr}.kinsta.cloud";
                }
                $current_action->shared_with = array_column( $current_action->shared_with, "account_id" );
    
                $response = ( new Site )->create( [
                    "name"         => $current_action->domain,
                    "site"         => $site->name,
                    "key"          => "",
                    "remote_key"   => $result->idSite,
                    "shared_with"  => $current_action->shared_with,
                    "account_id"   => $current_action->account_id,
                    "customer_id"  => empty( $current_action->customer_id ) ? "" : $current_action->customer_id,
                    'provider'     => "kinsta",
                    "environments" => [
                        [
                            "address"           => $site->environment->activeContainer->loadBalancer->extIP,
                            "username"          => $site->usr,
                            "password"          => $password,
                            "protocol"          => "sftp",
                            "port"              => $site->environment->activeContainer->lxdSshPort,
                            "home_directory"    => "/www/{$site->path}/public",
                            "database_username" => $site->dbName,
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
                foreach( $account_ids as $account_id ) {
                    // Shared MyKinsta access if needed
                    $account       = ( new Account( $account_id, true ) );
                    $kinsta_emails = empty( $account->get()->defaults->kinsta_emails ) ? "" : $account->get()->defaults->kinsta_emails;
                    if ( ! empty( $kinsta_emails ) ) {
                        $kinsta_emails = array_map( 'trim', explode( ",", $kinsta_emails ) );
                        \CaptainCore\Providers\Kinsta::invite_emails( $kinsta_emails, $result->idSite );
                    }
                }
    
                captaincore_run_background_command( "site sync $site_id --update-extras" );
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