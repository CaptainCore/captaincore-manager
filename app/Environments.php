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
                    "type"   => "plugin"
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
                    "type"   => "theme"
                ];
            }
        }
        usort($results->themes, function($a, $b) { return strcmp( strtolower($a['search']), strtolower($b['search']) ); });
        usort($results->plugins, function($a, $b) { return strcmp( strtolower($a['search']), strtolower($b['search']) ); });
        return array_merge( [[ 'header' => "Themes" ]], $results->themes, [[ 'header' => "Plugins" ]], $results->plugins );

    }

}