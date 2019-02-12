<?php

namespace CaptainCore;

class DB {

	private static function _table() {
		global $wpdb;
		$tablename = str_replace( '\\', '_', strtolower( get_called_class() ) );
		return $wpdb->prefix . $tablename;
	}

	private static function _fetch_sql( $value ) {
		global $wpdb;
		$sql = sprintf( 'SELECT * FROM %s WHERE %s = %%s', self::_table(), static::$primary_key );
		return $wpdb->prepare( $sql, $value );
	}

	static function valid_check( $data ) {
		global $wpdb;

		$sql_where       = '';
		$sql_where_count = count( $data );
		$i               = 1;
		foreach ( $data as $key => $row ) {
			if ( $i < $sql_where_count ) {
				$sql_where .= "`$key` = '$row' and ";
			} else {
				$sql_where .= "`$key` = '$row'";
			}
			$i++;
		}
		$sql     = 'SELECT * FROM ' . self::_table() . " WHERE $sql_where";
		$results = $wpdb->get_results( $sql );
		if ( count( $results ) != 0 ) {
			return false;
		} else {
			return true;
		}
	}

	static function get( $value ) {
		global $wpdb;
		return $wpdb->get_row( self::_fetch_sql( $value ) );
	}

	static function insert( $data ) {
		global $wpdb;
		$wpdb->insert( self::_table(), $data );
	}

	static function update( $data, $where ) {
		global $wpdb;
		$wpdb->update( self::_table(), $data, $where );
	}

	static function delete( $value ) {
		global $wpdb;
		$sql = sprintf( 'DELETE FROM %s WHERE %s = %%s', self::_table(), static::$primary_key );
		return $wpdb->query( $wpdb->prepare( $sql, $value ) );
	}

	static function fetch_logs( $value ) {
		global $wpdb;
		$value   = intval( $value );
		$sql     = 'SELECT * FROM ' . self::_table() . " WHERE `site_id` = '$value'";
		$results = $wpdb->get_results( $sql );
		$reponse = [];
		foreach ( $results as $result ) {

			$update_log = json_decode( $result->update_log );

			foreach ( $update_log as $log ) {
				$log->type = $result->update_type;
				$log->date = $result->created_at;
				$reponse[] = $log;
			}
		}
		return $reponse;
	}

	static function fetch( $value ) {
		global $wpdb;
		$value = intval( $value );
		$sql   = 'SELECT * FROM ' . self::_table() . " WHERE `site_id` = '$value' order by `created_at` DESC";
		return $wpdb->get_results( $sql );
	}

}

class update_logs extends DB {

	static $primary_key = 'log_id';

}

class quicksaves extends DB {

	static $primary_key = 'quicksave_id';

}

class Customers {

	protected $customers = [];

	public function __construct( $customers = [] ) {

		$user       = wp_get_current_user();
		$role_check = in_array( 'administrator', $user->roles );

		// Bail if role not assigned
		if ( !$role_check ) {
			return "Error: Please log in.";
		}

		$customers = get_posts( array(
			'order'          => 'asc',
			'orderby'        => 'title',
			'posts_per_page' => '-1',
			'post_type'      => 'captcore_customer'
		) );

		$this->customers = $customers;

	}

	public function all() {
		return $this->customers;
	}

}

class Customer {

	public function get( $customer ) {

		// Prepare site details to be returned
		$customer_details              = new \stdClass();
		$customer_details->customer_id = $customer->ID;
		$customer_details->name        = $customer->post_title;

		if( get_field('partner', $customer->ID ) ) { 
			$customer_details->developer = true;
		} else { 
			$customer_details->developer = false;
		} 

		return $customer_details;

	}

}

class Domains {

	protected $domains = [];

