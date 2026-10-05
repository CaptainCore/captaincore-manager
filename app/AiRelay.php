<?php

namespace CaptainCore;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * AI Relay intake: email-verified signup, staged file uploads and the
 * submission that turns them into a site request.
 *
 * The public page lives in the front-end theme (layout `ai-relay`). This class
 * owns everything that touches accounts, cards and files:
 *
 *   signup_start()  public   email in, single-use link out (same reply either way)
 *   signup_finish() public   link token + password in, new user signed in
 *   stage_file()    user     one file per request, held under the private dir
 *   submit()        user     billing + card on file + staged files → site request
 *
 * A card on file is required to submit. It is saved through the same Stripe
 * Sources path as the Billing screen (User::add_payment_method), so renewals
 * charge it without any AI Relay specific billing code. Nothing is charged
 * here. Launching the site is a plan change made by staff.
 *
 * Files are written outside the web root: CAPTAINCORE_AI_RELAY_DIR when
 * defined, else <parent of ABSPATH>/private/ai-relay.
 */
class AiRelay {

	const SIGNUP_TTL      = 3600;
	const STAGED_META     = '_captaincore_ai_relay_staged';
	const MAX_FILES       = 40;
	const MAX_TOTAL_BYTES = 250 * 1024 * 1024;
	const EXTENSIONS      = [ 'jpg', 'jpeg', 'png', 'gif', 'webp', 'heic', 'heif', 'svg', 'pdf', 'doc', 'docx', 'txt', 'md', 'rtf', 'csv', 'xls', 'xlsx', 'ppt', 'pptx', 'zip', 'mp4', 'm4v', 'mov', 'webm' ];
	// Videos play in the thread as well as download. Nothing else is ever
	// served with its own type.
	const VIDEO_TYPES     = [ 'mp4' => 'video/mp4', 'm4v' => 'video/mp4', 'mov' => 'video/quicktime', 'webm' => 'video/webm' ];

	/**
	 * Limits the page enforces client side. The per-file cap is whatever PHP
	 * will accept in one request, since files upload one at a time.
	 */
	public static function limits() {
		$per_file = min( wp_max_upload_size(), self::MAX_TOTAL_BYTES );
		return [
			'files'      => self::MAX_FILES,
			'bytes'      => self::MAX_TOTAL_BYTES,
			'file_bytes' => (int) $per_file,
			'extensions' => self::EXTENSIONS,
		];
	}

	/**
	 * Public config for the page: limits plus the keys a browser may see.
	 */
	public static function public_config() {
		return [
			'limits'       => self::limits(),
			'stripeKey'    => class_exists( '\WC_Gateway_Stripe' ) ? ( new \WC_Gateway_Stripe )->publishable_key : '',
			'turnstileKey' => defined( 'CAPTAINCORE_TURNSTILE_SITE_KEY' ) ? CAPTAINCORE_TURNSTILE_SITE_KEY : '',
		];
	}

	/* ---------------------------------------------------------------------
	 *  Signup
	 * ------------------------------------------------------------------- */

	/**
	 * Start a signup. Always answers the same way so the form cannot be used
	 * to learn which emails have accounts. A known email gets a sign-in
	 * reminder instead of a signup link.
	 */
	public static function signup_start( $email, $name, $turnstile_token ) {
		$reply = [ 'message' => 'Check your email for a link to finish creating your account.' ];
		$email = sanitize_email( (string) $email );
		$name  = sanitize_text_field( (string) $name );

		if ( ! is_email( $email ) ) {
			return new \WP_Error( 'invalid_email', 'Enter a valid email address.', [ 'status' => 400 ] );
		}

		if ( ! self::turnstile_ok( $turnstile_token ) ) {
			return new \WP_Error( 'turnstile_failed', 'Verification failed. Refresh the page and try again.', [ 'status' => 400 ] );
		}

		// Its own throttle scope, so signup mail cannot lock anyone out of sign-in.
		$ip   = GeoIP::client_ip();
		$keys = [ 'cc_relay_ip_' . md5( (string) $ip ), 'cc_relay_email_' . md5( strtolower( $email ) ) ];
		if ( captaincore_login_is_throttled( $keys, 5 ) ) {
			return $reply;
		}
		captaincore_login_record_failure( $keys, HOUR_IN_SECONDS );

		if ( email_exists( $email ) ) {
			Mailer::send_ai_relay_existing_account( $email, wp_login_url( self::page_path() ) );
			return $reply;
		}

		// Only the hash is stored, so a leaked options table holds no live links.
		$token = bin2hex( random_bytes( 32 ) );
		set_site_transient( self::signup_key( $token ), [
			'email'      => $email,
			'name'       => $name,
			'created_at' => time(),
		], self::SIGNUP_TTL );

		$url = add_query_arg( 'relay_token', $token, home_url( self::page_path() ) );
		Mailer::send_ai_relay_signup( $email, $url );

		return $reply;
	}

	/**
	 * Look up a pending signup without consuming it (the page shows the email).
	 */
	public static function signup_pending( $token ) {
		if ( ! preg_match( '/^[a-f0-9]{64}$/', (string) $token ) ) {
			return false;
		}
		$record = get_site_transient( self::signup_key( $token ) );
		return is_array( $record ) ? $record : false;
	}

	/**
	 * Finish a signup: consume the token, create the user, sign them in.
	 */
	public static function signup_finish( $token, $password ) {
		$record = self::signup_pending( $token );
		if ( ! $record ) {
			return new \WP_Error( 'expired', 'That link has expired or was already used. Start again to get a new one.', [ 'status' => 400 ] );
		}

		$password = (string) $password;
		if ( strlen( $password ) < 10 || ! preg_match( '/[a-zA-Z]/', $password ) || ! preg_match( '/[0-9]/', $password ) ) {
			return new \WP_Error( 'weak_password', 'Use at least 10 characters with a letter and a number.', [ 'status' => 400 ] );
		}

		// Single use: delete before creating so a double submit cannot make two users.
		delete_site_transient( self::signup_key( $token ) );

		if ( email_exists( $record['email'] ) ) {
			return new \WP_Error( 'exists', 'An account with this email already exists. Sign in instead.', [ 'status' => 400 ] );
		}

		$parts   = preg_split( '/\s+/', trim( (string) $record['name'] ), 2 );
		$user_id = wp_insert_user( [
			'user_login'   => $record['email'],
			'user_email'   => $record['email'],
			'user_pass'    => $password,
			'first_name'   => $parts[0] ?? '',
			'last_name'    => $parts[1] ?? '',
			'display_name' => $record['name'] ? $record['name'] : $record['email'],
		] );

		if ( is_wp_error( $user_id ) ) {
			return new \WP_Error( 'create_failed', 'The account could not be created.', [ 'status' => 400 ] );
		}

		update_user_meta( $user_id, 'captaincore_signup_source', 'ai-relay' );

		wp_set_current_user( $user_id );
		wp_set_auth_cookie( $user_id, true, is_ssl() );

		return [ 'success' => true ];
	}

