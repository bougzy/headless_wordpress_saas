<?php

declare(strict_types=1);

namespace Jenga\SaaS;

use Jenga\SaaS\PostTypes\Plan;
use Jenga\SaaS\PostTypes\Subscription;
use Jenga\SaaS\PostTypes\Content;
use Jenga\SaaS\Auth\JWT;
use Jenga\SaaS\Roles\RoleManager;
use Jenga\SaaS\API\V1\AuthController;
use Jenga\SaaS\API\V1\PlanController;
use Jenga\SaaS\API\V1\ContentController;
use Jenga\SaaS\API\V1\SubscriptionController;
use Jenga\SaaS\API\V1\WebhookController;
use Jenga\SaaS\API\Middleware\RateLimiter;
use Jenga\SaaS\Webhooks\RevalidationDispatcher;

/**
 * Main plugin orchestrator. Singleton pattern to ensure single initialization.
 */
final class Plugin {

    private static ?self $instance = null;

    private function __construct() {}

    public static function get_instance(): self {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Initialize all plugin subsystems.
     */
    public function init(): void {
        $this->register_post_types();
        $this->register_api();
        $this->register_hooks();
    }

    private function register_post_types(): void {
        $plan = new Plan();
        $plan->register();

        $subscription = new Subscription();
        $subscription->register();

        $content = new Content();
        $content->register();
    }

    private function register_api(): void {
        $rate_limiter = new RateLimiter();

        add_action('rest_api_init', function () use ($rate_limiter) {
            // Auth endpoints
            $auth = new AuthController(new JWT());
            $auth->register_routes();

            // Plan endpoints
            $plans = new PlanController();
            $plans->register_routes();

            // Content endpoints
            $content = new ContentController();
            $content->register_routes();

            // Subscription endpoints
            $subscriptions = new SubscriptionController();
            $subscriptions->register_routes();

            // Webhook endpoints (Stripe + revalidation)
            $webhooks = new WebhookController();
            $webhooks->register_routes();
        });

        // Apply rate limiting to all jenga/v1 routes
        add_filter('rest_pre_dispatch', [$rate_limiter, 'check'], 10, 3);
    }

    private function register_hooks(): void {
        // CORS headers for headless frontend
        add_action('rest_api_init', [$this, 'add_cors_headers']);

        // Trigger frontend revalidation on content changes
        $revalidation = new RevalidationDispatcher();
        add_action('save_post', [$revalidation, 'on_post_save'], 10, 2);
        add_action('delete_post', [$revalidation, 'on_post_delete'], 10, 1);
    }

    /**
     * Set permissive CORS headers for the headless frontend origin.
     */
    public function add_cors_headers(): void {
        $origin = JENGA_FRONTEND_URL;

        remove_filter('rest_pre_serve_request', 'rest_send_cors_headers');
        add_filter('rest_pre_serve_request', function ($value) use ($origin) {
            header('Access-Control-Allow-Origin: ' . $origin);
            header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS');
            header('Access-Control-Allow-Headers: Authorization, Content-Type, X-WP-Nonce, X-Jenga-Nonce');
            header('Access-Control-Allow-Credentials: true');
            header('Access-Control-Max-Age: 86400');
            return $value;
        });
    }
}
