<?php 

namespace CaptainCore;

class WebRiskLogs extends DB {

	static $primary_key = 'web_risk_log_id';

	public function list() {
		$results = [];
		$logs    = self::all( 'created_at', 'DESC' );
		foreach ( $logs as $log ) {
			$log->details = json_decode( $log->details );
			$results[]    = $log;
		}
		return $results;
	}

}
