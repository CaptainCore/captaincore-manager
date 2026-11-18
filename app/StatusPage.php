<?php

namespace CaptainCore;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The public status page: incidents, maintenance windows, postmortems and
 * the people emailed about them. Replaces a hosted Statuspage.io page.
 *
 * An incident moves investigating → identified → monitoring → resolved; a
 * maintenance window scheduled → in_progress → verifying → completed. Each
 * move is an update with a message. A postmortem is written on the incident
 * afterwards and published on its own.
 *
 * Email. An update (or a published postmortem) marked to notify gets
 * notify = 'pending'. run_due() sends it to every active subscriber, one
 * email each, in subscriber order, saving the last id sent (notify_cursor)
 * after every email, so a send cut short by a timeout resumes where it
 * stopped. It runs right after the request that queued it (after the
 * response is flushed) and from WP-Cron every minute as a safety net.
 *
 * Subscribers. One row per address. A Manager login toggles its own row in
 * Profile; addresses without a login (imported from Statuspage) keep theirs
 * until they click unsubscribe. Every email carries a one-click
 * unsubscribe link signed with the row's token.
 *
 * The page. /status/ (CAPTAINCORE_STATUS_PATH or the captaincore_status_path
 * site option to move it) is served by this plugin: the theme's
 * captaincore/status.php when it has one, else templates/status.php.
 * /status/incidents/<id>/ is one incident (a Statuspage id works too, so
 * old links redirect cleanly) and /status/feed/ is an Atom feed.
 */
class StatusPage {

	const INCIDENT_STATUSES    = [ 'investigating', 'identified', 'monitoring', 'resolved' ];
	const MAINTENANCE_STATUSES = [ 'scheduled', 'in_progress', 'verifying', 'completed' ];
	const IMPACTS              = [ 'none', 'minor', 'major', 'critical', 'maintenance' ];
	const PER_PAGE             = 20;
	const REWRITE_VERSION      = '1';

	// ── settings ────────────────────────────────────────────────────────

	public static function path() {
		$p = defined( 'CAPTAINCORE_STATUS_PATH' ) ? CAPTAINCORE_STATUS_PATH : get_site_option( 'captaincore_status_path', 'status' );
		$p = trim( preg_replace( '#[^a-z0-9/_-]#', '', strtolower( (string) $p ) ), '/' );
		return $p === '' ? 'status' : $p;
	}

	public static function url( $sub = '' ) {
		return home_url( '/' . self::path() . '/' . ( $sub !== '' ? trim( $sub, '/' ) . '/' : '' ) );
	}

	public static function incident_url( $incident ) {
		return self::url( 'incidents/' . (int) $incident->status_incident_id );
	}

	public static function name() {
		$config = Configurations::get();
		$name   = ! empty( $config->name ) ? $config->name : get_bloginfo( 'name' );
		return html_entity_decode( (string) $name, ENT_QUOTES, 'UTF-8' );
	}

	private static function table( $name ) {
		global $wpdb;
		return $wpdb->base_prefix . 'captaincore_status_' . $name;
	}

	private static function now() {
		return gmdate( 'Y-m-d H:i:s' );
	}

	public static function is_maintenance_status( $status ) {
		return in_array( $status, self::MAINTENANCE_STATUSES, true );
	}

	public static function is_closed( $status ) {
		return in_array( $status, [ 'resolved', 'completed' ], true );
	}

	public static function status_label( $status ) {
		$labels = [
			'investigating' => 'Investigating',
			'identified'    => 'Identified',
			'monitoring'    => 'Monitoring',
			'resolved'      => 'Resolved',
			'scheduled'     => 'Scheduled',
			'in_progress'   => 'In progress',
			'verifying'     => 'Verifying',
			'completed'     => 'Completed',
			'postmortem'    => 'Postmortem',
		];
		return $labels[ $status ] ?? ucfirst( str_replace( '_', ' ', (string) $status ) );
	}

	public static function impact_label( $impact ) {
		$labels = [
			'none'        => 'No impact',
			'minor'       => 'Minor',
			'major'       => 'Major',
			'critical'    => 'Critical',
			'maintenance' => 'Maintenance',
		];
		return $labels[ $impact ] ?? ucfirst( (string) $impact );
	}

	/** Markdown → safe HTML. Bodies are operator-written but rendered in safe mode anyway. */
	public static function html( $text ) {
		return captaincore_markdown( (string) $text, false, false, true );
	}

	/** GMT datetime string → unix time (0 when empty). */
	private static function ts( $gmt ) {
		return $gmt ? (int) strtotime( $gmt . ' UTC' ) : 0;
	}

	public static function iso( $gmt ) {
		return $gmt ? gmdate( 'c', self::ts( $gmt ) ) : null;
	}

	/** A GMT time as the site shows it: "Nov 17, 2026 4:31 PM EST". */
	public static function local( $gmt, $format = 'M j, Y g:i A T' ) {
		return $gmt ? wp_date( $format, self::ts( $gmt ) ) : '';
	}

	// ── reads ───────────────────────────────────────────────────────────

	public static function get( $id ) {
		global $wpdb;
		$t = self::table( 'incidents' );
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $t WHERE status_incident_id = %d", (int) $id ) );
	}

	public static function get_by_source( $source_id ) {
		global $wpdb;
		$t = self::table( 'incidents' );
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $t WHERE source_id = %s AND source_id <> '' LIMIT 1", (string) $source_id ) );
	}

	public static function updates( $incident_id ) {
		global $wpdb;
		$t = self::table( 'updates' );
		return $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $t WHERE status_incident_id = %d ORDER BY created_at DESC, status_update_id DESC", (int) $incident_id ) );
	}

	/** Incidents not yet resolved/completed, newest first. Maintenance includes upcoming windows. */
	public static function open() {
		global $wpdb;
		$t = self::table( 'incidents' );
		return $wpdb->get_results( "SELECT * FROM $t WHERE status NOT IN ('resolved','completed') ORDER BY started_at DESC, status_incident_id DESC" );
	}