	public function __construct( $domains = [] ) {

		$user        = wp_get_current_user();
		$role_check  = in_array( 'subscriber', $user->roles ) + in_array( 'customer', $user->roles ) + in_array( 'partner', $user->roles ) + in_array( 'administrator', $user->roles ) + in_array( 'editor', $user->roles );
		$partner     = get_field( 'partner', 'user_' . get_current_user_id() );
		$all_domains = [];

		// Bail if not assigned a role
		if ( !$role_check ) {
			return "Error: Please log in.";
		}

		// Administrators return all sites
		if ( in_array( 'administrator', $user->roles ) ) {
			$customers = get_posts( array(
				'order'          => 'asc',
				'orderby'        => 'title',
				'posts_per_page' => '-1',
				'post_type'      => 'captcore_customer',
				'meta_query'     => array(
					'relation' => 'AND',
					array(
						'key'     => 'status',
						'value'   => 'closed',
						'compare' => '!=',
					),
				) ) );
		}

		if ( in_array( 'subscriber', $user->roles ) or in_array( 'customer', $user->roles ) or in_array( 'partner', $user->roles ) or in_array( 'editor', $user->roles ) ) {

			$customers = array();

			$user_id = get_current_user_id();
			$partner = get_field( 'partner', 'user_' . get_current_user_id() );
			if ( $partner ) {
				foreach ( $partner as $partner_id ) {
					$websites_for_partner = get_posts(
						array(
							'post_type'      => 'captcore_website',
							'posts_per_page' => '-1',
							'order'          => 'asc',
							'orderby'        => 'title',
							'fields'         => 'ids',
							'meta_query'     => array(
								'relation' => 'AND',
								array(
									'key'     => 'partner', // name of custom field
									'value'   => '"' . $partner_id . '"', // matches exactly "123", not just 123. This prevents a match for "1234"
									'compare' => 'LIKE',
								),
							),
						)
					);
					foreach ( $websites_for_partner as $website ) :
						$customers[] = get_field( 'customer', $website );
					endforeach;
				}
			}
			if ( count($customers) == 0 ) {
				foreach ( $partner as $partner_id ) {
					$websites_for_partner = get_posts(
						array(
							'post_type'      => 'captcore_website',
							'posts_per_page' => '-1',
							'order'          => 'asc',
							'orderby'        => 'title',
							'fields'         => 'ids',
							'meta_query'     => array(
								'relation' => 'AND',
								array(
									'key'     => 'customer', // name of custom field
									'value'   => '"' . $partner_id . '"', // matches exactly "123", not just 123. This prevents a match for "1234"
									'compare' => 'LIKE',
								),
							),
						)
					);
					foreach ( $websites_for_partner as $website ) :
						$customers[] = get_field( 'customer', $website );
					endforeach;
				}
			}
		}

		foreach ( $customers as $customer ) :

			if ( is_array( $customer ) ) {
				$customer = $customer[0];
			}

			$domains = get_field( 'domains', $customer );
			if ( $domains ) {
				foreach ( $domains as $domain ) :
					$domain_name = get_the_title( $domain );
					if ( $domain_name ) {
						$all_domains[ $domain_name ] = $domain;
					}
				endforeach;
			}

		endforeach;

		foreach ( $partner as $customer ) :

			$domains = get_field( 'domains', $customer );
			if ( $domains ) {
				foreach ( $domains as $domain ) :
					$domain_name = get_the_title( $domain );
					if ( $domain_name ) {
						$all_domains[ $domain_name ] = $domain;
					}
				endforeach;
			}

		endforeach;

		// Sort array by domain name
		ksort( $all_domains );

		$this->domains = $all_domains;
	}

	public function all() {
		return $this->domains;
	}

}

class Sites {

	protected $sites = [];

