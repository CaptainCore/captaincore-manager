<?php

namespace CaptainCore;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Email for CaptainCore Chat: unread replies go out by email, and replies to
 * that email come back into the thread.
 *
 * Out. An operator reply that the customer has not read gets emailed. The
 * wait depends on whether the reply reached an open tab (the relay reports
 * that on publish): 20 minutes if it did, 5 if the customer was offline or
 * the relay could not say. Replies inside the wait go out together in one
 * email. A read (the thread on screen in a visible tab) or a customer
 * message cancels the email.
 *
 * In. The email's Reply-To is chat-<conversation>-<hmac>@<reply domain>.
 * A Mailgun route forwards mail for that address to POST /chat/inbound,
 * which checks Mailgun's signature and the address HMAC, trims the quoted
 * history and signature, and adds the reply to the thread. A reply from an
 * address that is not the customer's becomes an internal note instead.
 *
 * Settings (a constant wins over the site option):
 *   CAPTAINCORE_CHAT_REPLY_DOMAIN / captaincore_chat_reply_domain
 *       Domain Mailgun receives for, e.g. mg.anchor.host. Unset: emails
 *       still go out, with no reply address (they link to the chat).
 *   CAPTAINCORE_CHAT_MAILGUN_SIGNING_KEY / captaincore_chat_mailgun_signing_key
 *       Mailgun's HTTP webhook signing key. Unset: /chat/inbound refuses.
 */
class ChatNotify {

	const DELAY_ONLINE  = 1200;
	const DELAY_OFFLINE = 300;
	const MARKER        = '##- Reply above this line -##';
	const MAX_FILES     = 10;
	const MAX_FILE_SIZE = 10485760;

	// ── settings ────────────────────────────────────────────────────────

	public static function reply_domain() {
		$d = defined( 'CAPTAINCORE_CHAT_REPLY_DOMAIN' ) ? CAPTAINCORE_CHAT_REPLY_DOMAIN : get_site_option( 'captaincore_chat_reply_domain', '' );
		$d = strtolower( trim( (string) $d ) );
		return preg_match( '/^[a-z0-9.-]+\.[a-z]{2,}$/', $d ) ? $d : '';
	}

	private static function signing_key() {
		return trim( (string) ( defined( 'CAPTAINCORE_CHAT_MAILGUN_SIGNING_KEY' ) ? CAPTAINCORE_CHAT_MAILGUN_SIGNING_KEY : get_site_option( 'captaincore_chat_mailgun_signing_key', '' ) ) );
	}

	/** The address a customer replies to for this conversation, or ''. */
	public static function reply_address( $conversation_id ) {
		$domain = self::reply_domain();
		$sig    = self::address_sig( (int) $conversation_id );
		return ( $domain === '' || $sig === '' ) ? '' : 'chat-' . (int) $conversation_id . '-' . $sig . '@' . $domain;
	}

	private static function address_sig( $conversation_id ) {
		$key = ChatRelay::derived_key( 'reply.v1' );
		return $key === '' ? '' : substr( hash_hmac( 'sha256', (string) $conversation_id, $key ), 0, 16 );
	}

	// ── out ─────────────────────────────────────────────────────────────

	/**
	 * After an operator reply. $online is how many of the customer's
	 * connections got it live (null: unknown). An earlier deadline already
	 * set is kept, so a quick follow-up never delays the email.
	 */
	public static function schedule( $conversation_id, $online ) {
		global $wpdb;
		$conversation = ChatConversations::get( (int) $conversation_id );
		if ( ! $conversation || (int) $conversation->user_id <= 0 || ! Chat::enabled_for( (int) $conversation->user_id ) ) {
			return;
		}
		$delay = (int) apply_filters( 'captaincore_chat_email_delay', $online ? self::DELAY_ONLINE : self::DELAY_OFFLINE, $online );
		$at    = gmdate( 'Y-m-d H:i:s', time() + max( 0, $delay ) );
		$c     = $wpdb->base_prefix . 'captaincore_chat_conversations';
		$wpdb->query( $wpdb->prepare(
			"UPDATE $c SET notify_after = LEAST(COALESCE(notify_after, %s), %s) WHERE chat_conversation_id = %d",
			$at, $at, $conversation_id
		) );
	}

