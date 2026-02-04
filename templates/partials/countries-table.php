<?php
/**
 * Countries/Geo Table Partial
 */
defined('ABSPATH') || exit;

$days = match ($period) {
    '30d' => 30,
    '90d' => 90,
    default => 7,
};

$countries = SA_Database::get_country_stats($days, 50);
$max_visitors = !empty($countries) ? max(array_column($countries, 'visitors')) : 1;
?>

<p class="description">
    <?php esc_html_e('Visitor locations detected via Cloudflare headers or geo-IP database.', 'the-simplest-analytics'); ?>
</p>

<?php if (empty($countries)) : ?>
    <p><?php esc_html_e('No geo data available. Country detection requires Cloudflare or a geo-IP database.', 'the-simplest-analytics'); ?></p>
<?php else : ?>
    <table class="wp-list-table widefat fixed striped">
        <thead>
            <tr>
                <th scope="col" class="manage-column column-primary"><?php esc_html_e('Country', 'the-simplest-analytics'); ?></th>
                <th scope="col" class="manage-column" style="width: 40%;"><?php esc_html_e('Visitors', 'the-simplest-analytics'); ?></th>
                <th scope="col" class="manage-column" style="width: 100px;"><?php esc_html_e('Views', 'the-simplest-analytics'); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($countries as $country) :
                $visitors = (int) ($country['visitors'] ?? 0);
                $bar_width = $max_visitors > 0 ? ($visitors / $max_visitors) * 100 : 0;
                $country_name = SA_Database::get_country_name($country['country_code']);
            ?>
                <tr>
                    <td class="column-primary">
                        <span class="sa-country-flag"><?php echo esc_html(get_flag_emoji($country['country_code'])); ?></span>
                        <?php echo esc_html($country_name); ?>
                        <span class="sa-country-code">(<?php echo esc_html($country['country_code']); ?>)</span>
                    </td>
                    <td>
                        <div class="sa-bar-container">
                            <div class="sa-bar" style="width: <?php echo esc_attr($bar_width); ?>%;"></div>
                            <span class="sa-bar-value"><?php echo esc_html(number_format_i18n($visitors)); ?></span>
                        </div>
                    </td>
                    <td><?php echo esc_html(number_format_i18n((int) ($country['views'] ?? 0))); ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>

<?php
/**
 * Get flag emoji from country code.
 */
function get_flag_emoji($code) {
    $code = strtoupper($code);
    if (strlen($code) !== 2) {
        return '';
    }
    $flag = '';
    for ($i = 0; $i < 2; $i++) {
        $flag .= mb_chr(ord($code[$i]) - ord('A') + 0x1F1E6);
    }
    return $flag;
}
?>
