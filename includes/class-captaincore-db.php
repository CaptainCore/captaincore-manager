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
		return $wpdb->insert_id;
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

	static function fetch_logs( $value, $environment_id ) {
		global $wpdb;
		$value          = intval( $value );
		$environment_id = intval( $environment_id );
		$sql            = 'SELECT * FROM ' . self::_table() . " WHERE `site_id` = '$value' and `environment_id` = '$environment_id'";
		$results        = $wpdb->get_results( $sql );
		$reponse        = [];
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

	static function fetch_environment( $value, $environment_id ) {
		global $wpdb;
		$value          = intval( $value );
		$environment_id = intval( $environment_id );
		$sql            = 'SELECT * FROM ' . self::_table() . " WHERE `site_id` = '$value' and `environment_id` = '$environment_id' order by `created_at` DESC";
		return $wpdb->get_results( $sql );
	}

	static function all( $sort = "created_at", $sort_order = "DESC" ) {
		global $wpdb;
		$sql = 'SELECT * FROM ' . self::_table() . ' order by `' . $sort . '` '. $sort_order;
		return $wpdb->get_results( $sql );
	}

	static function fetch_recipes( $sort = "created_at", $sort_order = "DESC" ) {
		global $wpdb;
		$user_id = get_current_user_id();
		$sql = 'SELECT * FROM ' . self::_table() . " WHERE user_id = '{$user_id}' or `public` = '1' order by `{$sort}` {$sort_order}";
		return $wpdb->get_results( $sql );
	}

	static function fetch_environments( $value ) {
		global $wpdb;
		$value = intval( $value );
		$sql   = 'SELECT * FROM ' . self::_table() . " WHERE `site_id` = '$value' order by `environment` ASC";
		return $wpdb->get_results( $sql );
	}

	static function fetch_field( $value, $environment, $field ) {
		global $wpdb;
		$value = intval( $value );
		$sql   = "SELECT $field FROM " . self::_table() . " WHERE `site_id` = '$value' and `environment` = '$environment' order by `created_at` DESC";
		return $wpdb->get_results( $sql );
	}

	static function fetch_by_environments( $site_id ) {
		
		$results = array();

		$environment_id = get_field( 'environment_production_id', $site_id );
		if ( $environment_id != "" ) {
			$results["Production"] = self::fetch_environment( $site_id, $environment_id );
		}

		$environment_id = get_field( 'environment_staging_id', $site_id );
		if ( $environment_id != "" ) {
			$results["Staging"] = self::fetch_environment( $site_id, $environment_id );
		}
		
		return $results;
	}

}

class environments extends DB {

	static $primary_key = 'environment_id';

}

class update_logs extends DB {

	static $primary_key = 'log_id';

}

class quicksaves extends DB {

	static $primary_key = 'quicksave_id';

}

class snapshots extends DB {

	static $primary_key = 'snapshot_id';

}

class recipes extends DB {

	static $primary_key = 'recipe_id';

}

class Customers {

	protected $customers = [];

	public function __construct( $customers = [] ) {

		$user       = wp_get_current_user();
		$role_check = in_array( 'administrator', $user->roles );

		// Bail if role not assigned
		if ( ! $role_check ) {
			return 'Error: Please log in.';
		}

		$customers = get_posts(
			array(
				'order'          => 'asc',
				'orderby'        => 'title',
				'posts_per_page' => '-1',
				'post_type'      => 'captcore_customer',
			)
		);

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

		if ( get_field( 'partner', $customer->ID ) ) {
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
		if ( ! $role_check ) {
			return 'Error: Please log in.';
		}

		// Administrators return all sites
		if ( in_array( 'administrator', $user->roles ) ) {
			$customers = get_posts(
				array(
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
					),
				)
			);
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
			if ( count( $customers ) == 0 and is_array( $partner ) ) {
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
					$domain_id = get_field( "domain_id", $domain );
					if ( $domain_name ) {
						$all_domains[ $domain_name ] = array( "name" => $domain_name, "id" => $domain_id );
					}
				endforeach;
			}

		endforeach;

		foreach ( $partner as $customer ) :

			$domains = get_field( 'domains', $customer );
			if ( $domains ) {
				foreach ( $domains as $domain ) :
					$domain_name = get_the_title( $domain );
					$domain_id = get_field( "domain_id", $domain );
					if ( $domain_name ) {
						$all_domains[ $domain_name ] = array( "name" => $domain_name, "id" => $domain_id );
					}
				endforeach;
			}

		endforeach;

		usort( $all_domains, "sort_by_name" );

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
		if ( ! $role_check ) {
			return 'Error: Please log in.';
		}

		// Administrators return all sites
		if ( $partner && $role_check && in_array( 'administrator', $user->roles ) ) {
			$sites = get_posts(
				array(
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
					),
				)
			);

			$this->sites = $sites;
			return;
		}

		// Bail if no partner set.
		if ( ! is_array( $partner ) ) {
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
				),
			);

