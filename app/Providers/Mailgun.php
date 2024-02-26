<?php 

namespace CaptainCore\Providers;

class Mailgun {

    public static function setup( $domain = "" ) {
        // Prep to handle remote responses
        $responses = '';

        // Prep Mailgun domain variable
        $mailgun_subdomain = "mg.$domain";
        $domain_found      = false;
        $domain_unverified = false;

        // Fetch all domains from Mailgun
        $response = \CaptainCore\Remote\Mailgun::get( "v4/domains" );
        foreach ( $$response->items as $domain ) {
            if ( $domain->name == $mailgun_subdomain ) {
                $domain_found = true;
                if ( $domain->state == "unverified" ) {
                    $domain_unverified = true;
                }
            }
        }

        // If Mailgun domain already exists then exit
        if ( $domain_found && ! $domain_unverified ) {
            return "Mailgun domain $mailgun_subdomain already entered and verified";
        }

        if ( ! $domain_found ) {
            // Create domain in Mailgun
            $result = \CaptainCore\Remote\Mailgun::post( "v4/domains", [ 'name' => $mailgun_subdomain ] );
        }

        // Fetch domain from Mailgun
        $domain = \CaptainCore\Remote\Mailgun::get( "v4/domains/$mailgun_subdomain" );

        // Load Constellix domains from transient
        $constellix_domains = get_transient( 'constellix_all_domains' );

        // If empty then update transient with large remote call
        if ( empty( $constellix_domains ) ) {

            // Fetch Constellix domains
            $constellix_domains = \CaptainCore\Remote\Constellix::get( "domains" );

            // Save the API response
            set_transient( 'constellix_all_domains', $constellix_domains, HOUR_IN_SECONDS );

        }

        // Check Consellix for domain
        foreach ( $constellix_domains as $constellix_domain ) {

            // Search API for domain ID
            if ( $domain == $constellix_domain->name ) {
                $domain_id = $constellix_domain->id;
            }
        }

        // Found domain ID from Consellix so add Mailgun dns records
        if ( $domain_id ) {

            // Loop through Mailgun's API new receiving records and prep for Constellix
            $mx_records = [];
            foreach ( $domain->receiving_dns_records as $record ) {
                if ( $record->record_type == 'MX' and $record->valid != 'valid' ) {
                    $mx_records[] = [
                        'value'       => $record->value . '.',
                        'level'       => $record->priority,
                        'disableFlag' => false,
                    ];
                }
            }

            // Prep new Constellix records
            $record_type = 'mx';
            $post        = [
                'recordOption' => 'roundRobin',
                'name'         => 'mg',
                'ttl'          => '3600',
                'roundRobin'   => $mx_records,
            ];

            // Post to new MX records to Constellix
            $response = \CaptainCore\Remote\Constellix::post( "domains/$domain_id/records/$record_type", $post );

            // Capture responses
            foreach ( $response as $result ) {
                if ( is_array( $result ) ) {
                    $result['errors'] = $result[0];
                    $responses        = $responses . json_encode( $result ) . ',';
                } else {
                    $responses = $responses . json_encode( $result ) . ',';
                }
            }

            // Loop through Mailgun's API new receiving records and prep for Constellix
            foreach ( $domain->sending_dns_records as $record ) {
                if ( $record->record_type == 'TXT' and $record->valid != 'valid' ) {
                    $record_name_without_domain = str_replace( '.' . $domain, '', $record->name );
                    $post                       = [
                        'recordOption' => 'roundRobin',
                        'name'         => $record_name_without_domain,
                        'ttl'          => '3600',
                        'roundRobin'   => [ [
                                'value'       => $record->value,
                                'disableFlag' => false,
                            ],
                        ],
                    ];
                    $response = \CaptainCore\Remote\Constellix::post( "domains/$domain_id/records/txt", $post );
                    foreach ( $response as $result ) {
                        if ( is_array( $result ) ) {
                            $result['errors'] = $result[0];
                            $responses        = $responses . json_encode( $result ) . ',';
                        } else {
                            $responses = $responses . json_encode( $result ) . ',';
                        }
                    }
                }
                if ( $record->record_type == 'CNAME' and $record->valid != 'valid' ) {
                    $record_name_without_domain = str_replace( '.' . $domain, '', $record->name );
                    $post = [
                        'name' => $record_name_without_domain,
                        'host' => "$record->value.",
                        'ttl'  => 3600,
                    ];
                    $response = \CaptainCore\Remote\Constellix::post( "domains/$domain_id/records/cname", $post );
                    foreach ( $response as $result ) {
                        if ( is_array( $result ) ) {
                            $result['errors'] = $result[0];
                            $responses        = $responses . json_encode( $result ) . ',';
                        } else {
                            $responses = $responses . json_encode( $result ) . ',';
                        }
                    }
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

        // Check if records are valid. If not need to flag the domain
        // (TO DO: add place to flag domain with automattic retry schedule. 60sec, 3 minutes, 6 minutes, 1hr, 24hrs)
       // \WP_CLI::log( "Response: $domain {$response->domain->state}" );

        return $response->domain->state;
    }

}

