<?php

namespace CaptainCore;

class Domain {

    protected $domain_id = "";

    public function __construct( $domain_id = "" ) {
        $this->domain_id = $domain_id;
    }

    public function accounts() {

        $accountdomain = new AccountDomain();
        $account_ids   = ( new Accounts )->account_ids();
        $response      = [];

        // Fetch current records
        $current_account_ids = array_column ( $accountdomain->where( [ "domain_id" => $this->domain_id ] ), "account_id" );
        foreach ( $current_account_ids as $current_account_id ) {
            if ( in_array( $current_account_id, $account_ids ) ) {
                $response[] =[ 
                    "account_id" => $current_account_id,
                    "name"       => \CaptainCore\Accounts::get( $current_account_id )->name
                ];
            }
        }

        return $response;

    }

    public function insert_accounts( $account_ids = [] ) {

        $accountdomain = new AccountDomain();

        foreach( $account_ids as $account_id ) {

            // Fetch current records
            $lookup = $accountdomain->where( [ "domain_id" => $this->domain_id, "account_id" => $account_id ] );

            // Add new record
            if ( count( $lookup ) == 0 ) {
                $accountdomain->insert( [ "domain_id" => $this->domain_id, "account_id" => $account_id ] );
            }

        }

    }

    public function assign_accounts( $account_ids = [] ) {

        $accountdomain = new AccountDomain();

        // Fetch current records
        $current_account_ids = array_column ( $accountdomain->where( [ "domain_id" => $this->domain_id ] ), "account_id" );

        // Removed current records not found new records.
        foreach ( array_diff( $current_account_ids, $account_ids ) as $account_id ) {
            $records = $accountdomain->where( [ "domain_id" => $this->domain_id, "account_id" => $account_id ] );
            foreach ( $records as $record ) {
                $accountdomain->delete( $record->account_domain_id );
            }
        }

        // Add new records
        foreach ( array_diff( $account_ids, $current_account_ids ) as $account_id ) {
            $accountdomain->insert( [ "domain_id" => $this->domain_id, "account_id" => $account_id ] );
        }

    }

    public function fetch_remote_id() {

        $domain = ( new Domains )->get( $this->domain_id );
        
        // Load domains from transient
		$constellix_all_domains = get_transient( 'constellix_all_domains' );

		// If empty then update transient with large remote call
		if ( empty( $constellix_all_domains ) ) {

			$constellix_all_domains = Remote\Constellix::get( 'domains' );

			// Save the API response so we don't have to call again until tomorrow.
			set_transient( 'constellix_all_domains', $constellix_all_domains, HOUR_IN_SECONDS );

		}

		// Search API for domain ID
		foreach ( $constellix_all_domains->data as $item ) {
			if ( $domain->name == $item->name ) {
                $remote_id = $item->id;
                break;
			}
        }
        
        // Generate a new domain zone and add the new domain
        if ( empty( $remote_id ) ) {
            $arguments = [ 'name' => $domain->name ];
            if ( defined( 'CAPTAINCORE_CONSTELLIX_VANITY_ID' ) && defined( 'CAPTAINCORE_CONSTELLIX_SOA_NAME' ) ) {
                $arguments['vanityNameserver'] = CAPTAINCORE_CONSTELLIX_VANITY_ID;
                $arguments['soa']              = [ 
                    "primaryNameserver" => CAPTAINCORE_CONSTELLIX_SOA_NAME,
                    "ttl"               => "86400",
                    "refresh"           => "43200",
                    "retry"             => "3600",
                    "expire"            => "1209600",
                    "negCache"          => "180",
                ];
            }
            $response  = Remote\Constellix::post( 'domains', $arguments );
            if ( ! empty( $response->data ) ) {
                $remote_id = $response->data->id;
            }

        }

        if ( ! empty( $response->errors ) ) {
            return [ "errors" => $response->errors ];
        }
        
        ( new Domains )->update( [ "remote_id" => $remote_id ], [ "domain_id" => $this->domain_id ] );

		return $remote_id;
    }

    public function fetch() {
        $domain = Domains::get( $this->domain_id );
        $details = empty( $domain->details ) ? (object) [] : json_decode( $domain->details ); 
        return [
            "provider"        => self::fetch_remote(),
            "accounts"        => self::accounts(),
            "provider_id"     => $domain->provider_id,
            "connected_sites" => self::connected_sites(),
            "details"         => $details,
        ];
    }

