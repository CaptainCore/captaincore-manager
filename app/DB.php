<?php

namespace CaptainCore;

class DB {

    private static function _table() {
        global $wpdb;
        $tablename = str_replace( '\\', '_', strtolower( get_called_class() ) );
        return $wpdb->prefix . $tablename;
    }

    private static function _fetch_sql( $value ) {
        global $wpdb;
        $sql = sprintf( 'SELECT * FROM %s WHERE %s = %%s', self::_table(), static::$primary_key );
        return $wpdb->prepare( $sql, $value );
    }

    static function valid_check( $data ) {
        global $wpdb;

        $sql_where       = '';
        $sql_where_count = count( $data );
        $i               = 1;
        foreach ( $data as $key => $row ) {
            if ( $i < $sql_where_count ) {
                $sql_where .= "`$key` = '$row' and ";
            } else {
                $sql_where .= "`$key` = '$row'";
            }
            $i++;
        }
        $sql     = 'SELECT * FROM ' . self::_table() . " WHERE $sql_where";
        $results = $wpdb->get_results( $sql );
        if ( count( $results ) != 0 ) {
            return false;
        } else {
            return true;
        }
    }

    static function get( $value ) {
        global $wpdb;
        return $wpdb->get_row( self::_fetch_sql( $value ) );
    }

    static function insert( $data ) {
        global $wpdb;
        $wpdb->insert( self::_table(), $data );
        return $wpdb->insert_id;
    }

    static function update( $data, $where ) {
        global $wpdb;
        $wpdb->update( self::_table(), $data, $where );
    }

    static function delete( $value ) {
        global $wpdb;
        $sql = sprintf( 'DELETE FROM %s WHERE %s = %%s', self::_table(), static::$primary_key );
        return $wpdb->query( $wpdb->prepare( $sql, $value ) );
    }

    static function fetch_logs( $value, $environment_id ) {
        global $wpdb;
        $value          = intval( $value );
        $environment_id = intval( $environment_id );
        $sql            = 'SELECT * FROM ' . self::_table() . " WHERE `site_id` = '$value' and `environment_id` = '$environment_id'";
        $results        = $wpdb->get_results( $sql );
        $response       = [];
        foreach ( $results as $result ) {

            $update_log = json_decode( $result->update_log );

            foreach ( $update_log as $log ) {
                $log->type  = $result->update_type;
                $log->date  = $result->created_at;
                $response[] = $log;
            }
        }
        return $response;
    }

    static function where( $conditions ) {
        global $wpdb;
        $where_statements = [];
        foreach ( $conditions as $row => $value ) {
            $where_statements[] =  "`{$row}` = '{$value}'";
        }
        $where_statements = implode( " AND ", $where_statements );
        $sql = 'SELECT * FROM ' . self::_table() . " WHERE $where_statements order by `created_at` DESC";
        return $wpdb->get_results( $sql );
    }

    static function fetch( $value ) {
        global $wpdb;
        $value = intval( $value );
        $sql   = 'SELECT * FROM ' . self::_table() . " WHERE `site_id` = '$value' order by `created_at` DESC";
        return $wpdb->get_results( $sql );
    }

    static function fetch_environment( $value, $environment_id ) {
        global $wpdb;
        $value          = intval( $value );
        $environment_id = intval( $environment_id );
        $sql            = 'SELECT * FROM ' . self::_table() . " WHERE `site_id` = '$value' and `environment_id` = '$environment_id' order by `created_at` DESC";
        return $wpdb->get_results( $sql );
    }

    static function all( $sort = "created_at", $sort_order = "DESC" ) {
        global $wpdb;
        $sql = 'SELECT * FROM ' . self::_table() . ' order by `' . $sort . '` '. $sort_order;
        return $wpdb->get_results( $sql );
    }

    static function mine( $sort = "created_at", $sort_order = "DESC" ) {
        global $wpdb;
        $user_id = get_current_user_id();
        $sql = 'SELECT * FROM ' . self::_table() . " WHERE user_id = '{$user_id}' order by `{$sort}` {$sort_order}";
        return $wpdb->get_results( $sql );
    }

