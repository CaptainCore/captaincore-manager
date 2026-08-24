<?php 

namespace CaptainCore;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Snapshots extends DB {

	static $primary_key = 'snapshot_id';

}