	/* ---------------------------------------------------------------------
	 *  Staged files
	 * ------------------------------------------------------------------- */

	public static function staged( $user_id ) {
		$staged = get_user_meta( $user_id, self::STAGED_META, true );
		return is_array( $staged ) ? $staged : [];
	}

	/**
	 * Accept one uploaded file ($_FILES entry) into the user's staging area.
	 */
	public static function stage_file( $user_id, $file ) {
		if ( empty( $file ) || ! isset( $file['error'] ) || (int) $file['error'] !== UPLOAD_ERR_OK || ! is_uploaded_file( $file['tmp_name'] ) ) {
			return new \WP_Error( 'upload_failed', 'The file did not upload. It may be larger than the server allows.', [ 'status' => 400 ] );
		}

		$name = sanitize_file_name( wp_basename( (string) $file['name'] ) );
		$ext  = strtolower( pathinfo( $name, PATHINFO_EXTENSION ) );
		if ( ! in_array( $ext, self::EXTENSIONS, true ) ) {
			return new \WP_Error( 'file_type', 'That file type is not accepted.', [ 'status' => 400 ] );
		}

		$size   = (int) filesize( $file['tmp_name'] );
		$staged = self::staged( $user_id );
		$total  = array_sum( array_column( $staged, 'size' ) );
		if ( count( $staged ) >= self::MAX_FILES ) {
			return new \WP_Error( 'too_many', 'That is more files than a single build can take.', [ 'status' => 400 ] );
		}
		if ( $total + $size > self::MAX_TOTAL_BYTES ) {
			return new \WP_Error( 'too_big', 'Uploads are capped at 250 MB in total.', [ 'status' => 400 ] );
		}

		$dir = self::dir( 'staging/' . (int) $user_id );
		if ( is_wp_error( $dir ) ) {
			return $dir;
		}

		// Stored under a random name with the checked extension, never the
		// visitor's filename, so nothing they send decides a path.
		$id   = bin2hex( random_bytes( 8 ) );
		$path = $dir . '/' . $id . '.' . $ext;
		if ( ! move_uploaded_file( $file['tmp_name'], $path ) ) {
			return new \WP_Error( 'move_failed', 'The file could not be saved.', [ 'status' => 500 ] );
		}
		chmod( $path, 0640 );

		$staged[ $id ] = [
			'id'         => $id,
			'name'       => $name,
			'size'       => $size,
			'ext'        => $ext,
			'created_at' => time(),
		];
		update_user_meta( $user_id, self::STAGED_META, $staged );

		return self::public_file( $staged[ $id ] );
	}

	public static function unstage_file( $user_id, $id ) {
		$staged = self::staged( $user_id );
		if ( ! isset( $staged[ $id ] ) ) {
			return new \WP_Error( 'not_found', 'File not found.', [ 'status' => 404 ] );
		}
		$path = self::dir_path( 'staging/' . (int) $user_id ) . '/' . $id . '.' . $staged[ $id ]['ext'];
		if ( is_file( $path ) ) {
			unlink( $path );
		}
		unset( $staged[ $id ] );
		update_user_meta( $user_id, self::STAGED_META, $staged );
		return [ 'success' => true ];
	}

	/* ---------------------------------------------------------------------
	 *  Projects
	 * ------------------------------------------------------------------- */

	/**
	 * Turn the staged files, notes and card into an AI Relay project whose
	 * first message is what the customer sent.
	 *
	 * @param int   $user_id
	 * @param array $input mode, url, notes, site_name, billing (array), source_id
	 */
	public static function submit( $user_id, $input ) {
		$user   = new User( $user_id, true );
		$mode   = ( $input['mode'] ?? '' ) === 'port' ? 'port' : 'new';
		$url    = $mode === 'port' ? esc_url_raw( trim( (string) ( $input['url'] ?? '' ) ) ) : '';
		$notes  = sanitize_textarea_field( (string) ( $input['notes'] ?? '' ) );
		$name   = sanitize_text_field( (string) ( $input['site_name'] ?? '' ) );
		$staged = self::staged( $user_id );

		if ( $mode === 'port' && ! self::valid_url( $url ) ) {
			return new \WP_Error( 'invalid_url', 'Enter the full address of your current site.', [ 'status' => 400 ] );
		}
		if ( ! $staged && $notes === '' && $url === '' ) {
			return new \WP_Error( 'empty', 'Add at least one file, a note, or the address of your current site.', [ 'status' => 400 ] );
		}
		if ( self::open_project( $user_id ) ) {
			return new \WP_Error( 'open_project', 'You already have an AI Relay build in progress. Add anything new to it from your account.', [ 'status' => 400 ] );
		}

		// Billing address first: Stripe card saves require it (see
		// captaincore_billing_address_missing). Only fields sent are changed.
		if ( ! empty( $input['billing'] ) && is_array( $input['billing'] ) ) {
			self::save_billing( $user_id, $input['billing'] );
		}

		// Card on file: a new Stripe source from the page, or one already saved.
		$source_id = (string) ( $input['source_id'] ?? '' );
		if ( $source_id !== '' ) {
			if ( ! preg_match( '/^src_[A-Za-z0-9]+$/', $source_id ) ) {
				return new \WP_Error( 'invalid_card', 'The card could not be read. Try entering it again.', [ 'status' => 400 ] );
			}
			$missing = captaincore_billing_address_missing( $user_id );
			if ( $missing ) {
				return new \WP_Error( 'billing_missing', 'Billing details are required before a card can be added: ' . implode( ', ', $missing ) . '.', [ 'status' => 400 ] );
			}
			$added = $user->add_payment_method( $source_id );
			if ( is_object( $added ) && ! empty( $added->error ) ) {
				return new \WP_Error( 'card_failed', (string) $added->error, [ 'status' => 400 ] );
			}
		}

		if ( ! self::has_card( $user_id ) ) {
			return new \WP_Error( 'card_required', 'A card on file is required to submit.', [ 'status' => 400 ] );
		}

		$label      = $name ? $name : ( $url ? (string) wp_parse_url( $url, PHP_URL_HOST ) : 'AI Relay site' );
		$account_id = self::account_for( $user, $label );
		$now        = current_time( 'mysql' );
		$project_id = AiRelayProjects::insert( [
			'account_id'      => $account_id,
			'user_id'         => $user_id,
			'name'            => $label,
			'mode'            => $mode,
			'source_url'      => $url,
			'status'          => 'building',
			'storage_key'     => gmdate( 'Ymd-His' ) . '-' . bin2hex( random_bytes( 6 ) ),
			'last_message_at' => $now,
			'created_at'      => $now,
			'updated_at'      => $now,
		] );

		$intro = $mode === 'port' ? "Port an existing site: {$url}" : 'Start something new.';
		$body  = $notes !== '' ? $intro . "\n\n" . $notes : $intro;
		$sent  = self::add_message( $project_id, $user_id, 'customer', $body, array_keys( $staged ), false );
		if ( is_wp_error( $sent ) ) {
			return $sent;
		}

		$project = AiRelayProjects::get( $project_id );
		Mailer::send_ai_relay_staff_notice( $project, $sent, true );

		return [ 'success' => true, 'project' => self::project_for_view( $project, $user_id ) ];
	}

