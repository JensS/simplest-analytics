<?php
/**
 * Browsers & Devices Table Partial
 */
defined('ABSPATH') || exit;

$days = match ($period) {
    '30d' => 30,
    '90d' => 90,
    default => 7,
};

$browsers = SA_Database::get_browser_stats($days, 20);
$devices = SA_Database::get_device_stats($days);
$max_browser_visitors = !empty($browsers) ? max(array_column($browsers, 'visitors')) : 1;
$total_device_visitors = array_sum(array_column($devices, 'visitors')) ?: 1;
?>

<div class="sa-two-columns">
    <div class="sa-column">
        <h3><?php esc_html_e('Browsers', 'the-simplest-analytics'); ?></h3>
        <?php if (empty($browsers)) : ?>
            <p><?php esc_html_e('No browser data available yet.', 'the-simplest-analytics'); ?></p>
        <?php else : ?>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th scope="col"><?php esc_html_e('Browser', 'the-simplest-analytics'); ?></th>
                        <th scope="col" style="width: 50%;"><?php esc_html_e('Visitors', 'the-simplest-analytics'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($browsers as $browser) :
                        $visitors = (int) ($browser['visitors'] ?? 0);
                        $bar_width = $max_browser_visitors > 0 ? ($visitors / $max_browser_visitors) * 100 : 0;
                    ?>
                        <tr>
                            <td>
                                <span class="sa-browser-icon"><?php echo esc_html(get_browser_icon($browser['browser'])); ?></span>
                                <?php echo esc_html($browser['browser']); ?>
                            </td>
                            <td>
                                <div class="sa-bar-container">
                                    <div class="sa-bar sa-bar-blue" style="width: <?php echo esc_attr($bar_width); ?>%;"></div>
                                    <span class="sa-bar-value"><?php echo esc_html(number_format_i18n($visitors)); ?></span>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>

    <div class="sa-column">
        <h3><?php esc_html_e('Devices', 'the-simplest-analytics'); ?></h3>
        <?php if (empty($devices)) : ?>
            <p><?php esc_html_e('No device data available yet.', 'the-simplest-analytics'); ?></p>
        <?php else : ?>
            <div class="sa-device-chart">
                <?php foreach ($devices as $device) :
                    $visitors = (int) ($device['visitors'] ?? 0);
                    $percentage = $total_device_visitors > 0 ? round(($visitors / $total_device_visitors) * 100) : 0;
                    $device_name = SA_Database::get_device_name($device['device_type']);
                    $device_icon = get_device_icon($device['device_type']);
                ?>
                    <div class="sa-device-row">
                        <div class="sa-device-info">
                            <span class="sa-device-icon"><?php echo esc_html($device_icon); ?></span>
                            <span class="sa-device-name"><?php echo esc_html($device_name); ?></span>
                        </div>
                        <div class="sa-device-bar-wrap">
                            <div class="sa-device-bar" style="width: <?php echo esc_attr($percentage); ?>%;"></div>
                        </div>
                        <div class="sa-device-percent"><?php echo esc_html($percentage); ?>%</div>
                    </div>
                <?php endforeach; ?>
            </div>
            <table class="wp-list-table widefat fixed striped" style="margin-top: 20px;">
                <thead>
                    <tr>
                        <th scope="col"><?php esc_html_e('Device', 'the-simplest-analytics'); ?></th>
                        <th scope="col" style="width: 100px;"><?php esc_html_e('Visitors', 'the-simplest-analytics'); ?></th>
                        <th scope="col" style="width: 100px;"><?php esc_html_e('Views', 'the-simplest-analytics'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($devices as $device) : ?>
                        <tr>
                            <td>
                                <?php echo esc_html(get_device_icon($device['device_type'])); ?>
                                <?php echo esc_html(SA_Database::get_device_name($device['device_type'])); ?>
                            </td>
                            <td><?php echo esc_html(number_format_i18n((int) ($device['visitors'] ?? 0))); ?></td>
                            <td><?php echo esc_html(number_format_i18n((int) ($device['views'] ?? 0))); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>

<?php
function get_browser_icon($browser) {
    $icons = [
        'Chrome'           => '🌐',
        'Firefox'          => '🦊',
        'Safari'           => '🧭',
        'Edge'             => '🌐',
        'Opera'            => '🔴',
        'Internet Explorer'=> '🔵',
        'Samsung Internet' => '🌐',
        'Brave'            => '🦁',
        'Vivaldi'          => '🎨',
    ];
    return $icons[$browser] ?? '🌐';
}

function get_device_icon($type) {
    $icons = [
        1 => '🖥️',  // Desktop
        2 => '📱',  // Mobile
        3 => '📱',  // Tablet
    ];
    return $icons[(int) $type] ?? '❓';
}
?>
