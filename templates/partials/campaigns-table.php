<?php
/**
 * Campaigns Table Partial
 */
defined('ABSPATH') || exit;

$days = match ($period) {
    '30d' => 30,
    '90d' => 90,
    default => 7,
};

$campaigns = SA_Database::get_campaign_stats($days, 50);
?>

<p class="description">
    <?php esc_html_e('Track marketing campaigns using UTM parameters (utm_source, utm_medium, utm_campaign).', 'the-simplest-analytics'); ?>
</p>

<table class="wp-list-table widefat fixed striped">
    <thead>
        <tr>
            <th scope="col" class="manage-column"><?php esc_html_e('Source', 'the-simplest-analytics'); ?></th>
            <th scope="col" class="manage-column"><?php esc_html_e('Medium', 'the-simplest-analytics'); ?></th>
            <th scope="col" class="manage-column"><?php esc_html_e('Campaign', 'the-simplest-analytics'); ?></th>
            <th scope="col" class="manage-column" style="width: 100px;"><?php esc_html_e('Visitors', 'the-simplest-analytics'); ?></th>
            <th scope="col" class="manage-column" style="width: 100px;"><?php esc_html_e('Views', 'the-simplest-analytics'); ?></th>
        </tr>
    </thead>
    <tbody>
        <?php if (empty($campaigns)) : ?>
            <tr>
                <td colspan="5"><?php esc_html_e('No campaign data yet. Add ?utm_source=...&utm_medium=...&utm_campaign=... to your URLs.', 'the-simplest-analytics'); ?></td>
            </tr>
        <?php else : ?>
            <?php foreach ($campaigns as $campaign) : ?>
                <tr>
                    <td><?php echo esc_html($campaign['utm_source'] ?: '—'); ?></td>
                    <td><?php echo esc_html($campaign['utm_medium'] ?: '—'); ?></td>
                    <td><?php echo esc_html($campaign['utm_campaign'] ?: '—'); ?></td>
                    <td><?php echo esc_html(number_format_i18n((int) ($campaign['visitors'] ?? 0))); ?></td>
                    <td><?php echo esc_html(number_format_i18n((int) ($campaign['views'] ?? 0))); ?></td>
                </tr>
            <?php endforeach; ?>
        <?php endif; ?>
    </tbody>
</table>