	/**
	 * Projects the user can see. Staff see every project.
	 */
	public static function projects_for( $user_id ) {
		$user = new User( $user_id, true );
		$rows = [];
		if ( $user->is_admin() ) {
			global $wpdb;
			$table = $wpdb->prefix . 'captaincore_ai_relay_projects';
			$rows  = $wpdb->get_results( "SELECT * FROM $table ORDER BY last_message_at DESC" ); // phpcs:ignore WordPress.DB.PreparedSQL
		} else {
			$seen = [];
			foreach ( $user->accounts() as $account_id ) {
				foreach ( AiRelayProjects::where( [ 'account_id' => $account_id ] ) as $row ) {
					$seen[ $row->ai_relay_project_id ] = $row;
				}
			}
			$rows = array_values( $seen );
		}
		return array_map( function ( $row ) use ( $user_id ) {
			return self::project_for_view( $row, $user_id );
		}, $rows );
	}

	/**
	 * One project with its thread, or a 404 shaped error when the user may
	 * not see it (never a 403, so ids cannot be probed).
	 */
	public static function project( $user_id, $project_id ) {
		$project = self::viewable( $user_id, $project_id );
		if ( ! $project ) {
			return new \WP_Error( 'not_found', 'Project not found.', [ 'status' => 404 ] );
		}
		$view     = self::project_for_view( $project, $user_id );
		$is_admin = ( new User( $user_id, true ) )->is_admin();
		$thread   = array_filter( AiRelayMessages::thread( $project->ai_relay_project_id ), function ( $m ) use ( $is_admin ) {
			return $is_admin || $m->author !== 'internal';
		} );
		$view['messages'] = array_values( array_map( function ( $m ) {
			$author = get_userdata( $m->user_id );
			return [
				'id'         => (int) $m->ai_relay_message_id,
				'author'     => $m->author,
				'name'       => $m->author === 'staff' ? get_bloginfo( 'name' ) : ( $author ? $author->display_name : '' ),
				'internal'   => $m->author === 'internal',
				'body'       => (string) $m->body,
				'files'      => array_map( [ __CLASS__, 'public_file' ], (array) json_decode( (string) $m->files, true ) ),
				'created_at' => $m->created_at,
			];
		}, $thread ) );
		return $view;
	}

	/**
	 * Post to a project's thread. Staff posts email the customer; customer
	 * posts email staff. $internal (staff only) saves a note the customer
	 * never sees and nobody is emailed about.
	 */
	public static function post_message( $user_id, $project_id, $body, $file_ids, $internal = false ) {
		$project = self::viewable( $user_id, $project_id );
		if ( ! $project ) {
			return new \WP_Error( 'not_found', 'Project not found.', [ 'status' => 404 ] );
		}
		if ( $project->status === 'launched' ) {
			return new \WP_Error( 'launched', 'This site has launched. Reach us through support from here on.', [ 'status' => 400 ] );
		}
		$is_admin = ( new User( $user_id, true ) )->is_admin();
		$author   = $is_admin ? ( $internal ? 'internal' : 'staff' ) : 'customer';
		$body     = sanitize_textarea_field( (string) $body );
		$sent   = self::add_message( $project->ai_relay_project_id, $user_id, $author, $body, (array) $file_ids, true );
		if ( is_wp_error( $sent ) ) {
			return $sent;
		}
		$project = AiRelayProjects::get( $project->ai_relay_project_id );
		if ( $author === 'staff' ) {
			Mailer::send_ai_relay_customer_notice( $project, $sent );
		} elseif ( $author === 'customer' ) {
			Mailer::send_ai_relay_staff_notice( $project, $sent, false );
		}
		return self::project( $user_id, $project->ai_relay_project_id );
	}

	/**
	 * Staff edits: status (building / preview / cancelled), preview link, the
	 * staff-held site to hand over at launch, and the display name.
	 */
	public static function update_project( $user_id, $project_id, $input ) {
		if ( ! ( new User( $user_id, true ) )->is_admin() ) {
			return new \WP_Error( 'not_found', 'Project not found.', [ 'status' => 404 ] );
		}
		$project = AiRelayProjects::get( $project_id );
		if ( ! $project ) {
			return new \WP_Error( 'not_found', 'Project not found.', [ 'status' => 404 ] );
		}
		if ( $project->status === 'launched' ) {
			return new \WP_Error( 'launched', 'A launched project cannot be changed here.', [ 'status' => 400 ] );
		}
		$data = [ 'updated_at' => current_time( 'mysql' ) ];
		if ( isset( $input['status'] ) && in_array( $input['status'], [ 'building', 'preview', 'cancelled' ], true ) ) {
			$data['status'] = $input['status'];
		}
		if ( isset( $input['preview_url'] ) ) {
			$preview = esc_url_raw( trim( (string) $input['preview_url'] ) );
			if ( $preview !== '' && ! self::valid_url( $preview ) ) {
				return new \WP_Error( 'invalid_url', 'The preview link is not a valid URL.', [ 'status' => 400 ] );
			}
			$data['preview_url'] = $preview;
		}
		$linked = false;
		if ( isset( $input['site_id'] ) ) {
			$site_id = (int) $input['site_id'];
			if ( $site_id && ! Sites::get( $site_id ) ) {
				return new \WP_Error( 'invalid_site', 'No site with that id.', [ 'status' => 400 ] );
			}
			$data['site_id'] = $site_id;
			$linked          = $site_id && $site_id !== (int) $project->site_id;
		}
		if ( isset( $input['name'] ) && trim( (string) $input['name'] ) !== '' ) {
			$data['name'] = sanitize_text_field( (string) $input['name'] );
		}
		// "latest" marks everything in the thread as reviewed; a message id
		// marks up to and including that message.
		if ( isset( $input['reviewed'] ) ) {
			$thread = AiRelayMessages::thread( $project->ai_relay_project_id );
			$ids    = array_map( 'intval', array_column( $thread, 'ai_relay_message_id' ) );
			$upto   = $input['reviewed'] === 'latest' ? ( $ids ? max( $ids ) : 0 ) : (int) $input['reviewed'];
			if ( $upto && ! in_array( $upto, $ids, true ) ) {
				return new \WP_Error( 'invalid_message', 'That message is not in this project.', [ 'status' => 400 ] );
			}
			$data['reviewed_message_id'] = $upto;
			$data['reviewed_at']         = current_time( 'mysql' );
		}
		$status = $data['status'] ?? $project->status;
		if ( $status === 'preview' && ( $data['preview_url'] ?? $project->preview_url ) === '' ) {
			return new \WP_Error( 'preview_missing', 'Add a preview link before marking the preview ready.', [ 'status' => 400 ] );
		}
		// login_url is the customer's set-password link on the new site. It is
		// emailed once with the site link and never stored here. It must sit
		// on the same host as the site link and be a core password-reset URL.
		$login_url = trim( (string) ( $input['login_url'] ?? '' ) );
		$site_url  = $data['preview_url'] ?? $project->preview_url;
		if ( $login_url !== '' ) {
			$same_host = strtolower( (string) wp_parse_url( $login_url, PHP_URL_HOST ) ) === strtolower( (string) wp_parse_url( $site_url, PHP_URL_HOST ) );
			if ( ! self::valid_url( $login_url ) || ! $same_host || strpos( $login_url, 'wp-login.php' ) === false || strpos( $login_url, 'action=rp' ) === false ) {
				return new \WP_Error( 'invalid_login_url', 'login_url must be a wp-login.php?action=rp link on the same host as the site link.', [ 'status' => 400 ] );
			}
		}

		AiRelayProjects::update( $data, [ 'ai_relay_project_id' => $project->ai_relay_project_id ] );
		$fresh       = AiRelayProjects::get( $project->ai_relay_project_id );
		$to_preview  = ( $data['status'] ?? '' ) === 'preview' && $project->status !== 'preview';
		$url_changed = isset( $data['preview_url'] ) && $data['preview_url'] !== '' && $data['preview_url'] !== $project->preview_url;

		// One email per change: "ready to launch" wins over "your site is up".
		if ( $to_preview ) {
			Mailer::send_ai_relay_preview_ready( $fresh, $login_url );
		} elseif ( $url_changed || $login_url !== '' ) {
			Mailer::send_ai_relay_site_ready( $fresh, $login_url );
		}
		if ( $to_preview || $url_changed || $login_url !== '' ) {
			$what = $to_preview ? 'Ready-to-launch email' : 'Site link email';
			self::add_message( $project->ai_relay_project_id, $user_id, 'internal', "{$what} sent to the customer for {$fresh->preview_url}" . ( $login_url !== '' ? ' (with a set-password link, not stored).' : '.' ), [], false );
		}
		if ( $linked ) {
			$note = self::is_existing_site( $fresh )
				? "Linked existing site #{$fresh->site_id}. It is not on the staff-held account, so this build is not charged: Launch makes no plan, invoice or account change."
				: "Linked site #{$fresh->site_id} on the staff-held account. Launch charges the first year and moves it to the customer's account.";
			self::add_message( $project->ai_relay_project_id, $user_id, 'internal', $note, [], false );
		}
		return self::project( $user_id, $project->ai_relay_project_id );
	}