	public function __construct( $sites = [] ) {
		$user       = wp_get_current_user();
		$role_check = in_array( 'subscriber', $user->roles ) + in_array( 'customer', $user->roles ) + in_array( 'partner', $user->roles ) + in_array( 'administrator', $user->roles ) + in_array( 'editor', $user->roles );
		$partner    = get_field( 'partner', 'user_' . get_current_user_id() );

		// New array to collect IDs
		$site_ids = array();

		// Bail if not assigned a role
		if ( !$role_check ) {
			return "Error: Please log in.";
		}

		// Administrators return all sites
		if ( $partner && $role_check && in_array( 'administrator', $user->roles ) ) {
			$sites = get_posts( array(
				'order'          => 'asc',
				'orderby'        => 'title',
				'posts_per_page' => '-1',
				'post_type'      => 'captcore_website',
				'meta_query'     => array(
					'relation' => 'AND',
					array(
						'key'     => 'status',
						'value'   => 'closed',
						'compare' => '!=',
					),
			) ) );

			$this->sites = $sites;
			return;
		}

		// Bail if no partner set.
		if ( !is_array( $partner ) ) {
			return;
		}

		// Loop through each partner assigned to current user
		foreach ( $partner as $partner_id ) {

			// Load websites assigned to partner
			$arguments = array(
				'fields'         => 'ids',
				'post_type'      => 'captcore_website',
				'posts_per_page' => '-1',
				'meta_query'     => array(
					'relation' => 'AND',
					array(
						'key'     => 'partner',
						'value'   => '"' . $partner_id . '"',
						'compare' => 'LIKE',
					),
					array(
						'key'     => 'status',
						'value'   => 'closed',
						'compare' => '!=',
					),
					array(
						'key'     => 'address',
						'compare' => 'EXISTS',
					),
					array(
						'key'     => 'address',
						'value'   => '',
						'compare' => '!=',
					),
				),
			);

			$sites = new \WP_Query( $arguments );
			foreach($sites->posts as $site_id) {
				if( !in_array($site_id, $site_ids) ) {
					$site_ids[] = $site_id;
				}
			}

			// Load websites assigned to partner
			$arguments = array(
				'fields'         => 'ids',
				'post_type'      => 'captcore_website',
				'posts_per_page' => '-1',
				'meta_query'     => array(
					'relation' => 'AND',
					array(
						'key'     => 'customer',
						'value'   => '"' . $partner_id . '"',
						'compare' => 'LIKE',
					),
					array(
						'key'     => 'status',
						'value'   => 'closed',
						'compare' => '!=',
					),
					array(
						'key'     => 'address',
						'compare' => 'EXISTS',
					),
					array(
						'key'     => 'address',
						'value'   => '',
						'compare' => '!=',
					),
				),
			);

			$sites = new \WP_Query( $arguments );

			foreach($sites->posts as $site_id) {
				if( !in_array($site_id, $site_ids) ) {
					$site_ids[] = $site_id;
				}
			}

		}

		// Bail if no site ids found
		if ( count($site_ids) == 0 ) {
			return;
		}

		$sites = get_posts( array(
			'order'          => 'asc',
			'orderby'        => 'title',
			'posts_per_page' => '-1',
			'post_type'      => 'captcore_website',
			'include'		 => $site_ids,
			'meta_query'     => array(
				'relation' => 'AND',
				array(
					'key'     => 'status',
					'value'   => 'closed',
					'compare' => '!=',
				),
		) ) );
		$this->sites = $sites;
		return;
		
	}

	public function all() {
		return $this->sites;
	}

}

class Site {

