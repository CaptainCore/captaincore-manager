<?php

namespace CaptainCore;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ActivityLogs extends DB {

    static $primary_key = 'activity_log_id';

}
