<?php 

namespace CaptainCore\Providers;

class Kinsta {

    public static function credentials( $record = "", $provider_id = "" ) {
        $credentials = ( new \CaptainCore\Provider( "kinsta" ) )->credentials();
        if ( ! empty( $provider_id ) ) {
            $provider    = \CaptainCore\Providers::get( $provider_id );
            $credentials = ! empty( $provider->credentials ) ? json_decode( $provider->credentials ) : [];
        }
        if ( $record == "" ) {
            return $credentials;
        }
        foreach( $credentials as $credential ) {
            if ( $credential->name == $record ) {
                return $credential->value;
            }
        }
    }

    public static function list() {
        $providers = [];
        $user      = (object) ( new \CaptainCore\User )->fetch();
        if ( ( new \CaptainCore\User )->is_admin() ) {
            $providers = \CaptainCore\Providers::where( ["provider" => "kinsta" ] );
        }
        if ( empty( $providers ) ) {
            $providers_admin = \CaptainCore\Providers::where( [ "provider" => "kinsta", "user_id" => "0" ] );
            $providers_user  = \CaptainCore\Providers::where( [ "provider" => "kinsta", "user_id" => $user->user_id ] );
            $providers       = array_merge($providers_admin, $providers_user);
        }
        $filteredProviders = array_map(function($provider) {
            $name = $provider->name . " " . $provider->provider;
            return [
                'provider_id' => $provider->provider_id,
                'provider'    => $provider->provider,
                'name'        => $provider->name
            ];
        }, $providers);

        return $filteredProviders;
    }

    public static function list_sites( $record = "", $provider_id = "" ) {
        $providers = self::list();
        $providers_with_sites = [];
        foreach( $providers as $provider ) {
            if ( $provider['provider_id'] == 1 ) {
                continue;
            }
            $api_key = self::credentials("api", $provider['provider_id']);
            \CaptainCore\Remote\Kinsta::setApiKey( $api_key );
            $response = \CaptainCore\Remote\Kinsta::get( "sites?company=". self::credentials("company_id", $provider['provider_id']) );
            $sites = ! empty( $response->company->sites ) ? $response->company->sites : [];
            $providers_with_sites[$provider['provider_id']] = $sites;
        }
        return $providers_with_sites;
    }

    public static function fetch_remote_sites( $provider_id = "" ) {
        $api_key    = self::credentials( "api", $provider_id );
        $company_id = self::credentials( "company_id", $provider_id );

        if ( empty( $api_key ) || empty( $company_id ) ) {
            return [];
        }

        \CaptainCore\Remote\Kinsta::setApiKey( $api_key );
        $response = \CaptainCore\Remote\Kinsta::get( "sites?company={$company_id}" );

        if ( empty( $response->company->sites ) ) {
            return [];
        }

        $sites = [];
        foreach ( $response->company->sites as $kinsta_site ) {
            $sites[] = [
                'remote_id' => $kinsta_site->id,
                'name'      => $kinsta_site->display_name,
                'label'     => $kinsta_site->display_name,
                'status'    => $kinsta_site->status ?? 'active',
            ];
        }
        return $sites;
    }

    public static function update_token( $token = "", $provider_id = "" ) {
        if ( ! empty( $provider_id ) ) {
            $provider = \CaptainCore\Providers::get( $provider_id );
        } else {
            $provider = ( new \CaptainCore\Provider( "kinsta" ) )->get();
        }
        $credentials = self::credentials( "", $provider_id );
        foreach( $credentials as $credential ) {
            if ( $credential->name == "token" ) {
                $credential->value = $token;
            }
        }
        ( new \CaptainCore\Providers )->update( [ "credentials" => json_encode( $credentials ) ], [ "provider_id" => $provider->provider_id ] );
        return self::verify( $provider_id );
    }

    public static function verify( $provider_id = "" ) {
        $token = self::credentials( "token", $provider_id );
        $data = [ 
            'timeout' => 45,
            'headers' => [
                'Content-Type' => 'application/json',
                'X-Token'      => "$token",
            ],
            'body'        => json_encode( [
                "variables" => [ 
                    "lang" => "en"
                ],
                "operationName" => "KinstaBlog",
                "query"         => 'query KinstaBlog($lang: String!) {
                    kinstablog(lang: $lang) {
                      link
                      title
                      authorName
                      featuredImage
                      date
                      __typename
                    }
                  }'
            ] )
        ];

        $response = wp_remote_post( "https://graphql-router.kinsta.com", $data );

        if ( is_wp_error( $response ) ) {
            return false;
        }

        $response = json_decode( $response['body'] );

        if ( ! empty ( $response->errors ) ) {
            return false;
        }

        if ( empty ( $response->data->kinstablog ) ) {
            return false;
        }
        
