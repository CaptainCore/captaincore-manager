<?php

namespace CaptainCore;

/**
 * Read + destroy helpers for WordPress auth sessions (WP's session_tokens
 * usermeta). Produces the payload consumed by the /account/profile "Active
 * Sessions" UI.
 *
 * WP_User_Meta_Session_Tokens is the default session store on a single-site
 * install and keys its session_tokens usermeta by sha256 hash of the raw
 * auth cookie token. All reads/writes here go through the usermeta directly
 * because WP's public session API does not expose deletion by hash.
 */
class Sessions {

    /**
     * Return all active sessions for a user, each enriched with geo + parsed
     * UA, sorted newest first, with `is_current` flagged on the session tied
     * to the current request.
     */
    public static function list_for_user( int $user_id ): array {
        $sessions = get_user_meta( $user_id, 'session_tokens', true );
        if ( ! is_array( $sessions ) || empty( $sessions ) ) {
            return [];
        }

        $current_hash = self::current_hash();
        $out          = [];

        foreach ( $sessions as $hash => $data ) {
            if ( ! is_array( $data ) ) {
                continue;
            }
            $ip      = (string) ( $data['ip'] ?? '' );
            $ua_raw  = (string) ( $data['ua'] ?? '' );
            $ua      = self::parse_ua( $ua_raw );
            $fp      = GeoIP::fingerprint( $ip );

            $out[] = [
                'id'           => substr( (string) $hash, 0, 16 ),
                'hash'         => (string) $hash,
                'ip'           => $ip,
                'country'      => $fp['country']     ?? null,
                'country_name' => self::country_name( $fp['country'] ?? null ),
                'region'       => $fp['region']      ?? null,
                'asn'          => $fp['asn']         ?? null,
                'asn_org'      => $fp['asn_org']     ?? null,
                'is_local'     => ! empty( $fp['is_local'] ),
                'ua_browser'   => $ua['browser'],
                'ua_os'        => $ua['os'],
                'ua_raw'       => $ua_raw,
                'login_at'     => (int) ( $data['login']      ?? 0 ),
                'expires_at'   => (int) ( $data['expiration'] ?? 0 ),
                'is_current'   => ( $hash === $current_hash ),
            ];
        }

        // Current session pinned to the top; remaining sorted newest-first.
        usort( $out, function ( $a, $b ) {
            if ( $a['is_current'] !== $b['is_current'] ) {
                return $b['is_current'] <=> $a['is_current'];
            }
            return $b['login_at'] <=> $a['login_at'];
        } );
        return $out;
    }

    /**
     * Destroy a single session by its (stored) sha256 hash. Refuses to destroy
     * the current request's own session — use a sign-out for that.
     */
    public static function destroy_by_hash( int $user_id, string $hash ): bool {
        if ( $hash === '' ) {
            return false;
        }
        if ( $hash === self::current_hash() ) {
            return false;
        }
        $sessions = get_user_meta( $user_id, 'session_tokens', true );
        if ( ! is_array( $sessions ) || ! isset( $sessions[ $hash ] ) ) {
            return false;
        }
        unset( $sessions[ $hash ] );
        if ( empty( $sessions ) ) {
            delete_user_meta( $user_id, 'session_tokens' );
        } else {
            update_user_meta( $user_id, 'session_tokens', $sessions );
        }
        return true;
    }

    /**
     * Destroy all sessions except the current one. Returns the number of
     * sessions that were destroyed.
     */
    public static function destroy_others( int $user_id ): int {
        $sessions = get_user_meta( $user_id, 'session_tokens', true );
        if ( ! is_array( $sessions ) || empty( $sessions ) ) {
            return 0;
        }
        $current_hash = self::current_hash();
        $kept         = [];
        $killed       = 0;
        foreach ( $sessions as $hash => $data ) {
            if ( $hash === $current_hash ) {
                $kept[ $hash ] = $data;
            } else {
                $killed++;
            }
        }
        if ( empty( $kept ) ) {
            delete_user_meta( $user_id, 'session_tokens' );
        } else {
            update_user_meta( $user_id, 'session_tokens', $kept );
        }
        return $killed;
    }