	/**
	 * Launch: put the account on the $240/year plan, charge the first year
	 * through the normal invoice path, and only when that is paid hand the
	 * staff-held site over to the customer's account. A declined card rolls
	 * the plan back and cancels the unpaid invoice.
	 */
	public static function launch( $user_id, $project_id ) {
		$project = self::viewable( $user_id, $project_id );
		if ( ! $project ) {
			return new \WP_Error( 'not_found', 'Project not found.', [ 'status' => 404 ] );
		}
		// Launch charges the launching user's card and makes them the plan's
		// billing owner, so it belongs to the person who requested the build,
		// not to any member of the account.
		if ( (int) $project->user_id !== (int) $user_id ) {
			return new \WP_Error( 'not_owner', 'Only the person who requested this build can launch it.', [ 'status' => 403 ] );
		}

		// One launch at a time per project: a double click must not bill twice.
		// A database lock, because a transient check-then-set let two requests
		// through together.
		global $wpdb;
		$lock = 'cc_relay_' . substr( md5( DB_NAME ), 0, 12 ) . '_' . (int) $project->ai_relay_project_id;
		if ( (string) $wpdb->get_var( $wpdb->prepare( "SELECT GET_LOCK( %s, 0 )", $lock ) ) !== '1' ) {
			return new \WP_Error( 'busy', 'Launch is already in progress.', [ 'status' => 409 ] );
		}
		try {
			// Read again under the lock so a launch that just finished is seen.
			$project = AiRelayProjects::get( (int) $project->ai_relay_project_id );
			if ( $project->status !== 'preview' ) {
				return new \WP_Error( 'not_ready', 'This site can launch once the preview is ready.', [ 'status' => 400 ] );
			}
			if ( ! empty( $project->order_id ) ) {
				$pending = wc_get_order( (int) $project->order_id );
				if ( $pending && $pending->has_status( [ 'on-hold', 'processing' ] ) ) {
					return new \WP_Error( 'payment_pending', 'Your launch payment is still clearing. We will finish the launch once it does.', [ 'status' => 409 ] );
				}
			}
			return self::is_existing_site( $project ) ? self::launch_existing( $user_id, $project ) : self::charge_launch( $user_id, $project );
		} finally {
			$wpdb->query( $wpdb->prepare( "SELECT RELEASE_LOCK( %s )", $lock ) );
		}
	}

	/**
	 * An existing-site build: the project's site was not provisioned by AI
	 * Relay (those sit on the staff-held account until launch), so it already
	 * belongs to a customer account and is billed there. These builds are
	 * never charged.
	 */
	public static function is_existing_site( $project ) {
		if ( empty( $project->site_id ) ) {
			return false;
		}
		$site = Sites::get( $project->site_id );
		if ( ! $site ) {
			return false;
		}
		// Read the option directly: holding_account() would create the account.
		$holding  = (int) get_site_option( 'captaincore_ai_relay_holding_account', 0 );
		$accounts = array_column( AccountSite::where( [ 'site_id' => (int) $project->site_id ] ), 'account_id' );
		$accounts = array_filter( array_map( 'intval', array_merge( $accounts, [ $site->account_id, $site->customer_id ] ) ) );
		return ! in_array( $holding, $accounts, true );
	}

	/**
	 * Whether this project costs the customer nothing. Before launch that is
	 * an existing-site build; after launch, a launch that raised no order.
	 */
	public static function is_free( $project ) {
		if ( $project->status === 'launched' ) {
			return empty( $project->order_id );
		}
		return self::is_existing_site( $project );
	}

	/**
	 * Launch for an existing-site build: the customer's approval to go live.
	 * No card, plan, invoice or account link is touched. Staff take it live.
	 */
	private static function launch_existing( $user_id, $project ) {
		$now = current_time( 'mysql' );
		AiRelayProjects::update( [
			'status'      => 'launched',
			'launched_at' => $now,
			'updated_at'  => $now,
		], [ 'ai_relay_project_id' => $project->ai_relay_project_id ] );
		self::add_message( $project->ai_relay_project_id, $user_id, 'internal', "Customer approved the launch. No charge: site #{$project->site_id} is an existing site on their own account, so no plan, invoice or account change was made. Take it live.", [], false );

		$project = AiRelayProjects::get( $project->ai_relay_project_id );
		Mailer::send_ai_relay_staff_notice( $project, null, false, 'launched' );

		return self::project( $user_id, $project->ai_relay_project_id );
	}