	public function get( $site_id ) {

		if ( is_object( $site_id ) ) {
			$site = $site_id;
		}

		if ( !$site ) {
			$site = get_post( $site_id );
		}

		$domain              = get_the_title( $site->ID );
		$plugins             = json_decode( get_field( 'plugins', $site->ID ) );
		$themes              = json_decode( get_field( 'themes', $site->ID ) );
		$customer            = get_field( 'customer', $site->ID );
		$shared_with         = get_field( 'partner', $site->ID );
		$storage             = get_field( 'storage', $site->ID );
		if ($storage) {
			$storage_gbs = round($storage / 1024 / 1024 / 1024, 1);
			$storage_gbs = $storage_gbs ."GB";
		} else { 
			$storage_gbs = "";
		}
		$views               = get_field( 'views', $site->ID );
		$mailgun             = get_field( 'mailgun', $site->ID );
		$subsite_count       = get_field( 'subsite_count', $site->ID );
		$fathom              = json_decode( get_field( 'fathom', $site->ID ) );
		$exclude_themes      = get_field( 'exclude_themes', $site->ID );
		$exclude_plugins     = get_field( 'exclude_plugins', $site->ID );
		$updates_enabled     = get_post_meta( $site->ID, 'updates_enabled' );
		$production_address  = get_field( 'address', $site->ID );
		$production_username = get_field( 'username', $site->ID );
		$production_port     = get_field( 'port', $site->ID );
		$database_username   = get_field( 'database_username', $site->ID);
		$staging_address     = get_field( 'address_staging', $site->ID );
		$staging_username    = get_field( 'username_staging', $site->ID );
		$staging_port        = get_field( 'port_staging', $site->ID );
		$home_url            = get_field( 'home_url', $site->ID );
		if ( $fathom == "" ) {
			$fathom = array();
		}
		if ( strpos( $production_address, '.wpengine.com' ) !== false ) { 
			$server = "WP Engine";
		} 
        if ( strpos( $production_address, '.kinsta.' ) !== false ) { 
			$server = "Kinsta";
		}

		if ( !isset( $server ) ) {
			$server = "";
		}

		// Prepare site details to be returned
		$site_details       = new \stdClass();
		$site_details->id   = $site->ID;
		$site_details->name = get_the_title( $site->ID );
		$site_details->site = get_field( 'site', $site->ID );
		$site_details->filtered = true;
		$site_details->selected = false;
		$site_details->loading_plugins = false;
		$site_details->loading_themes = false;
		$site_details->environment_selected = "Production";
		$site_details->mailgun = $mailgun;
		$site_details->subsite_count = $subsite_count;
		$site_details->fathom = $fathom;
		$site_details->tabs = "tab-Site-Management";
		$site_details->tabs_management = "tab-Keys";
		$site_details->storage = $storage_gbs;
		$site_details->server = $server;
		if ( is_string($views) ) {
			$site_details->views = number_format( intval( $views ) );
		}
		$site_details->exclude_themes = $exclude_themes;
		$site_details->exclude_plugins = $exclude_plugins;
		$site_details->update_logs = array();
		$site_details->update_logs_pagination = array( "descending" => true, "sortBy" => "date" );
		$site_details->updates_enabled = $updates_enabled;
		$site_details->themes_selected = array();
		$site_details->plugins_selected = array();
		$site_details->users_selected = array();
		$site_details->pagination = array( "sortBy" => "roles" );

		if ( !isset( $site_details->views ) ) {
			$site_details->views = "";
		}

		if ( $site_details->views == 0 ) {
			$site_details->views = "";
		}

		if (  $customer ) {
			foreach ( $customer as $customer_id ) {
				$customer_name          = get_post_field( 'post_title', $customer_id, 'raw' );
				$site_details->customer[] = array(
					'customer_id' => "$customer_id",
					'name'        => $customer_name
				);
			}
		}
				$site_details->users       = array();
				$site_details->update_logs = array();
		if ( $exclude_themes ) {
			$exclude_themes               = explode( ',', $exclude_themes );
			$site_details->exclude_themes = $exclude_themes;
		} else {
			$site_details->exclude_themes = array();
		}
		if ( $exclude_plugins ) {
			$exclude_plugins               = explode( ',', $exclude_plugins );
			$site_details->exclude_plugins = $exclude_plugins;
		} else {
			$site_details->exclude_plugins = array();
		}

		if ( $updates_enabled && $updates_enabled[0] == '1' ) {
			$site_details->updates_enabled = 1;
		} else {
			$site_details->updates_enabled = 0;
		}

		if ( $plugins && $plugins != '' ) {
			$site_details->plugins = $plugins;
		} else {
			$site_details->plugins = array();
		}
		if ( $themes && $themes != '' ) {
			$site_details->themes = $themes;
		} else {
			$site_details->themes = array();
		}

		if ( $shared_with ) {
			foreach ( $shared_with as $customer_id ) {
				$site_details->shared_with[] = array(
					'customer_id' => "$customer_id",
					'name'        => get_post_field( 'post_title', $customer_id, 'raw' ),
				);
			}
		}

		$site_details->keys[0] = array(
			'key_id'      => 1,
			'link'        => "http://$domain",
			'environment' => 'Production',
			'address'     => get_field( 'address', $site->ID ),
			'username'    => get_field( 'username', $site->ID ),
			'password'    => get_field( 'password', $site->ID ),
			'protocol'    => get_field( 'protocol', $site->ID ),
			'port'        => get_field( 'port', $site->ID ),
			'homedir'     => get_field( 'homedir', $site->ID ),
		);

		if ( strpos( $production_address, '.kinsta.' ) ) {
			$site_details->keys[0]["ssh"] = "ssh ${production_username}@${production_address} -p ${production_port}";
			$production_address_find_ending = strpos( $production_address,'.kinsta.' ) + 1;
			$production_address_ending = substr( $production_address, $production_address_find_ending );
		}
		if ( strpos( $production_address, '.kinsta.' ) and get_field( 'database_username', $site->ID ) ) {
			
			$site_details->keys[0]["database"] = "https://mysqleditor-${database_username}.${production_address_ending}";
			$site_details->keys[0]["database_username"] = get_field('database_username', $site->ID);
			$site_details->keys[0]["database_password"] = get_field('database_password', $site->ID);
		}

		if ( get_field( 'address_staging', $site->ID ) ) {

			if ( strpos( get_field( 'address_staging', $site->ID ), '.kinsta.' ) ) {
				$link_staging = "https://staging-" . get_field( 'site_staging', $site->ID ) . ".${production_address_ending}";
			} else {
				$link_staging = 'https://' . get_field( 'site_staging', $site->ID ) . '.staging.wpengine.com';
			}

			$site_details->keys[1] = array(
				'key_id'      => 2,
				'link'        => $link_staging,
				'environment' => 'Staging',
				'address'     => get_field( 'address_staging', $site->ID ),
				'username'    => get_field( 'username_staging', $site->ID ),
				'password'    => get_field( 'password_staging', $site->ID ),
				'protocol'    => get_field( 'protocol_staging', $site->ID ),
				'port'        => get_field( 'port_staging', $site->ID ),
				'homedir'     => get_field( 'homedir_staging', $site->ID ),
			);

			if ( strpos( $staging_address, '.kinsta.' ) ) {
				$site_details->keys[1]["ssh"] = "ssh ${staging_username}@${staging_address} -p ${staging_port}";
				$staging_address_find_ending = strpos( $staging_address,'.kinsta.' ) + 1;
				$staging_address_ending = substr( $staging_address, $staging_address_find_ending );
			}
			if ( strpos( $staging_address, '.kinsta.' ) and get_field( 'database_username_staging', $site->ID ) ) {
				$site_details->keys[1]["database"] = "https://mysqleditor-staging-${database_username}.${staging_address_ending}";
				$site_details->keys[1]["database_username"] = get_field('database_username_staging', $site->ID);
				$site_details->keys[1]["database_password"] = get_field('database_password_staging', $site->ID);
			}

		}

		$site_details->core                   = get_field( 'core', $site->ID );
		$site_details->home_url               = $home_url;
		return $site_details;

	}

