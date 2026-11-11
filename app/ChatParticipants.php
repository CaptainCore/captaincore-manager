<?php

namespace CaptainCore;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * CaptainCore Chat table model: people besides the customer who get a
 * conversation's reply emails. Rules live in ChatNotify; this is storage only.
 */
class ChatParticipants extends DB {

	static $primary_key = 'chat_participant_id';

}
