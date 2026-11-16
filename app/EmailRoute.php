<?php

namespace CaptainCore;

/**
 * How a site sends email, from the `email` probe in fetch-site-data (stored
 * on the environment's details by sync-data). classify() turns the probe into
 * a route: through our Mailgun account (status anchor), someone else's
 * Mailgun, another provider, the server's PHP mail, or a mailer that was
 * never set up. "Ours" is a sending domain in our Mailgun account (zones(),
 * cached daily), not a guess from the host name. The password check compares
 * the site's 16-hex sha256 of its Mailgun secret with the one the Manager
 * stored on the domain when it last rotated it.
 *
 * Signals: a production site that leaves our Mailgun records an
 * `email-left-anchor` event (priority, for the agent's attention list); any
 * other change of route records `email-changed`. Conditions (stale password,
 * test mode, mail switched off, failing sends, our zone left unused) are synced as `email-state` and clear on their own.
 */
class EmailRoute {

	const ZONES_OPTION = 'captaincore_mailgun_domains';

	const PROVIDERS = [
		'mailgun' => 'Mailgun', 'sendgrid' => 'SendGrid', 'postmark' => 'Postmark', 'ses' => 'Amazon SES', 'google' => 'Google',
		'microsoft' => 'Microsoft 365', 'brevo' => 'Brevo', 'smtp2go' => 'SMTP2GO', 'sparkpost' => 'SparkPost', 'mailjet' => 'Mailjet',
		'zoho' => 'Zoho', 'elasticemail' => 'Elastic Email', 'resend' => 'Resend', 'mailersend' => 'MailerSend', 'mandrill' => 'Mandrill',
		'socketlabs' => 'SocketLabs', 'smtpcom' => 'SMTP.com', 'sendlayer' => 'SendLayer', 'godaddy' => 'GoDaddy', 'kinsta' => 'Kinsta',
		'site-mailer' => 'Site Mailer', 'netcore' => 'Netcore', 'smtp' => 'SMTP server', 'unknown' => 'Unknown service',
	];

	// Issues that put a production site on the attention list.
	const PRIORITY_ISSUES = [ 'stale-password', 'test-mode', 'mail-blocked' ];

	/**
	 * Sending domains in our Mailgun account => state (active, unverified).
	 * Cached in a site option, refreshed once a day; the old list is kept
	 * when Mailgun can't be reached.
	 */
	public static function zones( $refresh = false ) {
		static $memo = null;
		if ( $memo !== null && ! $refresh ) {
			return $memo;
		}
		$cached = get_site_option( self::ZONES_OPTION );
		$fresh  = is_array( $cached ) && ! empty( $cached['fetched_at'] ) && $cached['fetched_at'] > time() - DAY_IN_SECONDS;
		if ( ( $refresh || ! $fresh ) && ! get_transient( 'captaincore_mailgun_domains_lock' ) ) {
			set_transient( 'captaincore_mailgun_domains_lock', 1, 5 * MINUTE_IN_SECONDS );
			$list = self::fetch_zones();
			if ( $list !== null ) {
				$cached = [ 'fetched_at' => time(), 'domains' => $list ];
				update_site_option( self::ZONES_OPTION, $cached );
			}
		}
		$memo = is_array( $cached ) && is_array( $cached['domains'] ?? null ) ? $cached['domains'] : [];
		return $memo;
	}

	private static function fetch_zones() {
		if ( ! class_exists( '\CaptainCore\Remote\Mailgun' ) ) {
			return null;
		}
		$all  = [];
		$skip = 0;
		do {
			$r     = Remote\Mailgun::get( 'v4/domains', [ 'limit' => 1000, 'skip' => $skip ] );
			$items = is_object( $r ) && isset( $r->items ) && is_array( $r->items ) ? $r->items : null;
			if ( $items === null ) {
				return $skip ? $all : null;
			}
			foreach ( $items as $d ) {
				if ( ! empty( $d->name ) ) {
					$all[ strtolower( $d->name ) ] = (string) ( $d->state ?? '' );
				}
			}
			$skip += count( $items );
		} while ( count( $items ) === 1000 && $skip < 50000 );
		return $all;
	}

	/** Zone => 16-hex sha256 of the SMTP password the Manager last set there. */
	public static function passwords() {
		static $memo = null;
		if ( $memo !== null ) {
			return $memo;
		}
		global $wpdb;
		$memo = [];
		foreach ( (array) $wpdb->get_col( "SELECT details FROM {$wpdb->base_prefix}captaincore_domains WHERE details LIKE '%mailgun_smtp_password%'" ) as $d ) {
			$o = json_decode( (string) $d );
			if ( ! empty( $o->mailgun_zone ) && ! empty( $o->mailgun_smtp_password ) ) {
				$memo[ strtolower( $o->mailgun_zone ) ] = substr( hash( 'sha256', (string) $o->mailgun_smtp_password ), 0, 16 );
			}
		}
		return $memo;
	}

