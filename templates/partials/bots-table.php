<?php
/**
 * Crawlers & AI Bots Table Partial
 */
defined('ABSPATH') || exit;

$days = match ($period) {
    '30d' => 30,
    '90d' => 90,
    default => 7,
};

$bots = SA_Database::get_bot_stats($days, 50);
?>

<p class="description">
    <?php esc_html_e('Tracking search engine crawlers and AI bots helps you understand how your content is being indexed and scraped.', 'the-simplest-analytics'); ?>
</p>

<table class="wp-list-table widefat fixed striped">
    <thead>
        <tr>
            <th scope="col" class="manage-column column-primary"><?php esc_html_e('Bot / Crawler', 'the-simplest-analytics'); ?></th>
            <th scope="col" class="manage-column" style="width: 80px;"><?php esc_html_e('Type', 'the-simplest-analytics'); ?></th>
            <th scope="col" class="manage-column" style="width: 100px;"><?php esc_html_e('Requests', 'the-simplest-analytics'); ?></th>
        </tr>
    </thead>
    <tbody>
        <?php if (empty($bots)) : ?>
            <tr>
                <td colspan="3"><?php esc_html_e('No bot activity recorded for this period.', 'the-simplest-analytics'); ?></td>
            </tr>
        <?php else : ?>
            <?php foreach ($bots as $bot) : ?>
                <tr>
                    <td class="column-primary"><?php echo esc_html($bot['agent']); ?></td>
                    <td>
                        <?php
                        $type_label = $bot['device_type'] == 4 ? __('Search', 'the-simplest-analytics') : __('AI/Marketing', 'the-simplest-analytics');
                        echo esc_html($type_label);
                        ?>
                    </td>
                    <td><?php echo esc_html(number_format_i18n($bot['requests'])); ?></td>
                </tr>
            <?php endforeach; ?>
        <?php endif; ?>
    </tbody>
</table>
