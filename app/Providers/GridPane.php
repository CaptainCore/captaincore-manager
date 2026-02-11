<?php 

namespace CaptainCore\Providers;

class GridPane {

    public static function credentials( $record = "", $provider_id = "" ) {
        // If a specific provider ID is passed (multi-provider support), use it.
        // Otherwise defaults to the first provider found with slug 'gridpane'.
        if ( ! empty( $provider_id ) ) {
            $provider    = \CaptainCore\Providers::get( $provider_id );
            $credentials = ! empty( $provider->credentials ) ? json_decode( $provider->credentials ) : [];
        } else {
            $credentials = ( new \CaptainCore\Provider( "gridpane" ) )->credentials();
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

    private static function api_get( $endpoint, $provider_id = "" ) {
        $api_key = self::credentials( "api_key", $provider_id );
        if ( empty( $api_key ) ) {
            return null;
        }
        $response = wp_remote_get( "https://my.gridpane.com/oauth/api/v1/{$endpoint}", [
            'timeout' => 30,
            'headers' => [
                'Authorization' => 'Bearer ' . $api_key,
                'Accept'        => 'application/json',
            ],
        ] );
        if ( is_wp_error( $response ) ) {
            return null;
        }
        return json_decode( wp_remote_retrieve_body( $response ) );
    }

    public static function fetch_remote_sites( $provider_id = "" ) {
        $body = self::api_get( 'site?per_page=200', $provider_id );

        if ( empty( $body->data ) ) {
            return [];
        }

        $sites = [];
        foreach ( $body->data as $gp_site ) {
            $sites[] = [
                'remote_id'      => $gp_site->id,
                'name'           => $gp_site->url,
                'label'          => $gp_site->url,
                'status'         => $gp_site->type ?? 'primary',
                'server_ip'      => $gp_site->server->ip ?? '',
                'system_user_id' => $gp_site->system_user_id ?? '',
            ];
        }
        return $sites;
    }

    public static function fetch_system_users( $provider_id = "" ) {
        $body = self::api_get( 'system-user?per_page=200', $provider_id );

        if ( empty( $body->data ) ) {
            return [];
        }

        $users = [];
        foreach ( $body->data as $user ) {
            $users[ $user->id ] = $user;
        }
        return $users;
    }

    public static function enrich_imported_site( $site_id, $remote_site, $provider_id = "" ) {
        $remote_site = (object) $remote_site;

        // Fetch system users (cached per-request would be ideal, use static cache)
        static $system_users_cache = [];
        if ( ! isset( $system_users_cache[ $provider_id ] ) ) {
            $system_users_cache[ $provider_id ] = self::fetch_system_users( $provider_id );
        }
        $system_users = $system_users_cache[ $provider_id ];

        $username = '';
        $password = '';
        $system_user_id = $remote_site->system_user_id ?? '';
        if ( ! empty( $system_user_id ) && isset( $system_users[ $system_user_id ] ) ) {
            $user     = $system_users[ $system_user_id ];
            $username = $user->username ?? '';
            $password = isset( $user->password ) ? trim( $user->password ) : '';
        }

        $address = $remote_site->server_ip ?? '';

        // Update the production environment with SFTP details
        $environments = ( new \CaptainCore\Environments )->where( [ "site_id" => $site_id ] );
        foreach ( $environments as $env ) {
            if ( $env->environment === 'Production' ) {
                ( new \CaptainCore\Environments )->update( [
                    'address'        => $address,
                    'username'       => $username,
                    'password'       => $password,
                    'protocol'       => 'sftp',
                    'port'           => '22',
                    'home_directory' => '/var/www/' . $remote_site->name . '/htdocs',
                ], [ 'environment_id' => $env->environment_id ] );
            }
        }
    }
}