    /**
     * sha256 of the current request's raw session token, or null if this is
     * a CLI / no-cookie context. WordPress stores sessions keyed by this hash.
     */
    private static function current_hash(): ?string {
        if ( ! function_exists( 'wp_get_session_token' ) ) {
            return null;
        }
        $raw = wp_get_session_token();
        if ( empty( $raw ) ) {
            return null;
        }
        return hash( 'sha256', $raw );
    }

    /**
     * Lightweight UA parser. Returns `['browser' => 'Chrome 147', 'os' => 'macOS 10.15.7']`.
     * Intentionally compact — good enough for display, not a forensic-quality parser.
     */
    public static function parse_ua( string $ua ): array {
        $browser = 'Unknown browser';
        $os      = 'Unknown OS';

        if ( $ua === '' ) {
            return [ 'browser' => $browser, 'os' => $os ];
        }

        // OS — order matters (iOS/Android come before the Safari/macOS checks).
        if ( preg_match( '#Android ?([0-9.]+)?#', $ua, $m ) ) {
            $os = 'Android' . ( isset( $m[1] ) && $m[1] !== '' ? ' ' . $m[1] : '' );
        } elseif ( preg_match( '#iPhone|iPad|iPod#', $ua ) ) {
            $os = 'iOS';
            if ( preg_match( '#OS ([0-9_]+)#', $ua, $m ) ) {
                $os = 'iOS ' . str_replace( '_', '.', $m[1] );
            }
        } elseif ( preg_match( '#Mac OS X ([0-9_]+)#', $ua, $m ) ) {
            $os = 'macOS ' . str_replace( '_', '.', $m[1] );
        } elseif ( preg_match( '#Windows NT ([0-9.]+)#', $ua, $m ) ) {
            $map = [
                '10.0' => '10/11',
                '6.3'  => '8.1',
                '6.2'  => '8',
                '6.1'  => '7',
            ];
            $os = 'Windows ' . ( $map[ $m[1] ] ?? $m[1] );
        } elseif ( strpos( $ua, 'CrOS' ) !== false ) {
            $os = 'ChromeOS';
        } elseif ( strpos( $ua, 'Linux' ) !== false ) {
            $os = 'Linux';
        }

        // Browser — order matters (Edge / Opera / Brave before Chrome; Chrome before Safari).
        if ( preg_match( '#Edg/([0-9]+)#', $ua, $m ) ) {
            $browser = 'Edge ' . $m[1];
        } elseif ( preg_match( '#OPR/([0-9]+)#', $ua, $m ) ) {
            $browser = 'Opera ' . $m[1];
        } elseif ( preg_match( '#Brave/([0-9]+)#', $ua, $m ) ) {
            $browser = 'Brave ' . $m[1];
        } elseif ( preg_match( '#Firefox/([0-9]+)#', $ua, $m ) ) {
            $browser = 'Firefox ' . $m[1];
        } elseif ( preg_match( '#Chrome/([0-9]+)#', $ua, $m ) ) {
            $browser = 'Chrome ' . $m[1];
        } elseif ( preg_match( '#Version/([0-9]+).*Safari#', $ua, $m ) ) {
            $browser = 'Safari ' . $m[1];
        } elseif ( preg_match( '#curl/([0-9.]+)#', $ua, $m ) ) {
            $browser = 'curl ' . $m[1];
        }

        return [ 'browser' => $browser, 'os' => $os ];
    }

    /**
     * ISO 3166-1 alpha-2 → human-readable country name. Reuses WooCommerce's
     * country list when available (no extra data bundled for this alone);
     * falls back to the ISO code when WC isn't loaded.
     */
    public static function country_name( ?string $iso ): ?string {
        if ( empty( $iso ) ) {
            return null;
        }
        if ( function_exists( 'WC' ) && is_object( WC()->countries ?? null ) ) {
            $names = WC()->countries->get_countries();
            if ( isset( $names[ $iso ] ) ) {
                return (string) $names[ $iso ];
            }
        }
        return $iso;
    }
}
