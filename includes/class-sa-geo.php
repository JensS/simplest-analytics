<?php
/**
 * Handles Geo-location lookups for Simplest Analytics.
 */

defined('ABSPATH') || exit;

class SA_Geo {

    /**
     * Determines the visitor's country code based on their anonymized IP.
     * * @param string $ip The visitor's IP address.
     * @return string|null Two-letter ISO country code or null.
     */
    public static function get_country_code( $ip ) {
        if ( ! get_option( 'sa_enable_geo', true ) ) {
            return null;
        }

        if ( empty( $ip ) || ! filter_var( $ip, FILTER_VALIDATE_IP ) ) {
            return null;
        }

        // Skip lookup for local/private IPs.
        if ( self::is_private_ip( $ip ) ) {
            return null;
        }

        // 1. Check for Proxy/CDN Headers first (Cloudflare, etc.).
        $country = self::get_country_from_headers();
        if ( $country ) {
            return $country;
        }

        // 2. Lookup from the local MMDB file.
        return self::lookup_country_in_db( $ip );
    }

    /**
     * Attempts to find the country code in server headers provided by CDNs.
     */
    private static function get_country_from_headers() {
        $headers = [
            'HTTP_CF_IPCOUNTRY',  // Cloudflare
            'HTTP_X_COUNTRY_CODE' // Generic Proxy
        ];

        foreach ( $headers as $header ) {
            if ( ! empty( $_SERVER[ $header ] ) ) {
                $code = strtoupper( sanitize_text_field( $_SERVER[ $header ] ) );
                if ( 2 === strlen( $code ) && 'XX' !== $code ) {
                    return $code;
                }
            }
        }

        return null;
    }

    /**
     * Performs a lookup using the bundled/downloaded MMDB database.
     */
    private static function lookup_country_in_db( $ip ) {
        $upload_dir = wp_upload_dir();
        $db_path    = $upload_dir['basedir'] . '/simplest-analytics/dbip-country-lite.mmdb';

        if ( ! file_exists( $db_path ) ) {
            return null;
        }

        try {
            // Note: Requires the MaxMind DB Reader PHP extension or library.
            // This assumes the library is included via composer or manually in 'includes/'.
            $reader = new \MaxMind\Db\Reader( $db_path );
            $record = $reader->get( $ip );
            $reader->close();

            return isset( $record['country']['iso_code'] ) ? $record['country']['iso_code'] : null;
        } catch ( \Exception $e ) {
            return null;
        }
    }

    /**
     * Utility to check if an IP is within a private or reserved range.
     */
    private static function is_private_ip( $ip ) {
        return ! filter_var(
            $ip,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
        );
    }

    /**
     * Downloads/Updates the Geo-IP database from DB-IP.
     */
    public static function update_database() {
        $url  = 'https://download.db-ip.com/free/dbip-country-lite-' . date('Y-m') . '.mmdb.gz';
        $upload_dir = wp_upload_dir();
        $dest = $upload_dir['basedir'] . '/simplest-analytics/dbip-country-lite.mmdb';

        $response = wp_remote_get( $url, [ 'timeout' => 120 ] );

        if ( is_wp_error( $response ) ) {
            return false;
        }

        $body = wp_remote_retrieve_body( $response );
        $data = gzdecode( $body );

        if ( false === $data ) {
            return false;
        }

        $result = file_put_contents( $dest, $data );

        if ( false !== $result ) {
            update_option( 'sa_geo_db_updated', current_time( 'mysql' ) );
            return true;
        }

        return false;
    }
}