	private static function charge_launch( $user_id, $project ) {
		if ( ! self::has_card( $user_id ) ) {
			return new \WP_Error( 'card_required', 'Add a card in Billing before launching.', [ 'status' => 400 ] );
		}

		// Nothing to hand over without a site: never charge for an empty launch.
		if ( empty( $project->site_id ) ) {
			return new \WP_Error( 'no_site', 'This build has no site linked yet. We will let you know when it is ready to launch.', [ 'status' => 400 ] );
		}

		$account_id = (int) $project->account_id;
		$account    = Accounts::get( $account_id );

		// Launch sets the account's whole plan. A project filed on an account
		// already in use (a plan, or sites of its own; possible for projects
		// submitted before 2026-10-04) moves to a fresh account instead, so
		// that account keeps its plan and its sites are not billed under the
		// launch plan.
		if ( ! $account || self::in_use( $account ) ) {
			$from       = $account_id;
			$account_id = self::new_account( new User( $user_id, true ), $project->name );
			AiRelayProjects::update( [ 'account_id' => $account_id, 'updated_at' => current_time( 'mysql' ) ], [ 'ai_relay_project_id' => $project->ai_relay_project_id ] );
			self::add_message( $project->ai_relay_project_id, $user_id, 'internal', "Launch moved this project from account #{$from}, which already has a plan, to new account #{$account_id}, so that plan was left as it was.", [], false );
			$project = AiRelayProjects::get( $project->ai_relay_project_id );
			$account = Accounts::get( $account_id );
		}

		$previous   = (string) $account->plan;
		$preset     = self::launch_plan();
		$plan       = (object) [
			'name'            => $preset->name,
			'price'           => $preset->price,
			'interval'        => $preset->interval,
			'limits'          => $preset->limits,
			'usage'           => (object) [ 'sites' => 1, 'storage' => 0, 'visits' => 0 ],
			'addons'          => [],
			'billing_user_id' => (string) $user_id,
			'auto_pay'        => 'true',
			'next_renewal'    => gmdate( 'Y-m-d H:i:s', strtotime( '+' . (int) $preset->interval . ' month' ) ),
		];
		Accounts::update( [ 'plan' => wp_json_encode( $plan ) ], [ 'account_id' => $account_id ] );

		$before = wc_get_orders( [ 'limit' => 1, 'return' => 'ids', 'meta_key' => 'captaincore_account_id', 'meta_value' => $account_id, 'orderby' => 'ID', 'order' => 'DESC' ] );
		( new Account( $account_id, true ) )->generate_order();
		$after = wc_get_orders( [ 'limit' => 1, 'meta_key' => 'captaincore_account_id', 'meta_value' => $account_id, 'orderby' => 'ID', 'order' => 'DESC' ] );
		$order = $after ? $after[0] : null;
		$new   = $order && ( ! $before || (int) $order->get_id() !== (int) $before[0] );

		if ( $new && ! $order->is_paid() && ! $order->needs_payment() ) {
			// On hold or processing: an ACH debit or a card charge under review
			// is still clearing. Cancelling would not stop the money and nothing
			// would ever finish the launch, so keep the plan and the invoice and
			// leave the handover to staff once it clears.
			AiRelayProjects::update( [ 'order_id' => $order->get_id(), 'updated_at' => current_time( 'mysql' ) ], [ 'ai_relay_project_id' => $project->ai_relay_project_id ] );
			self::add_message( $project->ai_relay_project_id, $user_id, 'internal', "Customer pressed Launch. Invoice #{$order->get_id()} is {$order->get_status()} (payment still clearing). Once it is paid, hand site #{$project->site_id} to account #{$account_id} and mark the project launched.", [], false );
			Mailer::send_ai_relay_staff_notice( AiRelayProjects::get( $project->ai_relay_project_id ), null, false, 'launched' );
			return new \WP_Error( 'payment_pending', 'Your payment is processing. We will finish the launch as soon as it clears.', [ 'status' => 202 ] );
		}

		if ( ! $new || ! $order->is_paid() ) {
			if ( $new ) {
				$order->update_status( 'cancelled', 'AI Relay launch charge did not go through.' );
			}
			Accounts::update( [ 'plan' => $previous ], [ 'account_id' => $account_id ] );
			return new \WP_Error( 'payment_failed', 'Your card was not charged successfully. Update it in Billing, then try again.', [ 'status' => 402 ] );
		}

		// Paid. Hand the staff-held site to the customer's account.
		if ( ! empty( $project->site_id ) ) {
			// Replace, not add: the staff-held account drops off the site here.
			Sites::update( [ 'account_id' => $account_id, 'customer_id' => $account_id ], [ 'site_id' => $project->site_id ] );
			( new Site( $project->site_id ) )->assign_accounts( [ $account_id ] );
			( new Account( self::holding_account(), true ) )->calculate_totals();
		}
		( new Account( $account_id, true ) )->calculate_totals();
		( new Account( $account_id, true ) )->sync();

		$now = current_time( 'mysql' );
		AiRelayProjects::update( [
			'status'      => 'launched',
			'order_id'    => $order->get_id(),
			'launched_at' => $now,
			'updated_at'  => $now,
		], [ 'ai_relay_project_id' => $project->ai_relay_project_id ] );

		$project = AiRelayProjects::get( $project->ai_relay_project_id );
		Mailer::send_ai_relay_staff_notice( $project, null, false, 'launched' );

		return self::project( $user_id, $project->ai_relay_project_id );
	}

	/**
	 * The hosting plan a launch applies: the configured preset priced at
	 * $240 every 12 months (Basic), filterable for other brands.
	 */
	private static function launch_plan() {
		$config = ( new Configurations )->get();
		$match  = null;
		foreach ( (array) ( $config->hosting_plans ?? [] ) as $preset ) {
			if ( (float) $preset->price === 240.0 && (int) $preset->interval === 12 ) {
				$match = $preset;
				break;
			}
		}
		if ( ! $match ) {
			$match = (object) [
				'name'     => 'Basic',
				'price'    => '240',
				'interval' => '12',
				'limits'   => (object) [ 'visits' => '100000', 'storage' => '10', 'sites' => '1' ],
			];
		}
		return apply_filters( 'captaincore_ai_relay_launch_plan', $match );
	}