    public function connected_sites() {
        $domain = Domains::get( $this->domain_id );
        $details = empty( $domain->details ) ? (object) [] : json_decode( $domain->details ); 
        
        // New logic starts here
        $domain_accounts = self::accounts();
        $connected_sites = [];
        $seen_site_ids   = []; // Use this to get unique site IDs first.

        if ( ! empty( $domain_accounts ) ) {
            foreach ( $domain_accounts as $account ) {
                $account_id = $account['account_id'];
                // Use admin 'true' flag to ensure Account class can fetch sites
                // `sites()` returns array of [ 'site_id' => ..., 'name' => ... ]
                $account_sites = ( new \CaptainCore\Account( $account_id, true ) )->sites(); 
                
                if ( ! empty( $account_sites ) ) {
                    foreach ( $account_sites as $site ) {
                        // Ensure site list is unique
                        if ( ! isset( $seen_site_ids[ $site['site_id'] ] ) ) {
                            $seen_site_ids[ $site['site_id'] ] = $site['name'];
                        }
                    }
                }
            }
        }

        // Now, loop through the unique site IDs and get their environments
        if ( ! empty( $seen_site_ids ) ) {
            foreach ( $seen_site_ids as $site_id => $site_name ) {
                // Fetch environments for this site
                $environments = ( new \CaptainCore\Environments )->where( [ "site_id" => $site_id ] );
                
                if ( ! empty( $environments ) ) {
                    foreach($environments as $env) {
                        // Add an entry for each environment
                        $connected_sites[] = [
                            "id" => $site_id, // Match JS 'site.id'
                            "name" => $site_name,
                            "environment" => $env->environment,
                        ];
                    }
                }
            }
        }

        // Sort the results: Primary by name (ASC), Secondary by environment (DESC - so Prod comes before Stage)
        if ( ! empty( $connected_sites ) ) {
            $names = array_column( $connected_sites, 'name' );
            $envs  = array_column( $connected_sites, 'environment' );
            // Sort by name ASC, then environment DESC
            array_multisort( $names, SORT_ASC, $envs, SORT_ASC, $connected_sites );
        }

        return $connected_sites;
    }

    public function fetch_remote() {
        $domain        = Domains::get( $this->domain_id );
        if ( empty( $domain->provider_id ) ) {
            return [ "errors" => [ "No remote domain found." ] ];
        }
        $provider      = Providers::get( $domain->provider_id );
        if ( $provider->provider == "hoverdotcom" ) {
            
            if ( empty( get_transient( 'captaincore_hovercom_auth' ) ) ) {
                ( new Domains )->provider_login();
            }
            $auth = get_transient( 'captaincore_hovercom_auth' );
            $args = [
                'headers' => [
                    'Cookie' => 'hoverauth=' . $auth
                ]
            ];

            $response = wp_remote_get( "https://www.hover.com/api/control_panel/domains/{$domain->name}", $args );
            if ( is_wp_error( $response ) ) {
                return json_decode( $response->get_error_message() );
            } else {
                $response    = json_decode( $response['body'] )->domain;
                $nameservers = [];
                foreach( $response->nameservers as $nameserver ) {
                    $nameservers[] = [ "value" => $nameserver ];
                }
                $domain   = [
                    "domain"        => $response->name,
                    "nameservers"   => $nameservers,
                    "contacts"      => [
                        "owner"   => $response->owner,
                        "admin"   => $response->admin,
                        "billing" => $response->billing,
                        "tech"    => $response->tech,
                    ],
                    "locked"        => $response->locked,
                    "whois_privacy" => $response->whois_privacy,
                    "status"        => $response->status,
                ];
                return $domain;
            }
        }

        if ( $provider->provider == "spaceship" ) {
            $response             = \CaptainCore\Remote\Spaceship::get( "domains/{$domain->name}" );
            $owner                = \CaptainCore\Remote\Spaceship::get( "contacts/{$response->contacts->registrant}" );
            $admin                = $response->contacts->registrant == $response->contacts->admin ? $owner : \CaptainCore\Remote\Spaceship::get( "contacts/{$response->contacts->admin}" );
            $billing              = $response->contacts->registrant == $response->contacts->billing ? $owner : \CaptainCore\Remote\Spaceship::get( "contacts/{$response->contacts->billing}" );
            $tech                 = $response->contacts->registrant == $response->contacts->tech ? $owner : \CaptainCore\Remote\Spaceship::get( "contacts/{$response->contacts->tech}" );
            $owner->first_name    = $owner->firstName;
            $owner->last_name     = $owner->lastName;
            $owner->org_name      = $owner->organization;
            $owner->state         = $owner->stateProvince;
            $owner->postal_code   = $owner->postalCode;
            $admin->first_name    = $admin->firstName;
            $admin->last_name     = $admin->lastName;
            $admin->org_name      = $admin->organization;
            $admin->state         = $admin->stateProvince;
            $admin->postal_code   = $admin->postalCode;
            $billing->first_name  = $billing->firstName;
            $billing->last_name   = $billing->lastName;
            $billing->org_name    = $billing->organization;
            $billing->state       = $billing->stateProvince;
            $billing->postal_code = $billing->postalCode;
            $tech->first_name     = $tech->firstName;
            $tech->last_name      = $tech->lastName;
            $tech->org_name       = $tech->organization;
            $tech->state          = $tech->stateProvince;
            $tech->postal_code    = $tech->postalCode;
            $domain   = [
                "domain"        => empty( $response->name ) ? "" : $response->name,
                "nameservers"   => empty( $response->nameservers->hosts ) ? [] : array_map(function ($host) { return ["value" => $host]; }, $response->nameservers->hosts),
                "contacts"      => [
                    "owner"   => $owner,
                    "admin"   => $admin,
                    "billing" => $billing,
                    "tech"    => $tech,
                ],
                "locked"        => empty( $response->eppStatuses ) ? "off" : "on",
                "whois_privacy" => $response->privacyProtection->level == "high" ? "on" : "off",
                "status"        => $response->lifecycleStatus,
            ];
            unset( $domain['contacts']["owner"]->firstName );
            unset( $domain['contacts']["owner"]->lastName );
            unset( $domain['contacts']["owner"]->organization );
            unset( $domain['contacts']["owner"]->stateProvince );
            unset( $domain['contacts']["owner"]->postalCode );
            unset( $domain['contacts']["admin"]->firstName );
            unset( $domain['contacts']["admin"]->lastName );
            unset( $domain['contacts']["admin"]->organization );
            unset( $domain['contacts']["admin"]->stateProvince );
            unset( $domain['contacts']["admin"]->postalCode );
            unset( $domain['contacts']["billing"]->firstName );
            unset( $domain['contacts']["billing"]->lastName );
            unset( $domain['contacts']["billing"]->organization );
            unset( $domain['contacts']["billing"]->stateProvince );
            return $domain;
        }

    }

