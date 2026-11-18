<?php

namespace CaptainCore;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Updates posted to a status page incident. Logic lives in StatusPage. */
class StatusUpdates extends DB {

	static $primary_key = 'status_update_id';

}
