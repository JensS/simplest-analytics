<?php
/**
 * Top Pages Table Partial
 */
defined('ABSPATH') || exit;

$days = match ($period) {
    '30d' => 30,
    '90d' => 90,
    default => 7,
};

$pages = SA_Database::get_top_pages($days, 50);
?>

<table class="wp-list-table widefat fixed striped">
    <thead>
        <tr>
            <th scope="col" class="manage-column column-primary"><?php esc_html_e('Page', 'the-simplest-analytics'); ?></th>
            <th scope="col" class="manage-column" style="width: 100px;"><?php esc_html_e('Visitors', 'the-simplest-analytics'); ?></th>
            <th scope="col" class="manage-column" style="width: 100px;"><?php esc_html_e('Views', 'the-simplest-analytics'); ?></th>
        </tr>
    </thead>
    <tbody>
        <?php if (empty($pages)) : ?>
            <tr>
                <td colspan="3"><?php esc_html_e('No data available for this period.', 'the-simplest-analytics'); ?></td>
            </tr>
        <?php else : ?>
            <?php foreach ($pages as $page) : ?>
                <tr>
                    <td class="column-primary">
                        <a href="<?php echo esc_url(home_url($page['path'])); ?>" target="_blank">
                            <?php echo esc_html($page['path']); ?>
                        </a>
                    </td>
                    <td><?php echo esc_html(number_format_i18n($page['visitors'])); ?></td>
                    <td><?php echo esc_html(number_format_i18n($page['views'])); ?></td>
                </tr>
            <?php endforeach; ?>
        <?php endif; ?>
    </tbody>
</table>
