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

		// 4. Main Pageviews Table (Fact Table)
		$sql_pageviews = "CREATE TABLE {$wpdb->prefix}sa_pageviews (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			recorded_at DATETIME NOT NULL,
			path_id BIGINT UNSIGNED NOT NULL,
			ref_id BIGINT UNSIGNED DEFAULT NULL,
			agent_id BIGINT UNSIGNED NOT NULL,
			country_code CHAR(2) DEFAULT NULL,
			device_type TINYINT NOT NULL,
			is_unique TINYINT(1) NOT NULL DEFAULT 1,
			PRIMARY KEY (id),
			KEY idx_recorded_at (recorded_at)
		) $charset_collate;";

		dbDelta( $sql_paths );
		dbDelta( $sql_referrers );
		dbDelta( $sql_agents );
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

		// 4. Final Insert (handle NULL ref_id properly)
		$insert_data = [
			'recorded_at'  => $data['recorded_at'],
			'path_id'      => $path_id,
			'agent_id'     => $agent_id,
			'country_code' => $country_code,
			'device_type'  => $ua_info['type'],
			'is_unique'    => $data['is_unique'],
		];
		$format = [ '%s', '%d', '%d', '%s', '%d', '%d' ];

		// Only include ref_id if it's not null
		if ( $ref_id !== null ) {
			$insert_data['ref_id'] = $ref_id;
			$format[] = '%d';
		}

		return $wpdb->insert(
			$wpdb->prefix . 'sa_pageviews',
			$insert_data,
			$format
		);
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
		$search_bots = [ 'googlebot', 'bingbot', 'duckduckbot', 'baiduspider', 'yandexbot' ];
		foreach ( $search_bots as $bot ) {
			if ( str_contains( $ua_lower, $bot ) ) {
				return [ 'type' => 4, 'name' => ucfirst( $bot ) ];
			}
		}

		// AI & Marketing Bots (Type 5)
		$ai_bots = [ 'gptbot', 'claudebot', 'anthropic-ai', 'perplexitybot', 'semrushbot', 'ahrefsbot' ];
		foreach ( $ai_bots as $bot ) {
			if ( str_contains( $ua_lower, $bot ) ) {
				return [ 'type' => 5, 'name' => ucfirst( $bot ) ];
			}
		}

		// Device Detection
		if ( str_contains( $ua_lower, 'mobile' ) || str_contains( $ua_lower, 'android' ) ) {
			return [ 'type' => 2, 'name' => 'Mobile User' ];
		}
		if ( str_contains( $ua_lower, 'tablet' ) || str_contains( $ua_lower, 'ipad' ) ) {
			return [ 'type' => 3, 'name' => 'Tablet User' ];
		}

		return [ 'type' => 1, 'name' => 'Desktop User' ]; // Default
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
}