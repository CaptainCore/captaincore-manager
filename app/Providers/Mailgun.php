<?php 

namespace CaptainCore\Providers;

class Mailgun {

    public static function setup( $mailgun_subdomain = "" ) {
        // Prep to handle remote responses
        $responses = '';

        // Prep Mailgun domain variable
        $domain_found      = false;
        $domain_unverified = false;

        // Default retry delay in seconds if the header is missing
        $default_retry_delay = 60;
        
        // Match the main domain and TLD
        if (preg_match('/([a-z0-9\-]+\.[a-z]{2,})$/i', $mailgun_subdomain, $matches)) {
            $domain = $matches[1];
        }

        // Fetch domains from Mailgun
        $mailgun_domain = \CaptainCore\Remote\Mailgun::get( "v4/domains/$mailgun_subdomain" );

        if ( ! empty( $mailgun_domain->message ) && $mailgun_domain->message == "Domain not found" ) {
            // Create domain in Mailgun
            $mailgun_domain = \CaptainCore\Remote\Mailgun::post( "v4/domains", [ 'name' => $mailgun_subdomain ] );
        }

        // Bail if Mailgun didn't return a usable response (e.g. account has disabled domains, rate-limited, auth failure)
        if ( empty( $mailgun_domain->sending_dns_records ) || ! is_array( $mailgun_domain->sending_dns_records ) ) {
            $error_message = $mailgun_domain->message ?? 'unexpected response';
            error_log( "CaptainCore: Mailgun setup failed for $mailgun_subdomain — $error_message. Response: " . json_encode( $mailgun_domain ) );
            return (object) [
                'error'   => true,
                'message' => "Mailgun setup failed for $mailgun_subdomain: $error_message",
            ];
        }

        // If Mailgun domain already exists then exit
        $valid_records = array_column( $mailgun_domain->sending_dns_records, "valid" );
        if ( ! in_array( "unknown", $valid_records ) ) {
            return "Mailgun domain $mailgun_subdomain already entered and verified";
        }

        // Search Constellix directly for an exact domain match
        $search_response = \CaptainCore\Remote\Constellix::get( "search/domains", [ "name" => $domain ] );

        // Check if the domain was found
        if ( ! empty( $search_response->data ) && count( $search_response->data ) > 0 ) {
            $domain_id = $search_response->data[0]->id;
        }

        if ( empty( $domain_id ) && defined( 'WP_CLI' ) ) {
            echo "Domain not found with Constellix, skipping adding DNS records. Manually add these:\n";
            foreach ( $mailgun_domain->receiving_dns_records as $record ) {
                if ( $record->record_type == 'MX' and $record->valid != 'valid' ) {
                    echo "MX record mg to $record->value\n";
                }
            }
            foreach ( $mailgun_domain->sending_dns_records as $record ) {
                $record_name_without_domain = str_replace( ".{$domain}", "", $record->original_name );
                if ( $record->record_type == 'TXT' and $record->valid != 'valid' ) {
                    echo "TXT record $record_name_without_domain to $record->value\n";
                }
                if ( $record->record_type == 'CNAME' and $record->valid != 'valid' ) {
                    echo "CNAME record $record_name_without_domain to $record->value\n";
                }
            }
        }

        // Found domain ID from Consellix so add Mailgun dns records
        if ( ! empty( $domain_id ) ) {

            $mx_name = str_replace( ".{$domain}", "", $mailgun_subdomain );

            // Loop through Mailgun's API new receiving records and prep for Constellix
            $mx_records = [];
            foreach ( $mailgun_domain->receiving_dns_records as $record ) {
                if ( $record->record_type == 'MX' and $record->valid != 'valid' ) {
                    $mx_records[] = [
                        'server'   => $record->value . '.',
						'priority' => $record->priority,
						'enabled'  => true,
                    ];
                }
            }
            // Formats MX records into array which API can read
            $post = [
                'name'  => $mx_name,
                'type'  => 'mx',
                'ttl'   => '3600',
                'value' => $mx_records,
            ];
            // Post to new MX records to Constellix - using post_raw()
            $response = \CaptainCore\Remote\Constellix::post_raw( "domains/$domain_id/records", $post );

            // Check for rate limiting
            if ( ( isset($response->body->message) && $response->body->message == "You have made too many requests, please wait and try again later" ) || ( isset($response->headers['x-ratelimit-remaining']) && $response->headers['x-ratelimit-remaining'] == 0 ) ) {
                $retry_delay = $response->headers['x-ratelimit-reset'] ?? $default_retry_delay;
                wp_schedule_single_event( time() + (int)$retry_delay, 'schedule_mailgun_retry', [ $mailgun_subdomain ] );
                error_log("CaptainCore: Mailgun setup rate-limited when adding MX. Retrying in $retry_delay seconds.");
                return "Rate-limited. Retrying in $retry_delay seconds."; // Stop execution
            }
            error_log( "CaptainCore: Mailgun setup adding MX records for $domain. Request: " . json_encode($post) . " Response: " . json_encode($response->body) );

            // Loop through Mailgun's API new receiving records and prep for Constellix
            foreach ( $mailgun_domain->sending_dns_records as $record ) {
                if ( $record->record_type == 'TXT' and $record->valid != 'valid' ) {
                    $record_name_without_domain = str_replace( ".{$domain}", "", $record->name );
                    $post = [
                        'name'  => $record_name_without_domain,
                        'type'  => 'txt',
                        'ttl'   => '3600',
                        'value' => [ 
                            [
                                'value'   => $record->value,
                                'enabled' => true,
                            ],
                        ],
                    ];
                    $response = \CaptainCore\Remote\Constellix::post_raw( "domains/$domain_id/records", $post );

                    // Check for rate limiting
                    if ( ( isset($response->body->message) && $response->body->message == "You have made too many requests, please wait and try again later" ) || ( isset($response->headers['x-ratelimit-remaining']) && $response->headers['x-ratelimit-remaining'] == 0 ) ) {
                        $retry_delay = $response->headers['x-ratelimit-reset'] ?? $default_retry_delay;
                        wp_schedule_single_event( time() + (int)$retry_delay, 'schedule_mailgun_retry', [ $mailgun_subdomain ] );
                        error_log("CaptainCore: Mailgun setup rate-limited when adding TXT. Retrying in $retry_delay seconds.");
                        return "Rate-limited. Retrying in $retry_delay seconds."; // Stop execution
                    }
                    error_log( "CaptainCore: Mailgun setup adding TXT record for $domain. Request: " . json_encode($post) . " Response: " . json_encode($response->body) );
                }
                if ( $record->record_type == 'CNAME' and $record->valid != 'valid' ) {
                    $record_name_without_domain = str_replace( ".{$domain}", "", $record->name );
                    $post = [
                        'name'  => $record_name_without_domain,
                        'type'  => 'cname',
                        'ttl'   => 3600,
                        'value' => [ 
                            [
                                'value'   => "$record->value.",
                                'enabled' => true,
                            ] 
                        ],
                    ];
                    $response = \CaptainCore\Remote\Constellix::post_raw( "domains/$domain_id/records", $post );

                    // Check for rate limiting
                    if ( ( isset($response->body->message) && $response->body->message == "You have made too many requests, please wait and try again later" ) || ( isset($response->headers['x-ratelimit-remaining']) && $response->headers['x-ratelimit-remaining'] == 0 ) ) {
                        $retry_delay = $response->headers['x-ratelimit-reset'] ?? $default_retry_delay;
                        wp_schedule_single_event( time() + (int)$retry_delay, 'schedule_mailgun_retry', [ $mailgun_subdomain ] );
                        error_log("CaptainCore: Mailgun setup rate-limited when adding CNAME. Retrying in $retry_delay seconds.");
                        return "Rate-limited. Retrying in $retry_delay seconds."; // Stop execution
                    }
                    error_log( "CaptainCore: Mailgun setup adding CNAME record for $domain. Request: " . json_encode($post) . " Response: " . json_encode($response->body) );
                }
            }
        }

        // Valid Mailgun domains
        $result = \CaptainCore\Remote\Mailgun::put( "v4/domains/$mailgun_subdomain/verify" );

        // In 1 minute run Mailgun verify domain
        wp_schedule_single_event( time() + 60, 'schedule_mailgun_verify', [ $domain ] );

        if ( $responses ) {
            return $responses;
        }
    }

