<?php

namespace CaptainCore;

class ScheduledReports extends DB {

    static $primary_key = 'scheduled_report_id';

    protected static function table_name() {
        global $wpdb;
        return $wpdb->prefix . 'captaincore_scheduled_reports';
    }

    /**
     * Get all scheduled reports
     */
    public static function all( $sort = "created_at", $sort_order = "DESC" ) {
        global $wpdb;
        $table = self::table_name();
        $results = $wpdb->get_results( "SELECT * FROM {$table} ORDER BY `{$sort}` {$sort_order}" );
        return $results;
    }

    /**
     * Get a single scheduled report
     */
    public static function get( $id ) {
        global $wpdb;
        $table = self::table_name();
        $result = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE scheduled_report_id = %d", $id ) );
        return $result;
    }

    /**
     * Create a new scheduled report
     */
    public static function create( $data ) {
        global $wpdb;
        $table = self::table_name();

        $wpdb->insert( $table, [
            'site_ids'   => json_encode( $data['site_ids'] ),
            'interval'   => $data['interval'],
            'recipient'  => $data['recipient'],
            'created_at' => current_time( 'mysql' ),
            'updated_at' => current_time( 'mysql' ),
            'next_run'   => self::calculate_next_run( $data['interval'] ),
            'user_id'    => get_current_user_id(),
        ] );

        return $wpdb->insert_id;
    }

    /**
     * Update a scheduled report
     */
    public static function update_report( $id, $data ) {
        global $wpdb;
        $table = self::table_name();

        $update_data = [
            'updated_at' => current_time( 'mysql' ),
        ];

        if ( isset( $data['site_ids'] ) ) {
            $update_data['site_ids'] = json_encode( $data['site_ids'] );
        }
        if ( isset( $data['interval'] ) ) {
            $update_data['interval'] = $data['interval'];
            $update_data['next_run'] = self::calculate_next_run( $data['interval'] );
        }
        if ( isset( $data['recipient'] ) ) {
            $update_data['recipient'] = $data['recipient'];
        }

        $wpdb->update( $table, $update_data, [ 'scheduled_report_id' => $id ] );

        return true;
    }

    /**
     * Delete a scheduled report
     */
    public static function delete_report( $id ) {
        global $wpdb;
        $table = self::table_name();
        $wpdb->delete( $table, [ 'scheduled_report_id' => $id ] );
        return true;
    }

    /**
     * Calculate next run date based on interval
     */
    public static function calculate_next_run( $interval ) {
        $now = new \DateTime();

        switch ( $interval ) {
            case 'monthly':
                // First day of next month
                $next = new \DateTime( 'first day of next month' );
                break;
            case 'quarterly':
                // First day of next quarter
                $month = (int) $now->format( 'n' );
                $quarter_month = ( ceil( $month / 3 ) * 3 ) + 1;
                if ( $quarter_month > 12 ) {
                    $quarter_month = 1;
                    $next = new \DateTime( $now->format( 'Y' ) + 1 . '-' . str_pad( $quarter_month, 2, '0', STR_PAD_LEFT ) . '-01' );
                } else {
                    $next = new \DateTime( $now->format( 'Y' ) . '-' . str_pad( $quarter_month, 2, '0', STR_PAD_LEFT ) . '-01' );
                }
                break;
            case 'yearly':
                // First day of next year
                $next = new \DateTime( ( $now->format( 'Y' ) + 1 ) . '-01-01' );
                break;
            default:
                $next = new \DateTime( 'first day of next month' );
        }

        return $next->format( 'Y-m-d H:i:s' );
    }

    /**
     * Get date range for report based on interval
     */
    public static function get_date_range( $interval ) {
        $now = new \DateTime();

        switch ( $interval ) {
            case 'monthly':
                // Previous month
                $start = new \DateTime( 'first day of last month' );
                $end = new \DateTime( 'last day of last month' );
                break;
            case 'quarterly':
                // Previous quarter
                $month = (int) $now->format( 'n' );
                $current_quarter = ceil( $month / 3 );
                $prev_quarter = $current_quarter - 1;
                $year = (int) $now->format( 'Y' );
                if ( $prev_quarter < 1 ) {
                    $prev_quarter = 4;
                    $year--;
                }
                $start_month = ( ( $prev_quarter - 1 ) * 3 ) + 1;
                $end_month = $prev_quarter * 3;
                $start = new \DateTime( $year . '-' . str_pad( $start_month, 2, '0', STR_PAD_LEFT ) . '-01' );
                $end = new \DateTime( $year . '-' . str_pad( $end_month, 2, '0', STR_PAD_LEFT ) . '-' . cal_days_in_month( CAL_GREGORIAN, $end_month, $year ) );
                break;
            case 'yearly':
                // Previous year
                $prev_year = $now->format( 'Y' ) - 1;
                $start = new \DateTime( $prev_year . '-01-01' );
                $end = new \DateTime( $prev_year . '-12-31' );
                break;
            default:
                $start = new \DateTime( 'first day of last month' );
                $end = new \DateTime( 'last day of last month' );
        }

        return [
            'start' => $start->format( 'Y-m-d' ),
            'end'   => $end->format( 'Y-m-d' ),
        ];
    }

    /**
     * Run due scheduled reports
     */
    public static function run_due() {
        $reports = self::all();
        $now = current_time( 'mysql' );

        foreach ( $reports as $report ) {
            if ( $report->next_run <= $now ) {
                self::send_scheduled_report( $report );
            }
        }
    }

    /**
     * Send a scheduled report
     */
    public static function send_scheduled_report( $report ) {
        $site_ids = json_decode( $report->site_ids, true );
        $date_range = self::get_date_range( $report->interval );

        Report::send( $site_ids, $date_range['start'], $date_range['end'], $report->recipient );

        // Update next run time
        global $wpdb;
        $table = self::table_name();
        $wpdb->update( $table, [
            'next_run'   => self::calculate_next_run( $report->interval ),
            'last_run'   => current_time( 'mysql' ),
            'updated_at' => current_time( 'mysql' ),
        ], [ 'scheduled_report_id' => $report->scheduled_report_id ] );
    }

    /**
     * Create database table
     */
    public static function create_table() {
        global $wpdb;
        $table = self::table_name();
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS {$table} (
            scheduled_report_id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            site_ids longtext NOT NULL,
            `interval` varchar(20) NOT NULL,
            recipient varchar(255) NOT NULL,
            user_id bigint(20) unsigned NOT NULL,
            next_run datetime NOT NULL,
            last_run datetime DEFAULT NULL,
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY (scheduled_report_id)
        ) {$charset_collate};";

        require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
        dbDelta( $sql );
    }

}