    public function activate_email_forwarding( $overwrite_mx = false ) {
        $domain = ( new Domains )->get( $this->domain_id );
        $details = empty( $domain->details ) ? (object) [] : json_decode( $domain->details );

        if ( ! empty( $details->forward_email_id ) ) {
            return new \WP_Error( 'already_active', 'Email forwarding is already active for this domain.' );
        }

        // Check if domain already exists and is verified on Forward Email
        $existing_domain_response = \CaptainCore\Remote\ForwardEmail::get( "domains/{$domain->name}" );

        if ( !is_wp_error( $existing_domain_response ) && !isset( $existing_domain_response->message ) && isset( $existing_domain_response->id ) ) {
            // Domain exists on Forward Email.
            
            // Check if it's already fully verified.
            if ( $existing_domain_response->has_mx_record && $existing_domain_response->has_txt_record ) {
                // Domain exists and is verified. Just link it.
                $details->forward_email_id = $existing_domain_response->id;
                ( new Domains )->update( [ "details" => json_encode( $details ) ], [ "domain_id" => $this->domain_id ] );
                
                // Return the domain object, just as if we had activated it.
                return $existing_domain_response; 
            }
            
            // Domain exists but is NOT verified. We need to proceed with DNS setup.
            // We can skip the "create" step later by using this response object.
            $response = $existing_domain_response;
            
        } else {
            // Domain does not exist on Forward Email. We will need to create it.
            $response = null; 
        }

        // 1. Check for DNS conflicts *before* calling the Forward Email API
        $constellix_domain = ( new Domains )->get( $this->domain_id );
        $at_mx_records = [];
        $existing_at_txt_record = null; 
        $has_existing_at_mx = false;

        if ( ! empty( $constellix_domain->remote_id ) ) {
            
            $all_records_response = \CaptainCore\Remote\Constellix::get( "domains/{$constellix_domain->remote_id}/records" );
            
            if ( is_object( $all_records_response ) && isset( $all_records_response->data ) && is_array( $all_records_response->data ) ) {
                foreach ( $all_records_response->data as $record ) {
                    if ( $record->type === 'MX' && $record->name === "" ) { 
                     $at_mx_records[] = $record;
                    }
                    if ( $record->type === 'TXT' && $record->name === "" ) { 
                        $existing_at_txt_record = $record; 
                    }
                }
            }
            
            $has_existing_at_mx = ( count( $at_mx_records ) > 0 );

            if ( $has_existing_at_mx && ! $overwrite_mx ) {
                return new \WP_Error( 'mx_conflict', 'Existing MX records found. Please confirm to overwrite.', [ 'status' => 409 ] );
            }
        }

        // 2. No conflicts (or user approved overwrite), proceed with Forward Email API
        if ( is_null($response) ) {
            $response = \CaptainCore\Remote\ForwardEmail::post( "domains", [ "domain" => $domain->name ] );

            if ( is_wp_error( $response ) || isset( $response->message ) ) {
                $existing_domain_response_fallback = \CaptainCore\Remote\ForwardEmail::get( "domains/{$domain->name}" );
                if ( !is_wp_error( $existing_domain_response_fallback ) && !isset( $existing_domain_response_fallback->message ) && isset( $existing_domain_response_fallback->id ) ) {
                    $response = $existing_domain_response_fallback;
                } else {
                    return new \WP_Error( 'api_error', $response->message ?? 'Failed to create or retrieve domain on Forward Email.' );
                }
            }
        }

        if ( empty( $response->id ) || empty( $response->verification_record ) ) {
            return new \WP_Error( 'api_response_invalid', 'Invalid response from Forward Email API.' );
        }

        $forward_email_id = $response->id;
        $verification_record = $response->verification_record;

        // 3. Apply DNS changes (if managed)
        if ( ! empty( $constellix_domain->remote_id ) ) {
            
            // 3a. Add TXT Verification Record
            $record_name = ""; // '@' record
            // *** FIX: Add quotes to the TXT value, as required by Constellix ***
            $record_value = "\"forward-email-site-verification={$verification_record}\"";
            
            // We already found the $existing_at_txt_record in Step 1
            if ( $existing_at_txt_record ) {
                // UPDATE existing '@' TXT record
                $record_id = $existing_at_txt_record->id;
                $current_values = $existing_at_txt_record->value; 
                
                $value_exists = false;
                foreach ( $current_values as $value_obj ) {
                    // Check if the *exact* quoted string already exists
                    if ( $value_obj->value === $record_value ) {
                        $value_exists = true;
                        break;
                    }
                }

                if ( ! $value_exists ) {
                    // Value does not exist, append it as an object
                    $new_value_obj = (object) [ 'value' => $record_value, 'enabled' => true ];
                    $current_values[] = $new_value_obj;
                    
                    // The formatter will now use the raw values (with quotes)
                    $formatted_data = self::_format_dns_record_for_api( 'txt', $record_name, $current_values, 3600 );
                    $dns_response = \CaptainCore\Remote\Constellix::put( "domains/{$constellix_domain->remote_id}/records/{$record_id}", $formatted_data );
                    error_log( json_encode( $dns_response ) );
                    
                    if ( ! empty( $dns_response->errors ) ) {
                        error_log( 'CaptainCore: Failed to UPDATE Forward Email TXT record on Constellix for domain ' . $domain->name . ': ' . json_encode( $dns_response->errors ) );
                    }
                }

            } else {
                // CREATE new '@' TXT record
                // *** FIX: Add quotes to the TXT value here too ***
                $record_data_value = [ [ 'value' => $record_value, 'enabled' => true ] ];
                $formatted_data = self::_format_dns_record_for_api( 'txt', $record_name, $record_data_value, 3600 );
                $dns_response = \CaptainCore\Remote\Constellix::post( "domains/{$constellix_domain->remote_id}/records", $formatted_data );
                
                if ( ! empty( $dns_response->errors ) ) {
                    error_log( 'CaptainCore: Failed to CREATE Forward Email TXT record on Constellix for domain ' . $domain->name . ': ' . json_encode( $dns_response->errors ) );
                }
            }

            // 3b. Handle MX Records
            // We already have $has_existing_at_mx and $at_mx_records from Step 1
            if ( $has_existing_at_mx && $overwrite_mx ) {
                // User confirmed overwrite, delete existing '@' MX records.
                foreach ( $at_mx_records as $record ) {
                    \CaptainCore\Remote\Constellix::delete( "domains/{$constellix_domain->remote_id}/records/mx/{$record->id}" );
                }
            }

            // Add new Forward Email MX records (if none existed, or if we just deleted them)
            if ( !$has_existing_at_mx || $overwrite_mx ) {
                $new_mx_records = [
                    [ 'priority' => 10, 'server' => 'mx1.forwardemail.net.' ],
                    [ 'priority' => 10, 'server' => 'mx2.forwardemail.net.' ],
                ];
                $mx_record_data = [
                    'name'  => '', // '@' record
                    'type'  => 'mx',
                    'ttl'   => 3600,
                    'value' => $new_mx_records,
                ];
                $formatted_mx_data = self::_format_dns_record_for_api( 'mx', '', $mx_record_data['value'], 3600 );
                $mx_dns_response = \CaptainCore\Remote\Constellix::post( "domains/{$constellix_domain->remote_id}/records", $formatted_mx_data );

                if ( ! empty( $mx_dns_response->errors ) ) {
                    error_log( 'CaptainCore: Failed to add Forward Email MX records to Constellix for domain ' . $domain->name . ': ' . json_encode( $mx_dns_response->errors ) );
                }
            }
        }

        // 4. Save the Forward Email ID to the domain details (only after success)
        $details->forward_email_id = $forward_email_id;
        \CaptainCore\Remote\ForwardEmail::get( "domains/{$forward_email_id}/verify-records" );
        ( new Domains )->update( [ "details" => json_encode( $details ) ], [ "domain_id" => $this->domain_id ] );

        return $response;
    }

