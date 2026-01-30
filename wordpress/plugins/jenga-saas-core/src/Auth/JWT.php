<?php

declare(strict_types=1);

namespace Jenga\SaaS\Auth;

use Firebase\JWT\JWT as FirebaseJWT;
use Firebase\JWT\Key;
use Firebase\JWT\ExpiredException;

/**
 * JWT token management for headless authentication.
 *
 * Issues short-lived access tokens and long-lived refresh tokens.
 * Tokens are signed with HMAC-SHA256.
 */
final class JWT {

    private string $secret;
    private int $expiration;
    private int $refresh_expiration;
    private string $issuer;

    public function __construct() {
        $this->secret             = JENGA_JWT_SECRET;
        $this->expiration         = JENGA_JWT_EXPIRATION;
        $this->refresh_expiration = JENGA_JWT_REFRESH_EXPIRATION;
        $this->issuer             = get_bloginfo('url');
    }

    /**
     * Generate an access + refresh token pair for a user.
     *
     * @return array{access_token: string, refresh_token: string, expires_in: int}
     */
    public function generate_tokens(\WP_User $user): array {
        $now = time();

        $access_payload = [
            'iss'  => $this->issuer,
            'iat'  => $now,
            'nbf'  => $now,
            'exp'  => $now + $this->expiration,
            'sub'  => $user->ID,
            'type' => 'access',
            'data' => [
                'user_id' => $user->ID,
                'email'   => $user->user_email,
                'roles'   => $user->roles,
            ],
        ];

        $refresh_payload = [
            'iss'  => $this->issuer,
            'iat'  => $now,
            'nbf'  => $now,
            'exp'  => $now + $this->refresh_expiration,
            'sub'  => $user->ID,
            'type' => 'refresh',
            'jti'  => wp_generate_uuid4(),
        ];

        return [
            'access_token'  => FirebaseJWT::encode($access_payload, $this->secret, 'HS256'),
            'refresh_token' => FirebaseJWT::encode($refresh_payload, $this->secret, 'HS256'),
            'expires_in'    => $this->expiration,
            'token_type'    => 'Bearer',
        ];
    }

    /**
     * Validate a JWT and return its decoded payload.
     *
     * @throws \Exception On invalid/expired token.
     */
    public function validate(string $token): object {
        try {
            $decoded = FirebaseJWT::decode($token, new Key($this->secret, 'HS256'));

            if ($decoded->iss !== $this->issuer) {
                throw new \Exception('Token issuer mismatch.');
            }

            return $decoded;
        } catch (ExpiredException $e) {
            throw new \Exception('Token has expired.', 401);
        } catch (\Exception $e) {
            throw new \Exception('Invalid token: ' . $e->getMessage(), 401);
        }
    }

    /**
     * Extract user from a validated access token.
     */
    public function get_user_from_token(string $token): ?\WP_User {
        try {
            $decoded = $this->validate($token);

            if (($decoded->type ?? '') !== 'access') {
                return null;
            }

            $user = get_user_by('ID', $decoded->sub);
            return $user instanceof \WP_User ? $user : null;
        } catch (\Exception) {
            return null;
        }
    }

    /**
     * Refresh tokens — validate a refresh token and issue new token pair.
     *
     * @return array{access_token: string, refresh_token: string, expires_in: int}
     * @throws \Exception On invalid refresh token.
     */
    public function refresh(string $refresh_token): array {
        $decoded = $this->validate($refresh_token);

        if (($decoded->type ?? '') !== 'refresh') {
            throw new \Exception('Invalid token type. Expected refresh token.', 401);
        }

        $user = get_user_by('ID', $decoded->sub);
        if (!$user) {
            throw new \Exception('User not found.', 404);
        }

        return $this->generate_tokens($user);
    }
}
