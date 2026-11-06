<?php

namespace CaptainCore;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * CaptainCore Chat commands.
 */
class ChatCLI {

	/** Part types that carry a human reply when they have a body. */
	const REPLY_TYPES = [ 'comment', 'assignment', 'open', 'close', 'snoozed', 'unsnoozed', 'away_mode_assignment',
		'default_assignment', 'assign_and_reopen', 'assign_and_unsnooze', 'quick_reply', 'note_and_reopen' ];

	const HUMAN = [ 'user', 'lead', 'contact', 'admin' ];

	private $db;
	private $files_dir;
	private $dry_run;
	private $users      = [];
	private $operators  = [];
	private $contacts   = [];
	private $admins     = [];
	private $report;

	/**
	 * Import Intercom history (from `wp intercom sync`) into CaptainCore Chat.
	 *
	 * Reads intercom-cli's SQLite database directly (--db), or a bundle made
	 * by `export-intercom` (--bundle) for hosts without pdo_sqlite.
	 *
	 * Idempotent: conversations and messages are keyed on their Intercom IDs,
	 * so re-running only adds what is new. Conversations the sync has not
	 * captured in full yet (HTML + files) are skipped and reported.
	 *
	 * Contacts are matched to Manager users by email on the Manager running
	 * the import. A contact with no user becomes a guest conversation
	 * (operators only) and is claimed by a later run once a user with that
	 * email exists.
	 *
	 * ## OPTIONS
	 *
	 * [--db=<path>]
	 * : intercom-cli database. Default: <parent of ABSPATH>/private/intercom.db
	 *
	 * [--bundle=<path>]
	 * : A JSONL bundle from `wp captaincore chat export-intercom` (instead of --db).
	 *
	 * [--files=<dir>]
	 * : intercom-cli downloads. Default: <parent of ABSPATH>/private/intercom-files
	 *
	 * [--conversation=<id>]
	 * : Import one Intercom conversation.
	 *
	 * [--limit=<number>]
	 * : Stop after this many conversations.
	 *
	 * [--dry-run]
	 * : Map everything and print the report without writing.
	 *
	 * ## EXAMPLES
	 *
	 *     wp captaincore chat import-intercom --dry-run
	 *     wp captaincore chat import-intercom
	 *     wp captaincore chat import-intercom --bundle=~/private/intercom-bundle.jsonl --files=~/private/intercom-files
	 *
	 * @subcommand import-intercom
	 * @when after_wp_load
	 */
	public function import_intercom( $args, $assoc ) {
		$private         = dirname( untrailingslashit( ABSPATH ) ) . '/private';
		$this->files_dir = untrailingslashit( $assoc['files'] ?? $private . '/intercom-files' );
		$this->dry_run   = isset( $assoc['dry-run'] );
		$this->report    = [ 'years' => [], 'skipped_parts' => [], 'guests' => [], 'pending' => 0, 'files_missing' => 0 ];
		$only            = isset( $assoc['conversation'] ) ? (string) $assoc['conversation'] : null;
		$limit           = (int) ( $assoc['limit'] ?? 0 );

		if ( ! empty( $assoc['bundle'] ) ) {
			$records = $this->records_from_bundle( (string) $assoc['bundle'] );
			$total   = 0; // unknown up front; counted as we go
		} else {
			$this->open_sqlite( $assoc['db'] ?? $private . '/intercom.db' );
			$total   = (int) $this->db->query( 'SELECT COUNT(*) FROM conversations' )->fetchColumn();
			$records = $this->records_from_sqlite( $only );
		}

		$progress = $total ? \WP_CLI\Utils\make_progress_bar( ( $this->dry_run ? 'Mapping' : 'Importing' ) . " $total conversations", $only ? 1 : ( $limit ?: $total ) ) : null;
		$done     = 0;
		foreach ( $records as $record ) {
			if ( $only !== null && (string) $record['conversation']['id'] !== $only ) {
				continue;
			}
			$this->import_conversation( $record );
			$progress && $progress->tick();
			if ( $limit && ++$done >= $limit ) {
				break;
			}
		}
		$progress && $progress->finish();
		$this->print_report();
	}