    public function get_email_forwards() {
        $domain = ( new Domains )->get( $this->domain_id );
        if ( empty( $domain->name ) ) {
            return new \WP_Error( 'no_domain', 'Domain not found.' );
        }
        return \CaptainCore\Remote\ForwardEmail::get( "domains/{$domain->name}/aliases" );
    }

    public function add_email_forward( $alias_input ) {
        $domain = ( new Domains )->get( $this->domain_id );
        if ( empty( $domain->name ) ) {
            return new \WP_Error( 'no_domain', 'Domain not found.' );
        }
        return \CaptainCore\Remote\ForwardEmail::post( "domains/{$domain->name}/aliases", $alias_input );
    }

    public function update_email_forward( $alias_id, $alias_input ) {
        $domain = ( new Domains )->get( $this->domain_id );
        if ( empty( $domain->name ) ) {
            return new \WP_Error( 'no_domain', 'Domain not found.' );
        }
        return \CaptainCore\Remote\ForwardEmail::put( "domains/{$domain->name}/aliases/{$alias_id}", $alias_input );
    }

    public function delete_email_forward( $alias_id ) {
        $domain = ( new Domains )->get( $this->domain_id );
        if ( empty( $domain->name ) ) {
            return new \WP_Error( 'no_domain', 'Domain not found.' );
        }
        return \CaptainCore\Remote\ForwardEmail::delete( "domains/{$domain->name}/aliases/{$alias_id}" );
    }

