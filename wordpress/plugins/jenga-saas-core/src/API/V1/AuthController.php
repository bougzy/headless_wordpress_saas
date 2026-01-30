<?php

declare(strict_types=1);

namespace Jenga\SaaS\API\V1;

use Jenga\SaaS\Auth\JWT;
use Jenga\SaaS\Auth\Middleware;
use Jenga\SaaS\Roles\RoleManager;

/**
 * Authentication REST API endpoints.
 *
 * POST /jenga/v1/auth/login    - Authenticate with email/password
 * POST /jenga/v1/auth/register - Create new account
 * POST /jenga/v1/auth/refresh  - Refresh access token
 * GET  /jenga/v1/auth/me       - Get current user profile
 */
final class AuthController {

    private const NAMESPACE = 'jenga/v1';
    private JWT $jwt;
    private Middleware $middleware;

    public function __construct(JWT $jwt) {
        $this->jwt = $jwt;
        $this->middleware = new Middleware($jwt);
    }

    public function register_routes(): void {
        register_rest_route(self::NAMESPACE, '/auth/login', [
            'methods'             => 'POST',
            'callback'            => [$this, 'login'],
            'permission_callback' => '__return_true',
            'args'                => [
                'email'    => ['required' => true, 'type' => 'string', 'sanitize_callback' => 'sanitize_email'],
                'password' => ['required' => true, 'type' => 'string'],
            ],
        ]);

        register_rest_route(self::NAMESPACE, '/auth/register', [
            'methods'             => 'POST',
            'callback'            => [$this, 'register'],
            'permission_callback' => '__return_true',
            'args'                => [
                'email'      => ['required' => true, 'type' => 'string', 'sanitize_callback' => 'sanitize_email'],
                'password'   => ['required' => true, 'type' => 'string'],
                'first_name' => ['required' => false, 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field'],
                'last_name'  => ['required' => false, 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field'],
            ],
        ]);

        register_rest_route(self::NAMESPACE, '/auth/refresh', [
            'methods'             => 'POST',
            'callback'            => [$this, 'refresh'],
            'permission_callback' => '__return_true',
            'args'                => [
                'refresh_token' => ['required' => true, 'type' => 'string'],
            ],
        ]);

        register_rest_route(self::NAMESPACE, '/auth/me', [
            'methods'             => 'GET',
            'callback'            => [$this, 'me'],
            'permission_callback' => [$this->middleware, 'require_auth'],
        ]);
    }

    /**
     * POST /auth/login
     */
    public function login(\WP_REST_Request $request): \WP_REST_Response|\WP_Error {
        $email    = $request->get_param('email');
        $password = $request->get_param('password');

        $user = wp_authenticate($email, $password);
        if (is_wp_error($user)) {
            return new \WP_Error(
                'jenga_auth_failed',
                'Invalid email or password.',
                ['status' => 401]
            );
        }

        $tokens = $this->jwt->generate_tokens($user);

        return new \WP_REST_Response([
            'user'   => $this->format_user($user),
            'tokens' => $tokens,
        ], 200);
    }

    /**
     * POST /auth/register
     */
    public function register(\WP_REST_Request $request): \WP_REST_Response|\WP_Error {
        $email    = $request->get_param('email');
        $password = $request->get_param('password');

        if (!is_email($email)) {
            return new \WP_Error('jenga_invalid_email', 'Invalid email address.', ['status' => 400]);
        }

        if (strlen($password) < 8) {
            return new \WP_Error('jenga_weak_password', 'Password must be at least 8 characters.', ['status' => 400]);
        }

        if (email_exists($email)) {
            return new \WP_Error('jenga_email_exists', 'An account with this email already exists.', ['status' => 409]);
        }

        $user_id = wp_insert_user([
            'user_login'   => $email,
            'user_email'   => $email,
            'user_pass'    => $password,
            'first_name'   => $request->get_param('first_name') ?? '',
            'last_name'    => $request->get_param('last_name') ?? '',
            'role'         => RoleManager::ROLE_FREE,
        ]);

        if (is_wp_error($user_id)) {
            return new \WP_Error('jenga_registration_failed', 'Registration failed.', ['status' => 500]);
        }

        $user   = get_user_by('ID', $user_id);
        $tokens = $this->jwt->generate_tokens($user);

        return new \WP_REST_Response([
            'user'   => $this->format_user($user),
            'tokens' => $tokens,
        ], 201);
    }

    /**
     * POST /auth/refresh
     */
    public function refresh(\WP_REST_Request $request): \WP_REST_Response|\WP_Error {
        $refresh_token = $request->get_param('refresh_token');

        try {
            $tokens = $this->jwt->refresh($refresh_token);
            return new \WP_REST_Response(['tokens' => $tokens], 200);
        } catch (\Exception $e) {
            return new \WP_Error(
                'jenga_refresh_failed',
                $e->getMessage(),
                ['status' => $e->getCode() ?: 401]
            );
        }
    }

    /**
     * GET /auth/me
     */
    public function me(\WP_REST_Request $request): \WP_REST_Response {
        $user = wp_get_current_user();
        return new \WP_REST_Response([
            'user' => $this->format_user($user),
        ], 200);
    }

    private function format_user(\WP_User $user): array {
        $sub = \Jenga\SaaS\PostTypes\Subscription::get_user_subscription($user->ID);

        return [
            'id'         => $user->ID,
            'email'      => $user->user_email,
            'first_name' => $user->first_name,
            'last_name'  => $user->last_name,
            'display_name' => $user->display_name,
            'avatar'     => get_avatar_url($user->ID, ['size' => 96]),
            'roles'      => $user->roles,
            'tier'       => \Jenga\SaaS\PostTypes\Subscription::get_user_tier($user->ID),
            'subscription' => $sub ? \Jenga\SaaS\PostTypes\Subscription::to_array($sub) : null,
            'created_at' => $user->user_registered,
        ];
    }
}