	/** The zone in our account that belongs to this address (mg.example.com for shop.example.com), or ''. */
	public static function zone_for( $home_url, $zones = null ) {
		$zones = $zones ?? self::zones();
		$host  = strtolower( preg_replace( '/^www\./i', '', (string) wp_parse_url( (string) $home_url, PHP_URL_HOST ) ) );
		if ( $host === '' ) {
			return '';
		}
		$parts = explode( '.', $host );
		for ( $i = 0; $i < count( $parts ) - 1; $i++ ) {
			$apex = implode( '.', array_slice( $parts, $i ) );
			foreach ( [ 'mg.', 'mail.', 'email.', 'mailgun.', 'm.' ] as $label ) {
				if ( isset( $zones[ $label . $apex ] ) ) {
					return $label . $apex;
				}
			}
		}
		return '';
	}

	/**
	 * The route for one environment's probe, as stored on its details
	 * (email_route) and shown on the site page and the Sites list.
	 *
	 * @param object|array|null $email    The probe (details->email).
	 * @param string            $home_url The environment's address.
	 */
	public static function classify( $email, $home_url = '' ) {
		$email = json_decode( wp_json_encode( $email ), true );
		if ( ! is_array( $email ) || empty( $email['v'] ) ) {
			return null;
		}
		$zones   = self::zones();
		$mailers = array_values( array_filter( (array) ( $email['mailers'] ?? [] ), 'is_array' ) );
		$eff     = null;
		foreach ( $mailers as $m ) {
			if ( ( $m['plugin'] ?? '' ) === ( $email['effective'] ?? '' ) && $email['effective'] !== '' ) {
				$eff = $m;
			}
		}
		$route = [
			'status' => 'php_mail', 'label' => 'Server default (PHP mail)', 'plugin' => '', 'plugin_name' => '', 'provider' => '',
			'domain' => '', 'from_email' => '', 'test_mode' => false, 'secret' => 'unknown', 'zone_state' => '',
			'zone' => self::zone_for( $home_url, $zones ), 'interceptors' => array_values( array_map( 'strval', (array) ( $email['mu'] ?? [] ) ) ),
			'blocker' => '', 'conflicts' => [], 'unconfigured' => [], 'log' => is_array( $email['log'] ?? null ) ? $email['log'] : null, 'issues' => [],
		];
		foreach ( $mailers as $m ) {
			if ( empty( $m['configured'] ) || ( $m['provider'] ?? '' ) === 'php_mail' ) {
				$route['unconfigured'][] = (string) ( $m['name'] ?? $m['plugin'] ?? '' );
			} elseif ( ! $eff || ( $m['plugin'] ?? '' ) !== ( $eff['plugin'] ?? '' ) ) {
				$route['conflicts'][] = (string) ( $m['name'] ?? $m['plugin'] ?? '' ) . ' (' . self::provider_name( (string) ( $m['provider'] ?? '' ) ) . ')';
			}
		}
		if ( $eff ) {
			$provider = (string) ( $eff['provider'] ?? '' );
			$domain   = strtolower( (string) ( $eff['domain'] ?? '' ) );
			$route    = array_merge( $route, [
				'plugin' => (string) $eff['plugin'], 'plugin_name' => (string) ( $eff['name'] ?? $eff['plugin'] ), 'provider' => $provider,
				'domain' => $domain, 'from_email' => (string) ( $eff['from_email'] ?? '' ), 'test_mode' => ! empty( $eff['test_mode'] ),
			] );
			if ( $provider === 'mailgun' && $domain !== '' && isset( $zones[ $domain ] ) ) {
				$route['status']     = 'anchor';
				$route['label']      = 'Anchor Mailgun';
				$route['zone_state'] = (string) $zones[ $domain ];
				$hash                = (string) ( $eff['secret_hash'] ?? '' );
				$stored              = self::passwords()[ $domain ] ?? '';
				// The stored password is the SMTP one; an API-key setup can't be compared.
				$smtp                = ( $eff['host'] ?? '' ) !== '' || ( $eff['connector'] ?? '' ) === 'generic' || ( $eff['mode'] ?? '' ) === 'smtp';
				if ( $stored !== '' && $hash !== '' && $smtp ) {
					$route['secret'] = hash_equals( $stored, $hash ) ? 'current' : 'stale';
				}
			} elseif ( $provider === 'mailgun' ) {
				$route['status'] = 'mailgun';
				$route['label']  = 'Mailgun (another account)';
			} else {
				$route['status'] = 'other';
				$route['label']  = self::provider_name( $provider );
			}
		} elseif ( $route['unconfigured'] ) {
			$route['status'] = 'unconfigured';
			$route['label']  = $route['unconfigured'][0] . ' not set up';
		}
		// Only what certainly stops mail (the probe's `blockers`). Other must-use
		// plugins that touch wp_mail are listed as interceptors: most only act
		// on a staging copy.
		$route['blocker'] = implode( ', ', array_map( 'strval', (array) ( $email['blockers'] ?? [] ) ) );

		$issue = function ( $key, $label ) use ( &$route ) { $route['issues'][] = [ 'key' => $key, 'label' => $label ]; };
		if ( $route['blocker'] !== '' ) {
			$issue( 'mail-blocked', 'Mail is switched off: ' . $route['blocker'] );
		}
		// Sends that fail now: the newest event in Gravity SMTP's log is a
		// failure, or this week has failures and nothing delivered.
		$log         = $route['log'];
		$last_sent   = is_array( $log ) ? (string) ( $log['last_sent'] ?? '' ) : '';
		$last_failed = is_array( $log ) ? (string) ( $log['last_failed'] ?? '' ) : '';
		$failing     = is_array( $log ) && ( ( $last_failed !== '' && $last_failed > $last_sent ) || ( (int) ( $log['failed'] ?? 0 ) > 0 && (int) ( $log['sent'] ?? 0 ) === 0 ) );
		// A password that differs from the one we stored only matters once
		// sending fails: Mailgun can still accept an older one.
		if ( $route['status'] === 'anchor' && $route['secret'] === 'stale' && $failing ) {
			$issue( 'stale-password', 'Mailgun password is not the current one and the last send failed' . ( $last_failed !== '' ? ' (' . $last_failed . ' UTC)' : '' ) );
		} elseif ( $failing && ! $route['test_mode'] ) {
			$issue( 'failing', 'The last send failed' . ( $last_failed !== '' ? ' (' . $last_failed . ' UTC)' : '' ) . ( $last_sent !== '' ? ', last delivered ' . $last_sent . ' UTC' : ', nothing delivered' ) );
		}
		if ( $route['test_mode'] ) {
			$issue( 'test-mode', 'Gravity SMTP is in test mode: nothing is delivered' );
		}
		if ( $route['status'] === 'anchor' && $route['zone_state'] !== '' && $route['zone_state'] !== 'active' ) {
			$issue( 'zone-unverified', 'Mailgun zone ' . $route['domain'] . ' is ' . $route['zone_state'] );
		}
		if ( $route['status'] !== 'anchor' && $route['zone'] !== '' ) {
			$issue( 'zone-unused', 'Our Mailgun zone ' . $route['zone'] . ' exists but the site sends ' . ( $route['status'] === 'php_mail' ? 'with PHP mail' : 'through ' . $route['label'] ) );
		}
		$route['issue_keys'] = array_column( $route['issues'], 'key' );
		return $route;
	}

