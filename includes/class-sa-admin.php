<?php
/**
 * Handles the WordPress Admin interface, including the Dashboard widget and Statistics page.
 */

defined('ABSPATH') || exit;

class SA_Admin {

    /**
     * Initialize Admin hooks.
     */
    public static function init() {
        add_action( 'admin_menu', [ __CLASS__, 'add_admin_menu' ] );
        add_action( 'wp_dashboard_setup', [ __CLASS__, 'add_dashboard_widget' ] );
        add_action( 'admin_enqueue_scripts', [ __CLASS__, 'enqueue_admin_assets' ] );
    }

    /**
     * Register the main stats page in the Settings menu.
     */
    public static function add_admin_menu() {
        add_options_page(
            __( 'The Simplest Analytics', 'the-simplest-analytics' ),
            __( 'The Simplest Analytics', 'the-simplest-analytics' ),
            'manage_options',
            'the-simplest-analytics',
            [ __CLASS__, 'render_stats_page' ]
        );
    }

    /**
     * Register the Dashboard widget for quick overviews.
     */
    public static function add_dashboard_widget() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        wp_add_dashboard_widget(
            'sa_dashboard_widget',
            esc_html__( 'Analytics Overview', 'the-simplest-analytics' ),
            [ __CLASS__, 'render_dashboard_widget' ]
        );
    }

    /**
     * Enqueue CSS and JS only on relevant admin pages.
     */
    public static function enqueue_admin_assets( $hook ) {
        // Load only on the stats page or the main dashboard.
        if ( 'settings_page_the-simplest-analytics' !== $hook && 'index.php' !== $hook ) {
            return;
        }

        wp_enqueue_style( 'sa-admin-css', SA_PLUGIN_URL . 'assets/css/admin.css', [], SA_VERSION );
        
        if ( 'settings_page_the-simplest-analytics' === $hook ) {
            wp_enqueue_script( 'sa-admin-js', SA_PLUGIN_URL . 'assets/js/admin.js', [ 'jquery' ], SA_VERSION, true );
            wp_localize_script( 'sa-admin-js', 'saAdmin', [
                'ajaxUrl' => admin_url( 'admin-ajax.php' ),
                'nonce'   => wp_create_nonce( 'sa_admin_nonce' ),
            ] );
        }
    }

    /**
     * Render the main statistics page using sub-templates.
     */
    public static function render_stats_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Unauthorized access', 'the-simplest-analytics' ) );
        }

        // Determine current tab and period.
        $tab    = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'pages';
        $period = isset( $_GET['period'] ) ? sanitize_key( $_GET['period'] ) : '7d';

        // Load the main template file.
        include SA_PLUGIN_DIR . 'templates/stats-page.php';
    }

    /**
     * Render the Dashboard widget content.
     */
    public static function render_dashboard_widget() {
        // Retrieve cached stats to maintain performance.
        $stats = self::get_overview_stats( 7 ); 
        include SA_PLUGIN_DIR . 'templates/dashboard-widget.php';
    }

    /**
     * Fetch overview statistics from the normalized database.
     */
    public static function get_overview_stats( $days ) {
        global $wpdb;
        $table     = $wpdb->prefix . 'sa_pageviews';
        $date_from = date( 'Y-m-d H:i:s', strtotime( "-$days days" ) );

        $results = $wpdb->get_row( $wpdb->prepare(
            "SELECT 
                COUNT(*) as pageviews,
                SUM(is_unique) as visitors,
                SUM(CASE WHEN device_type IN (4, 5) THEN 1 ELSE 0 END) as bots
            FROM $table
            WHERE recorded_at >= %s",
            $date_from
        ), ARRAY_A );

        return $results ?: [ 'pageviews' => 0, 'visitors' => 0, 'bots' => 0 ];
    }
}