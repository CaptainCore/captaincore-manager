<?php

namespace CaptainCore;

use GeoIp2\Database\Reader;
use GeoIp2\Exception\AddressNotFoundException;

/**
 * Thin wrapper around MaxMind / DB-IP mmdb databases.
 *
 * Databases live in <site>/private/geoip/ by default, or in the path set
 * via the CAPTAINCORE_GEOIP_PATH constant in wp-config.php. Refresh with
 * scripts/refresh-geoip.sh.
 *
 * Readers are lazy-loaded and cached for the request lifetime. All failures
 * degrade silently — the caller receives null and decides how to proceed
 * (login flow falls back to "unresolved" state instead of crashing).
 */
class GeoIP {

    private static ?Reader $city_reader = null;
    private static ?Reader $asn_reader  = null;

    /**
     * Absolute path to the directory holding GeoLite2-City.mmdb and
     * GeoLite2-ASN.mmdb. Override with `define('CAPTAINCORE_GEOIP_PATH', ...)`.
     */
    public static function db_path(): string {
        if ( defined( 'CAPTAINCORE_GEOIP_PATH' ) ) {
            return CAPTAINCORE_GEOIP_PATH;
        }
        return dirname( rtrim( ABSPATH, '/\\' ) ) . '/private/geoip';
    }

    private static function city_reader(): ?Reader {
        if ( self::$city_reader !== null ) {
            return self::$city_reader;
        }
        $path = self::db_path() . '/GeoLite2-City.mmdb';
        if ( ! is_readable( $path ) ) {
            return null;
        }
        try {
            self::$city_reader = new Reader( $path );
            return self::$city_reader;
        } catch ( \Throwable $e ) {
            return null;
        }
    }

    private static function asn_reader(): ?Reader {
        if ( self::$asn_reader !== null ) {
            return self::$asn_reader;
        }
        $path = self::db_path() . '/GeoLite2-ASN.mmdb';
        if ( ! is_readable( $path ) ) {
            return null;
        }
        try {
            self::$asn_reader = new Reader( $path );
            return self::$asn_reader;
        } catch ( \Throwable $e ) {
            return null;
        }
    }

    /**
     * Returns the visitor's best-guess public IP from common proxy headers,
     * falling back to REMOTE_ADDR. Kinsta-fronted sites already put the real
     * client IP in REMOTE_ADDR, so this mostly matters in dev.
     */
    public static function client_ip(): string {
        if ( ! empty( $_SERVER['HTTP_CF_CONNECTING_IP'] ) ) {
            return (string) $_SERVER['HTTP_CF_CONNECTING_IP'];
        }
        if ( ! empty( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
            $first = trim( explode( ',', (string) $_SERVER['HTTP_X_FORWARDED_FOR'] )[0] );
            if ( $first !== '' ) {
                return $first;
            }
        }
        return (string) ( $_SERVER['REMOTE_ADDR'] ?? '' );
    }

    /**
     * Resolve an IP to a login fingerprint.
     *
     * Shape:
     *   [
     *     'ip'        => '41.249.30.162',
     *     'country'   => 'MA',      // ISO 3166-1 alpha-2, or null if unresolved
     *     'region'    => '06',      // ISO 3166-2 subdivision, or null
     *     'asn'       => 36903,     // int, or null
     *     'is_local'  => false,     // true for private/loopback ranges
     *     'lookup_ok' => true,      // false if DBs missing or IP unknown
     *   ]
     *
     * Returns null only for an empty / malformed input.
     */
    public static function fingerprint( string $ip ): ?array {
        $ip = trim( $ip );
        if ( $ip === '' || ! filter_var( $ip, FILTER_VALIDATE_IP ) ) {
            return null;
        }

        $base = [
            'ip'        => $ip,
            'country'   => null,
            'region'    => null,
            'asn'       => null,
            'asn_org'   => null,
            'is_local'  => false,
            'lookup_ok' => false,
        ];

        // Private / loopback ranges are treated as trusted-local.
        if ( ! filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE ) ) {
            $base['is_local']  = true;
            $base['lookup_ok'] = true;
            return $base;
        }

        $city = self::city_reader();
        if ( $city !== null ) {
            try {
                $record = $city->city( $ip );
                $base['country']   = $record->country->isoCode ?: null;
                $base['region']    = $record->mostSpecificSubdivision->isoCode ?: null;
                $base['lookup_ok'] = true;
            } catch ( AddressNotFoundException $e ) {
                // IP not in DB — leave nulls, keep lookup_ok=false so caller knows
            } catch ( \Throwable $e ) {
                // reader error — same treatment
            }
        }

        $asn = self::asn_reader();
        if ( $asn !== null ) {
            try {
                $record = $asn->asn( $ip );
                if ( $record->autonomousSystemNumber !== null ) {
                    $base['asn'] = (int) $record->autonomousSystemNumber;
                }
                if ( ! empty( $record->autonomousSystemOrganization ) ) {
                    $base['asn_org'] = (string) $record->autonomousSystemOrganization;
                }
            } catch ( \Throwable $e ) {
                // leave asn/asn_org null
            }
        }

        return $base;
    }

    /**
     * Two fingerprints match iff country + region + asn all equal. Null values
     * are only equal to null — an unresolved lookup never matches a resolved one.
     */
    public static function matches( array $a, array $b ): bool {
        return ( $a['country'] ?? null ) === ( $b['country'] ?? null )
            && ( $a['region']  ?? null ) === ( $b['region']  ?? null )
            && ( $a['asn']     ?? null ) === ( $b['asn']     ?? null );
    }

    /** True when the mmdb files are in place and readable. */
    public static function available(): bool {
        return self::city_reader() !== null;
    }
}
