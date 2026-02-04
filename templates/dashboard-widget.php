<?php
/**
 * Dashboard Widget Template
 */
defined('ABSPATH') || exit;
?>

<div class="sa-dashboard-widget">
    <div class="sa-widget-stats">
        <div class="sa-widget-stat">
            <span class="sa-widget-number"><?php echo esc_html(number_format_i18n($stats['visitors'])); ?></span>
            <span class="sa-widget-label"><?php esc_html_e('Visitors', 'the-simplest-analytics'); ?></span>
        </div>
        <div class="sa-widget-stat">
            <span class="sa-widget-number"><?php echo esc_html(number_format_i18n($stats['pageviews'])); ?></span>
            <span class="sa-widget-label"><?php esc_html_e('Pageviews', 'the-simplest-analytics'); ?></span>
        </div>
        <div class="sa-widget-stat">
            <span class="sa-widget-number"><?php echo esc_html(number_format_i18n($stats['bots'])); ?></span>
            <span class="sa-widget-label"><?php esc_html_e('Bots', 'the-simplest-analytics'); ?></span>
        </div>
    </div>
    <p class="sa-widget-period"><?php esc_html_e('Last 7 days', 'the-simplest-analytics'); ?></p>
    <p class="sa-widget-link">
        <a href="<?php echo esc_url(admin_url('tools.php?page=the-simplest-analytics')); ?>">
            <?php esc_html_e('View full statistics', 'the-simplest-analytics'); ?> &rarr;
        </a>
    </p>
</div>
