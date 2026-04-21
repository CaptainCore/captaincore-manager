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
        if ( ! $item ) {
            return null;
        }
        $process               = ( new Processes() )->get( $item->process_id );
        $item->name            = $process ? $process->name : '';
        $item->created_at_raw  = $item->created_at;
        $item->created_at      = strtotime( $item->created_at );
        $item->description_raw = $item->description;
        $item->description     = $Parsedown->text( $item->description );
        $item->author          = get_the_author_meta( 'display_name', $item->user_id );
        $item->author_avatar   = "https://www.gravatar.com/avatar/" . md5( get_the_author_meta( 'email', $item->user_id ) ) . "?s=80&d=mp";
        $item->websites        = ( new ProcessLogSite )->fetch_sites_for_process_log( [ "process_log_id" => $this->process_log_id ] );
        $item->files           = $this->files();
        return $item;
    }

    /**
     * Fetch file change records attached to this process log, with hunks decoded.
     */
    public function files() {
        $rows = ( new ProcessLogFile )->where( [ "process_log_id" => $this->process_log_id ] );
        $files = [];
        foreach ( $rows as $row ) {
            $hunks = ! empty( $row->hunks ) ? json_decode( $row->hunks ) : [];
            if ( ! is_array( $hunks ) ) {
                $hunks = [];
            }
            $files[] = (object) [
                'process_log_file_id' => (int) $row->process_log_file_id,
                'process_log_id'      => (int) $row->process_log_id,
                'site_id'             => $row->site_id !== null ? (int) $row->site_id : null,
                'file_path'           => $row->file_path,
                'change_type'         => $row->change_type,
                'hunks'               => $hunks,
                'lines_added'         => (int) $row->lines_added,
                'lines_removed'       => (int) $row->lines_removed,
                'created_at'          => $row->created_at,
            ];
        }
        return $files;
    }

    /**
     * Replace the file change set for this log entry.
     *
     * @param array $files            Array of file objects/arrays: path, change_type, hunks[], site_id?
     * @param int   $default_site_id  Site ID applied when a file doesn't specify one.
     */
    public function assign_files( $files = [], $default_site_id = null ) {
        $process_log_file = new ProcessLogFile();
        foreach ( $process_log_file->where( [ "process_log_id" => $this->process_log_id ] ) as $existing ) {
            $process_log_file->delete( $existing->process_log_file_id );
        }
        if ( empty( $files ) ) {
            return;
        }
        $time_now = date( 'Y-m-d H:i:s' );
        foreach ( $files as $file ) {
            $file  = (array) $file;
            $path  = isset( $file['path'] ) ? (string) $file['path'] : '';
            if ( $path === '' ) {
                continue;
            }
            $hunks_in = isset( $file['hunks'] ) && is_array( $file['hunks'] ) ? $file['hunks'] : [];
            $hunks    = [];
            $added    = 0;
            $removed  = 0;
            foreach ( $hunks_in as $hunk ) {
                $hunk = (array) $hunk;
                $normalized = [
                    'line_start'     => isset( $hunk['line_start'] ) ? (int) $hunk['line_start'] : 0,
                    'context_before' => isset( $hunk['context_before'] ) ? array_values( (array) $hunk['context_before'] ) : [],
                    'removed'        => isset( $hunk['removed'] ) ? array_values( (array) $hunk['removed'] ) : [],
                    'added'          => isset( $hunk['added'] ) ? array_values( (array) $hunk['added'] ) : [],
                    'context_after'  => isset( $hunk['context_after'] ) ? array_values( (array) $hunk['context_after'] ) : [],
                ];
                $added   += count( $normalized['added'] );
                $removed += count( $normalized['removed'] );
                $hunks[]  = $normalized;
            }
            if ( isset( $file['lines_added'] ) ) {
                $added = (int) $file['lines_added'];
            }
            if ( isset( $file['lines_removed'] ) ) {
                $removed = (int) $file['lines_removed'];
            }
            $change_type = isset( $file['change_type'] ) ? (string) $file['change_type'] : 'modified';
            $site_id     = array_key_exists( 'site_id', $file ) && $file['site_id'] !== null && $file['site_id'] !== ''
                ? (int) $file['site_id']
                : ( $default_site_id !== null ? (int) $default_site_id : null );
            $process_log_file->insert( [
                'process_log_id' => $this->process_log_id,
                'site_id'        => $site_id,
                'file_path'      => $path,
                'change_type'    => $change_type,
                'hunks'          => wp_json_encode( $hunks ),
                'lines_added'    => $added,
                'lines_removed'  => $removed,
                'created_at'     => $time_now,
            ] );
        }
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