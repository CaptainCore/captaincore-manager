<?php
/**
 * Self-updater. Checks the manifest.json on GitHub and feeds WordPress the
 * update package from GitHub Releases. Same pattern as Disembark and Minn Admin.
 *
 * @package captaincore
 */

defined( 'ABSPATH' ) || exit;

class Captaincore_Manager_Updater {

	public $plugin_slug;
	public $version;
	public $cache_key;
	public $cache_allowed;

	const MANIFEST_URL = 'https://raw.githubusercontent.com/CaptainCore/captaincore-manager/master/manifest.json';

	/** Hosts the update package may legitimately come from. */
	const PACKAGE_HOSTS = array( 'github.com', 'objects.githubusercontent.com', 'codeload.github.com' );

	/**
	 * Repository paths packages may live under. Each entry is matched ANCHORED
	 * at the start of the URL path. Unanchored, anyone could satisfy it with a
	 * repository of their own containing that directory path.
	 */
	const PACKAGE_PATHS = array( '/CaptainCore/captaincore-manager/' );

	public function __construct() {
		// Scope any TLS relaxation to this plugin's own requests, and only when
		// the dev flag is truthy. Global sslverify filters would turn off
		// certificate verification for every outbound HTTPS request WordPress makes.
		if ( defined( 'CAPTAINCORE_DEV_MODE' ) && CAPTAINCORE_DEV_MODE ) {
			add_filter( 'http_request_args', array( $this, 'dev_mode_request_args' ), 10, 2 );
		}
		$this->plugin_slug   = 'captaincore-manager';
		$this->version       = CAPTAINCORE_VERSION;
		$this->cache_key     = 'captaincore_manager_updater';
		$this->cache_allowed = true;

		add_filter( 'plugins_api', array( $this, 'info' ), 30, 3 );
		add_filter( 'site_transient_update_plugins', array( $this, 'update' ) );
		add_action( 'upgrader_process_complete', array( $this, 'purge' ), 10, 2 );
		add_filter( 'upgrader_pre_download', array( $this, 'verify_package' ), 10, 4 );
	}

	/**
	 * Relax TLS for THIS plugin's own requests only, under dev mode.
	 *
	 * @param array  $args Request args.
	 * @param string $url  Request URL.
	 * @return array
	 */
	public function dev_mode_request_args( $args, $url ) {
		// Manifest only. Relaxing TLS on the PACKAGE leg too would make the
		// sha256 pin meaningless: an on-path attacker serves a forged manifest
		// carrying the hash of their own zip, then serves that zip, and
		// hash_equals() passes against the attacker's own expected value.
		if ( self::MANIFEST_URL === $url ) {
			$args['sslverify'] = false;
		}
		return $args;
	}

