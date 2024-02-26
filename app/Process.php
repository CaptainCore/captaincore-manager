<?php

namespace CaptainCore;

class Process {

    protected $process_id = "";

    public function __construct( $process_id = "" ) {
        $this->process_id = $process_id;
    }

    public function get() {
        $process              = ( new Processes )->get( $this->process_id );
        $process_repeat       = json_decode( get_option('captaincore_process_repeat') );
        $process_roles        = json_decode( get_option('captaincore_process_roles') );
		$description          = $GLOBALS['wp_embed']->autoembed( $process->description ) ;
        $key                  = array_search( $process->roles, array_column( $process_roles, 'role_id' ) );
        $fetch_process = [
            "process_id"      => $process->process_id,
            "name"            => $process->name,
            "repeat_quantity" => $process->repeat_quantity,
            "repeat"          => $process_repeat->{"$process->repeat_interval"},
            "description"     => ( new \Parsedown )->text( $description ),
            "roles"           => $process_roles[$key]->name,
            "time_estimate"   => $process->time_estimate,

        ];
        if ( $process->roles == "" ) {
            $fetch_process['roles'] = "";
        } else {
            $fetch_process['roles'] = $process_roles[$key]->name;
        }
        return $fetch_process;
    }

}