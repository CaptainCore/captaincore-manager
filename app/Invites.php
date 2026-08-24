<?php 

namespace CaptainCore;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Invites extends DB {

	static $primary_key = 'invite_id';

}