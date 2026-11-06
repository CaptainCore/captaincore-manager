<?php

namespace CaptainCore;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * CaptainCore Chat table model. Rules live in Chat; this is storage only.
 */
class ChatAttachments extends DB {

	static $primary_key = 'chat_attachment_id';

}