			$sites = new \WP_Query( $arguments );

			foreach ( $sites->posts as $site_id ) {
				if ( ! in_array( $site_id, $site_ids ) ) {
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
				),
			);

			$sites = new \WP_Query( $arguments );

			foreach ( $sites->posts as $site_id ) {
				if ( ! in_array( $site_id, $site_ids ) ) {
					$site_ids[] = $site_id;
				}
			}
		}

		// Bail if no site ids found
		if ( count( $site_ids ) == 0 ) {
			return;
		}

		$sites       = get_posts(
			array(
				'order'          => 'asc',
				'orderby'        => 'title',
				'posts_per_page' => '-1',
				'post_type'      => 'captcore_website',
				'include'        => $site_ids,
				'meta_query'     => array(
					'relation' => 'AND',
					array(
						'key'     => 'status',
						'value'   => 'closed',
						'compare' => '!=',
					),
				),
			)
		);
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

		if ( ! isset( $site ) ) {
			$site = get_post( $site_id );
		}

		$upload_dir   = wp_upload_dir();

		// Fetch relating environments
		$db_environments = new environments();
		$environments    = $db_environments->fetch_environments( $site->ID );

		$domain      = get_the_title( $site->ID );
		$customer    = get_field( 'customer', $site->ID );
		$shared_with = get_field( 'partner', $site->ID );
		$mailgun     = get_field( 'mailgun', $site->ID );
		$storage     = $environments[0]->storage;
		if ( $storage ) {
			$storage_gbs = round( $storage / 1024 / 1024 / 1024, 1 );
			$storage_gbs = $storage_gbs . 'GB';
		} else {
			$storage_gbs = '';
		}
		$visits              = $environments[0]->visits;
		$subsite_count       = $environments[0]->subsite_count;
		$production_address  = $environments[0]->address;
		$production_username = $environments[0]->username;
		$production_port     = $environments[0]->port;
		$database_username   = $environments[0]->database_username;
		$staging_address     = ( isset( $environments[1] ) ? $environments[1]->address : '' );
		$staging_username    = ( isset( $environments[1] ) ? $environments[1]->username : '' );
		$staging_port        = ( isset( $environments[1] ) ? $environments[1]->port : '' );
		$home_url            = $environments[0]->home_url;

		// Prepare site details to be returned
		$site_details                       = new \stdClass();
		$site_details->id                   = $site->ID;
		$site_details->name                 = $domain;
		$site_details->site                 = get_field( 'site', $site->ID );
		$site_details->provider             = get_field( 'provider', $site->ID );
		$site_details->filtered             = true;
		$site_details->usage_breakdown      = array();
		$site_details->timeline             = array();
		$site_details->selected             = false;
		$site_details->loading_plugins      = false;
		$site_details->loading_themes       = false;
		$site_details->environment_selected = 'Production';
		$site_details->mailgun              = $mailgun;
		$site_details->subsite_count        = $subsite_count;
		$site_details->tabs                 = 'tab-Site-Management';
		$site_details->tabs_management      = 'tab-Info';
		$site_details->storage_raw          = $environments[0]->storage;
		$site_details->storage              = $storage_gbs;
		if ( is_string( $visits ) ) {
			$site_details->visits = number_format( intval( $visits ) );
		}
		$site_details->update_logs            = array();
		$site_details->update_logs_pagination = array(
			'descending' => true,
			'sortBy'     => 'date',
		);
		$site_details->pagination             = array( 'sortBy' => 'roles' );

		if ( ! isset( $site_details->visits ) ) {
			$site_details->visits = '';
		}

		if ( $site_details->visits == 0 ) {
			$site_details->visits = '';
		}

		if ( $customer ) {
			foreach ( $customer as $customer_id ) {
				$customer_name = get_post_field( 'post_title', $customer_id, 'raw' );
				$addons        = get_field( 'addons', $customer_id );
				if ( $addons == '' ) {
					$addons = array();
				}
				$site_details->customer = array(
					'customer_id'    => $customer_id,
					'name'           => $customer_name,
					'hosting_addons' => $addons,
					'hosting_plan'   => array(
						'name'          => get_field( 'hosting_plan', $customer_id ),
						'visits_limit'  => get_field( 'visits_limit', $customer_id ),
						'storage_limit' => get_field( 'storage_limit', $customer_id ),
						'sites_limit'   => get_field( 'sites_limit', $customer_id ),
						'price'         => get_field( 'price', $customer_id ),
					),
					'usage'          => array(
						'storage' => get_field( 'storage', $customer_id ),
						'visits'  => get_field( 'visits', $customer_id ),
						'sites'   => get_field( 'sites', $customer_id ),
					),
				);
			}
		}

		if ( count( $site_details->customer ) == 0 ) {
			$site_details->customer = array(
				'customer_id'   => '',
				'name'          => '',
				'hosting_plan'  => '',
				'visits_limit'  => '',
				'storage_limit' => '',
				'sites_limit'   => '',
			);
		}

		$site_details->users       = array();
		$site_details->update_logs = array();

		if ( $shared_with ) {
			foreach ( $shared_with as $customer_id ) {
				$site_details->shared_with[] = array(
					'customer_id' => "$customer_id",
					'name'        => get_post_field( 'post_title', $customer_id, 'raw' ),
				);
			}
		}

		$site_details->environments[0] = array(
			'id'                      => $environments[0]->environment_id,
			'link'                    => "http://$domain",
			'environment'             => 'Production',
			'address'                 => $environments[0]->address,
			'username'                => $environments[0]->username,
			'password'                => $environments[0]->password,
			'protocol'                => $environments[0]->protocol,
			'port'                    => $environments[0]->port,
			'home_directory'          => $environments[0]->home_directory,
			'fathom'                  => json_decode( $environments[0]->fathom ),
			'plugins'                 => json_decode( $environments[0]->plugins ),
			'themes'                  => json_decode( $environments[0]->themes ),
			'users'                   => 'Loading',
			'quicksaves'              => 'Loading',
			'snapshots'               => 'Loading',
			'update_logs'             => 'Loading',
			'quicksave_panel'         => array(),
			'quicksave_search'        => '',
			'core'                    => $environments[0]->core,
			'home_url'                => $environments[0]->home_url,
			'updates_enabled'         => intval( $environments[0]->updates_enabled ),
			'updates_exclude_plugins' => $environments[0]->updates_exclude_plugins,
			'updates_exclude_themes'  => $environments[0]->updates_exclude_themes,
			'offload_enabled'         => $environments[0]->offload_enabled,
			'offload_provider'        => $environments[0]->offload_provider,
			'offload_access_key'      => $environments[0]->offload_access_key,
			'offload_secret_key'      => $environments[0]->offload_secret_key,
			'offload_bucket'          => $environments[0]->offload_bucket,
			'offload_path'            => $environments[0]->offload_path,
			'screenshot'              => intval( $environments[0]->screenshot ),
			'screenshot_small'        => '',
			'screenshot_large'        => '',
			'stats'                   => 'Loading',
			'themes_selected'         => array(),
			'plugins_selected'        => array(),
			'users_selected'          => array(),
		);

		if ( $site_details->environments[0]['fathom'] == '' ) {
			$site_details->environments[0]['fathom'] = array(
				array(
					'code'   => '',
					'domain' => '',
				),
			);
		}

		if ( intval( $environments[0]->screenshot ) ) {
			$site_details->environments[0]['screenshot_small'] = $upload_dir['baseurl'] . "/screenshots/{$site_details->site}_{$site_details->id}/production/screenshot-100.png";
			$site_details->environments[0]['screenshot_large'] = $upload_dir['baseurl'] . "/screenshots/{$site_details->site}_{$site_details->id}/production/screenshot-800.png";
		}

		if ( $site_details->environments[0]['updates_exclude_themes'] ) {
			$site_details->environments[0]['updates_exclude_themes'] = explode( ',', $site_details->environments[0]['updates_exclude_themes'] );
		} else {
			$site_details->environments[0]['updates_exclude_themes'] = array();
		}
		if ( $site_details->environments[0]['updates_exclude_plugins'] ) {
			$site_details->environments[0]['updates_exclude_plugins'] = explode( ',', $site_details->environments[0]['updates_exclude_plugins'] );
		} else {
			$site_details->environments[0]['updates_exclude_plugins'] = array();
		}

		if ( $site_details->environments[0]['themes'] == '' ) {
			$site_details->environments[0]['themes'] = array();
		}
		if ( $site_details->environments[0]['plugins'] == '' ) {
			$site_details->environments[0]['plugins'] = array();
		}

		if ( $site_details->provider == 'kinsta' ) {
			$site_details->environments[0]['ssh'] = "ssh ${production_username}@${production_address} -p ${production_port}";
		}
		if ( $site_details->provider == 'kinsta' and $environments[0]->database_username ) {
			$kinsta_ending = array_pop( explode(".", $site_details->environments[0]['address']) );
			if ( $kinsta_ending != "com" && $$kinsta_ending != "cloud" ) {
				$kinsta_ending = "cloud";
			}
			$site_details->environments[0]['database']          = "https://mysqleditor-${database_username}.kinsta.{$kinsta_ending}";
			$site_details->environments[0]['database_username'] = $environments[0]->database_username;
			$site_details->environments[0]['database_password'] = $environments[0]->database_password;
		}

		if ( $site_details->provider == 'kinsta' ) {
			$link_staging = $environments[1]->home_url;
		}

		if ( $site_details->provider == 'wpengine' ) {
			$link_staging = 'https://' . get_field( 'site', $site->ID ) . '.staging.wpengine.com';
		}

		$site_details->environments[1] = array(
			'key_id'                  => 2,
			'link'                    => $link_staging,
			'environment'             => 'Staging',
			'address'                 => $environments[1]->address,
			'username'                => $environments[1]->username,
			'password'                => $environments[1]->password,
			'protocol'                => $environments[1]->protocol,
			'port'                    => $environments[1]->port,
			'home_directory'          => $environments[1]->home_directory,
			'fathom'                  => json_decode( $environments[1]->fathom ),
			'plugins'                 => json_decode( $environments[1]->plugins ),
			'themes'                  => json_decode( $environments[1]->themes ),
			'users'                   => 'Loading',
			'quicksaves'              => 'Loading',
			'snapshots'               => 'Loading',
			'update_logs'             => 'Loading',
			'quicksave_panel'         => array(),
			'quicksave_search'        => '',
			'core'                    => $environments[1]->core,
			'home_url'                => $environments[1]->home_url,
			'updates_enabled'         => intval( $environments[1]->updates_enabled ),
			'updates_exclude_plugins' => $environments[1]->updates_exclude_plugins,
			'updates_exclude_themes'  => $environments[1]->updates_exclude_themes,
			'offload_enabled'         => $environments[1]->offload_enabled,
			'offload_provider'        => $environments[1]->offload_provider,
			'offload_access_key'      => $environments[1]->offload_access_key,
			'offload_secret_key'      => $environments[1]->offload_secret_key,
			'offload_bucket'          => $environments[1]->offload_bucket,
			'offload_path'            => $environments[1]->offload_path,
			'screenshot'              => intval( $environments[1]->screenshot ),
			'screenshot_small'        => '',
			'screenshot_large'        => '',
			'stats'                   => 'Loading',
			'themes_selected'         => array(),
			'plugins_selected'        => array(),
			'users_selected'          => array(),
		);

		if ( $site_details->environments[1]['fathom'] == '' ) {
			$site_details->environments[1]['fathom'] = array(
				array(
					'code'   => '',
					'domain' => '',
				),
			);
		}

		if ( intval( $environments[1]->screenshot ) == 1 ) {
			$site_details->environments[1]['screenshot_small'] = $upload_dir['baseurl'] . "/screenshots/{$site_details->site}_{$site_details->id}/staging/screenshot-100.png";
			$site_details->environments[1]['screenshot_large'] = $upload_dir['baseurl'] . "/screenshots/{$site_details->site}_{$site_details->id}/production/screenshot-800.png";
		}

		if ( $site_details->environments[1]['updates_exclude_themes'] ) {
			$site_details->environments[1]['updates_exclude_themes'] = explode( ',', $site_details->environments[1]['updates_exclude_themes'] );
		} else {
			$site_details->environments[1]['updates_exclude_themes'] = array();
		}
		if ( $site_details->environments[1]['updates_exclude_plugins'] ) {
			$site_details->environments[1]['updates_exclude_plugins'] = explode( ',', $site_details->environments[1]['updates_exclude_plugins'] );
		} else {
			$site_details->environments[1]['updates_exclude_plugins'] = array();
		}

		if ( $site_details->environments[1]['themes'] == '' ) {
			$site_details->environments[1]['themes'] = array();
		}
		if ( $site_details->environments[1]['plugins'] == '' ) {
			$site_details->environments[1]['plugins'] = array();
		}

		if ( $site_details->provider == 'kinsta' ) {
			$site_details->environments[1]['ssh'] = "ssh ${staging_username}@${staging_address} -p ${staging_port}";
		}
		if ( $site_details->provider == 'kinsta' and $environments[1]->database_username ) {
			$kinsta_ending = array_pop( explode(".", $site_details->environments[1]['address']) );
			if ( $kinsta_ending != "com" && $$kinsta_ending != "cloud" ) {
				$kinsta_ending = "cloud";
			}
			$site_details->environments[1]['database']          = "https://mysqleditor-staging-${database_username}.kinsta.{$kinsta_ending}";
			$site_details->environments[1]['database_username'] = $environments[1]->database_username;
			$site_details->environments[1]['database_password'] = $environments[1]->database_password;
		}

		return $site_details;

	}

	public function create( $site ) {

		// Work with array as PHP object
		$site = (object) $site;

		// Prep for response to return
		$response = array( "errors" => array() );

		// Pull in current user
		$current_user = wp_get_current_user();

		// Validate
		if ( $site->domain == '' ) {
			$response['errors'][] = "Error: Domain can't be empty.";
		}
		if ( $site->site == '' ) {
			$response['errors'][] = "Error: Site can't be empty.";
		}
		if ( ! ctype_alnum ( $site->site ) ) {
			$response['errors'][] = "Error: Site does not consist of all letters or digits.";
		}
		if ( strlen($site->site) < 3 ) {
			$response['errors'][] = "Error: Site length less then 3 characters.";
		}
		if ( $site->environments[0]['address'] == "" ) {
			$response['errors'][] = "Error: Production environment address can't be empty.";
		}
		if ( $site->environments[0]['username'] == "" ) {
			$response['errors'][] = "Error: Production environment username can't be empty.";
		}
		if ( $site->environments[0]['protocol'] == "" ) {
			$response['errors'][] = "Error: Production environment protocol can't be empty.";
		}
		if ( $site->environments[0]['port'] == "" ) {
			$response['errors'][] = "Error: Production environment port can't be empty.";
		}
		if ( $site->environments[0]['port'] != "" and ! ctype_digit( $site->environments[0]['port'] ) ) {
			$response['errors'][] = "Error: Production environment port can only be numbers.";
		}

		if ( $site->environments[1]['port'] and ! ctype_digit( $site->environments[1]['port'] ) ) {
			$response['errors'][] = "Error: Staging environment port can only be numbers.";
		}
		
		
		// Hunt for conflicting site names
		$arguments = array(
			'fields'         => 'ids',
			'post_type'      => 'captcore_website',
			'posts_per_page' => '-1',
			'meta_query'     => array(
				'relation' => 'AND',
				array(
					'key'     => 'site',
					'value'   => $site->site,
					'compare' => '=',
				),
				array(
					'key'     => 'status',
					'value'   => 'closed',
					'compare' => '!=',
				),
			),
		);

		$site_check = get_posts( $arguments ); 

		if ( count( $site_check ) > 0 ) {
			$response['errors'][] = "Error: Site name needs to be unique.";
		}

		if ( count($response['errors']) > 0 ) {
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
			update_field( 'provider', $site->provider, $site_id );
			update_field( 'customer', $site->customers, $site_id );
			update_field( 'partner', array_column( $site->shared_with, 'customer_id' ), $site_id );
			update_field( 'updates_enabled', $site->updates_enabled, $site_id );
			update_field( 'status', 'active', $site_id );

			if ( get_field( 'launch_date', $site_id ) == '' ) {

				// No date was entered for Launch Date, assign to today.
				update_field( 'launch_date', date( 'Ymd' ), $site_id );

			}

			$db_environments = new environments();

			$environment = array(
				'site_id'                 => $site_id,
				'environment'             => 'Production',
				'address'                 => $site->environments[0]['address'],
				'username'                => $site->environments[0]['username'],
				'password'                => $site->environments[0]['password'],
				'protocol'                => $site->environments[0]['protocol'],
				'port'                    => $site->environments[0]['port'],
				'home_directory'          => $site->environments[0]['home_directory'],
				'database_username'       => $site->environments[0]['database_username'],
				'database_password'       => $site->environments[0]['database_password'],
				'updates_enabled'         => $site->environments[0]['updates_enabled'],
				'updates_exclude_plugins' => $site->environments[0]['updates_exclude_plugins'],
				'updates_exclude_themes'  => $site->environments[0]['updates_exclude_themes'],
				'offload_enabled'         => $site->environments[0]['offload_enabled'],
				'offload_provider'        => $site->environments[0]['offload_provider'],
				'offload_access_key'      => $site->environments[0]['offload_access_key'],
				'offload_secret_key'      => $site->environments[0]['offload_secret_key'],
				'offload_bucket'          => $site->environments[0]['offload_bucket'],
				'offload_path'            => $site->environments[0]['offload_path'],
			);

			$time_now                  = date( 'Y-m-d H:i:s' );
			$environment['created_at'] = $time_now;
			$environment['updated_at'] = $time_now;
			$environment_id            = $db_environments->insert( $environment );
			update_field( 'environment_production_id', $environment_id, $site_id );

			$environment = array(
				'site_id'                 => $site_id,
				'environment'             => 'Staging',
				'address'                 => $site->environments[1]['address'],
				'username'                => $site->environments[1]['username'],
				'password'                => $site->environments[1]['password'],
				'protocol'                => $site->environments[1]['protocol'],
				'port'                    => $site->environments[1]['port'],
				'home_directory'          => $site->environments[1]['home_directory'],
				'database_username'       => $site->environments[1]['database_username'],
				'database_password'       => $site->environments[1]['database_password'],
				'updates_enabled'         => $site->environments[1]['updates_enabled'],
				'updates_exclude_plugins' => $site->environments[1]['updates_exclude_plugins'],
				'updates_exclude_themes'  => $site->environments[1]['updates_exclude_themes'],
				'offload_enabled'         => $site->environments[1]['offload_enabled'],
				'offload_provider'        => $site->environments[1]['offload_provider'],
				'offload_access_key'      => $site->environments[1]['offload_access_key'],
				'offload_secret_key'      => $site->environments[1]['offload_secret_key'],
				'offload_bucket'          => $site->environments[1]['offload_bucket'],
				'offload_path'            => $site->environments[1]['offload_path'],
			);

			$time_now                  = date( 'Y-m-d H:i:s' );
			$environment['created_at'] = $time_now;
			$environment['updated_at'] = $time_now;
			$environment_id            = $db_environments->insert( $environment );
			update_field( 'environment_staging_id', $environment_id, $site_id );

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
			$response['response'] = 'Error: Site ID not found.';
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

		if ( is_wp_error( $site_id ) ) {
			$errors                          = $site_id->get_error_messages();
				return $response['response'] = implode( ' ', $errors );
		}

		if ( $site_id ) {

			$response['response'] = 'Successfully updated site';
			$response['site_id']  = $site_id;

			// add in ACF fields
			update_field( 'customer', $site->customer["customer_id"], $site_id );
			update_field( 'partner', array_column( $site->shared_with, 'customer_id' ), $site_id );
			update_field( 'provider', $site->provider, $site_id );

			// update_field( 'status', 'active', $site_id );
			if ( get_field( 'launch_date', $site_id ) == '' ) {
				// No date was entered for Launch Date, assign to today.
				update_field( 'launch_date', date( 'Ymd' ), $site_id );
			}

			// Fetch relating environments
			$db_environments = new environments();

			$environment = array(
				'address'                 => $site->environments[0]['address'],
				'username'                => $site->environments[0]['username'],
				'password'                => $site->environments[0]['password'],
				'protocol'                => $site->environments[0]['protocol'],
				'port'                    => $site->environments[0]['port'],
				'home_directory'          => $site->environments[0]['home_directory'],
				'database_username'       => $site->environments[0]['database_username'],
				'database_password'       => $site->environments[0]['database_password'],
				'offload_enabled'         => $site->environments[0]['offload_enabled'],
				'offload_access_key'      => $site->environments[0]['offload_access_key'],
				'offload_secret_key'      => $site->environments[0]['offload_secret_key'],
				'offload_bucket'          => $site->environments[0]['offload_bucket'],
				'offload_path'            => $site->environments[0]['offload_path'],
				'updates_enabled'         => $site->environments[0]['updates_enabled'],
				'updates_exclude_plugins' => $site->environments[0]['updates_exclude_plugins'],
				'updates_exclude_themes'  => $site->environments[0]['updates_exclude_themes'],
			);

			$environment_id = get_field( 'environment_production_id', $site_id );
			$db_environments->update( $environment, array( 'environment_id' => $environment_id ) );

			$environment = array(
				'address'                 => $site->environments[1]['address'],
				'username'                => $site->environments[1]['username'],
				'password'                => $site->environments[1]['password'],
				'protocol'                => $site->environments[1]['protocol'],
				'port'                    => $site->environments[1]['port'],
				'home_directory'          => $site->environments[1]['home_directory'],
				'database_username'       => $site->environments[1]['database_username'],
				'database_password'       => $site->environments[1]['database_password'],
				'offload_enabled'         => $site->environments[1]['offload_enabled'],
				'offload_access_key'      => $site->environments[1]['offload_access_key'],
				'offload_secret_key'      => $site->environments[1]['offload_secret_key'],
				'offload_bucket'          => $site->environments[1]['offload_bucket'],
				'offload_path'            => $site->environments[1]['offload_path'],
				'updates_enabled'         => $site->environments[1]['updates_enabled'],
				'updates_exclude_plugins' => $site->environments[1]['updates_exclude_plugins'],
				'updates_exclude_themes'  => $site->environments[1]['updates_exclude_themes'],
			);

			$environment_id = get_field( 'environment_staging_id', $site_id );
			$db_environments->update( $environment, array( 'environment_id' => $environment_id ) );

		}

		return $response;
	}

	public function delete( $site_id ) {

		// Remove environments attached to site
		// $db_environments = new environments();
		// $environment_id  = get_field( 'environment_production_id', $site_id );
		// $db_environments->delete( $environment_id );
		// $environment_id = get_field( 'environment_staging_id', $site_id );
		// $db_environments->delete( $environment_id );

		// Mark site removed
		update_field( 'closed_date', date( 'Ymd' ), $site_id );
		update_field( 'status', 'closed', $site_id );

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