    public static function verify( $domain ) {
        $response = \CaptainCore\Remote\Mailgun::put( "v4/domains/$domain/verify" );
        return $response->domain->state;
    }

    /**
     * Rotate (regenerate) the SMTP sending password for a domain's Mailgun zone.
     *
     * Generates a fresh 32-character password, sets it at Mailgun via
     * PUT v4/domains/{zone}, and persists it to the domain's details JSON.
     *
     * @param int $domain_id CaptainCore domain ID.
     * @return object On success { error: false, password: <string> }.
     *                On failure { error: true, message: <string> }.
     */
    public static function rotate_smtp_password( $domain_id ) {
        $domain = ( new \CaptainCore\Domains )->get( $domain_id );
        if ( ! $domain ) {
            return (object) [ 'error' => true, 'message' => 'Domain not found in CaptainCore.' ];
        }

        $details = empty( $domain->details ) ? (object) [] : json_decode( $domain->details );

        if ( empty( $details->mailgun_zone ) ) {
            return (object) [ 'error' => true, 'message' => 'No Mailgun zone configured for this domain.' ];
        }

        $password = wp_generate_password( 32, false );
        $response = \CaptainCore\Remote\Mailgun::put( "v4/domains/{$details->mailgun_zone}", [ "smtp_password" => $password ] );

        if ( ! empty( $response->errors ) ) {
            return (object) [ 'error' => true, 'message' => implode( '; ', $response->errors ) ];
        }
        if ( isset( $response->message ) && stripos( $response->message, 'not found' ) !== false ) {
            return (object) [ 'error' => true, 'message' => $response->message ];
        }

        $details->mailgun_smtp_password = $password;
        ( new \CaptainCore\Domains )->update(
            [ "details" => json_encode( $details ) ],
            [ "domain_id" => $domain_id ]
        );

        return (object) [ 'error' => false, 'password' => $password ];
    }

