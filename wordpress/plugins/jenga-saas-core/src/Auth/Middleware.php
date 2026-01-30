<?php

declare(strict_types=1);

namespace Jenga\SaaS\Auth;

/**
 * REST API authentication middleware.
 *
 * Extracts JWT from the Authorization header and sets the current user.
 * Used as a permission_callback in REST route registrations.
 */
final class Middleware {

    private JWT $jwt;

    public function __construct(?JWT $jwt = null) {
        $this->jwt = $jwt ?? new JWT();
    }

    /**
     * Permission callback: require a valid JWT access token.
     */
    public function require_auth(\WP_REST_Request $request): bool|\WP_Error {
        $token = $this->extract_token($request);

        if (!$token) {
            return new \WP_Error(
                'jenga_unauthorized',
                'Authentication required. Provide a valid Bearer token.',
                ['status' => 401]
            );
        }

        $user = $this->jwt->get_user_from_token($token);
        if (!$user) {
            return new \WP_Error(
                'jenga_invalid_token',
                'Invalid or expired token.',
                ['status' => 401]
            );
        }

        // Set the current user for this request
        wp_set_current_user($user->ID);

        return true;
    }

    /**
     * Permission callback: require admin capabilities.
     */
    public function require_admin(\WP_REST_Request $request): bool|\WP_Error {
        $auth = $this->require_auth($request);
        if ($auth !== true) {
            return $auth;
        }

        if (!current_user_can('manage_options')) {
            return new \WP_Error(
                'jenga_forbidden',
                'Insufficient permissions.',
                ['status' => 403]
            );
        }

        return true;
    }

    /**
     * Permission callback: optionally authenticated (for mixed public/private endpoints).
     */
    public function optional_auth(\WP_REST_Request $request): true {
        $token = $this->extract_token($request);

        if ($token) {
            $user = $this->jwt->get_user_from_token($token);
            if ($user) {
                wp_set_current_user($user->ID);
            }
        }

        return true;
    }

    /**
     * Extract Bearer token from the Authorization header.
     */
    private function extract_token(\WP_REST_Request $request): ?string {
        $auth_header = $request->get_header('Authorization');

        if (!$auth_header || !str_starts_with($auth_header, 'Bearer ')) {
            return null;
        }

        $token = trim(substr($auth_header, 7));
        return $token !== '' ? $token : null;
    }
}
