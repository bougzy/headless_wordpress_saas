<?php

declare(strict_types=1);

namespace Jenga\SaaS\API\V1;

use Jenga\SaaS\PostTypes\Plan;

/**
 * Plan REST API endpoints.
 *
 * GET /jenga/v1/plans      - List all active plans
 * GET /jenga/v1/plans/{id} - Get a single plan
 */
final class PlanController {

    private const NAMESPACE = 'jenga/v1';

    public function register_routes(): void {
        register_rest_route(self::NAMESPACE, '/plans', [
            'methods'             => 'GET',
            'callback'            => [$this, 'index'],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route(self::NAMESPACE, '/plans/(?P<id>\d+)', [
            'methods'             => 'GET',
            'callback'            => [$this, 'show'],
            'permission_callback' => '__return_true',
            'args'                => [
                'id' => [
                    'required'          => true,
                    'type'              => 'integer',
                    'sanitize_callback' => 'absint',
                ],
            ],
        ]);
    }

    /**
     * GET /plans — List all active plans, sorted by tier.
     */
    public function index(\WP_REST_Request $request): \WP_REST_Response {
        $plans = Plan::get_active_plans();
        $data  = array_map([Plan::class, 'to_array'], $plans);

        return new \WP_REST_Response([
            'data'  => $data,
            'total' => count($data),
        ], 200);
    }

    /**
     * GET /plans/{id}
     */
    public function show(\WP_REST_Request $request): \WP_REST_Response|\WP_Error {
        $post = get_post($request->get_param('id'));

        if (!$post || $post->post_type !== Plan::POST_TYPE || $post->post_status !== 'publish') {
            return new \WP_Error('jenga_plan_not_found', 'Plan not found.', ['status' => 404]);
        }

        return new \WP_REST_Response([
            'data' => Plan::to_array($post),
        ], 200);
    }
}
