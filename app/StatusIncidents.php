<?php

namespace CaptainCore;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Status page incidents and maintenance windows. Logic lives in StatusPage. */
class StatusIncidents extends DB {

	static $primary_key = 'status_incident_id';

}