	/**
	 * Stream one file from a project's thread to someone allowed to see it.
	 * A video asked for $inline plays in the page under its own type; any
	 * other file, or any request without $inline, downloads as plain bytes.
	 * A single byte range in $range is honoured so players can seek, and
	 * Safari will not play a video without one.
	 */
	public static function send_file( $user_id, $project_id, $file_id, $inline = false, $range = '' ) {
		$project = self::viewable( $user_id, $project_id );
		if ( ! $project || ! preg_match( '/^[a-f0-9]{16}$/', (string) $file_id ) ) {
			return new \WP_Error( 'not_found', 'File not found.', [ 'status' => 404 ] );
		}
		foreach ( AiRelayMessages::thread( $project->ai_relay_project_id ) as $message ) {
			foreach ( (array) json_decode( (string) $message->files, true ) as $file ) {
				if ( ( $file['id'] ?? '' ) !== $file_id ) {
					continue;
				}
				$path = self::dir_path( 'projects/' . $project->storage_key ) . '/' . $file['id'] . '.' . $file['ext'];
				if ( ! is_file( $path ) ) {
					break 2;
				}
				$type = $inline ? ( self::VIDEO_TYPES[ $file['ext'] ] ?? '' ) : '';
				self::stream( $path, $file['name'], $type, (string) $range );
				exit;
			}
		}
		return new \WP_Error( 'not_found', 'File not found.', [ 'status' => 404 ] );
	}

	/**
	 * Send a file, or the one byte range asked for. $type set means show it
	 * inline under that type; empty means a download.
	 */
	private static function stream( $path, $name, $type, $range ) {
		$size  = (int) filesize( $path );
		$start = 0;
		$end   = $size - 1;
		$part  = false;
		// One range only. Anything else gets the whole file, which a client
		// must accept.
		if ( preg_match( '/^bytes=(\d*)-(\d*)$/', trim( $range ), $m ) && ( $m[1] !== '' || $m[2] !== '' ) ) {
			if ( $m[1] === '' ) {
				$start = max( 0, $size - (int) $m[2] );
			} else {
				$start = (int) $m[1];
				if ( $m[2] !== '' ) {
					$end = min( (int) $m[2], $size - 1 );
				}
			}
			if ( $start >= $size || $start > $end ) {
				status_header( 416 );
				header( 'Content-Range: bytes */' . $size );
				return;
			}
			$part = true;
		}

		while ( ob_get_level() ) {
			ob_end_clean();
		}
		nocache_headers();
		header( 'Content-Type: ' . ( $type ? $type : 'application/octet-stream' ) );
		header( 'Content-Disposition: ' . ( $type ? 'inline' : 'attachment' ) . '; filename="' . str_replace( '"', '', $name ) . '"' );
		header( 'Accept-Ranges: bytes' );
		header( 'X-Content-Type-Options: nosniff' );
		if ( $part ) {
			status_header( 206 );
			header( 'Content-Range: bytes ' . $start . '-' . $end . '/' . $size );
		}
		header( 'Content-Length: ' . ( $end - $start + 1 ) );

		$fh = fopen( $path, 'rb' );
		if ( ! $fh ) {
			return;
		}
		fseek( $fh, $start );
		$left = $end - $start + 1;
		while ( $left > 0 && ! connection_aborted() ) {
			$chunk = fread( $fh, min( 1048576, $left ) );
			if ( $chunk === false || $chunk === '' ) {
				break;
			}
			echo $chunk; // phpcs:ignore WordPress.Security.EscapeOutput -- raw file bytes
			$left -= strlen( $chunk );
			flush();
		}
		fclose( $fh );
	}

	/**
	 * Staff: provision a WordPress site at Kinsta for a project. It is created
	 * on the staff-held account (never the customer's), and the provisioning
	 * chain links it to the project via link_site() once the site record
	 * exists. Drive the chain with GET /provider-actions/check and
	 * /provider-actions/<id>/run as the same user.
	 *
	 * @param array $input datacenter (default us-ashburn-1), name (Kinsta site name, 5-32)
	 */
	public static function create_site( $user_id, $project_id, $input ) {
		if ( ! ( new User( $user_id, true ) )->is_admin() ) {
			return new \WP_Error( 'not_found', 'Project not found.', [ 'status' => 404 ] );
		}
		$project = AiRelayProjects::get( (int) $project_id );
		if ( ! $project || ! in_array( $project->status, [ 'building', 'preview' ], true ) ) {
			return new \WP_Error( 'not_found', 'No open project with that id.', [ 'status' => 404 ] );
		}
		if ( ! empty( $project->site_id ) ) {
			return new \WP_Error( 'has_site', 'This project already has a site (#' . (int) $project->site_id . ').', [ 'status' => 400 ] );
		}
		if ( ! empty( $project->provider_action_id ) ) {
			$pending = ProviderActions::get( $project->provider_action_id );
			if ( $pending && ! in_array( $pending->status, [ 'done', 'failed' ], true ) ) {
				return new \WP_Error( 'in_progress', 'A site is already being created for this project (provider action #' . (int) $project->provider_action_id . ').', [ 'status' => 409 ] );
			}
		}

		$name = strtolower( preg_replace( '/[^a-zA-Z0-9]/', '', (string) ( $input['name'] ?? '' ) ) );
		if ( $name === '' ) {
			$name = strtolower( preg_replace( '/[^a-zA-Z0-9]/', '', (string) $project->name ) );
		}
		if ( strlen( $name ) < 5 ) {
			$name = substr( $name . 'relaysite', 0, max( 9, strlen( $name ) ) );
		}
		$name       = substr( $name, 0, 32 );
		$datacenter = preg_match( '/^[a-z0-9-]{3,40}$/', (string) ( $input['datacenter'] ?? '' ) ) ? $input['datacenter'] : 'us-ashburn-1';
		$holding    = self::holding_account();
		$provider   = ( new Provider( 'kinsta' ) )->get();

		$result = ( new Provider( 'kinsta' ) )->new_site( (object) [
			'name'                => $name,
			'domain'              => '',
			'datacenter'          => $datacenter,
			'clone_site_id'       => '',
			'provider_id'         => (string) $provider->provider_id,
			'account_id'          => $holding,
			'customer_id'         => $holding,
			'shared_with'         => [],
			'ai_relay_project_id' => (int) $project->ai_relay_project_id,
		] );

		if ( empty( $result['operation_id'] ) ) {
			return new \WP_Error( 'kinsta_refused', 'Kinsta did not accept the new site request. Try again shortly.', [ 'status' => 502 ] );
		}
		$action = ProviderActions::where( [ 'provider_key' => $result['operation_id'] ] );
		$action_id = $action ? (int) $action[0]->provider_action_id : 0;
		AiRelayProjects::update( [ 'provider_action_id' => $action_id, 'updated_at' => current_time( 'mysql' ) ], [ 'ai_relay_project_id' => $project->ai_relay_project_id ] );
		self::add_message( $project->ai_relay_project_id, $user_id, 'internal', "Creating Kinsta site {$name} in {$datacenter} on the staff-held account (provider action #{$action_id}).", [], false );

		return [
			'provider_action_id' => $action_id,
			'operation_id'       => $result['operation_id'],
			'name'               => $name,
			'datacenter'         => $datacenter,
			'holding_account_id' => $holding,
		];
	}