    /**
	 * Formats DNS record data for the Constellix API.
	 * (Internal helper copied from captaincore.php)
	 */
	private static function _format_dns_record_for_api( $record_type, $record_name, $record_value, $record_ttl ) {
		$record_type = strtolower( $record_type );
		$post_data   = [
			'name' => $record_name,
			'type' => $record_type,
			'ttl'  => $record_ttl,
		];

		if ( in_array( $record_type, [ 'a', 'aaaa', 'aname', 'cname', 'txt', 'spf' ] ) ) {
			$records = [];
			foreach ( (array) $record_value as $record ) {
                $record_obj = (object) $record;
				$records[] = [
					'value'   => $record_obj->value,
					'enabled' => $record_obj->enabled ?? true,
				];
			}
			$post_data['value'] = $records;
		} elseif ( $record_type == 'mx' ) {
			$mx_records = [];
			foreach ( (array) $record_value as $mx_record ) {
                $mx_record_obj = (object) $mx_record;
				$mx_records[] = [
					'server'   => $mx_record_obj->server, 
					'priority' => $mx_record_obj->priority, 
					'enabled'  => $mx_record_obj->enabled ?? true, // Preserve enabled state
				];
			}
			$post_data['value'] = $mx_records;
		} elseif ( $record_type == 'srv' ) {
			$srv_records = [];
			foreach ( (array) $record_value as $srv_record ) {
                $srv_record_obj = (object) $srv_record;
				$srv_records[] = [
					'host'     => $srv_record_obj->host, 
					'priority' => $srv_record_obj->priority, 
					'weight'   => $srv_record_obj->weight, 
					'port'     => $srv_record_obj->port, 
					'enabled'  => $srv_record_obj->enabled ?? true, // Preserve enabled state
				];
			}
			$post_data['value'] = $srv_records;
		} elseif ( $record_type == 'http' ) {
            $url = is_object($record_value) ? $record_value->url : $record_value;
			$post_data['value'] = [
				'hard'         => true,
				'url'          => $url,
				'redirectType' => '301',
			];
		} else {
			$post_data['value'] = $record_value;
		}

		return $post_data;
	}

    public function auth_code() {
        $domain        = ( new Domains )->get( $this->domain_id );
        if ( empty( $domain->provider_id ) ) {
            return [ "errors" => [ "No remote domain found." ] ];
        }
        $provider      = Providers::get( $domain->provider_id );
        if ( $provider->provider == "hoverdotcom" ) {
            if ( empty( get_transient( 'captaincore_hovercom_auth' ) ) ) {
                ( new Domains )->provider_login();
            }
            $auth = get_transient( 'captaincore_hovercom_auth' );
            $args = [
                'headers' => [
                    'Cookie' => 'hoverauth=' . $auth
                ]
            ];

            $response = wp_remote_get( "https://www.hover.com/api/domains/{$domain->name}/auth_code", $args );
            if ( is_wp_error( $response ) ) {
                return json_decode( $response->get_error_message() );
            }
            $response = json_decode( $response['body'] );
            if ( empty( $response->auth_code ) ) {
                return "";
            }
            return $response->auth_code;
        }
        if ( $provider->provider == "spaceship" ) {
            return \CaptainCore\Remote\Spaceship::get( "domains/{$domain->name}/transfer/auth-code" )->authCode;
        }
    }

    public function lock() {
        $domain        = ( new Domains )->get( $this->domain_id );
        if ( empty( $domain->provider_id ) ) {
            return [ "errors" => [ "No remote domain found." ] ];
        }
        $provider      = Providers::get( $domain->provider_id );
        if ( $provider->provider == "hoverdotcom" ) {
            if ( empty( get_transient( 'captaincore_hovercom_auth' ) ) ) {
                ( new Domains )->provider_login();
            }
            $auth = get_transient( 'captaincore_hovercom_auth' );
            $data = [ 
                'timeout' => 45,
                'headers' => [
                    'Content-Type' => 'application/json; charset=utf-8',
                    'Cookie' => 'hoverauth=' . $auth
                ],
                'body'        => json_encode( [ 
                    "field" => "locked", 
                    'value' => true
                ] ), 
                'method'      => 'PUT', 
                'data_format' => 'body',
            ];

            $response = wp_remote_request( "https://www.hover.com/api/control_panel/domains/domain-{$domain->name}", $data );
            echo json_encode(  $response );
            if ( is_wp_error( $response ) ) {
                return json_decode( $response->get_error_message() );
            } else {
                return json_decode( $response['body'] );
            }
        }
        if ( $provider->provider == "spaceship" ) {
            return \CaptainCore\Remote\Spaceship::put( "domains/{$domain->name}/transfer/lock", [ "isLocked" => true ] );
        }
    }

    public function unlock() {
        $domain        = ( new Domains )->get( $this->domain_id );
        if ( empty( $domain->provider_id ) ) {
            return [ "errors" => [ "No remote domain found." ] ];
        }
        $provider      = Providers::get( $domain->provider_id );
        if ( $provider->provider == "hoverdotcom" ) {
            if ( empty( get_transient( 'captaincore_hovercom_auth' ) ) ) {
                ( new Domains )->provider_login();
            }
            $auth = get_transient( 'captaincore_hovercom_auth' );
            $data = [ 
                'timeout' => 45,
                'headers' => [
                    'Content-Type' => 'application/json; charset=utf-8',
                    'Cookie' => 'hoverauth=' . $auth
                ],
                'body'        => json_encode( [ 
                    "field" => "locked", 
                    'value' => false
                ] ), 
                'method'      => 'PUT', 
                'data_format' => 'body',
            ];

            $response = wp_remote_request( "https://www.hover.com/api/control_panel/domains/domain-{$domain->name}", $data );

            if ( is_wp_error( $response ) ) {
                return json_decode( $response->get_error_message() );
            } else {
                return json_decode( $response['body'] );
            }
        }
        if ( $provider->provider == "spaceship" ) {
            return \CaptainCore\Remote\Spaceship::put( "domains/{$domain->name}/transfer/lock", [ "isLocked" => false ] );
        }
    }