	/** Closed incidents, newest first, paged. */
	public static function history( $page = 1, $per_page = self::PER_PAGE ) {
		global $wpdb;
		$t     = self::table( 'incidents' );
		$page  = max( 1, (int) $page );
		$total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM $t WHERE status IN ('resolved','completed')" );
		$rows  = $wpdb->get_results( $wpdb->prepare(
			"SELECT * FROM $t WHERE status IN ('resolved','completed') ORDER BY started_at DESC, status_incident_id DESC LIMIT %d OFFSET %d",
			$per_page, ( $page - 1 ) * $per_page
		) );
		return [ 'rows' => $rows, 'total' => $total, 'page' => $page, 'pages' => max( 1, (int) ceil( $total / $per_page ) ) ];
	}

	/** The page's headline: worst impact among open incidents (upcoming maintenance does not count). */
	public static function indicator( $open = null ) {
		$open  = $open ?? self::open();
		$rank  = [ 'none' => 0, 'maintenance' => 1, 'minor' => 2, 'major' => 3, 'critical' => 4 ];
		$worst = -1;
		foreach ( $open as $i ) {
			if ( $i->kind === 'maintenance' && $i->status === 'scheduled' ) {
				continue;
			}
			$impact = $i->kind === 'maintenance' ? 'maintenance' : $i->impact;
			$worst  = max( $worst, $rank[ $impact ] ?? 0 );
		}
		$map = [
			-1 => [ 'operational', 'All systems operational' ],
			0  => [ 'operational', 'All systems operational' ],
			1  => [ 'maintenance', 'Maintenance in progress' ],
			2  => [ 'minor', 'Minor service disruption' ],
			3  => [ 'major', 'Partial service outage' ],
			4  => [ 'critical', 'Major service outage' ],
		];
		return [ 'indicator' => $map[ $worst ][0], 'description' => $map[ $worst ][1] ];
	}

	/** One incident as the public sees it (no author ids, no notify state). */
	public static function public_incident( $i, $with_updates = true ) {
		$out = [
			'id'                      => (int) $i->status_incident_id,
			'kind'                    => $i->kind,
			'title'                   => $i->title,
			'status'                  => $i->status,
			'status_label'            => self::status_label( $i->status ),
			'impact'                  => $i->kind === 'maintenance' ? 'maintenance' : $i->impact,
			'impact_label'            => self::impact_label( $i->kind === 'maintenance' ? 'maintenance' : $i->impact ),
			'started_at'              => self::iso( $i->started_at ),
			'resolved_at'             => self::iso( $i->resolved_at ),
			'scheduled_for'           => self::iso( $i->scheduled_for ),
			'scheduled_until'         => self::iso( $i->scheduled_until ),
			'started_local'           => self::local( $i->started_at ),
			'resolved_local'          => self::local( $i->resolved_at ),
			'scheduled_for_local'     => self::local( $i->scheduled_for ),
			'scheduled_until_local'   => self::local( $i->scheduled_until ),
			'duration'                => self::duration( $i ),
			'url'                     => self::incident_url( $i ),
			'postmortem_html'         => ( $i->postmortem_published_at && trim( (string) $i->postmortem ) !== '' ) ? self::html( $i->postmortem ) : '',
			'postmortem_published_at' => self::iso( $i->postmortem_published_at ),
			'postmortem_local'        => self::local( $i->postmortem_published_at ),
		];
		if ( $with_updates ) {
			$out['updates'] = array_map( function ( $u ) {
				return [
					'status'       => $u->status,
					'status_label' => self::status_label( $u->status ),
					'body_html'    => self::html( $u->body ),
					'created_at'   => self::iso( $u->created_at ),
					'created_local'=> self::local( $u->created_at ),
				];
			}, self::updates( $i->status_incident_id ) );
		}
		return $out;
	}

	/** "23 minutes", "3 hours", "2 days" from start to resolution (or now). */
	public static function duration( $i ) {
		$start = self::ts( $i->kind === 'maintenance' && $i->scheduled_for ? $i->scheduled_for : $i->started_at );
		$end   = $i->resolved_at ? self::ts( $i->resolved_at ) : ( self::is_closed( $i->status ) ? 0 : time() );
		if ( ! $start || ! $end || $end < $start ) {
			return '';
		}
		return human_time_diff( $start, $end );
	}

	/** Everything the public page renders. */
	public static function public_feed( $page = 1 ) {
		$open     = self::open();
		$history  = self::history( $page );
		$active   = [];
		$upcoming = [];
		foreach ( $open as $i ) {
			if ( $i->kind === 'maintenance' && $i->status === 'scheduled' ) {
				$upcoming[] = self::public_incident( $i );
			} else {
				$active[] = self::public_incident( $i );
			}
		}
		return [
			'name'      => self::name(),
			'url'       => self::url(),
			'status'    => self::indicator( $open ),
			'active'    => $active,
			'upcoming'  => array_reverse( $upcoming ),
			'history'   => array_map( function ( $i ) { return self::public_incident( $i, true ); }, $history['rows'] ),
			'page'      => $history['page'],
			'pages'     => $history['pages'],
			'total'     => $history['total'],
			'feed_url'  => self::url( 'feed' ),
			'since'     => self::first_year(),
		];
	}

	public static function first_year() {
		global $wpdb;
		$t = self::table( 'incidents' );
		$d = $wpdb->get_var( "SELECT MIN(started_at) FROM $t" );
		return $d ? (int) substr( $d, 0, 4 ) : (int) gmdate( 'Y' );
	}

