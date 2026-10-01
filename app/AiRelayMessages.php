<?php

namespace CaptainCore;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AiRelayMessages extends DB {

	static $primary_key = 'ai_relay_message_id';

	/**
	 * A project's thread, oldest first, by primary key so two messages in the
	 * same second keep their order.
	 */
	public static function thread( $project_id ) {
		global $wpdb;
		$table = $wpdb->prefix . 'captaincore_ai_relay_messages';
		return $wpdb->get_results( $wpdb->prepare(
			"SELECT * FROM $table WHERE ai_relay_project_id = %d ORDER BY ai_relay_message_id ASC",
			$project_id
		) );
	}

}
