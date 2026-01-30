<?php

declare(strict_types=1);

namespace Jenga\SaaS\API\V1;

use Jenga\SaaS\Auth\JWT;
use Jenga\SaaS\Auth\Middleware;
use Jenga\SaaS\PostTypes\Content;

/**
 * Content REST API endpoints.
 *
 * GET /jenga/v1/content           - List content (public metadata)
 * GET /jenga/v1/content/{slug}    - Get single content (gated body)
 */
final class ContentController {

    private const NAMESPACE = 'jenga/v1';
    private Middleware $middleware;

    public function __construct() {
        $this->middleware = new Middleware();
    }

    public function register_routes(): void {
        register_rest_route(self::NAMESPACE, '/content', [
            'methods'             => 'GET',
            'callback'            => [$this, 'index'],
            'permission_callback' => '__return_true',
            'args'                => [
                'page'     => ['default' => 1,  'type' => 'integer', 'sanitize_callback' => 'absint'],
                'per_page' => ['default' => 12, 'type' => 'integer', 'sanitize_callback' => 'absint'],
                'topic'    => ['default' => '', 'type' => 'string',  'sanitize_callback' => 'sanitize_text_field'],
                'tier'     => ['default' => '', 'type' => 'string',  'sanitize_callback' => 'sanitize_text_field'],
                'search'   => ['default' => '', 'type' => 'string',  'sanitize_callback' => 'sanitize_text_field'],
            ],
        ]);

        register_rest_route(self::NAMESPACE, '/content/(?P<slug>[a-zA-Z0-9-]+)', [
            'methods'             => 'GET',
            'callback'            => [$this, 'show'],
            'permission_callback' => [$this->middleware, 'optional_auth'],
            'args'                => [
                'slug' => [
                    'required'          => true,
                    'type'              => 'string',
                    'sanitize_callback' => 'sanitize_title',
                ],
            ],
        ]);
    }

    /**
     * GET /content — paginated list with public metadata only.
     */
    public function index(\WP_REST_Request $request): \WP_REST_Response {
        $page     = max(1, $request->get_param('page'));
        $per_page = min(50, max(1, $request->get_param('per_page')));
        $topic    = $request->get_param('topic');
        $tier     = $request->get_param('tier');
        $search   = $request->get_param('search');

        $args = [
            'post_type'      => Content::POST_TYPE,
            'post_status'    => 'publish',
            'posts_per_page' => $per_page,
            'paged'          => $page,
            'orderby'        => 'date',
            'order'          => 'DESC',
        ];

        if ($topic) {
            $args['tax_query'] = [
                [
                    'taxonomy' => Content::TAXONOMY_TOPIC,
                    'field'    => 'slug',
                    'terms'    => $topic,
                ],
            ];
        }

        if ($tier !== '') {
            $args['meta_query'] = [
                [
                    'key'   => '_jenga_content_tier',
                    'value' => (int) $tier,
                    'type'  => 'NUMERIC',
                ],
            ];
        }

        if ($search) {
            $args['s'] = $search;
        }

        $query = new \WP_Query($args);
        $items = array_map(fn($p) => Content::to_array($p, false), $query->posts);

        return new \WP_REST_Response([
            'data' => $items,
            'meta' => [
                'total'        => $query->found_posts,
                'pages'        => $query->max_num_pages,
                'current_page' => $page,
                'per_page'     => $per_page,
            ],
        ], 200);
    }

    /**
     * GET /content/{slug} — single content with access gating.
     */
    public function show(\WP_REST_Request $request): \WP_REST_Response|\WP_Error {
        $slug = $request->get_param('slug');

        $posts = get_posts([
            'post_type'      => Content::POST_TYPE,
            'post_status'    => 'publish',
            'name'           => $slug,
            'posts_per_page' => 1,
        ]);

        if (empty($posts)) {
            return new \WP_Error('jenga_content_not_found', 'Content not found.', ['status' => 404]);
        }

        $post    = $posts[0];
        $user_id = get_current_user_id();
        $has_access = Content::user_can_access($post->ID, $user_id);

        $data = Content::to_array($post, $has_access);
        $data['has_access'] = $has_access;

        if (!$has_access) {
            $data['upgrade_message'] = sprintf(
                'This content requires a %s subscription. Upgrade to access it.',
                Content::tier_label((int) get_post_meta($post->ID, '_jenga_content_tier', true))
            );
        }

        return new \WP_REST_Response(['data' => $data], 200);
    }
}
