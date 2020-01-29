<?php

namespace CaptainCore;

class DB {

    private static function _table() {
        global $wpdb;
        $tablename = explode( '\\', get_called_class(), 2 );
        $tablename[0] = strtolower ( $tablename[0] );
        // Add '_' before each capitalized letter and trim the first
        $tablename[1] = strtolower ( trim ( preg_replace( '/([A-Z])/', '_$1', $tablename[1] ), "_" ) );
        $tablename = implode ( '_', $tablename);
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
        return $wpdb->update( self::_table(), $data, $where );
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
        $sql            = 'SELECT * FROM ' . self::_table() . " WHERE `site_id` = '$value' and `environment_id` = '$environment_id' order by `created_at` DESC";
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

    static function select( $field = "site_id", $where = "status", $value = "active", $sort = "created_at", $sort_order = "DESC" ) {
        global $wpdb;
        $sql = "SELECT $field FROM " . self::_table() . " WHERE $where = '{$value}' order by `{$sort}` {$sort_order}";
        $results = array_column( $wpdb->get_results( $sql ), $field );
        return $results;
    }

    static function select_by_conditions( $field = "environment_id", $conditions = [] ) {
        global $wpdb;
        $table = self::_table();
        $where_statements = [];
        foreach ( $conditions as $row => $value ) {
            $where_statements[] =  "{$table}.{$row} = '{$value}'";
        }
        $where_statements = implode( " AND ", $where_statements );
        $sql = "SELECT {$table}.{$field} FROM {$table} WHERE $where_statements";
        $results = array_column( $wpdb->get_results( $sql ), $field );
        return $results;
    }

    static function select_domains( $field = "domain_id", $sort = "name", $sort_order = "ASC" ) {
        global $wpdb;
        $sql = "SELECT $field FROM " . self::_table() . " order by `{$sort}` {$sort_order}";
        $results = array_column( $wpdb->get_results( $sql ), $field );
        return $results;
    }
    
    static function select_all( $field = "site_id" ) {
        global $wpdb;
        $sql = "SELECT $field FROM " . self::_table() . " order by `created_at` DESC";
        $results = array_column( $wpdb->get_results( $sql ), $field );
        return $results;
    }

    static function select_active_sites( $field = "site_id", $conditions = [] ) {
        global $wpdb;
        $table = self::_table();
        $where_statements = [];
        foreach ( $conditions as $row => $value ) {
            $where_statements[] =  "{$table}.{$row} = '{$value}'";
        }
        $where_statements = implode( " AND ", $where_statements );
        $sql = "SELECT {$table}.{$field} FROM {$table} INNER JOIN wp_captaincore_sites ON {$table}.site_id = wp_captaincore_sites.site_id WHERE $where_statements AND wp_captaincore_sites.status = 'active'";
        $results = array_column( $wpdb->get_results( $sql ), $field );
        return $results;
    }

    static function select_domains_for_account( $account_ids = [] ) {
        global $wpdb;
        $table   = self::_table();
        $ids     = implode( ",", $account_ids );
        $sql     = "SELECT {$table}.domain_id FROM {$table} INNER JOIN wp_captaincore_domains ON {$table}.domain_id = wp_captaincore_domains.domain_id WHERE {$table}.account_id in ({$ids})";
        $results = array_column( $wpdb->get_results( $sql ), 'domain_id' );
        return $results;
    }

    static function fetch_domains( $conditions = [] ) {
        global $wpdb;
        $table = self::_table();
        $where_statements = [];
        foreach ( $conditions as $row => $value ) {
            if ( is_array($value) ) {
                $values = implode( ", ", $value );
                $where_statements[] =  "{$table}.{$row} IN ($values)";
                continue;
            }
            $where_statements[] =  "{$table}.{$row} = '{$value}'";
        }
        $where_statements = implode( " AND ", $where_statements );
        $sql = "SELECT {$table}.domain_id, {$wpdb->prefix}captaincore_domains.name
                FROM {$table}
                INNER JOIN {$wpdb->prefix}captaincore_domains ON {$table}.domain_id = {$wpdb->prefix}captaincore_domains.domain_id
                WHERE $where_statements 
                order by {$wpdb->prefix}captaincore_domains.`name` ASC";
        $results = array_column( $wpdb->get_results( $sql ), $field );
        return $results;
    }

    static function fetch_filters_for_account( $account_id = "" ) {
        global $wpdb;
        $table = self::_table();
        $sql = "SELECT {$table}.themes, {$table}.plugins
                FROM {$table}
                INNER JOIN {$wpdb->prefix}captaincore_sites ON {$table}.site_id = {$wpdb->prefix}captaincore_sites.site_id
                WHERE {$wpdb->prefix}captaincore_sites.account_id = $account_id AND {$wpdb->prefix}captaincore_sites.`status` = 'active'";
        $results = $wpdb->get_results( $sql );
        return $results;
    }

    static function fetch_filters_for_shared_accounts( $account_id = "" ) {
        global $wpdb;
        $table = self::_table();
        $sql = "SELECT {$table}.themes, {$table}.plugins
                FROM {$table}
                INNER JOIN {$wpdb->prefix}captaincore_sites ON {$table}.site_id = {$wpdb->prefix}captaincore_sites.site_id
                INNER JOIN {$wpdb->prefix}captaincore_account_site ON {$table}.site_id = {$wpdb->prefix}captaincore_account_site.site_id
                INNER JOIN {$wpdb->prefix}captaincore_accounts ON {$wpdb->prefix}captaincore_account_site.account_id = {$wpdb->prefix}captaincore_accounts.account_id
                WHERE {$wpdb->prefix}captaincore_accounts.account_id = $account_id AND {$wpdb->prefix}captaincore_sites.`status` = 'active'";
        $results = $wpdb->get_results( $sql );
        return $results;
    }

    static function fetch_process_logs( $conditions = [] ) {
        global $wpdb;
        $table = self::_table();
        $where_statements = [];
        foreach ( $conditions as $row => $value ) {
            if ( is_array($value) ) {
                $values = implode( ", ", $value );
                $where_statements[] =  "{$table}.{$row} IN ($values)";
                continue;
            }
            $where_statements[] =  "{$table}.{$row} = '{$value}'";
        }
        $where_statements = implode( " AND ", $where_statements );
        $sql = "SELECT {$table}.process_log_id, {$wpdb->prefix}captaincore_processes.name
                FROM {$table}
                INNER JOIN {$wpdb->prefix}captaincore_process_logs ON {$table}.process_log_id = {$wpdb->prefix}captaincore_process_logs.process_log_id
                LEFT JOIN {$wpdb->prefix}captaincore_processes ON {$wpdb->prefix}captaincore_process_logs.process_id = {$wpdb->prefix}captaincore_processes.process_id
                WHERE $where_statements 
                order by {$wpdb->prefix}captaincore_process_logs.`created_at` DESC";
        $results = array_column( $wpdb->get_results( $sql ), $field );
        return $results;
    }

    static function fetch_sites_for_account( $account_id = "" ) {
        global $wpdb;
        $table = self::_table();
        $sql = "SELECT *
                FROM {$table}
                INNER JOIN {$wpdb->prefix}captaincore_account_site ON {$wpdb->prefix}captaincore_account_site.account_id = {$table}.account_id
                INNER JOIN {$wpdb->prefix}captaincore_accounts ON {$wpdb->prefix}captaincore_accounts.account_id = {$table}.account_id
                WHERE {$table}.account_id = $account_id OR {$wpdb->prefix}captaincore_account_site.account_id = $account_id
                order by {$wpdb->prefix}captaincore_sites.`name` ASC";
        $results = $wpdb->get_results( $sql );
        return $results;
    }

    static function fetch_sites_matching( $arguments = [] ) {
        global $wpdb;
        $arguments = (object) $arguments;
        $filter    = (object) $arguments->filter;
        $table     = self::_table();
        $provider_conditions    = "";
        $environment_conditions = "";
        $target_conditions      = [];
        $field_selection        = "";
        $environment_columns    = [ "address", "username", "password", "protocol", "port", "home_directory", "database_username", "database_password", "storage", "visits", "core", "fathom", "home_url", "themes", "plugins", "updates_enabled", "updates_exclude_themes", "updates_exclude_plugins", "screenshot", "capture_pages" ];
        if ( ! empty( $arguments->provider ) ) {
            $provider_conditions = "AND {$table}.provider = '{$arguments->provider}'";
        }
        if ( $arguments->environment != "all" ) {
            $environment_conditions = "AND {$wpdb->prefix}captaincore_environments.environment = '{$arguments->environment}'";
        }

        if ( ! empty( $arguments->field ) ) {
            $field_selection = ", {$table}.{$arguments->field}";
        }

        if ( count( $arguments->targets ) > 0 ) {

        }
        if ( in_array("updates-on", $arguments->targets ) ) {
            $target_conditions[] = "AND {$wpdb->prefix}captaincore_environments.updates_enabled = '1'";
        }
        if ( in_array("updates-off", $arguments->targets ) ) {
            $target_conditions[] = "AND {$wpdb->prefix}captaincore_environments.updates_enabled = '0'";
        }
        if ( in_array("offload-on", $arguments->targets ) ) {
            $target_conditions[] = "AND {$wpdb->prefix}captaincore_environments.offload_enabled = '1'";
        }
        if ( in_array("offload-off", $arguments->targets ) ) {
            $target_conditions[] = "AND {$wpdb->prefix}captaincore_environments.offload_enabled = '0'";
        }
        $target_conditions = implode( $target_conditions, " " );

        if ( ! empty( $arguments->field ) && in_array( $arguments->field, $environment_columns ) ) {
            $field_selection = ", {$wpdb->prefix}captaincore_environments.{$arguments->field}";
        }

        if ( empty( $filter->type ) ) {
            $sql = "SELECT {$table}.site, {$wpdb->prefix}captaincore_environments.environment $field_selection
                    FROM {$table}
                    INNER JOIN {$wpdb->prefix}captaincore_environments ON {$table}.site_id = {$wpdb->prefix}captaincore_environments.site_id
                    WHERE {$table}.status = 'active' $provider_conditions $environment_conditions $target_conditions
                    order by {$wpdb->prefix}captaincore_sites.`name` ASC";
            $results = $wpdb->get_results( $sql );
            return $results;
        }

        if ( $filter->type == "core" ) {
            $sql = "SELECT {$table}.site, {$wpdb->prefix}captaincore_environments.environment $field_selection
                    FROM {$table}
                    INNER JOIN {$wpdb->prefix}captaincore_environments ON {$table}.site_id = {$wpdb->prefix}captaincore_environments.site_id
                    WHERE {$wpdb->prefix}captaincore_environments.core = '{$filter->version}' $provider_conditions $environment_conditions $target_conditions
                    AND {$table}.status = 'active'
                    order by {$wpdb->prefix}captaincore_sites.`name` ASC";
            $results = $wpdb->get_results( $sql );
            return $results;
        }

        if ( empty( $filter->name ) ) {
            $filter->name = '[^"]*';
        }
        if ( empty( $filter->status ) ) {
            $filter->status = '[^"]*';
        }
        if ( empty( $filter->version ) ) {
            $filter->version = '[^"]*';
        }

        // WordPress thinks {} in SQL is a syntax error. To workaround we can wrap them in brackets likes so [{] and [}].
        $pattern = '{"name":"'.$filter->name.'","title":"[^"]*","status":"'.$filter->status.'","version":"'.$filter->version.'"}';
        $pattern = str_replace ( "{", "[{]", $pattern );
        $pattern = str_replace ( "}", "[}]", $pattern );

        $sql = "SELECT {$table}.site, {$wpdb->prefix}captaincore_environments.environment $field_selection
                FROM {$table}
                INNER JOIN {$wpdb->prefix}captaincore_environments ON {$table}.site_id = {$wpdb->prefix}captaincore_environments.site_id
                WHERE {$wpdb->prefix}captaincore_environments.{$filter->type} REGEXP '{$pattern}' $provider_conditions $environment_conditions $target_conditions
                AND {$table}.status = 'active'
                order by {$wpdb->prefix}captaincore_sites.`name` ASC";
        $results = $wpdb->get_results( $sql );
        return $results;
    }

    static function fetch_sites_for_process_log( $conditions = [] ) {
        global $wpdb;
        $table = self::_table();
        $where_statements = [];
        foreach ( $conditions as $row => $value ) {
            $where_statements[] =  "{$table}.{$row} = '{$value}'";
        }
        $where_statements = implode( " AND ", $where_statements );
        $sql = "SELECT {$table}.site_id, {$wpdb->prefix}captaincore_sites.name
                FROM {$table}
                INNER JOIN {$wpdb->prefix}captaincore_sites ON {$table}.site_id = {$wpdb->prefix}captaincore_sites.site_id
                WHERE $where_statements 
                order by {$wpdb->prefix}captaincore_sites.`name` ASC";
        $results = array_column( $wpdb->get_results( $sql ), $field );
        return $results;
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

     // Perform CaptainCore database upgrades by running `CaptainCore\DB::upgrade();`
     public static function upgrade( $force = false ) {
        $required_version = (int) "19";
        $version          = (int) get_site_option( 'captaincore_db_version' );
    
        if ( $version >= $required_version and $force != true ) {
            echo "Not needed `captaincore_db_version` is v{$version} and required v{$required_version}.";
            return;
        }
    
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE `{$wpdb->base_prefix}captaincore_update_logs` (
            log_id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            site_id bigint(20) UNSIGNED NOT NULL,
            environment_id bigint(20) UNSIGNED NOT NULL,
            created_at datetime NOT NULL,
            update_type varchar(255),
            update_log longtext,
        PRIMARY KEY  (log_id)
        ) $charset_collate;";
        
        dbDelta($sql);

        $sql = "CREATE TABLE `{$wpdb->base_prefix}captaincore_captures` (
            capture_id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            site_id bigint(20) UNSIGNED NOT NULL,
            environment_id bigint(20) UNSIGNED NOT NULL,
            created_at datetime NOT NULL,
            git_commit varchar(100),
            pages longtext,
        PRIMARY KEY  (capture_id)
        ) $charset_collate;";
        
        dbDelta($sql);
    
        $sql = "CREATE TABLE `{$wpdb->base_prefix}captaincore_quicksaves` (
            quicksave_id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            site_id bigint(20) UNSIGNED NOT NULL,
            environment_id bigint(20) UNSIGNED NOT NULL,
            created_at datetime NOT NULL,
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
            created_at datetime NOT NULL,
            snapshot_name varchar(255),
            storage varchar(20),
            email varchar(100),
            notes longtext,
            expires_at datetime NOT NULL,
            token varchar(32),
        PRIMARY KEY  (snapshot_id)
        ) $charset_collate;";
        
        dbDelta($sql);
    
        $sql = "CREATE TABLE `{$wpdb->base_prefix}captaincore_environments` (
            environment_id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            site_id bigint(20) UNSIGNED NOT NULL,
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
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

        $sql = "CREATE TABLE `{$wpdb->base_prefix}captaincore_processes` (
            process_id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id bigint(20) UNSIGNED NOT NULL,
            name varchar(255),
            description longtext,
            time_estimate varchar(100),
            repeat_interval varchar(100),
            repeat_quantity varchar(100),
            roles longtext,
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
        PRIMARY KEY  (process_id)
        ) $charset_collate;";
        
