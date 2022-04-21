<?php 

namespace CaptainCore;

class Domains extends DB {

    static $primary_key = 'domain_id';

    protected $domains = [];

    public function __construct( $domains = [] ) {
        $user        = new User;
        $account_ids = $user->accounts();

        // New array to collect IDs
        $domain_ids = [];

        // Bail if not assigned a role
        if ( ! $user->role_check() ) {
            return 'Error: Please log in.';
        }

        // Administrators return all domains
        if ( $user->is_admin() ) {
            $this->domains = self::select_domains();
            return;
        }

        // Bail if no accounts set.
        if ( ! is_array( $account_ids ) ) {
            return;
        }

        // Loop through each account for current user and fetch SiteIDs
        foreach ( $account_ids as $account_id ) {
             // Fetch sites assigned as shared access
             $domain_ids = ( new Account( $account_id, true ) )->domains();
             foreach ( $domain_ids as $domain ) {
                $this->domains[] = $domain->domain_id;
            }
        }

        $this->domains = array_unique( $this->domains );
        
    }

    public function list() {
        $domains        = [];
        foreach( $this->domains as $domain_id ) {
            $domain = self::get( $domain_id );
            $domains[] = [
                "domain_id"   => (int) $domain_id,
                "remote_id"   => $domain->remote_id,
                "provider_id" => ! empty( $domain->provider_id ) ? $domain->provider_id : "",
                "name"        => $domain->name,
                "status"      => $domain->status,
                "price"       => $domain->price,
            ];
        }
        return $domains;
    }

    public function verify( $domain_id = "" ) {
        // Check multiple site ids
        if ( is_array( $domain_id ) ) {
            $valid = true;
            foreach ( $domain_id as $id ) {
                if ( in_array( $id, $this->domains ) ) {
                    continue;
                }
                $valid = false;
            }
            return $valid;
        }
        // Check individual site id
        if ( in_array( $domain_id, $this->domains ) ) {
            return true;
        }
        return false;
    }

    public function delete_domain( $domain_id ) {

        if ( ! in_array( $domain_id, $this->domains ) ) {
            return [ "errors" => "Permission denied." ];
        }
        $domain = self::get( $domain_id );
		if ( $domain->remote_id  ) {
			constellix_api_delete( "domains/{$domain->remote_id}" );
		}
        if ( $domain->provider_id  ) {
			( new Domain( $domain_id ) )->renew_off();
		}
        self::delete( $domain_id );
        return [ "domain_id" => $domain_id, "message" => "Deleted domain {$domain->name}" ];
    }

    public function get_domain( $host ) {
        $myhost = strtolower(trim($host));
        $count = substr_count($myhost, '.');
        if($count === 2){
            if(strlen(explode('.', $myhost)[1]) > 3) $myhost = explode('.', $myhost, 2)[1];
        } else if($count > 2){
            $myhost = get_domain(explode('.', $myhost, 2)[1]);
        }
        return $myhost;
    }

    public function attempt_fetch_verification_record ( $domain ) {

        $data = [ 
            'timeout' => 45,
            'headers' => [
                'Content-Type' => 'application/json',
                'X-Token'      => MY_KINSTA_TOKEN
            ],
            'body'        => json_encode( [
                "variables" => [ 
                    "idCompany" => MY_KINSTA_COMPANY
                ],
                "operationName" => "SiteListFull",
                "query"         => 'query SiteListFull($idCompany: String!) {
                    company(id: $idCompany) {
                      name
                      id
                      sites {
                        id
                        ...siteData
                        __typename
                      }
                      __typename
                    }
                  }
                  
                  fragment siteData on Site {
                    id
                    displayName
                    name
                    idCompany
                    environment(name: "live") {
                      id
                      domains {
                        id
                        name
                        __typename
                      }
                      __typename
                    }
                    __typename
                  }'
            ] )
        ];

         // Load domains from transient
		$response = get_transient( 'kinsta_all_domains' );

		// If empty then update transient with large remote call
		if ( empty( $response ) ) {

            $response = wp_remote_post( "https://my.kinsta.com/graphql", $data );

            if ( is_wp_error( $response ) ) {
                $to      = get_option('admin_email');
                $subject = "Communication with Kinsta error";
                $headers = [ 
                    'Content-Type: text/html; charset=UTF-8',
                ];
                $body    = $response->get_error_message();
                wp_mail( $to, $subject, $body, $headers );
                return "";
            }

			$response = json_decode( $response['body'] );

			// Save the API response so we don't have to call as often
			set_transient( 'kinsta_all_domains', $response, HOUR_IN_SECONDS );

		}

        $idSite        = null;
        $idEnvironment = null;
        foreach ( $response->data->company->sites as $key => $site ) {
            foreach ( $site->environment->domains as $check ) {
                if ( $check->name == $domain ) {
                    $idSite        = $response->data->company->sites[$key]->id;
                    $idEnvironment = $response->data->company->sites[$key]->environment->id;
                    break;
                }
            }
            if ( ! empty ( $idSite ) ) {
                break;
            }
        }

        if ( empty( $idSite ) ) {
            return "";
        }

