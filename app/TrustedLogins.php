<?php

namespace CaptainCore;

/**
 * Per-user list of trusted login fingerprints, stored as an array in usermeta
 * under `captaincore_trusted_logins`.
 *
 * Each entry:
 *   [
 *     'country'   => 'US',
 *     'region'    => 'PA',
 *     'asn'       => 7922,
 *     'added_at'  => 1745678901,
 *     'added_via' => 'email_verify' | 'invite' | 'password_reset'
 *                  | 'invoice_magic'  | 'first_login_auto' | 'manual',
 *     'last_seen' => 1745678901,
 *   ]
 */
class TrustedLogins {

    const META_KEY = 'captaincore_trusted_logins';

    public static function get( int $user_id ): array {
        $value = get_user_meta( $user_id, self::META_KEY, true );
        return is_array( $value ) ? $value : [];
    }

    public static function save( int $user_id, array $list ): void {
        update_user_meta( $user_id, self::META_KEY, array_values( $list ) );
    }

    /**
     * True when the user already has a trusted record that matches this
     * fingerprint. Local/private IPs always return true — we don't store
     * fingerprints for them and treat them as implicitly trusted in dev.
     */
    public static function is_trusted( int $user_id, array $fingerprint ): bool {
        if ( ! empty( $fingerprint['is_local'] ) ) {
            return true;
        }
        if ( empty( $fingerprint['lookup_ok'] ) ) {
            // GeoIP failed — don't grant trust on unresolved lookups.
            return false;
        }
        foreach ( self::get( $user_id ) as $entry ) {
            if ( GeoIP::matches( $entry, $fingerprint ) ) {
                return true;
            }
        }
        return false;
    }

    /**
     * Add a new trusted fingerprint or bump last_seen on an existing match.
     * Local fingerprints are not persisted.
     *
     * $via tags the enrollment source so we can audit how trust was established:
     *   'email_verify', 'invite', 'password_reset', 'invoice_magic',
     *   'first_login_auto', 'manual'
     */
    public static function add( int $user_id, array $fingerprint, string $via ): array {
        if ( ! empty( $fingerprint['is_local'] ) ) {
            return self::get( $user_id );
        }
        if ( empty( $fingerprint['lookup_ok'] ) ) {
            return self::get( $user_id );
        }

        $list = self::get( $user_id );
        $now  = time();

        foreach ( $list as $i => $entry ) {
            if ( GeoIP::matches( $entry, $fingerprint ) ) {
                $list[ $i ]['last_seen'] = $now;
                self::save( $user_id, $list );
                return $list;
            }
        }

        $list[] = [
            'country'   => $fingerprint['country'] ?? null,
            'region'    => $fingerprint['region']  ?? null,
            'asn'       => $fingerprint['asn']     ?? null,
            'added_at'  => $now,
            'added_via' => $via,
            'last_seen' => $now,
        ];
        self::save( $user_id, $list );
        return $list;
    }

    /** Update last_seen on a matching entry. No-op if no match. */
    public static function touch( int $user_id, array $fingerprint ): void {
        if ( ! empty( $fingerprint['is_local'] ) ) {
            return;
        }
        $list    = self::get( $user_id );
        $changed = false;
        $now     = time();
        foreach ( $list as $i => $entry ) {
            if ( GeoIP::matches( $entry, $fingerprint ) ) {
                $list[ $i ]['last_seen'] = $now;
                $changed = true;
                break;
            }
        }
        if ( $changed ) {
            self::save( $user_id, $list );
        }
    }

    /** Remove the entry at the given index. Returns the updated list. */
    public static function revoke( int $user_id, int $index ): array {
        $list = self::get( $user_id );
        if ( isset( $list[ $index ] ) ) {
            array_splice( $list, $index, 1 );
            self::save( $user_id, $list );
        }
        return array_values( $list );
    }

    /** Remove all trusted fingerprints for a user. */
    public static function revoke_all( int $user_id ): void {
        delete_user_meta( $user_id, self::META_KEY );
    }

    /**
     * Convenience: resolve the current request's fingerprint and add it as
     * trusted. Use this from email-proof-of-possession flows (password reset,
     * invite accept, invoice pay-for-order) where the user has already proven
     * they control the email tied to the account.
     */
    public static function trust_current_request( int $user_id, string $via ): void {
        $fingerprint = GeoIP::fingerprint( GeoIP::client_ip() );
        if ( $fingerprint !== null ) {
            self::add( $user_id, $fingerprint, $via );
        }
    }
}
