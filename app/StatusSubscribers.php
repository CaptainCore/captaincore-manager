<?php

namespace CaptainCore;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** People emailed when the status page changes. Logic lives in StatusPage. */
class StatusSubscribers extends DB {

	static $primary_key = 'status_subscriber_id';

}
