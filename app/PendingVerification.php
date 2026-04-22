<?php

namespace CaptainCore;

/**
 * In-flight "new location" verification records.
 *
 * When a user successfully enters their password from an untrusted fingerprint
 * (and 2FA is not enabled), we stash a record here and email them a link. The
 * record is single-use and expires in TTL_SECONDS.
 *
 * Storage: usermeta `captaincore_pending_verification`. Only one pending
 * record per user — a new signIn overwrites any prior pending record.
 *
 * Record shape:
 *   [
 *     'token_hash'  => '64-char sha256 hex',  // we never store the raw token
 *     'fingerprint' => [ 'country' => 'MA', 'asn' => 36903, ... ],
 *     'ip'          => '41.249.30.162',       // for display in email
 *     'ua'          => '...',
 *     'created_at'  => 1745678901,
 *     'expires_at'  => 1745680701,            // 30 minutes out
 *   ]
 */
class PendingVerification {

    const META_KEY    = 'captaincore_pending_verification';
    const TTL_SECONDS = 1800; // 30 min

    /**
     * Mint a random token, persist the pending record, return the raw token
     * so the caller can embed it in the verification email.
     */
    public static function create( int $user_id, array $fingerprint ): string {
        $token = bin2hex( random_bytes( 32 ) ); // 64 hex chars
        $record = [
            'token_hash'  => hash( 'sha256', $token ),
            'fingerprint' => $fingerprint,
            'ip'          => $fingerprint['ip'] ?? '',
            'ua'          => (string) ( $_SERVER['HTTP_USER_AGENT'] ?? '' ),
            'created_at'  => time(),
            'expires_at'  => time() + self::TTL_SECONDS,
        ];
        update_user_meta( $user_id, self::META_KEY, $record );
        return $token;
    }

    /**
     * Consume a token: verify against the stored hash, check expiry, delete on
     * success. Returns the fingerprint to trust, or null if invalid/expired.
     */
    public static function consume( int $user_id, string $token ): ?array {
        if ( $token === '' ) {
            return null;
        }
        $record = get_user_meta( $user_id, self::META_KEY, true );
        if ( ! is_array( $record ) || empty( $record['token_hash'] ) ) {
            return null;
        }
        if ( ! hash_equals( $record['token_hash'], hash( 'sha256', $token ) ) ) {
            return null;
        }
        if ( time() > (int) ( $record['expires_at'] ?? 0 ) ) {
            delete_user_meta( $user_id, self::META_KEY );
            return null;
        }
        delete_user_meta( $user_id, self::META_KEY );
        return is_array( $record['fingerprint'] ?? null ) ? $record['fingerprint'] : null;
    }

    /** Verification URL the email links to. Points at the REST endpoint. */
    public static function verify_url( int $user_id, string $token ): string {
        return add_query_arg(
            [ 'user' => $user_id, 'token' => $token ],
            rest_url( 'captaincore/v1/verify-login' )
        );
    }

    /** Remove any pending record for this user. Used e.g. on explicit re-auth. */
    public static function clear( int $user_id ): void {
        delete_user_meta( $user_id, self::META_KEY );
    }
}
