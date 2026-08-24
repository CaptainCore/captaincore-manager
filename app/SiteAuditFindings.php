<?php

namespace CaptainCore;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SiteAuditFindings extends DB {

	static $primary_key = 'site_audit_finding_id';

}
