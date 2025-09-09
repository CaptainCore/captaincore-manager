<?php

namespace CaptainCore;

class ProcessLog {

    protected $process_log_id = "";

    public function __construct( $process_log_id = "" ) {
        $this->process_log_id = $process_log_id;
    }

    public function insert_sites( $site_ids = [] ) {
        $time_now         = date( 'Y-m-d H:i:s' );
        $process_log_site = new ProcessLogSite();
        foreach( $site_ids as $site_id ) {
            // Fetch current records
            $lookup = $process_log_site->where( [ "process_log_id" => $this->process_log_id, "site_id" => $site_id ] );
            // Add new record
            if ( count($lookup) == 0 ) {
                $process_log_site->insert( [
                    "process_log_id" => $this->process_log_id,
                    "site_id"        => $site_id,
                    'created_at'     => $time_now,
                    'updated_at'     => $time_now,
                ] );
            }
        }
    }

    public function assign_sites( $site_ids = [] ) {
        $time_now         = date( 'Y-m-d H:i:s' );
        $process_log_site = new ProcessLogSite();
        // Fetch current records
        $current_site_ids = array_column ( $process_log_site->where( [ "process_log_id" => $this->process_log_id ] ), "site_id" );
        // Removed current records not found new records.
        foreach ( array_diff( $current_site_ids, $site_ids ) as $site_id ) {
            $records = $process_log_site->where( [ "process_log_id" => $this->process_log_id, "site_id" => $site_id ] );
            foreach ( $records as $record ) {
                $process_log_site->delete( $record->process_log_site_id );
            }
        }
        // Add new records
        foreach ( array_diff( $site_ids, $current_site_ids ) as $site_id ) {
            $process_log_site->insert( [ 
                "process_log_id" => $this->process_log_id, 
                "site_id"        => $site_id,
                'created_at'     => $time_now,
                'updated_at'     => $time_now,
            ] );
        }
    }

    public function get() {
        $Parsedown             = new \Parsedown();
        $process_log           = new ProcessLogs();
        $item                  = $process_log->get( $this->process_log_id );
        $item->name            = ( new Processes() )->get( $item->process_id )->name;
        $item->created_at_raw  = $item->created_at;
        $item->created_at      = strtotime( $item->created_at );
        $item->description_raw = $item->description;
        $item->description     = $Parsedown->text( $item->description );
        $item->author          = get_the_author_meta( 'display_name', $item->user_id );
        $item->author_avatar   = "https://www.gravatar.com/avatar/" . md5( get_the_author_meta( 'email', $item->user_id ) ) . "?s=80&d=mp";
        $item->websites        = ( new ProcessLogSite )->fetch_sites_for_process_log( [ "process_log_id" => $this->process_log_id ] );
        return $item;
    }

    public static function insert( $message = "", $site_id ) {
        $time_now        = date( 'Y-m-d H:i:s' );
        $site_ids        = is_array( $site_id ) ? $site_id : [ $site_id ];
        $process_log_new = (object) [
            "process_id"   => 0,
            'user_id'      => get_current_user_id(),
            'public'       => 1,
            'description'  => $message,
            'status'       => 'completed',
            'created_at'   => $time_now,
            'updated_at'   => $time_now,
            'completed_at' => $time_now
        ];
        $process_log_id_new = ( new ProcessLogs )->insert( (array) $process_log_new );
        ( new \CaptainCore\ProcessLog( $process_log_id_new ) )->assign_sites( $site_ids );
    }

}