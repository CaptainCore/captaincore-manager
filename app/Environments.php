<?php

namespace CaptainCore;

class Environments extends DB {

    static $primary_key = 'environment_id';

    public function filters() {
        $filters     = [];
        $user        = new User;
        $account_ids = $user->accounts();

        if ( $user->is_admin() ) {
            $account_ids = [];
            $filters = self::fetch_filters_for_admins();
        }

        // Loop through each account for current user and fetch SiteIDs
        foreach ( $account_ids as $account_id ) {
            // Fetch filters assigned as owners
            $results = self::fetch_filters_for_account( $account_id );
            if ( is_array( $results ) ) {
                foreach ( $results as $result ) {
                    $filters[] = $result;
                }
            }
            // Fetch filters assigned as shared access
            $results = self::fetch_filters_for_shared_accounts( $account_id );
            if ( is_array( $results ) ) {
                foreach ( $results as $result ) {
                    $filters[] = $result;
                }
            }
        }

        // Pull out themes and plugins and remove empty results
        $results            = (object) [ "plugins" => [], "themes" => [] ];
        $filter_plugins_set = array_filter(array_column( $filters, 'plugins' ));
        $filter_themes_set  = array_filter(array_column( $filters, 'themes' ));

        foreach ( $filter_plugins_set as $filter_plugins ) {
            $plugins = json_decode( $filter_plugins );
            foreach( $plugins as $plugin ) {
                if ( in_array( $plugin->name, array_column( $results->plugins, "name" ) ) ) {
                    continue;
                }
                $title  = html_entity_decode( $plugin->title );
                $search = "{$title} ({$plugin->name})";
                if ( $plugin->title == "" ) {
                    $search = $plugin->name;
                }
                $results->plugins[] = [
                    "name"   => $plugin->name,
                    "title"  => $title,
                    "search" => $search,
                    "type"   => "plugins"
                ];
            }
        }

        foreach ( $filter_themes_set as $filter_themes ) {
            $themes = json_decode( $filter_themes );
            foreach( $themes as $theme ) {
                if ( in_array( $theme->name, array_column( $results->themes, "name" ) ) ) {
                    continue;
                }
                $title  = html_entity_decode( $theme->title );
                $search = "{$title} ({$theme->name})";
                if ( $theme->title == "" ) {
                    $search = $theme->name;
                }
                $results->themes[] = [
                    "name"   => $theme->name,
                    "title"  => $title,
                    "search" => $search,
                    "type"   => "themes"
                ];
            }
        }
        usort($results->themes, function($a, $b) { return strcmp( strtolower($a['search']), strtolower($b['search']) ); });
        usort($results->plugins, function($a, $b) { return strcmp( strtolower($a['search']), strtolower($b['search']) ); });
        return array_merge( [[ 'header' => "Themes" ]], $results->themes, [[ 'header' => "Plugins" ]], $results->plugins );

    }

    public function filters_for_versions( $search_filters = [] ) {
        $filters     = [];
        $user        = new User;
        $account_ids = $user->accounts();

        if ( $user->is_admin() ) {
            $account_ids = [];
            $filters = self::fetch_filters_for_admins( "Production" );
        }

        // Loop through each account for current user and fetch SiteIDs
        foreach ( $account_ids as $account_id ) {
            // Fetch filters assigned as owners
            $results = self::fetch_filters_for_account( $account_id, "Production" );
            if ( is_array( $results ) ) {
                foreach ( $results as $result ) {
                    $filters[] = $result;
                }
            }
            // Fetch filters assigned as shared access
            $results = self::fetch_filters_for_shared_accounts( $account_id, "Production" );
            if ( is_array( $results ) ) {
                foreach ( $results as $result ) {
                    $filters[] = $result;
                }
            }
        }

        // Pull out themes and plugins and remove empty results
        $results            = [];
        $filter_plugins_set = array_filter(array_column( $filters, 'plugins' ));
        $filter_themes_set  = array_filter(array_column( $filters, 'themes' ));

        foreach( $search_filters as $search_filter ) {
            foreach ( $filter_plugins_set as $filter_plugins ) {
                $plugins = json_decode( $filter_plugins );
                foreach( $plugins as $plugin ) {
                    if ( $search_filter == $plugin->name ) {
                        $key   = 'plugin__'. $plugin->name;
                        $count = empty( $results[ $key ][ $plugin->version ] ) ? 1 : $results[ $key ][ $plugin->version ][ "count" ] + 1;
                        $results[ $key ][ $plugin->version ] = [
                            "name"  => $plugin->version,
                            "slug"  => $plugin->name,
                            "type"  => "plugins",
                            "count" => $count,
                        ];
                    }
                }
            }

           foreach ( $filter_themes_set as $filter_themes ) {
                $themes = json_decode( $filter_themes );
                foreach( $themes as $theme ) {
                    if ( $search_filter == $theme->name ) {
                        $key   = 'theme__'. $theme->name;
                        $count = empty( $results[ $key ][ $theme->version ] ) ? 1 : $results[ $key ][ $theme->version ][ "count" ] + 1;
                        $results[ $key ][ $theme->version ] = [
                            "name"  => $theme->version,
                            "slug"  => $theme->name,
                            "type"  => "themes",
                            "count" => $count,
                        ];
                    }
                }
            }
        }

        foreach( $results as $key => $items ) {
            //$items = array_values( $items );
            usort( $items, function( $a, $b ) { return -1 * version_compare ( $b['name'] , $a['name'] ); });
            $results[ $key ] = $items;
        }

        $response  = [];
        foreach($results as $key => $value) {
            $key = str_replace( "theme__", "", $key );
            $key = str_replace( "plugin__", "", $key );
            $response[] = [ 
                "name"     => $key,
                "versions" => $value,
            ];
        }

        //usort($results->themes, function($a, $b) { return strcmp( strtolower($a['search']), strtolower($b['search']) ); });
        //usort($results->plugins, function($a, $b) { return strcmp( strtolower($a['search']), strtolower($b['search']) ); });
        return $response;

    }

