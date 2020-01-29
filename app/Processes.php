<?php 

namespace CaptainCore;

class Processes extends DB {

	static $primary_key = 'process_id';

	protected $processes = [];

    public function __construct( $processes = [] ) {
        $user       = wp_get_current_user();
        $role_check = in_array( 'administrator', $user->roles );

        // Bail if not an administrator
        if ( ! $role_check ) {
            return 'Error: Please log in.';
        }

        // Administrators return all sites
		$this->processes = self::all( "name", "ASC" );
        return;
    }

	public function list($sort = 'created_at') {
		$process_repeat = json_decode( get_option('captaincore_process_repeat') );
		$process_roles  = json_decode( get_option('captaincore_process_roles') );
		$processes      = [];
        foreach( $this->processes as $item ) {
			$process    = self::get( $item->process_id );
			$key        = array_search( $process->roles, array_column( $process_roles, 'role_id' ) );
			$process->repeat_interval = $process_repeat->{$process->repeat_interval};
			$process->roles = $process_roles[$key]->name;
            $processes[]    = $process;
        }
        return $processes;
    }

}