    /**
     * Deploy Mailgun SMTP configuration to a site via the remote deploy-mailgun script.
     *
     * Ensures an SMTP password exists (minting one if absent), fetches GravitySMTP
     * credentials, and runs the deploy-mailgun SSH script against the given site slug.
     *
     * @param int    $domain_id  CaptainCore domain ID (must have mailgun_zone configured).
     * @param string $site_slug  Final remote site slug (caller applies any environment suffix).
     * @param string $from_name  "Send From" display name passed to the deploy script.
     * @return string|object Run::CLI output string, or { error: true, message: <string> } on failure.
     */
    public static function deploy( $domain_id, $site_slug, $from_name ) {
        $domain = ( new \CaptainCore\Domains )->get( $domain_id );
        if ( ! $domain ) {
            return (object) [ 'error' => true, 'message' => 'Domain not found in CaptainCore.' ];
        }

        $details = empty( $domain->details ) ? (object) [] : json_decode( $domain->details );

        if ( empty( $details->mailgun_zone ) ) {
            return (object) [ 'error' => true, 'message' => 'No Mailgun zone configured for this domain.' ];
        }

        // Ensure an SMTP password exists; mint one if this zone has never been deployed.
        if ( empty( $details->mailgun_smtp_password ) ) {
            $rotate = self::rotate_smtp_password( $domain_id );
            if ( is_object( $rotate ) && ! empty( $rotate->error ) ) {
                return $rotate;
            }
            $details->mailgun_smtp_password = $rotate->password;
        }

        // Fetch GravitySMTP credentials.
        $credentials  = ( new \CaptainCore\Provider( "gravitysmtp" ) )->credentials();
        $license      = '';
        $download_url = '';
        foreach ( (array) $credentials as $credential ) {
            if ( $credential->name == "license" ) {
                $license = $credential->value;
            }
            if ( $credential->name == "download_url" ) {
                $download_url = $credential->value;
            }
        }

        $command = sprintf(
            "ssh %s --script=deploy-mailgun -- --key=%s --name=%s --domain=%s --password=%s --gravitysmtp_zip=%s",
            $site_slug,
            json_encode( $license ),
            json_encode( $from_name ),
            json_encode( $details->mailgun_zone ),
            json_encode( $details->mailgun_smtp_password ),
            json_encode( $download_url )
        );

        return \CaptainCore\Run::CLI( $command );
    }

}