	/**
	 * Whether a URL is a plausible package URL for this plugin: https, on a
	 * GitHub host, under this repo. The manifest is fetched over TLS from a
	 * pinned URL; its download_url must never be handed to the upgrader
	 * unchecked (a manifest naming an http:// URL would have WordPress fetch
	 * executable code in the clear).
	 *
	 * @param string $url Candidate URL.
	 * @return bool
	 */
	public function is_our_package_url( $url ) {
		if ( ! is_string( $url ) || '' === $url ) {
			return false;
		}
		$parts = wp_parse_url( $url );
		if ( empty( $parts['scheme'] ) || 'https' !== strtolower( $parts['scheme'] ) || empty( $parts['host'] ) ) {
			return false;
		}
		$host = strtolower( $parts['host'] );
		if ( ! in_array( $host, self::PACKAGE_HOSTS, true ) ) {
			return false;
		}
		// objects.githubusercontent.com is only ever reached as a redirect
		// target inside download_url(), never as a manifest download_url, so it
		// does not carry the owner/repo pair and legitimately never matches.
		$path = (string) ( $parts['path'] ?? '' );
		foreach ( self::PACKAGE_PATHS as $prefix ) {
			if ( 0 === strpos( $path, $prefix ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * The sha256 the manifest publishes for a given package URL.
	 *
	 * Three-state on purpose:
	 *   null  the manifest does not claim this URL at all.
	 *   ''    the manifest claims it but publishes no hash. That is a refusal,
	 *         not a pass: treating "no sha256" as "verification not required"
	 *         makes integrity opt-out for whoever serves the manifest.
	 *   hash  verify against this.
	 *
	 * @param object|null $remote  Decoded manifest.
	 * @param string      $package Package URL being downloaded.
	 * @return string|null
	 */
	public function hash_for_package( $remote, $package ) {
		if ( ! is_object( $remote ) ) {
			return null;
		}
		if ( ! empty( $remote->download_url ) && $package === $remote->download_url ) {
			return isset( $remote->sha256 ) ? (string) $remote->sha256 : '';
		}
		return null;
	}

	/**
	 * Verify the update zip against the manifest's sha256 before install.
	 *
	 * Only intercepts our own package URL (https, GitHub host, this repo), and
	 * REQUIRES the manifest to publish a sha256 for it. The manifest travels
	 * over TLS from the repo while the zip comes from GitHub's release CDN; the
	 * pinned hash ties the two together.
	 *
	 * @param bool|string|WP_Error $reply      Filter chain value.
	 * @param string               $package    Package URL being downloaded.
	 * @param WP_Upgrader          $upgrader   Upgrader instance.
	 * @param array                $hook_extra Extra install context.
	 * @return bool|string|WP_Error Local file path on verified download.
	 */
	public function verify_package( $reply, $package, $upgrader, $hook_extra = array() ) {
		if ( false !== $reply || ! is_string( $package ) ) {
			return $reply;
		}
		// Cheap check BEFORE any network call: this filter fires for every
		// plugin, theme and core download on the site.
		if ( ! $this->is_our_package_url( $package ) ) {
			return $reply;
		}
		$remote   = $this->request();
		$expected = $this->hash_for_package( $remote, $package );
		if ( null === $expected || '' === $expected ) {
			return new WP_Error(
				'captaincore_missing_package_hash',
				__( 'CaptainCore Manager update rejected: the release manifest does not publish a sha256 for this package.', 'captaincore' )
			);
		}
		if ( ! function_exists( 'download_url' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}
		$file = download_url( $package, 300 );
		if ( is_wp_error( $file ) ) {
			return $file;
		}
		$hash = (string) hash_file( 'sha256', $file );
		if ( ! hash_equals( strtolower( $expected ), $hash ) ) {
			wp_delete_file( $file );
			return new WP_Error(
				'captaincore_bad_package_hash',
				__( 'CaptainCore Manager update rejected: the downloaded package does not match the sha256 published in the release manifest.', 'captaincore' )
			);
		}
		return $file;
	}

	public function request() {
		// The bundled manifest is the fallback when GitHub is unreachable.
		$manifest_file  = dirname( __DIR__ ) . '/manifest.json';
		$local_manifest = null;
		if ( file_exists( $manifest_file ) ) {
			$local_manifest = json_decode( file_get_contents( $manifest_file ) );
		}
		if ( ! is_object( $local_manifest ) ) {
			$local_manifest = new \stdClass();
		}

		$remote = get_transient( $this->cache_key );

		if ( false === $remote || ! $this->cache_allowed ) {
			$remote_response = wp_remote_get(
				self::MANIFEST_URL,
				array(
					'timeout' => 30,
					'headers' => array( 'Accept' => 'application/json' ),
				)
			);

			if ( is_wp_error( $remote_response ) || 200 !== wp_remote_retrieve_response_code( $remote_response ) || empty( wp_remote_retrieve_body( $remote_response ) ) ) {
				// Back off briefly on failure too, or an unreachable GitHub
				// re-blocks on the next pageload, and the next, and the next.
				set_transient( $this->cache_key, $local_manifest, 5 * MINUTE_IN_SECONDS );
				return $local_manifest;
			}

			$remote = json_decode( wp_remote_retrieve_body( $remote_response ) );
			// An hour: long enough that the update transient's many readers
			// (admin page loads, wp-cron) stop making a live GitHub request
			// each, short enough that a fresh release surfaces promptly.
			// `wp transient delete captaincore_manager_updater` forces a re-read.
			set_transient( $this->cache_key, $remote, HOUR_IN_SECONDS );
		}

		if ( is_object( $remote ) ) {
			return $remote;
		}

		return $local_manifest;
	}

	public function info( $response, $action, $args ) {
		if ( 'plugin_information' !== $action || empty( $args->slug ) || $this->plugin_slug !== $args->slug ) {
			return $response;
		}

		$remote = $this->request();
		if ( ! $remote || empty( $remote->version ) ) {
			return $response;
		}

		$response                 = new \stdClass();
		$response->name           = $remote->name;
		$response->slug           = $remote->slug;
		$response->version        = $remote->version;
		$response->tested         = $remote->tested;
		$response->requires       = $remote->requires;
		$response->author         = $remote->author;
		$response->author_profile = $remote->author_profile;
		$response->homepage       = $remote->homepage;
		// Same guard update() applies. verify_package() only intercepts URLs
		// that pass is_our_package_url(), so advertising one that does not
		// would hand the core upgrader a package with no host check and no
		// hash check at all.
		if ( $this->is_our_package_url( $remote->download_url ) && ! empty( $remote->sha256 ) ) {
			$response->download_link = $remote->download_url;
			$response->trunk         = $remote->download_url;
		}
		$response->requires_php   = $remote->requires_php;
		$response->last_updated   = $remote->last_updated;
		$response->sections       = array( 'description' => isset( $remote->sections->description ) ? $remote->sections->description : '' );

		return $response;
	}

	public function update( $transient ) {
		if ( empty( $transient->checked ) ) {
			return $transient;
		}

		$remote = $this->request();
		// Never offer an update whose package we would refuse to install: the
		// URL has to be https on a GitHub host under this repo, and the
		// manifest has to publish a hash to check the download against.
		if ( $remote && ! empty( $remote->download_url )
			&& ( ! $this->is_our_package_url( $remote->download_url ) || empty( $remote->sha256 ) ) ) {
			return $transient;
		}
		if ( $remote && isset( $remote->version ) && version_compare( $this->version, $remote->version, '<' ) ) {
			$response               = new \stdClass();
			$response->slug         = $this->plugin_slug;
			$response->plugin       = "{$this->plugin_slug}/captaincore.php";
			$response->new_version  = $remote->version;
			$response->package      = $remote->download_url;
			$response->tested       = isset( $remote->tested ) ? $remote->tested : '';
			$response->requires_php = isset( $remote->requires_php ) ? $remote->requires_php : '';

			$transient->response[ $response->plugin ] = $response;
		}

		return $transient;
	}

	public function purge( $upgrader, $options ) {
		if ( $this->cache_allowed && isset( $options['action'], $options['type'] ) && 'update' === $options['action'] && 'plugin' === $options['type'] ) {
			delete_transient( $this->cache_key );
		}
	}
}

new Captaincore_Manager_Updater();