	/** Operator list: open first, then the latest closed ones. */
	public static function listing( $limit = 50, $offset = 0, $search = '' ) {
		global $wpdb;
		$t      = self::table( 'incidents' );
		$u      = self::table( 'updates' );
		$where  = '1=1';
		$args   = [];
		if ( $search !== '' ) {
			$where .= ' AND title LIKE %s';
			$args[] = '%' . $wpdb->esc_like( $search ) . '%';
		}
		$args[] = (int) $limit;
		$args[] = (int) $offset;
		$rows   = $wpdb->get_results( $wpdb->prepare(
			"SELECT i.*, (SELECT COUNT(*) FROM $u WHERE $u.status_incident_id = i.status_incident_id) AS update_count
			 FROM $t i WHERE $where
			 ORDER BY (i.status IN ('resolved','completed')) ASC, i.started_at DESC, i.status_incident_id DESC
			 LIMIT %d OFFSET %d",
			$args
		) );
		$total = (int) $wpdb->get_var( $search !== '' ? $wpdb->prepare( "SELECT COUNT(*) FROM $t WHERE title LIKE %s", '%' . $wpdb->esc_like( $search ) . '%' ) : "SELECT COUNT(*) FROM $t" );
		return [
			'incidents'   => array_map( [ __CLASS__, 'operator_incident' ], $rows ),
			'total'       => $total,
			'subscribers' => self::subscriber_count(),
			'page_url'    => self::url(),
		];
	}

	/** One incident for the operator panel: everything, plus send progress. */
	public static function operator_incident( $i, $with_updates = false ) {
		$out = [
			'id'                      => (int) $i->status_incident_id,
			'source_id'               => $i->source_id,
			'kind'                    => $i->kind,
			'title'                   => $i->title,
			'status'                  => $i->status,
			'impact'                  => $i->impact,
			'started_at'              => self::iso( $i->started_at ),
			'resolved_at'             => self::iso( $i->resolved_at ),
			'scheduled_for'           => self::iso( $i->scheduled_for ),
			'scheduled_until'         => self::iso( $i->scheduled_until ),
			'postmortem'              => (string) $i->postmortem,
			'postmortem_published_at' => self::iso( $i->postmortem_published_at ),
			'postmortem_notify'       => $i->postmortem_notify,
			'postmortem_notified'     => (int) $i->postmortem_notified,
			'update_count'            => isset( $i->update_count ) ? (int) $i->update_count : null,
			'url'                     => self::incident_url( $i ),
		];
		if ( $with_updates ) {
			$out['updates'] = array_map( function ( $u ) {
				$author = $u->created_by ? get_userdata( $u->created_by ) : null;
				return [
					'id'         => (int) $u->status_update_id,
					'status'     => $u->status,
					'body'       => (string) $u->body,
					'body_html'  => self::html( $u->body ),
					'notify'     => $u->notify,
					'notified'   => (int) $u->notified,
					'author'     => $author ? $author->display_name : ( $u->source_id ? 'Statuspage' : '' ),
					'created_at' => self::iso( $u->created_at ),
				];
			}, self::updates( $i->status_incident_id ) );
			$out['subscribers'] = self::subscriber_count();
		}
		return $out;
	}

	// ── writes ──────────────────────────────────────────────────────────

	private static function clean_status( $status, $kind ) {
		$allowed = $kind === 'maintenance' ? self::MAINTENANCE_STATUSES : self::INCIDENT_STATUSES;
		return in_array( $status, $allowed, true ) ? $status : $allowed[0];
	}

	private static function clean_gmt( $value ) {
		if ( $value === null || $value === '' ) {
			return null;
		}
		$t = is_numeric( $value ) ? (int) $value : strtotime( (string) $value );
		return $t ? gmdate( 'Y-m-d H:i:s', $t ) : null;
	}

	/**
	 * Open an incident (or schedule maintenance) with its first update.
	 *
	 * @param array $args title, kind, impact, status, body, notify, started_at, scheduled_for, scheduled_until
	 * @return object|\WP_Error the incident row
	 */
	public static function create( $args, $user_id = 0 ) {
		$kind  = ( $args['kind'] ?? '' ) === 'maintenance' ? 'maintenance' : 'incident';
		$title = trim( wp_strip_all_tags( (string) ( $args['title'] ?? '' ) ) );
		$body  = trim( (string) ( $args['body'] ?? '' ) );
		if ( $title === '' ) {
			return new \WP_Error( 'status_title', 'A title is required.' );
		}
		if ( $body === '' ) {
			return new \WP_Error( 'status_body', 'Write the first update.' );
		}
		$impact = $kind === 'maintenance' ? 'maintenance' : ( in_array( $args['impact'] ?? '', self::IMPACTS, true ) ? $args['impact'] : 'minor' );
		$status = self::clean_status( $args['status'] ?? '', $kind );
		$now    = self::now();
		$data   = [
			'kind'            => $kind,
			'title'           => mb_substr( $title, 0, 255 ),
			'status'          => $status,
			'impact'          => $impact,
			'started_at'      => self::clean_gmt( $args['started_at'] ?? '' ) ?: $now,
			'scheduled_for'   => $kind === 'maintenance' ? self::clean_gmt( $args['scheduled_for'] ?? '' ) : null,
			'scheduled_until' => $kind === 'maintenance' ? self::clean_gmt( $args['scheduled_until'] ?? '' ) : null,
			'resolved_at'     => self::is_closed( $status ) ? $now : null,
			'created_by'      => (int) $user_id,
			'created_at'      => $now,
			'updated_at'      => $now,
		];
		if ( $kind === 'maintenance' && $data['scheduled_for'] ) {
			$data['started_at'] = $data['scheduled_for'];
		}
		$id = StatusIncidents::insert( $data );
		if ( ! $id ) {
			return new \WP_Error( 'status_insert', 'Could not save the incident.' );
		}
		self::insert_update( $id, $status, $body, ! empty( $args['notify'] ), $user_id );
		return self::get( $id );
	}

	private static function insert_update( $incident_id, $status, $body, $notify, $user_id ) {
		$now = self::now();
		$id  = StatusUpdates::insert( [
			'status_incident_id' => (int) $incident_id,
			'status'             => $status,
			'body'               => $body,
			'notify'             => $notify ? 'pending' : '',
			'created_by'         => (int) $user_id,
			'created_at'         => $now,
			'updated_at'         => $now,
		] );
		if ( $notify ) {
			self::kick();
		}
		return $id;
	}

