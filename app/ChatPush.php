<?php

namespace CaptainCore;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Phone (and desktop) notifications for operators: a customer message
 * pushes to every device an operator turned notifications on for, through
 * WebPush. The device needs no login to receive; the subscription is
 * stored here against the operator.
 *
 * Who gets one: every operator with a subscription, except operators the
 * relay reports as watching the inbox (on screen and in use; the inbox
 * sends focus frames), so a phone stays quiet while its owner is at the
 * desk. Operator status is checked at send time: a former admin's devices
 * get nothing.
 *
 * Lock screen text follows captaincore_chat_push_preview: 'full' (default:
 * customer name and the first line) or 'name' (just who wrote).
 */
class ChatPush {

	const MAX_FAILURES = 20;

	/** Push services browsers use. Endpoints anywhere else are refused (no SSRF). */
	public static function allowed_endpoint( $endpoint ) {
		$p = wp_parse_url( (string) $endpoint );
		if ( empty( $p['scheme'] ) || $p['scheme'] !== 'https' || empty( $p['host'] ) ) {
			return false;
		}
		return (bool) preg_match( '/(^|\.)(push\.apple\.com|fcm\.googleapis\.com|push\.services\.mozilla\.com|notify\.windows\.com)$/', strtolower( $p['host'] ) );
	}

	public static function subscribe( $user_id, $input ) {
		if ( ! Chat::is_operator( (int) $user_id ) ) {
			return new \WP_Error( 'operators_only', 'Notifications are for operators.', [ 'status' => 403 ] );
		}
		$endpoint = (string) ( $input['endpoint'] ?? '' );
		$p256dh   = (string) ( $input['keys']['p256dh'] ?? '' );
		$auth     = (string) ( $input['keys']['auth'] ?? '' );
		if ( ! self::allowed_endpoint( $endpoint ) || strlen( $endpoint ) > 2000
			|| strlen( WebPush::b64url_decode( $p256dh ) ) !== 65 || strlen( WebPush::b64url_decode( $auth ) ) !== 16 ) {
			return new \WP_Error( 'bad_subscription', 'That push subscription is not valid.', [ 'status' => 400 ] );
		}
		global $wpdb;
		$t = $wpdb->base_prefix . 'captaincore_push_subscriptions';
		$wpdb->query( $wpdb->prepare(
			"INSERT INTO $t (user_id, endpoint, endpoint_hash, p256dh, auth, user_agent, failures, created_at) VALUES (%d, %s, %s, %s, %s, %s, 0, %s)
			ON DUPLICATE KEY UPDATE user_id = VALUES(user_id), p256dh = VALUES(p256dh), auth = VALUES(auth), user_agent = VALUES(user_agent), failures = 0",
			$user_id, $endpoint, hash( 'sha256', $endpoint ), $p256dh, $auth,
			mb_substr( sanitize_text_field( (string) ( $_SERVER['HTTP_USER_AGENT'] ?? '' ) ), 0, 255 ), gmdate( 'Y-m-d H:i:s' )
		) );
		return self::status( $user_id );
	}

	public static function unsubscribe( $user_id, $endpoint ) {
		global $wpdb;
		$t = $wpdb->base_prefix . 'captaincore_push_subscriptions';
		$wpdb->query( $wpdb->prepare( "DELETE FROM $t WHERE user_id = %d AND endpoint_hash = %s", $user_id, hash( 'sha256', (string) $endpoint ) ) );
		return self::status( $user_id );
	}

	public static function status( $user_id ) {
		global $wpdb;
		$t = $wpdb->base_prefix . 'captaincore_push_subscriptions';
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT push_subscription_id, user_agent, created_at, last_success_at FROM $t WHERE user_id = %d ORDER BY push_subscription_id", $user_id ) ) ?: [];
		return [
			'public_key' => WebPush::public_key(),
			'devices'    => array_map( fn( $r ) => [
				'id'           => (int) $r->push_subscription_id,
				'device'       => self::device_name( $r->user_agent ),
				'created_at'   => strtotime( $r->created_at . ' UTC' ),
				'last_success' => $r->last_success_at ? strtotime( $r->last_success_at . ' UTC' ) : null,
			], $rows ),
		];
	}

	private static function device_name( $ua ) {
		foreach ( [ 'iPhone' => 'iPhone', 'iPad' => 'iPad', 'Android' => 'Android', 'Macintosh' => 'Mac', 'Windows' => 'Windows', 'Linux' => 'Linux' ] as $needle => $name ) {
			if ( stripos( (string) $ua, $needle ) !== false ) {
				return $name;
			}
		}
		return 'Browser';
	}