	public static function provider_name( $provider ) {
		return self::PROVIDERS[ $provider ] ?? ( $provider === '' ? 'Unknown' : ucfirst( $provider ) );
	}

	/** One line for a change log and signal text: "Gravity SMTP → Anchor Mailgun (mg.example.com)". */
	public static function describe( $route ) {
		if ( ! is_array( $route ) ) {
			return 'Unknown';
		}
		$what = $route['label'] ?? 'Unknown';
		if ( ( $route['domain'] ?? '' ) !== '' ) {
			$what .= ' (' . $route['domain'] . ')';
		}
		if ( ( $route['plugin_name'] ?? '' ) !== '' ) {
			$what = $route['plugin_name'] . ' → ' . $what;
		}
		if ( ! empty( $route['test_mode'] ) ) {
			$what .= ', test mode';
		}
		if ( ( $route['blocker'] ?? '' ) !== '' ) {
			$what .= ', switched off by ' . $route['blocker'];
		}
		if ( ! empty( $route['interceptors'] ) ) {
			$what .= ', must-use ' . implode( ', ', (array) $route['interceptors'] );
		}
		return $what;
	}

	/** What counts as a change of route (not counts, not the password check). */
	public static function fingerprint( $route ) {
		if ( ! is_array( $route ) ) {
			return '';
		}
		$mu = (array) ( $route['interceptors'] ?? [] );
		sort( $mu );
		return implode( '|', [ $route['status'] ?? '', $route['plugin'] ?? '', $route['provider'] ?? '', $route['domain'] ?? '', ! empty( $route['test_mode'] ) ? 't' : '', $route['blocker'] ?? '', implode( ',', $mu ) ] );
	}

