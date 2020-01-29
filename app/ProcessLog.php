<?php

namespace CaptainCore;

class ProcessLog {

    protected $process_log_id = "";

    public function __construct( $process_log_id = "" ) {
        $this->process_log_id = $process_log_id;
    }

    public function get_legacy() {
        $post     = get_post( $this->process_log_id );
        $role_ids = [];
        if ( $terms   = get_the_terms( $post->ID, 'process_role' ) ) {
            $role_ids = wp_list_pluck( $terms, 'term_id' );
        }
        $process = (object) [
            'process_id'   => get_post_meta( $post->ID, 'process', true ),
            'created_at'   => get_the_date( 'Y-m-d H:i:s', $post->ID ),
            'user_id'      => $post->post_author,
            'description'  => get_post_meta( $post->ID, 'description', true ),
            'public'       => get_post_meta( $post->ID, 'public', true ),
            'status'       => get_post_meta( $post->ID, 'status', true ),
            'completed_at' => get_post_meta( $post->ID, 'completed', true ),
        ];
        return $process;
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
        $item->description_raw = $item->description;
        $item->description     = $Parsedown->text( $item->description );
        $item->author          = get_the_author_meta( 'display_name', $item->user_id );
        $item->websites        = ( new ProcessLogSite )->fetch_sites_for_process_log( [ "process_log_id" => $this->process_log_id ] );
        return $item;
    }

}