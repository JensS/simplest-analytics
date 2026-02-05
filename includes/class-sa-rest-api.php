<?php
/**
 * REST API Endpoints for Simplest Analytics.
 */
defined('ABSPATH') || exit;

class SA_REST_API {

    public static function init() {
        add_action('rest_api_init', [__CLASS__, 'register_routes']);
    }

    /**
     * Register the tracking route.
     */
    public static function register_routes() {
        register_rest_route('sa/v1', '/track', [
            'methods'             => 'POST',
            'callback'            => [__CLASS__, 'handle_track_ping'],
            'permission_callback' => '__return_true', // Public endpoint for tracking
        ]);

        register_rest_route('sa/v1', '/duration', [
            'methods'             => 'POST',
            'callback'            => [__CLASS__, 'handle_duration_ping'],
            'permission_callback' => '__return_true', // Public endpoint for tracking
        ]);
    }

    /**
     * Handle the duration update ping from the JS tracker.
     */
    public static function handle_duration_ping(WP_REST_Request $request) {
        $params = $request->get_json_params();

        $pageview_id = isset($params['pageview_id']) ? sanitize_text_field($params['pageview_id']) : null;
        $duration    = isset($params['duration']) ? absint($params['duration']) : 0;

        if (empty($pageview_id) || $duration <= 0) {
            return new WP_REST_Response(['updated' => false, 'reason' => 'invalid_payload'], 400);
        }

        $updated = SA_Database::update_duration($pageview_id, $duration);

        return new WP_REST_Response(['updated' => (bool) $updated], 200);
    }

    /**
     * Handle the tracking ping from JS fallback.
     * Note: sendBeacon cannot send custom headers, so we skip nonce verification
     * for this specific tracking endpoint. This is safe because:
     * 1. The endpoint only records pageview data (no sensitive operations)
     * 2. IP-based rate limiting can be added for abuse protection
     * 3. Data is anonymized before storage
     */
    public static function handle_track_ping(WP_REST_Request $request) {
        // Basic rate limiting via transient (10 requests per minute per IP)
        $ip = self::get_client_ip();
        $rate_key = 'sa_rate_' . md5($ip);
        $requests = (int) get_transient($rate_key);

        if ($requests > 10) {
            return new WP_REST_Response(['tracked' => false, 'reason' => 'rate_limited'], 429);
        }

        set_transient($rate_key, $requests + 1, MINUTE_IN_SECONDS);

        // Extract and sanitize parameters sent from tracker.js.
        $params    = $request->get_json_params();
        $full_path = isset($params['path']) ? sanitize_text_field($params['path']) : '/';
        $referrer  = isset($params['ref']) ? esc_url_raw($params['ref']) : '';
        $pageview_id = isset($params['pageview_id']) ? sanitize_text_field($params['pageview_id']) : null;
        $ua        = sanitize_text_field($_SERVER['HTTP_USER_AGENT'] ?? '');

        // Extract UTM params from the path before sanitizing it further
        $utm = self::extract_utm_from_path($full_path);

        // Process data similarly to the server-side tracker.
        $data = [
            'path'         => self::sanitize_path($full_path),
            'referrer'     => self::get_referrer_domain($referrer),
            'user_agent'   => $ua,
            'ip'           => self::get_anonymized_ip(),
            'is_unique'    => self::check_is_unique($ua),
            'recorded_at'  => current_time('mysql'),
            'pageview_id'  => $pageview_id,
            'user_agent'   => $ua,
            'ip'           => self::get_anonymized_ip(),
            'is_unique'    => self::check_is_unique($ua),
            'recorded_at'  => current_time('mysql'),
            'utm_source'   => $utm['source'],
            'utm_medium'   => $utm['medium'],
            'utm_campaign' => $utm['campaign'],
        ];

        $inserted = SA_Database::insert_pageview($data);

        return new WP_REST_Response(['tracked' => (bool) $inserted], 200);
    }

    /**
     * Get client IP address.
     */
    private static function get_client_ip() {
        return $_SERVER['REMOTE_ADDR'] ?? '';
    }

    /**
     * Truncate IP for GDPR compliance.
     */
    private static function get_anonymized_ip() {
        $ip = self::get_client_ip();

        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            return preg_replace('/[0-9]+$/', '0', $ip);
        }

        // IPv6: zero out last segment
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            $pos = strrpos($ip, ':');
            if ($pos !== false) {
                return substr($ip, 0, $pos) . ':0000';
            }
        }

        return '';
    }

    /**
     * Extract UTM parameters from a path with query string.
     */
    private static function extract_utm_from_path($path) {
        $utm = [
            'source'   => '',
            'medium'   => '',
            'campaign' => '',
        ];

        $query_string = wp_parse_url($path, PHP_URL_QUERY);
        if ($query_string) {
            parse_str($query_string, $query_params);
            $utm['source']   = isset($query_params['utm_source']) ? sanitize_text_field($query_params['utm_source']) : '';
            $utm['medium']   = isset($query_params['utm_medium']) ? sanitize_text_field($query_params['utm_medium']) : '';
            $utm['campaign'] = isset($query_params['utm_campaign']) ? sanitize_text_field($query_params['utm_campaign']) : '';
        }

        return $utm;
    }

    /**
     * Sanitize and normalize path.
     */
    private static function sanitize_path($path) {
        $path = sanitize_text_field($path);

        // Strip query parameters if enabled
        if (get_option('sa_strip_query_params', true)) {
            $path = strtok($path, '?');
        }

        // Normalize and limit length
        return substr(trailingslashit($path), 0, 2048);
    }

    /**
     * Extract referrer domain, excluding self-referrals.
     */
    private static function get_referrer_domain($referrer) {
        if (empty($referrer)) {
            return null;
        }

        $host = wp_parse_url($referrer, PHP_URL_HOST);
        if (empty($host)) {
            return null;
        }

        // Exclude self-referrals
        $site_host = wp_parse_url(home_url(), PHP_URL_HOST);
        if ($host === $site_host) {
            return null;
        }

        // Remove 'www.' and limit length
        return substr(preg_replace('/^www\./i', '', $host), 0, 255);
    }

    /**
     * Check if this is a unique visitor for today.
     */
    private static function check_is_unique($ua) {
        $ip = self::get_anonymized_ip();
        $salt = get_option('sa_daily_salt', '');
        $today = current_time('Y-m-d');

        // Rotate salt if the day has changed
        if (get_option('sa_salt_date') !== $today) {
            $salt = wp_generate_password(32, true, true);
            update_option('sa_daily_salt', $salt);
            update_option('sa_salt_date', $today);
        }

        $hash = hash('sha256', $ip . $ua . $salt);
        $cache_key = 'sa_visitors_' . $today;

        // Use a more memory-efficient bloom filter approach with hash prefix
        $hash_prefix = substr($hash, 0, 16);
        $visitors = get_transient($cache_key) ?: [];

        if (in_array($hash_prefix, $visitors, true)) {
            return 0; // Returning visitor
        }

        // Limit array size to prevent memory issues (accept some false negatives on very high traffic)
        if (count($visitors) < 50000) {
            $visitors[] = $hash_prefix;
            set_transient($cache_key, $visitors, DAY_IN_SECONDS);
        }

        return 1; // New unique visitor
    }
}
