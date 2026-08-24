<?php 

namespace CaptainCore;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AccountUser extends DB {

    static $primary_key = 'account_user_id';

}