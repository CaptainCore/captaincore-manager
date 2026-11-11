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
 * Out. An operator message the customer has not read gets emailed. The
 * wait depends on whether it reached an open tab (the relay reports that on
 * publish): 10 minutes if it did, 5 if the customer was offline or the relay
 * could not say, none when chat is not on for them yet (email is the only
 * way it reaches them, and the email then has no link into the Manager).
 * Messages inside the wait go out together in one email. A read (the thread
 * on screen in a visible tab) or a customer message cancels the email.
 *
 * In. The email's Reply-To is chat-<conversation>-<hmac>@<reply domain>.
 * A Mailgun route forwards mail for that address to POST /chat/inbound,
 * which checks Mailgun's signature and the address HMAC, trims the quoted
 * history and signature, and adds the reply to the thread. A reply from an
 * address that is not the customer's becomes an internal note instead.
 *
 * Others on the email. Addresses on the To or Cc of the customer's email
 * are suggested to operators, never added on their own. Once an operator
 * adds someone (participants, status 'active') they are Cc'd on reply
 * emails, and their replies post as messages under their own name. They
 * get email only: no login, no chat. Removing them stops both.
 *
 * Settings (a constant wins over the site option):
 *   CAPTAINCORE_CHAT_REPLY_DOMAIN / captaincore_chat_reply_domain
 *       Domain Mailgun receives for, e.g. mg.anchor.host. Unset: emails
 *       still go out, with no reply address (they link to the chat).
 *   CAPTAINCORE_CHAT_MAILGUN_SIGNING_KEY / captaincore_chat_mailgun_signing_key
 *       Mailgun's HTTP webhook signing key. Unset: /chat/inbound refuses.
 */
class ChatNotify {

	const DELAY_ONLINE  = 600;
	const DELAY_OFFLINE = 300;
	const EARLIER_MAX   = 3;
	const EARLIER_GAP   = 7 * DAY_IN_SECONDS;
	const EARLIER_CHARS = 500;
	const MARKER        = '##- Reply above this line -##';
	const MAX_FILES     = 10;
	const MAX_FILE_SIZE = 26214400; // Mailgun takes 25 MB per message

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

	/**
	 * Message-Id (without brackets) for the email that carries replies up to
	 * $message_id, or ''. Signed, so a reply whose In-Reply-To is one of these
	 * provably answers the email we sent to the customer's address.
	 */
	public static function message_id( $conversation_id, $message_id ) {
		$key = ChatRelay::derived_key( 'message-id.v1' );
		if ( $key === '' ) {
			return '';
		}
		$domain = self::reply_domain() ?: (string) wp_parse_url( home_url(), PHP_URL_HOST );
		$c      = (int) $conversation_id;
		$m      = (int) $message_id;
		return "chat-{$c}-{$m}-" . substr( hash_hmac( 'sha256', "{$c}:{$m}", $key ), 0, 16 ) . "@{$domain}";
	}

	/**
	 * Whether an In-Reply-To header names an email we sent for this
	 * conversation. Mail that a customer's address forwards on automatically
	 * keeps our Message-Id, so a reply from that mailbox points straight back
	 * at it. A hand forward ("Fwd:") is a new message, so a reply to it does not.
	 */
	private static function answers_our_email( $conversation_id, $in_reply_to ) {
		preg_match_all( '/<([^<>\s]+)>/', (string) $in_reply_to, $ids );
		foreach ( $ids[1] as $id ) {
			if ( preg_match( '/^chat-(\d+)-(\d+)-[a-f0-9]{16}@/i', $id, $m ) && (int) $m[1] === (int) $conversation_id ) {
				$expect = self::message_id( (int) $m[1], (int) $m[2] );
				if ( $expect !== '' && hash_equals( strtolower( $expect ), strtolower( $id ) ) ) {
					return true;
				}
			}
		}
		return false;
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
		if ( ! $conversation || (int) $conversation->user_id <= 0 ) {
			return;
		}
		$delay = ! Chat::enabled_for( (int) $conversation->user_id ) ? 0
			: (int) apply_filters( 'captaincore_chat_email_delay', $online ? self::DELAY_ONLINE : self::DELAY_OFFLINE, $online );
		$at    = gmdate( 'Y-m-d H:i:s', time() + max( 0, $delay ) );
		$c     = $wpdb->base_prefix . 'captaincore_chat_conversations';
		$wpdb->query( $wpdb->prepare(
			"UPDATE $c SET notify_after = LEAST(COALESCE(notify_after, %s), %s) WHERE chat_conversation_id = %d",
			$at, $at, $conversation_id
		) );
	}

