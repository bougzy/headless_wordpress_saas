<?php
/**
 * Plugin Name: Jenga SaaS Core
 * Plugin URI:  https://github.com/yourusername/headless-wordpress-saas
 * Description: Headless WordPress backend for the Jenga content subscription platform. Provides REST API, JWT auth, Stripe payments, and custom business logic.
 * Version:     1.0.0
 * Author:      Jenga Engineering
 * Author URI:  https://jenga.dev
 * License:     GPL-2.0-or-later
 * Text Domain: jenga-saas
 * Requires PHP: 8.1
 * Requires at least: 6.4
 */

declare(strict_types=1);

defined('ABSPATH') || exit;

define('JENGA_SAAS_VERSION', '1.0.0');
define('JENGA_SAAS_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('JENGA_SAAS_PLUGIN_URL', plugin_dir_url(__FILE__));

// Composer autoloader
if (file_exists(JENGA_SAAS_PLUGIN_DIR . 'vendor/autoload.php')) {
    require_once JENGA_SAAS_PLUGIN_DIR . 'vendor/autoload.php';
}

// Load environment-based configuration
require_once JENGA_SAAS_PLUGIN_DIR . 'config/settings.php';

/**
 * Boot the plugin.
 */
function jenga_saas_boot(): void {
    $plugin = \Jenga\SaaS\Plugin::get_instance();
    $plugin->init();
}
add_action('plugins_loaded', 'jenga_saas_boot');

/**
 * Activation hook — set up roles, capabilities, and flush rewrite rules.
 */
function jenga_saas_activate(): void {
    $role_manager = new \Jenga\SaaS\Roles\RoleManager();
    $role_manager->create_roles();
    flush_rewrite_rules();
}
register_activation_hook(__FILE__, 'jenga_saas_activate');

/**
 * Deactivation hook — clean up.
 */
function jenga_saas_deactivate(): void {
    flush_rewrite_rules();
}
register_deactivation_hook(__FILE__, 'jenga_saas_deactivate');
