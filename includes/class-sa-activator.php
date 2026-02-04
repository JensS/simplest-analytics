<?php
/**
 * Fired during plugin activation.
 * This class defines all code necessary to run during the plugin's activation.
 */

defined('ABSPATH') || exit;

class SA_Activator {

	/**
	 * Run all activation logic.
	 */
	public static function activate() {
		// Create the normalized database tables via the Database class.
		SA_Database::create_tables();

		// Create the secure directory for the Geo-IP database.
		self::create_geo_directory();
		
		// Set default version and salt.
		add_option('sa_version', SA_VERSION);
		if ( ! get_option( 'sa_daily_salt' ) ) {
			update_option( 'sa_daily_salt', wp_generate_password( 32, true, true ) );
			update_option( 'sa_salt_date', date( 'Y-m-d' ) );
		}
	}

	/**
	 * Create the uploads directory for the .mmdb file to survive plugin updates.
	 */
	private static function create_geo_directory() {
		$upload_dir = wp_upload_dir();
		$sa_dir     = $upload_dir['basedir'] . '/simplest-analytics';

		if ( ! file_exists( $sa_dir ) ) {
			wp_mkdir_p( $sa_dir );
			
			// Add an index.php and .htaccess to prevent directory listing/access.
			file_put_contents( $sa_dir . '/index.php', '<?php // Silence' );
			file_put_contents( $sa_dir . '/.htaccess', 'Deny from all' );
		}
	}
}