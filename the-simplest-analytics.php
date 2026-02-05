<?php
/**
 * The Simplest Analytics
 *
 * Privacy-first, lightweight analytics for WordPress.
 *
 * @package           TheSimplestAnalytics
 * @author            Jens Sage
 * @copyright         2026 Jens Sage
 * @license           GPL-2.0-or-later
 *
 * @wordpress-plugin
 * Plugin Name:       The Simplest Analytics
 * Plugin URI:        https://github.com/JensS/simplest-analytics
 * Description:       Privacy-first, lightweight, and cache-compatible analytics. No cookies, GDPR compliant, and tracks all bots including AI crawlers.
 * Version:           1.3.1
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            Jens Sage
 * Author URI:        https://www.jenssage.com
 * Text Domain:       the-simplest-analytics
 * Domain Path:       /languages
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 */

defined('ABSPATH') || exit;

/**
 * Define Plugin Constants
 */
define('SA_VERSION', '1.3.1');
define('SA_PLUGIN_FILE', __FILE__);
define('SA_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('SA_PLUGIN_URL', plugin_dir_url(__FILE__));
define('SA_PLUGIN_BASENAME', plugin_basename(__FILE__));

/**
 * Autoload Classes
 */
require_once SA_PLUGIN_DIR . 'includes/class-sa-database.php';
require_once SA_PLUGIN_DIR . 'includes/class-sa-activator.php';
require_once SA_PLUGIN_DIR . 'includes/class-sa-tracker.php';
require_once SA_PLUGIN_DIR . 'includes/class-sa-geo.php';
require_once SA_PLUGIN_DIR . 'includes/class-sa-admin.php';
require_once SA_PLUGIN_DIR . 'includes/class-sa-rest-api.php';

/**
 * Plugin Activation
 * Creates normalized tables and schedules daily cleanup.
 */
register_activation_hook(__FILE__, function() {
    // Run full activation logic via Activator class
    SA_Activator::activate();

    // Default settings initialization
    add_option('sa_tracking_enabled', true);
    add_option('sa_retention_days', 365);

    // Schedule the daily cleanup via WP-Cron
    if (!wp_next_scheduled('sa_daily_cleanup')) {
        wp_schedule_event(time(), 'daily', 'sa_daily_cleanup');
    }

    flush_rewrite_rules();
});

/**
 * Plugin Deactivation
 * Cleans up scheduled cron jobs but keeps data.
 */
register_deactivation_hook(__FILE__, function() {
    wp_clear_scheduled_hook('sa_daily_cleanup');
});

/**
 * Initialize The Simplest Analytics
 */
add_action('plugins_loaded', function() {
    // Load translations
    load_plugin_textdomain(
        'the-simplest-analytics',
        false,
        dirname(SA_PLUGIN_BASENAME) . '/languages'
    );

    // Initialize Components
    SA_Tracker::init();   // Logic for PHP tracking & script injection
    SA_Admin::init();     // Admin UI and Dashboard widgets
    SA_REST_API::init();  // REST endpoints for JS fallback
});

/**
 * Daily Cleanup Handler
 */
add_action('sa_daily_cleanup', function() {
    $retention = get_option('sa_retention_days', 365);
    SA_Database::cleanup_old_records($retention); // Normalised cleanup logic
});