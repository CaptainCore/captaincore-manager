<?php 

namespace CaptainCore;

class Domains extends DB {

    static $primary_key = 'domain_id';

    protected $domains = [];

    public function __construct( $domains = [] ) {
        global $wpdb;
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

        // Filter to accounts where user has domain access
        $allowed_account_ids = [];
        foreach ( $account_ids as $account_id ) {
             $level = $user->account_level( $account_id );
             $perms = User::tier_permissions( $level );
             if ( $perms['domains'] ) {
                 $allowed_account_ids[] = $account_id;
             }
        }

        if ( ! empty( $allowed_account_ids ) ) {
            // Include shared accounts (via customer_id on sites) using a single query
            $all_account_ids = $allowed_account_ids;
            $ids_str = implode( ',', array_map( 'intval', $allowed_account_ids ) );
            $shared  = $wpdb->get_col( "
                SELECT DISTINCT customer_id FROM {$wpdb->prefix}captaincore_sites
                WHERE ( account_id IN ($ids_str) OR site_id IN (
                    SELECT site_id FROM {$wpdb->prefix}captaincore_account_site WHERE account_id IN ($ids_str)
                ) ) AND status = 'active' AND customer_id > 0
            " );
            $all_account_ids = array_unique( array_merge( $all_account_ids, $shared ) );

            $results = ( new AccountDomain )->fetch_domains( [ "account_id" => $all_account_ids ] );
            foreach ( $results as $domain ) {
                $this->domains[] = $domain->domain_id;
            }
        }

        $this->domains = array_unique( $this->domains );
        
    }

    public function list() {
        $domains        = [];
        $provider_names = [];
        foreach( $this->domains as $domain_id ) {
            $domain  = self::get( $domain_id );
            $details = empty( $domain->details ) ? (object) [] : json_decode( $domain->details );
            $provider_name = "";
            if ( ! empty( $domain->provider_id ) ) {
                if ( ! isset( $provider_names[ $domain->provider_id ] ) ) {
                    $provider = Providers::get( $domain->provider_id );
                    $provider_names[ $domain->provider_id ] = $provider->name ?? "";
                }
                $provider_name = $provider_names[ $domain->provider_id ];
            }
            $domains[] = [
                "domain_id"   => (int) $domain_id,
                "remote_id"   => $domain->remote_id,
                "provider_id" => ! empty( $domain->provider_id ) ? $domain->provider_id : "",
                "provider"    => $provider_name,
                "name"        => $domain->name,
                "status"      => $domain->status,
                "price"       => $domain->price,
                "forwarding"  => ! empty( $details->mailgun_forwarding_id ),
                "sending"     => ! empty( $details->mailgun_id ),
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
        if ( empty( $domain ) || empty( $domain->name ) ) {
            return [ "errors" => "Domain not found." ];
        }

        $details  = empty( $domain->details ) ? (object) [] : json_decode( $domain->details );
        if ( ! is_object( $details ) ) {
            $details = (object) [];
        }
        $removed  = [];
        $warnings = [];

        // Mailgun sending zone (typically mg.example.com) — distinct from the
        // apex forwarding domain, so tear it down first.
        $sending_zone = ! empty( $details->mailgun_zone ) ? $details->mailgun_zone : null;
        if ( ! empty( $details->mailgun_id ) && $sending_zone ) {
            $result = self::delete_mailgun_zone( $sending_zone );
            if ( $result === true ) {
                $removed[] = 'mailgun';
            } else {
                $warnings[] = "Mailgun sending zone: {$result}";
            }
        }

        // Email forwarding lives on the apex Mailgun domain. Skip a second
        // API call if sending was configured on the same zone.
        if ( ! empty( $details->mailgun_forwarding_id ) ) {
            if ( $sending_zone && strtolower( $sending_zone ) === strtolower( $domain->name ) ) {
                $removed[] = 'email_forwarding';
            } else {
                $result = self::delete_mailgun_zone( $domain->name );
                if ( $result === true ) {
                    $removed[] = 'email_forwarding';
                } else {
                    $warnings[] = "Email forwarding: {$result}";
                }
            }
        }

        if ( ! empty( $domain->remote_id ) ) {
            $response  = Remote\Constellix::delete( "domains/{$domain->remote_id}" );
            $dns_error = self::constellix_delete_error( $response );
            if ( $dns_error === null ) {
                $removed[] = 'dns_zone';
            } else {
                // Deleting the row while the zone is still live loses track of a
                // zone that is still answering queries, and a zone nothing points
                // at can then be claimed by whoever next adds that domain name.
                return [ "errors" => [ "Could not remove the DNS zone: {$dns_error}. The domain has been left in place." ] ];
            }
        }

        // Existing v1 behavior: turn off Hover auto-renew. Does not cancel
        // the registrar registration.
        if ( ! empty( $domain->provider_id ) ) {
            ( new Domain( $domain_id ) )->renew_off();
        }

        $pivots     = ( new AccountDomain() )->where( [ "domain_id" => $domain_id ] );
        $account_id = $pivots[0]->account_id ?? null;
        foreach ( $pivots as $pivot ) {
            ( new AccountDomain() )->delete( $pivot->account_domain_id );
        }

        self::delete( $domain_id );

        $suffix = empty( $removed ) ? '' : ' (also removed: ' . implode( ', ', $removed ) . ')';
        ActivityLog::log( 'deleted', 'domain', $domain_id, $domain->name, "Deleted domain {$domain->name}{$suffix}", [
            'removed'  => $removed,
            'warnings' => $warnings,
        ], $account_id );

        return [
            "domain_id" => (int) $domain_id,
            "message"   => "Deleted domain {$domain->name}",
            "removed"   => $removed,
            "warnings"  => $warnings,
        ];
    }

    /**
     * Delete a Mailgun domain. "Already gone" is success so a retry of
     * domain delete doesn't get stuck on a half-removed zone.
     *
     * @return true|string True on success, otherwise the error message.
     */
    private static function delete_mailgun_zone( $zone ) {
        if ( empty( $zone ) ) {
            return true;
        }

        $response = Remote\Mailgun::delete( "v3/domains/{$zone}" );
        if ( is_object( $response ) && ! empty( $response->errors ) ) {
            return is_array( $response->errors ) ? implode( ', ', $response->errors ) : (string) $response->errors;
        }

        if ( isset( $response->message ) ) {
            $ok = [
                'Domain not found',
                'Domain will be deleted in the background',
                'Domain has been deleted',
            ];
            if ( ! in_array( $response->message, $ok, true ) ) {
                return $response->message;
            }
        }

        return true;
    }

    /**
     * @return string|null Error text, or null when the zone is gone.
     */
    private static function constellix_delete_error( $response ) {
        if ( is_string( $response ) && $response !== '' ) {
            return $response;
        }
        if ( is_object( $response ) && ! empty( $response->errors ) ) {
            $errors = (array) $response->errors;
            $first  = (string) reset( $errors );
            if ( $first !== '' && strpos( $first, 'Domain not found' ) === false ) {
                return implode( ', ', $errors );
            }
        }
        return null;
    }

    public static function get_domain( $host ) {
        $myhost = strtolower(trim($host));
        $count = substr_count($myhost, '.');
        if($count === 2){
            if(strlen(explode('.', $myhost)[1]) > 3) $myhost = explode('.', $myhost, 2)[1];
        } else if($count > 2){
            $myhost = self::get_domain(explode('.', $myhost, 2)[1]);
        }
        return $myhost;
    }

    public function attempt_fetch_verification_record ( $domain ) {

        $data = [ 
            'timeout' => 45,
            'headers' => [
                'Content-Type' => 'application/json',
                'X-Token'      => \CaptainCore\Providers\Kinsta::credentials("token")
            ],
            'body'        => json_encode( [
                "variables" => [ 
                    "idCompany" => \CaptainCore\Providers\Kinsta::credentials("company_id")
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

            $response = wp_remote_post( "https://graphql-router.kinsta.com", $data );

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
        $sites         = $response->data->company->sites ?? [];
        foreach ( $sites as $key => $site ) {
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
                'X-Token'      => \CaptainCore\Providers\Kinsta::credentials("token")
            ],
            'body'    => json_encode( [
                "variables" => [ 
                    "idSite"        => $idSite,
                    "idEnvironment" => $idEnvironment
                ],
                "operationName" => "FullSiteDomains",
                "query"         => 'query FullSiteDomains($idSite: String!, $idEnvironment: String!) {
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

        $response = wp_remote_post( "https://graphql-router.kinsta.com", $data );

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

			$constellix_all_domains = Remote\Constellix::get( 'domains' );

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
                return "Domain $domain not found with DNS provider and no nameservers were found. Manually add TXT verification record `$txt`.";
            }
            return "Domain $domain not found with DNS provider. Nameservers are `$nameservers`. Manually add TXT verification record `$txt`.";
        }

        $txt_records  = Remote\Constellix::get( "domains/{$record->id}/records/txt" );
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
                $response = Remote\Constellix::put( "domains/{$record->id}/records/txt/$txt_record->id", $post );
			
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
        $response = Remote\Constellix::post( "domains/{$record->id}/records/txt", $post );

        return "Adding `$txt` to $domain";

    }

    public static function domain_providers() {
        return [ 'hoverdotcom', 'spaceship' ];
    }

    public function provider_sync() {

        foreach ( self::domain_providers() as $provider_type ) {
            $providers = Providers::where( [ "provider" => $provider_type ] );
            foreach ( $providers as $provider ) {
                $class_name = "CaptainCore\Providers\\" . ucfirst( $provider_type );
                if ( ! method_exists( $class_name, 'fetch_domains' ) ) {
                    continue;
                }
                $domains = $class_name::fetch_domains();
                foreach ( $domains as $domain ) {
                    $lookup = self::where( [ "name" => $domain->name ] );
                    if ( count( $lookup ) == 1 ) {
                        self::update( [
                            "status"      => $domain->status,
                            "provider_id" => $provider->provider_id,
                        ], [ "domain_id" => $lookup[0]->domain_id ] );
                    }
                }
            }
        }

    }

    public static function records( $domain, $zone ) {
        $records = [];
        $zone    = \Badcow\DNS\Parser\Parser::parse("{$domain}.", $zone );
        foreach ($zone->getResourceRecords() as $record) {
            $name = $record->getName();
            $name = str_replace( "{$domain}.", "", $name );
            if ( $name == "@" ) {
                $name = "";
            }
            $item = [
                "name"  => $name,
                "type"  => $record->getType(),
                "value" => $record->getRdata()->toText()
            ];
            if ( $record->getType() == "TXT" && $record->getRdata()->toText() == "" ) {
                continue;
            }
            $records[] = $item;
        }
        return $records;
    }

}