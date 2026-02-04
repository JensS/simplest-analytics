<?php
/**
 * Referrers Table Partial
 */
defined('ABSPATH') || exit;

$days = match ($period) {
    '30d' => 30,
    '90d' => 90,
    default => 7,
};

$referrers = SA_Database::get_top_referrers($days, 50);
?>

<table class="wp-list-table widefat fixed striped">
    <thead>
        <tr>
            <th scope="col" class="manage-column column-primary"><?php esc_html_e('Referrer', 'the-simplest-analytics'); ?></th>
            <th scope="col" class="manage-column" style="width: 100px;"><?php esc_html_e('Visitors', 'the-simplest-analytics'); ?></th>
            <th scope="col" class="manage-column" style="width: 100px;"><?php esc_html_e('Views', 'the-simplest-analytics'); ?></th>
        </tr>
    </thead>
    <tbody>
        <?php if (empty($referrers)) : ?>
            <tr>
                <td colspan="3"><?php esc_html_e('No referrer data available for this period.', 'the-simplest-analytics'); ?></td>
            </tr>
        <?php else : ?>
            <?php foreach ($referrers as $ref) : ?>
                <tr>
                    <td class="column-primary">
                        <a href="https://<?php echo esc_attr($ref['referrer']); ?>" target="_blank" rel="noopener noreferrer">
                            <?php echo esc_html($ref['referrer']); ?>
                        </a>
                    </td>
                    <td><?php echo esc_html(number_format_i18n($ref['visitors'])); ?></td>
                    <td><?php echo esc_html(number_format_i18n($ref['views'])); ?></td>
                </tr>
            <?php endforeach; ?>
        <?php endif; ?>
    </tbody>
</table>
