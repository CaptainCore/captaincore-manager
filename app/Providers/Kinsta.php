<?php 

namespace CaptainCore\Providers;

class Kinsta {

    public static function credentials( $record = "" ) {
        $credentials = ( new \CaptainCore\Provider( "kinsta" ) )->credentials();
        if ( $record == "" ) {
            return $credentials;
        }
        foreach( $credentials as $credential ) {
            if ( $credential->name == $record ) {
                return $credential->value;
            }
        }
    }
    
    public static function update_token( $token = "" ) {
        $provider    = ( new \CaptainCore\Provider( "kinsta" ) )->get();
        $credentials = self::credentials();
        foreach( $credentials as $credential ) {
            if ( $credential->name == "token" ) {
                $credential->value = $token;
            }
        }
        ( new \CaptainCore\Providers )->update( [ "credentials" => json_encode( $credentials ) ], [ "provider_id" => $provider->provider_id ] );
        return self::verify();
    }

    public static function verify() {
        $token = self::credentials("token");
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
        $token      = self::credentials("token");
        $company_id = self::credentials("company_id");
        $site       = (object) $site;
        $data  = [
            'timeout' => 45,
            'headers' => [
                'Content-Type' => 'application/json',
                'X-Token'      => "$token",
            ],
            'body'        => json_encode( [
                "operationName" => "AddSite",
                "variables" => [
                    "displayName"          => $site->name,
                    "installMode"          => "new",
                    "idCompany"            => $company_id,
                    "region"               => $site->datacenter,
                    "isSubdomainMultisite" => false,
                    "idMigrationForm"      => "",
                    "adminEmail"           => "support@anchor.host",
                    "adminPassword"        => substr ( str_shuffle( "0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ" ), 0, 16 ),
                    "adminUser"            => "anchorhost",
                    "isMultisite"          => false,
                    "siteTitle"            => $site->name,
                    "woocommerce"          => false,
                    "wordpressseo"         => false,
                    "wpLanguage"           => "en_US"
                ],
                "query"         => 'mutation AddSite($displayName: String!, $region: String, $installMode: InstallMode!, $idSourceEnv: String, $idSourceSnapshot: Int, $siteTitle: String, $adminUser: String, $adminPassword: String, $adminEmail: String, $woocommerce: Boolean, $wordpressseo: Boolean, $wordpressPluginEDD: Boolean, $wpLanguage: String, $isMultisite: Boolean, $isSubdomainMultisite: Boolean, $idCompany: String!, $idMigrationForm: String) {
                    idAction: addSite(
                      displayName: $displayName
                      region: $region
                      installMode: $installMode
                      idSourceEnv: $idSourceEnv
                      idSourceSnapshot: $idSourceSnapshot
                      siteTitle: $siteTitle
                      adminUser: $adminUser
                      adminPassword: $adminPassword
                      adminEmail: $adminEmail
                      woocommerce: $woocommerce
                      wordpressseo: $wordpressseo
                      wordpressPluginEDD: $wordpressPluginEDD
                      wpLanguage: $wpLanguage
                      isMultisite: $isMultisite
                      isSubdomainMultisite: $isSubdomainMultisite
                      idCompany: $idCompany
                      idMigrationForm: $idMigrationForm
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

        if ( empty ( $response->data->idAction ) ) {
            return false;
        }

        $site->command = "new-site";

        self::add_action( $response->data->idAction, $site );

        // Track new request in the background or maybe just create the site and fill in the data after the site is created?
        // The site should be hidden until ready

        return $response->data->idAction;

    }

    public static function environments( $kinsta_id ) {
        $response = \CaptainCore\Remote\Kinsta::get( "sites/$kinsta_id/environments" );
        return $response->site->environments;
    }

    public static function connect_staging( $site_id ) {
        $site         = ( new \CaptainCore\Sites )->get( $site_id );
        $environments = ( new \CaptainCore\Environments )->where( [ "site_id" => $site_id ] );
        if ( empty( $site->provider_id ) ) {
            return;
        }
        foreach( $environments as $environment ) {
            if ( $environment->environment == "Production" ) {
                $production_environment = $environment;
            }
        }
        $time_now        = date("Y-m-d H:i:s");
        $response = \CaptainCore\Remote\Kinsta::get( "sites/{$site->provider_id}/environments" );

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
        if ( empty( $site->provider_id ) ) {
            return;
        }
        foreach( $environments as $environment ) {
            if ( $environment->environment == "Production" ) {
                $production_environment = $environment;
            }
        }
        $response = \CaptainCore\Remote\Kinsta::get( "sites/{$site->provider_id}/environments" );

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
        if ( empty( $site->provider_id ) ) {
            return;
        }
        $environments              = self::environments( $site->provider_id );
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
        $response = \CaptainCore\Remote\Kinsta::post( "sites/{$site->provider_id}/environments/clone", $data );

        $action = (object) [
            "command"                   => "deploy-to-staging",
            "step"                      => 2,
            "message"                   => "Deploying $site->name to staging environment",
            "name"                      => $site->name,
            "site_id"                   => $site_id,
            "kinsta_site_id"            => $site->provider_id,
            "environment_production_id" => $environment_production_id,
        ];

        self::add_action( $response->operation_id, $action );
        return $response->operation_id;
    }

    public static function deploy_to_staging( $site_id ) {
        $site         = ( new \CaptainCore\Sites )->get( $site_id );
        if ( empty( $site->provider_id ) ) {
            return;
        }
        $environments = self::environments( $site->provider_id );
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
            "kinsta_site_id"            => $site->provider_id,
            "environment_production_id" => $environment_production_id,
            "environment_staging_id"    => $environment_staging_id,
        ];

        self::add_action( $response->operation_id, $action );
        return $response->operation_id;
    }

    public static function deploy_to_production( $site_id ) {
        $site         = ( new \CaptainCore\Sites )->get( $site_id );
        if ( empty( $site->provider_id ) ) {
            return;
        }
        $environments = self::environments( $site->provider_id );
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
        $response = \CaptainCore\Remote\Kinsta::put( "sites/{$site->provider_id}/environments", $data );

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
            "kinsta_site_id"            => $site->provider_id,
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
                    ( new  \CaptainCore\Sites )->update( [ "provider_id" => $kinsta_site->id ], [ "site_id" => $site->site_id ] );
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
        //( new \CaptainCore\ProviderAction( $provider_action_id ) )->run();
      
        return;
    }

    public static function action_check( $provider_action_id = 0, $return_response = false ) {
        $provider_action = ( new \CaptainCore\ProviderActions )->get( $provider_action_id );
        $action          = json_decode( $provider_action->action );
        if ( $action->command == "deploy-to-staging" || $action->command == "deploy-to-production" ) {
            $response = \CaptainCore\Remote\Kinsta::get( "operations/{$provider_action->provider_key}" );
            return $response->status;
        }

        $token = self::credentials("token");
        $data  = [ 
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
            return false;
        }

        $response = json_decode( $response['body'] );

        if ( ! empty ( $response->errors ) ) {
            return false;
        }

        if ( empty ( $response->data->action ) ) {
            return false;
        }

        if ( $return_response ) {
            return $response;
        }
        
        return $response->data->action->isDone;

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
                "operationName" => "ActionResult",
                "query"         => 'query ActionResult($idAction: Int!) {
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

}

