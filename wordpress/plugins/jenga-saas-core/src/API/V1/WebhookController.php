<?php

declare(strict_types=1);

namespace Jenga\SaaS\API\V1;

use Jenga\SaaS\Payments\StripeHandler;

/**
 * Webhook REST API endpoints.
 *
 * POST /jenga/v1/webhooks/stripe      - Stripe event handler
 * POST /jenga/v1/webhooks/revalidate  - Frontend revalidation trigger
 */
final class WebhookController {

    private const NAMESPACE = 'jenga/v1';

    public function register_routes(): void {
        register_rest_route(self::NAMESPACE, '/webhooks/stripe', [
            'methods'             => 'POST',
            'callback'            => [$this, 'stripe'],
            'permission_callback' => '__return_true', // Verified by Stripe signature
        ]);

        register_rest_route(self::NAMESPACE, '/webhooks/revalidate', [
            'methods'             => 'POST',
            'callback'            => [$this, 'revalidate'],
            'permission_callback' => '__return_true', // Verified by shared secret
        ]);
    }

    /**
     * POST /webhooks/stripe — handle Stripe webhook events.
     *
     * Stripe signs every webhook with a signature header. We verify it
     * using the webhook secret before processing any event.
     */
    public function stripe(\WP_REST_Request $request): \WP_REST_Response|\WP_Error {
        $payload   = $request->get_body();
        $signature = $request->get_header('stripe-signature');

        if (!$signature) {
            return new \WP_Error('jenga_no_signature', 'Missing Stripe signature.', ['status' => 400]);
        }

        try {
            $handler = new StripeHandler();
            $handler->handle_webhook($payload, $signature);

            return new \WP_REST_Response(['received' => true], 200);
        } catch (\Exception $e) {
            error_log('[Jenga] Stripe webhook error: ' . $e->getMessage());
            return new \WP_Error('jenga_webhook_error', $e->getMessage(), ['status' => 400]);
        }
    }

    /**
     * POST /webhooks/revalidate — manually trigger Next.js ISR revalidation.
     * Protected by a shared secret.
     */
    public function revalidate(\WP_REST_Request $request): \WP_REST_Response|\WP_Error {
        $secret = $request->get_param('secret');

        if ($secret !== JENGA_REVALIDATION_SECRET) {
            return new \WP_Error('jenga_unauthorized', 'Invalid revalidation secret.', ['status' => 401]);
        }

        $paths = $request->get_param('paths');
        if (!is_array($paths) || empty($paths)) {
            return new \WP_Error('jenga_invalid_paths', 'Provide an array of paths to revalidate.', ['status' => 400]);
        }

        $results = [];
        foreach ($paths as $path) {
            $path = sanitize_text_field($path);
            $response = wp_remote_post(JENGA_FRONTEND_URL . '/api/revalidate', [
                'body'    => wp_json_encode(['path' => $path, 'secret' => JENGA_REVALIDATION_SECRET]),
                'headers' => ['Content-Type' => 'application/json'],
                'timeout' => 10,
            ]);

            $results[$path] = !is_wp_error($response) && wp_remote_retrieve_response_code($response) === 200;
        }

        return new \WP_REST_Response([
            'revalidated' => $results,
        ], 200);
    }
}
