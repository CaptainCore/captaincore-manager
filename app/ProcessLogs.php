<?php 

namespace CaptainCore;

class ProcessLogs extends DB {

	static $primary_key = 'process_log_id';

	public function list() {

		$results = [];
		$process_log_ids = self::select_all( 'process_log_id' );
		foreach ( $process_log_ids as $process_log_id ) {
			$result = ( new ProcessLog( $process_log_id ) )->get();
			if ( $result ) {
				$results[] = $result;
			}
		}
		return $results;

	}

}