	public function create( $site ) {

		// Work with array as PHP object
		$site = (object) $site;

		// Prep for response to return
		$response = array();

		// Pull in current user
		$current_user = wp_get_current_user();

		// Validate
		if ( $site->domain == '') {
			$response['response'] = "Error: Domain can't be empty.";
			return $response;
		}

		// Create post object
		$new_site = array(
			'post_title'  => $site->domain,
			'post_author' => $current_user->ID,
			'post_type'   => 'captcore_website',
			'post_status' => 'publish',
		);

		// Insert the post into the database
		$site_id = wp_insert_post( $new_site );

		if ( $site_id ) {

			$response['response'] = 'Successfully added new site';
			$response['site_id']  = $site_id;

			// add in ACF fields
			update_field( 'site', $site->site, $site_id );
			update_field( 'customer', array_column( $site->customer, 'customer_id' ), $site_id );
			update_field( 'partner', array_column( $site->shared_with, 'customer_id' ), $site_id );
			update_field( 'updates_enabled', $site->updates_enabled, $site_id );
			update_field( 'status', 'active', $site_id );

			if ( get_field( 'launch_date', $site_id ) == '' ) {

				// No date was entered for Launch Date, assign to today.
				update_field( 'launch_date', date( 'Ymd' ), $site_id );

			}

			foreach ( $site->keys as $key ) {

				// Work with array as PHP object
				$key = (object) $key;

				// Add production key
				if ( $key->environment == 'Production' ) {
					if ( strpos( $key->address, '.kinsta.' ) ) {
						update_field( 'provider', 'kinsta', $site_id );
					}
					if ( strpos( $key->address, '.wpengine.com' ) ) {
						update_field( 'provider', 'wpengine', $site_id );
					}
					update_field( 'address', $key->address, $site_id );
					update_field( 'username', $key->username, $site_id );
					update_field( 'password', $key->password, $site_id );
					update_field( 'protocol', $key->protocol, $site_id );
					update_field( 'port', $key->port, $site_id );
					update_field( 'homedir', $key->homedir, $site_id );
					update_field( 's3_access_key', $key->s3_access_key, $site_id );
					update_field( 's3_secret_key', $key->s3_secret_key, $site_id );
					update_field( 's3_bucket', $key->s3_bucket, $site_id );
					update_field( 's3_path', $key->s3_path, $site_id );
					if ( $key->use_s3 ) {
						update_field( 'use_s3', '1', $site_id );
					}
					update_field( 'database_username', $key->database_username, $site_id );
					update_field( 'database_password', $key->database_password, $site_id );
				}

				// Add staging key
				if ( $key->environment == 'Staging' ) {
					update_field( 'site_staging', $key->site, $site_id );
					update_field( 'address_staging', $key->address, $site_id );
					update_field( 'username_staging', $key->username, $site_id );
					update_field( 'password_staging', $key->password, $site_id );
					update_field( 'protocol_staging', $key->protocol, $site_id );
					update_field( 'port_staging', $key->port, $site_id );
					update_field( 'homedir_staging', $key->homedir, $site_id );
					update_field( 'database_username_staging', $key->database_username, $site_id );
					update_field( 'database_password_staging', $key->database_password, $site_id );
				}
			}

			// Run ACF custom tasks afterward.
			captaincore_acf_save_post_after( $site_id );
		}
		if ( ! $site_id ) {
			$response['response'] = 'Failed to add new site';
		}

		return $response;
	}

