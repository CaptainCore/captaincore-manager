<?php

namespace CaptainCore;

class Domain {

    protected $domain_id = "";

    public function __construct( $domain_id = "" ) {
        $this->domain_id = $domain_id;
    }

    public function accounts() {

        $accountdomain = new AccountDomain();
        $response      = [];

        // Fetch current records
        $current_account_ids = array_column ( $accountdomain->where( [ "domain_id" => $this->domain_id ] ), "account_id" );
        foreach ( $current_account_ids as $current_account_id ) {
            // Get the account details
            $account = \CaptainCore\Accounts::get( $current_account_id );

            // Ensure the account exists before adding it to the response
            if ( $account ) {
                $response[] =[ 
                    "account_id" => $current_account_id,
                    "name"       => $account->name
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

    public function fetch_remote_id( $link_existing = false ) {

        $domain = ( new Domains )->get( $this->domain_id );
        $response = null;
        $remote_id = null;
        
        // Load domains from transient
		$constellix_all_domains = get_transient( 'constellix_all_domains' );

		// If empty then update transient with large remote call
		if ( empty( $constellix_all_domains ) ) {

			$constellix_all_domains = Remote\Constellix::get( 'domains' );

			// Save the API response so we don't have to call again until tomorrow.
			set_transient( 'constellix_all_domains', $constellix_all_domains, HOUR_IN_SECONDS );

		}

		// Search API for domain ID
        if ( ! empty( $constellix_all_domains->data ) ) {
            foreach ( $constellix_all_domains->data as $item ) {
                if ( $domain->name == $item->name ) {
                    $remote_id = $item->id;
                    break;
                }
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

            // Check if domain already exists error and admin wants to link to it
            if ( ! empty( $response->errors ) && $link_existing ) {
                foreach ( $response->errors as $error ) {
                    // Check for "Domain with name X already exists, Domain Id: Y" error
                    if ( preg_match( '/Domain with name .+ already exists, Domain Id: (\d+)/', $error, $matches ) ) {
                        $remote_id = intval( $matches[1] );
                        // Clear the transient so next lookup will find this domain
                        delete_transient( 'constellix_all_domains' );
                        break;
                    }
                }
            }

        }

        // Only return errors if we still don't have a remote_id
        if ( $response && ! empty( $response->errors ) && empty( $remote_id ) ) {
            return [ "errors" => $response->errors ];
        }
        
        if ( ! empty( $remote_id ) ) {
            ( new Domains )->update( [ "remote_id" => $remote_id ], [ "domain_id" => $this->domain_id ] );
        }

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
            if ( empty( $response ) || empty( $response->contacts ) ) {
                return [ "errors" => [ "Remote domain details not found." ] ];
            }
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

        // Check if already active (using mailgun_forwarding_id)
        if ( ! empty( $details->mailgun_forwarding_id ) ) {
            return new \WP_Error( 'already_active', 'Email forwarding is already active for this domain.' );
        }

        // 1. Check for DNS conflicts before proceeding
        $constellix_domain = ( new Domains )->get( $this->domain_id );
        $at_mx_records = [];
        $existing_txt_records = [];
        $has_existing_at_mx = false;

        if ( ! empty( $constellix_domain->remote_id ) ) {
            
            $all_records_response = \CaptainCore\Remote\Constellix::get( "domains/{$constellix_domain->remote_id}/records" );
            
            if ( is_object( $all_records_response ) && isset( $all_records_response->data ) && is_array( $all_records_response->data ) ) {
                foreach ( $all_records_response->data as $record ) {
                    if ( $record->type === 'MX' && $record->name === "" ) { 
                        $at_mx_records[] = $record;
                    }
                    // Index existing TXT records by name for later lookup
                    if ( $record->type === 'TXT' ) {
                        $existing_txt_records[ $record->name ] = $record;
                    }
                }
            }
            
            $has_existing_at_mx = ( count( $at_mx_records ) > 0 );

            if ( $has_existing_at_mx && ! $overwrite_mx ) {
                return new \WP_Error( 'mx_conflict', 'Existing MX records found. Please confirm to overwrite.', [ 'status' => 409 ] );
            }
        }

        // 2. Check if domain already exists in Mailgun, or create it
        $mailgun_domain = \CaptainCore\Remote\Mailgun::get( "v4/domains/{$domain->name}" );

        if ( ! empty( $mailgun_domain->message ) && $mailgun_domain->message == "Domain not found" ) {
            // Create domain in Mailgun for receiving
            $mailgun_domain = \CaptainCore\Remote\Mailgun::post( "v4/domains", [ 'name' => $domain->name ] );
        }

        if ( empty( $mailgun_domain->domain ) && empty( $mailgun_domain->name ) ) {
            return new \WP_Error( 'api_error', 'Failed to create or retrieve domain on Mailgun.' );
        }

        // 3. Apply DNS changes (if managed via Constellix)
        if ( ! empty( $constellix_domain->remote_id ) ) {
            
            // Handle MX Records - delete existing if user confirmed overwrite
            if ( $has_existing_at_mx && $overwrite_mx ) {
                foreach ( $at_mx_records as $record ) {
                    \CaptainCore\Remote\Constellix::delete( "domains/{$constellix_domain->remote_id}/records/mx/{$record->id}" );
                }
            }

            // Add MX records for receiving (from Mailgun's response)
            if ( ! $has_existing_at_mx || $overwrite_mx ) {
                if ( ! empty( $mailgun_domain->receiving_dns_records ) ) {
                    $mx_records = [];
                    foreach ( $mailgun_domain->receiving_dns_records as $record ) {
                        // Add all MX records from Mailgun (don't skip based on valid status)
                        if ( $record->record_type === 'MX' ) {
                            $mx_records[] = [
                                'server'   => $record->value . '.',
                                'priority' => $record->priority ?? 10,
                                'enabled'  => true,
                            ];
                        }
                    }
                    if ( ! empty( $mx_records ) ) {
                        $formatted_mx_data = self::_format_dns_record_for_api( 'mx', '', $mx_records, 3600 );
                        $mx_dns_response = \CaptainCore\Remote\Constellix::post( "domains/{$constellix_domain->remote_id}/records", $formatted_mx_data );
                        if ( ! empty( $mx_dns_response->errors ) ) {
                            error_log( 'CaptainCore: Failed to add Mailgun MX records for ' . $domain->name . ': ' . json_encode( $mx_dns_response->errors ) );
                        }
                    }
                }
            }

            // Add TXT and CNAME records for sending/verification (from Mailgun's response)
            if ( ! empty( $mailgun_domain->sending_dns_records ) ) {
                foreach ( $mailgun_domain->sending_dns_records as $record ) {
                    $record_name = str_replace( ".{$domain->name}", "", rtrim( $record->name, '.' ) );
                    // If name equals the domain itself, use empty string for root
                    if ( $record_name === $domain->name ) {
                        $record_name = '';
                    }

                    if ( $record->record_type === 'TXT' && $record->valid !== 'valid' ) {
                        // Check if a TXT record with this name already exists
                        if ( isset( $existing_txt_records[ $record_name ] ) ) {
                            // Append to existing TXT record using PUT
                            $existing_record = $existing_txt_records[ $record_name ];
                            $existing_values = $existing_record->value ?? [];
                            
                            // Add the new Mailgun value to existing values
                            $new_values = [];
                            foreach ( $existing_values as $existing_value ) {
                                $new_values[] = [
                                    'value'   => $existing_value->value,
                                    'enabled' => $existing_value->enabled ?? true,
                                ];
                            }
                            $new_values[] = [ 'value' => $record->value, 'enabled' => true ];
                            
                            $txt_data = self::_format_dns_record_for_api( 'txt', $record_name, $new_values, $existing_record->ttl ?? 3600 );
                            $response = \CaptainCore\Remote\Constellix::put( "domains/{$constellix_domain->remote_id}/records/txt/{$existing_record->id}", $txt_data );
                            if ( ! empty( $response->errors ) ) {
                                error_log( 'CaptainCore: Failed to update TXT record for ' . $domain->name . ': ' . json_encode( $response->errors ) );
                            }
                        } else {
                            // Create new TXT record using POST
                            $txt_data = self::_format_dns_record_for_api( 'txt', $record_name, [
                                [ 'value' => $record->value, 'enabled' => true ]
                            ], 3600 );
                            $response = \CaptainCore\Remote\Constellix::post( "domains/{$constellix_domain->remote_id}/records", $txt_data );
                            if ( ! empty( $response->errors ) ) {
                                error_log( 'CaptainCore: Failed to add Mailgun TXT record for ' . $domain->name . ': ' . json_encode( $response->errors ) );
                            }
                        }
                    }

                    if ( $record->record_type === 'CNAME' && $record->valid !== 'valid' ) {
                        $cname_data = self::_format_dns_record_for_api( 'cname', $record_name, [
                            [ 'value' => $record->value . '.', 'enabled' => true ]
                        ], 3600 );
                        $response = \CaptainCore\Remote\Constellix::post( "domains/{$constellix_domain->remote_id}/records", $cname_data );
                        if ( ! empty( $response->errors ) ) {
                            error_log( 'CaptainCore: Failed to add Mailgun CNAME record for ' . $domain->name . ': ' . json_encode( $response->errors ) );
                        }
                    }
                }
            }
        }

        // 4. Trigger Mailgun domain verification
        \CaptainCore\Remote\Mailgun::put( "v4/domains/{$domain->name}/verify" );

        // 5. Schedule a follow-up verification in 60 seconds
        wp_schedule_single_event( time() + 60, 'schedule_mailgun_verify', [ $domain->name ] );

        // 6. Save the Mailgun forwarding ID to domain details
        $details->mailgun_forwarding_id = $mailgun_domain->domain->id ?? $mailgun_domain->id ?? $domain->name;
        ( new Domains )->update( [ "details" => json_encode( $details ) ], [ "domain_id" => $this->domain_id ] );

        // Return a response object for the frontend
        return (object) [
            'id'                => $details->mailgun_forwarding_id,
            'name'              => $domain->name,
            'mailgun_domain'    => $mailgun_domain->domain ?? $mailgun_domain,
            'has_mx_record'     => true,
            'forwarding_active' => true,
        ];
    }

    public function get_email_forwards() {
        $domain = ( new Domains )->get( $this->domain_id );
        if ( empty( $domain->name ) ) {
            return new \WP_Error( 'no_domain', 'Domain not found.' );
        }

        // Get all routes from Mailgun
        $routes_response = \CaptainCore\Remote\Mailgun::get( "v3/routes", [ 'limit' => 1000 ] );
        
        // Check for errors
        if ( ! empty( $routes_response->errors ) ) {
            return new \WP_Error( 'mailgun_error', 'Error fetching routes from Mailgun.', [ 'details' => $routes_response->errors ] );
        }

        if ( empty( $routes_response->items ) ) {
            return [];
        }

        // Filter routes that match this domain and transform to alias format
        $aliases = [];
        $domain_pattern = '/@' . preg_quote( $domain->name, '/' ) . '(["\']|\))/i';
        
        foreach ( $routes_response->items as $route ) {
            // Check if this route's expression matches our domain
            if ( ! empty( $route->expression ) && preg_match( $domain_pattern, $route->expression ) ) {
                $alias = self::_mailgun_route_to_alias( $route, $domain->name );
                if ( $alias ) {
                    $aliases[] = $alias;
                }
            }
        }

        return $aliases;
    }

    public function add_email_forward( $alias_input ) {
        $domain = ( new Domains )->get( $this->domain_id );
        if ( empty( $domain->name ) ) {
            return new \WP_Error( 'no_domain', 'Domain not found.' );
        }

        $alias_input = (object) $alias_input;
        
        // Build the Mailgun route expression
        $alias_name = $alias_input->name ?? '';
        
        if ( $alias_name === '*' || $alias_name === '' ) {
            // Catch-all alias
            $expression = 'match_recipient(".*@' . $domain->name . '")';
        } else {
            // Specific alias
            $expression = 'match_recipient("' . $alias_name . '@' . $domain->name . '")';
        }

        // Build the forward actions
        $actions = [];
        $recipients = $alias_input->recipients ?? [];
        if ( is_string( $recipients ) ) {
            $recipients = array_map( 'trim', explode( ',', $recipients ) );
        }
        
        foreach ( $recipients as $recipient ) {
            $recipient_email = is_object( $recipient ) ? $recipient->address : $recipient;
            $actions[] = 'forward("' . $recipient_email . '")';
        }
        $actions[] = 'stop()';

        // Determine priority (catch-all should be lower priority = higher number)
        $priority = ( $alias_name === '*' || $alias_name === '' ) ? 100 : 0;

        // Create the route in Mailgun
        $route_data = [
            'priority'    => $priority,
            'description' => "Email forward: {$alias_name}@{$domain->name}",
            'expression'  => $expression,
            'action'      => $actions,
        ];

        $response = \CaptainCore\Remote\Mailgun::post( "v3/routes", $route_data );

        if ( empty( $response->route ) && empty( $response->id ) ) {
            return new \WP_Error( 'mailgun_error', 'Failed to create route in Mailgun.', [ 'details' => $response ] );
        }

        // Transform response to alias format for compatibility
        return self::_mailgun_route_to_alias( $response->route ?? $response, $domain->name );
    }

    public function update_email_forward( $route_id, $alias_input ) {
        $domain = ( new Domains )->get( $this->domain_id );
        if ( empty( $domain->name ) ) {
            return new \WP_Error( 'no_domain', 'Domain not found.' );
        }

        $alias_input = (object) $alias_input;

        // Build update data
        $update_data = [];

        // If name is being updated, rebuild the expression
        if ( isset( $alias_input->name ) ) {
            $alias_name = $alias_input->name;
            if ( $alias_name === '*' || $alias_name === '' ) {
                $update_data['expression'] = 'match_recipient(".*@' . $domain->name . '")';
                $update_data['priority'] = 100;
            } else {
                $update_data['expression'] = 'match_recipient("' . $alias_name . '@' . $domain->name . '")';
                $update_data['priority'] = 0;
            }
            $update_data['description'] = "Email forward: {$alias_name}@{$domain->name}";
        }

        // If recipients are being updated, rebuild actions
        if ( isset( $alias_input->recipients ) ) {
            $actions = [];
            $recipients = $alias_input->recipients;
            if ( is_string( $recipients ) ) {
                $recipients = array_map( 'trim', explode( ',', $recipients ) );
            }
            
            foreach ( $recipients as $recipient ) {
                $recipient_email = is_object( $recipient ) ? $recipient->address : $recipient;
                $actions[] = 'forward("' . $recipient_email . '")';
            }
            $actions[] = 'stop()';
            $update_data['action'] = $actions;
        }

        if ( empty( $update_data ) ) {
            return new \WP_Error( 'no_changes', 'No changes provided.' );
        }

        $response = \CaptainCore\Remote\Mailgun::put( "v3/routes/{$route_id}", $update_data );

        if ( empty( $response->id ) && empty( $response->message ) ) {
            return new \WP_Error( 'mailgun_error', 'Failed to update route in Mailgun.', [ 'details' => $response ] );
        }

        return $response;
    }

    public function delete_email_forward( $route_id ) {
        $domain = ( new Domains )->get( $this->domain_id );
        if ( empty( $domain->name ) ) {
            return new \WP_Error( 'no_domain', 'Domain not found.' );
        }

        $response = \CaptainCore\Remote\Mailgun::delete( "v3/routes/{$route_id}" );

        return $response;
    }

    /**
     * Converts a Mailgun route object to the alias format used by the frontend.
     * This maintains compatibility with the existing UI.
     *
     * @param object $route The Mailgun route object.
     * @param string $domain_name The domain name for context.
     * @return object|null The alias object or null if not a valid forward route.
     */
    private static function _mailgun_route_to_alias( $route, $domain_name ) {
        if ( empty( $route ) ) {
            return null;
        }

        // Extract alias name from expression
        // Expression format: match_recipient("alias@domain.com") or match_recipient(".*@domain.com")
        $alias_name = '';
        if ( preg_match( '/match_recipient\(["\'](.+)@' . preg_quote( $domain_name, '/' ) . '["\']\)/', $route->expression, $matches ) ) {
            $alias_name = $matches[1];
            if ( $alias_name === '.*' ) {
                $alias_name = '*'; // Convert regex catch-all to simple asterisk
            }
        }

        // Extract recipients from actions
        // Action format: ["forward(\"target@email.com\")", "stop()"]
        $recipients = [];
        $actions = is_array( $route->actions ) ? $route->actions : [ $route->actions ];
        foreach ( $actions as $action ) {
            if ( preg_match( '/forward\(["\'](.+?)["\']\)/', $action, $matches ) ) {
                $recipients[] = $matches[1]; // Return simple email strings for frontend compatibility
            }
        }

        return (object) [
            'id'          => $route->id,
            'name'        => $alias_name,
            'recipients'  => $recipients,
            'description' => $route->description ?? '',
            'created_at'  => $route->created_at ?? '',
            // Additional fields for compatibility
            'domain'      => $domain_name,
            'expression'  => $route->expression ?? '',
            'priority'    => $route->priority ?? 0,
        ];
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