<?php

namespace CaptainCore;

class ProviderAction {

    protected $provider_action_id = "";

    public function __construct( $provider_action_id = "" ) {
        $this->provider_action_id = $provider_action_id;
    }

    public function check() {
        $actions = ( new ProviderActions )->where( [ "status" => "started" ] );
        foreach( $actions as $action ) {
            $provider   = ( new Providers )->get( $action->provider_id );
            $class_name = "\CaptainCore\Providers\\" . ucfirst( $provider->provider );
            $isDone     = $class_name::action_check( $action->provider_key );
            if ( $isDone == true ) {
                ( new ProviderActions )->update( [ "status" => "waiting" ], [ "provider_action_id" => $action->provider_action_id ] );
            }
        }
        return self::active();
    }

    public function active() {
        $actions = ( new ProviderActions )->where( [ "status" => [ "'waiting'", "'started'" ] ] );
        foreach( $actions as $action ) {
            if ( ! empty( $action->action ) ) {
                $action->action = json_decode( $action->action );
            }
        }
        return $actions;
    }

    public function all() {
        $actions = ( new ProviderActions )->all();
        foreach( $actions as $action ) {
            if ( ! empty( $action->action ) ) {
                $action->action = json_decode( $action->action );
            }
        }
        return $actions;
    }

    public function run() {
        $action     = ( new ProviderActions )->get( $this->provider_action_id );
        $provider   = ( new Providers )->get( $action->provider_id );
        $class_name = "\CaptainCore\Providers\\" . ucfirst( $provider->provider );
        $result     = $class_name::action_result( $action->provider_key );
        
        // Save Kinsta result from background activity
        $current_action = json_decode ( $action->action );
        $current_action->result = $result;

        $time_now = date( 'Y-m-d H:i:s' );
        $action   = [
            'action'     => json_encode ( $current_action ),
            'updated_at' => $time_now,
            'status'     => "done",
        ];
        ( new ProviderActions )->update( $action, [ "provider_action_id" => $this->provider_action_id ] );

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
                        "environment"       => "Production"
                    ]
                ],
            ] );

            $site_id = $response["site_id"];
            captaincore_run_background_command( "site sync $site_id --update-extras" );
        }

        return self::active();

    }

}