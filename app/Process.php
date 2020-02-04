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

    public function get_legacy() {
        $post     = get_post( $this->process_id );
        $role_ids = [];
        if ( $terms = get_the_terms( $post->ID, 'process_role' ) ) {
            $role_ids = wp_list_pluck( $terms, 'term_id' );
        }
        $process = (object) [
            'created_at'      => get_the_date( 'Y-m-d H:i:s', $post->ID ),
            'user_id'         => $post->post_author,
            'name'            => $post->post_title,
            'description'     => get_post_meta( $post->ID, 'description', true ),
            'time_estimate'   => get_post_meta( $post->ID, 'time_estimate', true ),
            'repeat_interval' => get_post_meta( $post->ID, 'repeat', true ),
            'repeat_quantity' => get_post_meta( $post->ID, 'repeat_quantity', true ),
            'roles'           => $role_ids[0],
        ];
        return $process;
    }

    public function get_legacy_roles() {
        $terms = get_terms( [ 'taxonomy' => 'process_role' ], [ 'hide_empty' => false, 'parent' => 0,] );
        $roles = [];
        foreach( $terms as $term ) {
            $roles[] = [
                'role_id'     => $term->term_id,
                'name'        => $term->name,
                'description' => $term->description,
            ];
        }
        return $roles;
    }

}