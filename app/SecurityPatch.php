<?php

namespace CaptainCore;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SecurityPatch extends DB {

	static $primary_key = 'security_patch_id';

}
