<?php

declare(strict_types=1);

namespace Jenga\SaaS\PostTypes;

/**
 * Subscription post type — tracks user subscriptions to plans.
 *
 * Meta fields:
 *  - _jenga_sub_user_id          (int)    WordPress user ID
 *  - _jenga_sub_plan_id          (int)    Plan post ID
 *  - _jenga_sub_stripe_id        (string) Stripe Subscription ID
 *  - _jenga_sub_stripe_customer  (string) Stripe Customer ID
 *  - _jenga_sub_status           (string) active|cancelled|past_due|trialing|expired
 *  - _jenga_sub_current_period_end (int)  Unix timestamp
 *  - _jenga_sub_created_at       (int)    Unix timestamp
 */
final class Subscription {

    public const POST_TYPE = 'jenga_subscription';

    public const STATUS_ACTIVE    = 'active';
    public const STATUS_CANCELLED = 'cancelled';
    public const STATUS_PAST_DUE  = 'past_due';
    public const STATUS_TRIALING  = 'trialing';
    public const STATUS_EXPIRED   = 'expired';

    public function register(): void {
        add_action('init', [$this, 'register_post_type']);
        add_action('init', [$this, 'register_meta']);
    }

    public function register_post_type(): void {
        $labels = [
            'name'               => __('Subscriptions', 'jenga-saas'),
            'singular_name'      => __('Subscription', 'jenga-saas'),
            'all_items'          => __('All Subscriptions', 'jenga-saas'),
            'search_items'       => __('Search Subscriptions', 'jenga-saas'),
            'not_found'          => __('No subscriptions found.', 'jenga-saas'),
        ];

        register_post_type(self::POST_TYPE, [
            'labels'          => $labels,
            'public'          => false,
            'show_ui'         => true,
            'show_in_menu'    => true,
            'menu_icon'       => 'dashicons-money-alt',
            'show_in_rest'    => false,
            'supports'        => ['title'],
            'capability_type' => 'post',
            'map_meta_cap'    => true,
            'has_archive'     => false,
            'rewrite'         => false,
        ]);
    }

    public function register_meta(): void {
        $meta_fields = [
            '_jenga_sub_user_id'            => ['type' => 'integer', 'default' => 0],
            '_jenga_sub_plan_id'            => ['type' => 'integer', 'default' => 0],
            '_jenga_sub_stripe_id'          => ['type' => 'string',  'default' => ''],
            '_jenga_sub_stripe_customer'    => ['type' => 'string',  'default' => ''],
            '_jenga_sub_status'             => ['type' => 'string',  'default' => self::STATUS_ACTIVE],
            '_jenga_sub_current_period_end' => ['type' => 'integer', 'default' => 0],
            '_jenga_sub_created_at'         => ['type' => 'integer', 'default' => 0],
        ];

        foreach ($meta_fields as $key => $args) {
            register_post_meta(self::POST_TYPE, $key, [
                'type'              => $args['type'],
                'single'            => true,
                'default'           => $args['default'],
                'show_in_rest'      => false,
                'auth_callback'     => fn() => current_user_can('manage_options'),
            ]);
        }
    }

    /**
     * Find the active subscription for a given user.
     */
    public static function get_user_subscription(int $user_id): ?\WP_Post {
        $posts = get_posts([
            'post_type'      => self::POST_TYPE,
            'post_status'    => 'publish',
            'posts_per_page' => 1,
            'meta_query'     => [
                'relation' => 'AND',
                [
                    'key'   => '_jenga_sub_user_id',
                    'value' => $user_id,
                    'type'  => 'NUMERIC',
                ],
                [
                    'key'     => '_jenga_sub_status',
                    'value'   => [self::STATUS_ACTIVE, self::STATUS_TRIALING],
                    'compare' => 'IN',
                ],
            ],
        ]);

        return $posts[0] ?? null;
    }

    /**
     * Find a subscription by Stripe Subscription ID.
     */
    public static function get_by_stripe_id(string $stripe_sub_id): ?\WP_Post {
        $posts = get_posts([
            'post_type'      => self::POST_TYPE,
            'post_status'    => 'publish',
            'posts_per_page' => 1,
            'meta_query'     => [
                [
                    'key'   => '_jenga_sub_stripe_id',
                    'value' => $stripe_sub_id,
                ],
            ],
        ]);

        return $posts[0] ?? null;
    }

    /**
     * Get the access tier level for a user based on their subscription.
     * Returns 0 (free) if no active subscription.
     */
    public static function get_user_tier(int $user_id): int {
        $sub = self::get_user_subscription($user_id);
        if (!$sub) {
            return 0;
        }

        $plan_id = (int) get_post_meta($sub->ID, '_jenga_sub_plan_id', true);
        if (!$plan_id) {
            return 0;
        }

        return (int) get_post_meta($plan_id, '_jenga_plan_tier', true);
    }

    /**
     * Format a subscription post into an API-friendly array.
     */
    public static function to_array(\WP_Post $post): array {
        $plan_id = (int) get_post_meta($post->ID, '_jenga_sub_plan_id', true);
        $plan_post = get_post($plan_id);

        return [
            'id'                 => $post->ID,
            'user_id'            => (int) get_post_meta($post->ID, '_jenga_sub_user_id', true),
            'plan_id'            => $plan_id,
            'plan'               => $plan_post ? Plan::to_array($plan_post) : null,
            'stripe_id'          => get_post_meta($post->ID, '_jenga_sub_stripe_id', true),
            'status'             => get_post_meta($post->ID, '_jenga_sub_status', true),
            'current_period_end' => (int) get_post_meta($post->ID, '_jenga_sub_current_period_end', true),
            'created_at'         => (int) get_post_meta($post->ID, '_jenga_sub_created_at', true),
        ];
    }
}