	/**
	 * Record what changed on a sync and append it to the environment's
	 * change log. Returns the log to store on details->email_route_changes.
	 */
	public static function changes( $prev, $route, $log ) {
		$log = is_array( $log ) ? $log : json_decode( wp_json_encode( $log ), true );
		$log = is_array( $log ) ? $log : [];
		if ( is_array( $prev ) && is_array( $route ) && self::fingerprint( $prev ) !== self::fingerprint( $route ) ) {
			$log[] = [ 'at' => time(), 'from' => self::describe( $prev ), 'to' => self::describe( $route ), 'from_status' => $prev['status'] ?? '', 'to_status' => $route['status'] ?? '' ];
		}
		return array_slice( $log, -10 );
	}

	/**
	 * Security signals for a production environment: the change from the
	 * previous route (an event) and the route's current issues (a state).
	 */
	public static function signals( $site_id, $environment_id, $environment, $prev, $route ) {
		if ( ! is_array( $route ) ) {
			return;
		}
		$prev = is_array( $prev ) ? $prev : json_decode( wp_json_encode( $prev ), true );
		// Moving onto our Mailgun is the fix, not news: it goes in the change log only.
		$joined = is_array( $prev ) && ( $prev['status'] ?? '' ) !== 'anchor' && $route['status'] === 'anchor';
		if ( is_array( $prev ) && ! $joined && self::fingerprint( $prev ) !== self::fingerprint( $route ) ) {
			$left   = ( $prev['status'] ?? '' ) === 'anchor' && $route['status'] !== 'anchor';
			$before = self::describe( $prev );
			$after  = self::describe( $route );
			MalwareFindings::record( (int) $site_id, (int) $environment_id, $environment, 'email', [ (object) [
				'filename'              => 'mail:route',
				'signature_id'          => $left ? 'email-left-anchor' : 'email-changed',
				'signature_name'        => $left ? 'Email no longer sends through Anchor Mailgun' : 'Email setup changed',
				'signature_description' => ( $left
					? 'This site sent through our Mailgun account on the previous sync and now does not. A redeploy, a restore or a plugin change usually does this, and mail may now go out unauthenticated or not at all.'
					: 'The way this site sends email changed since the previous sync.' ) . "\nBefore: {$before}\nNow: {$after}",
				'matched_text'          => "Before: {$before}\nNow: {$after}",
				'severity'              => $left ? 'high' : 'medium',
				'content_hash'          => hash( 'sha256', self::fingerprint( $prev ) . '>' . self::fingerprint( $route ) ),
			] ] );
		}
		// Back on our Mailgun: the move away is over.
		if ( $route['status'] === 'anchor' ) {
			global $wpdb;
			$t = $wpdb->base_prefix . 'captaincore_malware_findings';
			foreach ( (array) $wpdb->get_col( $wpdb->prepare( "SELECT malware_finding_id FROM $t WHERE site_id = %d AND environment = %s AND source = 'email' AND signature_id = 'email-left-anchor' AND status = 'open'", (int) $site_id, $environment ) ) as $id ) {
				MalwareFindings::resolve( (int) $site_id, (int) $id, 'Back on Anchor Mailgun (' . $route['domain'] . ') on a later sync.', 'scanner' );
			}
		}
		$state = [];
		foreach ( $route['issues'] as $i ) {
			$state[] = (object) [
				'filename'              => 'mail:' . $i['key'],
				'signature_id'          => 'email-' . $i['key'],
				'signature_name'        => $i['label'],
				'signature_description' => $i['label'] . "\nRoute: " . self::describe( $route ),
				'matched_text'          => self::describe( $route ),
				'severity'              => in_array( $i['key'], self::PRIORITY_ISSUES, true ) ? 'high' : 'medium',
				'content_hash'          => hash( 'sha256', $i['key'] . '|' . self::fingerprint( $route ) . '|' . ( $route['zone'] ?? '' ) ),
			];
		}
		MalwareFindings::sync_state( (int) $site_id, (int) $environment_id, $environment, 'email-state', $state );
	}

	/** The compact form the Sites list carries (operators only). */
	public static function for_list( $status, $label, $issue_keys ) {
		$keys = json_decode( (string) $issue_keys, true );
		return [ 'status' => (string) $status, 'label' => (string) $label, 'issues' => is_array( $keys ) ? array_values( $keys ) : [] ];
	}
}
