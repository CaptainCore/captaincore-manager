<?php 

namespace CaptainCore;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

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
        if ( ! $user->has_known_role() ) {
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
        $allowed = array_map( 'intval', (array) $this->domains );

        // Check multiple domain ids
        if ( is_array( $domain_id ) ) {
            // An empty or malformed scope has to mean "no" - see Sites::verify().
            if ( empty( $domain_id ) ) {
                return false;
            }
            foreach ( $domain_id as $id ) {
                if ( ! self::is_valid_id( $id ) || ! in_array( (int) $id, $allowed, true ) ) {
                    return false;
                }
            }
            return true;
        }

        // Check individual domain id.
        return self::is_valid_id( $domain_id ) && in_array( (int) $domain_id, $allowed, true );
    }

    public function delete_domain( $domain_id ) {

        if ( ! self::verify( $domain_id ) ) {
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

    public function add_verification_record( $domain ) {
        return Providers\Kinsta::provision_hostname_records( $domain );
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