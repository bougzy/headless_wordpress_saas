<?php
/**
 * Environment-based configuration for Jenga SaaS.
 *
 * Values are read from wp-config.php constants or environment variables.
 * Never commit secrets — use environment variables in production.
 */

declare(strict_types=1);

defined('ABSPATH') || exit;

/**
 * Retrieve a Jenga config value from constants or env.
 */
function jenga_config(string $key, mixed $default = null): mixed {
    if (defined($key)) {
        return constant($key);
    }

    $env = getenv($key);
    return $env !== false ? $env : $default;
}

// JWT
define('JENGA_JWT_SECRET', jenga_config('JENGA_JWT_SECRET', 'change-me-in-production'));
define('JENGA_JWT_EXPIRATION', (int) jenga_config('JENGA_JWT_EXPIRATION', 3600));
define('JENGA_JWT_REFRESH_EXPIRATION', (int) jenga_config('JENGA_JWT_REFRESH_EXPIRATION', 604800));

// Stripe
define('JENGA_STRIPE_SECRET_KEY', jenga_config('JENGA_STRIPE_SECRET_KEY', ''));
define('JENGA_STRIPE_PUBLISHABLE_KEY', jenga_config('JENGA_STRIPE_PUBLISHABLE_KEY', ''));
define('JENGA_STRIPE_WEBHOOK_SECRET', jenga_config('JENGA_STRIPE_WEBHOOK_SECRET', ''));

// Frontend
define('JENGA_FRONTEND_URL', jenga_config('JENGA_FRONTEND_URL', 'http://localhost:3000'));

// Revalidation
define('JENGA_REVALIDATION_SECRET', jenga_config('JENGA_REVALIDATION_SECRET', 'change-me'));

// Rate limiting
define('JENGA_RATE_LIMIT_REQUESTS', (int) jenga_config('JENGA_RATE_LIMIT_REQUESTS', 60));
define('JENGA_RATE_LIMIT_WINDOW', (int) jenga_config('JENGA_RATE_LIMIT_WINDOW', 60));