	/** After a customer read: drop the pending email once nothing is unread. */
	public static function cancel_if_read( $conversation_id, $user_id ) {
		global $wpdb;
		if ( ! self::unread_replies( $conversation_id, $user_id, 0 ) ) {
			$c = $wpdb->base_prefix . 'captaincore_chat_conversations';
			$wpdb->query( $wpdb->prepare( "UPDATE $c SET notify_after = NULL WHERE chat_conversation_id = %d", $conversation_id ) );
		}
	}

	/**
	 * Operator comments the customer has not seen: newer than their read
	 * mark, their own last comment and the last reply already emailed.
	 */
	private static function unread_replies( $conversation_id, $user_id, $notified ) {
		global $wpdb;
		$m     = $wpdb->base_prefix . 'captaincore_chat_messages';
		$since = max( (int) $notified, Chat::last_read( $user_id, $conversation_id ), (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT COALESCE(MAX(chat_message_id), 0) FROM $m WHERE chat_conversation_id = %d AND kind = 'comment' AND author_kind = 'customer'",
			$conversation_id
		) ) );
		return $wpdb->get_results( $wpdb->prepare(
			"SELECT * FROM $m WHERE chat_conversation_id = %d AND chat_message_id > %d AND kind = 'comment' AND internal = 0 AND author_kind = 'operator'
			ORDER BY chat_message_id ASC LIMIT 20",
			$conversation_id, $since
		) ) ?: [];
	}

	/** Send every email that is due. Cron runs it each minute; chat requests nudge it too. */
	public static function run_due( $limit = 20 ) {
		global $wpdb;
		$c   = $wpdb->base_prefix . 'captaincore_chat_conversations';
		$now = gmdate( 'Y-m-d H:i:s' );
		$due = $wpdb->get_results( $wpdb->prepare(
			"SELECT chat_conversation_id, notify_after FROM $c WHERE notify_after IS NOT NULL AND notify_after <= %s ORDER BY notify_after ASC LIMIT %d",
			$now, $limit
		) ) ?: [];
		$sent = 0;
		foreach ( $due as $row ) {
			// Claim the row first, so two runs at once send one email.
			$claimed = $wpdb->query( $wpdb->prepare(
				"UPDATE $c SET notify_after = NULL WHERE chat_conversation_id = %d AND notify_after = %s",
				$row->chat_conversation_id, $row->notify_after
			) );
			if ( $claimed && self::send( (int) $row->chat_conversation_id ) ) {
				$sent++;
			}
		}
		return $sent;
	}

	/** Email the customer their unread replies. Returns whether one went out. */
	public static function send( $conversation_id ) {
		global $wpdb;
		$conversation = ChatConversations::get( (int) $conversation_id );
		if ( ! $conversation || (int) $conversation->user_id <= 0 ) {
			return false;
		}
		$user = get_userdata( (int) $conversation->user_id );
		if ( ! $user || ! is_email( $user->user_email ) || ! Chat::enabled_for( (int) $user->ID ) ) {
			return false;
		}
		$replies = self::unread_replies( (int) $conversation_id, (int) $user->ID, (int) $conversation->notified_message_id );
		if ( ! $replies ) {
			return false;
		}
		$last = (int) end( $replies )->chat_message_id;
		ChatConversations::update( [ 'notified_message_id' => $last ], [ 'chat_conversation_id' => (int) $conversation_id ] );

		$files = Chat::attachments_for( array_map( fn( $r ) => (int) $r->chat_message_id, $replies ) );
		Mailer::send_chat_replies( $user, $conversation, $replies, $files, self::reply_address( $conversation_id ) );

		$event = Chat::append( (int) $conversation_id, [
			'kind' => 'event', 'internal' => 1, 'author_kind' => 'system',
			'body' => ( count( $replies ) === 1 ? 'Unread reply' : count( $replies ) . ' unread replies' ) . ' emailed to ' . $user->user_email,
		] );
		Chat::emit( 'message.created', Chat::present_conversation( ChatConversations::get( (int) $conversation_id ), false ), $event );
		return true;
	}

	// ── in ──────────────────────────────────────────────────────────────

	/**
	 * POST /chat/inbound (a Mailgun route's forward()). Mailgun retries
	 * anything but 200 and 406 for 8 hours, so permanent problems answer 406.
	 */
	public static function inbound( \WP_REST_Request $request ) {
		$key = self::signing_key();
		if ( $key === '' ) {
			return new \WP_Error( 'not_configured', 'Inbound chat email is not configured.', [ 'status' => 503 ] );
		}
		$ts    = (string) $request->get_param( 'timestamp' );
		$token = (string) $request->get_param( 'token' );
		$sig   = (string) $request->get_param( 'signature' );
		if ( ! ctype_digit( $ts ) || $token === '' || ! hash_equals( hash_hmac( 'sha256', $ts . $token, $key ), $sig ) ) {
			return new \WP_Error( 'unauthorized', 'Bad signature.', [ 'status' => 401 ] );
		}
		// Retries span 8 hours; allow 12 and remember each token as long.
		if ( abs( time() - (int) $ts ) > 12 * HOUR_IN_SECONDS ) {
			return new \WP_REST_Response( [ 'error' => 'stale' ], 406 );
		}
		$seen = 'cc_chat_mg_' . md5( $token );
		if ( get_site_transient( $seen ) ) {
			return [ 'ok' => true, 'duplicate' => true ];
		}
		set_site_transient( $seen, 1, 12 * HOUR_IN_SECONDS );

		$result = self::receive( [
			'recipient'      => (string) $request->get_param( 'recipient' ),
			'from'           => (string) $request->get_param( 'from' ),
			'sender'         => (string) $request->get_param( 'sender' ),
			'body_plain'     => (string) $request->get_param( 'body-plain' ),
			'stripped_text'  => (string) $request->get_param( 'stripped-text' ),
			'headers'        => (string) $request->get_param( 'message-headers' ),
			'content_id_map' => (string) $request->get_param( 'content-id-map' ),
			'files'          => $request->get_file_params(),
		] );
		if ( is_wp_error( $result ) ) {
			return new \WP_REST_Response( [ 'error' => $result->get_error_code() ], 406 );
		}
		return [ 'ok' => true ] + $result;
	}

	/**
	 * Add an inbound email to its conversation. Separate from inbound() so
	 * tests can call it without a Mailgun signature.
	 *
	 * @return array|\WP_Error message_id, as ('customer'|'note'), or skipped.
	 */
	public static function receive( array $mail ) {
		global $wpdb;
		$conversation = null;
		foreach ( preg_split( '/[,;]/', strtolower( $mail['recipient'] ) ) as $rcpt ) {
			if ( preg_match( '/\bchat-(\d+)-([a-f0-9]{16})@([a-z0-9.-]+)/', $rcpt, $m )
				&& $m[3] === self::reply_domain() && hash_equals( self::address_sig( (int) $m[1] ), $m[2] ) ) {
				$conversation = ChatConversations::get( (int) $m[1] );
				break;
			}
		}
		if ( ! $conversation ) {
			return new \WP_Error( 'unknown_address', 'No conversation for this address.' );
		}
		$conversation_id = (int) $conversation->chat_conversation_id;

		$message_id = self::header( $mail['headers'], 'Message-Id' );
		$external   = $message_id !== '' ? 'mail:' . substr( sha1( $message_id ), 0, 40 ) : '';
		if ( $external !== '' ) {
			$m      = $wpdb->base_prefix . 'captaincore_chat_messages';
			$exists = $wpdb->get_var( $wpdb->prepare( "SELECT chat_message_id FROM $m WHERE chat_conversation_id = %d AND external_id = %s", $conversation_id, $external ) );
			if ( $exists ) {
				return [ 'message_id' => (int) $exists, 'skipped' => 'duplicate' ];
			}
		}

		$original = Chat::strip_controls( $mail['body_plain'] );
		if ( ! mb_check_encoding( $original, 'UTF-8' ) ) {
			$original = mb_convert_encoding( $original, 'UTF-8', 'UTF-8' );
		}
		$body  = self::clean_reply( $mail['stripped_text'], $mail['body_plain'] );
		$files = self::incoming_files( $mail['files'], $mail['content_id_map'] );
		if ( $body === '' && ! $files ) {
			return [ 'message_id' => 0, 'skipped' => 'empty' ];
		}

		$from     = self::address_of( $mail['from'] ) ?: self::address_of( $mail['sender'] );
		$user     = (int) $conversation->user_id > 0 ? get_userdata( (int) $conversation->user_id ) : null;
		$known    = array_filter( [ $user ? strtolower( $user->user_email ) : '', strtolower( (string) $conversation->contact_email ) ] );
		$customer = $from !== '' && in_array( $from, $known, true );

		if ( $customer ) {
			$message = Chat::append( $conversation_id, [
				'kind' => 'comment', 'author_kind' => 'customer', 'author_user_id' => $user ? (int) $user->ID : 0,
				'author_name' => $user ? $user->display_name : (string) $conversation->contact_name,
				'body' => $body !== '' ? $body : '(sent ' . ( count( $files ) === 1 ? 'a file' : 'files' ) . ')',
				'via' => 'email', 'original' => mb_substr( $original, 0, 100000 ), 'external_id' => $external,
			] );
		} else {
			// Someone else replied (a forward, a colleague, a spoof). Operators
			// see it and decide; the customer's thread does not change.
			$message = Chat::append( $conversation_id, [
				'kind' => 'note', 'author_kind' => 'system', 'author_name' => $from !== '' ? $from : 'Unknown sender',
				'body' => 'Email reply from ' . ( $from !== '' ? $from : 'an unknown sender' ) . ", which is not the customer's address. The customer does not see this.\n\n" . ( $body !== '' ? $body : '(files only)' ),
				'via' => 'email', 'original' => mb_substr( $original, 0, 100000 ), 'external_id' => $external,
			] );
		}
		foreach ( $files as $file ) {
			self::store_file( $conversation_id, (int) $message['id'], $file );
		}
		if ( $customer && $user ) {
			// Replying means they read it: move their read mark to the end.
			$r = $wpdb->base_prefix . 'captaincore_chat_reads';
			$wpdb->query( $wpdb->prepare(
				"INSERT INTO $r (user_id, chat_conversation_id, last_read_message_id, updated_at) VALUES (%d, %d, %d, %s)
				ON DUPLICATE KEY UPDATE last_read_message_id = GREATEST(last_read_message_id, VALUES(last_read_message_id)), updated_at = VALUES(updated_at)",
				$user->ID, $conversation_id, $message['id'], gmdate( 'Y-m-d H:i:s' )
			) );
		}
		$attachments = Chat::attachments_for( [ (int) $message['id'] ] );
		$message     = Chat::present_message( ChatMessages::get( (int) $message['id'] ), $attachments[ (int) $message['id'] ] ?? [] );
		$fresh       = Chat::present_conversation( ChatConversations::get( $conversation_id ), false );
		Chat::emit( 'message.created', $fresh, $message );
		if ( $customer ) {
			ChatPush::customer_message( $fresh, $message );
		}
		Chat::emit( 'conversation.updated', $fresh );
		return [ 'message_id' => (int) $message['id'], 'as' => $customer ? 'customer' : 'note' ];
	}

	/**
	 * The new part of an email reply. Mailgun's stripped-text already drops
	 * quoted history and signatures; this catches what it misses (and does
	 * the whole job when stripped-text is missing).
	 */
	public static function clean_reply( $stripped, $plain ) {
		$text  = trim( (string) $stripped ) !== '' ? (string) $stripped : (string) $plain;
		$text  = str_replace( [ "\r\n", "\r" ], "\n", $text );
		if ( ! mb_check_encoding( $text, 'UTF-8' ) ) {
			$text = mb_convert_encoding( $text, 'UTF-8', 'UTF-8' );
		}
		$lines = explode( "\n", Chat::strip_controls( $text ) );
		$keep  = [];
		$stops = [
			'/reply above this line/i',
			'/^\s*-{2,}\s*(original|forwarded) message\s*-{2,}/i',
			'/^\s*_{20,}\s*$/',
			'/^\s*(sent from my |sent from mail for |sent from yahoo mail|get outlook for )/i',
			'/^-- ?$/',
			'/^\s*on\b.{0,300}\bwrote:\s*$/i',
			'/^\s*le\b.{0,300}\ba écrit\s*:\s*$/iu',
			'/^\s*am\b.{0,300}\bschrieb\b.{0,200}:\s*$/i',
			'/^\s*el\b.{0,300}\bescribió:\s*$/iu',
		];
		$n = count( $lines );
		for ( $i = 0; $i < $n; $i++ ) {
			$line = $lines[ $i ];
			foreach ( $stops as $re ) {
				if ( preg_match( $re, $line ) ) {
					break 2;
				}
			}
			// "On <date>, <name>" with "wrote:" wrapped onto the next line.
			if ( preg_match( '/^\s*on\b.{0,200}$/i', $line ) && isset( $lines[ $i + 1 ] ) && preg_match( '/^.{0,200}\bwrote:\s*$/i', $lines[ $i + 1 ] ) && preg_match( '/\d/', $line . $lines[ $i + 1 ] ) ) {
				break;
			}
			// Outlook's header block: From: then Sent:/Date:/To:/Subject: nearby.
			if ( preg_match( '/^\s*\*?from:\*?\s/i', $line ) ) {
				$block = implode( "\n", array_slice( $lines, $i + 1, 4 ) );
				if ( preg_match( '/^\s*\*?(sent|date|to|subject):/im', $block ) ) {
					break;
				}
			}
			if ( preg_match( '/^\s*>/', $line ) ) {
				continue;
			}
			$keep[] = rtrim( $line );
		}
		$out = trim( (string) preg_replace( "/\n{3,}/", "\n\n", implode( "\n", $keep ) ) );
		return mb_strlen( $out ) > Chat::MAX_BODY ? rtrim( mb_substr( $out, 0, Chat::MAX_BODY - 1 ) ) . '…' : $out;
	}

	/** The bare lowercase address from "Name <a@b.c>" or "a@b.c". */
	private static function address_of( $value ) {
		$value = (string) $value;
		if ( preg_match( '/<([^<>\s]+@[^<>\s]+)>/', $value, $m ) ) {
			$value = $m[1];
		}
		$value = strtolower( trim( $value ) );
		return is_email( $value ) ? $value : '';
	}

	/** One header from Mailgun's message-headers (a JSON list of [name, value]). */
	private static function header( $json, $name ) {
		$headers = json_decode( (string) $json, true );
		foreach ( is_array( $headers ) ? $headers : [] as $h ) {
			if ( is_array( $h ) && isset( $h[0], $h[1] ) && strcasecmp( (string) $h[0], $name ) === 0 ) {
				return trim( (string) $h[1] );
			}
		}
		return '';
	}

	/**
	 * Uploaded attachment-N files worth keeping. Small inline images are
	 * signature logos and are dropped; larger inline ones are pasted
	 * screenshots and stay.
	 */
	private static function incoming_files( $files, $content_id_map ) {
		$inline = array_values( (array) json_decode( (string) $content_id_map, true ) );
		$out    = [];
		foreach ( (array) $files as $field => $f ) {
			if ( ! preg_match( '/^attachment-\d+$/', (string) $field ) || ! is_array( $f ) || (int) ( $f['error'] ?? 1 ) !== 0 ) {
				continue;
			}
			$size = (int) ( $f['size'] ?? 0 );
			if ( $size <= 0 || $size > self::MAX_FILE_SIZE || ( ! is_uploaded_file( (string) $f['tmp_name'] ) && ! defined( 'CAPTAINCORE_CHAT_TESTING' ) ) ) {
				continue;
			}
			if ( in_array( $field, $inline, true ) && $size < 30720 ) {
				continue;
			}
			$out[] = $f;
			if ( count( $out ) >= self::MAX_FILES ) {
				break;
			}
		}
		return $out;
	}

	/** Store one uploaded file the way the importer does (by sha256, outside the web root). */
	private static function store_file( $conversation_id, $message_id, array $f ) {
		$src  = (string) $f['tmp_name'];
		$sha  = hash_file( 'sha256', $src );
		$name = sanitize_file_name( (string) ( $f['name'] ?? '' ) ) ?: 'file';
		$ext  = strtolower( (string) pathinfo( $name, PATHINFO_EXTENSION ) );
		$ext  = preg_match( '/^[a-z0-9]{1,5}$/', $ext ) ? '.' . $ext : '';
		$rel  = $conversation_id . '/' . $sha . $ext;
		$dest = Chat::base_dir() . '/' . $rel;
		if ( ! $sha || ( ! is_dir( dirname( $dest ) ) && ! wp_mkdir_p( dirname( $dest ) ) ) ) {
			return false;
		}
		if ( ! is_file( $dest ) ) {
			if ( ! copy( $src, $dest ) ) {
				return false;
			}
			chmod( $dest, 0600 );
		}
		$mime = function_exists( 'finfo_open' ) ? (string) finfo_file( finfo_open( FILEINFO_MIME_TYPE ), $dest ) : (string) ( $f['type'] ?? '' );
		ChatAttachments::insert( [
			'chat_message_id'      => (int) $message_id,
			'chat_conversation_id' => (int) $conversation_id,
			'name'                 => mb_substr( $name, 0, 255 ),
			'mime'                 => mb_substr( $mime, 0, 100 ),
			'size'                 => (int) filesize( $dest ),
			'sha256'               => $sha,
			'storage_path'         => $rel,
			'created_at'           => gmdate( 'Y-m-d H:i:s' ),
		] );
		return true;
	}
}