	/**
	 * Write intercom-cli's database out as a portable JSONL bundle: a meta
	 * line (contacts, admins), then one line per conversation with its parts
	 * and downloaded files. Copy it with the files directory to a Manager
	 * without pdo_sqlite and import with --bundle.
	 *
	 * ## OPTIONS
	 *
	 * [--db=<path>]
	 * : intercom-cli database. Default: <parent of ABSPATH>/private/intercom.db
	 *
	 * [--out=<path>]
	 * : Bundle path. Default: <parent of ABSPATH>/private/intercom-bundle.jsonl
	 *
	 * @subcommand export-intercom
	 * @when after_wp_load
	 */
	public function export_intercom( $args, $assoc ) {
		$private = dirname( untrailingslashit( ABSPATH ) ) . '/private';
		$out     = $assoc['out'] ?? $private . '/intercom-bundle.jsonl';
		$this->open_sqlite( $assoc['db'] ?? $private . '/intercom.db' );
		$fh = fopen( $out, 'wb' );
		if ( ! $fh ) {
			\WP_CLI::error( "Cannot write $out" );
		}
		chmod( $out, 0600 );
		fwrite( $fh, wp_json_encode( [ 'type' => 'meta', 'version' => 1, 'contacts' => $this->contacts, 'admins' => $this->admins ] ) . "\n" );
		$n = 0;
		$pending = 0;
		foreach ( $this->records_from_sqlite( null ) as $record ) {
			if ( (int) $record['conversation']['detail_version'] < 2 ) {
				$pending++;
			}
			fwrite( $fh, wp_json_encode( [ 'type' => 'conversation' ] + $record ) . "\n" );
			$n++;
		}
		fclose( $fh );
		\WP_CLI::success( "Wrote $n conversations to $out" . ( $pending ? " ($pending not fully synced yet)" : '' ) . '. Copy it with the files directory.' );
	}

	private function open_sqlite( $db_path ) {
		if ( ! class_exists( '\PDO' ) || ! in_array( 'sqlite', \PDO::getAvailableDrivers(), true ) ) {
			\WP_CLI::error( 'pdo_sqlite is not available here. Run `wp captaincore chat export-intercom` where it is, then import with --bundle.' );
		}
		if ( ! is_file( $db_path ) ) {
			\WP_CLI::error( "No Intercom database at $db_path" );
		}
		// The sync may be writing at the same time: wait for its lock instead of failing.
		$this->db = new \PDO( 'sqlite:' . $db_path, null, null, [ \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION, \PDO::ATTR_TIMEOUT => 30 ] );
		$cols     = $this->db->query( 'PRAGMA table_info(conversations)' )->fetchAll( \PDO::FETCH_COLUMN, 1 );
		if ( ! in_array( 'detail_version', $cols, true ) ) {
			\WP_CLI::error( 'This intercom.db predates HTML + attachment capture. Run `wp intercom sync --full` with the current intercom-cli first.' );
		}
		foreach ( $this->db->query( 'SELECT * FROM contacts', \PDO::FETCH_ASSOC ) as $c ) {
			$this->contacts[ $c['id'] ] = $c;
		}
		foreach ( $this->db->query( 'SELECT * FROM admins', \PDO::FETCH_ASSOC ) as $a ) {
			$this->admins[ $a['id'] ] = $a;
		}
	}