    public function privacy_on() {
        $domain        = ( new Domains )->get( $this->domain_id );
        if ( empty( $domain->provider_id ) ) {
            return [ "errors" => [ "No remote domain found." ] ];
        }
        $provider      = Providers::get( $domain->provider_id );
        if ( $provider->provider == "hoverdotcom" ) {
            if ( empty( get_transient( 'captaincore_hovercom_auth' ) ) ) {
                ( new Domains )->provider_login();
            }
            $auth = get_transient( 'captaincore_hovercom_auth' );
            $data = [ 
                'timeout' => 45,
                'headers' => [
                    'Content-Type' => 'application/json; charset=utf-8',
                    'Cookie' => 'hoverauth=' . $auth
                ],
                'body'      => json_encode( [ 
                    "field" => "whois_privacy", 
                    'value' => true
                ] ), 
                'method'      => 'PUT', 
                'data_format' => 'body',
            ];

            $response = wp_remote_request( "https://www.hover.com/api/control_panel/domains/domain-{$domain->name}", $data );
            echo json_encode(  $response );
            if ( is_wp_error( $response ) ) {
                return json_decode( $response->get_error_message() );
            }
            return json_decode( $response['body'] );
        }
        if ( $provider->provider == "spaceship" ) {
            return \CaptainCore\Remote\Spaceship::put( "domains/{$domain->name}/privacy/preference", [ "privacyLevel" => "high", "userConsent" => true ] );
        }
    }

    public function privacy_off() {
        $domain        = ( new Domains )->get( $this->domain_id );
        if ( empty( $domain->provider_id ) ) {
            return [ "errors" => [ "No remote domain found." ] ];
        }
        $provider      = Providers::get( $domain->provider_id );
        if ( $provider->provider == "hoverdotcom" ) {
            if ( empty( get_transient( 'captaincore_hovercom_auth' ) ) ) {
                ( new Domains )->provider_login();
            }
            $auth = get_transient( 'captaincore_hovercom_auth' );
            $data = [ 
                'timeout' => 45,
                'headers' => [
                    'Content-Type' => 'application/json; charset=utf-8',
                    'Cookie' => 'hoverauth=' . $auth
                ],
                'body'        => json_encode( [ 
                    "field" => "whois_privacy", 
                    'value' => false
                ] ), 
                'method'      => 'PUT', 
                'data_format' => 'body',
            ];

            $response = wp_remote_request( "https://www.hover.com/api/control_panel/domains/domain-{$domain->name}", $data );
            echo json_encode(  $response );
            if ( is_wp_error( $response ) ) {
                return json_decode( $response->get_error_message() );
            } else {
                return json_decode( $response['body'] );
            }
        }
        if ( $provider->provider == "spaceship" ) {
            return \CaptainCore\Remote\Spaceship::put( "domains/{$domain->name}/privacy/preference", [ "privacyLevel" => "public", "userConsent" => true ] );
        }
    }

    public function renew_off() {
        $domain        = ( new Domains )->get( $this->domain_id );
        if ( empty( $domain->provider_id ) ) {
            return [ "errors" => [ "No remote domain found." ] ];
        }
        if ( empty( get_transient( 'captaincore_hovercom_auth' ) ) ) {
            ( new Domains )->provider_login();
        }
        $auth = get_transient( 'captaincore_hovercom_auth' );
        $data = [ 
            'timeout' => 45,
            'headers' => [
                'Content-Type' => 'application/json; charset=utf-8',
                'Cookie' => 'hoverauth=' . $auth
            ],
            'body'        => json_encode( [ 
                "field" => "autorenew", 
                'value' => false
            ] ), 
            'method'      => 'PUT', 
            'data_format' => 'body',
        ];

        $response = wp_remote_request( "https://www.hover.com/api/control_panel/domains/domain-{$domain->name}", $data );
        echo json_encode(  $response );
        if ( is_wp_error( $response ) ) {
            return json_decode( $response->get_error_message() );
        } else {
            return json_decode( $response['body'] );
        }
    }

