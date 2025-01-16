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
		foreach ( $constellix_all_domains as $item ) {
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
            $remote_id = $response->data->id;
        }

        if ( ! empty( $response->errors ) ) {
            return [ "errors" => $response->errors ];
        }
        
        ( new Domains )->update( [ "remote_id" => $remote_id ], [ "domain_id" => $this->domain_id ] );

		return $remote_id;
    }

    public function fetch() {
        return [
            "provider" => self::fetch_remote(),
            "accounts" => self::accounts(),
        ];
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
                "domain"        => $response->name,
                "nameservers"   => array_map(function ($host) {
                                        return ["value" => $host];
                                    }, $response->nameservers->hosts),
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
            echo json_encode(  $response );
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
        $records     = Remote\Constellix::get( "domains/$domain->remote_id/records" );
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