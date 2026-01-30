<?php

declare(strict_types=1);

namespace Jenga\SaaS\PostTypes;

/**
 * Plan post type — represents subscription tiers (Free, Pro, Premium).
 *
 * Meta fields:
 *  - _jenga_plan_price        (float)  Monthly price in cents
 *  - _jenga_plan_stripe_price (string) Stripe Price ID
 *  - _jenga_plan_features     (array)  Feature list as JSON
 *  - _jenga_plan_tier         (int)    Access tier level (0=free, 1=pro, 2=premium)
 *  - _jenga_plan_active       (bool)   Whether the plan is currently offered
 */
final class Plan {

    public const POST_TYPE = 'jenga_plan';

    public function register(): void {
        add_action('init', [$this, 'register_post_type']);
        add_action('init', [$this, 'register_meta']);
    }

    public function register_post_type(): void {
        $labels = [
            'name'               => __('Plans', 'jenga-saas'),
            'singular_name'      => __('Plan', 'jenga-saas'),
            'add_new_item'       => __('Add New Plan', 'jenga-saas'),
            'edit_item'          => __('Edit Plan', 'jenga-saas'),
            'all_items'          => __('All Plans', 'jenga-saas'),
            'search_items'       => __('Search Plans', 'jenga-saas'),
            'not_found'          => __('No plans found.', 'jenga-saas'),
        ];

        register_post_type(self::POST_TYPE, [
            'labels'              => $labels,
            'public'              => false,
            'show_ui'             => true,
            'show_in_menu'        => true,
            'menu_icon'           => 'dashicons-clipboard',
            'show_in_rest'        => false, // We expose via custom REST routes
            'supports'            => ['title', 'editor', 'thumbnail'],
            'capability_type'     => 'post',
            'map_meta_cap'        => true,
            'has_archive'         => false,
            'rewrite'             => false,
        ]);
    }

    public function register_meta(): void {
        $meta_fields = [
            '_jenga_plan_price' => [
                'type'         => 'number',
                'description'  => 'Monthly price in cents',
                'single'       => true,
                'default'      => 0,
            ],
            '_jenga_plan_stripe_price' => [
                'type'         => 'string',
                'description'  => 'Stripe Price ID',
                'single'       => true,
                'default'      => '',
            ],
            '_jenga_plan_features' => [
                'type'         => 'string',
                'description'  => 'JSON array of feature strings',
                'single'       => true,
                'default'      => '[]',
            ],
            '_jenga_plan_tier' => [
                'type'         => 'integer',
                'description'  => 'Access tier level (0=free, 1=pro, 2=premium)',
                'single'       => true,
                'default'      => 0,
            ],
            '_jenga_plan_active' => [
                'type'         => 'boolean',
                'description'  => 'Whether this plan is currently available',
                'single'       => true,
                'default'      => true,
            ],
        ];

        foreach ($meta_fields as $key => $args) {
            register_post_meta(self::POST_TYPE, $key, [
                'type'              => $args['type'],
                'description'       => $args['description'],
                'single'            => $args['single'],
                'default'           => $args['default'],
                'show_in_rest'      => false,
                'sanitize_callback' => $this->get_sanitize_callback($args['type']),
                'auth_callback'     => fn() => current_user_can('manage_options'),
            ]);
        }
    }

    private function get_sanitize_callback(string $type): callable {
        return match ($type) {
            'number'  => fn($v) => (float) $v,
            'integer' => fn($v) => (int) $v,
            'boolean' => fn($v) => (bool) $v,
            'string'  => 'sanitize_text_field',
            default   => 'sanitize_text_field',
        };
    }

    /**
     * Get all active plans, ordered by tier.
     *
     * @return \WP_Post[]
     */
    public static function get_active_plans(): array {
        return get_posts([
            'post_type'      => self::POST_TYPE,
            'post_status'    => 'publish',
            'posts_per_page' => 20,
            'meta_key'       => '_jenga_plan_active',
            'meta_value'     => '1',
            'orderby'        => 'meta_value_num',
            'meta_key'       => '_jenga_plan_tier',
            'order'          => 'ASC',
        ]);
    }

    /**
     * Format a plan post into an API-friendly array.
     */
    public static function to_array(\WP_Post $post): array {
        return [
            'id'           => $post->ID,
            'name'         => $post->post_title,
            'description'  => $post->post_content,
            'slug'         => $post->post_name,
            'price'        => (float) get_post_meta($post->ID, '_jenga_plan_price', true),
            'stripe_price' => get_post_meta($post->ID, '_jenga_plan_stripe_price', true),
            'features'     => json_decode(get_post_meta($post->ID, '_jenga_plan_features', true) ?: '[]', true),
            'tier'         => (int) get_post_meta($post->ID, '_jenga_plan_tier', true),
            'active'       => (bool) get_post_meta($post->ID, '_jenga_plan_active', true),
        ];
    }
}