	/** Post an update; moves the incident to the update's status. */
	public static function add_update( $incident_id, $args, $user_id = 0 ) {
		$i = self::get( $incident_id );
		if ( ! $i ) {
			return new \WP_Error( 'status_missing', 'No such incident.' );
		}
		$body = trim( (string) ( $args['body'] ?? '' ) );
		if ( $body === '' ) {
			return new \WP_Error( 'status_body', 'Write the update.' );
		}
		$status = self::clean_status( $args['status'] ?? $i->status, $i->kind );
		$now    = self::now();
		$data   = [ 'status' => $status, 'updated_at' => $now ];
		if ( self::is_closed( $status ) && ! self::is_closed( $i->status ) ) {
			$data['resolved_at'] = $now;
		} elseif ( ! self::is_closed( $status ) ) {
			$data['resolved_at'] = null;
		}
		if ( $i->kind === 'maintenance' && $status === 'in_progress' && $i->status === 'scheduled' ) {
			$data['started_at'] = $now;
		}
		if ( $i->kind !== 'maintenance' && in_array( $args['impact'] ?? '', self::IMPACTS, true ) && $args['impact'] !== 'maintenance' ) {
			$data['impact'] = $args['impact'];
		}
		StatusIncidents::update( $data, [ 'status_incident_id' => $i->status_incident_id ] );
		self::insert_update( $i->status_incident_id, $status, $body, ! empty( $args['notify'] ), $user_id );
		return self::get( $i->status_incident_id );
	}

	/** Edit an incident's title, impact or times (no email). */
	public static function edit( $incident_id, $args ) {
		$i = self::get( $incident_id );
		if ( ! $i ) {
			return new \WP_Error( 'status_missing', 'No such incident.' );
		}
		$data = [];
		if ( isset( $args['title'] ) && trim( (string) $args['title'] ) !== '' ) {
			$data['title'] = mb_substr( trim( wp_strip_all_tags( (string) $args['title'] ) ), 0, 255 );
		}
		if ( isset( $args['impact'] ) && in_array( $args['impact'], self::IMPACTS, true ) && $i->kind !== 'maintenance' ) {
			$data['impact'] = $args['impact'];
		}
		foreach ( [ 'started_at', 'resolved_at', 'scheduled_for', 'scheduled_until' ] as $k ) {
			if ( array_key_exists( $k, $args ) ) {
				$data[ $k ] = self::clean_gmt( $args[ $k ] );
				if ( $k === 'started_at' && ! $data[ $k ] ) {
					unset( $data[ $k ] );
				}
			}
		}
		if ( ! $data ) {
			return $i;
		}
		$data['updated_at'] = self::now();
		StatusIncidents::update( $data, [ 'status_incident_id' => $i->status_incident_id ] );
		return self::get( $i->status_incident_id );
	}

	/** Correct an update's text (no email). */
	public static function edit_update( $update_id, $body ) {
		$body = trim( (string) $body );
		if ( $body === '' ) {
			return new \WP_Error( 'status_body', 'An update needs text.' );
		}
		return StatusUpdates::update( [ 'body' => $body, 'updated_at' => self::now() ], [ 'status_update_id' => (int) $update_id ] ) !== false;
	}

	public static function delete_update( $update_id ) {
		return (bool) StatusUpdates::delete( (int) $update_id );
	}

	public static function delete_incident( $incident_id ) {
		global $wpdb;
		$wpdb->delete( self::table( 'updates' ), [ 'status_incident_id' => (int) $incident_id ] );
		return (bool) StatusIncidents::delete( (int) $incident_id );
	}

	/**
	 * Save the postmortem. $publish shows it on the page (stamps the time the
	 * first time); $notify emails subscribers (only once per incident).
	 */
	public static function save_postmortem( $incident_id, $body, $publish = false, $notify = false ) {
		$i = self::get( $incident_id );
		if ( ! $i ) {
			return new \WP_Error( 'status_missing', 'No such incident.' );
		}
		$body = trim( (string) $body );
		$data = [ 'postmortem' => $body, 'updated_at' => self::now() ];
		if ( $publish && $body !== '' && ! $i->postmortem_published_at ) {
			$data['postmortem_published_at'] = self::now();
		}
		if ( ! $publish ) {
			$data['postmortem_published_at'] = null;
		}
		if ( $publish && $notify && $body !== '' && $i->postmortem_notify === '' ) {
			$data['postmortem_notify'] = 'pending';
		}
		StatusIncidents::update( $data, [ 'status_incident_id' => $i->status_incident_id ] );
		if ( ( $data['postmortem_notify'] ?? '' ) === 'pending' ) {
			self::kick();
		}
		return self::get( $i->status_incident_id );
	}

	// ── subscribers ─────────────────────────────────────────────────────

	public static function subscriber_count() {
		global $wpdb;
		$t = self::table( 'subscribers' );
		return (int) $wpdb->get_var( "SELECT COUNT(*) FROM $t WHERE status = 'active'" );
	}

	private static function subscriber_by_email( $email ) {
		global $wpdb;
		$t = self::table( 'subscribers' );
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $t WHERE email = %s", strtolower( trim( (string) $email ) ) ) );
	}

	private static function subscriber_by_user( $user_id ) {
		global $wpdb;
		$t = self::table( 'subscribers' );
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $t WHERE user_id = %d ORDER BY status_subscriber_id ASC LIMIT 1", (int) $user_id ) );
	}

	public static function is_subscribed( $user_id ) {
		$row = self::subscriber_by_user( $user_id );
		if ( ! $row ) {
			$user = get_userdata( $user_id );
			$row  = $user ? self::subscriber_by_email( $user->user_email ) : null;
		}
		return $row && $row->status === 'active';
	}

