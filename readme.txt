=== The Simplest Analytics ===
Contributors: jenssage
Tags: analytics, privacy, statistics, GDPR, pageviews
Requires at least: 6.0
Tested up to: 6.7
Requires PHP: 7.4
Stable tag: 1.3.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Privacy-first, lightweight analytics. No cookies, GDPR compliant, cache-compatible, with bot and AI crawler tracking.

== Description ==

The Simplest Analytics provides essential website statistics without compromising visitor privacy or site performance.

= Key Features =

* **No cookies** - No consent banners needed
* **GDPR compliant** - IP addresses are anonymized before storage
* **Cache compatible** - Works with page caching through hybrid tracking
* **Bot tracking** - Monitors search engine crawlers and AI bots (GPTBot, ClaudeBot, etc.)
* **Lightweight** - Under 1KB JavaScript, minimal database footprint
* **Self-hosted** - Your data stays on your server

= Privacy by Design =

* IP addresses are truncated (last octet zeroed) before any storage
* Unique visitors identified via daily-rotating salted hashes
* No persistent identifiers or fingerprinting
* Optional Do Not Track (DNT) header support
* Configurable data retention with automatic cleanup

= What Gets Tracked =

* Page views and unique visitors
* Referrer domains (not full URLs)
* Device types (desktop, mobile, tablet)
* Country (via CDN headers or optional geo-IP database)
* Search engine crawlers (Googlebot, Bingbot, etc.)
* AI crawlers (GPTBot, ClaudeBot, PerplexityBot, etc.)

= How It Works =

The Simplest Analytics uses a hybrid tracking approach:

1. **Server-side tracking** runs on each uncached page request
2. **JavaScript fallback** fires only when pages are served from cache
3. Both methods use the same privacy-preserving data collection

This ensures accurate tracking regardless of your caching setup.

== Installation ==

1. Upload the `simplest-analytics` folder to `/wp-content/plugins/`
2. Activate the plugin through the 'Plugins' menu in WordPress
3. View statistics at Settings → The Simplest Analytics

The plugin automatically creates the necessary database tables on activation.

= Optional: Geo-IP Database =

For country detection without relying on CDN headers:

1. Download the free DB-IP Country Lite database from [db-ip.com](https://db-ip.com/db/download/ip-to-country-lite)
2. Extract and rename to `dbip-country-lite.mmdb`
3. Upload to `wp-content/uploads/simplest-analytics/`

If you use Cloudflare or a similar CDN, country detection works automatically via headers.

== Frequently Asked Questions ==

= Does this require cookie consent? =

No. The Simplest Analytics does not use cookies or any persistent client-side storage.

= Is this GDPR compliant? =

Yes. IP addresses are anonymized before storage, no personal data is retained, and data can be automatically deleted after a configurable retention period.

= Will it work with my caching plugin? =

Yes. The hybrid tracking approach ensures pageviews are counted even when pages are served from cache.

= What bots are tracked? =

Search engine bots (Googlebot, Bingbot, DuckDuckBot, Baiduspider, Yandex) and AI/marketing bots (GPTBot, ClaudeBot, PerplexityBot, SEMrushBot, AhrefsBot) are identified and tracked separately from human visitors.

= Can I exclude certain users from tracking? =

Yes. By default, administrators are excluded. You can configure which user roles to exclude in Settings.

= Does this slow down my site? =

No. The tracking JavaScript is under 1KB and loads asynchronously. Server-side tracking adds minimal overhead.

== Screenshots ==

1. Analytics overview with key metrics
2. Top pages report
3. Referrer sources
4. Bot and crawler activity
5. Privacy-focused settings

== Changelog ==

= 1.3.1 =
* Fixed tracking on cached pages - pages served from PHP-level cache now correctly tracked via JS fallback
* Added deduplication to prevent duplicate pageviews within 30-second window
* Changed cache detection from boolean flag to timestamp-based signature
* Fixed duplicate array keys in REST API data handling

= 1.3.0 =
* Added time-on-page tracking with session duration
* Added pageview identifiers for accurate session tracking
* Added transient caching for improved admin performance
* Display average duration per page in statistics
* Enhanced JavaScript tracker with heartbeat and visibility detection
* Optimized database queries with indexing

= 1.2.0 =
* Added Countries tab with flag emojis and geographic statistics
* Added Browsers tab with browser name detection and visual charts
* Added Devices breakdown with percentage visualization
* Added Campaign tracking with UTM parameter support
* Improved user agent parsing for accurate browser identification
* Added horizontal bar charts for visual statistics

= 1.1.0 =
* Added rate limiting to prevent API abuse
* Improved memory efficiency for high-traffic sites
* Fixed sendBeacon compatibility for cached page tracking
* Added referrers report tab
* Added uninstall cleanup for complete data removal
* Fixed NULL handling in database inserts
* Improved XSS prevention in admin templates

= 1.0.0 =
* Initial release

== Upgrade Notice ==

= 1.3.1 =
Important bugfix for sites using page caching. Ensures all visitors are tracked without duplicates.

= 1.3.0 =
Feature release adding time-on-page tracking and performance improvements through caching.

= 1.2.0 =
Feature release adding geographic, browser, device, and campaign statistics with visual charts.

= 1.1.0 =
Recommended update with security improvements and bug fixes for cached page tracking.
