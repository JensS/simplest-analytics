<?php
/**
 * Main Statistics Page Template
 */
defined('ABSPATH') || exit;

// Determine period
$days = match ($period) {
    '30d' => 30,
    '90d' => 90,
    default => 7,
};

// Fetch aggregate data for cards
$stats = SA_Admin::get_overview_stats($days);

// Fetch daily data for chart
$daily_stats = SA_Database::get_daily_stats($days);
$max_visitors = max(array_column($daily_stats, 'visitors') ?: [1]);
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

    <?php if (!empty($daily_stats)) : ?>
    <div class="sa-chart-container">
        <h3><?php esc_html_e('Daily Visitors', 'the-simplest-analytics'); ?></h3>
        <div class="sa-chart">
            <?php foreach ($daily_stats as $day) :
                $height = $max_visitors > 0 ? ((int) $day['visitors'] / $max_visitors) * 100 : 0;
                $date = date_i18n('M j', strtotime($day['date']));
            ?>
            <div class="sa-chart-bar-wrap" title="<?php echo esc_attr($date . ': ' . number_format_i18n((int) $day['visitors']) . ' visitors'); ?>">
                <div class="sa-chart-bar" style="height: <?php echo esc_attr($height); ?>%;"></div>
                <span class="sa-chart-label"><?php echo esc_html($date); ?></span>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <h2 class="nav-tab-wrapper">
        <a href="?page=the-simplest-analytics&tab=pages&period=<?php echo esc_attr($period); ?>" class="nav-tab <?php echo $tab === 'pages' ? 'nav-tab-active' : ''; ?>"><?php esc_html_e('Top Pages', 'the-simplest-analytics'); ?></a>
        <a href="?page=the-simplest-analytics&tab=referrers&period=<?php echo esc_attr($period); ?>" class="nav-tab <?php echo $tab === 'referrers' ? 'nav-tab-active' : ''; ?>"><?php esc_html_e('Referrers', 'the-simplest-analytics'); ?></a>
        <a href="?page=the-simplest-analytics&tab=campaigns&period=<?php echo esc_attr($period); ?>" class="nav-tab <?php echo $tab === 'campaigns' ? 'nav-tab-active' : ''; ?>"><?php esc_html_e('Campaigns', 'the-simplest-analytics'); ?></a>
        <a href="?page=the-simplest-analytics&tab=countries&period=<?php echo esc_attr($period); ?>" class="nav-tab <?php echo $tab === 'countries' ? 'nav-tab-active' : ''; ?>"><?php esc_html_e('Countries', 'the-simplest-analytics'); ?></a>
        <a href="?page=the-simplest-analytics&tab=browsers&period=<?php echo esc_attr($period); ?>" class="nav-tab <?php echo $tab === 'browsers' ? 'nav-tab-active' : ''; ?>"><?php esc_html_e('Browsers', 'the-simplest-analytics'); ?></a>
        <a href="?page=the-simplest-analytics&tab=bots&period=<?php echo esc_attr($period); ?>" class="nav-tab <?php echo $tab === 'bots' ? 'nav-tab-active' : ''; ?>"><?php esc_html_e('Crawlers & AI', 'the-simplest-analytics'); ?></a>
        <a href="?page=the-simplest-analytics&tab=settings&period=<?php echo esc_attr($period); ?>" class="nav-tab <?php echo $tab === 'settings' ? 'nav-tab-active' : ''; ?>"><?php esc_html_e('Settings', 'the-simplest-analytics'); ?></a>
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
            case 'campaigns':
                include SA_PLUGIN_DIR . 'templates/partials/campaigns-table.php';
                break;
            case 'countries':
                include SA_PLUGIN_DIR . 'templates/partials/countries-table.php';
                break;
            case 'browsers':
                include SA_PLUGIN_DIR . 'templates/partials/browsers-table.php';
                break;
            case 'pages':
            default:
                include SA_PLUGIN_DIR . 'templates/partials/pages-table.php';
                break;
        }
        ?>
    </div>
</div>
