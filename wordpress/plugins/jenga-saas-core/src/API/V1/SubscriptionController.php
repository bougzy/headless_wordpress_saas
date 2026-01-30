<?php

declare(strict_types=1);

namespace Jenga\SaaS\API\V1;

use Jenga\SaaS\Auth\Middleware;
use Jenga\SaaS\Payments\StripeHandler;
use Jenga\SaaS\PostTypes\Subscription;

/**
 * Subscription REST API endpoints.
 *
 * GET  /jenga/v1/subscriptions/current   - Get current user's subscription
 * POST /jenga/v1/subscriptions/checkout  - Create Stripe checkout session
 * POST /jenga/v1/subscriptions/portal    - Create Stripe billing portal session
 * POST /jenga/v1/subscriptions/cancel    - Cancel active subscription
 */
final class SubscriptionController {

    private const NAMESPACE = 'jenga/v1';
    private Middleware $middleware;

    public function __construct() {
        $this->middleware = new Middleware();
    }

    public function register_routes(): void {
        register_rest_route(self::NAMESPACE, '/subscriptions/current', [
            'methods'             => 'GET',
            'callback'            => [$this, 'current'],
            'permission_callback' => [$this->middleware, 'require_auth'],
        ]);

        register_rest_route(self::NAMESPACE, '/subscriptions/checkout', [
            'methods'             => 'POST',
            'callback'            => [$this, 'checkout'],
            'permission_callback' => [$this->middleware, 'require_auth'],
            'args'                => [
                'plan_id' => ['required' => true, 'type' => 'integer', 'sanitize_callback' => 'absint'],
            ],
        ]);

        register_rest_route(self::NAMESPACE, '/subscriptions/portal', [
            'methods'             => 'POST',
            'callback'            => [$this, 'portal'],
            'permission_callback' => [$this->middleware, 'require_auth'],
        ]);

        register_rest_route(self::NAMESPACE, '/subscriptions/cancel', [
            'methods'             => 'POST',
            'callback'            => [$this, 'cancel'],
            'permission_callback' => [$this->middleware, 'require_auth'],
        ]);
    }

    /**
     * GET /subscriptions/current
     */
    public function current(\WP_REST_Request $request): \WP_REST_Response {
        $user_id = get_current_user_id();
        $sub = Subscription::get_user_subscription($user_id);

        if (!$sub) {
            return new \WP_REST_Response([
                'data' => null,
                'message' => 'No active subscription found.',
            ], 200);
        }

        return new \WP_REST_Response([
            'data' => Subscription::to_array($sub),
        ], 200);
    }

    /**
     * POST /subscriptions/checkout — create a Stripe Checkout session.
     */
    public function checkout(\WP_REST_Request $request): \WP_REST_Response|\WP_Error {
        $user_id = get_current_user_id();
        $plan_id = $request->get_param('plan_id');

        // Verify plan exists
        $plan = get_post($plan_id);
        if (!$plan || $plan->post_type !== \Jenga\SaaS\PostTypes\Plan::POST_TYPE) {
            return new \WP_Error('jenga_plan_not_found', 'Plan not found.', ['status' => 404]);
        }

        $stripe_price = get_post_meta($plan_id, '_jenga_plan_stripe_price', true);
        if (!$stripe_price) {
            return new \WP_Error('jenga_plan_no_price', 'Plan has no Stripe price configured.', ['status' => 400]);
        }

        try {
            $stripe = new StripeHandler();
            $session = $stripe->create_checkout_session(
                user_id: $user_id,
                plan_id: $plan_id,
                stripe_price_id: $stripe_price
            );

            return new \WP_REST_Response([
                'checkout_url' => $session->url,
                'session_id'   => $session->id,
            ], 200);
        } catch (\Exception $e) {
            return new \WP_Error('jenga_checkout_failed', $e->getMessage(), ['status' => 500]);
        }
    }

    /**
     * POST /subscriptions/portal — create a Stripe Billing Portal session.
     */
    public function portal(\WP_REST_Request $request): \WP_REST_Response|\WP_Error {
        $user_id = get_current_user_id();
        $sub = Subscription::get_user_subscription($user_id);

        if (!$sub) {
            return new \WP_Error('jenga_no_subscription', 'No active subscription.', ['status' => 404]);
        }

        $customer_id = get_post_meta($sub->ID, '_jenga_sub_stripe_customer', true);
        if (!$customer_id) {
            return new \WP_Error('jenga_no_customer', 'No Stripe customer found.', ['status' => 400]);
        }

        try {
            $stripe  = new StripeHandler();
            $session = $stripe->create_portal_session($customer_id);

            return new \WP_REST_Response([
                'portal_url' => $session->url,
            ], 200);
        } catch (\Exception $e) {
            return new \WP_Error('jenga_portal_failed', $e->getMessage(), ['status' => 500]);
        }
    }

    /**
     * POST /subscriptions/cancel
     */
    public function cancel(\WP_REST_Request $request): \WP_REST_Response|\WP_Error {
        $user_id = get_current_user_id();
        $sub = Subscription::get_user_subscription($user_id);

        if (!$sub) {
            return new \WP_Error('jenga_no_subscription', 'No active subscription.', ['status' => 404]);
        }

        $stripe_id = get_post_meta($sub->ID, '_jenga_sub_stripe_id', true);

        try {
            $stripe = new StripeHandler();
            $stripe->cancel_subscription($stripe_id);

            update_post_meta($sub->ID, '_jenga_sub_status', Subscription::STATUS_CANCELLED);

            // Downgrade user role to free
            \Jenga\SaaS\Roles\RoleManager::assign_tier_role($user_id, 0);

            return new \WP_REST_Response([
                'message' => 'Subscription cancelled successfully.',
            ], 200);
        } catch (\Exception $e) {
            return new \WP_Error('jenga_cancel_failed', $e->getMessage(), ['status' => 500]);
        }
    }
}