	/**
	 * Turn status alerts on or off for an address. With a user, the row is
	 * found by user first, then by the user's email (an imported row gets
	 * linked), so a login never ends up with two rows.
	 */
	public static function set_subscription( $email, $on, $user_id = 0, $source = '' ) {
		$email = strtolower( trim( (string) $email ) );
		if ( ! is_email( $email ) ) {
			return new \WP_Error( 'status_email', 'Not a valid email address.' );
		}
		$row = $user_id ? self::subscriber_by_user( $user_id ) : null;
		$row = $row ?: self::subscriber_by_email( $email );
		$now = self::now();
		if ( ! $row ) {
			if ( ! $on ) {
				return true;
			}
			StatusSubscribers::insert( [
				'email'      => $email,
				'user_id'    => (int) $user_id,
				'status'     => 'active',
				'source'     => $source,
				'token'      => wp_generate_password( 32, false ),
				'created_at' => $now,
				'updated_at' => $now,
			] );
			return true;
		}
		$data = [ 'status' => $on ? 'active' : 'unsubscribed', 'updated_at' => $now ];
		if ( $user_id && ! $row->user_id ) {
			$data['user_id'] = (int) $user_id;
		}
		if ( $row->email !== $email && ! self::subscriber_by_email( $email ) ) {
			$data['email'] = $email;
		}
		StatusSubscribers::update( $data, [ 'status_subscriber_id' => $row->status_subscriber_id ] );
		return true;
	}

	/** Where a subscriber's mail goes: a login's current address, else the stored one. */
	public static function subscriber_email( $row ) {
		if ( $row->user_id ) {
			$user = get_userdata( $row->user_id );
			if ( $user && is_email( $user->user_email ) ) {
				return $user->user_email;
			}
		}
		return $row->email;
	}

	public static function unsubscribe_url( $row ) {
		return add_query_arg( [ 's' => (int) $row->status_subscriber_id, 't' => $row->token ], self::url( 'unsubscribe' ) );
	}

	/** Check an unsubscribe link; returns the row or null. */
	public static function subscriber_from_link( $id, $token ) {
		$row = StatusSubscribers::get( (int) $id );
		if ( ! $row || ! is_string( $token ) || $token === '' || ! hash_equals( (string) $row->token, $token ) ) {
			return null;
		}
		return $row;
	}

	public static function unsubscribe_link( $id, $token, $on = false ) {
		$row = self::subscriber_from_link( $id, $token );
		if ( ! $row ) {
			return null;
		}
		StatusSubscribers::update( [ 'status' => $on ? 'active' : 'unsubscribed', 'updated_at' => self::now() ], [ 'status_subscriber_id' => $row->status_subscriber_id ] );
		return StatusSubscribers::get( $row->status_subscriber_id );
	}

	/** Operator view of the list (emails are shown only to operators). */
	public static function subscribers() {
		global $wpdb;
		$t    = self::table( 'subscribers' );
		$rows = $wpdb->get_results( "SELECT * FROM $t ORDER BY status ASC, created_at DESC" );
		return array_map( function ( $r ) {
			return [
				'id'         => (int) $r->status_subscriber_id,
				'email'      => self::subscriber_email( $r ),
				'user_id'    => (int) $r->user_id,
				'status'     => $r->status,
				'source'     => $r->source,
				'created_at' => self::iso( $r->created_at ),
			];
		}, $rows );
	}

	// ── email ───────────────────────────────────────────────────────────

	/**
	 * Start sending now: after the response is flushed when the server can,
	 * else on the next cron run (every minute).
	 */
	public static function kick() {
		static $registered = false;
		if ( $registered ) {
			return;
		}
		$registered = true;
		register_shutdown_function( function () {
			if ( function_exists( 'fastcgi_finish_request' ) ) {
				fastcgi_finish_request();
			} elseif ( ! defined( 'WP_CLI' ) ) {
				return; // Without fastcgi the browser would wait on every email; cron sends it.
			}
			self::run_due( 120 );
		} );
	}

	/**
	 * Send what is pending. Returns emails sent. A site-option lock keeps two
	 * runs (cron + shutdown) from sending the same update twice.
	 */
	public static function run_due( $budget = 40 ) {
		global $wpdb;
		$lock = 'captaincore_status_notify_lock';
		$held = (int) get_site_option( $lock, 0 );
		if ( $held && $held > time() - 300 ) {
			return 0;
		}
		update_site_option( $lock, time() );
		$start = time();
		$sent  = 0;
		try {
			ignore_user_abort( true );
			if ( function_exists( 'set_time_limit' ) ) {
				@set_time_limit( $budget + 60 );
			}
			$u    = self::table( 'updates' );
			$t    = self::table( 'incidents' );
			$jobs = [];
			foreach ( $wpdb->get_results( "SELECT status_update_id FROM $u WHERE notify IN ('pending','sending') ORDER BY status_update_id ASC" ) as $r ) {
				$jobs[] = [ 'update', (int) $r->status_update_id ];
			}
			foreach ( $wpdb->get_results( "SELECT status_incident_id FROM $t WHERE postmortem_notify IN ('pending','sending') ORDER BY status_incident_id ASC" ) as $r ) {
				$jobs[] = [ 'postmortem', (int) $r->status_incident_id ];
			}
			foreach ( $jobs as $job ) {
				$sent += self::send_job( $job[0], $job[1], $start + $budget );
				if ( time() >= $start + $budget ) {
					break;
				}
				update_site_option( $lock, time() );
			}
		} finally {
			delete_site_option( $lock );
		}
		return $sent;
	}