	public function update( $site ) {

		// Work with array as PHP object
		$site = (object) $site;

		// Prep for response to return
		$response = array();

		// Pull in current user
		$current_user = wp_get_current_user();

		$site_id = $site->id;

		// Validate site exists
		if ( get_post_type( $site_id ) != 'captcore_website' ) {
			$response['response'] = "Error: Site ID not found.";
			return $response;
		}

		// Updates post
		$update_site = array(
			'ID'          => $site_id,
			'post_title'  => $site->name,
			'post_author' => $current_user->ID,
			'post_type'   => 'captcore_website',
			'post_status' => 'publish',
		);

		wp_update_post( $update_site, true );

		if (is_wp_error($site_id)) {
		    $errors = $site_id->get_error_messages();
				return $response['response'] = implode( " ", $errors );
		}

		if ( $site_id ) {

			$response['response'] = "Successfully updated site";
			$response['site_id']  = $site_id;

			// add in ACF fields
			update_field( 'customer', array_column($site->customer, 'customer_id'), $site_id );
			update_field( 'partner', array_column($site->shared_with, 'customer_id'), $site_id );
			update_field( 'updates_enabled', $site->updates_enabled, $site_id );
			//update_field( 'status', 'active', $site_id );

			if ( get_field( 'launch_date', $site_id ) == '' ) {

				// No date was entered for Launch Date, assign to today.
				update_field( 'launch_date', date( 'Ymd' ), $site_id );

			}

			foreach ( $site->keys as $key ) {

				// Work with array as PHP object
				$key = (object) $key;

				// Add production key
				if ( $key->environment == 'Production' ) {
					if ( strpos( $key->address, '.kinsta.' ) ) {
						update_field( 'provider', 'kinsta', $site_id );
					}
					if ( strpos( $key->address, '.wpengine.com' ) ) {
						update_field( 'provider', 'wpengine', $site_id );
					}
					update_field( 'site', $key->site, $site_id );
					update_field( 'address', $key->address, $site_id );
					update_field( 'username', $key->username, $site_id );
					update_field( 'password', $key->password, $site_id );
					update_field( 'protocol', $key->protocol, $site_id );
					update_field( 'port', $key->port, $site_id );
					update_field( 'homedir', $key->homedir, $site_id );
					update_field( 's3_access_key', $key->s3_access_key, $site_id );
					update_field( 's3_secret_key', $key->s3_secret_key, $site_id );
					update_field( 's3_bucket', $key->s3_bucket, $site_id );
					update_field( 's3_path', $key->s3_path, $site_id );
					if ( $key->use_s3 ) {
						update_field( 'use_s3', '1', $site_id );
					}
					update_field( 'database_username', $key->database_username, $site_id );
					update_field( 'database_password', $key->database_password, $site_id );
				}

				// Add staging key
				if ( $key->environment == 'Staging' ) {
					update_field( 'site_staging', $key->site, $site_id );
					update_field( 'address_staging', $key->address, $site_id );
					update_field( 'username_staging', $key->username, $site_id );
					update_field( 'password_staging', $key->password, $site_id );
					update_field( 'protocol_staging', $key->protocol, $site_id );
					update_field( 'port_staging', $key->port, $site_id );
					update_field( 'homedir_staging', $key->homedir, $site_id );
					update_field( 'database_username_staging', $key->database_username, $site_id );
					update_field( 'database_password_staging', $key->database_password, $site_id );
				}
			}
		}

		return $response;
	}

}