	/** One record per conversation: the row, its parts, its downloaded files. */
	private function records_from_sqlite( $only ) {
		$sql = 'SELECT id, title, state, contact_ids, source_type, source_author_type, source_author_id, source_body,
			snoozed_until, created_at, updated_at, detail_version FROM conversations';
		$sql .= $only !== null ? ' WHERE id = ?' : '';
		$stmt = $this->db->prepare( $sql . ' ORDER BY created_at ASC' );
		$stmt->execute( $only !== null ? [ $only ] : [] );
		$parts = $this->db->prepare( 'SELECT id, part_type, body, author_type, author_id, author_name, author_email, created_at
			FROM conversation_parts WHERE conversation_id = ? ORDER BY created_at ASC, CAST(id AS INTEGER) ASC' );
		$files = $this->db->prepare( "SELECT part_id, kind, position, name, content_type, sha256, local_path
			FROM attachments WHERE conversation_id = ? AND status = 'ok' ORDER BY part_id, kind, position" );
		while ( $row = $stmt->fetch( \PDO::FETCH_ASSOC ) ) {
			$parts->execute( [ $row['id'] ] );
			$files->execute( [ $row['id'] ] );
			yield [ 'conversation' => $row, 'parts' => $parts->fetchAll( \PDO::FETCH_ASSOC ), 'attachments' => $files->fetchAll( \PDO::FETCH_ASSOC ) ];
		}
	}

	private function records_from_bundle( $path ) {
		$fh = is_file( $path ) ? fopen( $path, 'rb' ) : false;
		if ( ! $fh ) {
			\WP_CLI::error( "Cannot read bundle $path" );
		}
		$meta = json_decode( (string) fgets( $fh ), true );
		if ( ( $meta['type'] ?? '' ) !== 'meta' || (int) ( $meta['version'] ?? 0 ) !== 1 ) {
			\WP_CLI::error( 'Not a CaptainCore Chat Intercom bundle (version 1).' );
		}
		$this->contacts = (array) $meta['contacts'];
		$this->admins   = (array) $meta['admins'];
		while ( ( $line = fgets( $fh ) ) !== false ) {
			$record = json_decode( $line, true );
			if ( ( $record['type'] ?? '' ) === 'conversation' ) {
				yield $record;
			}
		}
		fclose( $fh );
	}

	private function import_conversation( array $record ) {
		global $wpdb;
		$ic   = $record['conversation'];
		$year = $ic['created_at'] ? gmdate( 'Y', (int) $ic['created_at'] ) : '?';
		$y    = &$this->report['years'][ $year ];
		$y    = ( $y ?? [] ) + [ 'intercom' => 0, 'pending' => 0, 'new' => 0, 'updated' => 0, 'messages' => 0, 'notes' => 0, 'events' => 0, 'files' => 0, 'guest' => 0 ];
		$y['intercom']++;
		if ( (int) $ic['detail_version'] < 2 ) {
			$y['pending']++;
			$this->report['pending']++;
			return;
		}

		// The customer: who opened it, else the first contact on it.
		$contact_id = in_array( $ic['source_author_type'], [ 'user', 'lead', 'contact' ], true )
			? $ic['source_author_id']
			: ( json_decode( (string) $ic['contact_ids'], true )[0] ?? null );
		$contact = $contact_id ? ( $this->contacts[ $contact_id ] ?? [] ) : [];
		$email   = strtolower( trim( (string) ( $contact['email'] ?? '' ) ) );
		$name    = trim( (string) ( $contact['name'] ?? '' ) ) ?: ( $email ?: 'Unknown contact' );
		$user_id = $this->user_for_email( $email );
		if ( ! $user_id ) {
			$y['guest']++;
			$this->report['guests'][ $email ?: '(no email)' ] = true;
		}
		$account_id = 0;
		if ( $user_id ) {
			$accounts   = ( new User( $user_id, true ) )->accounts();
			$account_id = count( $accounts ) === 1 ? (int) $accounts[0] : 0;
		}

		$messages = $this->map_messages( $record, $contact_id );
		$times    = $this->conversation_times( $messages, $ic );
		$subject  = Chat::clean_line( (string) $ic['title'], Chat::MAX_SUBJECT );
		if ( is_wp_error( $subject ) ) {
			$first   = $messages[0]['body'] ?? '';
			$subject = $first !== '' ? Chat::first_line( Chat::strip_controls( $first ), 80 ) : 'Conversation';
			$subject = is_wp_error( Chat::clean_line( $subject, Chat::MAX_SUBJECT ) ) ? 'Conversation' : Chat::clean_line( $subject, Chat::MAX_SUBJECT );
		}
		$status = in_array( $ic['state'], Chat::STATUSES, true ) ? $ic['state'] : 'closed';
		$fields = [
			'user_id'          => $user_id,
			'account_id'       => $account_id,
			'contact_name'     => mb_substr( $name, 0, 255 ),
			'contact_email'    => mb_substr( $email, 0, 255 ),
			'subject'          => $subject,
			'status'           => $status,
			'snoozed_until'    => $status === 'snoozed' && $ic['snoozed_until'] ? gmdate( 'Y-m-d H:i:s', (int) $ic['snoozed_until'] ) : null,
			'waiting_since'    => $status === 'open' ? $times['waiting_since'] : null,
			'last_customer_at' => $times['last_customer_at'],
			'last_operator_at' => $times['last_operator_at'],
			'last_message_at'  => $times['last_message_at'],
			'source'           => 'intercom',
			'external_id'      => (string) $ic['id'],
			'created_at'       => gmdate( 'Y-m-d H:i:s', (int) $ic['created_at'] ),
			'updated_at'       => gmdate( 'Y-m-d H:i:s', (int) ( $ic['updated_at'] ?: $ic['created_at'] ) ),
		];

		$c        = $wpdb->base_prefix . 'captaincore_chat_conversations';
		$existing = $wpdb->get_row( $wpdb->prepare( "SELECT chat_conversation_id, user_id, site_id FROM $c WHERE source = 'intercom' AND external_id = %s", (string) $ic['id'] ) );
		if ( $this->dry_run ) {
			$existing ? $y['updated']++ : $y['new']++;
			foreach ( $messages as $m ) {
				$y[ $m['kind'] === 'note' ? 'notes' : ( $m['kind'] === 'event' ? 'events' : 'messages' ) ]++;
				$y['files'] += count( $m['files'] );
			}
			return;
		}

		if ( $existing ) {
			// Keep a user link someone made by hand; claim a guest thread
			// whose email now has a user.
			if ( (int) $existing->user_id > 0 && ! $user_id ) {
				unset( $fields['user_id'], $fields['account_id'] );
			}
			ChatConversations::update( $fields, [ 'chat_conversation_id' => (int) $existing->chat_conversation_id ] );
			$conversation_id = (int) $existing->chat_conversation_id;
			$y['updated']++;
		} else {
			$conversation_id = ChatConversations::insert( $fields );
			$y['new']++;
		}

		$m    = $wpdb->base_prefix . 'captaincore_chat_messages';
		$have = array_flip( $wpdb->get_col( $wpdb->prepare( "SELECT external_id FROM $m WHERE chat_conversation_id = %d", $conversation_id ) ) );
		foreach ( $messages as $msg ) {
			if ( isset( $have[ $msg['external_id'] ] ) ) {
				continue;
			}
			$message_id = ChatMessages::insert( [
				'chat_conversation_id' => $conversation_id,
				'kind'                 => $msg['kind'],
				'internal'             => $msg['internal'],
				'author_kind'          => $msg['author_kind'],
				'author_user_id'       => $msg['author_user_id'],
				'author_name'          => mb_substr( $msg['author_name'], 0, 255 ),
				'body'                 => $msg['body'],
				'external_id'          => $msg['external_id'],
				'created_at'           => $msg['created_at'],
			] );
			$y[ $msg['kind'] === 'note' ? 'notes' : ( $msg['kind'] === 'event' ? 'events' : 'messages' ) ]++;
			foreach ( $msg['files'] as $file ) {
				if ( $this->store_file( $conversation_id, $message_id, $file ) ) {
					$y['files']++;
				}
			}
		}
	}

	/**
	 * Turn the opening message and every part into Chat messages, oldest
	 * first. Bot chatter and bodiless bookkeeping parts are dropped (counted
	 * in the report); a human part with only files keeps its files.
	 */
	private function map_messages( array $record, $contact_id ) {
		$ic    = $record['conversation'];
		$files = [];
		foreach ( $record['attachments'] as $a ) {
			// The same screenshot often arrives as an attachment and inline.
			$files[ $a['part_id'] ][ $a['sha256'] ] = $a;
		}

		$out    = [];
		$source = [
			'id'          => 'source-' . $ic['id'],
			'part_type'   => 'comment',
			'body'        => $ic['source_body'],
			'author_type' => $ic['source_author_type'],
			'author_id'   => $ic['source_author_id'],
			'author_name' => null,
			'author_email'=> null,
			'created_at'  => $ic['created_at'],
		];
		$parts = array_merge( [ $source ], $record['parts'] );

		foreach ( $parts as $p ) {
			$type   = (string) $p['part_type'];
			$author = (string) $p['author_type'];
			$body   = trim( Chat::strip_controls( (string) $p['body'] ) );
			$body   = mb_strlen( $body ) > 60000 ? mb_substr( $body, 0, 60000 ) . "\n\n[trimmed on import]" : $body;
			$pfiles = array_values( $files[ $p['id'] ] ?? [] );
			$human  = in_array( $author, self::HUMAN, true );
			$is_op  = $author === 'admin';
			$name   = $this->author_name( $p, $is_op, $contact_id );
			$when   = gmdate( 'Y-m-d H:i:s', (int) ( $p['created_at'] ?: $ic['created_at'] ) );
			$base   = [ 'external_id' => (string) $p['id'], 'created_at' => $when, 'author_name' => $name, 'files' => $pfiles,
				'author_kind' => $is_op ? 'operator' : 'customer', 'author_user_id' => $is_op ? $this->operator_user( $p['author_id'] ) : $this->user_for_email( $this->contact_email( $p['author_id'] ) ) ];

			if ( ! $human ) {
				$this->skip( $author === 'bot' ? 'bot' : "author:$author" );
				continue;
			}
			if ( ( $type === 'note' || $type === 'note_and_reopen' ) && $is_op ) {
				if ( $body === '' && ! $pfiles ) {
					$this->skip( 'empty note' );
					continue;
				}
				$out[] = $base + [ 'kind' => 'note', 'internal' => 1, 'body' => $body ];
				continue;
			}
			if ( in_array( $type, self::REPLY_TYPES, true ) && ( $body !== '' || $pfiles ) ) {
				$out[] = $base + [ 'kind' => 'comment', 'internal' => 0, 'body' => $body ];
				continue;
			}
			if ( ( $type === 'close' || $type === 'open' ) && $is_op ) {
				$out[] = array_merge( $base, [ 'kind' => 'event', 'internal' => 0, 'files' => [],
					'body' => ( $type === 'close' ? 'Closed by ' : 'Reopened by ' ) . $name ] );
				continue;
			}
			if ( $pfiles ) {
				$out[] = $base + [ 'kind' => 'comment', 'internal' => 0, 'body' => '' ];
				continue;
			}
			$this->skip( $type );
		}
		return $out;
	}

	private function conversation_times( array $messages, array $ic ) {
		$last = [ 'customer' => null, 'operator' => null ];
		$waiting = null;
		foreach ( $messages as $m ) {
			if ( $m['kind'] !== 'comment' ) {
				continue;
			}
			$last[ $m['author_kind'] ] = $m['created_at'];
			if ( $m['author_kind'] === 'customer' ) {
				$waiting = $waiting ?? $m['created_at'];
			} else {
				$waiting = null;
			}
		}
		$latest = max( array_filter( [ $last['customer'], $last['operator'] ] ) ?: [ gmdate( 'Y-m-d H:i:s', (int) $ic['created_at'] ) ] );
		return [ 'last_customer_at' => $last['customer'], 'last_operator_at' => $last['operator'], 'last_message_at' => $latest, 'waiting_since' => $waiting ];
	}

	private function author_name( array $p, $is_op, $contact_id ) {
		if ( ! empty( $p['author_name'] ) ) {
			return (string) $p['author_name'];
		}
		if ( $is_op ) {
			return (string) ( $this->admins[ $p['author_id'] ]['name'] ?? 'Operator' );
		}
		$c = $this->contacts[ $p['author_id'] ?: $contact_id ] ?? [];
		return (string) ( $c['name'] ?: ( $c['email'] ?? 'Customer' ) );
	}

	private function contact_email( $contact_id ) {
		return strtolower( trim( (string) ( $this->contacts[ $contact_id ]['email'] ?? '' ) ) );
	}

	/** Manager user ID for a customer email (0 = none). Cached. */
	private function user_for_email( $email ) {
		if ( $email === '' || ! is_email( $email ) ) {
			return 0;
		}
		if ( ! array_key_exists( $email, $this->users ) ) {
			$user                 = get_user_by( 'email', $email );
			$this->users[ $email ] = $user ? (int) $user->ID : 0;
		}
		return $this->users[ $email ];
	}

	/** Manager user ID for an Intercom admin, only if that user is a Manager administrator. */
	private function operator_user( $admin_id ) {
		if ( ! array_key_exists( (string) $admin_id, $this->operators ) ) {
			$email = strtolower( (string) ( $this->admins[ $admin_id ]['email'] ?? '' ) );
			$user  = $email ? get_user_by( 'email', $email ) : null;
			$this->operators[ (string) $admin_id ] = $user && Chat::is_operator( (int) $user->ID ) ? (int) $user->ID : 0;
		}
		return $this->operators[ (string) $admin_id ];
	}

	/** Copy one downloaded file into chat storage (named by sha256) and record it. */
	private function store_file( $conversation_id, $message_id, array $a ) {
		$src = $this->files_dir . '/' . ltrim( (string) $a['local_path'], '/' );
		if ( strpos( (string) $a['local_path'], '..' ) !== false || ! is_file( $src ) || ! preg_match( '/^[a-f0-9]{64}$/', (string) $a['sha256'] ) ) {
			$this->report['files_missing']++;
			return false;
		}
		$name = (string) ( $a['name'] ?: preg_replace( '/^[a-f0-9]{12}-/', '', basename( $a['local_path'] ) ) );
		$ext  = strtolower( (string) pathinfo( $a['local_path'], PATHINFO_EXTENSION ) );
		$ext  = preg_match( '/^[a-z0-9]{1,5}$/', $ext ) ? '.' . $ext : '';
		$rel  = $conversation_id . '/' . $a['sha256'] . $ext;
		$dest = Chat::base_dir() . '/' . $rel;
		if ( ! is_dir( dirname( $dest ) ) && ! wp_mkdir_p( dirname( $dest ) ) ) {
			\WP_CLI::warning( 'Cannot create ' . dirname( $dest ) );
			return false;
		}
		if ( ! is_file( $dest ) ) {
			copy( $src, $dest );
			chmod( $dest, 0600 );
		}
		$mime = (string) $a['content_type'];
		if ( ( $mime === '' || $mime === 'application/force-download' ) && function_exists( 'finfo_open' ) ) {
			$mime = (string) finfo_file( finfo_open( FILEINFO_MIME_TYPE ), $dest );
		}
		ChatAttachments::insert( [
			'chat_message_id'      => (int) $message_id,
			'chat_conversation_id' => (int) $conversation_id,
			'name'                 => mb_substr( sanitize_file_name( $name ) ?: 'file', 0, 255 ),
			'mime'                 => mb_substr( $mime, 0, 100 ),
			'size'                 => (int) filesize( $dest ),
			'sha256'               => $a['sha256'],
			'storage_path'         => $rel,
			'created_at'           => gmdate( 'Y-m-d H:i:s' ),
		] );
		return true;
	}

	private function skip( $why ) {
		$this->report['skipped_parts'][ $why ] = ( $this->report['skipped_parts'][ $why ] ?? 0 ) + 1;
	}

	private function print_report() {
		$rows = [];
		$tot  = [];
		ksort( $this->report['years'] );
		foreach ( $this->report['years'] as $year => $y ) {
			$rows[] = [ 'year' => $year ] + $y;
			foreach ( $y as $k => $v ) {
				$tot[ $k ] = ( $tot[ $k ] ?? 0 ) + $v;
			}
		}
		$rows[] = [ 'year' => 'total' ] + $tot;
		\WP_CLI::log( '' );
		\WP_CLI\Utils\format_items( 'table', $rows, [ 'year', 'intercom', 'pending', 'new', 'updated', 'messages', 'notes', 'events', 'files', 'guest' ] );
		arsort( $this->report['skipped_parts'] );
		\WP_CLI::log( 'Skipped parts: ' . ( $this->report['skipped_parts'] ? implode( ', ', array_map( fn( $k, $v ) => "$k $v", array_keys( $this->report['skipped_parts'] ), $this->report['skipped_parts'] ) ) : 'none' ) );
		\WP_CLI::log( 'Guest contacts (no Manager user): ' . count( $this->report['guests'] ) );
		if ( $this->report['files_missing'] ) {
			\WP_CLI::warning( $this->report['files_missing'] . ' files were listed as downloaded but are missing on disk.' );
		}
		if ( $this->report['pending'] ) {
			\WP_CLI::log( $this->report['pending'] . ' conversations are not fully synced yet (run `wp intercom sync --full`), then import again.' );
		}
		\WP_CLI::success( $this->dry_run ? 'Dry run: nothing written.' : 'Import finished.' );
	}

	/**
	 * Show CaptainCore Chat totals.
	 *
	 * @when after_wp_load
	 */
	public function stats( $args, $assoc ) {
		global $wpdb;
		$c    = $wpdb->base_prefix . 'captaincore_chat_conversations';
		$m    = $wpdb->base_prefix . 'captaincore_chat_messages';
		$a    = $wpdb->base_prefix . 'captaincore_chat_attachments';
		$rows = $wpdb->get_results( "SELECT source, status, COUNT(*) conversations, SUM(user_id = 0) guests FROM $c GROUP BY source, status ORDER BY source, status", ARRAY_A );
		\WP_CLI\Utils\format_items( 'table', $rows ?: [], [ 'source', 'status', 'conversations', 'guests' ] );
		\WP_CLI::log( 'Messages: ' . (int) $wpdb->get_var( "SELECT COUNT(*) FROM $m" ) . ' · Attachments: ' . (int) $wpdb->get_var( "SELECT COUNT(*) FROM $a" )
			. ' (' . size_format( (int) $wpdb->get_var( "SELECT COALESCE(SUM(size),0) FROM $a" ) ) . ') · Mode: ' . Chat::mode() );
	}
}