	/**
	 * A customer wrote. Call right after its message.created publish, so
	 * the relay's watching list is current.
	 *
	 * @param array $conversation presented conversation
	 * @param array $message      presented message
	 */
	public static function customer_message( $conversation, $message ) {
		if ( ! empty( $message['internal'] ) || ( $message['author_kind'] ?? '' ) !== 'customer' ) {
			return 0;
		}
		$name    = (string) ( $conversation['customer_name'] ?? '' ) ?: ( (string) ( $message['author_name'] ?? '' ) ?: 'A customer' );
		$body    = trim( (string) ( $message['body'] ?? '' ) );
		$preview = get_site_option( 'captaincore_chat_push_preview', 'full' ) === 'name' ? 'New message' : ( $body !== '' ? Chat::first_line( $body, 140 ) : 'Sent a file' );
		$id      = (int) $conversation['id'];
		return self::send_to_operators( [
			'title' => $name,
			'body'  => $preview,
			'url'   => self::app_url( 'chat/' . $id ),
			'tag'   => 'chat-' . $id,
			'badge' => (int) ( Chat::counts()['waiting'] ?? 0 ),
		], ChatRelay::last_watching(), 'chat-' . $id );
	}

	/** A test notification to one operator's devices. */
	public static function test( $user_id ) {
		if ( ! Chat::is_operator( (int) $user_id ) ) {
			return new \WP_Error( 'operators_only', 'Notifications are for operators.', [ 'status' => 403 ] );
		}
		$sent = self::send_to_operators( [
			'title' => get_bloginfo( 'name' ),
			'body'  => 'Notifications are on for this device.',
			'url'   => self::app_url( 'chat' ),
			'tag'   => 'chat-test',
		], [], 'chat-test', (int) $user_id );
		return [ 'sent' => $sent ] + self::status( $user_id );
	}

	private static function app_url( $path ) {
		$config = Configurations::get();
		$base   = isset( $config->path ) ? trim( (string) $config->path, '/' ) : 'account';
		return home_url( '/' . ( $base !== '' ? $base . '/' : '' ) . $path );
	}

	/** Push $note to operators' devices, skipping the watching ones. Returns deliveries. */
	private static function send_to_operators( $note, $skip_users, $topic, $only_user = 0 ) {
		global $wpdb;
		$t    = $wpdb->base_prefix . 'captaincore_push_subscriptions';
		$rows = $only_user
			? $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $t WHERE user_id = %d", $only_user ) )
			: $wpdb->get_results( "SELECT * FROM $t" );
		$icon         = get_site_icon_url( 192 );
		$note['icon'] = $icon ?: '';
		$payload      = wp_json_encode( $note, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
		$operator     = [];
		$sent         = 0;
		foreach ( $rows ?: [] as $row ) {
			$uid = (int) $row->user_id;
			if ( in_array( $uid, (array) $skip_users, true ) ) {
				continue;
			}
			$operator[ $uid ] = $operator[ $uid ] ?? Chat::is_operator( $uid );
			if ( ! $operator[ $uid ] ) {
				continue;
			}
			$code = WebPush::send( $row, $payload, [ 'ttl' => DAY_IN_SECONDS, 'urgency' => 'high', 'topic' => $topic ] );
			if ( $code === 404 || $code === 410 ) {
				$wpdb->delete( $t, [ 'push_subscription_id' => (int) $row->push_subscription_id ] );
			} elseif ( $code >= 200 && $code < 300 ) {
				$sent++;
				$wpdb->update( $t, [ 'failures' => 0, 'last_success_at' => gmdate( 'Y-m-d H:i:s' ) ], [ 'push_subscription_id' => (int) $row->push_subscription_id ] );
			} elseif ( (int) $row->failures + 1 >= self::MAX_FAILURES ) {
				$wpdb->delete( $t, [ 'push_subscription_id' => (int) $row->push_subscription_id ] );
			} else {
				$wpdb->update( $t, [ 'failures' => (int) $row->failures + 1 ], [ 'push_subscription_id' => (int) $row->push_subscription_id ] );
				if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
					error_log( "CaptainCore Chat: push to subscription {$row->push_subscription_id} failed (HTTP $code)" );
				}
			}
		}
		return $sent;
	}
}
