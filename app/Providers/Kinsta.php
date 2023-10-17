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

        $response = wp_remote_post( "https://my.kinsta.com/gateway", $data );

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

        $response = wp_remote_post( "https://my.kinsta.com/gateway", $data );

//        {"operationName":"AddSite","variables":{"displayName":"testing captaincore","installMode":"new","idCompany":"1577","region":"us-east5","isSubdomainMultisite":false,"idMigrationForm":"","adminEmail":"support@anchor.host","adminPassword":"vNgdzeCd_t&KJ^6#","adminUser":"anchorhost","isMultisite":false,"siteTitle":"CaptainCore title","woocommerce":false,"wordpressseo":false,"wpLanguage":"en_US"},"query":"mutation AddSite($displayName: String!, $region: String, $installMode: InstallMode!, $idSourceEnv: String, $idSourceSnapshot: Int, $siteTitle: String, $adminUser: String, $adminPassword: String, $adminEmail: String, $woocommerce: Boolean, $wordpressseo: Boolean, $wordpressPluginEDD: Boolean, $wpLanguage: String, $isMultisite: Boolean, $isSubdomainMultisite: Boolean, $idCompany: String!, $idMigrationForm: String) {\n  idAction: addSite(\n    displayName: $displayName\n    region: $region\n    installMode: $installMode\n    idSourceEnv: $idSourceEnv\n    idSourceSnapshot: $idSourceSnapshot\n    siteTitle: $siteTitle\n    adminUser: $adminUser\n    adminPassword: $adminPassword\n    adminEmail: $adminEmail\n    woocommerce: $woocommerce\n    wordpressseo: $wordpressseo\n    wordpressPluginEDD: $wordpressPluginEDD\n    wpLanguage: $wpLanguage\n    isMultisite: $isMultisite\n    isSubdomainMultisite: $isSubdomainMultisite\n    idCompany: $idCompany\n    idMigrationForm: $idMigrationForm\n    runActionInBackground: true\n  )\n}\n"}
//        response is {"data":{"idAction":12765378}}
//
//
//        {"operationName":"Action","variables":{"idAction":12765378},"query":"query Action($idAction: Int!) {\n  action(id: $idAction) {\n    error\n    isDone\n    __typename\n  }\n  action_liveKeys(id: $idAction)\n}\n"}
//        response is {"data":{"action":{"error":null,"isDone":false,"__typename":"Action"},"action_liveKeys":["de16ace036c654b3428e72206641e34c946f38d19dcbb26169700b5da4e9af97"]}}
//
//        {"operationName":"ActionResult","variables":{"idAction":12765378},"query":"query ActionResult($idAction: Int!) {\n  action(id: $idAction) {\n    error\n    result\n    __typename\n  }\n}\n"}
//        responsee is {"data":{"action":{"error":null,"result":{"idSite":"42dca000-feba-4bfd-9cc3-9145968cdc52","idEnv":"83e56dd5-0ee9-4590-a5a9-2a407fc25f97"},"__typename":"Action"}}}
//
//        {"operationName":"DeleteLiveSite","variables":{"idSite":"42dca000-feba-4bfd-9cc3-9145968cdc52"},"query":"mutation DeleteLiveSite($idSite: String!) {\n  idAction: deleteLiveSite(idSite: $idSite, runActionInBackground: true)\n}\n"}
//        {"data":{"idAction":12766202}}
//

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

        self::add_action( $response->data->idAction, $site );

        // Track new request in the background or maybe just create the site and fill in the data after the site is created?
        // The site should be hidden until ready

        return $response->data->idAction;

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

    public static function action_check( $provider_key = 0 ) {
        $token           = self::credentials("token");
        $data  = [ 
            'timeout' => 45,
            'headers' => [
                'Content-Type' => 'application/json',
                'X-Token'      => "$token",
            ],
            'body'        => json_encode( [
                "variables" => [ 
                    "idAction" => (int) $provider_key
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

        $response = wp_remote_post( "https://my.kinsta.com/gateway", $data );

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

        $response = wp_remote_post( "https://my.kinsta.com/gateway", $data );
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

        $response = wp_remote_post( "https://my.kinsta.com/gateway", $data );
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

        $response = wp_remote_post( "https://my.kinsta.com/gateway", $data );
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

        $response = wp_remote_post( "https://my.kinsta.com/gateway", $data );
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

        $response = wp_remote_post( "https://my.kinsta.com/gateway", $data );

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

        $response = wp_remote_post( "https://my.kinsta.com/gateway", $data );

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

        $response = wp_remote_post( "https://my.kinsta.com/gateway", $data );

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

        $response = wp_remote_post( "https://my.kinsta.com/gateway", $data );

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