	/**
	 * Called by the provisioning chain (ProviderAction::run) once the site
	 * record exists.
	 */
	public static function link_site( $project_id, $site_id, $provider_action_id = 0 ) {
		$project = AiRelayProjects::get( $project_id );
		if ( ! $project || ! empty( $project->site_id ) ) {
			return;
		}
		AiRelayProjects::update( [ 'site_id' => $site_id, 'updated_at' => current_time( 'mysql' ) ], [ 'ai_relay_project_id' => $project_id ] );
		$site = Sites::get( $site_id );
		self::add_message( $project_id, (int) get_current_user_id(), 'internal', 'Site #' . (int) $site_id . ' (' . ( $site ? $site->name : '' ) . ') provisioned and linked. It stays on the staff-held account until launch.', [], false );
	}

	/**
	 * The account AI Relay sites live on before launch. Created on first use;
	 * it has no plan and nobody but staff on it.
	 */
	public static function holding_account() {
		$id = (int) get_site_option( 'captaincore_ai_relay_holding_account', 0 );
		if ( $id && Accounts::get( $id ) ) {
			return $id;
		}
		$now = current_time( 'mysql' );
		$id  = (int) Accounts::insert( [
			'name'       => 'AI Relay (staff-held)',
			'status'     => 'active',
			'created_at' => $now,
			'updated_at' => $now,
			'defaults'   => wp_json_encode( [ 'email' => '', 'timezone' => '', 'recipes' => [], 'users' => [] ] ),
		] );
		update_site_option( 'captaincore_ai_relay_holding_account', $id );
		return $id;
	}

