<?php
/**
 * The Simplest Analytics Uninstall
 *
 * Removes all plugin data when the plugin is deleted.
 */

// Exit if not called by WordPress
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

global $wpdb;

// Drop custom tables
$tables = [
    $wpdb->prefix . 'sa_pageviews',
    $wpdb->prefix . 'sa_paths',
    $wpdb->prefix . 'sa_referrers',
    $wpdb->prefix . 'sa_agents',
    $wpdb->prefix . 'sa_campaigns',
];

foreach ( $tables as $table ) {
    $wpdb->query( "DROP TABLE IF EXISTS {$table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
}

// Delete plugin options
$options = [
    'sa_version',
    'sa_tracking_enabled',
    'sa_retention_days',
    'sa_daily_salt',
    'sa_salt_date',
    'sa_respect_dnt',
    'sa_strip_query_params',
    'sa_enable_geo',
    'sa_excluded_roles',
    'sa_geo_db_updated',
];

foreach ( $options as $option ) {
    delete_option( $option );
}

// Delete transients
$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_sa_%'" );
$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_sa_%'" );

// Remove geo database directory
$upload_dir = wp_upload_dir();
$sa_dir = $upload_dir['basedir'] . '/simplest-analytics';

if ( is_dir( $sa_dir ) ) {
    $files = glob( $sa_dir . '/*' );
    foreach ( $files as $file ) {
        if ( is_file( $file ) ) {
            unlink( $file );
        }
    }
    rmdir( $sa_dir );
}

// Clear scheduled hooks
wp_clear_scheduled_hook( 'sa_daily_cleanup' );
