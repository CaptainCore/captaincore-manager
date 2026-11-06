<?php

namespace CaptainCore;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * CaptainCore Chat table model. Rules live in Chat; this is storage only.
 */
class ChatConversations extends DB {

	static $primary_key = 'chat_conversation_id';

}