    public function set_contacts( $contacts = [] ) {
        $domain        = Domains::get( $this->domain_id );
        $contacts      = (object) $contacts;
        if ( empty( $domain->provider_id ) ) {
            return [ "errors" => [ "No remote domain found." ] ];
        }
        $provider      = Providers::get( $domain->provider_id );
        if ( $provider->provider == "hoverdotcom" ) {
            if ( empty( get_transient( 'captaincore_hovercom_auth' ) ) ) {
                ( new Domains )->provider_login();
            }
            $auth = get_transient( 'captaincore_hovercom_auth' );
            $data = [ 
                'timeout' => 45,
                'headers' => [
                    'Content-Type' => 'application/json; charset=utf-8',
                    'Cookie' => 'hoverauth=' . $auth
                ],
                'body'        => json_encode( [
                    "id"       => "domain-{$domain->name}",
                    "contacts" => [
                        "nolock"  => true,
                        "owner"   => $contacts->owner,
                        "admin"   => $contacts->admin,
                        "tech"    => $contacts->tech,
                        "billing" => $contacts->billing,

                    ]
                ] ), 
                'method'      => 'PUT', 
                'data_format' => 'body',
            ];

            $response = wp_remote_request( "https://www.hover.com/api/control_panel/domains/set_contacts", $data );
            if ( is_wp_error( $response ) ) {
                return json_decode( $response->get_error_message() );
            }
            $response = json_decode( $response['body'] );
            if ( ! empty( $response->error ) ) {
                return [ "error" => "There was a problem updating the contact info. Check the formatting and try again." ];
            }
            if ( $response->succeeded == "true" ) {
                return [ "response" => "Contacts have been updated." ];
            }
        }
        if ( $provider->provider == "spaceship" ) {
            $changed              = false;
            $response             = \CaptainCore\Remote\Spaceship::get( "domains/{$domain->name}" );
            $owner                = \CaptainCore\Remote\Spaceship::get( "contacts/{$response->contacts->registrant}" );
            $admin                = $response->contacts->admin == $response->contacts->registrant ? $owner : \CaptainCore\Remote\Spaceship::get( "contacts/{$response->contacts->admin}" );
            $billing              = $response->contacts->billing == $response->contacts->registrant ? $owner : \CaptainCore\Remote\Spaceship::get( "contacts/{$response->contacts->billing}" );
            $tech                 = $response->contacts->tech == $response->contacts->registrant ? $owner : \CaptainCore\Remote\Spaceship::get( "contacts/{$response->contacts->tech}" );
            foreach (['owner', 'admin', 'tech', 'billing'] as $record) {
                if (isset($contacts->$record)) {
                    $new_contact      = $contacts->$record;
                    $existing_contact = ${$record};
                    // Skip if no changes made
                    if ( $existing_contact->firstName == $new_contact->first_name && $existing_contact->lastName == $new_contact->last_name && $existing_contact->email == $new_contact->email && $existing_contact->phone == $new_contact->phone && 
                        $existing_contact->organization == $new_contact->org_name && $existing_contact->address1 == $new_contact->address1 && $existing_contact->address2 == $new_contact->address2 && $existing_contact->stateProvince == $new_contact->state &&
                        $existing_contact->postalCode == $new_contact->postal_code && $existing_contact->city == $new_contact->city && $existing_contact->country == $new_contact->country ) {
                        continue;
                    }
                    $changed = true;
                    $updated_contact = [
                        "firstName"       => $new_contact->first_name,
                        "lastName"        => $new_contact->last_name,
                        "organization"    => $new_contact->org_name,
                        "email"           => $new_contact->email,
                        "address1"        => $new_contact->address1,
                        "address2"        => $new_contact->address2,
                        "city"            => $new_contact->city,
                        "country"         => $new_contact->country,
                        "stateProvince"   => $new_contact->state,
                        "postalCode"      => $new_contact->postal_code,
                        "phone"           => $new_contact->phone
                    ];
                    $response_contact              = \CaptainCore\Remote\Spaceship::put( "contacts", $updated_contact );
                    $response->contacts->{$record} = $response_contact->contactId;
                }
            }
            if ( ! $changed ) {
                return [ "response" => "No changes found." ];
            }
            $results = \CaptainCore\Remote\Spaceship::put( "domains/{$domain->name}/contacts", [
                "registrant" => $response->contacts->registrant,
                "admin"      => $response->contacts->admin,
                "tech"       => $response->contacts->tech,
                "billing"    => $repsonse->contacts->billing
            ] );
            if ( ! empty( $results->data ) ) {
                return [ "error" => "There was a problem updating the contact info. Check the formatting and try again." . json_encode( $results->data ) ];
            }
            return [ "response" => "Contacts have been updated.". json_encode( $results ) ];
        }
    }

    public function set_nameservers( $nameservers = [] ) {
        $domain        = ( new Domains )->get( $this->domain_id );
        if ( empty( $domain->provider_id ) ) {
            return [ "errors" => [ "No remote domain found." ] ];
        }
        $provider      = Providers::get( $domain->provider_id );
        if ( $provider->provider == "hoverdotcom" ) {
            if ( empty( get_transient( 'captaincore_hovercom_auth' ) ) ) {
                ( new Domains )->provider_login();
            }
            $auth = get_transient( 'captaincore_hovercom_auth' );
            $data = [ 
                'timeout' => 45,
                'headers' => [
                    'Content-Type' => 'application/json; charset=utf-8',
                    'Cookie'       => 'hoverauth=' . $auth
                ],
                'body' => json_encode( [ 
                    "field" => "nameservers", 
                    'value' => $nameservers
                ] ), 
                'method'      => 'PUT', 
                'data_format' => 'body',
            ];

            $response = wp_remote_request( "https://www.hover.com/api/control_panel/domains/domain-{$domain->name}", $data );
            if ( is_wp_error( $response ) ) {
                return json_decode( $response->get_error_message() );
            }
            $response = json_decode( $response['body'] );
            if ( ! empty( $response->error ) ) {
                return [ "error" => "There was a problem updating nameservers. Check formatting and try again." ];
            }
            if ( $response->succeeded == "true" ) {
                return [ "response" => "Nameservers have been updated." ];
            }
            if ( $response->succeeded == "false" ) {
                return [ "response" => $response->errors ];
            }
        }
        if ( $provider->provider == "spaceship" ) {
            $response = \CaptainCore\Remote\Spaceship::put( "domains/{$domain->name}/nameservers", [ "provider" => "custom", "hosts" => $nameservers ] );
            if ( empty( $response->hosts ) || ! empty( $response->data ) ) {
                return [ "error" => "There was a problem updating nameservers. Check formatting and try again." ];
            }
            return [ "response" => "Nameservers have been updated." ];
        }
    }