        $data = [ 
            'timeout' => 45,
            'headers' => [
                'Content-Type' => 'application/json',
                'X-Token'      => MY_KINSTA_TOKEN
            ],
            'body'        => json_encode( [
                "variables" => [ 
                    "idSite"        => $idSite,
                    "idEnvironment" => $idEnvironment
                ],
                "operationName" => "FullSiteDomains",
                "query" => 'query FullSiteDomains($idSite: String!, $idEnvironment: String!) {
                    site(id: $idSite) {
                      id
                      environment(id: $idEnvironment) {
                        id
                        customHostnames {
                          id
                          rootDomain
                          idRootDomain
                          verificationRecords {
                            name
                            value
                            __typename
                          }
                        }
                      }
                    }
                  }'
            ] )
        ];

        $response = wp_remote_post( "https://my.kinsta.com/graphql", $data );

        if ( is_wp_error( $response ) ) {
            $to      = get_option('admin_email');
            $subject = "Communication with Kinsta error";
            $headers = [ 
                'Content-Type: text/html; charset=UTF-8',
            ];
            $body    = $response->get_error_message();
            wp_mail( $to, $subject, $body, $headers );
            return "";
        }

        $response = json_decode( $response['body'] );
        foreach( $response->data->site->environment->customHostnames as $record ) {
            if ( $record->verificationRecords ) {
                foreach ($record->verificationRecords as $item ) {
                    if ( $item->name == $domain ) {
                        return $item->value;
                    }
                }
            }
        }

        return "";

    }

    public function add_verification_record( $domain, $txt = "" ) {

        $name = "";

        // Attempt to retrieve from Kinsta
        if ( empty( $txt ) ) {
            $txt = self::attempt_fetch_verification_record( $domain );
        }
        
        if ( substr( $txt, 0, 2 ) != "ca" ) {
            return "TXT record for $domain doesn't look right. `$txt`";
        }

        $primary_domain = self::get_domain( $domain );
        if ( $primary_domain != $domain ) {
            $name = rtrim ( substr ( $domain, 0, strrpos( $domain, $primary_domain ) ), "." );
        }

         // Load domains from transient
		$constellix_all_domains = get_transient( 'constellix_all_domains' );

        // If empty then update transient with large remote call
		if ( empty( $constellix_all_domains ) ) {

			$constellix_all_domains = constellix_api_get( 'domains' );

			// Save the API response so we don't have to call again until tomorrow.
			set_transient( 'constellix_all_domains', $constellix_all_domains, HOUR_IN_SECONDS );

		}

		// Search API for domain ID
		foreach ( $constellix_all_domains as $item ) {
			if ( $item->name == $primary_domain ) {
                $record = $item;
                break;
			}
        }

        if ( empty( $record ) ) {
            $nameservers = implode ( array_column ( dns_get_record( $domain, DNS_NS ), "target" ), " " );
            if ( empty( $nameservers ) ) {
                return "Domain $domain not found with DNS provider and no nameservers were found.";
            }
            return "Domain $domain not found with DNS provider. Nameservers are `$nameservers`.";
        }

        $txt_records  = constellix_api_get( "domains/{$record->id}/records/txt" );
        foreach ( $txt_records as $txt_record ) {
            if ( $txt_record->name == $name ) {
                foreach( $txt_record->value as $value ) {
                    if ( str_replace( '"', '', $value->value ) == $txt ) {
                        return "Domain $domain already has $txt added.";
                    }
                }
                $txt_record->value[] = [
                    'value'       => "\"$txt\"",
                    'disableFlag' => false,
                ];

				$post = [
					'recordOption' => 'roundRobin',
					'name'         => $name,
					'ttl'          => 3600,
					'roundRobin'   => $txt_record->value,
                ];
                $response = constellix_api_put( "domains/{$record->id}/records/txt/$txt_record->id", $post );
			
                return "Added `$txt` to $domain on existing TXT record.";
            }
        }

        // add new TXT record
        $post = [
            'recordOption' => 'roundRobin',
            'name'         => $name,
            'ttl'          => "3600",
            'roundRobin'   => [ [
                'value'       => $txt,
                'disableFlag' => false,
            ] ],
        ];
        $response = constellix_api_post( "domains/{$record->id}/records/txt", $post );

        return "Adding `$txt` to $domain";

    }

    public function provider_login() {

        $data = [ 
            'timeout' => 45,
            'headers' => [
                'Content-Type' => 'application/json; charset=utf-8'
            ],
            'body'        => json_encode( [ 
                "username" => HOVERCOM_USERNAME, 
                'password' => HOVERCOM_PASSWORD 
            ] ), 
            'method'      => 'POST', 
            'data_format' => 'body'
        ];
        
        $response    = wp_remote_post( "https://www.hover.com/api/login", $data );
        
        // Save Hover.com cookies as transient login 
        $cookie_data = json_encode( $response["cookies"] );
        set_transient( 'captaincore_hovercom_auth', $cookie_data, HOUR_IN_SECONDS * 48 );
        
    }

    public function provider_sync() {

        if ( empty( get_transient( 'captaincore_hovercom_auth' ) ) ) {
            self::provider_login();
        }
        $cookie_data = json_decode( get_transient( 'captaincore_hovercom_auth' ) );
        $cookies     = [];
        foreach ( $cookie_data as $key => $cookie ) {
            $cookies[] = new \WP_Http_Cookie( [
                'name'    => $cookie->name,
                'value'   => $cookie->value,
                'expires' => $cookie->expires,
                'path'    => $cookie->path,
                'domain'  => $cookie->domain,
            ] );
        }

        $args = [
            'timeout' => 45,
            'cookies' => $cookies,
        ];

        $response = wp_remote_get( "https://www.hover.com/api/control_panel/domains", $args );
        if ( is_wp_error( $response ) ) {
            return json_decode( $response->get_error_message() );
        }

        $domains = json_decode( $response['body'] )->domains;
        foreach ( $domains as $domain ) {
            $lookup = self::where( ["name" => $domain->name ] );
            if ( count( $lookup ) == 1 ) {
                self::update( [
                    "status"      => $domain->status,
                    "provider_id" => $domain->id,
                ], [ "domain_id"  => $lookup[0]->domain_id ] );
            }
        }
        
    }

}