	private static function send_job( $type, $id, $deadline ) {
		global $wpdb;
		$s = self::table( 'subscribers' );
		if ( $type === 'update' ) {
			$row = StatusUpdates::get( $id );
			if ( ! $row ) {
				return 0;
			}
			$incident = self::get( $row->status_incident_id );
			$cursor   = (int) $row->notify_cursor;
			$count    = (int) $row->notified;
			$table    = self::table( 'updates' );
			$where    = [ 'status_update_id' => $id ];
			$cols     = [ 'notify', 'notify_cursor', 'notified' ];
		} else {
			$incident = self::get( $id );
			$row      = $incident;
			if ( ! $incident ) {
				return 0;
			}
			$cursor   = (int) $incident->postmortem_notify_cursor;
			$count    = (int) $incident->postmortem_notified;
			$table    = self::table( 'incidents' );
			$where    = [ 'status_incident_id' => $id ];
			$cols     = [ 'postmortem_notify', 'postmortem_notify_cursor', 'postmortem_notified' ];
		}
		if ( ! $incident ) {
			$wpdb->update( $table, [ $cols[0] => 'sent' ], $where );
			return 0;
		}
		$wpdb->update( $table, [ $cols[0] => 'sending' ], $where );
		$sent = 0;
		while ( time() < $deadline ) {
			$subs = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $s WHERE status = 'active' AND status_subscriber_id > %d ORDER BY status_subscriber_id ASC LIMIT 50", $cursor ) );
			if ( ! $subs ) {
				$wpdb->update( $table, [ $cols[0] => 'sent' ], $where );
				return $sent;
			}
			foreach ( $subs as $sub ) {
				$to = self::subscriber_email( $sub );
				if ( is_email( $to ) ) {
					if ( $type === 'update' ) {
						Mailer::send_status_update( $to, $incident, $row, self::unsubscribe_url( $sub ) );
					} else {
						Mailer::send_status_postmortem( $to, $incident, self::unsubscribe_url( $sub ) );
					}
					$count++;
					$sent++;
				}
				$cursor = (int) $sub->status_subscriber_id;
				$wpdb->update( $table, [ $cols[1] => $cursor, $cols[2] => $count ], $where );
				if ( time() >= $deadline ) {
					return $sent;
				}
			}
		}
		return $sent;
	}

	// ── import ──────────────────────────────────────────────────────────

	/**
	 * Every incident on a Statuspage.io page, newest first, read from its
	 * public JSON: history.json (three months a page) for the ids, then
	 * incidents/<id>.json for the updates and postmortem.
	 *
	 * @return array|\WP_Error
	 */
	public static function fetch_statuspage( $base, $log = null ) {
		$base  = untrailingslashit( esc_url_raw( $base ) );
		$get   = function ( $url ) {
			$res = wp_remote_get( $url, [ 'timeout' => 30, 'headers' => [ 'Accept' => 'application/json', 'User-Agent' => 'Mozilla/5.0 (CaptainCore status import)' ] ] );
			if ( is_wp_error( $res ) || wp_remote_retrieve_response_code( $res ) !== 200 ) {
				return null;
			}
			return json_decode( wp_remote_retrieve_body( $res ), true );
		};
		$codes = [];
		$first = null;
		for ( $page = 1; $page <= 400; $page++ ) {
			$d = $get( "$base/history.json?page=$page" );
			if ( ! is_array( $d ) || empty( $d['months'] ) ) {
				if ( $page === 1 ) {
					return new \WP_Error( 'status_fetch', "Could not read $base/history.json" );
				}
				break;
			}
			if ( $first === null ) {
				$first = substr( (string) ( $d['page_status']['page']['created_at'] ?? '2000' ), 0, 7 );
			}
			foreach ( $d['months'] as $m ) {
				foreach ( (array) $m['incidents'] as $i ) {
					$codes[ $i['code'] ] = true;
				}
			}
			if ( (string) $d['start_time'] < $first ) {
				break;
			}
		}
		$out = [];
		foreach ( array_keys( $codes ) as $n => $code ) {
			$i = $get( "$base/incidents/$code.json" );
			if ( is_array( $i ) && ! empty( $i['id'] ) ) {
				$out[] = $i;
			}
			if ( $log && ( $n + 1 ) % 20 === 0 ) {
				call_user_func( $log, ( $n + 1 ) . ' of ' . count( $codes ) . ' incidents read' );
			}
		}
		return $out;
	}