// Example adding record
// (new update_logs)->insert( array( 'site_id' => 1, 'update_type' => "Plugin", 'update_log' => "json data" ) );
// (new update_logs)->list_records();
// get record
// $r = (new CaptainCore\update_logs)->get( 1 );
// $update_logs = new CaptainCore\update_logs;
// $update_logs->get(1);
// $update_log->insert( array( 'site_id' => 10, 'update_type' => "Theme", 'update_log' => "json data", 'created_at' => current_time( 'mysql') ));
/*
 $json = '[{"date":"2018-06-20-091520","type":"plugin","updates":[{"name":"akismet","old_version":"4.0.7","new_version":"4.0.8","status":"Updated"},{"name":"google-analytics-for-wordpress","old_version":"7.0.6","new_version":"7.0.8","status":"Updated"}]},{"date":"2018-06-22-141016","type":"plugin","updates":[{"name":"gutenberg","old_version":"3.0.1","new_version":"3.1.0","status":"Updated"},{"name":"wp-smush-pro","old_version":"2.7.9.1","new_version":"2.7.9.2","status":"Updated"},{"name":"woocommerce","old_version":"3.4.2","new_version":"3.4.3","status":"Updated"}]},{"date":"2018-06-26-212330","type":"plugin","updates":[{"name":"google-analytics-for-wordpress","old_version":"7.0.8","new_version":"7.0.9","status":"Updated"},{"name":"wordpress-seo","old_version":"7.6.1","new_version":"7.7","status":"Updated"}]},{"date":"2018-06-28-061239","type":"plugin","updates":[{"name":"wordpress-seo","old_version":"7.7","new_version":"7.7.1","status":"Updated"}]},{"date":"2018-06-28-180739","type":"plugin","updates":[{"name":"admin-columns-pro","old_version":"4.2.9","new_version":"4.3.4","status":"Updated"},{"name":"ac-addon-acf","old_version":"2.2.3","new_version":"2.3","status":"Updated"},{"name":"gutenberg","old_version":"3.1.0","new_version":"3.1.1","status":"Updated"},{"name":"wp-mail-smtp","old_version":"1.2.5","new_version":"1.3.0","status":"Updated"}]},{"date":"2018-06-29-143550","type":"plugin","updates":[{"name":"wp-mail-smtp","old_version":"1.3.0","new_version":"1.3.2","status":"Updated"},{"name":"wordpress-seo","old_version":"7.7.1","new_version":"7.7.2","status":"Updated"}]},{"date":"2018-07-04-092457","type":"plugin","updates":[{"name":"wordpress-seo","old_version":"7.7.2","new_version":"7.7.3","status":"Updated"}]}]';

$json_decode = json_decode($json);

foreach ( $json_decode as $row ) {

	// Format for mysql timestamp format
	$date_formatted = substr_replace($row->date," ",10,1);   # "2018-06-20-091520"
	$date_formatted = substr_replace($date_formatted,":",13,0);
	$date_formatted = substr_replace($date_formatted,":",16,0);
	$update_log = json_encode($row->updates);

	$update_log->insert( array( 'site_id' => 10, 'update_type' => $row->type, 'update_log' => $update_log, 'created_at' => $date_formatted ) );
}


foreach


*/
