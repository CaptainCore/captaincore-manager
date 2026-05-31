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
     *
     * Default is `<abspath>/wp-content/uploads/geoip` — always inside
     * PHP's open_basedir and writable from the refresh script. Falls back
     * to a sibling `private/geoip` layout if that exists (dev environments).
     */
    public static function db_path(): string {
        if ( defined( 'CAPTAINCORE_GEOIP_PATH' ) ) {
            return CAPTAINCORE_GEOIP_PATH;
        }
        $candidates = [
            rtrim( ABSPATH, '/\\' ) . '/wp-content/uploads/geoip',
            dirname( rtrim( ABSPATH, '/\\' ) ) . '/private/geoip',
        ];
        foreach ( $candidates as $path ) {
            // Suppress open_basedir warnings — is_dir returns false cleanly
            // and we continue probing.
            if ( @is_dir( $path ) ) {
                return $path;
            }
        }
        return $candidates[0];
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
     * Cloudflare's published edge IP ranges (https://www.cloudflare.com/ips/).
     * Proxy headers (CF-Connecting-IP / X-Forwarded-For) are only trusted when
     * the connecting peer (REMOTE_ADDR) falls inside one of these — otherwise a
     * direct attacker could spoof their apparent IP (and thus the login
     * step-up's geo/ASN fingerprint). Kinsta-fronted sites already put the real
     * client IP in REMOTE_ADDR, so those resolve correctly via the fallback.
     */
    private static array $cloudflare_ranges = [
        '173.245.48.0/20', '103.21.244.0/22', '103.22.200.0/22', '103.31.4.0/22',
        '141.101.64.0/18', '108.162.192.0/18', '190.93.240.0/20', '188.114.96.0/20',
        '197.234.240.0/22', '198.41.128.0/17', '162.158.0.0/15', '104.16.0.0/13',
        '104.24.0.0/14', '172.64.0.0/13', '131.0.72.0/22',
        '2400:cb00::/32', '2606:4700::/32', '2803:f800::/32', '2405:b500::/32',
        '2405:8100::/32', '2a06:98c0::/29', '2c0f:f248::/32',
    ];

    /**
     * True when REMOTE_ADDR is a trusted reverse proxy whose forwarding headers
     * may be believed. Trusts Cloudflare's ranges by default; a site can supply
     * its own list via the CAPTAINCORE_TRUSTED_PROXIES constant (array or
     * comma-separated string of CIDRs), or force-trust with CAPTAINCORE_TRUST_PROXY
     * (e.g. behind a different known load balancer).
     */
    private static function remote_addr_is_trusted_proxy(): bool {
        if ( defined( 'CAPTAINCORE_TRUST_PROXY' ) && CAPTAINCORE_TRUST_PROXY ) {
            return true;
        }
        $remote = (string) ( $_SERVER['REMOTE_ADDR'] ?? '' );
        if ( $remote === '' ) {
            return false;
        }
        $ranges = self::$cloudflare_ranges;
        if ( defined( 'CAPTAINCORE_TRUSTED_PROXIES' ) ) {
            $extra  = is_array( CAPTAINCORE_TRUSTED_PROXIES )
                ? CAPTAINCORE_TRUSTED_PROXIES
                : explode( ',', (string) CAPTAINCORE_TRUSTED_PROXIES );
            $ranges = array_merge( $ranges, array_map( 'trim', $extra ) );
        }
        foreach ( $ranges as $cidr ) {
            if ( self::ip_in_cidr( $remote, $cidr ) ) {
                return true;
            }
        }
        return false;
    }

    /** Bitwise CIDR membership test supporting both IPv4 and IPv6. */
    private static function ip_in_cidr( string $ip, string $cidr ): bool {
        if ( strpos( $cidr, '/' ) === false ) {
            return false;
        }
        list( $subnet, $bits ) = explode( '/', $cidr, 2 );
        $ip_bin     = @inet_pton( $ip );
        $subnet_bin = @inet_pton( $subnet );
        if ( $ip_bin === false || $subnet_bin === false || strlen( $ip_bin ) !== strlen( $subnet_bin ) ) {
            return false; // address family mismatch or malformed
        }
        $bits      = (int) $bits;
        $full      = intdiv( $bits, 8 );
        $remainder = $bits % 8;
        if ( $full > 0 && strncmp( $ip_bin, $subnet_bin, $full ) !== 0 ) {
            return false;
        }
        if ( $remainder === 0 ) {
            return true;
        }
        $mask = chr( 0xff << ( 8 - $remainder ) & 0xff );
        return ( $ip_bin[ $full ] & $mask ) === ( $subnet_bin[ $full ] & $mask );
    }

    /**
     * Returns the visitor's best-guess public IP. Proxy headers are only honored
     * when REMOTE_ADDR is a trusted proxy (see remote_addr_is_trusted_proxy);
     * otherwise REMOTE_ADDR itself is authoritative. This prevents header
     * spoofing from defeating the login geo/ASN step-up check.
     */
    public static function client_ip(): string {
        $remote = (string) ( $_SERVER['REMOTE_ADDR'] ?? '' );

        if ( self::remote_addr_is_trusted_proxy() ) {
            if ( ! empty( $_SERVER['HTTP_CF_CONNECTING_IP'] )
                && filter_var( $_SERVER['HTTP_CF_CONNECTING_IP'], FILTER_VALIDATE_IP ) ) {
                return (string) $_SERVER['HTTP_CF_CONNECTING_IP'];
            }
            if ( ! empty( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
                $first = trim( explode( ',', (string) $_SERVER['HTTP_X_FORWARDED_FOR'] )[0] );
                if ( $first !== '' && filter_var( $first, FILTER_VALIDATE_IP ) ) {
                    return $first;
                }
            }
        }

        return $remote;
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
        $host = isset( $_SERVER['HTTP_HOST'] ) ? $_SERVER['HTTP_HOST'] : '';
        if ( $host === 'localhost' || preg_match( '/(^|[\.])(localhost|test|local)(:\d+)?$/', $host ) || ! filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE ) ) {
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
