<?php

namespace CaptainCore;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SiteAudits extends DB {

	static $primary_key = 'site_audit_id';

}
