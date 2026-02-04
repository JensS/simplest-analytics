<?php
/**
 * Settings View Partial
 */
defined('ABSPATH') || exit;

// Handle form submission
if (isset($_POST['sa_save_settings']) && check_admin_referer('sa_settings_nonce', 'sa_nonce')) {
    update_option('sa_tracking_enabled', isset($_POST['sa_tracking_enabled']));
    update_option('sa_retention_days', absint($_POST['sa_retention_days']));
    update_option('sa_respect_dnt', isset($_POST['sa_respect_dnt']));
    update_option('sa_strip_query_params', isset($_POST['sa_strip_query_params']));
    update_option('sa_enable_geo', isset($_POST['sa_enable_geo']));

    echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Settings saved.', 'the-simplest-analytics') . '</p></div>';
}

// Get current settings
$tracking_enabled = get_option('sa_tracking_enabled', true);
$retention_days = get_option('sa_retention_days', 365);
$respect_dnt = get_option('sa_respect_dnt', false);
$strip_query_params = get_option('sa_strip_query_params', true);
$enable_geo = get_option('sa_enable_geo', true);
$geo_db_updated = get_option('sa_geo_db_updated', '');
?>

<form method="post" action="">
    <?php wp_nonce_field('sa_settings_nonce', 'sa_nonce'); ?>

    <table class="form-table" role="presentation">
        <tr>
            <th scope="row"><?php esc_html_e('Enable Tracking', 'the-simplest-analytics'); ?></th>
            <td>
                <label>
                    <input type="checkbox" name="sa_tracking_enabled" value="1" <?php checked($tracking_enabled); ?>>
                    <?php esc_html_e('Collect analytics data', 'the-simplest-analytics'); ?>
                </label>
                <p class="description"><?php esc_html_e('Logged-in users are never tracked.', 'the-simplest-analytics'); ?></p>
            </td>
        </tr>

        <tr>
            <th scope="row"><?php esc_html_e('Data Retention', 'the-simplest-analytics'); ?></th>
            <td>
                <input type="number" name="sa_retention_days" value="<?php echo esc_attr($retention_days); ?>" min="7" max="3650" class="small-text">
                <?php esc_html_e('days', 'the-simplest-analytics'); ?>
                <p class="description"><?php esc_html_e('Pageview data older than this will be automatically deleted.', 'the-simplest-analytics'); ?></p>
            </td>
        </tr>

        <tr>
            <th scope="row"><?php esc_html_e('Privacy', 'the-simplest-analytics'); ?></th>
            <td>
                <fieldset>
                    <label>
                        <input type="checkbox" name="sa_respect_dnt" value="1" <?php checked($respect_dnt); ?>>
                        <?php esc_html_e('Respect Do Not Track (DNT) browser setting', 'the-simplest-analytics'); ?>
                    </label>
                    <br>
                    <label>
                        <input type="checkbox" name="sa_strip_query_params" value="1" <?php checked($strip_query_params); ?>>
                        <?php esc_html_e('Strip query parameters from URLs', 'the-simplest-analytics'); ?>
                    </label>
                </fieldset>
            </td>
        </tr>

        <tr>
            <th scope="row"><?php esc_html_e('Geolocation', 'the-simplest-analytics'); ?></th>
            <td>
                <label>
                    <input type="checkbox" name="sa_enable_geo" value="1" <?php checked($enable_geo); ?>>
                    <?php esc_html_e('Enable country detection', 'the-simplest-analytics'); ?>
                </label>
                <?php if ($geo_db_updated) : ?>
                    <p class="description">
                        <?php
                        printf(
                            /* translators: %s: date of last update */
                            esc_html__('Geo database last updated: %s', 'the-simplest-analytics'),
                            esc_html($geo_db_updated)
                        );
                        ?>
                    </p>
                <?php endif; ?>
            </td>
        </tr>
    </table>

    <?php submit_button(__('Save Settings', 'the-simplest-analytics'), 'primary', 'sa_save_settings'); ?>
</form>

<hr>

<h3><?php esc_html_e('Data Management', 'the-simplest-analytics'); ?></h3>
<p class="description">
    <?php esc_html_e('Database tables will be retained when the plugin is deactivated. To completely remove all data, delete the plugin.', 'the-simplest-analytics'); ?>
</p>