    static function fetch_recipes( $sort = "created_at", $sort_order = "DESC" ) {
        global $wpdb;
        $user_id = get_current_user_id();
        $sql = 'SELECT * FROM ' . self::_table() . " WHERE user_id = '{$user_id}' or `public` = '1' order by `{$sort}` {$sort_order}";
        return $wpdb->get_results( $sql );
    }

    static function fetch_environments( $value ) {
        global $wpdb;
        $value = intval( $value );
        $sql   = 'SELECT * FROM ' . self::_table() . " WHERE `site_id` = '$value' order by `environment` ASC";
        return $wpdb->get_results( $sql );
    }

    static function fetch_field( $value, $environment, $field ) {
        global $wpdb;
        $value = intval( $value );
        $sql   = "SELECT $field FROM " . self::_table() . " WHERE `site_id` = '$value' and `environment` = '$environment' order by `created_at` DESC";
        return $wpdb->get_results( $sql );
    }

    static function fetch_by_environments( $site_id ) {
        
        $results = [];

        $environment_id = get_field( 'environment_production_id', $site_id );
        if ( $environment_id != "" ) {
            $results["Production"] = self::fetch_environment( $site_id, $environment_id );
        }

        $environment_id = get_field( 'environment_staging_id', $site_id );
        if ( $environment_id != "" ) {
            $results["Staging"] = self::fetch_environment( $site_id, $environment_id );
        }
        
        return $results;
    }

     // Perform CaptainCore database upgrades by running `CaptainCore\DB::upgrade();`
     public static function upgrade( $force = false ) {
        $required_version = 18;
        $version = (int) get_site_option( 'captaincore_db_version' );
    
        if ( $version >= $required_version and $force != true ) {
            echo "Not needed `captaincore_db_version` is v{$version} and required v{$required_version}.";
        }
    
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE `{$wpdb->base_prefix}captaincore_update_logs` (
            log_id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            site_id bigint(20) UNSIGNED NOT NULL,
            environment_id bigint(20) UNSIGNED NOT NULL,
            created_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
            update_type varchar(255),
            update_log longtext,
        PRIMARY KEY  (log_id)
        ) $charset_collate;";
        
        dbDelta($sql);

        $sql = "CREATE TABLE `{$wpdb->base_prefix}captaincore_captures` (
            capture_id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            site_id bigint(20) UNSIGNED NOT NULL,
            environment_id bigint(20) UNSIGNED NOT NULL,
            created_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
            git_commit varchar(100),
            pages longtext,
        PRIMARY KEY  (capture_id)
        ) $charset_collate;";
        
        dbDelta($sql);
    
