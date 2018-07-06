<?php

namespace CaptainCore;

class DB {

	private static function _table() {
		global $wpdb;
		$tablename = str_replace( "\\", "_", strtolower( get_called_class() ) );
		return $wpdb->prefix . $tablename;
	}

	private static function _fetch_sql( $value ) {
		global $wpdb;
		$sql = sprintf( 'SELECT * FROM %s WHERE %s = %%s', self::_table(), static::$primary_key );
		return $wpdb->prepare( $sql, $value );
	}

	static function valid_check( $data ) {
		global $wpdb;

		$sql_where = "";
		$sql_where_count = count($data);
		$i = 1;
		foreach($data as $key => $row) {
			if ( $i < $sql_where_count) {
				$sql_where .= "`$key` = '$row' and ";
			} else {
				$sql_where .= "`$key` = '$row'";
			}
			$i++;
		}
		$sql = "SELECT * FROM ".self::_table()." WHERE $sql_where";
		$results = $wpdb->get_results( $sql );
		if ( count($results) != 0 ) {
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
	}

	static function update( $data, $where ) {
		global $wpdb;
		$wpdb->update( self::_table(), $data, $where );
	}

	static function delete( $value ) {
		global $wpdb;
		$sql = sprintf( 'DELETE FROM %s WHERE %s = %%s', self::_table(), static::$primary_key );
		return $wpdb->query( $wpdb->prepare( $sql, $value ) );
	}

	static function fetch_logs( $value ) {
		global $wpdb;
		$value = intval( $value );
		$sql = "SELECT * FROM ".self::_table()." WHERE `site_id` = '$value'";
		$results = $wpdb->get_results( $sql );
		$reponse = [];
		foreach ($results as $result) {

			$update_log = json_decode( $result->update_log );

			foreach ($update_log as $log) {
				$log->type = $result->update_type;
				$log->date = $result->created_at;
				$reponse[] = $log;
			}
		}
		return $reponse;
	}

}

class update_logs extends DB {

	static $primary_key = 'log_id';

}


// Example adding record
// (new update_logs)->insert( array( 'site_id' => 1, 'update_type' => "Plugin", 'update_log' => "json data" ) );

#(new update_logs)->list_records();

# get record
# $r = (new CaptainCore\update_logs)->get( 1 );

# $update_logs = new CaptainCore\update_logs;
# $update_logs->get(1);
# $update_log->insert( array( 'site_id' => 10, 'update_type' => "Theme", 'update_log' => "json data", 'created_at' => current_time( 'mysql') ));

/*
 $json = '[{"date":"2018-06-20-091520","type":"plugin","updates":[{"name":"akismet","old_version":"4.0.7","new_version":"4.0.8","status":"Updated"},{"name":"google-analytics-for-wordpress","old_version":"7.0.6","new_version":"7.0.8","status":"Updated"}]},{"date":"2018-06-22-141016","type":"plugin","updates":[{"name":"gutenberg","old_version":"3.0.1","new_version":"3.1.0","status":"Updated"},{"name":"wp-smush-pro","old_version":"2.7.9.1","new_version":"2.7.9.2","status":"Updated"},{"name":"woocommerce","old_version":"3.4.2","new_version":"3.4.3","status":"Updated"}]},{"date":"2018-06-26-212330","type":"plugin","updates":[{"name":"google-analytics-for-wordpress","old_version":"7.0.8","new_version":"7.0.9","status":"Updated"},{"name":"wordpress-seo","old_version":"7.6.1","new_version":"7.7","status":"Updated"}]},{"date":"2018-06-28-061239","type":"plugin","updates":[{"name":"wordpress-seo","old_version":"7.7","new_version":"7.7.1","status":"Updated"}]},{"date":"2018-06-28-180739","type":"plugin","updates":[{"name":"admin-columns-pro","old_version":"4.2.9","new_version":"4.3.4","status":"Updated"},{"name":"ac-addon-acf","old_version":"2.2.3","new_version":"2.3","status":"Updated"},{"name":"gutenberg","old_version":"3.1.0","new_version":"3.1.1","status":"Updated"},{"name":"wp-mail-smtp","old_version":"1.2.5","new_version":"1.3.0","status":"Updated"}]},{"date":"2018-06-29-143550","type":"plugin","updates":[{"name":"wp-mail-smtp","old_version":"1.3.0","new_version":"1.3.2","status":"Updated"},{"name":"wordpress-seo","old_version":"7.7.1","new_version":"7.7.2","status":"Updated"}]},{"date":"2018-07-04-092457","type":"plugin","updates":[{"name":"wordpress-seo","old_version":"7.7.2","new_version":"7.7.3","status":"Updated"}]}]';

$json_decode = json_decode($json);

foreach ( $json_decode as $row ) {

	// Format for mysql timestamp format
	$date_formatted = substr_replace($row->date," ",10,1);   # "2018-06-20-091520"
	$date_formatted = substr_replace($date_formatted,":",13,0);
	$date_formatted = substr_replace($date_formatted,":",16,0);
	$update_log = json_encode($row->updates);

	$update_log->insert( array( 'site_id' => 10, 'update_type' => $row->type, 'update_log' => $update_log, 'created_at' => $date_formatted ) );
}


foreach


*/
