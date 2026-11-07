<?php

namespace CaptainCore;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Web Push subscriptions (operators' devices). Rules live in ChatPush;
 * this is storage only.
 */
class PushSubscriptions extends DB {

	static $primary_key = 'push_subscription_id';

}