	/**
	 * Import Statuspage incidents (incidents/<id>.json shape). Idempotent:
	 * rows are matched on source_id and refreshed, never duplicated. Never
	 * emails anyone.
	 *
	 * @return array{created:int, updated:int, updates:int, postmortems:int}
	 */
	public static function import_statuspage( $incidents ) {
		global $wpdb;
		$gmt    = function ( $v ) { return $v ? gmdate( 'Y-m-d H:i:s', strtotime( $v ) ) : null; };
		$counts = [ 'created' => 0, 'updated' => 0, 'updates' => 0, 'postmortems' => 0 ];
		foreach ( $incidents as $i ) {
			$i           = (array) $i;
			$maintenance = ! empty( $i['scheduled_for'] ) || ( $i['impact'] ?? '' ) === 'maintenance';
			$kind        = $maintenance ? 'maintenance' : 'incident';
			$status      = (string) ( $i['status'] ?? 'resolved' );
			if ( $status === 'postmortem' ) {
				$status = 'resolved';
			}
			$status = self::clean_status( $status, $kind );
			$impact = $maintenance ? 'maintenance' : ( in_array( $i['impact'] ?? '', self::IMPACTS, true ) ? $i['impact'] : 'none' );
			$pm     = trim( (string) ( $i['postmortem_body'] ?? '' ) );
			$data   = [
				'source_id'               => (string) $i['id'],
				'kind'                    => $kind,
				'title'                   => mb_substr( (string) ( $i['name'] ?? '' ), 0, 255 ),
				'status'                  => $status,
				'impact'                  => $impact,
				'started_at'              => $gmt( $i['started_at'] ?? $i['created_at'] ),
				'resolved_at'             => $gmt( $i['resolved_at'] ?? null ),
				'scheduled_for'           => $gmt( $i['scheduled_for'] ?? null ),
				'scheduled_until'         => $gmt( $i['scheduled_until'] ?? null ),
				'postmortem'              => $pm,
				'postmortem_published_at' => $pm !== '' ? $gmt( $i['postmortem_published_at'] ?? $i['postmortem_body_last_updated_at'] ?? $i['updated_at'] ) : null,
				// Statuspage already emailed these; mark them sent so nothing re-sends.
				'postmortem_notify'       => $pm !== '' ? 'sent' : '',
				'created_at'              => $gmt( $i['created_at'] ),
				'updated_at'              => $gmt( $i['updated_at'] ?? $i['created_at'] ),
			];
			if ( $kind === 'maintenance' && self::is_closed( $status ) && ! $data['resolved_at'] ) {
				$data['resolved_at'] = $data['scheduled_until'] ?: $data['updated_at'];
			}
			$existing = self::get_by_source( $data['source_id'] );
			if ( $existing ) {
				StatusIncidents::update( $data, [ 'status_incident_id' => $existing->status_incident_id ] );
				$incident_id = (int) $existing->status_incident_id;
				$counts['updated']++;
			} else {
				$incident_id = (int) StatusIncidents::insert( $data );
				$counts['created']++;
			}
			if ( $pm !== '' ) {
				$counts['postmortems']++;
			}
			foreach ( (array) ( $i['incident_updates'] ?? [] ) as $u ) {
				$u   = (array) $u;
				$row = [
					'status_incident_id' => $incident_id,
					'source_id'          => (string) $u['id'],
					'status'             => self::clean_status( (string) $u['status'], $kind ),
					'body'               => (string) ( $u['body'] ?? '' ),
					'notify'             => '',
					'created_at'         => $gmt( $u['display_at'] ?? $u['created_at'] ),
					'updated_at'         => $gmt( $u['updated_at'] ?? $u['created_at'] ),
				];
				$have = $wpdb->get_var( $wpdb->prepare( 'SELECT status_update_id FROM ' . self::table( 'updates' ) . ' WHERE source_id = %s', $row['source_id'] ) );
				if ( $have ) {
					StatusUpdates::update( $row, [ 'status_update_id' => $have ] );
				} else {
					StatusUpdates::insert( $row );
				}
				$counts['updates']++;
			}
		}
		return $counts;
	}

	/**
	 * Import a Statuspage subscriber export (CSV: Email, State, Created At).
	 * Active rows are subscribed (linked to a login with that email when one
	 * exists). Addresses someone already unsubscribed here are left alone.
	 *
	 * @return array{subscribed:int, linked:int, skipped:int, kept_unsubscribed:int}
	 */
	public static function import_subscribers( $csv_path, $dry_run = false ) {
		$counts = [ 'subscribed' => 0, 'linked' => 0, 'skipped' => 0, 'kept_unsubscribed' => 0 ];
		$fh     = fopen( $csv_path, 'r' );
		if ( ! $fh ) {
			return new \WP_Error( 'status_csv', "Cannot read $csv_path" );
		}
		$head = fgetcsv( $fh, 0, ',', '"', '\\' );
		$cols = array_flip( array_map( 'strtolower', array_map( 'trim', (array) $head ) ) );
		if ( ! isset( $cols['email'] ) ) {
			return new \WP_Error( 'status_csv', 'The CSV has no Email column.' );
		}
		while ( ( $r = fgetcsv( $fh, 0, ',', '"', '\\' ) ) !== false ) {
			$email = strtolower( trim( (string) ( $r[ $cols['email'] ] ?? '' ) ) );
			$state = isset( $cols['state'] ) ? strtolower( trim( (string) ( $r[ $cols['state'] ] ?? '' ) ) ) : 'active';
			if ( ! is_email( $email ) || ( $state !== '' && $state !== 'active' ) ) {
				$counts['skipped']++;
				continue;
			}
			$existing = self::subscriber_by_email( $email );
			if ( $existing && $existing->status === 'unsubscribed' ) {
				$counts['kept_unsubscribed']++;
				continue;
			}
			$user = get_user_by( 'email', $email );
			if ( $user ) {
				$counts['linked']++;
			}
			if ( ! $dry_run ) {
				self::set_subscription( $email, true, $user ? $user->ID : 0, 'statuspage' );
			}
			$counts['subscribed']++;
		}
		fclose( $fh );
		return $counts;
	}

	// ── public page ─────────────────────────────────────────────────────

	public static function init() {
		add_action( 'init', [ __CLASS__, 'rewrite' ] );
		add_filter( 'query_vars', function ( $vars ) {
			$vars[] = 'captaincore_status';
			$vars[] = 'captaincore_status_arg';
			return $vars;
		} );
		add_filter( 'template_include', [ __CLASS__, 'template' ], 20 );
		add_filter( 'redirect_canonical', function ( $url ) {
			return get_query_var( 'captaincore_status' ) ? false : $url;
		} );
		add_action( 'captaincore_status_notify', [ __CLASS__, 'run_due' ] );
		add_action( 'init', function () {
			if ( ! wp_next_scheduled( 'captaincore_status_notify' ) ) {
				wp_schedule_event( time() + 60, 'captaincore_minute', 'captaincore_status_notify' );
			}
		} );
	}

	public static function rewrite() {
		$p = preg_quote( self::path(), '#' );
		add_rewrite_rule( "^$p/?$", 'index.php?captaincore_status=page', 'top' );
		add_rewrite_rule( "^$p/page/([0-9]+)/?$", 'index.php?captaincore_status=page&captaincore_status_arg=$matches[1]', 'top' );
		add_rewrite_rule( "^$p/incidents/([a-z0-9]+)/?$", 'index.php?captaincore_status=incident&captaincore_status_arg=$matches[1]', 'top' );
		add_rewrite_rule( "^$p/feed/?$", 'index.php?captaincore_status=feed', 'top' );
		add_rewrite_rule( "^$p/unsubscribe/?$", 'index.php?captaincore_status=unsubscribe', 'top' );
		$version = self::REWRITE_VERSION . ':' . self::path();
		if ( get_option( 'captaincore_status_rewrite' ) !== $version ) {
			flush_rewrite_rules( false );
			update_option( 'captaincore_status_rewrite', $version );
		}
	}

