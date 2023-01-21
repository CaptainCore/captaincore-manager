<?php 

namespace CaptainCore\Providers;

class Envato {

    public static function credentials( $record = "" ) {
        $credentials = ( new \CaptainCore\Provider( "envato" ) )->credentials();
        if ( $record == "" ) {
            return $credentials;
        }
        foreach( $credentials as $credential ) {
            if ( $credential->name == $record ) {
                return $credential->value;
            }
        }
    }

    public static function themes() {
        $providers = ( new \CaptainCore\Providers )->where( [ "provider" => "envato" ] );
        $themes   = [];
        foreach( $providers as $provider ) {
            $details = empty( $provider->details ) ? (object) [] : json_decode( $provider->details );
            foreach( $details->themes as $theme ) {
                $themes[] = $theme->item;
            }
        }
        $themes = array_intersect_key($themes, array_unique(array_column($themes, 'id')));
        usort($themes, fn($a, $b) => strcmp($a->name, $b->name));
        return $themes;
    }

    public static function plugins() {
        $providers = ( new \CaptainCore\Providers )->where( [ "provider" => "envato" ] );
        $plugins   = [];
        foreach( $providers as $provider ) {
            $details = empty( $provider->details ) ? (object) [] : json_decode( $provider->details );
            foreach( $details->plugins as $plugin ) {
                $plugins[] = $plugin->item;
            }
        }
        $plugins = array_intersect_key($plugins, array_unique(array_column($plugins, 'id')));
        usort($plugins, fn($a, $b) => strcmp($a->name, $b->name));
        return $plugins;
    }

    public static function fetch_themes() {
        $providers = ( new \CaptainCore\Providers )->where( [ "provider" => "envato" ] );
        foreach( $providers as $provider ) {
            $token       = "";
            $credentials = empty( $provider->credentials ) ? (object) [] : json_decode( $provider->credentials );
            $details     = empty( $provider->details ) ? (object) [] : json_decode( $provider->details );
            foreach( $credentials as $credential ) {
                if ( $credential->name == "token" ) {
                    $token = $credential->value;
                }
            }
            $api_request = "https://api.envato.com/v3/market/buyer/list-purchases?filter_by=wordpress-themes";
            $response    = wp_remote_get( $api_request, [
                'headers'     => [
                    'Authorization' => "Bearer $token",
                ]
            ]);

            if ( is_wp_error( $response ) ) {
                continue;
            }

            $response = json_decode( $response['body'] );
            if ( empty( $response->results ) ) {
                continue;
            }

            $purchased_themes = $response->results;

            if ( ! empty( $response->pagination->next ) ) {

                $next = $response->pagination->next;

                do {
                    $response = wp_remote_get( $next, [
                        'headers'     => [
                            'Authorization' => "Bearer $token",
                        ]
                    ]);

                    if ( is_wp_error( $response ) ) {
                        continue;
                    }
        
                    $response = json_decode( $response['body'] );
                    if ( empty( $response->results ) ) {
                        continue;
                    }
                    foreach( $response->results as $item ) {
                        $purchased_themes[] = $item;
                    }
                  } while ( $response->pagination->next != $response->pagination->previous );

            }

            $details->themes = $purchased_themes;

            ( new \CaptainCore\Providers )->update( [ "details" => json_encode( $details ) ], [ "provider_id" => $provider->provider_id ] );
        }
    }

    public static function fetch_plugins() {
        $providers = ( new \CaptainCore\Providers )->where( [ "provider" => "envato" ] );
        foreach( $providers as $provider ) {
            $plugins     = [];
            $token       = "";
            $credentials = empty( $provider->credentials ) ? (object) [] : json_decode( $provider->credentials );
            $details     = empty( $provider->details ) ? (object) [] : json_decode( $provider->details );
            foreach( $credentials as $credential ) {
                if ( $credential->name == "token" ) {
                    $token = $credential->value;
                }
            }
            $api_request = "https://api.envato.com/v3/market/buyer/list-purchases?filter_by=wordpress-plugins";
            $response    = wp_remote_get( $api_request, [
                'headers'     => [
                    'Authorization' => "Bearer $token",
                ]
            ]);

            if ( is_wp_error( $response ) ) {
                //return $response->get_error_message();
                continue;
            }

            $response = json_decode( $response['body'] );
            if ( empty( $response->results ) ) {
                continue;
                //return $response->results;
            }

            $purchased_plugins = $response->results;

            if ( ! empty( $response->pagination->next ) ) {

                $next = $response->pagination->next;

                do {
                    $response = wp_remote_get( $next, [
                        'headers'     => [
                            'Authorization' => "Bearer $token",
                        ]
                    ]);

                    if ( is_wp_error( $response ) ) {
                        continue;
                    }
        
                    $response = json_decode( $response['body'] );
                    if ( empty( $response->results ) ) {
                        continue;
                    }
                    foreach( $response->results as $item ) {
                        $purchased_plugins[] = $item;
                    }
                  } while ( ! empty( $next ) );

            }

            $details->plugins = $purchased_plugins;

            ( new \CaptainCore\Providers )->update( [ "details" => json_encode( $details ) ], [ "provider_id" => $provider->provider_id ] );
        }
    }

    public static function download_theme( $theme_id ) {

        $providers = ( new \CaptainCore\Providers )->where( [ "provider" => "envato" ] );
        foreach( $providers as $provider ) {
            $token       = "";
            $credentials = empty( $provider->credentials ) ? (object) [] : json_decode( $provider->credentials );
            $details     = empty( $provider->details ) ? (object) [] : json_decode( $provider->details );
            foreach( $credentials as $credential ) {
                if ( $credential->name == "token" ) {
                    $token = $credential->value;
                }
            }
            foreach( $details->themes as $theme ) {
                if ( $theme->item->id == $theme_id ) {
                    $api_request = "https://api.envato.com/v3/market/buyer/download?purchase_code={$theme->code}";
                    $response    = wp_remote_get( $api_request, [
                        'headers'     => [
                            'Authorization' => "Bearer $token",
                        ]
                    ]);
                    if ( is_wp_error( $response ) ) {
                        return;
                    }
                    $response = json_decode( $response['body'] );
                    return $response->wordpress_theme;
                }
            }
        }

    }

    public static function download_plugin( $plugin_id ) {

        $providers = ( new \CaptainCore\Providers )->where( [ "provider" => "envato" ] );
        foreach( $providers as $provider ) {
            $plugins     = [];
            $token       = "";
            $credentials = empty( $provider->credentials ) ? (object) [] : json_decode( $provider->credentials );
            $details     = empty( $provider->details ) ? (object) [] : json_decode( $provider->details );
            foreach( $credentials as $credential ) {
                if ( $credential->name == "token" ) {
                    $token = $credential->value;
                }
            }
            foreach( $details->plugins as $plugin ) {
                if ( $plugin->item->id == $plugin_id ) {
                    $api_request = "https://api.envato.com/v3/market/buyer/download?purchase_code={$plugin->code}";
                    $response    = wp_remote_get( $api_request, [
                        'headers'     => [
                            'Authorization' => "Bearer $token",
                        ]
                    ]);
                    if ( is_wp_error( $response ) ) {
                        return;
                    }
                    $response = json_decode( $response['body'] );
                    return $response->wordpress_plugin;
                }
            }
        }
        
    }

}