	private static function unreviewed_count( $project ) {
		global $wpdb;
		$table = $wpdb->prefix . 'captaincore_ai_relay_messages';
		return (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM $table WHERE ai_relay_project_id = %d AND author = 'customer' AND ai_relay_message_id > %d",
			$project->ai_relay_project_id, (int) $project->reviewed_message_id
		) );
	}

	/**
	 * The user's project that has not launched or been cancelled.
	 */
	public static function open_project( $user_id ) {
		foreach ( self::projects_for_owner( $user_id ) as $project ) {
			if ( in_array( $project->status, [ 'building', 'preview' ], true ) ) {
				return $project;
			}
		}
		return false;
	}

	public static function has_card( $user_id ) {
		if ( ! class_exists( '\WC_Payment_Tokens' ) ) {
			return false;
		}
		foreach ( \WC_Payment_Tokens::get_customer_tokens( $user_id ) as $token ) {
			if ( $token->get_type() === 'CC' ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Saved cards, shaped for the page (brand, last4, expiry, token id).
	 */
	public static function cards( $user_id ) {
		$cards = [];
		if ( ! class_exists( '\WC_Payment_Tokens' ) ) {
			return $cards;
		}
		foreach ( \WC_Payment_Tokens::get_customer_tokens( $user_id ) as $token ) {
			if ( $token->get_type() !== 'CC' ) {
				continue;
			}
			$cards[] = [
				'token'      => $token->get_id(),
				'brand'      => ucfirst( (string) $token->get_card_type() ),
				'last4'      => $token->get_last4(),
				'expires'    => $token->get_expiry_month() . '/' . substr( (string) $token->get_expiry_year(), -2 ),
				'is_default' => $token->is_default(),
			];
		}
		return $cards;
	}

	/* ---------------------------------------------------------------------
	 *  Helpers
	 * ------------------------------------------------------------------- */

	/**
	 * Path of the public page, filterable for sites that mount it elsewhere.
	 */
	public static function page_path() {
		return (string) apply_filters( 'captaincore_ai_relay_path', '/ai-relay/' );
	}

	private static function signup_key( $token ) {
		return 'cc_relay_signup_' . substr( hash( 'sha256', (string) $token ), 0, 40 );
	}

	private static function turnstile_ok( $token ) {
		if ( ! defined( 'CAPTAINCORE_TURNSTILE_SECRET_KEY' ) || ! CAPTAINCORE_TURNSTILE_SECRET_KEY ) {
			return true;
		}
		$response = wp_remote_post( 'https://challenges.cloudflare.com/turnstile/v0/siteverify', [
			'timeout' => 10,
			'body'    => [
				'secret'   => CAPTAINCORE_TURNSTILE_SECRET_KEY,
				'response' => (string) $token,
				'remoteip' => GeoIP::client_ip(),
			],
		] );
		if ( is_wp_error( $response ) ) {
			return false;
		}
		$body = json_decode( wp_remote_retrieve_body( $response ) );
		return ! empty( $body->success );
	}

	private static function save_billing( $user_id, $billing ) {
		if ( ! class_exists( '\WC_Customer' ) ) {
			return;
		}
		$customer = new \WC_Customer( $user_id );
		foreach ( [ 'first_name', 'last_name', 'company', 'address_1', 'address_2', 'city', 'state', 'postcode', 'country' ] as $key ) {
			if ( isset( $billing[ $key ] ) && trim( (string) $billing[ $key ] ) !== '' ) {
				$customer->{"set_billing_{$key}"}( sanitize_text_field( (string) $billing[ $key ] ) );
			}
		}
		if ( ! $customer->get_billing_email() ) {
			$customer->set_billing_email( get_userdata( $user_id )->user_email );
		}
		$customer->save();
	}

	/**
	 * The account a new build bills to. The user's only account is reused when
	 * it has no plan yet and they are a full member of it; anything else gets
	 * a fresh account without a plan (billing starts at launch). Reusing any
	 * single account let a launch replace an existing customer's plan, or
	 * bill a plan onto an account an invited member does not own.
	 */
	private static function account_for( User $user, $name ) {
		$accounts = $user->accounts();
		if ( count( $accounts ) === 1 ) {
			$account = Accounts::get( (int) $accounts[0] );
			$members = array_map( 'intval', array_column( AccountUser::where( [ 'account_id' => (int) $accounts[0] ] ), 'user_id' ) );
			if ( $account && ! self::in_use( $account ) && $members === [ (int) $user->user_id() ] ) {
				return (int) $accounts[0];
			}
		}
		return self::new_account( $user, $name );
	}

	/**
	 * Whether an account already carries a plan or sites of its own, so a
	 * launch plan must not be put on it.
	 */
	private static function in_use( $account ) {
		global $wpdb;
		if ( self::has_plan( $account ) ) {
			return true;
		}
		$id    = (int) $account->account_id;
		$sites = (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM {$wpdb->prefix}captaincore_sites WHERE ( account_id = %d OR customer_id = %d ) AND status = 'active'",
			$id, $id
		) );
		return $sites > 0 || count( AccountSite::where( [ 'account_id' => $id ] ) ) > 0;
	}

	/**
	 * Whether an account already carries a billed plan.
	 */
	private static function has_plan( $account ) {
		$plan = empty( $account->plan ) ? null : json_decode( $account->plan );
		return is_object( $plan ) && ( ! empty( $plan->next_renewal ) || (float) ( $plan->price ?? 0 ) > 0
			|| ( is_array( $plan->addons ?? null ) && count( $plan->addons ) > 0 ) );
	}

	/**
	 * A new account for this user, without a plan.
	 */
	private static function new_account( User $user, $name ) {
		$accounts   = $user->accounts();
		$now        = current_time( 'mysql' );
		$account_id = ( new Accounts )->insert( [
			'name'       => $name ? $name : 'AI Relay',
			'status'     => 'active',
			'created_at' => $now,
			'updated_at' => $now,
			'defaults'   => wp_json_encode( [ 'email' => '', 'timezone' => '', 'recipes' => [], 'users' => [] ] ),
		] );
		$user->assign_accounts( array_merge( $accounts, [ $account_id ] ) );
		( new Account( $account_id, true ) )->calculate_totals();
		( new Account( $account_id, true ) )->sync();
		return (int) $account_id;
	}

	/**
	 * Save a message and move the named staged files into the project folder.
	 */
	private static function add_message( $project_id, $user_id, $author, $body, $file_ids, $require_content ) {
		$project = AiRelayProjects::get( $project_id );
		$staged  = self::staged( $user_id );
		$files   = [];
		$from    = self::dir_path( 'staging/' . (int) $user_id );
		$dest    = null;

		foreach ( array_unique( array_map( 'strval', $file_ids ) ) as $id ) {
			if ( ! isset( $staged[ $id ] ) ) {
				continue;
			}
			if ( $dest === null ) {
				$dest = self::dir( 'projects/' . $project->storage_key );
				if ( is_wp_error( $dest ) ) {
					return $dest;
				}
			}
			$file = $staged[ $id ];
			$name = $file['id'] . '.' . $file['ext'];
			if ( is_file( $from . '/' . $name ) && rename( $from . '/' . $name, $dest . '/' . $name ) ) {
				$files[] = [ 'id' => $file['id'], 'name' => $file['name'], 'size' => (int) $file['size'], 'ext' => $file['ext'] ];
			}
			unset( $staged[ $id ] );
		}
		update_user_meta( $user_id, self::STAGED_META, $staged );

		if ( $require_content && trim( (string) $body ) === '' && ! $files ) {
			return new \WP_Error( 'empty', 'Write a message or attach a file.', [ 'status' => 400 ] );
		}

		$now = current_time( 'mysql' );
		$id  = AiRelayMessages::insert( [
			'ai_relay_project_id' => $project_id,
			'user_id'             => $user_id,
			'author'              => $author,
			'body'                => (string) $body,
			'files'               => wp_json_encode( $files ),
			'created_at'          => $now,
		] );
		AiRelayProjects::update( [ 'last_message_at' => $now, 'updated_at' => $now ], [ 'ai_relay_project_id' => $project_id ] );

		return AiRelayMessages::get( $id );
	}

	/**
	 * The project row when the user may see it: staff, or a member of the
	 * project's account.
	 */
	private static function viewable( $user_id, $project_id ) {
		$project = AiRelayProjects::get( (int) $project_id );
		if ( ! $project ) {
			return false;
		}
		$user = new User( $user_id, true );
		if ( $user->is_admin() ) {
			return $project;
		}
		return in_array( (string) $project->account_id, array_map( 'strval', $user->accounts() ), true ) ? $project : false;
	}

	/**
	 * An absolute http(s) link. A format check only: wp_http_validate_url()
	 * resolves the host, which is for outbound requests, not stored links.
	 */
	private static function valid_url( $url ) {
		$scheme = strtolower( (string) wp_parse_url( (string) $url, PHP_URL_SCHEME ) );
		return in_array( $scheme, [ 'http', 'https' ], true ) && (bool) filter_var( $url, FILTER_VALIDATE_URL ) && (string) wp_parse_url( $url, PHP_URL_HOST ) !== '';
	}

	private static function projects_for_owner( $user_id ) {
		return AiRelayProjects::where( [ 'user_id' => (int) $user_id ] );
	}

	/**
	 * What the dashboard sees. Before launch a customer gets the preview link
	 * and nothing about where the site is hosted; site_id is staff-only until
	 * the launch has been paid for.
	 */
	private static function project_for_view( $project, $user_id ) {
		$is_admin = ( new User( $user_id, true ) )->is_admin();
		$labels   = [ 'building' => 'Building', 'preview' => 'Preview ready', 'launched' => 'Launched', 'cancelled' => 'Cancelled' ];
		$account  = Accounts::get( $project->account_id );
		$free     = self::is_free( $project );
		$view     = [
			'id'              => (int) $project->ai_relay_project_id,
			'name'            => $project->name,
			'status'          => $project->status,
			'status_label'    => $labels[ $project->status ] ?? $project->status,
			'mode'            => $project->mode,
			'source_url'      => $project->source_url,
			// The site link is the customer's from the moment staff set it (they
			// are an editor on it); Launch still waits for status 'preview'.
			'preview_url'     => $project->preview_url,
			'account_id'      => (int) $project->account_id,
			'account'         => $account ? $account->name : '',
			'created_at'      => $project->created_at,
			'last_message_at' => $project->last_message_at,
			'launched_at'     => $project->launched_at,
			'can_launch'      => $project->status === 'preview' && (int) $project->user_id === (int) $user_id,
			// 'none' for existing-site builds, which are never charged.
			'billing'         => $free ? 'none' : 'launch',
			'price'           => $free ? 0.0 : (float) self::launch_plan()->price,
		];
		if ( $is_admin || $project->status === 'launched' ) {
			$view['site_id'] = (int) $project->site_id;
		}
		if ( $is_admin ) {
			$owner                      = get_userdata( $project->user_id );
			$view['user_email']         = $owner ? $owner->user_email : '';
			$view['reviewed_message_id'] = (int) $project->reviewed_message_id;
			$view['reviewed_at']        = $project->reviewed_at;
			$view['provider_action_id'] = (int) $project->provider_action_id;
			$view['unreviewed']         = self::unreviewed_count( $project );
			$view['needs_review']       = $view['unreviewed'] > 0 && in_array( $project->status, [ 'building', 'preview' ], true );
		}
		return $view;
	}

	public static function public_file( $file ) {
		return [
			'id'    => $file['id'],
			'name'  => $file['name'],
			'size'  => (int) $file['size'],
			'video' => isset( self::VIDEO_TYPES[ $file['ext'] ?? '' ] ),
		];
	}

	private static function base_dir() {
		if ( defined( 'CAPTAINCORE_AI_RELAY_DIR' ) && CAPTAINCORE_AI_RELAY_DIR ) {
			return untrailingslashit( CAPTAINCORE_AI_RELAY_DIR );
		}
		return dirname( untrailingslashit( ABSPATH ) ) . '/private/ai-relay';
	}

	private static function dir_path( $sub ) {
		return self::base_dir() . '/' . $sub;
	}

	private static function dir( $sub ) {
		$path = self::dir_path( $sub );
		if ( ! is_dir( $path ) && ! wp_mkdir_p( $path ) ) {
			return new \WP_Error( 'storage', 'File storage is not available.', [ 'status' => 500 ] );
		}
		return $path;
	}
}