    public static function zone( $domain_id ) {
        $domain      = ( new Domains )->get( $domain_id );
        $domain_info = Remote\Constellix::get( "domains/$domain->remote_id" );
        $records     = Remote\Constellix::get( "domains/$domain->remote_id/records?perPage=100" );
        $steps       = ceil( $records->meta->pagination->total / 100 );
        for ($i = 1; $i < $steps; $i++) {
            $page = $i + 1;
            $additional_records = Remote\Constellix::get( "domains/$domain->remote_id/records?page=$page&perPage=100" );
            $records->data = array_merge($records->data, $additional_records->data);
        }
        $zone        = new \Badcow\DNS\Zone( $domain->name .'.');
        $zone->setDefaultTtl(3600);
        $zone_record = new \Badcow\DNS\ResourceRecord;
        $zone_record->setName( "@" );
        $zone_record->setClass( "IN" );
        $zone_record->setRdata( \Badcow\DNS\Rdata\Factory::Soa(
            $domain_info->data->soa->primaryNameserver,
            $domain_info->data->soa->email,
            $domain_info->data->soa->serial,
            $domain_info->data->soa->refresh,
            $domain_info->data->soa->retry,
            $domain_info->data->soa->expire,
            $domain_info->data->soa->negativeCache
        ));
        $zone->addResourceRecord($zone_record);
        foreach( $domain_info->data->nameservers as $nameserver ) {
            $zone_record = new \Badcow\DNS\ResourceRecord;
            $zone_record->setName( "@" );
            $zone_record->setClass( "IN" );
            $zone_record->setRdata( \Badcow\DNS\Rdata\Factory::Ns( $nameserver . "." ) );
            $zone->addResourceRecord($zone_record);
        }
        foreach( $records->data as $record ) {
            if ( empty( $record->name ) ) {
                $record->name = "@";
            }
            if ( $record->type == "A" ) {
                foreach( $record->value as $value ) {
                    $zone_record = new \Badcow\DNS\ResourceRecord;
                    $zone_record->setName( $record->name );
                    $zone_record->setRdata( \Badcow\DNS\Rdata\Factory::A( $value->value ));
                    $zone->addResourceRecord($zone_record);
                }
            }
            if ( $record->type == "AAAA" ) {
                foreach( $record->value as $value ) {
                    $zone_record = new \Badcow\DNS\ResourceRecord;
                    $zone_record->setName( $record->name );
                    $zone_record->setRdata( \Badcow\DNS\Rdata\Factory::Aaaa( $value->value ));
                    $zone->addResourceRecord($zone_record);
                }
            }
            if ( $record->type == "CNAME" ) {
                foreach( $record->value as $value ) {
                    $zone_record = new \Badcow\DNS\ResourceRecord;
                    $zone_record->setName( $record->name );
                    $zone_record->setRdata( \Badcow\DNS\Rdata\Factory::Cname( $value->value ));
                    $zone->addResourceRecord($zone_record);
                }
            }
            if ( $record->type == "MX" ) {
                foreach( $record->value as $value ) {
                    $zone_record = new \Badcow\DNS\ResourceRecord;
                    $zone_record->setName( $record->name );
                    $zone_record->setRdata( \Badcow\DNS\Rdata\Factory::Mx( $value->priority, $value->server ));
                    $zone->addResourceRecord($zone_record);
                }
            }
            if ( $record->type == "SRV" ) {
                foreach( $record->value as $value ) {
                    $zone_record = new \Badcow\DNS\ResourceRecord;
                    $zone_record->setName( $record->name );
                    $zone_record->setRdata( \Badcow\DNS\Rdata\Factory::Srv( $value->priority, $value->weight, $value->port, $value->host ));
                    $zone->addResourceRecord($zone_record);
                }
            }
            if ( $record->type == "TXT" ) {
                foreach( $record->value as $value ) {
                    $zone_record = new \Badcow\DNS\ResourceRecord;
                    $zone_record->setName( $record->name );
                    $zone_record->setClass('IN');
                    $zone_record->setRdata( \Badcow\DNS\Rdata\Factory::Txt(trim($value->value,'"'), 0, 200));
                    $zone->addResourceRecord($zone_record);
                }
            }
        }

        $builder = new  \Badcow\DNS\AlignedBuilder();
        $builder->addRdataFormatter('TXT', '\CaptainCore\Domain::specialTxtFormatter' );
        $builder->build($zone);
        return $builder->build($zone);
    }

    public static function specialTxtFormatter(\Badcow\DNS\Rdata\TXT $rdata, int $padding): string {
        //If the text length is less than or equal to 50 characters, just return it unaltered.
        if (strlen($rdata->getText()) <= 500) {
            return sprintf('"%s"', addcslashes($rdata->getText(), '"\\'));
        }
    
        $returnVal = "(\n";
        $chunks = str_split($rdata->getText(), 500);
        foreach ($chunks as $chunk) {
            $returnVal .= str_repeat(' ', $padding).
                sprintf('"%s"', addcslashes($chunk, '"\\')).
                "\n";
        }
        $returnVal .= str_repeat(' ', $padding) . ")";
    
        return $returnVal;
    }

}