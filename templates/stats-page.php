<?php
/**
 * Main Statistics Page Template
 */
defined('ABSPATH') || exit;

// Fetch aggregate data for cards
$stats = SA_Admin::get_overview_stats(7); 
?>

<div class="wrap sa-stats-wrap">
    <h1><?php echo esc_html( get_admin_page_title() ); ?></h1>

    <div class="sa-period-switcher">
        <a href="?page=the-simplest-analytics&period=7d" class="button <?php echo $period === '7d' ? 'button-primary' : ''; ?>"><?php esc_html_e('Last 7 Days', 'the-simplest-analytics'); ?></a>
        <a href="?page=the-simplest-analytics&period=30d" class="button <?php echo $period === '30d' ? 'button-primary' : ''; ?>"><?php esc_html_e('Last 30 Days', 'the-simplest-analytics'); ?></a>
        <a href="?page=the-simplest-analytics&period=90d" class="button <?php echo $period === '90d' ? 'button-primary' : ''; ?>"><?php esc_html_e('Last 90 Days', 'the-simplest-analytics'); ?></a>
    </div>

    <div class="sa-cards">
        <div class="sa-card">
            <h3><?php esc_html_e('Unique Visitors', 'the-simplest-analytics'); ?></h3>
            <div class="sa-number"><?php echo esc_html( number_format( (int) ( $stats['visitors'] ?? 0 ) ) ); ?></div>
        </div>
        <div class="sa-card">
            <h3><?php esc_html_e('Total Pageviews', 'the-simplest-analytics'); ?></h3>
            <div class="sa-number"><?php echo esc_html( number_format( (int) ( $stats['pageviews'] ?? 0 ) ) ); ?></div>
        </div>
        <div class="sa-card">
            <h3><?php esc_html_e('Bot Requests', 'the-simplest-analytics'); ?></h3>
            <div class="sa-number"><?php echo esc_html( number_format( (int) ( $stats['bots'] ?? 0 ) ) ); ?></div>
        </div>
    </div>

    <h2 class="nav-tab-wrapper">
        <a href="?page=the-simplest-analytics&tab=pages" class="nav-tab <?php echo $tab === 'pages' ? 'nav-tab-active' : ''; ?>"><?php esc_html_e('Top Pages', 'the-simplest-analytics'); ?></a>
        <a href="?page=the-simplest-analytics&tab=referrers" class="nav-tab <?php echo $tab === 'referrers' ? 'nav-tab-active' : ''; ?>"><?php esc_html_e('Referrers', 'the-simplest-analytics'); ?></a>
        <a href="?page=the-simplest-analytics&tab=bots" class="nav-tab <?php echo $tab === 'bots' ? 'nav-tab-active' : ''; ?>"><?php esc_html_e('Crawlers & AI', 'the-simplest-analytics'); ?></a>
        <a href="?page=the-simplest-analytics&tab=settings" class="nav-tab <?php echo $tab === 'settings' ? 'nav-tab-active' : ''; ?>"><?php esc_html_e('Settings', 'the-simplest-analytics'); ?></a>
    </h2>

    <div class="sa-tab-content">
        <?php
        // Dynamic loading of tab content
        switch ($tab) {
            case 'settings':
                include SA_PLUGIN_DIR . 'templates/partials/settings-view.php';
                break;
            case 'bots':
                include SA_PLUGIN_DIR . 'templates/partials/bots-table.php';
                break;
            case 'referrers':
                include SA_PLUGIN_DIR . 'templates/partials/referrers-table.php';
                break;
            case 'pages':
            default:
                include SA_PLUGIN_DIR . 'templates/partials/pages-table.php';
                break;
        }
        ?>
    </div>
</div>