        return true;
    }

    public static function new_site( $site ) {
        $user        = ( new \CaptainCore\User )->profile();
        $token       = self::credentials("token");
        $company_id  = self::credentials("company_id");
        $username    = self::credentials("username");

        if ( ! empty( $site->provider_id ) ) {
            $api_key     = self::credentials("api", $site->provider_id);
            $company_id  = self::credentials("company_id", $site->provider_id);
            $username    = self::credentials("username", $site->provider_id);
            \CaptainCore\Remote\Kinsta::setApiKey( $api_key );
        }

        $site        = (object) $site;

        if ( ! empty( $site->clone_site_id ) ) {
            $environments = \CaptainCore\Remote\Kinsta::get( "sites/{$site->clone_site_id}/environments" );
            foreach( $environments->site->environments as $environment ) {
                if ( $environment->name == "live" ) {
                    $environment_id = $environment->id;
                }
            }
            $environment  = $environments->site->environments[0]->id;
            $new_site = [
                "company"                => $company_id,
                "display_name"           => $site->name,
                "source_env_id"          => $environment_id,
            ];
            $response      = \CaptainCore\Remote\Kinsta::post( "sites/clone", $new_site );
            $site->command = "new-site";
            $site->intial_response = $response;
            $site->message = "Creating site $site->name at Kinsta via site clone";
    
            self::add_action( $response->operation_id, $site );
            return $response->operation_id;
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
        $site->command = "new-site";
        $site->intial_response = $response;
        $site->message = "Creating site $site->name at Kinsta datacenter $site->datacenter";

        self::add_action( $response->operation_id, $site );
        return $response->operation_id;

    }

    public static function environments( $kinsta_id ) {
        $response = \CaptainCore\Remote\Kinsta::get( "sites/$kinsta_id/environments" );
        return $response->site->environments;
    }

    public static function connect_staging( $site_id ) {
        $site         = ( new \CaptainCore\Sites )->get( $site_id );
        $environments = ( new \CaptainCore\Environments )->where( [ "site_id" => $site_id ] );
        if ( empty( $site->provider_site_id ) ) {
            return;
        }
        foreach( $environments as $environment ) {
            if ( $environment->environment == "Production" ) {
                $production_environment = $environment;
            }
        }
        $time_now        = date("Y-m-d H:i:s");
        $response = \CaptainCore\Remote\Kinsta::get( "sites/{$site->provider_site_id}/environments" );

        foreach( $response->site->environments as $kinsta_environment ) {
            if ( $kinsta_environment->name == "staging" ) {
                $new_environment = [
                    'site_id'                 => $site_id,
                    'created_at'              => $time_now,
                    'updated_at'              => $time_now,
                    'environment'             => "Staging",
                    'address'                 => $kinsta_environment->ssh_connection->ssh_ip->external_ip,
                    'username'                => $production_environment->username,
                    'password'                => $production_environment->password,
                    'protocol'                => $production_environment->protocol,
                    'port'                    => $kinsta_environment->ssh_connection->ssh_port,
                    'home_directory'          => $production_environment->home_directory,
                    'database_username'       => $production_environment->database_username,
                    'database_password'       => "",
                    'offload_enabled'         => "",
                    'offload_access_key'      => "",
                    'offload_secret_key'      => "",
                    'offload_bucket'          => "",
                    'offload_path'            => "",
                    'monitor_enabled'         => 0,
                    'updates_enabled'         => 1,
                    'updates_exclude_plugins' => "",
                    'updates_exclude_themes'  => "",
                ];
                ( new \CaptainCore\Environments )->insert( $new_environment );
                \CaptainCore\Run::CLI("site sync $site_id");
            }
        }
    }

    public static function sync_environments( $site_id ) {
        $site         = ( new \CaptainCore\Sites )->get( $site_id );
        $environments = ( new \CaptainCore\Environments )->where( [ "site_id" => $site_id ] );
        if ( empty( $site->provider_site_id ) ) {
            return;
        }
        foreach( $environments as $environment ) {
            if ( $environment->environment == "Production" ) {
                $production_environment = $environment;
            }
        }
        $response = \CaptainCore\Remote\Kinsta::get( "sites/{$site->provider_site_id}/environments" );

        foreach( $response->site->environments as $kinsta_environment ) {
            if ( $kinsta_environment->name == "live" ) {
                foreach( $environments as $environment ) {
                    if ( $environment->environment == "Production" ) {
                        ( new \CaptainCore\Environments )->update( [ 
                            "address" => $kinsta_environment->ssh_connection->ssh_ip->external_ip,
                            "port"    => $kinsta_environment->ssh_connection->ssh_port,
                        ], [ "environment_id" => $environment->environment_id ] );
                    }
                }
            }
            if ( $kinsta_environment->name == "staging" ) {
                foreach( $environments as $environment ) {
                    if ( $environment->environment == "Staging" ) {
                        ( new \CaptainCore\Environments )->update( [
                            "address"        => $kinsta_environment->ssh_connection->ssh_ip->external_ip,
                            "port"           => $kinsta_environment->ssh_connection->ssh_port,
                            "username"       => $production_environment->username,
                            "password"       => $production_environment->password,
                            "protocol"       => $production_environment->protocol,
                            "home_directory" => $production_environment->home_directory,
                        ], [ "environment_id" => $environment->environment_id ]);
                    }
                }
            }
        }
    }

    public static function create_staging_and_deploy( $site_id ) {
        $site         = ( new \CaptainCore\Sites )->get( $site_id );
        if ( empty( $site->provider_site_id ) ) {
            return;
        }
        $environments              = self::environments( $site->provider_site_id );
        $environment_production_id = "";
        foreach( $environments as $environment ) {
            if ( $environment->name == "live" ) {
                $environment_production_id = $environment->id;
            }
        }
        $data = [ 
            "display_name"  => "Staging",
            "is_premium"    => false,
            "source_env_id" => $environment_production_id
        ];
        $response = \CaptainCore\Remote\Kinsta::post( "sites/{$site->provider_site_id}/environments/clone", $data );

        $action = (object) [
            "command"                   => "deploy-to-staging",
            "step"                      => 2,
            "message"                   => "Deploying $site->name to staging environment",
            "name"                      => $site->name,
            "site_id"                   => $site_id,
            "kinsta_site_id"            => $site->provider_site_id,
            "environment_production_id" => $environment_production_id,
        ];

        self::add_action( $response->operation_id, $action );
        return $response->operation_id;
    }

    public static function deploy_to_staging( $site_id ) {
        $site         = ( new \CaptainCore\Sites )->get( $site_id );
        if ( empty( $site->provider_site_id ) ) {
            return;
        }
        $environments = self::environments( $site->provider_site_id );
        $api_key      = self::credentials("api");
        $data         = [
            "tag" => "Deploy to staging from API"
        ];
        $environment_production_id = "";
        $environment_staging_id    = "";
        foreach( $environments as $environment ) {
            if ( $environment->name == "live" ) {
                $environment_production_id = $environment->id;
            }
            if ( $environment->name == "staging" ) {
                $environment_staging_id = $environment->id;
            }
        }
        // If no staging then create that
        if ( empty( $environment_staging_id ) ) {
            return self::create_staging_and_deploy( $site_id );
        }
        $response = \CaptainCore\Remote\Kinsta::post( "sites/environments/$environment_production_id/manual-backups", $data );

        if ( empty ( $response->operation_id ) ) {
            return false;
        }

        $connect_staging = false;
        $environments    = ( new \CaptainCore\Site( $site_id, true ) )->environments();
        if ( count( $environments ) == 1 ) {
            $connect_staging = true;
        }

        $action = (object) [
            "command"                   => "deploy-to-staging",
            "step"                      => 1,
            "message"                   => "Deploying $site->name to staging environment",
            "name"                      => $site->name,
            "site_id"                   => $site_id,
            "connect_staging"           => $connect_staging,
            "kinsta_site_id"            => $site->provider_site_id,
            "environment_production_id" => $environment_production_id,
            "environment_staging_id"    => $environment_staging_id,
        ];

        self::add_action( $response->operation_id, $action );
        return $response->operation_id;
    }

    public static function deploy_to_production( $site_id ) {
        $site         = ( new \CaptainCore\Sites )->get( $site_id );
        if ( empty( $site->provider_site_id ) ) {
            return;
        }
        $environments = self::environments( $site->provider_site_id );
        $api_key      = self::credentials("api");

        $environment_production_id = "";
        $environment_staging_id    = "";
        foreach( $environments as $environment ) {
            if ( $environment->name == "live" ) {
                $environment_production_id = $environment->id;
            }
            if ( $environment->name == "staging" ) {
                $environment_staging_id = $environment->id;
            }
        }
        $data = [
            "source_env_id"          => $environment_staging_id,
            "target_env_id"          => $environment_production_id,
            "push_db"                => true,
            "push_files"             => true,
            "run_search_and_replace" => true
        ];
        $response = \CaptainCore\Remote\Kinsta::put( "sites/{$site->provider_site_id}/environments", $data );

        if ( empty ( $response->operation_id ) ) {
            return false;
        }

        $name          = "";
        $environments  = ( new \CaptainCore\Site( $site_id, true ) )->environments();
        foreach( $environments as $environment ) {
            if ( $environment->environment == "Staging") {
                $name = $environment->home_url;
            }
        }

        $action = (object) [
            "command"                   => "deploy-to-production",
            "message"                   => "Deploying $name to production environment",
            "name"                      => $name,
            "site_id"                   => $site_id,
            "kinsta_site_id"            => $site->provider_site_id,
        ];

        self::add_action( $response->operation_id, $action );
        return $response->operation_id;

    }

    public static function provider_sync() {
        $data = [
            'timeout' => 45,
            'headers' => [
                'Content-Type' => 'application/json',
                'X-Token'      => self::credentials("token")
            ],
            'body' => json_encode( [
                "variables" => [
                    "idCompany" => self::credentials("company_id")
                ],
                "query"         => 'query ExportSites($idCompany: String!) {
                    company(id: $idCompany) {
                      id
                      name
                      sites {
                        id
                        name
                        path
                        createdAt
                        environment(name: "live") {
                          id
                                  primaryDomain {
                                      name
                                  }
                        }
                      }
                    }
                  }'
            ] )
        ];

        $response     = wp_remote_post( "https://graphql-router.kinsta.com", $data );
        $response     = json_decode( $response['body'] );
        $company_id   = self::credentials("company_id");
        $sites        = ( new \CaptainCore\Sites )->where( [ "provider" => "kinsta" ] );
        foreach( $sites as $site ) {
            foreach( $response->data->company->sites as $kinsta_site ) {
                $site_name = $kinsta_site->environment->primaryDomain->name;
                $site_name = str_replace( "www.", "", $site_name );
                if ( $site->name == $site_name ) {
                    ( new  \CaptainCore\Sites )->update( [ "provider_site_id" => $kinsta_site->id ], [ "site_id" => $site->site_id ] );
                }
            }
        }
    }

    public static function actions() {
        $provider = ( new \CaptainCore\Provider( "kinsta" ) )->get();
        $details  = $provider->details;
        $details  = empty( $details ) ? (object) [ "actions" => [] ] : json_decode( $details );
       return $details->actions;
    }

    public static function add_action( $action_id = 0, $action = "" ) {

        $provider = ( new \CaptainCore\Provider( "kinsta" ) )->get();
        if ( ! empty( $action->provider_id ) ) {
            $provider = \CaptainCore\Providers::get( $action->provider_id );
        }
        $user_id  = ( new \CaptainCore\User )->user_id();
        $time_now = date("Y-m-d H:i:s");

        $provider_action = [
            "user_id"      => $user_id,
            "provider_id"  => $provider->provider_id,
            "provider_key" => $action_id,
            "status"       => "started",
            "action"       => json_encode( $action ),
            'created_at'   => $time_now,
            'updated_at'   => $time_now,
        ];

        $provider_action_id = ( new \CaptainCore\ProviderActions )->insert( $provider_action );
        return;
    }

    public static function action_check( $provider_action_id = 0, $return_response = false ) {
        $provider_action = ( new \CaptainCore\ProviderActions )->get( $provider_action_id );
        $action          = json_decode( $provider_action->action );
        $token           = self::credentials("token");
        $data            = [ 
            'timeout' => 45,
            'headers' => [
                'Content-Type' => 'application/json',
                'X-Token'      => "$token",
            ],
            'body'        => json_encode( [
                "variables" => [ 
                    "idAction" => (int) $provider_action->provider_key
                ],
                "operationName" => "Action",
                "query"         => 'query Action($idAction: Int!) {
                    action(id: $idAction) {
                        error
                        isDone
                        __typename
                    }
                action_liveKeys(id: $idAction)
            }'
            ] )
        ];

        $response = wp_remote_post( "https://graphql-router.kinsta.com", $data );

        if ( is_wp_error( $response ) ) {
            return 404;
        }

        $response = json_decode( $response['body'] );

        if ( ! empty ( $response->errors ) ) {
            return 404;
        }

        if ( empty ( $response->data->action ) ) {
            return 202;
        }

        if ( $return_response ) {
            return $response;
        }

        if ( $response->data->action->isDone ) {
            return 200;
        }
        
        return 202;

    }

    public static function fetch_sftp_password( $site_key = 0 ) {
        $password = self::credentials("password");
        $token    = self::credentials("token");
        $data     = [ 
            'timeout' => 45,
            'headers' => [
                'Content-Type' => 'application/json',
                'X-Token'      => "$token",
            ],
            'body'        => json_encode( [
                "variables" => [ 
                    "idSite"        => $site_key,
                ],
                "operationName" => "getHeaderSite",
                "query"         => 'fragment HeaderEnvironment on Environment {
                    id
                    name
                    displayName
                    isPremium
                    isBlocked
                    __typename
                  }
                  
                  query getHeaderSite($idSite: String!) {
                    site(id: $idSite) {
                      id
                      displayName
                      environments {
                        ...HeaderEnvironment
                        __typename
                      }
                      environments_liveKeys
                      __typename
                    }
                  }'                  
            ] )
        ];

        $response = wp_remote_post( "https://graphql-router.kinsta.com", $data );
        $response = json_decode( $response['body'] );

        foreach( $response->data->site->environments as $env ) {
            if ( $env->name == "live" ) {
                $environment = $env;
            }
        }

        $data  = [ 
            'timeout' => 45,
            'headers' => [
                'Content-Type' => 'application/json',
                'X-Token'      => "$token",
            ],
            'body'        => json_encode( [
                "variables" => [ 
                    "password"  => $password
                ],
                "operationName" => "PasswordToken",
                "query"         => 'query PasswordToken($password: String!) {
                    passwordToken(password: $password)
                  }'
            ] )
        ];

        $response = wp_remote_post( "https://graphql-router.kinsta.com", $data );
        $response = json_decode( $response['body'] );

        $data  = [ 
            'timeout' => 45,
            'headers' => [
                'Content-Type' => 'application/json',
                'X-Token'      => "$token",
            ],
            'body'        => json_encode( [
                "variables" => [ 
                    "idSite"        => $site_key,
                    "idEnv"         => $environment->id,
                    "passwordToken" => $response->data->passwordToken
                ],
                "operationName" => "SftpPassword",
                "query"         => 'query SftpPassword($idSite: String!, $idEnv: String!, $passwordToken: String) {
                    site(id: $idSite) {
                      id
                      environment(id: $idEnv) {
                        id
                        sftpPassword(passwordToken: $passwordToken)
                        __typename
                      }
                      __typename
                    }
                  }'                  
            ] )
        ];

        $response = wp_remote_post( "https://graphql-router.kinsta.com", $data );
        $response = json_decode( $response['body'] );

        return $response->data->site->environment->sftpPassword;

    }

    public static function fetch_site_details( $site_key = 0 ) {
        $token = self::credentials("token");
        $data  = [ 
            'timeout' => 45,
            'headers' => [
                'Content-Type' => 'application/json',
                'X-Token'      => "$token",
            ],
            'body'        => json_encode( [
                "variables" => [ 
                    "idSite"        => $site_key,
                ],
                "operationName" => "getHeaderSite",
                "query"         => 'fragment HeaderEnvironment on Environment {
                    id
                    name
                    displayName
                    isPremium
                    isBlocked
                    __typename
                  }
                  
                  query getHeaderSite($idSite: String!) {
                    site(id: $idSite) {
                      id
                      displayName
                      environments {
                        ...HeaderEnvironment
                        __typename
                      }
                      environments_liveKeys
                      __typename
                    }
                  }'                  
            ] )
        ];

        $response = wp_remote_post( "https://graphql-router.kinsta.com", $data );
        $response = json_decode( $response['body'] );

        foreach( $response->data->site->environments as $env ) {
            if ( $env->name == "live" ) {
                $environment = $env;
            }
        }
        
        // te: String!, $idEnvironment: String) {\n  site(id: $idSite) {\n    id\n    name\n    displayName\n    dbName\n    path\n    usr\n    siteLabels {\n      id\n      name\n      __typename\n    }\n    company {\n      id\n      name\n      __typename\n    }\n    environment(id: $idEnvironment) {\n      id\n      isPremium\n      cloudflareIP\n      customHostnames {\n        id\n        status\n        __typename\n      }\n      phpWorkerLimit\n      mysqlEditorDomain {\n        id\n        name\n        __typename\n      }\n      activeContainer {\n        id\n        lxdSshPort\n        loadBalancer {\n          id\n          extIP\n          __typename\n        }\n        lxdServer {\n          id\n          extIP\n          intHostname\n          zone {\n            id\n            name\n            __typename\n          }\n          __typename\n        }\n        __typename\n      }\n      customHostnames_liveKeys\n      activeContainer_liveKeys\n      __typename\n    }\n    hasPendingTransfer\n    hasFreeEnvSlot\n    siteLabels_liveKeys\n    environment_liveKeys(id: $idEnvironment)\n    hasPendingTransfer_liveKeys\n    hasFreeEnvSlot_liveKeys\n    __typename\n  }\n}\n"}

        $data  = [ 
            'timeout' => 45,
            'headers' => [
                'Content-Type' => 'application/json',
                'X-Token'      => "$token",
            ],
            'body'        => json_encode( [
                "variables" => [ 
                    "idSite"        => $site_key,
                    "idEnvironment" => $environment->id
                ],
                "operationName" => "SiteDetails",
                "query"         => 'query SiteDetails($idSite: String!, $idEnvironment: String) {
                    site(id: $idSite) {
                      id
                      name
                      displayName
                      dbName
                      path
                      usr
                      siteLabels {
                        id
                        name
                        __typename
                      }
                      company {
                        id
                        name
                        __typename
                      }
                      environment(id: $idEnvironment) {
                        id
                        isPremium
                        cloudflareIP
                        customHostnames {
                          id
                          status
                          __typename
                        }
                        phpWorkerLimit
                        mysqlEditorDomain {
                          id
                          name
                          __typename
                        }
                        activeContainer {
                          id
                          lxdSshPort
                          loadBalancer {
                            id
                            extIP
                            __typename
                          }
                          lxdServer {
                            id
                            extIP
                            intHostname
                            zone {
                              id
                              name
                              __typename
                            }
                            __typename
                          }
                          __typename
                        }
                        customHostnames_liveKeys
                        activeContainer_liveKeys
                        __typename
                      }
                      hasPendingTransfer
                      hasFreeEnvSlot
                      siteLabels_liveKeys
                      environment_liveKeys(id: $idEnvironment)
                      hasPendingTransfer_liveKeys
                      hasFreeEnvSlot_liveKeys
                      __typename
                    }
                  }'
            ] )
        ];

        $response = wp_remote_post( "https://graphql-router.kinsta.com", $data );

        $response = json_decode( $response['body'] );

        return $response->data->site;

    }

    public static function action_result( $provider_key = 0 ) {
        $provider_key = (int) $provider_key;
        $token = self::credentials("token");
        $data  = [ 
            'timeout' => 45,
            'headers' => [
                'Content-Type' => 'application/json',
                'X-Token'      => "$token",
            ],
            'body'        => json_encode( [
                "variables" => [ 
                    "idAction" => $provider_key
                ],
                "operationName" => "Action",
                "query"         => 'query Action($idAction: Int!) {
                    action(id: $idAction) {
                        error
                        result
                        __typename
                    }
                }'
            ] )
        ];

        $response = wp_remote_post( "https://graphql-router.kinsta.com", $data );

        if ( is_wp_error( $response ) ) {
            return false;
        }

        $response = json_decode( $response['body'] );

        if ( ! empty ( $response->errors ) ) {
            return false;
        }

        if ( empty ( $response->data->action ) ) {
            return false;
        }
        
        return $response->data->action->result;

    }

    public static function company_users() {
        $company_id = self::credentials("company_id");
        $token      = self::credentials("token");
        $data  = [ 
            'timeout' => 45,
            'headers' => [
                'Content-Type' => 'application/json',
                'X-Token'      => "$token",
            ],
            'body'        => json_encode( [
                "variables" => [
                    "idCompany" => $company_id
                ],
                "operationName" => "CompanyUsers",
                "query"         => 'fragment CompanyCapabilities on Capability {
                    idCompany
                    idSite
                    idRole
                    type
                    permissions {
                      type
                      key
                      idCompany
                      idSite
                      __typename
                    }
                    __typename
                  }
                  
                  fragment CompanyUsersItem on CompanyUser {
                    id
                    user {
                      id
                      email
                      image
                      fullName
                      has2FA
                      isKinstaStaff
                      intercomConversationUrl(cacheControl: CACHED_OR_RENEW)
                      intercomConversationUrl_liveKeys(cacheControl: CACHED_OR_RENEW)
                      __typename
                    }
                    capabilities {
                      ...CompanyCapabilities
                      __typename
                    }
                    capabilities_liveKeys
                    __typename
                  }
                  
                  fragment PendingInvitationsFragment on Invitation {
                    id
                    email
                    capabilities {
                      ...CompanyCapabilities
                      __typename
                    }
                    __typename
                  }
                  
                  fragment CompanyUser on User {
                    id
                    email
                    image
                    fullName
                    isKinstaStaff
                    __typename
                  }
                  
                  query CompanyUsers($idCompany: String!) {
                    company(id: $idCompany) {
                      id
                      name
                      sites {
                        id
                        displayName
                        __typename
                      }
                      users {
                        ...CompanyUsersItem
                        __typename
                      }
                      pendingInvitations {
                        ...PendingInvitationsFragment
                        __typename
                      }
                      sites_liveKeys
                      users_liveKeys
                      pendingInvitations_liveKeys
                      __typename
                    }
                    user {
                      ...CompanyUser
                      __typename
                    }
                    user_liveKeys
                  }'
            ] )
        ];

        $response = wp_remote_post( "https://graphql-router.kinsta.com", $data );

        if ( is_wp_error( $response ) ) {
            return false;
        }

        $response = json_decode( $response['body'] );

        if ( ! empty ( $response->errors ) ) {
            return false;
        }

        if ( empty ( $response->data ) ) {
            return false;
        }
        
        return $response->data;

    }

    /**
     * Get list of Kinsta sites/environments eligible to be pushed to.
     */
    public static function get_push_targets( $source_site_id, $source_environment_id ) {
        global $wpdb; // Access the WordPress database object

        // --- Get Source Site Info ---
        $source_site = \CaptainCore\Sites::get( $source_site_id );
        // Need provider_id for filtering targets to the same Kinsta account
        $provider_id = $source_site->provider_id;

        // --- Get Allowed Sites for Current User ---
        $user_sites = new \CaptainCore\Sites(); // Instantiate to get user-specific permissions
        $allowed_site_ids = $user_sites->site_ids(); // Get the array of site IDs the user can access

        // If the user has no allowed sites, return early
        if ( empty( $allowed_site_ids ) ) {
            return [];
        }

        // --- Prepare Database Query ---
        $sites_table        = $wpdb->prefix . 'captaincore_sites';
        $environments_table = $wpdb->prefix . 'captaincore_environments';

        // Prepare base WHERE clauses safely
        $where_clauses = [
            $wpdb->prepare( "s.provider = %s", "kinsta" ),
            $wpdb->prepare( "s.status = %s", "active" ),
            $wpdb->prepare( "e.environment_id != %d", $source_environment_id ),
        ];

        // Add provider_id filtering logic (ensures push targets are within the same Kinsta account)
        if ( empty( $provider_id ) || $provider_id == '1' ) { // Treat provider_id 1 (often default/unmanaged) or empty as needing matching targets
             $where_clauses[] = "( s.provider_id = '1' OR s.provider_id IS NULL )";
        } else {
             // If source has a specific Kinsta provider_id, target must match
             $where_clauses[] = $wpdb->prepare( "s.provider_id = %s", $provider_id );
        }

        // --- Add User Permission Filter ---
        // Safely create the IN clause for allowed site IDs
        $allowed_ids_sql = implode( ',', array_map( 'intval', $allowed_site_ids ) ); // Ensure integers
        $where_clauses[] = "s.site_id IN ( $allowed_ids_sql )"; // Add the IN clause

        $where_sql = implode( ' AND ', $where_clauses );

        // Construct the final optimized query string
        // Note: $allowed_ids_sql is already sanitized by array_map('intval', ...)
        $sql = "SELECT s.site_id, s.name, e.environment, e.environment_id, e.home_url
                FROM $environments_table e
                INNER JOIN $sites_table s ON e.site_id = s.site_id
                WHERE $where_sql
                ORDER BY s.name ASC";

        $results = $wpdb->get_results( $sql ); // Execute the query

        // Map results directly to the target format
        $targets = [];
        if ( $results ) {
            foreach ( $results as $row ) {
                $targets[] = [
                    'site_id'        => (int) $row->site_id,
                    'name'           => $row->name,
                    'environment'    => $row->environment,
                    'environment_id' => (int) $row->environment_id,
                    'home_url'       => $row->home_url ?? $row->name,
                ];
            }
        }

        return $targets;
    }

    /**
     * Pushes one Kinsta environment to another.
     *
     * @param int $source_environment_id The CaptainCore ID of the source environment.
     * @param int $target_environment_id The CaptainCore ID of the target environment.
     * @return object|WP_Error Kinsta API response object or WP_Error on failure.
     */
    public static function push_environment( $source_environment_id, $target_environment_id ) {
		// Get source env and site
		$source_env  = \CaptainCore\Environments::get( $source_environment_id );
		$source_site = \CaptainCore\Sites::get( $source_env->site_id );
		
		// Get target env and site
		$target_env  = \CaptainCore\Environments::get( $target_environment_id );
		$target_site = \CaptainCore\Sites::get( $target_env->site_id );

		if ( ! $source_site || ! $target_site || $source_site->provider_id != $target_site->provider_id ) {
			return new \WP_Error('provider_mismatch', 'Source and target sites are not managed by the same provider account.', ['status' => 400]);
		}

        $api_key = self::credentials("api", $source_site->provider_id);
        if ( ! $api_key ) {
             return new \WP_Error('kinsta_api_key_missing', 'Kinsta API key not configured or set for the request.', ['status' => 500]);
        }
		\CaptainCore\Remote\Kinsta::setApiKey( $api_key );

		// --- Fetch Source Kinsta Env ID ---
		$source_kinsta_env_id = self::get_kinsta_env_id_from_cc_env( $source_site, $source_env );
		if ( is_wp_error( $source_kinsta_env_id ) ) {
			return $source_kinsta_env_id;
		}

		// --- Fetch Target Kinsta Env ID ---
		$target_kinsta_env_id = self::get_kinsta_env_id_from_cc_env( $target_site, $target_env );
		if ( is_wp_error( $target_kinsta_env_id ) ) {
			return $target_kinsta_env_id;
		}
		
        $data = [
            "source_env_id"          => $source_kinsta_env_id,
            "target_env_id"          => $target_kinsta_env_id,
            "push_db"                => true,
            "push_files"             => true,
        ];

        // Call Kinsta API
        $response = \CaptainCore\Remote\Kinsta::put( "sites/{$source_site->provider_site_id}/environments", $data );

        if ( ! $response || isset( $response->error ) || (isset($response->status) && $response->status >= 400) ) {
            $error_message = $response->message ?? 'Unknown Kinsta API error during push.';
            return new \WP_Error( 'kinsta_push_failed', $error_message, [ 'status' => $response->status ?? 500, 'details' => $response ] );
        }

        return $response;
    }

	/**
	 * Helper to get Kinsta Environment ID from a CaptainCore Environment object
	 */
	private static function get_kinsta_env_id_from_cc_env( $cc_site, $cc_env ) {
		if ( ! $cc_site || ! $cc_env || empty( $cc_site->provider_site_id ) ) {
			return new \WP_Error( 'invalid_input', 'Invalid CaptainCore site or environment object.' );
		}

		$kinsta_environments_response = \CaptainCore\Remote\Kinsta::get( "sites/{$cc_site->provider_site_id}/environments" );

		if ( ! $kinsta_environments_response || isset( $kinsta_environments_response->error ) || ! isset( $kinsta_environments_response->site->environments ) ) {
			return new \WP_Error( 'kinsta_api_error', "Could not fetch environments from Kinsta for site {$cc_site->name}.", [ 'status' => 500, 'details' => $kinsta_environments_response ] );
		}

		$cc_env_name_lower = strtolower( $cc_env->environment );
		$kinsta_env_name_match = ( $cc_env_name_lower == 'production' ) ? 'live' : $cc_env_name_lower;
		
		foreach ( $kinsta_environments_response->site->environments as $kinsta_env ) {
			if ( strtolower( $kinsta_env->name ) == $kinsta_env_name_match ) {
				return $kinsta_env->id; // Found it
			}
		}

		return new \WP_Error( 'kinsta_env_not_found', "Could not find matching Kinsta environment for {$cc_site->name} ({$cc_env->environment}).", [ 'status' => 404 ] );
	}

    public static function invite_emails( $emails = [], $idSite = "" ) {
        $users      = self::company_users();
        $token      = self::credentials("token");
        $company_id = self::credentials("company_id");
        $user_ids   = [];
        $response   = [];
        foreach ( $users->company->users as $user ) {
            if ( in_array( $user->user->email, $emails ) ) {
                $capabilities = [];
                $user_ids[]   = $user->user->id;
                foreach( $user->capabilities as $site ) {
                    $capabilities[] = (object) [
                        "idSite" => $site->idSite,
                        "idRole" => $site->idRole
                    ];
                }

                // Add new site to shared access
                $capabilities[] = (object) [
                    "idSite" => $idSite,
                    "idRole" => "site-admin"
                ];
                $response[]   = "Found {$user->user->email} adding the following capabilities";
                $response[]   = $capabilities;
            }
        }

        $data  = [ 
            'timeout' => 45,
            'headers' => [
                'Content-Type' => 'application/json',
                'X-Token'      => "$token",
            ],
            'body'        => json_encode( [
                "variables" => [ 
                    "idCompany" => $company_id,
                    "idUsers"   => $user_ids,
                    "siteCapabilities" => $capabilities
                ],
                "operationName" => "EditSiteCapabilities",
                "query"         => 'mutation EditSiteCapabilities($idCompany: String!, $idUsers: [String!]!, $siteCapabilities: [SiteCapability!]!) {
                    idAction: editSiteCapabilities(
                      idCompany: $idCompany
                      idUsers: $idUsers
                      siteCapabilities: $siteCapabilities
                      runActionInBackground: true
                    )
                  }'
            ] )
        ];

        $response = wp_remote_post( "https://graphql-router.kinsta.com", $data );

        if ( is_wp_error( $response ) ) {
            return false;
        }

        $response = json_decode( $response['body'] );

        if ( ! empty ( $response->errors ) ) {
            return false;
        }

        if ( empty ( $response->idAction ) ) {
            return false;
        }
        
        return $response->data->idAction;
    }

    /**
     * Private helper to get the Kinsta Environment ID.
     */
    private static function get_kinsta_env_id( $site_id, $environment_name ) {
        $site = ( new \CaptainCore\Sites )->get( $site_id );
        if ( ! $site || $site->provider != 'kinsta' || empty( $site->provider_site_id ) ) {
            return new \WP_Error( 'not_kinsta_site', 'This is not a Kinsta-managed site.', [ 'status' => 400 ] );
        }
        
        // Use the site's provider_id if available, otherwise default
        $api_key = self::credentials("api", $site->provider_id );
        \CaptainCore\Remote\Kinsta::setApiKey( $api_key );

        $response = \CaptainCore\Remote\Kinsta::get( "sites/{$site->provider_site_id}/environments" );

        if ( ! $response || isset( $response->error ) || ! isset( $response->site->environments ) ) {
            return new \WP_Error( 'kinsta_api_error', 'Could not fetch environments from Kinsta.', [ 'status' => 500, 'details' => $response ] );
        }

        foreach ( $response->site->environments as $env ) {
            if ( strtolower( $env->name ) == strtolower( $environment_name ) ) {
                return $env->id;
            }
        }

        return new \WP_Error( 'env_not_found', 'The specified environment was not found on Kinsta.', [ 'status' => 404 ] );
    }

    /**
     * Get phpMyAdmin login URL for a Kinsta environment.
     */
    public static function get_phpmyadmin_url( $site_id, $env_name ) {
        if ( strtolower( $env_name ) == "production" ) {
            $env_name = "live";
        }
        $kinsta_env_id = self::get_kinsta_env_id( $site_id, $env_name );
        if ( is_wp_error( $kinsta_env_id ) ) {
            return $kinsta_env_id;
        }

        $site = ( new \CaptainCore\Sites )->get( $site_id );
        $api_key = self::credentials("api", $site->provider_id );
        \CaptainCore\Remote\Kinsta::setApiKey( $api_key );

        $response = \CaptainCore\Remote\Kinsta::post( "sites/environments/{$kinsta_env_id}/pma-login-token" );
        
        if ( ! $response || isset( $response->error ) || ! isset($response->url) ) {
             return new \WP_Error( 'kinsta_api_error', 'Error generating phpMyAdmin URL.', [ 'status' => 500, 'details' => $response ] );
        }
        
        return $response->url;
    }

    /**
     * Get list of domains for a Kinsta environment.
     */
    public static function get_domains( $site_id, $environment = 'production' ) {
        
        $kinsta_env_name = ( $environment == 'production' ) ? 'live' : $environment;
        $site            = \CaptainCore\Sites::get( $site_id );
        $kinsta_site_id  = $site->provider_site_id;

        if ( empty( $kinsta_site_id ) ) {
            return new \WP_Error( 'kinsta_missing_id', 'Kinsta Remote ID not set for this site.' );
        }

        // 1. Fetch Environments to find the specific Environment ID
        $env_response = \CaptainCore\Remote\Kinsta::get( "sites/{$kinsta_site_id}/environments" );

        if ( is_wp_error( $env_response ) ) {
            return $env_response;
        }

        $env_id = null;
        $environments = $env_response->site->environments ?? [];

        foreach ( $environments as $env ) {
            if ( strtolower( $env->name ) === strtolower( $kinsta_env_name ) ) {
                $env_id = $env->id;
                break;
            }
        }

        if ( ! $env_id ) {
            return new \WP_Error( 'kinsta_env_not_found', "Kinsta environment '{$kinsta_env_name}' not found." );
        }

        // 2. Fetch Domains for the specific environment
        $response = \CaptainCore\Remote\Kinsta::get( "sites/environments/{$env_id}/domains" );

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        $domains = $response->environment->site_domains ?? [];

        // 3. Check verification status for each domain
        foreach ( $domains as $key => $domain ) {
            
            $is_active = true;
            $verification_records = [];

            if ( strpos( $domain->name, '*.' ) !== false ) {
                unset( $domains[$key] );
                continue;
            }

            if ( strpos( $domain->name, 'kinsta.cloud' ) !== false ) {
                $is_active = true;
            } else {
                $verify_response = \CaptainCore\Remote\Kinsta::get( "sites/environments/domains/{$domain->id}/verification-records" );
                
                if ( ! is_wp_error( $verify_response ) && ! empty( $verify_response->site_domain->verification_records ) ) {
                    $is_active = false;
                    // FIX: array_values() forces this to be a strictly indexed JSON array [{},{}]
                    // preventing Vue from seeing "empty" slots or object keys.
                    $verification_records = array_values( $verify_response->site_domain->verification_records );
                }
            }

            $domains[$key]->is_active = $is_active;
            $domains[$key]->verification_records = $verification_records;
        }

        return array_values( $domains );
    }

    /**
     * Add a domain to a Kinsta environment.
     */
    public static function add_domain( $site_id, $env_name, $params ) {
        if ( strtolower( $env_name ) == "production" ) {
            $env_name = "live";
        }
        $kinsta_env_id = self::get_kinsta_env_id( $site_id, $env_name );
        if ( is_wp_error( $kinsta_env_id ) ) {
            return $kinsta_env_id;
        }

        $site = ( new \CaptainCore\Sites )->get( $site_id );
        $api_key = self::credentials("api", $site->provider_id );
        \CaptainCore\Remote\Kinsta::setApiKey( $api_key );

        $body = [
            'domain_name' => $params['domain_name'],
            'is_wildcardless' => $params['is_wildcardless'] ?? false,
        ];

        return \CaptainCore\Remote\Kinsta::post( "sites/environments/{$kinsta_env_id}/domains", $body );
    }

    /**
     * Delete a domain from a Kinsta environment.
     */
    public static function delete_domain( $site_id, $env_name, $params ) {
        if ( strtolower( $env_name ) == "production" ) {
            $env_name = "live";
        }
        $kinsta_env_id = self::get_kinsta_env_id( $site_id, $env_name );
        if ( is_wp_error( $kinsta_env_id ) ) {
            return $kinsta_env_id;
        }

        $site = \CaptainCore\Sites::get( $site_id );
        $api_key = self::credentials("api", $site->provider_id );
        \CaptainCore\Remote\Kinsta::setApiKey( $api_key );
        
        $body = [
            'domain_ids' => $params['domain_ids'], // Expecting an array
        ];

        return \CaptainCore\Remote\Kinsta::delete( "sites/environments/{$kinsta_env_id}/domains", $body );
    }

    /**
     * Set the primary domain for a Kinsta environment.
     */
    public static function set_primary_domain( $site_id, $env_name, $params ) {
        if ( strtolower( $env_name ) == "production" ) {
            $env_name = "live";
        }
        $kinsta_env_id = self::get_kinsta_env_id( $site_id, $env_name );
        if ( is_wp_error( $kinsta_env_id ) ) {
            return $kinsta_env_id;
        }

        $site = ( new \CaptainCore\Sites )->get( $site_id );
        $api_key = self::credentials("api", $site->provider_id );
        \CaptainCore\Remote\Kinsta::setApiKey( $api_key );

        $body = [
            'domain_id' => $params['domain_id'],
            'run_search_and_replace' => $params['run_search_and_replace'] ?? true,
        ];

        return \CaptainCore\Remote\Kinsta::put( "sites/environments/{$kinsta_env_id}/change-primary-domain", $body );
    }

    /**
     * Fetch verification records for a specific domain.
     */
    public function get_verification_records( $domain_id ) {
        $response = \CaptainCore\Remote\Kinsta::get( "sites/environments/domains/{$domain_id}/verification-records" );

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        // Access as object: $response->site_domain->verification_records
        return $response->site_domain->verification_records ?? [];
    }

    /**
     * "Check" verification by re-fetching the domain details.
     * Kinsta checks in the background, so we just need to pull the latest status.
     */
    public function check_verification( $site_id, $domain_id ) {
        $site           = \CaptainCore\Sites::get( $site_id );
        $kinsta_site_id = $site->provider_site_id;
        
        // 1. Get Environment ID (assuming 'live')
        $env_response = \CaptainCore\Remote\Kinsta::get( "sites/{$kinsta_site_id}/environments" );
        if ( is_wp_error( $env_response ) ) return $env_response;

        $env_id = null;
        $environments = $env_response->site->environments ?? [];

        foreach ( $environments as $env ) {
            if ( $env->name === 'live' ) {
                $env_id = $env->id; 
                break;
            }
        }

        if ( ! $env_id ) return new \WP_Error( 'env_not_found', 'Kinsta environment not found.' );

        // 2. Re-fetch domains to get latest status
        $response = \CaptainCore\Remote\Kinsta::get( "sites/environments/{$env_id}/domains" );
        
        if ( is_wp_error( $response ) ) return $response;

        $domains = $response->site->domains ?? [];
        
        // Find our specific domain object
        foreach ( $domains as $domain ) {
            if ( $domain->id === $domain_id ) {
                // Map is_active status on this single object before returning
                $status = $domain->status ?? '';
                $domain->is_active = ( $status === 'live' );
                return $domain; 
            }
        }

        return new \WP_Error( 'domain_not_found', 'Domain not found in Kinsta response.' );
    }

}