    public function filters_for_statuses( $search_filters = [] ) {
        $filters     = [];
        $user        = new User;
        $account_ids = $user->accounts();

        if ( $user->is_admin() ) {
            $account_ids = [];
            $filters = self::fetch_filters_for_admins( "Production" );
        }

        // Loop through each account for current user and fetch SiteIDs
        foreach ( $account_ids as $account_id ) {
            // Fetch filters assigned as owners
            $results = self::fetch_filters_for_account( $account_id, "Production" );
            if ( is_array( $results ) ) {
                foreach ( $results as $result ) {
                    $filters[] = $result;
                }
            }
            // Fetch filters assigned as shared access
            $results = self::fetch_filters_for_shared_accounts( $account_id, "Production" );
            if ( is_array( $results ) ) {
                foreach ( $results as $result ) {
                    $filters[] = $result;
                }
            }
        }

        // Pull out themes and plugins and remove empty results
        $results            = [];
        $filter_plugins_set = array_filter(array_column( $filters, 'plugins' ));
        $filter_themes_set  = array_filter(array_column( $filters, 'themes' ));

        foreach( $search_filters as $search_filter ) {
            foreach ( $filter_plugins_set as $filter_plugins ) {
                $plugins = json_decode( $filter_plugins );
                foreach( $plugins as $plugin ) {
                    if ( $search_filter == $plugin->name ) {
                        $key   = 'plugin__'. $plugin->name;
                        $count = empty( $results[ $key ][ $plugin->status ] ) ? 1 : $results[ $key ][ $plugin->status ][ "count" ] + 1;
                        $results[ $key ][ $plugin->status ] = [
                            "name"  => $plugin->status,
                            "slug"  => $plugin->name,
                            "type"  => "plugins",
                            "count" => $count,
                        ];
                    }
                }
            }

           foreach ( $filter_themes_set as $filter_themes ) {
                $themes = json_decode( $filter_themes );
                foreach( $themes as $theme ) {
                    if ( $search_filter == $theme->name ) {
                        $key   = 'theme__'. $theme->name;
                        $count = empty( $results[ $key ][ $theme->status ] ) ? 1 : $results[ $key ][ $theme->status ][ "count" ] + 1;
                        $results[ $key ][ $theme->status ] = [
                            "name"  => $theme->status,
                            "slug"  => $theme->name,
                            "type"  => "themes",
                            "count" => $count,
                        ];
                    }
                }
            }
        }

        foreach( $results as $key => $items ) {
            usort( $items, function( $a, $b ) { return strcmp($a['name'], $b['name']); });
            $results[ $key ] = $items;
        }

        $response  = [];
        foreach($results as $key => $value) {
            $key = str_replace( "theme__", "", $key );
            $key = str_replace( "plugin__", "", $key );
            $response[] = [ 
                "name"     => $key,
                "statuses" => $value,
            ];
        }

        //usort($results->themes, function($a, $b) { return strcmp( strtolower($a['search']), strtolower($b['search']) ); });
        //usort($results->plugins, function($a, $b) { return strcmp( strtolower($a['search']), strtolower($b['search']) ); });
        return $response;

    }

    public function filters_sites( $versions, $status ) {

    }

}