<?php
/**
 * Handles the hybrid tracking logic (Server-side + JS Fallback).
 */

defined('ABSPATH') || exit;

class SA_Tracker {

	private static $tracked = false;

	public static function init() {
		// Hook into template_redirect for primary server-side tracking.
		add_action( 'template_redirect', [ __CLASS__, 'handle_server_side_tracking' ] );

		// Enqueue JS fallback in footer (after server-side tracking has run).
		add_action( 'wp_footer', [ __CLASS__, 'enqueue_tracking_assets' ], 5 );
	}

	/**
	 * Primary Tracking: Runs if PHP is executing the request (Uncached).
	 */
	public static function handle_server_side_tracking() {
		if ( self::should_skip_tracking() ) {
			return;
		}

		$data = self::collect_request_data();
		$inserted = SA_Database::insert_pageview( $data );

		if ( $inserted ) {
			self::$tracked = true;
		}
	}

	/**
	 * Enqueues the tracker.js which pings the REST API only if server-side tracking didn't run.
	 * This is hooked to wp_footer so it runs AFTER template_redirect (where server tracking happens).
	 */
	public static function enqueue_tracking_assets() {
		if ( is_admin() || self::is_excluded_user() ) {
			return;
		}

		// If server-side already tracked this request, don't load JS at all
		if ( self::$tracked ) {
			return;
		}

		wp_enqueue_script( 'sa-tracker', SA_PLUGIN_URL . 'assets/js/tracker.js', [], SA_VERSION, false );

		wp_localize_script( 'sa-tracker', 'sa_params', [
			'api_url' => get_rest_url( null, 'sa/v1/track' ),
		] );
	}

	/**
	 * Collects and anonymizes data for storage.
	 */
	private static function collect_request_data() {
		$ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
		
		return [
			'path'         => self::get_clean_path(),
			'referrer'     => self::get_referrer_domain(),
			'user_agent'   => $ua,
			'ip'           => self::get_anonymized_ip(), // IP is truncated here.
			'is_unique'    => self::check_is_unique( $ua ),
			'recorded_at'  => current_time( 'mysql' ),
		];
	}

	/**
	 * Truncates IP for GDPR compliance before it touches the database.
	 */
	private static function get_anonymized_ip() {
		$ip = $_SERVER['REMOTE_ADDR'] ?? '';
		if ( filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 ) ) {
			return preg_replace( '/[0-9]+$/', '0', $ip );
		}
		return substr( $ip, 0, strrpos( $ip, ':' ) ) . ':0000';
	}

	/**
	 * Logic to determine if we should skip (DNT headers, logged-in admins, etc.).
	 */
	private static function should_skip_tracking() {
		if ( is_admin() || is_preview() || is_robots() ) return true;
		if ( get_option( 'sa_respect_dnt' ) && isset( $_SERVER['HTTP_DNT'] ) && $_SERVER['HTTP_DNT'] == 1 ) return true;
		if ( self::is_excluded_user() ) return true;
		return false;
	}

	private static function is_excluded_user() {
		// Don't track any logged-in users by default
		if ( is_user_logged_in() ) {
			return true;
		}
		return false;
	}

	/**
	 * Helper methods for SA_Tracker to be added inside the class.
	 */
	private static function get_clean_path() {
		$path = $_SERVER['REQUEST_URI'] ?? '/';
		
		// Strip query parameters if enabled in settings.
		if (get_option('sa_strip_query_params', true)) {
			$path = strtok($path, '?');
		}
		
		// Normalize trailing slashes and limit length for database.
		return substr(trailingslashit($path), 0, 2048);
	}

	private static function get_referrer_domain() {
		$referrer = $_SERVER['HTTP_REFERER'] ?? '';
		if (empty($referrer)) return null;

		$host = wp_parse_url($referrer, PHP_URL_HOST);
		if (empty($host)) return null;

		// Exclude self-referrals.
		$site_host = wp_parse_url(home_url(), PHP_URL_HOST);
		if ($host === $site_host) return null;

		// Remove 'www.' and limit length.
		return substr(preg_replace('/^www\./i', '', $host), 0, 255);
	}

	private static function check_is_unique($ua) {
		$ip = self::get_anonymized_ip();
		$salt = get_option('sa_daily_salt', '');
		$today = current_time('Y-m-d');

		// Rotate salt if the day has changed.
		if (get_option('sa_salt_date') !== $today) {
			$salt = wp_generate_password(32, true, true);
			update_option('sa_daily_salt', $salt);
			update_option('sa_salt_date', $today);
		}

		$hash = hash('sha256', $ip . $ua . $salt);
		$cache_key = 'sa_visitors_' . $today;

		// Use hash prefix for memory efficiency (16 chars = 64 bits of entropy, sufficient for uniqueness)
		$hash_prefix = substr($hash, 0, 16);
		$visitors = get_transient($cache_key) ?: [];

		if (in_array($hash_prefix, $visitors, true)) {
			return 0; // Returning visitor.
		}

		// Limit array size to prevent memory issues on high-traffic sites
		if (count($visitors) < 50000) {
			$visitors[] = $hash_prefix;
			set_transient($cache_key, $visitors, DAY_IN_SECONDS);
		}

		return 1; // New unique visitor.
	}
}