	/**
	 * Hand the request to the status template. The theme can provide
	 * captaincore/status.php (it receives $captaincore_status, see
	 * view()); otherwise the plugin's own templates/status.php renders.
	 */
	public static function template( $template ) {
		$route = get_query_var( 'captaincore_status' );
		if ( ! $route ) {
			return $template;
		}
		if ( $route === 'feed' ) {
			self::atom();
			exit;
		}
		$GLOBALS['captaincore_status'] = self::view( $route, (string) get_query_var( 'captaincore_status_arg' ) );
		if ( ! empty( $GLOBALS['captaincore_status']['redirect'] ) ) {
			wp_safe_redirect( $GLOBALS['captaincore_status']['redirect'], 301 );
			exit;
		}
		status_header( $GLOBALS['captaincore_status']['code'] ?? 200 );
		global $wp_query;
		$wp_query->is_404  = false;
		$wp_query->is_home = false;
		$theme = locate_template( 'captaincore/status.php' );
		return $theme ?: dirname( __DIR__ ) . '/templates/status.php';
	}

	/**
	 * What a status template renders: ['view' => page|incident|unsubscribe, …].
	 * page: public_feed(). incident: ['incident' => public_incident()].
	 * unsubscribe: ['done' => bool, 'valid' => bool, 'email' => masked].
	 */
	public static function view( $route, $arg = '' ) {
		$base = [ 'name' => self::name(), 'url' => self::url(), 'feed_url' => self::url( 'feed' ) ];
		if ( $route === 'incident' ) {
			$i = ctype_digit( $arg ) ? self::get( (int) $arg ) : self::get_by_source( $arg );
			if ( $i && ! ctype_digit( $arg ) ) {
				return [ 'redirect' => self::incident_url( $i ) ];
			}
			if ( ! $i ) {
				return $base + [ 'view' => 'missing', 'code' => 404, 'status' => self::indicator() ];
			}
			return $base + [ 'view' => 'incident', 'incident' => self::public_incident( $i ), 'status' => self::indicator() ];
		}
		if ( $route === 'unsubscribe' ) {
			$id    = (int) ( $_REQUEST['s'] ?? 0 );
			$token = (string) ( $_REQUEST['t'] ?? '' );
			$row   = self::subscriber_from_link( $id, $token );
			$done  = false;
			if ( $row && $_SERVER['REQUEST_METHOD'] === 'POST' ) {
				// One-click (RFC 8058) posts List-Unsubscribe=One-Click; the page's own button posts confirm.
				$resub = ( $_POST['resubscribe'] ?? '' ) === '1';
				$row   = self::unsubscribe_link( $id, $token, $resub );
				$done  = true;
				if ( isset( $_POST['List-Unsubscribe'] ) ) {
					status_header( 200 );
					echo 'Unsubscribed';
					exit;
				}
			}
			return $base + [
				'view'   => 'unsubscribe',
				'valid'  => (bool) $row,
				'done'   => $done,
				'active' => $row ? $row->status === 'active' : false,
				'email'  => $row ? self::mask( self::subscriber_email( $row ) ) : '',
				'action' => $row ? self::unsubscribe_url( $row ) : '',
				'status' => self::indicator(),
			];
		}
		return $base + [ 'view' => 'page' ] + self::public_feed( max( 1, (int) $arg ) );
	}

	private static function mask( $email ) {
		[ $user, $domain ] = array_pad( explode( '@', (string) $email, 2 ), 2, '' );
		return mb_substr( $user, 0, 2 ) . str_repeat( '•', max( 1, mb_strlen( $user ) - 2 ) ) . '@' . $domain;
	}

	/** Atom feed of the latest 25 incidents (each update is in the entry body). */
	public static function atom() {
		global $wpdb;
		$t    = self::table( 'incidents' );
		$rows = $wpdb->get_results( "SELECT * FROM $t ORDER BY started_at DESC LIMIT 25" );
		header( 'Content-Type: application/atom+xml; charset=UTF-8' );
		$esc     = function ( $s ) { return htmlspecialchars( (string) $s, ENT_XML1 | ENT_QUOTES, 'UTF-8' ); };
		$updated = $rows ? gmdate( 'c', self::ts( $rows[0]->updated_at ) ) : gmdate( 'c' );
		echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
		echo '<feed xmlns="http://www.w3.org/2005/Atom">';
		echo '<id>' . $esc( self::url() ) . '</id><title>' . $esc( self::name() . ' status' ) . '</title><updated>' . $updated . '</updated>';
		echo '<link rel="alternate" type="text/html" href="' . $esc( self::url() ) . '"/><link rel="self" href="' . $esc( self::url( 'feed' ) ) . '"/>';
		foreach ( $rows as $i ) {
			$p    = self::public_incident( $i );
			$html = $p['postmortem_html'] ? '<h3>Postmortem</h3>' . $p['postmortem_html'] : '';
			foreach ( $p['updates'] as $u ) {
				$html .= '<p><strong>' . $esc( $u['status_label'] ) . '</strong> · ' . $esc( $u['created_local'] ) . '</p>' . $u['body_html'];
			}
			echo '<entry><id>' . $esc( $p['url'] ) . '</id><title>' . $esc( $p['title'] ) . '</title>';
			echo '<published>' . gmdate( 'c', self::ts( $i->started_at ) ) . '</published><updated>' . gmdate( 'c', self::ts( $i->updated_at ) ) . '</updated>';
			echo '<link rel="alternate" type="text/html" href="' . $esc( $p['url'] ) . '"/>';
			echo '<content type="html">' . $esc( $html ) . '</content></entry>';
		}
		echo '</feed>';
	}

}
