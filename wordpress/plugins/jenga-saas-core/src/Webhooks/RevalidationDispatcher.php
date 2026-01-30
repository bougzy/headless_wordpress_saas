<?php

declare(strict_types=1);

namespace Jenga\SaaS\Webhooks;

use Jenga\SaaS\PostTypes\Content;
use Jenga\SaaS\PostTypes\Plan;

/**
 * Dispatches ISR revalidation requests to the Next.js frontend
 * when WordPress content changes.
 */
final class RevalidationDispatcher {

    /**
     * Trigger revalidation when a post is saved.
     */
    public function on_post_save(int $post_id, \WP_Post $post): void {
        // Skip autosaves and revisions
        if (wp_is_post_autosave($post_id) || wp_is_post_revision($post_id)) {
            return;
        }

        $paths = $this->get_paths_for_post($post);
        if (!empty($paths)) {
            $this->dispatch($paths);
        }
    }

    /**
     * Trigger revalidation when a post is deleted.
     */
    public function on_post_delete(int $post_id): void {
        $post = get_post($post_id);
        if (!$post) {
            return;
        }

        $paths = $this->get_paths_for_post($post);
        if (!empty($paths)) {
            $this->dispatch($paths);
        }
    }

    /**
     * Determine which frontend paths need revalidation for a given post.
     */
    private function get_paths_for_post(\WP_Post $post): array {
        return match ($post->post_type) {
            Content::POST_TYPE => [
                '/content',
                '/content/' . $post->post_name,
            ],
            Plan::POST_TYPE => [
                '/pricing',
            ],
            default => [],
        };
    }

    /**
     * Send revalidation request to the Next.js frontend.
     */
    private function dispatch(array $paths): void {
        $url = JENGA_FRONTEND_URL . '/api/revalidate';

        wp_remote_post($url, [
            'body'      => wp_json_encode([
                'paths'  => $paths,
                'secret' => JENGA_REVALIDATION_SECRET,
            ]),
            'headers'   => ['Content-Type' => 'application/json'],
            'timeout'   => 5,
            'blocking'  => false, // Fire and forget — don't block WP admin
        ]);
    }
}