	/**
	 * After a customer read: drop the pending email once nothing is unread.
	 * Participants only ever read by email, so theirs still goes.
	 */
	public static function cancel_if_read( $conversation_id, $user_id ) {
		global $wpdb;
		if ( ! self::unread_replies( $conversation_id, $user_id, 0 ) && ! self::participant_replies( $conversation_id ) ) {
			$c = $wpdb->base_prefix . 'captaincore_chat_conversations';
			$wpdb->query( $wpdb->prepare( "UPDATE $c SET notify_after = NULL WHERE chat_conversation_id = %d", $conversation_id ) );
		}
	}

	/**
	 * Operator comments the customer has not seen: newer than their read
	 * mark, their own last comment and the last reply already emailed.
	 * (A participant's message is not the customer's, so it doesn't count.)
	 */
	private static function unread_replies( $conversation_id, $user_id, $notified ) {
		global $wpdb;
		$m     = $wpdb->base_prefix . 'captaincore_chat_messages';
		$since = max( (int) $notified, Chat::last_read( $user_id, $conversation_id ), (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT COALESCE(MAX(chat_message_id), 0) FROM $m WHERE chat_conversation_id = %d AND kind = 'comment' AND author_kind = 'customer' AND author_user_id = %d",
			$conversation_id, $user_id
		) ) );
		return $wpdb->get_results( $wpdb->prepare(
			"SELECT * FROM $m WHERE chat_conversation_id = %d AND chat_message_id > %d AND kind = 'comment' AND internal = 0 AND author_kind = 'operator'
			ORDER BY chat_message_id ASC LIMIT 20",
			$conversation_id, $since
		) ) ?: [];
	}

	/**
	 * Context for the email: the public comments before $first, newest
	 * first. Up to EARLIER_MAX, stopping at a gap of more than EARLIER_GAP
	 * (a gap usually means a new topic). The customer's latest message
	 * always comes along, since it is what the reply answers. Notes and
	 * events never do.
	 */
	private static function earlier( $conversation_id, $first ) {
		global $wpdb;
		$m    = $wpdb->base_prefix . 'captaincore_chat_messages';
		$rows = $wpdb->get_results( $wpdb->prepare(
			"SELECT * FROM $m WHERE chat_conversation_id = %d AND chat_message_id < %d AND kind = 'comment' AND internal = 0
			ORDER BY chat_message_id DESC LIMIT 50",
			$conversation_id, (int) $first->chat_message_id
		) ) ?: [];
		$out  = [];
		$prev = strtotime( $first->created_at . ' UTC' );
		foreach ( $rows as $r ) {
			$at = strtotime( $r->created_at . ' UTC' );
			if ( count( $out ) >= self::EARLIER_MAX || $prev - $at > self::EARLIER_GAP ) {
				break;
			}
			$out[] = $r;
			$prev  = $at;
		}
		if ( ! array_filter( $out, fn( $r ) => $r->author_kind === 'customer' ) ) {
			foreach ( $rows as $r ) {
				if ( $r->author_kind === 'customer' ) {
					$out[] = $r;
					break;
				}
			}
		}
		return $out;
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

	/**
	 * Email the unread replies: to the customer (with participants on Cc)
	 * when they have some unread, else to the participants alone. Returns
	 * whether one went out.
	 */
	public static function send( $conversation_id ) {
		$conversation = ChatConversations::get( (int) $conversation_id );
		if ( ! $conversation || (int) $conversation->user_id <= 0 ) {
			return false;
		}
		$user = get_userdata( (int) $conversation->user_id );
		if ( ! $user || ! is_email( $user->user_email ) || Chat::is_operator( (int) $user->ID ) ) {
			return false;
		}
		$replies = self::unread_replies( (int) $conversation_id, (int) $user->ID, (int) $conversation->notified_message_id );
		$others  = self::participant_replies( (int) $conversation_id );
		$all     = [];
		foreach ( array_merge( $replies, $others ) as $r ) {
			$all[ (int) $r->chat_message_id ] = $r;
		}
		if ( ! $all ) {
			return false;
		}
		ksort( $all );
		$all  = array_values( $all );
		$last = (int) end( $all )->chat_message_id;
		// Participants are on it only when it has something they haven't had.
		$cc   = $others ? array_map( fn( $p ) => self::mailbox( $p->name, $p->email ), self::active( (int) $conversation_id ) ) : [];
		if ( $replies ) {
			ChatConversations::update( [ 'notified_message_id' => $last ], [ 'chat_conversation_id' => (int) $conversation_id ] );
			$to = $user->user_email;
		} else {
			$to = array_shift( $cc );
		}
		if ( $others ) {
			global $wpdb;
			$p = $wpdb->base_prefix . 'captaincore_chat_participants';
			$wpdb->query( $wpdb->prepare( "UPDATE $p SET notified_message_id = GREATEST(notified_message_id, %d) WHERE chat_conversation_id = %d AND status = 'active'", $last, $conversation_id ) );
		}

		$earlier = self::earlier( (int) $conversation_id, $all[0] );
		$files   = Chat::attachments_for( array_map( fn( $r ) => (int) $r->chat_message_id, array_merge( $all, $earlier ) ) );
		// Only the customer can open the conversation; an email to the
		// participants alone has no button.
		Mailer::send_chat_replies( $user, $conversation, $all, $files, self::reply_address( $conversation_id ), $replies && Chat::enabled_for( (int) $user->ID ), self::message_id( $conversation_id, $last ), $earlier, $to, $cc );

		$sent_to = array_merge( [ self::address_of( $to ) ], array_map( [ __CLASS__, 'address_of' ], $cc ) );
		$event   = Chat::append( (int) $conversation_id, [
			'kind' => 'event', 'internal' => 1, 'author_kind' => 'system',
			'body' => ( count( $all ) === 1 ? 'Unread reply' : count( $all ) . ' unread replies' ) . ' emailed to ' . implode( ', ', $sent_to ),
		] );
		Chat::emit( 'message.created', Chat::present_conversation( ChatConversations::get( (int) $conversation_id ), false ), $event );
		return true;
	}

	// ── participants ────────────────────────────────────────────────────

	const MAX_PARTICIPANTS = 10;

	/** Active participants, oldest first. */
	public static function active( $conversation_id ) {
		global $wpdb;
		$p = $wpdb->base_prefix . 'captaincore_chat_participants';
		return $wpdb->get_results( $wpdb->prepare(
			"SELECT * FROM $p WHERE chat_conversation_id = %d AND status = 'active' ORDER BY chat_participant_id ASC",
			$conversation_id
		) ) ?: [];
	}

	/** Operator comments not yet emailed to every active participant. */
	private static function participant_replies( $conversation_id ) {
		global $wpdb;
		$p     = $wpdb->base_prefix . 'captaincore_chat_participants';
		$since = $wpdb->get_var( $wpdb->prepare( "SELECT MIN(notified_message_id) FROM $p WHERE chat_conversation_id = %d AND status = 'active'", $conversation_id ) );
		if ( $since === null ) {
			return [];
		}
		$m = $wpdb->base_prefix . 'captaincore_chat_messages';
		return $wpdb->get_results( $wpdb->prepare(
			"SELECT * FROM $m WHERE chat_conversation_id = %d AND chat_message_id > %d AND kind = 'comment' AND internal = 0 AND author_kind = 'operator'
			ORDER BY chat_message_id ASC LIMIT 20",
			$conversation_id, (int) $since
		) ) ?: [];
	}

	/**
	 * The conversation's participants for the thread: operators get every
	 * suggested and active one, the customer only who else is emailed.
	 */
	public static function participants( $conversation_id, $operator ) {
		global $wpdb;
		$p    = $wpdb->base_prefix . 'captaincore_chat_participants';
		$rows = $wpdb->get_results( $wpdb->prepare(
			"SELECT * FROM $p WHERE chat_conversation_id = %d AND status IN ('active', 'suggested') ORDER BY chat_participant_id ASC",
			$conversation_id
		) ) ?: [];
		$out = [];
		foreach ( $rows as $r ) {
			if ( ! $operator && $r->status !== 'active' ) {
				continue;
			}
			$out[] = [ 'id' => (int) $r->chat_participant_id, 'email' => $r->email, 'name' => $r->name, 'status' => $r->status ];
		}
		return $out;
	}

	/**
	 * Add someone to the emails (status active), stop emailing them or
	 * dismiss a suggestion (status removed). Operators only. Leaves an
	 * internal event so the thread shows who changed it.
	 *
	 * @param array $input email, status (active|removed), name (optional)
	 */
	public static function set_participant( $user_id, $conversation_id, $input ) {
		global $wpdb;
		$conversation = Chat::viewable( $user_id, $conversation_id );
		if ( ! $conversation || ! Chat::is_operator( $user_id ) ) {
			return new \WP_Error( 'not_found', 'Conversation not found.', [ 'status' => 404 ] );
		}
		if ( (int) $conversation->user_id <= 0 ) {
			return new \WP_Error( 'guest', 'Guest conversations are not emailed, so nobody can be added.', [ 'status' => 400 ] );
		}
		$email  = self::address_of( $input['email'] ?? '' );
		$status = (string) ( $input['status'] ?? 'active' );
		if ( $email === '' ) {
			return new \WP_Error( 'bad_email', 'That is not an email address.', [ 'status' => 400 ] );
		}
		if ( ! in_array( $status, [ 'active', 'removed' ], true ) ) {
			return new \WP_Error( 'bad_status', 'Status is active or removed.', [ 'status' => 400 ] );
		}
		$p   = $wpdb->base_prefix . 'captaincore_chat_participants';
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $p WHERE chat_conversation_id = %d AND email = %s", $conversation_id, $email ) );
		$now = gmdate( 'Y-m-d H:i:s' );
		if ( $status === 'active' ) {
			$why = self::not_addable( $conversation, $email );
			if ( $why !== '' ) {
				return new \WP_Error( 'not_addable', $why, [ 'status' => 400 ] );
			}
			if ( ( ! $row || $row->status !== 'active' ) && count( self::active( $conversation_id ) ) >= self::MAX_PARTICIPANTS ) {
				return new \WP_Error( 'too_many', 'A conversation can email at most ' . self::MAX_PARTICIPANTS . ' other people.', [ 'status' => 400 ] );
			}
		}
		if ( $row && $row->status === $status ) {
			return [ 'participants' => self::participants( $conversation_id, true ) ];
		}
		if ( ! $row && $status === 'removed' ) {
			return [ 'participants' => self::participants( $conversation_id, true ) ];
		}
		$name = self::display_name( $input['name'] ?? ( $row->name ?? '' ) );
		// Starting now: they get replies from here on, the earlier ones as
		// context under the first one.
		$m    = $wpdb->base_prefix . 'captaincore_chat_messages';
		$mark = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COALESCE(MAX(chat_message_id), 0) FROM $m WHERE chat_conversation_id = %d", $conversation_id ) );
		if ( $row ) {
			$set = [ 'status' => $status, 'name' => $name, 'added_by' => (int) $user_id, 'updated_at' => $now ];
			if ( $status === 'active' ) {
				$set['notified_message_id'] = $mark;
			}
			ChatParticipants::update( $set, [ 'chat_participant_id' => (int) $row->chat_participant_id ] );
		} else {
			ChatParticipants::insert( [
				'chat_conversation_id' => (int) $conversation_id, 'email' => $email, 'name' => $name, 'status' => 'active',
				'added_by' => (int) $user_id, 'notified_message_id' => $mark, 'created_at' => $now, 'updated_at' => $now,
			] );
		}
		$who   = get_userdata( (int) $user_id );
		$label = $name !== '' ? $name . ' (' . $email . ')' : $email;
		$body  = $status === 'active' ? 'added ' . $label . ' to the emails'
			: ( $row && $row->status === 'suggested' ? 'dismissed ' . $label . ' from the email suggestions' : 'stopped emailing ' . $label );
		$event = Chat::append( (int) $conversation_id, [
			'kind' => 'event', 'internal' => 1, 'author_kind' => 'system',
			'body' => ( $who ? $who->display_name : 'Someone' ) . ' ' . $body,
		] );
		Chat::emit( 'message.created', Chat::present_conversation( ChatConversations::get( (int) $conversation_id ), false ), $event );
		self::emit_participants( $conversation );
		return [ 'participants' => self::participants( $conversation_id, true ) ];
	}

	/** Why $email can't be a participant here, or ''. */
	private static function not_addable( $conversation, $email ) {
		$user = get_userdata( (int) $conversation->user_id );
		if ( in_array( $email, array_filter( [ $user ? strtolower( $user->user_email ) : '', strtolower( (string) $conversation->contact_email ) ] ), true ) ) {
			return 'That is the customer\'s own address.';
		}
		$domain = self::reply_domain();
		if ( $domain !== '' && substr( $email, -strlen( '@' . $domain ) ) === '@' . $domain ) {
			return 'That is one of our own reply addresses.';
		}
		$staff = get_user_by( 'email', $email );
		if ( $staff && Chat::is_operator( (int) $staff->ID ) ) {
			return 'That is a staff address. Staff see the conversation already.';
		}
		return '';
	}

	/**
	 * Addresses on an email the customer (or a participant) sent in become
	 * suggestions: the To and Cc, less the customer, our own addresses,
	 * staff, and anyone already active, suggested or removed.
	 */
	private static function suggest( $conversation, $headers, $from ) {
		$found = [];
		foreach ( [ 'To', 'Cc' ] as $h ) {
			foreach ( self::mailboxes( self::header( $headers, $h ) ) as $email => $name ) {
				if ( $email !== $from && ! isset( $found[ $email ] ) && self::not_addable( $conversation, $email ) === '' ) {
					$found[ $email ] = $name;
				}
			}
		}
		if ( ! $found ) {
			return false;
		}
		$id  = (int) $conversation->chat_conversation_id;
		$now = gmdate( 'Y-m-d H:i:s' );
		$new = false;
		foreach ( array_slice( $found, 0, self::MAX_PARTICIPANTS, true ) as $email => $name ) {
			global $wpdb;
			$p = $wpdb->base_prefix . 'captaincore_chat_participants';
			$wpdb->query( $wpdb->prepare(
				"INSERT IGNORE INTO $p (chat_conversation_id, email, name, status, created_at, updated_at) VALUES (%d, %s, %s, 'suggested', %s, %s)",
				$id, $email, $name, $now, $now
			) );
			$new = $new || $wpdb->rows_affected > 0;
		}
		return $new;
	}

	/** Tell anyone viewing the conversation to reload its participants. */
	private static function emit_participants( $conversation ) {
		do_action( 'captaincore_chat_event', [ 'type' => 'participants.updated', 'conversation_id' => (int) $conversation->chat_conversation_id ], (int) $conversation->user_id, false );
	}

	/** A person's name from a header or a form: one line, at most 100 characters. */
	private static function display_name( $text ) {
		$text = (string) $text;
		if ( ! mb_check_encoding( $text, 'UTF-8' ) ) {
			$text = mb_convert_encoding( $text, 'UTF-8', 'UTF-8' );
		}
		return mb_substr( trim( (string) preg_replace( '/\s+/u', ' ', Chat::strip_controls( $text ) ) ), 0, 100 );
	}

	/** "Name <email>" with the name made safe for a header. */
	private static function mailbox( $name, $email ) {
		$name = trim( str_replace( [ '"', '\\', "\r", "\n", '<', '>', ',', ';' ], '', (string) $name ) );
		return $name !== '' ? '"' . $name . '" <' . $email . '>' : $email;
	}

	/** email => display name for every address in a To/Cc header value. */
	private static function mailboxes( $value ) {
		$out = [];
		// Split on commas outside quotes.
		foreach ( preg_split( '/,(?=(?:[^"]*"[^"]*")*[^"]*$)/', (string) $value ) as $part ) {
			$email = self::address_of( $part );
			if ( $email === '' ) {
				continue;
			}
			$name          = preg_match( '/^\s*"?([^"<]*?)"?\s*</', $part, $m ) ? self::display_name( $m[1] ) : '';
			$out[ $email ] = $name;
		}
		return $out;
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
		// Not their address, but a direct reply to an email we sent them: their
		// mail forwards to this mailbox. It counts as the customer's reply, and
		// email_from keeps the address it really came from.
		if ( ! $customer && $from !== '' && $known && self::answers_our_email( $conversation_id, self::header( $mail['headers'], 'In-Reply-To' ) ) ) {
			$customer = true;
		}
		// Someone an operator added to the emails: their reply is a message.
		$participant = null;
		if ( ! $customer && $from !== '' ) {
			foreach ( self::active( $conversation_id ) as $p ) {
				if ( $p->email === $from ) {
					$participant = $p;
				}
			}
		}

		if ( $participant ) {
			$message = Chat::append( $conversation_id, [
				'kind' => 'comment', 'author_kind' => 'customer', 'author_user_id' => 0,
				'author_name' => $participant->name !== '' ? $participant->name : $participant->email,
				'body' => $body !== '' ? $body : '(sent ' . ( count( $files ) === 1 ? 'a file' : 'files' ) . ')',
				'via' => 'email', 'original' => mb_substr( $original, 0, 100000 ), 'external_id' => $external, 'email_from' => $from,
			] );
		} elseif ( $customer ) {
			$message = Chat::append( $conversation_id, [
				'kind' => 'comment', 'author_kind' => 'customer', 'author_user_id' => $user ? (int) $user->ID : 0,
				'author_name' => $user ? $user->display_name : (string) $conversation->contact_name,
				'body' => $body !== '' ? $body : '(sent ' . ( count( $files ) === 1 ? 'a file' : 'files' ) . ')',
				'via' => 'email', 'original' => mb_substr( $original, 0, 100000 ), 'external_id' => $external, 'email_from' => $from,
			] );
		} else {
			// Someone else replied (a forward, a colleague, a spoof). Operators
			// see it and decide; the customer's thread does not change.
			$message = Chat::append( $conversation_id, [
				'kind' => 'note', 'author_kind' => 'system', 'author_name' => $from !== '' ? $from : 'Unknown sender',
				'body' => 'Email reply from ' . ( $from !== '' ? $from : 'an unknown sender' ) . ", which is not the customer's address. The customer does not see this.\n\n" . ( $body !== '' ? $body : '(files only)' ),
				'via' => 'email', 'original' => mb_substr( $original, 0, 100000 ), 'external_id' => $external, 'email_from' => $from,
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
		// Others on the customer's (or a participant's) email: suggested to
		// operators, never added on their own.
		if ( ( $customer || $participant ) && (int) $conversation->user_id > 0 && self::suggest( $conversation, $mail['headers'], $from ) ) {
			self::emit_participants( $conversation );
		}
		$attachments = Chat::attachments_for( [ (int) $message['id'] ] );
		$message     = Chat::present_message( ChatMessages::get( (int) $message['id'] ), $attachments[ (int) $message['id'] ] ?? [] );
		$fresh       = Chat::present_conversation( ChatConversations::get( $conversation_id ), false );
		Chat::emit( 'message.created', $fresh, $message );
		if ( $customer || $participant ) {
			ChatPush::customer_message( $fresh, $message );
		}
		Chat::emit( 'conversation.updated', $fresh );
		return [ 'message_id' => (int) $message['id'], 'as' => $customer ? 'customer' : ( $participant ? 'participant' : 'note' ) ];
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

	/** Store one uploaded file the way the importer does (Chat::storage_path). */
	public static function store_file( $conversation_id, $message_id, array $f ) {
		$src  = (string) $f['tmp_name'];
		$sha  = hash_file( 'sha256', $src );
		$name = sanitize_file_name( (string) ( $f['name'] ?? '' ) ) ?: 'file';
		$dest = $sha ? Chat::storage_path( $conversation_id, $sha ) : '';
		if ( $dest === '' ) {
			return false;
		}
		$rel  = (int) $conversation_id . '/' . $sha;
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
