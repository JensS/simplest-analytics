<?php
/**
 * Handles the normalized database schema and data insertion for Simplest Analytics.
 */

defined('ABSPATH') || exit;

class SA_Database {

	/**
	 * Creates the normalized table structure on activation.
	 */
	public static function create_tables() {
		global $wpdb;
		$charset_collate = $wpdb->get_charset_collate();
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		// 1. Paths Lookup Table
		$sql_paths = "CREATE TABLE {$wpdb->prefix}sa_paths (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			path_hash CHAR(32) NOT NULL,
			path_value TEXT NOT NULL,
			PRIMARY KEY (id),
			UNIQUE KEY path_hash (path_hash)
		) $charset_collate;";

		// 2. Referrers Lookup Table
		$sql_referrers = "CREATE TABLE {$wpdb->prefix}sa_referrers (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			ref_hash CHAR(32) NOT NULL,
			ref_value VARCHAR(255) NOT NULL,
			PRIMARY KEY (id),
			UNIQUE KEY ref_hash (ref_hash)
		) $charset_collate;";

		// 3. User Agents / Bots Lookup Table
		$sql_agents = "CREATE TABLE {$wpdb->prefix}sa_agents (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			agent_hash CHAR(32) NOT NULL,
			agent_value VARCHAR(255) NOT NULL,
			PRIMARY KEY (id),
			UNIQUE KEY agent_hash (agent_hash)
		) $charset_collate;";

		// 4. Campaigns Lookup Table (UTM parameters)
		$sql_campaigns = "CREATE TABLE {$wpdb->prefix}sa_campaigns (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			campaign_hash CHAR(32) NOT NULL,
			utm_source VARCHAR(100) DEFAULT NULL,
			utm_medium VARCHAR(100) DEFAULT NULL,
			utm_campaign VARCHAR(100) DEFAULT NULL,
			PRIMARY KEY (id),
			UNIQUE KEY campaign_hash (campaign_hash)
		) $charset_collate;";

		// 5. Main Pageviews Table (Fact Table)
		$sql_pageviews = "CREATE TABLE {$wpdb->prefix}sa_pageviews (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			recorded_at DATETIME NOT NULL,
			path_id BIGINT UNSIGNED NOT NULL,
			ref_id BIGINT UNSIGNED DEFAULT NULL,
			agent_id BIGINT UNSIGNED NOT NULL,
			campaign_id BIGINT UNSIGNED DEFAULT NULL,
			country_code CHAR(2) DEFAULT NULL,
			device_type TINYINT NOT NULL,
			is_unique TINYINT(1) NOT NULL DEFAULT 1,
			PRIMARY KEY (id),
			KEY idx_recorded_at (recorded_at),
			KEY idx_campaign (campaign_id)
		) $charset_collate;";

		dbDelta( $sql_paths );
		dbDelta( $sql_referrers );
		dbDelta( $sql_agents );
		dbDelta( $sql_campaigns );
		dbDelta( $sql_pageviews );
	}

	/**
	 * Main entry point for inserting a hit. Handles normalization on the fly.
	 */
	public static function insert_pageview( $data ) {
		global $wpdb;

		// 1. Normalize strings into IDs
		$path_id  = self::get_or_create_id( 'paths', $data['path'] );
		$ref_id   = ! empty( $data['referrer'] ) ? self::get_or_create_id( 'referrers', $data['referrer'] ) : null;

		// 2. Parse User Agent for Device Type and Agent Name
		$ua_info  = self::parse_user_agent( $data['user_agent'] );
		$agent_id = self::get_or_create_id( 'agents', $ua_info['name'] );

		// 3. Get Country Code (via SA_Geo)
		$country_code = SA_Geo::get_country_code( $data['ip'] );

		// 4. Get Campaign ID if UTM params present
		$campaign_id = null;
		if ( ! empty( $data['utm_source'] ) || ! empty( $data['utm_medium'] ) || ! empty( $data['utm_campaign'] ) ) {
			$campaign_id = self::get_or_create_campaign(
				$data['utm_source'] ?? '',
				$data['utm_medium'] ?? '',
				$data['utm_campaign'] ?? ''
			);
		}

		// 5. Final Insert
		$insert_data = [
			'recorded_at'  => $data['recorded_at'],
			'path_id'      => $path_id,
			'agent_id'     => $agent_id,
			'country_code' => $country_code,
			'device_type'  => $ua_info['type'],
			'is_unique'    => $data['is_unique'],
		];
		$format = [ '%s', '%d', '%d', '%s', '%d', '%d' ];

		if ( $ref_id !== null ) {
			$insert_data['ref_id'] = $ref_id;
			$format[] = '%d';
		}

		if ( $campaign_id !== null ) {
			$insert_data['campaign_id'] = $campaign_id;
			$format[] = '%d';
		}

		return $wpdb->insert(
			$wpdb->prefix . 'sa_pageviews',
			$insert_data,
			$format
		);
	}

	/**
	 * Get or create a campaign record from UTM parameters.
	 */
	private static function get_or_create_campaign( $source, $medium, $campaign ) {
		global $wpdb;
		$table = $wpdb->prefix . 'sa_campaigns';
		$hash  = md5( $source . '|' . $medium . '|' . $campaign );

		$id = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM $table WHERE campaign_hash = %s", $hash ) );

		if ( ! $id ) {
			$wpdb->insert( $table, [
				'campaign_hash' => $hash,
				'utm_source'    => substr( $source, 0, 100 ),
				'utm_medium'    => substr( $medium, 0, 100 ),
				'utm_campaign'  => substr( $campaign, 0, 100 ),
			] );
			$id = $wpdb->insert_id;
		}

		return $id;
	}

	/**
	 * Normalization Helper: Returns ID for a string, creates it if missing.
	 */
	private static function get_or_create_id( $table_suffix, $value ) {
		global $wpdb;
		$table = $wpdb->prefix . 'sa_' . $table_suffix;
		$hash  = md5( $value );
		$col   = ( $table_suffix === 'paths' ) ? 'path' : ( ( $table_suffix === 'referrers' ) ? 'ref' : 'agent' );

		$id = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM $table WHERE {$col}_hash = %s", $hash ) );

		if ( ! $id ) {
			$wpdb->insert( $table, [
				"{$col}_hash"  => $hash,
				"{$col}_value" => $value,
			] );
			$id = $wpdb->insert_id;
		}

		return $id;
	}

	/**
	 * Detects Device Type and Browser/Bot name from User Agent.
	 */
	private static function parse_user_agent( $ua ) {
		$ua_lower = strtolower( $ua );

		// Search Engines (Type 4)
		$search_bots = [
			'googlebot'    => 'Googlebot',
			'bingbot'      => 'Bingbot',
			'duckduckbot'  => 'DuckDuckBot',
			'baiduspider'  => 'Baiduspider',
			'yandexbot'    => 'YandexBot',
			'slurp'        => 'Yahoo Slurp',
		];
		foreach ( $search_bots as $key => $name ) {
			if ( str_contains( $ua_lower, $key ) ) {
				return [ 'type' => 4, 'name' => $name ];
			}
		}

		// AI & Marketing Bots (Type 5)
		$ai_bots = [
			'gptbot'        => 'GPTBot',
			'chatgpt'       => 'ChatGPT',
			'claudebot'     => 'ClaudeBot',
			'anthropic-ai'  => 'Anthropic',
			'perplexitybot' => 'PerplexityBot',
			'semrushbot'    => 'SEMrushBot',
			'ahrefsbot'     => 'AhrefsBot',
			'dotbot'        => 'DotBot',
			'petalbot'      => 'PetalBot',
			'bytespider'    => 'Bytespider',
		];
		foreach ( $ai_bots as $key => $name ) {
			if ( str_contains( $ua_lower, $key ) ) {
				return [ 'type' => 5, 'name' => $name ];
			}
		}

		// Device type detection
		$device_type = 1; // Desktop default
		if ( str_contains( $ua_lower, 'mobile' ) || ( str_contains( $ua_lower, 'android' ) && ! str_contains( $ua_lower, 'tablet' ) ) ) {
			$device_type = 2; // Mobile
		} elseif ( str_contains( $ua_lower, 'tablet' ) || str_contains( $ua_lower, 'ipad' ) ) {
			$device_type = 3; // Tablet
		}

		// Browser detection (order matters - check specific before generic)
		$browsers = [
			'edg/'      => 'Edge',
			'edge/'     => 'Edge',
			'opr/'      => 'Opera',
			'opera'     => 'Opera',
			'chrome'    => 'Chrome',
			'crios'     => 'Chrome',
			'safari'    => 'Safari',
			'firefox'   => 'Firefox',
			'fxios'     => 'Firefox',
			'msie'      => 'Internet Explorer',
			'trident'   => 'Internet Explorer',
			'samsung'   => 'Samsung Internet',
			'ucbrowser' => 'UC Browser',
			'brave'     => 'Brave',
			'vivaldi'   => 'Vivaldi',
			'duckduckgo'=> 'DuckDuckGo',
		];

		foreach ( $browsers as $key => $name ) {
			if ( str_contains( $ua_lower, $key ) ) {
				return [ 'type' => $device_type, 'name' => $name ];
			}
		}

		return [ 'type' => $device_type, 'name' => 'Other' ];
	}

	/**
	 * Daily cleanup for data retention.
	 */
	public static function cleanup_old_records( $days ) {
		global $wpdb;
		$table  = $wpdb->prefix . 'sa_pageviews';
		$cutoff = date( 'Y-m-d H:i:s', strtotime( "-$days days" ) );
		
		$wpdb->query( $wpdb->prepare( "DELETE FROM $table WHERE recorded_at < %s", $cutoff ) );
	}

	/**
	 * Fetches top performing pages for the admin UI.
	 */
	public static function get_top_pages( $days = 7, $limit = 20 ) {
		global $wpdb;
		$date_from = gmdate( 'Y-m-d H:i:s', strtotime( "-$days days" ) );

		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT
					p.path_value as path,
					COUNT(pv.id) as views,
					SUM(pv.is_unique) as visitors
				FROM {$wpdb->prefix}sa_pageviews pv
				JOIN {$wpdb->prefix}sa_paths p ON pv.path_id = p.id
				WHERE pv.recorded_at >= %s
				  AND pv.device_type NOT IN (4, 5)
				GROUP BY pv.path_id
				ORDER BY visitors DESC
				LIMIT %d",
				$date_from,
				$limit
			),
			ARRAY_A
		);
	}

	/**
	 * Fetches top referrers for the admin UI.
	 */
	public static function get_top_referrers( $days = 7, $limit = 20 ) {
		global $wpdb;
		$date_from = gmdate( 'Y-m-d H:i:s', strtotime( "-$days days" ) );

		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT
					r.ref_value as referrer,
					COUNT(pv.id) as views,
					SUM(pv.is_unique) as visitors
				FROM {$wpdb->prefix}sa_pageviews pv
				JOIN {$wpdb->prefix}sa_referrers r ON pv.ref_id = r.id
				WHERE pv.recorded_at >= %s
				  AND pv.device_type NOT IN (4, 5)
				GROUP BY pv.ref_id
				ORDER BY visitors DESC
				LIMIT %d",
				$date_from,
				$limit
			),
			ARRAY_A
		);
	}

	/**
	 * Fetches bot/crawler statistics for the admin UI.
	 */
	public static function get_bot_stats( $days = 7, $limit = 20 ) {
		global $wpdb;
		$date_from = gmdate( 'Y-m-d H:i:s', strtotime( "-$days days" ) );

		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT
					a.agent_value as agent,
					pv.device_type,
					COUNT(pv.id) as requests
				FROM {$wpdb->prefix}sa_pageviews pv
				JOIN {$wpdb->prefix}sa_agents a ON pv.agent_id = a.id
				WHERE pv.recorded_at >= %s
				  AND pv.device_type IN (4, 5)
				GROUP BY pv.agent_id, pv.device_type
				ORDER BY requests DESC
				LIMIT %d",
				$date_from,
				$limit
			),
			ARRAY_A
		);
	}

	/**
	 * Fetches campaign statistics for the admin UI.
	 */
	public static function get_campaign_stats( $days = 7, $limit = 20 ) {
		global $wpdb;
		$date_from = gmdate( 'Y-m-d H:i:s', strtotime( "-$days days" ) );

		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT
					c.utm_source,
					c.utm_medium,
					c.utm_campaign,
					COUNT(pv.id) as views,
					SUM(pv.is_unique) as visitors
				FROM {$wpdb->prefix}sa_pageviews pv
				JOIN {$wpdb->prefix}sa_campaigns c ON pv.campaign_id = c.id
				WHERE pv.recorded_at >= %s
				  AND pv.device_type NOT IN (4, 5)
				GROUP BY pv.campaign_id
				ORDER BY visitors DESC
				LIMIT %d",
				$date_from,
				$limit
			),
			ARRAY_A
		);
	}

	/**
	 * Get daily stats for charts.
	 */
	public static function get_daily_stats( $days = 7 ) {
		global $wpdb;
		$date_from = gmdate( 'Y-m-d', strtotime( "-$days days" ) );

		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT
					DATE(recorded_at) as date,
					COUNT(id) as views,
					SUM(is_unique) as visitors
				FROM {$wpdb->prefix}sa_pageviews
				WHERE DATE(recorded_at) >= %s
				  AND device_type NOT IN (4, 5)
				GROUP BY DATE(recorded_at)
				ORDER BY date ASC",
				$date_from
			),
			ARRAY_A
		);
	}

	/**
	 * Fetches country statistics for the admin UI.
	 */
	public static function get_country_stats( $days = 7, $limit = 30 ) {
		global $wpdb;
		$date_from = gmdate( 'Y-m-d H:i:s', strtotime( "-$days days" ) );

		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT
					country_code,
					COUNT(id) as views,
					SUM(is_unique) as visitors
				FROM {$wpdb->prefix}sa_pageviews
				WHERE recorded_at >= %s
				  AND device_type NOT IN (4, 5)
				  AND country_code IS NOT NULL
				  AND country_code != ''
				GROUP BY country_code
				ORDER BY visitors DESC
				LIMIT %d",
				$date_from,
				$limit
			),
			ARRAY_A
		);
	}

	/**
	 * Fetches browser statistics for the admin UI.
	 */
	public static function get_browser_stats( $days = 7, $limit = 20 ) {
		global $wpdb;
		$date_from = gmdate( 'Y-m-d H:i:s', strtotime( "-$days days" ) );

		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT
					a.agent_value as browser,
					COUNT(pv.id) as views,
					SUM(pv.is_unique) as visitors
				FROM {$wpdb->prefix}sa_pageviews pv
				JOIN {$wpdb->prefix}sa_agents a ON pv.agent_id = a.id
				WHERE pv.recorded_at >= %s
				  AND pv.device_type NOT IN (4, 5)
				GROUP BY pv.agent_id
				ORDER BY visitors DESC
				LIMIT %d",
				$date_from,
				$limit
			),
			ARRAY_A
		);
	}

	/**
	 * Fetches device type statistics for the admin UI.
	 */
	public static function get_device_stats( $days = 7 ) {
		global $wpdb;
		$date_from = gmdate( 'Y-m-d H:i:s', strtotime( "-$days days" ) );

		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT
					device_type,
					COUNT(id) as views,
					SUM(is_unique) as visitors
				FROM {$wpdb->prefix}sa_pageviews
				WHERE recorded_at >= %s
				  AND device_type NOT IN (4, 5)
				GROUP BY device_type
				ORDER BY visitors DESC",
				$date_from
			),
			ARRAY_A
		);
	}

	/**
	 * Get country name from ISO code.
	 */
	public static function get_country_name( $code ) {
		$countries = [
			'AF' => 'Afghanistan', 'AL' => 'Albania', 'DZ' => 'Algeria', 'AR' => 'Argentina',
			'AU' => 'Australia', 'AT' => 'Austria', 'BE' => 'Belgium', 'BR' => 'Brazil',
			'BG' => 'Bulgaria', 'CA' => 'Canada', 'CL' => 'Chile', 'CN' => 'China',
			'CO' => 'Colombia', 'HR' => 'Croatia', 'CZ' => 'Czechia', 'DK' => 'Denmark',
			'EG' => 'Egypt', 'EE' => 'Estonia', 'FI' => 'Finland', 'FR' => 'France',
			'DE' => 'Germany', 'GR' => 'Greece', 'HK' => 'Hong Kong', 'HU' => 'Hungary',
			'IN' => 'India', 'ID' => 'Indonesia', 'IE' => 'Ireland', 'IL' => 'Israel',
			'IT' => 'Italy', 'JP' => 'Japan', 'KR' => 'South Korea', 'LV' => 'Latvia',
			'LT' => 'Lithuania', 'MY' => 'Malaysia', 'MX' => 'Mexico', 'NL' => 'Netherlands',
			'NZ' => 'New Zealand', 'NO' => 'Norway', 'PK' => 'Pakistan', 'PH' => 'Philippines',
			'PL' => 'Poland', 'PT' => 'Portugal', 'RO' => 'Romania', 'RU' => 'Russia',
			'SA' => 'Saudi Arabia', 'SG' => 'Singapore', 'SK' => 'Slovakia', 'SI' => 'Slovenia',
			'ZA' => 'South Africa', 'ES' => 'Spain', 'SE' => 'Sweden', 'CH' => 'Switzerland',
			'TW' => 'Taiwan', 'TH' => 'Thailand', 'TR' => 'Turkey', 'UA' => 'Ukraine',
			'AE' => 'UAE', 'GB' => 'United Kingdom', 'US' => 'United States', 'VN' => 'Vietnam',
		];
		return $countries[ strtoupper( $code ) ] ?? $code;
	}

	/**
	 * Get device type name.
	 */
	public static function get_device_name( $type ) {
		$devices = [
			1 => 'Desktop',
			2 => 'Mobile',
			3 => 'Tablet',
		];
		return $devices[ (int) $type ] ?? 'Unknown';
	}
}