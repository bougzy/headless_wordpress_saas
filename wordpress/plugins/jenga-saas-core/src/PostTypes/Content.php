<?php

declare(strict_types=1);

namespace Jenga\SaaS\PostTypes;

/**
 * Content post type — gated articles, courses, and resources.
 *
 * Meta fields:
 *  - _jenga_content_tier      (int)    Minimum tier required (0=free, 1=pro, 2=premium)
 *  - _jenga_content_excerpt   (string) Public teaser text
 *  - _jenga_content_read_time (int)    Estimated read time in minutes
 *  - _jenga_content_author_id (int)    WordPress user ID of the creator
 */
final class Content {

    public const POST_TYPE = 'jenga_content';
    public const TAXONOMY_TOPIC = 'jenga_topic';

    public function register(): void {
        add_action('init', [$this, 'register_post_type']);
        add_action('init', [$this, 'register_taxonomy']);
        add_action('init', [$this, 'register_meta']);
    }

    public function register_post_type(): void {
        $labels = [
            'name'               => __('Content', 'jenga-saas'),
            'singular_name'      => __('Content', 'jenga-saas'),
            'add_new_item'       => __('Add New Content', 'jenga-saas'),
            'edit_item'          => __('Edit Content', 'jenga-saas'),
            'all_items'          => __('All Content', 'jenga-saas'),
            'search_items'       => __('Search Content', 'jenga-saas'),
            'not_found'          => __('No content found.', 'jenga-saas'),
        ];

        register_post_type(self::POST_TYPE, [
            'labels'          => $labels,
            'public'          => false,
            'show_ui'         => true,
            'show_in_menu'    => true,
            'menu_icon'       => 'dashicons-media-document',
            'show_in_rest'    => false,
            'supports'        => ['title', 'editor', 'thumbnail', 'excerpt', 'revisions'],
            'capability_type' => 'post',
            'map_meta_cap'    => true,
            'has_archive'     => false,
            'rewrite'         => false,
        ]);
    }

    public function register_taxonomy(): void {
        register_taxonomy(self::TAXONOMY_TOPIC, self::POST_TYPE, [
            'labels' => [
                'name'          => __('Topics', 'jenga-saas'),
                'singular_name' => __('Topic', 'jenga-saas'),
            ],
            'public'            => false,
            'show_ui'           => true,
            'show_in_rest'      => false,
            'hierarchical'      => true,
            'show_admin_column' => true,
        ]);
    }

    public function register_meta(): void {
        $meta_fields = [
            '_jenga_content_tier'      => ['type' => 'integer', 'default' => 0],
            '_jenga_content_excerpt'   => ['type' => 'string',  'default' => ''],
            '_jenga_content_read_time' => ['type' => 'integer', 'default' => 5],
            '_jenga_content_author_id' => ['type' => 'integer', 'default' => 0],
        ];

        foreach ($meta_fields as $key => $args) {
            register_post_meta(self::POST_TYPE, $key, [
                'type'              => $args['type'],
                'single'            => true,
                'default'           => $args['default'],
                'show_in_rest'      => false,
                'auth_callback'     => fn() => current_user_can('edit_posts'),
            ]);
        }
    }

    /**
     * Check if a user has access to a specific content piece.
     */
    public static function user_can_access(int $content_id, int $user_id): bool {
        $required_tier = (int) get_post_meta($content_id, '_jenga_content_tier', true);

        // Free content is accessible to everyone
        if ($required_tier === 0) {
            return true;
        }

        // Admins always have access
        if (user_can($user_id, 'manage_options')) {
            return true;
        }

        $user_tier = Subscription::get_user_tier($user_id);
        return $user_tier >= $required_tier;
    }

    /**
     * Format a content post for the API. Respects access gating.
     *
     * @param bool $full Whether to include the full body (gated content).
     */
    public static function to_array(\WP_Post $post, bool $full = false): array {
        $tier = (int) get_post_meta($post->ID, '_jenga_content_tier', true);
        $author_id = (int) get_post_meta($post->ID, '_jenga_content_author_id', true) ?: $post->post_author;
        $author = get_userdata($author_id);

        $topics = wp_get_post_terms($post->ID, self::TAXONOMY_TOPIC, ['fields' => 'all']);
        $topic_list = [];
        if (!is_wp_error($topics)) {
            foreach ($topics as $topic) {
                $topic_list[] = [
                    'id'   => $topic->term_id,
                    'name' => $topic->name,
                    'slug' => $topic->slug,
                ];
            }
        }

        $data = [
            'id'           => $post->ID,
            'title'        => $post->post_title,
            'slug'         => $post->post_name,
            'excerpt'      => get_post_meta($post->ID, '_jenga_content_excerpt', true) ?: wp_trim_words($post->post_content, 40),
            'tier'         => $tier,
            'tier_label'   => self::tier_label($tier),
            'read_time'    => (int) get_post_meta($post->ID, '_jenga_content_read_time', true),
            'topics'       => $topic_list,
            'featured_image' => get_the_post_thumbnail_url($post->ID, 'large') ?: null,
            'author'       => $author ? [
                'id'     => $author->ID,
                'name'   => $author->display_name,
                'avatar' => get_avatar_url($author->ID, ['size' => 96]),
            ] : null,
            'published_at' => $post->post_date_gmt,
            'updated_at'   => $post->post_modified_gmt,
        ];

        // Only include body if access is granted
        if ($full) {
            $data['body'] = apply_filters('the_content', $post->post_content);
        }

        return $data;
    }

    public static function tier_label(int $tier): string {
        return match ($tier) {
            0 => 'Free',
            1 => 'Pro',
            2 => 'Premium',
            default => 'Unknown',
        };
    }
}