        $sql = "CREATE TABLE `{$wpdb->base_prefix}captaincore_quicksaves` (
            quicksave_id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            site_id bigint(20) UNSIGNED NOT NULL,
            environment_id bigint(20) UNSIGNED NOT NULL,
            created_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
            git_status varchar(255),
            git_commit varchar(100),
            core varchar(10),
            themes longtext,
            plugins longtext,
        PRIMARY KEY  (quicksave_id)
        ) $charset_collate;";
        
        dbDelta($sql);
    
        $sql = "CREATE TABLE `{$wpdb->base_prefix}captaincore_snapshots` (
            snapshot_id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id bigint(20) UNSIGNED NOT NULL,
            site_id bigint(20) UNSIGNED NOT NULL,
            environment_id bigint(20) UNSIGNED NOT NULL,
            created_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
            snapshot_name varchar(255),
            storage varchar(20),
            email varchar(100),
            notes longtext,
            expires_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
            token varchar(32),
        PRIMARY KEY  (snapshot_id)
        ) $charset_collate;";
        
        dbDelta($sql);
    
        $sql = "CREATE TABLE `{$wpdb->base_prefix}captaincore_environments` (
            environment_id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            site_id bigint(20) UNSIGNED NOT NULL,
            created_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
            updated_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
            environment varchar(255),
            address varchar(255),
            username varchar(255),
            password varchar(255),
            protocol varchar(255),
            port varchar(255),
            fathom varchar(255),
            home_directory varchar(255),
            database_username varchar(255),
            database_password varchar(255),
            offload_enabled boolean,
            offload_provider varchar(255),
            offload_access_key varchar(255),
            offload_secret_key varchar(255),
            offload_bucket varchar(255),
            offload_path varchar(255),
            storage varchar(20),
            visits varchar(20),
            core varchar(10),
            subsite_count varchar(10),
            home_url varchar(255),
            capture_pages longtext,
            themes longtext,
            plugins longtext,
            users longtext,
            screenshot boolean,
            updates_enabled boolean,
            updates_exclude_themes longtext,
            updates_exclude_plugins longtext,
        PRIMARY KEY  (environment_id)
        ) $charset_collate;";
        
        dbDelta($sql);
    
        $sql = "CREATE TABLE `{$wpdb->base_prefix}captaincore_recipes` (
            recipe_id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id bigint(20) UNSIGNED NOT NULL,
            title varchar(255),
            created_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
            updated_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
            content longtext,
            public boolean,
        PRIMARY KEY  (recipe_id)
        ) $charset_collate;";
        
        dbDelta($sql);
    
        $sql = "CREATE TABLE `{$wpdb->base_prefix}captaincore_keys` (
            key_id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id bigint(20) UNSIGNED NOT NULL,
            title varchar(255),
            fingerprint varchar(47),
            created_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
            updated_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
        PRIMARY KEY  (key_id)
        ) $charset_collate;";
        
        dbDelta($sql);
    
        $sql = "CREATE TABLE `{$wpdb->base_prefix}captaincore_invites` (
            invite_id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            account_id bigint(20) UNSIGNED NOT NULL,
            email varchar(255),
            token varchar(255),
            created_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
            updated_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
            accepted_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
        PRIMARY KEY  (invite_id)
        ) $charset_collate;";
        
        dbDelta($sql);
    
        // Permission/relationships data structure for CaptainCore: https://dbdiagram.io/d/5d7d409283427516dc0ba8b3
        $sql = "CREATE TABLE `{$wpdb->base_prefix}captaincore_accounts` (
            account_id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            name varchar(255),
            defaults longtext,
            plan longtext,
            account_usage longtext,
            status varchar(255),
            created_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
            updated_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
        PRIMARY KEY  (account_id)
        ) $charset_collate;";
        
        dbDelta($sql);
    
        $sql = "CREATE TABLE `{$wpdb->base_prefix}captaincore_sites` (
            site_id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            account_id bigint(20) UNSIGNED NOT NULL,
            environment_production_id bigint(20),
            environment_staging_id bigint(20),
            name varchar(255),
            site varchar(255),
            provider varchar(255),
            mailgun varchar(255),
            token varchar(255),
            status varchar(255),
            site_usage longtext,
            created_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
            updated_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
        PRIMARY KEY  (site_id)
        ) $charset_collate;";
        
        dbDelta($sql);
    
        $sql = "CREATE TABLE `{$wpdb->base_prefix}captaincore_domains` (
            domain_id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            name varchar(255),
            created_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
            updated_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
        PRIMARY KEY  (domain_id)
        ) $charset_collate;";
        
        dbDelta($sql);
    
        $sql = "CREATE TABLE `{$wpdb->base_prefix}captaincore_permissions` (
            user_permission_id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id bigint(20) UNSIGNED NOT NULL,
            account_id bigint(20) UNSIGNED NOT NULL,
            level varchar(255),
            created_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
            updated_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
        PRIMARY KEY  (user_permission_id)
        ) $charset_collate;";
        
        dbDelta($sql);
    
        $sql = "CREATE TABLE `{$wpdb->base_prefix}captaincore_account_domain` (
            account_domain_id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            account_id bigint(20) UNSIGNED NOT NULL,
            domain_id bigint(20) UNSIGNED NOT NULL,
            created_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
            updated_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
        PRIMARY KEY  (account_domain_id)
        ) $charset_collate;";
        
        dbDelta($sql);
    
        $sql = "CREATE TABLE `{$wpdb->base_prefix}captaincore_account_site` (
            account_site_id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            account_id bigint(20) UNSIGNED NOT NULL,
            site_id bigint(20) UNSIGNED NOT NULL,
            owner boolean,
            created_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
            updated_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
        PRIMARY KEY  (account_site_id)
        ) $charset_collate;";
        
        dbDelta($sql);
    
        if ( ! empty( $wpdb->last_error ) ) {
            return $wpdb->last_error;
        }
    
        update_site_option( 'captaincore_db_version', $required_version );
        echo "Updated `captaincore_db_version` to v$required_version";
    }

}