<?php

namespace CaptainCore;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SecurityThreatTracking extends DB {

	static $primary_key = 'security_threat_tracking_id';

}
