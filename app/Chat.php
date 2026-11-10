<?php

namespace CaptainCore;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * CaptainCore Chat: support conversations between customers and operators.
 *
 * The Manager is the system of record. The chat server (captaincore-chat, a
 * separate Go service) only relays live events: every change here fires the
 * `captaincore_chat_event` action, which the relay hooks to push frames.
 *
 * Rules (mirrors captaincore-chat's original store tests):
 *   - Customers see only their own conversations. Anything else is the same
 *     404 as a missing one. Guest threads (user_id 0, imported contacts
 *     with no Manager user) are operator-only.
 *   - Bodies are plain text, never HTML. Control characters and bidi
 *     overrides are stripped; the UI escapes everything on render.
 *   - Notes are internal. Internal rows never reach a customer.
 *   - A customer comment starts the waiting clock (if not running) and
 *     reopens a closed or snoozed thread. An operator comment stops it.
 *     A close stops it too. Notes and events never move last_message_at.
 *   - A conversation links only to a site and account the customer can use.
 *   - People edit only what they wrote: customers their own comments,
 *     operators their own comments and notes. Every edit keeps the version
 *     it replaced; only operators read that history.
 *   - Drafts are operator-only: one waiting reply per conversation, often
 *     from an AI agent, loaded into the reply box for a person to send.
 *     An operator comment clears it (a note clears only a note draft).
 *   - Operators can link CaptainCore logs (the site timeline) into a
 *     message. A reply takes only public logs from a site the customer can
 *     use; a note takes any. The card shows the log as it is now.
 *
 * Customer access follows the `captaincore_chat_mode` site option:
 *   off      operators only (history, imports). Customers keep Intercom.
 *   preview  plus customers with user meta captaincore_chat_preview = 1.
 *   on       everyone.
 */
class Chat {

	const MAX_BODY    = 10000;
	const MAX_SUBJECT = 200;
	const STATUSES    = [ 'open', 'snoozed', 'closed' ];
	const MODES       = [ 'off', 'preview', 'on' ];
	// Raster images display in the thread. Everything else (SVG included,
	// it can carry script) is always a download.
	const INLINE_TYPES = [ 'image/png', 'image/jpeg', 'image/gif', 'image/webp' ];

	// Videos play in the thread (and a larger player). Served with their
	// own type and byte ranges so browsers can seek; anything else is a download.
	const VIDEO_TYPES = [ 'video/mp4', 'video/webm', 'video/quicktime', 'video/ogg' ];

	// Reply emails show images and videos as pictures (Chat::email_thumb).
	// Stored as <file>-thumb, at most this size (twice the email's width).
	const THUMB_MAX_W = 1120;
	const THUMB_MAX_H = 840;

	// Automated helpers that write as themselves (`agent` on a message).
	// For now they only leave internal notes: they read, triage and
	// recommend; a person answers the customer.
	const AGENTS = [ 'grok-bot' => 'Grok Bot', 'claude' => 'Claude' ];

	// Emoji reactions on a message (Groddle's set). Keys are stored; the
	// glyphs and labels live in chat.js.
	const REACTIONS = [ 'like', 'heart', 'laugh', 'wow', 'sad', 'tada' ];

	// Files attached from the composer (drag and drop, paste, the paperclip).
	const MAX_LOGS      = 5;
	const MAX_FILES     = 10;
	const MAX_FILE_SIZE = 52428800; // 50 MB, so screen recordings fit

	// ── access ──────────────────────────────────────────────────────────

	public static function mode() {
		$mode = get_site_option( 'captaincore_chat_mode', 'off' );
		return in_array( $mode, self::MODES, true ) ? $mode : 'off';
	}

	public static function is_operator( $user_id ) {
		return $user_id > 0 && ( new User( $user_id, true ) )->is_admin();
	}

	/** Whether this user gets CaptainCore Chat (instead of Intercom). */
	public static function enabled_for( $user_id ) {
		if ( $user_id <= 0 ) {
			return false;
		}
		if ( self::is_operator( $user_id ) ) {
			return true;
		}
		$mode = self::mode();
		return $mode === 'on' || ( $mode === 'preview' && get_user_meta( $user_id, 'captaincore_chat_preview', true ) );
	}

	/**
	 * What a customer can reach in the Manager (operator sidebar): accounts
	 * with their access level, sites and domains. Built by the same classes
	 * that scope the customer's own requests (Sites, Domains, User), run as
	 * that customer, so it shows exactly what they see.
	 */
	public static function customer_access( $customer_user_id ) {
		global $wpdb;
		$customer_user_id = (int) $customer_user_id;
		$user             = $customer_user_id > 0 ? get_userdata( $customer_user_id ) : false;
		if ( ! $user ) {
			return new \WP_Error( 'not_found', 'No such user.', [ 'status' => 404 ] );
		}
		if ( self::is_operator( $customer_user_id ) ) {
			return [ 'operator' => true, 'accounts' => [], 'sites' => [], 'domains' => [] ];
		}
		$previous = get_current_user_id();
		wp_set_current_user( $customer_user_id );
		try {
			$member      = new User();
			$account_ids = array_map( 'intval', (array) $member->accounts() );
			$levels      = [];
			foreach ( $account_ids as $account_id ) {
				$levels[ $account_id ] = (string) $member->account_level( $account_id );
			}
			$site_ids   = array_map( 'intval', (array) ( new Sites() )->site_ids() );
			$domain_ids = ( new Domains() )->domain_ids();
		} finally {
			wp_set_current_user( $previous );
		}
		$in = fn( $ids ) => implode( ',', array_map( 'intval', $ids ) ?: [ 0 ] );
		$accounts = $account_ids ? $wpdb->get_results( "SELECT account_id, name FROM {$wpdb->prefix}captaincore_accounts WHERE account_id IN ({$in( $account_ids )}) ORDER BY name" ) : [];
		$sites    = $site_ids ? $wpdb->get_results( "SELECT site_id, name, JSON_UNQUOTE(JSON_EXTRACT(details, '$.home_url')) AS home_url FROM {$wpdb->prefix}captaincore_sites WHERE site_id IN ({$in( $site_ids )}) ORDER BY name" ) : [];
		$domains  = $domain_ids ? $wpdb->get_results( "SELECT domain_id, name FROM {$wpdb->prefix}captaincore_domains WHERE domain_id IN ({$in( $domain_ids )}) ORDER BY name" ) : [];
		// User Switching's "switch to" link, for the operator viewing this.
		$switch = class_exists( 'user_switching' ) ? \user_switching::maybe_switch_url( $user ) : false;
		return [
			'operator'   => false,
			'chat'       => self::enabled_for( $customer_user_id ), // false: they only get email
			'switch_url' => $switch ? html_entity_decode( $switch ) : '',
			'accounts' => array_map( fn( $a ) => [ 'id' => (int) $a->account_id, 'name' => html_entity_decode( (string) $a->name ), 'level' => $levels[ (int) $a->account_id ] ?? '' ], $accounts ?: [] ),
			'sites'    => array_map( fn( $x ) => [ 'id' => (int) $x->site_id, 'name' => (string) $x->name, 'url' => (string) ( $x->home_url ?? '' ) ], $sites ?: [] ),
			'domains'  => array_map( fn( $d ) => [ 'id' => (int) $d->domain_id, 'name' => (string) $d->name ], $domains ?: [] ),
		];
	}

	/** The conversation row if the user may see it, else null. */
	public static function viewable( $user_id, $conversation_id ) {
		$conversation = ChatConversations::get( (int) $conversation_id );
		if ( ! $conversation ) {
			return null;
		}
		if ( self::is_operator( $user_id ) ) {
			return $conversation;
		}
		if ( ! self::enabled_for( $user_id ) ) {
			return null;
		}
		return (int) $conversation->user_id === (int) $user_id && (int) $user_id > 0 ? $conversation : null;
	}

	private static function not_found() {
		return new \WP_Error( 'not_found', 'Conversation not found.', [ 'status' => 404 ] );
	}

	// ── reads ───────────────────────────────────────────────────────────

	/**
	 * Inbox listing, newest activity first, with a preview and unread count
	 * for the viewer.
	 *
	 * @param array $q status (open|snoozed|closed|waiting|''), q (search: name, email
	 *                 and subject for operators, subject for customers), customer
	 *                 (operators), site (operators), before (unix last_message_at
	 *                 cursor), limit
	 */
	public static function conversations( $user_id, $q = [] ) {
		global $wpdb;
		$operator = self::is_operator( $user_id );
		if ( ! $operator && ! self::enabled_for( $user_id ) ) {
			return [];
		}
		$c     = $wpdb->base_prefix . 'captaincore_chat_conversations';
		$m     = $wpdb->base_prefix . 'captaincore_chat_messages';
		$r     = $wpdb->base_prefix . 'captaincore_chat_reads';
		$d     = $wpdb->base_prefix . 'captaincore_chat_drafts';
		$where = [ '1=1' ];
		// Unread: the other side's comments after both the viewer's read mark
		// and the last reply from the viewer's side (replying means reading).
		$args  = [ $operator ? 'customer' : 'operator', (int) $user_id, $operator ? 'operator' : 'customer' ];

		if ( ! $operator ) {
			$where[] = 'c.user_id = %d';
			$args[]  = (int) $user_id;
		} else {
			if ( ! empty( $q['customer'] ) ) {
				$where[] = 'c.user_id = %d';
				$args[]  = (int) $q['customer'];
			}
			if ( ! empty( $q['site'] ) ) {
				$where[] = 'c.site_id = %d';
				$args[]  = (int) $q['site'];
			}
			if ( ! empty( $q['drafts'] ) ) {
				$where[] = "EXISTS (SELECT 1 FROM $d dd WHERE dd.chat_conversation_id = c.chat_conversation_id)";
			}
		}
		$search = trim( (string) ( $q['q'] ?? '' ) );
		if ( $search !== '' ) {
			$like = '%' . $wpdb->esc_like( mb_substr( $search, 0, 100 ) ) . '%';
			if ( $operator ) {
				$where[] = '(c.contact_name LIKE %s OR c.contact_email LIKE %s OR c.subject LIKE %s)';
				array_push( $args, $like, $like, $like );
			} else {
				// Customers search their own history: subjects and what was said.
				$where[] = "(c.subject LIKE %s OR EXISTS (SELECT 1 FROM $m ms WHERE ms.chat_conversation_id = c.chat_conversation_id
					AND ms.kind = 'comment' AND ms.internal = 0 AND ms.body LIKE %s))";
				array_push( $args, $like, $like );
			}
		}
		$status = (string) ( $q['status'] ?? '' );
		if ( ! $operator && $status === 'open' ) {
			// A snoozed conversation is still open as far as the customer knows.
			$where[] = "c.status IN ('open', 'snoozed')";
		} elseif ( $status === 'waiting' ) {
			$where[] = "c.status = 'open' AND c.waiting_since IS NOT NULL";
		} elseif ( in_array( $status, self::STATUSES, true ) ) {
			$where[] = 'c.status = %s';
			$args[]  = $status;
		}
		if ( ! empty( $q['before'] ) ) {
			$where[] = 'c.last_message_at < %s';
			$args[]  = gmdate( 'Y-m-d H:i:s', (int) $q['before'] );
		}
		$limit  = max( 1, min( 100, (int) ( $q['limit'] ?? 50 ) ) );
		$args[] = $limit;

		$sql = "SELECT c.*,
			(SELECT IF(m.body = '', IF(m.log_refs <> '', '📋 Shared a log', '📎 Sent a file'), SUBSTRING(m.body, 1, 200)) FROM $m m WHERE m.chat_conversation_id = c.chat_conversation_id
				AND m.kind = 'comment' ORDER BY m.chat_message_id DESC LIMIT 1) AS preview,
			(SELECT COUNT(*) FROM $m m WHERE m.chat_conversation_id = c.chat_conversation_id AND m.kind = 'comment'
				AND m.author_kind = %s AND m.chat_message_id > GREATEST(
					COALESCE((SELECT r.last_read_message_id FROM $r r WHERE r.user_id = %d AND r.chat_conversation_id = c.chat_conversation_id), 0),
					COALESCE((SELECT MAX(m2.chat_message_id) FROM $m m2 WHERE m2.chat_conversation_id = c.chat_conversation_id
						AND m2.kind = 'comment' AND m2.author_kind = %s), 0))) AS unread,
			(SELECT COUNT(*) FROM $m b WHERE b.chat_conversation_id = c.chat_conversation_id AND b.via = 'bot' AND b.chat_message_id > COALESCE(
				(SELECT MAX(x.chat_message_id) FROM $m x WHERE x.chat_conversation_id = c.chat_conversation_id AND x.kind = 'comment' AND x.author_kind = 'customer'), 0)) AS bot_notes" .
			( $operator ? ", (SELECT d.kind FROM $d d WHERE d.chat_conversation_id = c.chat_conversation_id) AS draft_kind" : '' ) . "
			FROM $c c WHERE " . implode( ' AND ', $where ) . '
			ORDER BY c.last_message_at DESC, c.chat_conversation_id DESC LIMIT %d';
		$rows = $wpdb->get_results( $wpdb->prepare( $sql, $args ) );
		return array_map( function ( $row ) use ( $operator ) {
			return self::present_conversation( $row, $operator );
		}, $rows ?: [] );
	}

	/** Inbox filter counts for operators. */
	public static function counts() {
		global $wpdb;
		$c   = $wpdb->base_prefix . 'captaincore_chat_conversations';
		$row = $wpdb->get_row( "SELECT SUM(status = 'open' AND waiting_since IS NOT NULL) waiting, SUM(status = 'open') open,
			SUM(status = 'snoozed') snoozed, SUM(status = 'closed') closed, COUNT(*) total FROM $c", ARRAY_A );
		return array_map( 'intval', $row ?: [ 'waiting' => 0, 'open' => 0, 'snoozed' => 0, 'closed' => 0, 'total' => 0 ] );
	}

	/**
	 * One conversation with its messages, oldest first.
	 *
	 * @param array $q before (message id cursor), limit
	 */
	public static function conversation( $user_id, $conversation_id, $q = [] ) {
		$conversation = self::viewable( $user_id, $conversation_id );
		if ( ! $conversation ) {
			return self::not_found();
		}
		$operator = self::is_operator( $user_id );
		$messages = self::messages( $conversation->chat_conversation_id, $operator, (int) ( $q['before'] ?? 0 ), (int) ( $q['limit'] ?? 200 ) );
		$out      = [
			'conversation' => self::present_conversation( $conversation, $operator ),
			'messages'     => $messages,
		];
		if ( $operator ) {
			// "Seen by <customer>" receipt.
			$out['customer_last_read_message_id'] = self::last_read( (int) $conversation->user_id, (int) $conversation->chat_conversation_id );
			$out['draft']                         = self::draft( (int) $conversation->chat_conversation_id );
		}
		return $out;
	}

	private static function messages( $conversation_id, $include_internal, $before = 0, $limit = 200 ) {
		global $wpdb;
		$m      = $wpdb->base_prefix . 'captaincore_chat_messages';
		$limit  = max( 1, min( 500, $limit ?: 200 ) );
		$before = $before > 0 ? $before : PHP_INT_MAX;
		$rows   = $wpdb->get_results( $wpdb->prepare(
			"SELECT * FROM $m WHERE chat_conversation_id = %d AND chat_message_id < %d" . ( $include_internal ? '' : ' AND internal = 0' ) .
			' ORDER BY chat_message_id DESC LIMIT %d',
			$conversation_id, $before, $limit
		) );
		$rows   = array_reverse( $rows ?: [] );
		$ids    = array_map( fn( $r ) => (int) $r->chat_message_id, $rows );
		$files  = self::attachments_for( $ids );
		$reacts = self::reactions_for( $ids );
		$logs   = self::logs_for( $rows );
		return array_map( function ( $row ) use ( $files, $reacts, $logs ) {
			return self::present_message( $row, $files[ (int) $row->chat_message_id ] ?? [], $reacts[ (int) $row->chat_message_id ] ?? [], $logs[ (int) $row->chat_message_id ] ?? [] );
		}, $rows );
	}

	public static function attachments_for( $message_ids ) {
		global $wpdb;
		if ( empty( $message_ids ) ) {
			return [];
		}
		$a    = $wpdb->base_prefix . 'captaincore_chat_attachments';
		$in   = implode( ',', array_map( 'intval', $message_ids ) );
		$rows = $wpdb->get_results( "SELECT * FROM $a WHERE chat_message_id IN ($in) ORDER BY chat_attachment_id ASC" );
		$out  = [];
		foreach ( $rows ?: [] as $row ) {
			$out[ (int) $row->chat_message_id ][] = [
				'id'     => (int) $row->chat_attachment_id,
				'name'   => $row->name,
				'mime'   => $row->mime,
				'size'   => (int) $row->size,
				'inline' => in_array( $row->mime, self::INLINE_TYPES, true ),
				'video'  => in_array( $row->mime, self::VIDEO_TYPES, true ),
				'thumb'  => in_array( $row->mime, self::VIDEO_TYPES, true ) && is_file( self::thumb_file( $row ) ),
				'url'    => rest_url( 'captaincore/v1/chat/attachments/' . (int) $row->chat_attachment_id ),
			];
		}
		return $out;
	}

	public static function last_read( $user_id, $conversation_id ) {
		global $wpdb;
		$r = $wpdb->base_prefix . 'captaincore_chat_reads';
		return (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT last_read_message_id FROM $r WHERE user_id = %d AND chat_conversation_id = %d", $user_id, $conversation_id
		) );
	}

	// ── writes ──────────────────────────────────────────────────────────

	/**
	 * A customer starts a conversation.
	 *
	 * @param array $input body, subject?, site_id?, account_id?
	 * @param array $files uploads (Chat::uploads): with files the body may be empty
	 */
	public static function create( $user_id, $input, $files = [] ) {
		if ( self::is_operator( $user_id ) ) {
			return self::create_for_customer( $user_id, $input, $files );
		}
		if ( ! self::enabled_for( $user_id ) ) {
			return new \WP_Error( 'chat_disabled', 'Chat is not available yet.', [ 'status' => 403 ] );
		}
		$opening = self::opening( $input, $files );
		if ( is_wp_error( $opening ) ) {
			return $opening;
		}
		[ $body, $subject, $files ] = $opening;

		// Links come only from what the user can already reach.
		$site_id = (int) ( $input['site_id'] ?? 0 );
		if ( $site_id > 0 && ( get_current_user_id() !== (int) $user_id || ! ( new Sites )->verify( $site_id ) ) ) {
			return new \WP_Error( 'site', "That site isn't on your account.", [ 'status' => 403 ] );
		}
		$accounts   = array_map( 'intval', ( new User( $user_id, true ) )->accounts() );
		$account_id = (int) ( $input['account_id'] ?? 0 );
		if ( $account_id > 0 && ! in_array( $account_id, $accounts, true ) ) {
			return new \WP_Error( 'account', "That account isn't yours.", [ 'status' => 403 ] );
		}
		if ( $account_id === 0 && count( $accounts ) === 1 ) {
			$account_id = $accounts[0];
		}

		$user = get_userdata( $user_id );
		$now  = gmdate( 'Y-m-d H:i:s' );
		$id   = ChatConversations::insert( [
			'user_id'         => (int) $user_id,
			'account_id'      => $account_id,
			'site_id'         => $site_id,
			'contact_name'    => $user ? $user->display_name : '',
			'contact_email'   => $user ? $user->user_email : '',
			'subject'         => $subject,
			'status'          => 'open',
			'last_message_at' => $now,
			'source'          => 'chat',
			'created_at'      => $now,
			'updated_at'      => $now,
		] );
		if ( ! $id ) {
			return new \WP_Error( 'storage', 'Could not save the conversation.', [ 'status' => 500 ] );
		}
		$message = self::append( $id, [
			'kind' => 'comment', 'author_kind' => 'customer', 'author_user_id' => (int) $user_id,
			'author_name' => $user ? $user->display_name : '', 'body' => $body,
		] );
		$message      = self::attach( $id, $message, $files );
		$conversation = self::present_conversation( ChatConversations::get( $id ), false );
		self::emit( 'conversation.created', $conversation, $message );
		ChatPush::customer_message( $conversation, $message );
		return [ 'conversation' => $conversation, 'messages' => [ $message ] ];
	}

	/** A new conversation's first message: [ body, subject, files ] or an error. */
	private static function opening( $input, $files ) {
		$files = self::uploads( $files );
		if ( is_wp_error( $files ) ) {
			return $files;
		}
		$body = self::body_or_files( $input['body'] ?? '', $files );
		if ( is_wp_error( $body ) ) {
			return $body;
		}
		$subject = trim( (string) ( $input['subject'] ?? '' ) );
		if ( $subject === '' ) {
			$subject = $body !== '' ? self::first_line( self::markdown_plain( $body ), 80 ) : ( count( $files ) === 1 ? 'Sent ' . sanitize_file_name( (string) $files[0]['name'] ) : 'Sent ' . count( $files ) . ' files' );
		}
		$subject = self::clean_line( $subject, self::MAX_SUBJECT );
		return is_wp_error( $subject ) ? $subject : [ $body, $subject, $files ];
	}

	/**
	 * An operator starts a conversation with a customer (customer_user_id).
	 * The first message is the operator's, so nothing is waiting. The
	 * customer sees it live in the Manager when chat is on for them, and
	 * ChatNotify emails it if it goes unread (right away when chat is not on
	 * for them yet: email is then the only way it reaches them).
	 * site_id and account_id must be ones the customer can reach.
	 */
	private static function create_for_customer( $operator_id, $input, $files ) {
		$customer_id = (int) ( $input['customer_user_id'] ?? 0 );
		$customer    = $customer_id > 0 ? get_userdata( $customer_id ) : false;
		if ( ! $customer ) {
			return new \WP_Error( 'customer', 'Choose who to message.', [ 'status' => 400 ] );
		}
		if ( self::is_operator( $customer_id ) ) {
			return new \WP_Error( 'customer', 'That person is an operator. Leave them an internal note instead.', [ 'status' => 400 ] );
		}
		$opening = self::opening( $input, $files );
		if ( is_wp_error( $opening ) ) {
			return $opening;
		}
		[ $body, $subject, $files ] = $opening;

		$access     = self::customer_access( $customer_id );
		$access     = is_wp_error( $access ) ? [ 'accounts' => [], 'sites' => [] ] : $access;
		$site_id    = (int) ( $input['site_id'] ?? 0 );
		if ( $site_id > 0 && ! in_array( $site_id, array_map( fn( $x ) => (int) $x['id'], $access['sites'] ), true ) ) {
			return new \WP_Error( 'site', "That site isn't on the customer's account.", [ 'status' => 400 ] );
		}
		$accounts   = array_map( fn( $a ) => (int) $a['id'], $access['accounts'] );
		$account_id = (int) ( $input['account_id'] ?? 0 );
		if ( $account_id > 0 && ! in_array( $account_id, $accounts, true ) ) {
			return new \WP_Error( 'account', "That account isn't the customer's.", [ 'status' => 400 ] );
		}
		if ( $account_id === 0 && count( $accounts ) === 1 ) {
			$account_id = $accounts[0];
		}

		$operator = get_userdata( $operator_id );
		$now      = gmdate( 'Y-m-d H:i:s' );
		$id       = ChatConversations::insert( [
			'user_id'         => $customer_id,
			'account_id'      => $account_id,
			'site_id'         => $site_id,
			'contact_name'    => $customer->display_name,
			'contact_email'   => $customer->user_email,
			'subject'         => $subject,
			'status'          => 'open',
			'last_message_at' => $now,
			'source'          => 'chat',
			'created_at'      => $now,
			'updated_at'      => $now,
		] );
		if ( ! $id ) {
			return new \WP_Error( 'storage', 'Could not save the conversation.', [ 'status' => 500 ] );
		}
		$message = self::append( $id, [
			'kind' => 'comment', 'author_kind' => 'operator', 'author_user_id' => (int) $operator_id,
			'author_name' => $operator ? $operator->display_name : '', 'body' => $body,
		] );
		$message = self::attach( $id, $message, $files );
		self::emit( 'conversation.created', self::present_conversation( ChatConversations::get( $id ), false ), $message );
		ChatNotify::schedule( $id, ChatRelay::last_customer_delivery() );
		return [ 'conversation' => self::present_conversation( ChatConversations::get( $id ), true ), 'messages' => [ $message ] ];
	}

	/**
	 * Post a comment, or (operators) an internal note.
	 */
	public static function post_message( $user_id, $conversation_id, $input, $files = [] ) {
		$conversation = self::viewable( $user_id, $conversation_id );
		if ( ! $conversation ) {
			return self::not_found();
		}
		$operator = self::is_operator( $user_id );
		$kind     = (string) ( $input['kind'] ?? 'comment' );
		if ( $kind === '' ) {
			$kind = 'comment';
		}
		if ( ! in_array( $kind, [ 'comment', 'note' ], true ) ) {
			return new \WP_Error( 'bad_kind', 'kind must be comment or note.', [ 'status' => 400 ] );
		}
		if ( $kind === 'note' && ! $operator ) {
			return new \WP_Error( 'operators_only', 'Only operators can write internal notes.', [ 'status' => 403 ] );
		}
		$agent = (string) ( $input['agent'] ?? '' );
		if ( $agent !== '' && ( ! $operator || ! isset( self::AGENTS[ $agent ] ) ) ) {
			return new \WP_Error( 'bad_agent', 'Unknown agent.', [ 'status' => 400 ] );
		}
		if ( $agent !== '' && $kind !== 'note' ) {
			return new \WP_Error( 'agent_notes_only', self::AGENTS[ $agent ] . ' can only write internal notes for now.', [ 'status' => 403 ] );
		}
		$files = self::uploads( $files );
		if ( is_wp_error( $files ) ) {
			return $files;
		}
		$refs = self::log_refs( $user_id, $conversation, $input['log_refs'] ?? '', $kind );
		if ( is_wp_error( $refs ) ) {
			return $refs;
		}
		$body = self::body_or_files( $input['body'] ?? '', $files ?: ( $refs !== '' ? [ 'log' ] : [] ) );
		if ( is_wp_error( $body ) ) {
			return $body;
		}
		$user    = get_userdata( $user_id );
		$message = self::append( (int) $conversation->chat_conversation_id, [
			'kind'           => $kind,
			'author_kind'    => $operator ? 'operator' : 'customer',
			'author_user_id' => (int) $user_id,
			'author_name'    => $agent !== '' ? self::AGENTS[ $agent ] : ( $user ? $user->display_name : '' ),
			'body'           => $body,
			'via'            => $agent !== '' ? 'bot' : '',
			'log_refs'       => $refs,
		] );
		$message = self::attach( (int) $conversation->chat_conversation_id, $message, $files );
		if ( $operator ) {
			self::clear_draft( (int) $conversation->chat_conversation_id, $kind );
		}
		$updated = self::present_conversation( ChatConversations::get( $conversation->chat_conversation_id ), false ); // customer-safe: the relay sends it to both sides
		self::emit( 'message.created', $updated, $message );
		if ( $operator && $kind === 'comment' ) {
			ChatNotify::schedule( (int) $conversation->chat_conversation_id, ChatRelay::last_customer_delivery() );
		} elseif ( ! $operator ) {
			ChatPush::customer_message( $updated, $message );
		}
		self::emit( 'conversation.updated', $updated );
		return [ 'message' => $message, 'conversation' => self::present_conversation( ChatConversations::get( $conversation->chat_conversation_id ), $operator ) ];
	}

	/**
	 * Insert a message and apply its effect on the conversation. Returns the
	 * presented message. Optional: via ('email'), original, external_id.
	 */
	public static function append( $conversation_id, $m ) {
		global $wpdb;
		$now      = gmdate( 'Y-m-d H:i:s' );
		$kind     = $m['kind'];
		$internal = $kind === 'note' ? 1 : ( $kind === 'comment' ? 0 : (int) ! empty( $m['internal'] ) );
		$id       = ChatMessages::insert( [
			'chat_conversation_id' => (int) $conversation_id,
			'kind'                 => $kind,
			'internal'             => $internal,
			'author_kind'          => $m['author_kind'],
			'author_user_id'       => (int) ( $m['author_user_id'] ?? 0 ),
			'author_name'          => (string) ( $m['author_name'] ?? '' ),
			'body'                 => (string) $m['body'],
			'via'                  => (string) ( $m['via'] ?? '' ),
			'original'             => isset( $m['original'] ) ? (string) $m['original'] : null,
			'external_id'          => (string) ( $m['external_id'] ?? '' ),
			'email_from'           => (string) ( $m['email_from'] ?? '' ),
			'log_refs'             => (string) ( $m['log_refs'] ?? '' ),
			'created_at'           => $now,
		] );
		$c = $wpdb->base_prefix . 'captaincore_chat_conversations';
		if ( $kind === 'comment' && $m['author_kind'] === 'customer' ) {
			$wpdb->query( $wpdb->prepare(
				"UPDATE $c SET last_customer_at = %s, last_message_at = %s, waiting_since = COALESCE(waiting_since, %s),
				status = 'open', snoozed_until = NULL, notify_after = NULL, updated_at = %s WHERE chat_conversation_id = %d",
				$now, $now, $now, $now, $conversation_id
			) );
		} elseif ( $kind === 'comment' && $m['author_kind'] === 'operator' ) {
			$wpdb->query( $wpdb->prepare(
				"UPDATE $c SET last_operator_at = %s, last_message_at = %s, waiting_since = NULL, updated_at = %s WHERE chat_conversation_id = %d",
				$now, $now, $now, $conversation_id
			) );
		} else {
			$wpdb->query( $wpdb->prepare( "UPDATE $c SET updated_at = %s WHERE chat_conversation_id = %d", $now, $conversation_id ) );
		}
		return self::present_message( ChatMessages::get( $id ), [] );
	}

	/**
	 * Change a conversation. Customers may only resolve (status closed) or
	 * reopen (status open) their own. Operators may also snooze, link a site
	 * or account, assign, and rename.
	 *
	 * @param array $patch status, snoozed_until (unix), site_id, account_id, assignee_user_id, subject. 0 clears an id.
	 */
	public static function update( $user_id, $conversation_id, $patch ) {
		$conversation = self::viewable( $user_id, $conversation_id );
		if ( ! $conversation ) {
			return self::not_found();
		}
		$operator = self::is_operator( $user_id );
		$allowed  = [ 'status', 'snoozed_until', 'site_id', 'account_id', 'assignee_user_id', 'subject' ];
		$patch    = array_intersect_key( (array) $patch, array_flip( $allowed ) );
		if ( ! $operator ) {
			$status_ok = isset( $patch['status'] ) && in_array( $patch['status'], [ 'open', 'closed' ], true );
			if ( ! $status_ok || count( $patch ) !== 1 ) {
				return new \WP_Error( 'operators_only', 'You can resolve or reopen a conversation; operators handle the rest.', [ 'status' => 403 ] );
			}
		}

		$user   = get_userdata( $user_id );
		$name   = $user ? $user->display_name : 'Someone';
		$now    = gmdate( 'Y-m-d H:i:s' );
		$set    = [ 'updated_at' => $now ];
		$events = [];

		if ( isset( $patch['status'] ) && $patch['status'] !== $conversation->status ) {
			switch ( $patch['status'] ) {
				case 'open':
					$set['status']        = 'open';
					$set['snoozed_until'] = null;
					$events[]             = [ "Reopened by $name", 0 ];
					break;
				case 'closed':
					$set['status']        = 'closed';
					$set['snoozed_until'] = null;
					$set['waiting_since'] = null;
					$events[]             = [ "Closed by $name", 0 ];
					break;
				case 'snoozed':
					$until = (int) ( $patch['snoozed_until'] ?? 0 );
					if ( $until <= time() || $until > time() + 366 * DAY_IN_SECONDS ) {
						return new \WP_Error( 'invalid', 'snoozed_until must be in the next year.', [ 'status' => 400 ] );
					}
					$set['status']        = 'snoozed';
					$set['snoozed_until'] = gmdate( 'Y-m-d H:i:s', $until );
					$events[]             = [ 'Snoozed until ' . gmdate( 'Y-m-d H:i', $until ) . " UTC by $name", 1 ];
					break;
				default:
					return new \WP_Error( 'invalid', 'status must be open, snoozed or closed.', [ 'status' => 400 ] );
			}
		}
		if ( array_key_exists( 'site_id', $patch ) ) {
			$set['site_id'] = max( 0, (int) $patch['site_id'] );
			$events[]       = [ $set['site_id'] ? "Linked to site #{$set['site_id']} by $name" : "Site unlinked by $name", 1 ];
		}
		if ( array_key_exists( 'account_id', $patch ) ) {
			$set['account_id'] = max( 0, (int) $patch['account_id'] );
		}
		if ( array_key_exists( 'assignee_user_id', $patch ) ) {
			$set['assignee_user_id'] = max( 0, (int) $patch['assignee_user_id'] );
			$events[]                = [ $set['assignee_user_id'] ? "Assigned to user #{$set['assignee_user_id']} by $name" : "Unassigned by $name", 1 ];
		}
		if ( array_key_exists( 'subject', $patch ) ) {
			$subject = self::clean_line( (string) $patch['subject'], self::MAX_SUBJECT );
			if ( is_wp_error( $subject ) ) {
				return $subject;
			}
			$set['subject'] = $subject;
		}

		ChatConversations::update( $set, [ 'chat_conversation_id' => (int) $conversation->chat_conversation_id ] );
		$created = [];
		foreach ( $events as [ $body, $internal ] ) {
			$created[] = self::append( (int) $conversation->chat_conversation_id, [
				'kind' => 'event', 'internal' => $internal, 'author_kind' => $operator ? 'operator' : 'customer',
				'author_user_id' => (int) $user_id, 'author_name' => $name, 'body' => $body,
			] );
		}
		$fresh = self::present_conversation( ChatConversations::get( $conversation->chat_conversation_id ), false ); // customer-safe: the relay sends it to both sides
		foreach ( $created as $message ) {
			self::emit( 'message.created', $fresh, $message );
		}
		self::emit( 'conversation.updated', $fresh );
		return [
			'conversation' => self::present_conversation( ChatConversations::get( $conversation->chat_conversation_id ), $operator ),
			'events'       => $operator ? $created : array_values( array_filter( $created, fn( $e ) => ! $e['internal'] ) ),
		];
	}

	/** Move the user's read mark forward. It never moves back. */
	public static function mark_read( $user_id, $conversation_id, $message_id ) {
		global $wpdb;
		$conversation = self::viewable( $user_id, $conversation_id );
		if ( ! $conversation ) {
			return self::not_found();
		}
		$m       = $wpdb->base_prefix . 'captaincore_chat_messages';
		if ( (int) $message_id <= 0 ) {
			// No message given (an inbox row's "Mark as read"): up to the latest.
			$message_id = (int) $wpdb->get_var( $wpdb->prepare( "SELECT MAX(chat_message_id) FROM $m WHERE chat_conversation_id = %d", $conversation->chat_conversation_id ) );
		}
		$belongs = $wpdb->get_var( $wpdb->prepare(
			"SELECT 1 FROM $m WHERE chat_message_id = %d AND chat_conversation_id = %d", $message_id, $conversation->chat_conversation_id
		) );
		if ( ! $belongs ) {
			return new \WP_Error( 'not_found', 'Message not found in this conversation.', [ 'status' => 404 ] );
		}
		$r = $wpdb->base_prefix . 'captaincore_chat_reads';
		$wpdb->query( $wpdb->prepare(
			"INSERT INTO $r (user_id, chat_conversation_id, last_read_message_id, updated_at) VALUES (%d, %d, %d, %s)
			ON DUPLICATE KEY UPDATE last_read_message_id = GREATEST(last_read_message_id, VALUES(last_read_message_id)), updated_at = VALUES(updated_at)",
			$user_id, $conversation->chat_conversation_id, $message_id, gmdate( 'Y-m-d H:i:s' )
		) );
		if ( ! self::is_operator( $user_id ) ) {
			ChatNotify::cancel_if_read( (int) $conversation->chat_conversation_id, (int) $user_id );
			do_action( 'captaincore_chat_event', [ 'type' => 'read', 'conversation_id' => (int) $conversation->chat_conversation_id, 'message_id' => (int) $message_id ], 0, true );
		}
		return true;
	}

	/**
	 * An operator's "Mark as unread": moves their read mark back to just before
	 * the customer's latest message, so it counts as unread again. The inbox
	 * counts customer messages after both the read mark and the last operator
	 * reply, so this only shows when the customer spoke last.
	 */
	public static function mark_unread( $user_id, $conversation_id ) {
		global $wpdb;
		if ( ! self::is_operator( $user_id ) ) {
			return new \WP_Error( 'forbidden', 'Only operators can mark a conversation unread.', [ 'status' => 403 ] );
		}
		$conversation = self::viewable( $user_id, $conversation_id );
		if ( ! $conversation ) {
			return self::not_found();
		}
		$id   = (int) $conversation->chat_conversation_id;
		$m    = $wpdb->base_prefix . 'captaincore_chat_messages';
		$last = (int) $wpdb->get_var( $wpdb->prepare( "SELECT MAX(chat_message_id) FROM $m WHERE chat_conversation_id = %d AND kind = 'comment' AND author_kind = 'customer'", $id ) );
		if ( ! $last ) {
			return new \WP_Error( 'nothing_unread', 'The customer has not written in this conversation.', [ 'status' => 400 ] );
		}
		$before = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COALESCE(MAX(chat_message_id), 0) FROM $m WHERE chat_conversation_id = %d AND chat_message_id < %d", $id, $last ) );
		$r      = $wpdb->base_prefix . 'captaincore_chat_reads';
		$wpdb->query( $wpdb->prepare(
			"INSERT INTO $r (user_id, chat_conversation_id, last_read_message_id, updated_at) VALUES (%d, %d, %d, %s)
			ON DUPLICATE KEY UPDATE last_read_message_id = VALUES(last_read_message_id), updated_at = VALUES(updated_at)",
			$user_id, $id, $before, gmdate( 'Y-m-d H:i:s' )
		) );
		return true;
	}

	/** Reopen snoozed conversations whose time has come. */
	public static function wake_snoozed() {
		global $wpdb;
		$c   = $wpdb->base_prefix . 'captaincore_chat_conversations';
		$ids = $wpdb->get_col( $wpdb->prepare( "SELECT chat_conversation_id FROM $c WHERE status = 'snoozed' AND snoozed_until <= %s", gmdate( 'Y-m-d H:i:s' ) ) );
		foreach ( $ids as $id ) {
			ChatConversations::update( [ 'status' => 'open', 'snoozed_until' => null, 'updated_at' => gmdate( 'Y-m-d H:i:s' ) ], [ 'chat_conversation_id' => (int) $id ] );
			$message = self::append( (int) $id, [ 'kind' => 'event', 'internal' => 1, 'author_kind' => 'system', 'body' => 'Snooze ended' ] );
			$fresh   = self::present_conversation( ChatConversations::get( $id ), false ); // customer-safe: the relay sends it to both sides
			self::emit( 'message.created', $fresh, $message );
			self::emit( 'conversation.updated', $fresh );
		}
		return count( $ids );
	}

	/**
	 * Fire a live event for the relay. Routing: operators get everything,
	 * the owning customer gets it unless the message is internal.
	 */
	public static function emit( $type, $conversation, $message = null ) {
		$event = [ 'type' => $type, 'conversation_id' => $conversation['id'], 'conversation' => $conversation ];
		if ( $message ) {
			$event['message'] = $message;
		}
		$internal = $message ? ! empty( $message['internal'] ) : false;
		do_action( 'captaincore_chat_event', $event, (int) $conversation['customer_user_id'], $internal );
	}

	// ── files ───────────────────────────────────────────────────────────

	/**
	 * Where attachments live: <parent of ABSPATH>/private/chat unless
	 * CAPTAINCORE_CHAT_DIR says otherwise. Web PHP must be able to read it,
	 * and Kinsta's open_basedir leaves out ~/private (WP-CLI over SSH is not
	 * limited, so an import there looks fine and then every file 404s).
	 * There, point it into uploads under a folder name with a random part,
	 * e.g. wp-content/uploads/captaincore-chat-<32 random hex>, so it rides
	 * the site's backups. Files are only ever served by send_attachment.
	 */
	public static function base_dir() {
		if ( defined( 'CAPTAINCORE_CHAT_DIR' ) && CAPTAINCORE_CHAT_DIR ) {
			return untrailingslashit( CAPTAINCORE_CHAT_DIR );
		}
		return dirname( untrailingslashit( ABSPATH ) ) . '/private/chat';
	}

	/**
	 * Where a stored file goes: <base>/<conversation>/<sha256>. No
	 * extension, ever: the name and type live in the database, and a file
	 * that may sit under the web root must never look runnable (a customer
	 * knows the sha256 of what they upload). Creates the folders, and on
	 * first use guards the base with an empty index.php and a deny-all
	 * .htaccess for Apache hosts. Returns '' when it cannot.
	 */
	public static function storage_path( $conversation_id, $sha256 ) {
		$base = self::base_dir();
		if ( ! preg_match( '/^[a-f0-9]{64}$/', (string) $sha256 ) || (int) $conversation_id <= 0 ) {
			return '';
		}
		$dir = $base . '/' . (int) $conversation_id;
		if ( ! is_dir( $dir ) && ! wp_mkdir_p( $dir ) ) {
			return '';
		}
		if ( ! is_file( $base . '/.htaccess' ) ) {
			@file_put_contents( $base . '/.htaccess', "Require all denied\nDeny from all\n" );
			@file_put_contents( $base . '/index.php', "<?php\n// Silence is golden.\n" );
		}
		return $dir . '/' . $sha256;
	}

	/**
	 * Stream an attachment the user may see. Raster images and videos
	 * display inline (type checked from the bytes, not the name); everything
	 * else downloads. Byte ranges are honoured (206) so video can seek and
	 * Safari will play it at all. $download asks for a download either way.
	 */
	public static function send_attachment( $user_id, $attachment_id, $download = false ) {
		$attachment = ChatAttachments::get( (int) $attachment_id );
		$message    = $attachment ? ChatMessages::get( (int) $attachment->chat_message_id ) : null;
		$visible    = $message && self::viewable( $user_id, (int) $attachment->chat_conversation_id )
			&& ( ! (int) $message->internal || self::is_operator( $user_id ) );
		$path       = $visible ? self::base_dir() . '/' . ltrim( $attachment->storage_path, '/' ) : '';
		if ( ! $visible || strpos( $attachment->storage_path, '..' ) !== false || ! is_file( $path ) ) {
			return new \WP_Error( 'not_found', 'File not found.', [ 'status' => 404 ] );
		}
		$sniffed = function_exists( 'finfo_open' ) ? (string) finfo_file( finfo_open( FILEINFO_MIME_TYPE ), $path ) : '';
		$video   = in_array( $sniffed, self::VIDEO_TYPES, true );
		$inline  = $video || in_array( $sniffed, self::INLINE_TYPES, true );
		$size    = (int) filesize( $path );
		$range   = self::byte_range( (string) ( $_SERVER['HTTP_RANGE'] ?? '' ), $size );
		while ( ob_get_level() ) {
			ob_end_clean();
		}
		if ( $range === false ) {
			status_header( 416 );
			header( "Content-Range: bytes */$size" );
			exit;
		}
		[ $start, $end ] = $range ?: [ 0, $size - 1 ];
		if ( $range ) {
			status_header( 206 );
			header( "Content-Range: bytes $start-$end/$size" );
		}
		header( 'Content-Type: ' . ( $inline ? $sniffed : 'application/octet-stream' ) );
		header( 'Content-Disposition: ' . ( $inline && ! $download ? 'inline' : 'attachment' ) . '; filename="' . str_replace( [ '"', "\r", "\n" ], '', $attachment->name ) . '"' );
		header( 'Content-Length: ' . ( $end - $start + 1 ) );
		header( 'Accept-Ranges: bytes' );
		header( 'X-Content-Type-Options: nosniff' );
		// A video opened in its own tab is a media page that loads itself
		// again; under sandbox its origin is null and that load is refused.
		header( "Content-Security-Policy: default-src 'none'" . ( $video ? "; media-src 'self'" : '; sandbox' ) );
		header( 'Cache-Control: private, max-age=86400' );
		$fh = fopen( $path, 'rb' );
		fseek( $fh, $start );
		for ( $left = $end - $start + 1; $left > 0 && ! feof( $fh ); $left -= 1048576 ) {
			echo fread( $fh, min( 1048576, $left ) );
			flush();
		}
		fclose( $fh );
		exit;
	}

	/**
	 * One "bytes=" range from a Range header: [start, end], null for no
	 * (or an unusable multi-part) range, false when it can't be satisfied.
	 */
	public static function byte_range( $header, $size ) {
		if ( ! preg_match( '/^bytes=(\d*)-(\d*)$/', trim( $header ), $m ) || ( $m[1] === '' && $m[2] === '' ) ) {
			return null;
		}
		if ( $m[1] === '' ) {
			$start = max( 0, $size - (int) $m[2] );
			$end   = $size - 1;
		} else {
			$start = (int) $m[1];
			$end   = $m[2] === '' ? $size - 1 : min( (int) $m[2], $size - 1 );
		}
		return $size > 0 && $start <= $end && $start < $size ? [ $start, $end ] : false;
	}

	// ── email thumbnails ────────────────────────────────────────────────
	// Mail clients can't sign in, so a reply email's pictures come from a
	// signed public URL that serves only the small JPEG, never the file.
	// Videos get theirs from a browser (anchor.host has no ffmpeg): the
	// first viewer allowed to (an operator or whoever sent it) draws a
	// frame and posts it; GD re-encodes it with a play button on top.

	public static function thumb_file( $attachment ) {
		return self::base_dir() . '/' . ltrim( (string) $attachment->storage_path, '/' ) . '-thumb';
	}

	public static function thumb_sig( $attachment_id ) {
		$key = ChatRelay::derived_key( 'thumb.v1' );
		return $key === '' ? '' : substr( hash_hmac( 'sha256', 'thumb.' . (int) $attachment_id, $key ), 0, 32 );
	}

	/**
	 * The email picture for an attachment, made now for an image: [url,
	 * width, height] at email size, or null (a video nobody has drawn a
	 * frame of yet, an internal note's file, anything else).
	 */
	public static function email_thumb( $attachment_id ) {
		$attachment = ChatAttachments::get( (int) $attachment_id );
		$message    = $attachment ? ChatMessages::get( (int) $attachment->chat_message_id ) : null;
		if ( ! $message || (int) $message->internal || strpos( (string) $attachment->storage_path, '..' ) !== false || self::thumb_sig( $attachment_id ) === '' ) {
			return null;
		}
		$thumb = self::thumb_file( $attachment );
		if ( ! is_file( $thumb ) && in_array( $attachment->mime, self::INLINE_TYPES, true ) && (int) $attachment->size <= 26214400 ) {
			$src = self::base_dir() . '/' . ltrim( $attachment->storage_path, '/' );
			$img = is_file( $src ) ? @imagecreatefromstring( (string) file_get_contents( $src ) ) : false;
			if ( $img ) {
				self::write_thumb( $img, $thumb, false );
			}
		}
		$size = is_file( $thumb ) ? @getimagesize( $thumb ) : false;
		if ( ! $size ) {
			return null;
		}
		$w = (int) min( 520, max( 1, round( $size[0] / 2 ) ) );
		return [
			'url'    => add_query_arg( 'sig', self::thumb_sig( $attachment_id ), rest_url( 'captaincore/v1/chat/attachments/' . (int) $attachment_id . '/thumb' ) ),
			'width'  => $w,
			'height' => (int) round( $w * $size[1] / max( 1, $size[0] ) ),
		];
	}

	/** Public, signed: the email picture. 404 for anything else. */
	public static function send_thumb( $attachment_id, $sig ) {
		$expected   = self::thumb_sig( $attachment_id );
		$attachment = $expected !== '' && hash_equals( $expected, (string) $sig ) ? ChatAttachments::get( (int) $attachment_id ) : null;
		$message    = $attachment ? ChatMessages::get( (int) $attachment->chat_message_id ) : null;
		$path       = $message && ! (int) $message->internal && strpos( (string) $attachment->storage_path, '..' ) === false ? self::thumb_file( $attachment ) : '';
		if ( $path === '' || ! is_file( $path ) ) {
			return new \WP_Error( 'not_found', 'Not found.', [ 'status' => 404 ] );
		}
		while ( ob_get_level() ) {
			ob_end_clean();
		}
		header( 'Content-Type: image/jpeg' );
		header( 'Content-Length: ' . filesize( $path ) );
		header( 'X-Content-Type-Options: nosniff' );
		header( "Content-Security-Policy: default-src 'none'; sandbox" );
		header( 'Cache-Control: public, max-age=604800' );
		readfile( $path );
		exit;
	}

	/**
	 * A frame of a video, drawn by a browser. The first one wins; only an
	 * operator or the person who sent the video may give it one.
	 */
	public static function save_thumb( $user_id, $attachment_id, $file ) {
		$attachment = ChatAttachments::get( (int) $attachment_id );
		$message    = $attachment ? ChatMessages::get( (int) $attachment->chat_message_id ) : null;
		$operator   = self::is_operator( $user_id );
		if ( ! $message || ! in_array( $attachment->mime, self::VIDEO_TYPES, true ) || ! self::viewable( $user_id, (int) $attachment->chat_conversation_id )
			|| ( (int) $message->internal && ! $operator ) || strpos( (string) $attachment->storage_path, '..' ) !== false ) {
			return new \WP_Error( 'not_found', 'File not found.', [ 'status' => 404 ] );
		}
		if ( ! $operator && (int) $message->author_user_id !== (int) $user_id ) {
			return new \WP_Error( 'forbidden', 'Only the sender or support can do that.', [ 'status' => 403 ] );
		}
		$thumb = self::thumb_file( $attachment );
		if ( is_file( $thumb ) ) {
			return [ 'thumb' => true ];
		}
		$tmp = is_array( $file ) ? (string) ( $file['tmp_name'] ?? '' ) : '';
		if ( $tmp === '' || (int) ( $file['error'] ?? 1 ) !== 0 || (int) ( $file['size'] ?? 0 ) > 4194304 || ( ! is_uploaded_file( $tmp ) && ! defined( 'CAPTAINCORE_CHAT_TESTING' ) ) ) {
			return new \WP_Error( 'bad_thumb', 'Send a JPEG or PNG under 4 MB as "thumb".', [ 'status' => 400 ] );
		}
		$type = function_exists( 'finfo_open' ) ? (string) finfo_file( finfo_open( FILEINFO_MIME_TYPE ), $tmp ) : '';
		$img  = in_array( $type, [ 'image/jpeg', 'image/png', 'image/webp' ], true ) ? @imagecreatefromstring( (string) file_get_contents( $tmp ) ) : false;
		if ( ! $img || imagesx( $img ) < 16 || imagesy( $img ) < 16 ) {
			return new \WP_Error( 'bad_thumb', 'That is not a picture.', [ 'status' => 400 ] );
		}
		if ( ! self::write_thumb( $img, $thumb, true ) ) {
			return new \WP_Error( 'thumb_failed', 'Could not save it.', [ 'status' => 500 ] );
		}
		return [ 'thumb' => true ];
	}

	/** Scale to thumbnail size, add a play button for a video, save as JPEG. */
	private static function write_thumb( $img, $path, $play ) {
		$w     = imagesx( $img );
		$h     = imagesy( $img );
		$scale = min( 1, self::THUMB_MAX_W / $w, self::THUMB_MAX_H / $h );
		$tw    = max( 1, (int) round( $w * $scale ) );
		$th    = max( 1, (int) round( $h * $scale ) );
		$out   = imagecreatetruecolor( $tw, $th );
		imagefill( $out, 0, 0, imagecolorallocate( $out, 255, 255, 255 ) ); // transparent PNGs on white
		imagecopyresampled( $out, $img, 0, 0, 0, 0, $tw, $th, $w, $h );
		if ( $play ) {
			// Drawn four times larger and scaled down, so the edges are smooth.
			$d   = (int) max( 56, min( 150, round( min( $tw, $th ) * 0.24 ) ) );
			$big = $d * 4;
			$btn = imagecreatetruecolor( $big, $big );
			imagealphablending( $btn, false );
			imagesavealpha( $btn, true );
			imagefill( $btn, 0, 0, imagecolorallocatealpha( $btn, 0, 0, 0, 127 ) );
			imagealphablending( $btn, true );
			imagefilledellipse( $btn, $big / 2, $big / 2, $big - 2, $big - 2, imagecolorallocatealpha( $btn, 0, 0, 0, 45 ) );
			$r = $big * 0.2;
			$cx = $big / 2 + $r * 0.2;
			imagefilledpolygon( $btn, [ (int) ( $cx - $r * 0.8 ), (int) ( $big / 2 - $r ), (int) ( $cx - $r * 0.8 ), (int) ( $big / 2 + $r ), (int) ( $cx + $r ), (int) ( $big / 2 ) ], imagecolorallocate( $btn, 255, 255, 255 ) );
			imagealphablending( $out, true );
			imagecopyresampled( $out, $btn, (int) ( ( $tw - $d ) / 2 ), (int) ( ( $th - $d ) / 2 ), 0, 0, $d, $d, $big, $big );
		}
		$tmp = $path . '.' . wp_generate_password( 8, false ) . '.tmp';
		$ok  = imagejpeg( $out, $tmp, 82 ) && rename( $tmp, $path );
		if ( $ok ) {
			chmod( $path, 0600 );
		} else {
			@unlink( $tmp );
		}
		return $ok;
	}

	// ── presentation ────────────────────────────────────────────────────

	private static function ts( $datetime ) {
		return $datetime ? (int) strtotime( $datetime . ' UTC' ) : null;
	}

	public static function present_conversation( $row, $operator ) {
		$out = [
			'id'               => (int) $row->chat_conversation_id,
			'customer_user_id' => (int) $row->user_id,
			'customer_name'    => $row->contact_name,
			'customer_email'   => $row->contact_email,
			'customer_avatar'  => self::avatar( (int) $row->user_id, (string) $row->contact_email ),
			'account_id'       => (int) $row->account_id ?: null,
			'site_id'          => (int) $row->site_id ?: null,
			'subject'          => $row->subject,
			'status'           => $row->status,
			'snoozed_until'    => self::ts( $row->snoozed_until ),
			'waiting_since'    => self::ts( $row->waiting_since ),
			'last_customer_at' => self::ts( $row->last_customer_at ),
			'last_operator_at' => self::ts( $row->last_operator_at ),
			'last_message_at'  => self::ts( $row->last_message_at ),
			'source'           => $row->source,
			'created_at'       => self::ts( $row->created_at ),
			'updated_at'       => self::ts( $row->updated_at ),
		];
		if ( isset( $row->preview ) ) {
			$out['preview'] = (string) $row->preview;
		}
		if ( isset( $row->unread ) ) {
			$out['unread'] = (int) $row->unread;
		}
		if ( $operator && isset( $row->bot_notes ) ) {
			// Grok Bot left a triage note since the customer last wrote.
			$out['triaged'] = (int) $row->bot_notes > 0;
		}
		if ( $operator && property_exists( $row, 'draft_kind' ) ) {
			// A reply is waiting in the drafts: 'comment', 'note' or ''.
			$out['draft'] = (string) $row->draft_kind;
		}
		if ( $operator ) {
			$out['assignee_user_id'] = (int) $row->assignee_user_id ?: null;
			$out['guest']            = (int) $row->user_id === 0;
		}
		return $out;
	}

	/**
	 * A person's Gravatar URL (by user, else by email), or ''. d=404 makes
	 * Gravatar answer 404 when there is no picture, so clients keep the
	 * initials. Built here so a browser never needs anyone's email.
	 */
	public static function avatar( $user_id, $email = '' ) {
		static $cache = [];
		$key = $user_id > 0 ? 'u' . $user_id : 'e' . strtolower( trim( (string) $email ) );
		if ( ! isset( $cache[ $key ] ) ) {
			$who           = $user_id > 0 ? (int) $user_id : ( is_email( $email ) ? strtolower( trim( $email ) ) : '' );
			$cache[ $key ] = $who ? (string) get_avatar_url( $who, [ 'size' => 96, 'default' => '404' ] ) : '';
		}
		return $cache[ $key ];
	}

	public static function present_message( $row, $attachments, $reactions = [], $logs = null ) {
		if ( $logs === null ) {
			$logs = ( $row->log_refs ?? '' ) !== '' ? ( self::logs_for( [ $row ] )[ (int) $row->chat_message_id ] ?? [] ) : [];
		}
		return [
			'id'              => (int) $row->chat_message_id,
			'conversation_id' => (int) $row->chat_conversation_id,
			'kind'            => $row->kind,
			'internal'        => (bool) (int) $row->internal,
			'author_kind'     => $row->author_kind,
			'author_user_id'  => (int) $row->author_user_id ?: null,
			'author_name'     => $row->author_name,
			// Gravatar for a person with a login ('' for system, bot and guests;
			// clients fall back to the conversation's customer_avatar).
			'author_avatar'   => in_array( $row->author_kind, [ 'customer', 'operator' ], true ) && ( $row->via ?? '' ) !== 'bot' ? self::avatar( (int) $row->author_user_id ) : '',
			'body'            => $row->body,
			'attachments'     => $attachments,
			'created_at'      => self::ts( $row->created_at ),
			'edited_at'       => self::ts( $row->edited_at ),
			// Who made the latest edit ('' when never edited). Everyone sees
			// it, so a customer knows when staff changed their message.
			'edited_by'       => $row->edited_at ? self::user_name( (int) ( $row->edited_by ?? 0 ), (string) $row->author_name ) : '',
			// Came from an import (Intercom). Read marks on imported history
			// are synthetic, so clients skip the "Seen" receipt for these.
			'imported'        => (string) $row->external_id !== '' && (string) ( $row->via ?? '' ) === '',
			// 'email' when it arrived as an email reply; original is that
			// email's full text, kept in case the cleanup cut too much.
			'via'             => (string) ( $row->via ?? '' ),
			'original'        => ( $row->via ?? '' ) === 'email' ? (string) $row->original : '',
			// The address an email reply came from. It differs from the
			// customer's when their mail forwards elsewhere (ChatNotify::receive).
			'email_from'      => ( $row->via ?? '' ) === 'email' ? (string) ( $row->email_from ?? '' ) : '',
			// [{key, by: [{id, name}]}] in the order each was first used.
			'reactions'       => $reactions,
			// Linked CaptainCore logs (Chat::logs_for), as they read now.
			'logs'            => $logs,
		];
	}

	// ── markdown ────────────────────────────────────────────────────────

	/**
	 * Message text as email HTML: chat.js's chatText rules (basic markdown
	 * on escaped text) with inline styles for mail clients.
	 */
	public static function markdown_html( $text ) {
		$keep   = [];
		$hold   = function ( $html ) use ( &$keep ) {
			$keep[] = $html;
			return "\x00" . ( count( $keep ) - 1 ) . "\x00";
		};
		$link   = fn( $href, $label ) => $hold( '<a href="' . $href . '" style="color:#1E4FA8;">' . $label . '</a>' );
		$code   = 'font-family:SFMono-Regular,Menlo,Consolas,monospace;font-size:13px;background:#F1F3F6;border-radius:4px;';
		$inline = function ( $x ) use ( $hold, $link, $code ) {
			$x = preg_replace_callback( '/`([^`\n]+)`/', fn( $m ) => $hold( '<code style="' . $code . 'padding:1px 5px;">' . $m[1] . '</code>' ), $x );
			$x = preg_replace_callback( '/\[([^\]\n]+)\]\((https?:\/\/[^\s)<]+)\)/', fn( $m ) => $link( $m[2], $m[1] ), $x );
			$x = preg_replace_callback( '/https?:\/\/[^\s<\x00]+[^\s<.,;:!?)\]\'"&\x00]/', fn( $m ) => $link( $m[0], $m[0] ), $x );
			$x = preg_replace( '/\*\*(?=\S)([^*\n]*?\S)\*\*/', '<strong>$1</strong>', $x );
			$x = preg_replace( '/~~(?=\S)([^~\n]*?\S)~~/', '<del>$1</del>', $x );
			$x = preg_replace( '/(^|[^\w*])\*(?=\S)([^*\n]*?\S)\*(?![\w*])/', '$1<em>$2</em>', $x );
			return preg_replace( '/(^|[^\w])_(?=\S)([^_\n]*?\S)_(?!\w)/', '$1<em>$2</em>', $x );
		};
		$lines = explode( "\n", esc_html( str_replace( "\r\n", "\n", (string) $text ) ) );
		$out   = [];
		$para  = [];
		$flush = function () use ( &$para, &$out, $inline ) {
			if ( $para ) {
				$out[] = '<p style="margin:0 0 8px;">' . implode( '<br>', array_map( $inline, $para ) ) . '</p>';
				$para  = [];
			}
		};
		$count = count( $lines );
		for ( $i = 0; $i < $count; $i++ ) {
			$line = $lines[ $i ];
			if ( preg_match( '/^\s*```/', $line ) ) {
				$flush();
				$block = [];
				for ( $i++; $i < $count && ! preg_match( '/^\s*```/', $lines[ $i ] ); $i++ ) {
					$block[] = $lines[ $i ];
				}
				$out[] = '<pre style="' . $code . 'padding:10px 12px;white-space:pre-wrap;margin:0 0 8px;">' . implode( "\n", $block ) . '</pre>';
				continue;
			}
			$matched = false;
			foreach ( [
				[ '/^\s*&gt;\s?/', '<blockquote style="margin:0 0 8px;padding-left:11px;border-left:3px solid #D5DAE1;color:#666D7A;">', '</blockquote>', 'quote' ],
				[ '/^\s*[-*]\s+/', '<ul style="margin:0 0 8px;padding-left:22px;">', '</ul>', 'li' ],
				[ '/^\s*\d+[.)]\s+/', '<ol style="margin:0 0 8px;padding-left:22px;">', '</ol>', 'li' ],
			] as [ $re, $open, $close, $kind ] ) {
				if ( ! preg_match( $re, $line ) ) {
					continue;
				}
				$flush();
				$items = [];
				for ( ; $i < $count && preg_match( $re, $lines[ $i ] ); $i++ ) {
					$items[] = $inline( preg_replace( $re, '', $lines[ $i ] ) );
				}
				$i--;
				$out[]   = $open . ( $kind === 'li' ? '<li>' . implode( '</li><li>', $items ) . '</li>' : implode( '<br>', $items ) ) . $close;
				$matched = true;
				break;
			}
			if ( $matched ) {
				continue;
			}
			if ( trim( $line ) === '' ) {
				$flush();
				continue;
			}
			$para[] = $line;
		}
		$flush();
		return preg_replace_callback( '/\x00(\d+)\x00/', fn( $m ) => $keep[ (int) $m[1] ], implode( '', $out ) );
	}

	/** Text with the markdown marks taken out (subjects, previews). */
	public static function markdown_plain( $text ) {
		$text = preg_replace( '/\[([^\]\n]+)\]\((https?:[^)\s]+)\)/', '$1', (string) $text );
		$text = preg_replace( '/^\s*(```.*|&gt;\s?|>\s?|[-*]\s+|\d+[.)]\s+)/m', '', $text );
		$text = preg_replace( '/\*\*(?=\S)([^*\n]*?\S)\*\*|~~(?=\S)([^~\n]*?\S)~~|`([^`\n]+)`/', '$1$2$3', $text );
		$text = preg_replace( '/(^|[^\w*])\*(?=\S)([^*\n]*?\S)\*(?![\w*])|(^|[^\w])_(?=\S)([^_\n]*?\S)_(?!\w)/', '$1$3$2$4', $text );
		return trim( $text );
	}

	// ── reactions ───────────────────────────────────────────────────────

	/** Reactions per message id: [id => [{key, by: [{id, name}]}]]. */
	public static function reactions_for( $message_ids ) {
		global $wpdb;
		if ( empty( $message_ids ) ) {
			return [];
		}
		$t    = $wpdb->base_prefix . 'captaincore_chat_reactions';
		$in   = implode( ',', array_map( 'intval', $message_ids ) );
		$rows = $wpdb->get_results( "SELECT chat_message_id, user_id, reaction FROM $t WHERE chat_message_id IN ($in) ORDER BY chat_reaction_id ASC" );
		$out  = [];
		$name = [];
		foreach ( $rows ?: [] as $r ) {
			$mid = (int) $r->chat_message_id;
			$uid = (int) $r->user_id;
			if ( ! isset( $name[ $uid ] ) ) {
				$u            = get_userdata( $uid );
				$name[ $uid ] = $u ? $u->display_name : 'Someone';
			}
			$at = null;
			foreach ( $out[ $mid ] ?? [] as $i => $g ) {
				if ( $g['key'] === $r->reaction ) {
					$at = $i;
				}
			}
			if ( $at === null ) {
				$out[ $mid ][] = [ 'key' => $r->reaction, 'by' => [] ];
				$at            = count( $out[ $mid ] ) - 1;
			}
			$out[ $mid ][ $at ]['by'][] = [ 'id' => $uid, 'name' => $name[ $uid ] ];
		}
		return $out;
	}

	/**
	 * Add ($on) or take back a reaction on a message the user can see.
	 * Customers can't react to internal notes or events (they never see
	 * them). Everyone in the conversation gets message.reacted live.
	 */
	public static function react( $user_id, $message_id, $key, $on = true ) {
		global $wpdb;
		$key     = (string) $key;
		$message = ChatMessages::get( (int) $message_id );
		if ( ! $message || ! self::viewable( $user_id, (int) $message->chat_conversation_id ) || ( (int) $message->internal && ! self::is_operator( $user_id ) ) ) {
			return new \WP_Error( 'not_found', 'Message not found.', [ 'status' => 404 ] );
		}
		if ( ! in_array( $key, self::REACTIONS, true ) ) {
			return new \WP_Error( 'bad_reaction', 'Unknown reaction.', [ 'status' => 400 ] );
		}
		if ( ! in_array( $message->kind, [ 'comment', 'note' ], true ) ) {
			return new \WP_Error( 'bad_reaction', 'Only messages take reactions.', [ 'status' => 400 ] );
		}
		$t   = $wpdb->base_prefix . 'captaincore_chat_reactions';
		$mid = (int) $message->chat_message_id;
		$cid = (int) $message->chat_conversation_id;
		if ( $on ) {
			$wpdb->query( $wpdb->prepare( "INSERT IGNORE INTO $t (chat_message_id, chat_conversation_id, user_id, reaction, created_at) VALUES (%d, %d, %d, %s, %s)", $mid, $cid, $user_id, $key, gmdate( 'Y-m-d H:i:s' ) ) );
		} else {
			$wpdb->delete( $t, [ 'chat_message_id' => $mid, 'user_id' => (int) $user_id, 'reaction' => $key ] );
		}
		$reactions = self::reactions_for( [ $mid ] )[ $mid ] ?? [];
		$event     = [ 'type' => 'message.reacted', 'conversation_id' => $cid, 'message_id' => $mid, 'reactions' => $reactions ];
		do_action( 'captaincore_chat_event', $event, (int) ChatConversations::get( $cid )->user_id, (bool) (int) $message->internal );
		return [ 'message_id' => $mid, 'reactions' => $reactions ];
	}

	// ── edits ───────────────────────────────────────────────────────────

	/** A user's display name, or $fallback when there is no such user. */
	private static function user_name( $user_id, $fallback = '' ) {
		static $cache = [];
		if ( $user_id <= 0 ) {
			return $fallback;
		}
		if ( ! isset( $cache[ $user_id ] ) ) {
			$u                 = get_userdata( $user_id );
			$cache[ $user_id ] = $u ? $u->display_name : '';
		}
		return $cache[ $user_id ] !== '' ? $cache[ $user_id ] : $fallback;
	}

	/** Whether $user_id may change this message row (see the rules up top). */
	public static function can_edit( $user_id, $message ) {
		if ( ! in_array( $message->kind, [ 'comment', 'note' ], true ) ) {
			return false;
		}
		if ( $user_id <= 0 || (int) $message->author_user_id !== (int) $user_id ) {
			return false;
		}
		return $message->kind === 'comment' || self::is_operator( $user_id );
	}

	/**
	 * Change a message's text. The version it replaces goes into
	 * captaincore_chat_revisions first. Attachments, reactions and the
	 * original email stay as they are, and nothing is re-sent: a reply email
	 * still waiting on its delay goes out with the new text, one already
	 * sent keeps the old. Everyone in the conversation gets message.updated.
	 */
	public static function edit_message( $user_id, $message_id, $body ) {
		global $wpdb;
		$message  = ChatMessages::get( (int) $message_id );
		$operator = self::is_operator( $user_id );
		if ( ! $message || ! self::viewable( $user_id, (int) $message->chat_conversation_id ) || ( (int) $message->internal && ! $operator ) ) {
			return new \WP_Error( 'not_found', 'Message not found.', [ 'status' => 404 ] );
		}
		if ( ! self::can_edit( $user_id, $message ) ) {
			return new \WP_Error( 'not_allowed', 'You can only edit your own messages.', [ 'status' => 403 ] );
		}
		$mid   = (int) $message->chat_message_id;
		$files = self::attachments_for( [ $mid ] )[ $mid ] ?? [];
		$body  = self::body_or_files( $body, $files );
		if ( is_wp_error( $body ) ) {
			return $body;
		}
		if ( $body !== (string) $message->body ) {
			$now  = gmdate( 'Y-m-d H:i:s' );
			$prev = (int) ( $message->edited_by ?? 0 );
			$wpdb->insert( $wpdb->base_prefix . 'captaincore_chat_revisions', [
				'chat_message_id'      => $mid,
				'chat_conversation_id' => (int) $message->chat_conversation_id,
				'body'                 => (string) $message->body,
				'user_id'              => $message->edited_at ? $prev : (int) $message->author_user_id,
				'user_name'            => $message->edited_at ? self::user_name( $prev ) : (string) $message->author_name,
				'written_at'           => $message->edited_at ?: $message->created_at,
				'replaced_at'          => $now,
			] );
			if ( ! $wpdb->insert_id ) {
				return new \WP_Error( 'save_failed', 'Could not keep the earlier version, so the message was not changed.', [ 'status' => 500 ] );
			}
			ChatMessages::update( [ 'body' => $body, 'edited_at' => $now, 'edited_by' => (int) $user_id ], [ 'chat_message_id' => $mid ] );
		}
		$row       = ChatMessages::get( $mid );
		$presented = self::present_message( $row, $files, self::reactions_for( [ $mid ] )[ $mid ] ?? [] );
		if ( $body !== (string) $message->body ) {
			self::emit( 'message.updated', self::present_conversation( ChatConversations::get( $row->chat_conversation_id ), false ), $presented );
		}
		return [ 'message' => $presented ];
	}

	/**
	 * Every version of a message, newest (the current text) first. Operators
	 * only: an edit is often a correction (another customer's details, a
	 * pasted password), and the customer side shows just "edited".
	 */
	public static function revisions( $user_id, $message_id ) {
		global $wpdb;
		$message = ChatMessages::get( (int) $message_id );
		if ( ! $message || ! self::is_operator( $user_id ) || ! self::viewable( $user_id, (int) $message->chat_conversation_id ) ) {
			return new \WP_Error( 'not_found', 'Message not found.', [ 'status' => 404 ] );
		}
		$t    = $wpdb->base_prefix . 'captaincore_chat_revisions';
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $t WHERE chat_message_id = %d ORDER BY chat_revision_id DESC", (int) $message->chat_message_id ) );
		$out  = [ [
			'body'    => (string) $message->body,
			'by'      => $message->edited_at ? self::user_name( (int) $message->edited_by, 'Someone' ) : (string) $message->author_name,
			'at'      => self::ts( $message->edited_at ?: $message->created_at ),
			'current' => true,
		] ];
		foreach ( $rows ?: [] as $r ) {
			$out[] = [
				'body'    => (string) $r->body,
				'by'      => (string) $r->user_name,
				'at'      => self::ts( $r->written_at ),
				'current' => false,
			];
		}
		return [ 'message_id' => (int) $message->chat_message_id, 'revisions' => $out ];
	}

	// ── CaptainCore logs ────────────────────────────────────────────────

	/**
	 * [title, body]. Without a process name the first line is the title,
	 * and it comes off the body so it doesn't show twice.
	 */
	private static function log_split( $name, $description ) {
		$description = trim( str_replace( "\r\n", "\n", (string) $description ) );
		if ( trim( (string) $name ) !== '' ) {
			return [ trim( (string) $name ), $description ];
		}
		$parts = explode( "\n", $description, 2 );
		$title = trim( self::markdown_plain( ltrim( $parts[0], "# \t" ) ) );
		if ( $title === '' ) {
			return [ 'Log entry', $description ];
		}
		return [ mb_strlen( $title ) > 90 ? mb_substr( $title, 0, 89 ) . '…' : $title, mb_strlen( $title ) > 90 ? $description : trim( $parts[1] ?? '' ) ];
	}

	/**
	 * Linked logs for message rows, keyed by message id, in the order they
	 * were linked: [{id, site_id, site_name, title, body (markdown as
	 * written), author, created_at, files, public, url}]. A log deleted
	 * since shows as {id, missing: true}.
	 */
	public static function logs_for( $rows ) {
		global $wpdb;
		$pairs = [];
		foreach ( $rows as $row ) {
			foreach ( array_filter( explode( ',', (string) ( $row->log_refs ?? '' ) ) ) as $ref ) {
				[ $log_id, $site_id ] = array_map( 'intval', explode( ':', $ref ) + [ 1 => 0 ] );
				if ( $log_id > 0 ) {
					$pairs[ (int) $row->chat_message_id ][] = [ $log_id, $site_id ];
				}
			}
		}
		if ( ! $pairs ) {
			return [];
		}
		$log_ids  = array_unique( array_merge( ...array_map( fn( $p ) => array_column( $p, 0 ), array_values( $pairs ) ) ) );
		$site_ids = array_unique( array_merge( ...array_map( fn( $p ) => array_column( $p, 1 ), array_values( $pairs ) ) ) );
		$in       = fn( $ids ) => implode( ',', array_map( 'intval', $ids ) ?: [ 0 ] );
		$p        = $wpdb->prefix;
		$logs     = [];
		foreach ( $wpdb->get_results( "SELECT l.*, pr.name, (SELECT COUNT(*) FROM {$p}captaincore_process_log_file f WHERE f.process_log_id = l.process_log_id) AS files
			FROM {$p}captaincore_process_logs l LEFT JOIN {$p}captaincore_processes pr ON pr.process_id = l.process_id WHERE l.process_log_id IN ({$in( $log_ids )})" ) ?: [] as $l ) {
			$logs[ (int) $l->process_log_id ] = $l;
		}
		$sites = [];
		foreach ( $wpdb->get_results( "SELECT site_id, name FROM {$p}captaincore_sites WHERE site_id IN ({$in( $site_ids )})" ) ?: [] as $st ) {
			$sites[ (int) $st->site_id ] = (string) $st->name;
		}
		$out = [];
		foreach ( $pairs as $mid => $list ) {
			foreach ( $list as [ $log_id, $site_id ] ) {
				$l = $logs[ $log_id ] ?? null;
				if ( ! $l ) {
					$out[ $mid ][] = [ 'id' => $log_id, 'missing' => true ];
					continue;
				}
				[ $title, $text ] = self::log_split( $l->name, $l->description );
				$out[ $mid ][] = [
					'id'         => $log_id,
					'site_id'    => $site_id ?: null,
					'site_name'  => $sites[ $site_id ] ?? '',
					'title'      => $title,
					'body'       => $text,
					'author'     => self::user_name( (int) $l->user_id, 'System' ),
					'created_at' => self::ts( $l->created_at ),
					'files'      => (int) $l->files,
					'public'     => (int) $l->public !== 0,
					'url'        => $site_id ? home_url( '/account/sites/' . $site_id . '/timeline' ) : '',
				];
			}
		}
		return $out;
	}

	/** Site ids the conversation's customer can use ([] for guests). */
	private static function customer_site_ids( $conversation ) {
		$access = (int) $conversation->user_id > 0 ? self::customer_access( (int) $conversation->user_id ) : [];
		return is_wp_error( $access ) || ! empty( $access['operator'] ) ? [] : array_column( $access['sites'] ?? [], 'id' );
	}

	/**
	 * Check the logs an operator links into a message and return them as
	 * log_refs ("<log>:<site>,…"), '' for none. $raw is that string or an
	 * array of the same pairs. Each log must be on that site's timeline; a
	 * reply also needs a public log on a site the customer can use.
	 */
	private static function log_refs( $user_id, $conversation, $raw, $kind ) {
		global $wpdb;
		$list = is_array( $raw ) ? $raw : explode( ',', (string) $raw );
		$list = array_values( array_unique( array_filter( array_map( 'trim', array_map( 'strval', $list ) ) ) ) );
		if ( ! $list ) {
			return '';
		}
		if ( ! self::is_operator( $user_id ) ) {
			return new \WP_Error( 'operators_only', 'Only operators can link logs.', [ 'status' => 403 ] );
		}
		if ( count( $list ) > self::MAX_LOGS ) {
			return new \WP_Error( 'too_many_logs', 'Link at most ' . self::MAX_LOGS . ' logs in one message.', [ 'status' => 400 ] );
		}
		$p     = $wpdb->prefix;
		$sites = $kind === 'comment' ? self::customer_site_ids( $conversation ) : null;
		$out   = [];
		foreach ( $list as $ref ) {
			if ( ! preg_match( '/^(\d+):(\d+)$/', $ref, $m ) ) {
				return new \WP_Error( 'bad_log', 'Logs are linked as <log id>:<site id>.', [ 'status' => 400 ] );
			}
			$row = $wpdb->get_row( $wpdb->prepare(
				"SELECT l.public FROM {$p}captaincore_process_logs l JOIN {$p}captaincore_process_log_site s ON s.process_log_id = l.process_log_id
				WHERE l.process_log_id = %d AND s.site_id = %d LIMIT 1", (int) $m[1], (int) $m[2]
			) );
			if ( ! $row ) {
				return new \WP_Error( 'bad_log', "Log {$m[1]} isn't on that site's timeline.", [ 'status' => 400 ] );
			}
			if ( $sites !== null && (int) $row->public === 0 ) {
				return new \WP_Error( 'private_log', "Log {$m[1]} is internal. Link it in a note instead.", [ 'status' => 400 ] );
			}
			if ( $sites !== null && ! in_array( (int) $m[2], $sites, true ) ) {
				return new \WP_Error( 'log_site', "The customer can't access that log's site. Link it in a note instead.", [ 'status' => 400 ] );
			}
			$out[] = (int) $m[1] . ':' . (int) $m[2];
		}
		return implode( ',', $out );
	}

	/**
	 * Logs an operator can link into this conversation: the sites they can
	 * pick from (the customer's, plus the conversation's own site), and the
	 * newest 30 logs on one of them, optionally searched.
	 */
	public static function log_options( $user_id, $conversation_id, $q = [] ) {
		global $wpdb;
		$conversation = self::viewable( $user_id, $conversation_id );
		if ( ! $conversation || ! self::is_operator( $user_id ) ) {
			return self::not_found();
		}
		$p       = $wpdb->prefix;
		$mine    = self::customer_site_ids( $conversation );
		$ids     = array_values( array_unique( array_merge( (int) $conversation->site_id ? [ (int) $conversation->site_id ] : [], $mine ) ) );
		$in      = implode( ',', array_map( 'intval', $ids ) ?: [ 0 ] );
		$sites   = array_map( fn( $s ) => [ 'id' => (int) $s->site_id, 'name' => (string) $s->name, 'customer' => in_array( (int) $s->site_id, $mine, true ) ],
			$wpdb->get_results( "SELECT site_id, name FROM {$p}captaincore_sites WHERE site_id IN ($in) ORDER BY name" ) ?: [] );
		$site_id = (int) ( $q['site'] ?? 0 );
		if ( $site_id && ! in_array( $site_id, $ids, true ) ) {
			// Any other site works for a note; it just isn't suggested.
			$name = $wpdb->get_var( $wpdb->prepare( "SELECT name FROM {$p}captaincore_sites WHERE site_id = %d", $site_id ) );
			if ( $name === null ) {
				return new \WP_Error( 'not_found', 'Site not found.', [ 'status' => 404 ] );
			}
			$sites[] = [ 'id' => $site_id, 'name' => (string) $name, 'customer' => false ];
		}
		if ( ! $site_id ) {
			$site_id = (int) $conversation->site_id ?: ( $sites[0]['id'] ?? 0 );
		}
		$logs = [];
		if ( $site_id ) {
			$where  = 's.site_id = %d';
			$args   = [ $site_id ];
			$search = trim( (string) ( $q['q'] ?? '' ) );
			if ( $search !== '' ) {
				$like  = '%' . $wpdb->esc_like( mb_substr( $search, 0, 100 ) ) . '%';
				$where .= ' AND (l.description LIKE %s OR pr.name LIKE %s)';
				array_push( $args, $like, $like );
			}
			$rows = $wpdb->get_results( $wpdb->prepare(
				"SELECT l.process_log_id, l.description, l.public, l.user_id, l.created_at, pr.name FROM {$p}captaincore_process_log_site s
				JOIN {$p}captaincore_process_logs l ON l.process_log_id = s.process_log_id LEFT JOIN {$p}captaincore_processes pr ON pr.process_id = l.process_id
				WHERE $where ORDER BY l.created_at DESC LIMIT 30", $args
			) );
			foreach ( $rows ?: [] as $r ) {
				$logs[] = [
					'id'         => (int) $r->process_log_id,
					'title'      => self::log_split( $r->name, $r->description )[0],
					'excerpt'    => mb_substr( preg_replace( '/\s+/', ' ', self::markdown_plain( self::log_split( $r->name, $r->description )[1] ) ), 0, 160 ),
					'author'     => self::user_name( (int) $r->user_id, 'System' ),
					'created_at' => self::ts( $r->created_at ),
					'public'     => (int) $r->public !== 0,
				];
			}
		}
		return [ 'sites' => $sites, 'site_id' => $site_id, 'logs' => $logs ];
	}

	// ── drafts ──────────────────────────────────────────────────────────

	/** The conversation's waiting draft, presented, or null. */
	public static function draft( $conversation_id ) {
		global $wpdb;
		$t   = $wpdb->base_prefix . 'captaincore_chat_drafts';
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $t WHERE chat_conversation_id = %d", (int) $conversation_id ) );
		if ( ! $row ) {
			return null;
		}
		return [
			'conversation_id'     => (int) $row->chat_conversation_id,
			'kind'                => $row->kind,
			'body'                => (string) $row->body,
			'by'                  => $row->agent !== '' && isset( self::AGENTS[ $row->agent ] ) ? self::AGENTS[ $row->agent ] : self::user_name( (int) $row->user_id, 'Someone' ),
			'agent'               => (string) $row->agent,
			'user_id'             => (int) $row->user_id ?: null,
			'based_on_message_id' => (int) $row->based_on_message_id,
			'log_refs'            => (string) $row->log_refs,
			'logs'                => $row->log_refs !== '' ? ( self::logs_for( [ (object) [ 'chat_message_id' => 0, 'log_refs' => $row->log_refs ] ] )[0] ?? [] ) : [],
			'created_at'          => self::ts( $row->created_at ),
			'updated_at'          => self::ts( $row->updated_at ),
		];
	}

	/**
	 * Write (or replace) the conversation's draft. Operators only. agent
	 * names the AI that wrote it (Chat::AGENTS); without one it is the
	 * operator's own. A person's edit in the reply box keeps the agent's
	 * name unless keep_author is false, so the strip still reads "Draft by
	 * Claude" while they polish it. Operators get draft.updated live.
	 *
	 * @param array $input body, kind (comment|note), agent, keep_author,
	 *                     log_refs ("<log>:<site>,…"; left out keeps the draft's)
	 */
	public static function save_draft( $user_id, $conversation_id, $input ) {
		global $wpdb;
		$conversation = self::viewable( $user_id, $conversation_id );
		if ( ! $conversation || ! self::is_operator( $user_id ) ) {
			return self::not_found();
		}
		$cid  = (int) $conversation->chat_conversation_id;
		$t    = $wpdb->base_prefix . 'captaincore_chat_drafts';
		$old  = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $t WHERE chat_conversation_id = %d", $cid ) );
		// Left out, kind stays what the draft was.
		$kind = (string) ( $input['kind'] ?? ( $old ? $old->kind : 'comment' ) ) ?: 'comment';
		if ( ! in_array( $kind, [ 'comment', 'note' ], true ) ) {
			return new \WP_Error( 'bad_kind', 'kind must be comment or note.', [ 'status' => 400 ] );
		}
		$agent = (string) ( $input['agent'] ?? '' );
		if ( $agent !== '' && ! isset( self::AGENTS[ $agent ] ) ) {
			return new \WP_Error( 'bad_agent', 'Unknown agent.', [ 'status' => 400 ] );
		}
		$now  = gmdate( 'Y-m-d H:i:s' );
		$refs = self::log_refs( $user_id, $conversation, array_key_exists( 'log_refs', $input ) ? $input['log_refs'] : ( $old ? $old->log_refs : '' ), $kind );
		if ( is_wp_error( $refs ) ) {
			return $refs;
		}
		$body = self::body_or_files( $input['body'] ?? '', $refs !== '' ? [ 'log' ] : [] );
		if ( is_wp_error( $body ) ) {
			return $body;
		}
		$keep = $old && $agent === '' && ( ! isset( $input['keep_author'] ) || ! empty( $input['keep_author'] ) );
		$m    = $wpdb->base_prefix . 'captaincore_chat_messages';
		$wpdb->replace( $t, [
			'chat_conversation_id' => $cid,
			'kind'                 => $kind,
			'body'                 => $body,
			'user_id'              => $keep ? (int) $old->user_id : (int) $user_id,
			'agent'                => $keep ? (string) $old->agent : $agent,
			// An edit of a draft is still based on what its writer saw.
			'log_refs'             => $refs,
			'based_on_message_id'  => $keep ? (int) $old->based_on_message_id : (int) $wpdb->get_var( $wpdb->prepare( "SELECT MAX(chat_message_id) FROM $m WHERE chat_conversation_id = %d", $cid ) ),
			'created_at'           => $old ? $old->created_at : $now,
			'updated_at'           => $now,
		] );
		if ( $wpdb->last_error ) {
			return new \WP_Error( 'save_failed', 'Could not save the draft.', [ 'status' => 500 ] );
		}
		$draft = self::draft( $cid );
		self::emit_draft( $cid, $draft );
		return [ 'draft' => $draft ];
	}

	/** Throw the draft away (operators). */
	public static function discard_draft( $user_id, $conversation_id ) {
		$conversation = self::viewable( $user_id, $conversation_id );
		if ( ! $conversation || ! self::is_operator( $user_id ) ) {
			return self::not_found();
		}
		self::clear_draft( (int) $conversation->chat_conversation_id );
		return [ 'draft' => null ];
	}

	/**
	 * Remove the draft once it has been answered: any operator comment
	 * clears it, a note clears only a note draft. $sent null always clears.
	 */
	public static function clear_draft( $conversation_id, $sent = null ) {
		global $wpdb;
		$t     = $wpdb->base_prefix . 'captaincore_chat_drafts';
		$where = [ 'chat_conversation_id' => (int) $conversation_id ];
		if ( $sent === 'note' ) {
			$where['kind'] = 'note';
		}
		if ( $wpdb->delete( $t, $where ) ) {
			self::emit_draft( (int) $conversation_id, null );
		}
	}

	/** draft.updated to operators only (internal, no customer). */
	private static function emit_draft( $conversation_id, $draft ) {
		do_action( 'captaincore_chat_event', [ 'type' => 'draft.updated', 'conversation_id' => (int) $conversation_id, 'draft' => $draft ], 0, true );
	}

	// ── agents ──────────────────────────────────────────────────────────

	/**
	 * Conversations still waiting on a person: open, the customer's last
	 * message unanswered for at least $min_wait seconds (and at most
	 * $max_wait, so old imported threads stay out), with no agent note
	 * since that message. The waiting message has to have been written
	 * here: one that came in with an Intercom import is answered there
	 * (or already was), so it is not ours to triage. Each comes with what a triage needs: the thread
	 * (internal notes included), the customer, what they can access and
	 * the site they tagged. Oldest wait first.
	 */
	public static function unattended( $min_wait = 600, $max_wait = 172800, $limit = 10 ) {
		global $wpdb;
		$c    = $wpdb->base_prefix . 'captaincore_chat_conversations';
		$m    = $wpdb->base_prefix . 'captaincore_chat_messages';
		$now  = time();
		$rows = $wpdb->get_results( $wpdb->prepare(
			"SELECT c.* FROM $c c WHERE c.status = 'open' AND c.waiting_since IS NOT NULL AND c.waiting_since <= %s AND c.waiting_since >= %s
			AND NOT EXISTS (SELECT 1 FROM $m b WHERE b.chat_conversation_id = c.chat_conversation_id AND b.via = 'bot' AND b.chat_message_id > COALESCE(
				(SELECT MAX(x.chat_message_id) FROM $m x WHERE x.chat_conversation_id = c.chat_conversation_id AND x.kind = 'comment' AND x.author_kind = 'customer'), 0))
			AND (SELECT y.external_id FROM $m y WHERE y.chat_conversation_id = c.chat_conversation_id AND y.kind = 'comment' AND y.author_kind = 'customer'
				ORDER BY y.created_at DESC, y.chat_message_id DESC LIMIT 1) = ''
			ORDER BY c.waiting_since ASC LIMIT %d",
			gmdate( 'Y-m-d H:i:s', $now - max( 60, (int) $min_wait ) ), gmdate( 'Y-m-d H:i:s', $now - max( 3600, (int) $max_wait ) ), max( 1, min( 25, (int) $limit ) )
		) );
		$config = Configurations::get();
		$base   = isset( $config->path ) ? trim( (string) $config->path, '/' ) : 'account';
		return array_map( function ( $row ) use ( $now, $base ) {
			$uid    = (int) $row->user_id;
			$access = $uid > 0 ? self::customer_access( $uid ) : null;
			$site   = null;
			if ( (int) $row->site_id ) {
				$s    = Sites::get( (int) $row->site_id );
				$site = $s ? [ 'id' => (int) $row->site_id, 'name' => (string) $s->name, 'url' => (string) ( json_decode( (string) $s->details )->home_url ?? '' ) ] : null;
			}
			return [
				'conversation'    => self::present_conversation( $row, true ),
				'waiting_minutes' => (int) floor( ( $now - strtotime( $row->waiting_since . ' UTC' ) ) / 60 ),
				'link'            => home_url( '/' . ( $base !== '' ? $base . '/' : '' ) . 'chat/' . (int) $row->chat_conversation_id ),
				'customer'        => [ 'user_id' => $uid ?: null, 'name' => $row->contact_name, 'email' => $row->contact_email, 'guest' => $uid === 0 ],
				'access'          => is_wp_error( $access ) ? null : $access,
				'site'            => $site,
				'messages'        => self::messages( (int) $row->chat_conversation_id, true, 0, 40 ),
			];
		}, $rows ?: [] );
	}

	// ── uploads ─────────────────────────────────────────────────────────

	/**
	 * The `files` field of a multipart request (one file or files[]) as a
	 * list, or a WP_Error naming the file that can't be taken.
	 */
	public static function uploads( $raw ) {
		if ( empty( $raw ) || ! isset( $raw['name'] ) ) {
			return is_array( $raw ) && isset( $raw[0]['tmp_name'] ) ? $raw : [];
		}
		$list = [];
		if ( is_array( $raw['name'] ) ) {
			foreach ( array_keys( $raw['name'] ) as $i ) {
				$list[] = [ 'name' => $raw['name'][ $i ], 'tmp_name' => $raw['tmp_name'][ $i ], 'size' => (int) $raw['size'][ $i ], 'error' => (int) $raw['error'][ $i ], 'type' => $raw['type'][ $i ] ?? '' ];
			}
		} else {
			$list[] = $raw;
		}
		if ( count( $list ) > self::MAX_FILES ) {
			return new \WP_Error( 'too_many_files', 'Up to ' . self::MAX_FILES . ' files per message.', [ 'status' => 400 ] );
		}
		foreach ( $list as $f ) {
			$name  = sanitize_file_name( (string) ( $f['name'] ?? '' ) ) ?: 'A file';
			$error = (int) ( $f['error'] ?? 0 );
			if ( in_array( $error, [ UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE ], true ) || (int) $f['size'] > self::MAX_FILE_SIZE ) {
				return new \WP_Error( 'file_too_big', "$name is too big (" . size_format( self::max_upload() ) . ' max).', [ 'status' => 413 ] );
			}
			if ( $error !== UPLOAD_ERR_OK || ! is_uploaded_file( (string) $f['tmp_name'] ) && ! defined( 'CAPTAINCORE_CHAT_TESTING' ) ) {
				return new \WP_Error( 'upload_failed', "$name didn't upload. Try again.", [ 'status' => 400 ] );
			}
			if ( (int) $f['size'] <= 0 ) {
				return new \WP_Error( 'file_empty', "$name is empty.", [ 'status' => 400 ] );
			}
		}
		return $list;
	}

	/** Largest file the composer may send: ours, or PHP's if lower. */
	public static function max_upload() {
		return (int) min( self::MAX_FILE_SIZE, wp_max_upload_size() ?: self::MAX_FILE_SIZE );
	}

	/** The message text; empty is fine when files carry the message. */
	private static function body_or_files( $raw, $files ) {
		if ( $files && is_string( $raw ) && trim( self::strip_controls( $raw ) ) === '' ) {
			return '';
		}
		return self::clean_body( $raw );
	}

	/** Store a new message's files, and return it presented with them. */
	private static function attach( $conversation_id, $message, $files ) {
		if ( ! $files ) {
			return $message;
		}
		foreach ( $files as $f ) {
			ChatNotify::store_file( (int) $conversation_id, (int) $message['id'], $f );
		}
		$message['attachments'] = self::attachments_for( [ (int) $message['id'] ] )[ (int) $message['id'] ] ?? [];
		return $message;
	}

	// ── text hygiene ────────────────────────────────────────────────────

	/** Strip control characters and bidi overrides, keep newlines and tabs. */
	public static function strip_controls( $text ) {
		$text = str_replace( "\r\n", "\n", (string) $text );
		return (string) preg_replace( '/[\x{0000}-\x{0008}\x{000B}-\x{001F}\x{007F}-\x{009F}\x{2028}\x{2029}\x{202A}-\x{202E}\x{2066}-\x{2069}]/u', '', $text );
	}

	public static function clean_body( $body ) {
		if ( ! is_string( $body ) || ! mb_check_encoding( $body, 'UTF-8' ) ) {
			return new \WP_Error( 'bad_body', 'Message must be valid UTF-8 text.', [ 'status' => 400 ] );
		}
		$body = trim( self::strip_controls( $body ) );
		if ( $body === '' ) {
			return new \WP_Error( 'bad_body', 'Message is empty.', [ 'status' => 400 ] );
		}
		if ( mb_strlen( $body ) > self::MAX_BODY ) {
			return new \WP_Error( 'bad_body', 'Message is too long (10,000 characters max).', [ 'status' => 400 ] );
		}
		return $body;
	}

	public static function clean_line( $text, $max ) {
		if ( ! is_string( $text ) || ! mb_check_encoding( $text, 'UTF-8' ) ) {
			return new \WP_Error( 'bad_text', 'Text must be valid UTF-8.', [ 'status' => 400 ] );
		}
		$text = trim( (string) preg_replace( '/\s+/u', ' ', self::strip_controls( $text ) ) );
		if ( $text === '' ) {
			return new \WP_Error( 'bad_text', 'Text is empty.', [ 'status' => 400 ] );
		}
		if ( mb_strlen( $text ) > $max ) {
			return new \WP_Error( 'bad_text', 'Text is too long.', [ 'status' => 400 ] );
		}
		return $text;
	}

	public static function first_line( $text, $max ) {
		$line = trim( strtok( (string) $text, "\n" ) ?: '' );
		return mb_strlen( $line ) > $max ? rtrim( mb_substr( $line, 0, $max - 1 ) ) . '…' : $line;
	}
}