        dbDelta($sql);

        $sql = "CREATE TABLE `{$wpdb->base_prefix}captaincore_process_logs` (
            process_log_id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            process_id bigint(20) UNSIGNED NOT NULL,
            user_id bigint(20) UNSIGNED NOT NULL,
            description longtext,
            public boolean,
            status varchar(50),
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            completed_at datetime NOT NULL,
        PRIMARY KEY  (process_log_id)
        ) $charset_collate;";

        dbDelta($sql);

        $sql = "CREATE TABLE `{$wpdb->base_prefix}captaincore_process_log_site` (
            process_log_site_id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            process_log_id bigint(20) UNSIGNED NOT NULL,
            site_id bigint(20) UNSIGNED NOT NULL,
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
        PRIMARY KEY  (process_log_site_id)
        ) $charset_collate;";
        
        dbDelta($sql);
    
        $sql = "CREATE TABLE `{$wpdb->base_prefix}captaincore_recipes` (
            recipe_id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id bigint(20) UNSIGNED NOT NULL,
            title varchar(255),
            public boolean,
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
        PRIMARY KEY  (recipe_id)
        ) $charset_collate;";
        
        dbDelta($sql);
    
        $sql = "CREATE TABLE `{$wpdb->base_prefix}captaincore_keys` (
            key_id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id bigint(20) UNSIGNED NOT NULL,
            title varchar(255),
            fingerprint varchar(47),
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
        PRIMARY KEY  (key_id)
        ) $charset_collate;";
        
        dbDelta($sql);
    
        $sql = "CREATE TABLE `{$wpdb->base_prefix}captaincore_invites` (
            invite_id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            account_id bigint(20) UNSIGNED NOT NULL,
            email varchar(255),
            token varchar(255),
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            accepted_at datetime NOT NULL,
        PRIMARY KEY  (invite_id)
        ) $charset_collate;";
        
        dbDelta($sql);
    
        // Permission/relationships data structure for CaptainCore: https://dbdiagram.io/d/5d7d409283427516dc0ba8b3
        $sql = "CREATE TABLE `{$wpdb->base_prefix}captaincore_accounts` (
            account_id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            name varchar(255),
            defaults longtext,
            plan longtext,
            metrics varchar(255),
            status varchar(255),
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
        PRIMARY KEY  (account_id)
        ) $charset_collate;";
        
        dbDelta($sql);
    
        $sql = "CREATE TABLE `{$wpdb->base_prefix}captaincore_sites` (
            site_id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            account_id bigint(20) UNSIGNED NOT NULL,
            name varchar(255),
            site varchar(255),
            provider varchar(255),
            token varchar(255),
            status varchar(255),
            details longtext,
            screenshot boolean,
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
        PRIMARY KEY  (site_id)
        ) $charset_collate;";
        
        dbDelta($sql);
    
        $sql = "CREATE TABLE `{$wpdb->base_prefix}captaincore_domains` (
            domain_id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            remote_id varchar(255),
            name varchar(255),
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
        PRIMARY KEY  (domain_id)
        ) $charset_collate;";
        
        dbDelta($sql);
    
        $sql = "CREATE TABLE `{$wpdb->base_prefix}captaincore_account_user` (
            account_user_id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            account_id bigint(20) UNSIGNED NOT NULL,
            user_id bigint(20) UNSIGNED NOT NULL,
            level varchar(255),
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
        PRIMARY KEY  (account_user_id)
        ) $charset_collate;";
        
        dbDelta($sql);
    
        $sql = "CREATE TABLE `{$wpdb->base_prefix}captaincore_account_domain` (
            account_domain_id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            account_id bigint(20) UNSIGNED NOT NULL,
            domain_id bigint(20) UNSIGNED NOT NULL,
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
        PRIMARY KEY  (account_domain_id)
        ) $charset_collate;";
        
        dbDelta($sql);
    
        $sql = "CREATE TABLE `{$wpdb->base_prefix}captaincore_account_site` (
            account_site_id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            account_id bigint(20) UNSIGNED NOT NULL,
            site_id bigint(20) UNSIGNED NOT NULL,
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
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