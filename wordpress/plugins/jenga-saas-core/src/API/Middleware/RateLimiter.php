<?php

declare(strict_types=1);

namespace Jenga\SaaS\API\Middleware;

/**
 * Rate limiter for REST API endpoints using WordPress transients.
 *
 * In production, replace transients with Redis or a dedicated rate-limiting service.
 * This implementation uses a sliding window counter per IP.
 */
final class RateLimiter {

    private int $max_requests;
    private int $window_seconds;

    public function __construct() {
        $this->max_requests   = JENGA_RATE_LIMIT_REQUESTS;
        $this->window_seconds = JENGA_RATE_LIMIT_WINDOW;
    }

    /**
     * Filter callback for rest_pre_dispatch.
     * Returns WP_Error if rate limit exceeded, otherwise passes through.
     *
     * @param mixed            $result  Pre-dispatch result.
     * @param \WP_REST_Server  $server  REST server instance.
     * @param \WP_REST_Request $request Current request.
     * @return mixed|\WP_Error
     */
    public function check(mixed $result, \WP_REST_Server $server, \WP_REST_Request $request): mixed {
        // Only rate-limit our own namespace
        $route = $request->get_route();
        if (!str_starts_with($route, '/jenga/v1')) {
            return $result;
        }

        // Skip rate limiting for webhook endpoints (they have their own signature verification)
        if (str_contains($route, '/webhooks/')) {
            return $result;
        }

        $ip = $this->get_client_ip();
        $key = 'jenga_rl_' . md5($ip);

        $data = get_transient($key);
        if ($data === false) {
            $data = ['count' => 0, 'start' => time()];
        }

        $data['count']++;

        // Check if window has expired
        $elapsed = time() - $data['start'];
        if ($elapsed >= $this->window_seconds) {
            $data = ['count' => 1, 'start' => time()];
        }

        set_transient($key, $data, $this->window_seconds);

        // Add rate limit headers
        $remaining = max(0, $this->max_requests - $data['count']);
        $reset     = $data['start'] + $this->window_seconds;

        header("X-RateLimit-Limit: {$this->max_requests}");
        header("X-RateLimit-Remaining: {$remaining}");
        header("X-RateLimit-Reset: {$reset}");

        if ($data['count'] > $this->max_requests) {
            return new \WP_Error(
                'jenga_rate_limited',
                'Rate limit exceeded. Please try again later.',
                ['status' => 429]
            );
        }

        return $result;
    }

    private function get_client_ip(): string {
        $headers = ['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'REMOTE_ADDR'];

        foreach ($headers as $header) {
            $ip = $_SERVER[$header] ?? '';
            if ($ip !== '') {
                // X-Forwarded-For may contain multiple IPs — take the first
                $ip = explode(',', $ip)[0];
                $ip = trim($ip);
                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    return $ip;
                }
            }
        }

        return '0.0.0.0';
    }
}
