# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Plugin Overview

The Simplest Analytics is a privacy-first, lightweight analytics plugin for WordPress/ClassicPress. It uses a hybrid tracking approach (server-side + JavaScript fallback) to work correctly with page caching, stores no cookies, and is GDPR compliant.

**Key Privacy Features:**
- IP addresses are truncated before storage (last octet zeroed for IPv4)
- Unique visitors tracked via daily-rotating salted hashes (stored in transients, not DB)
- No cookies or persistent identifiers
- Optional Do Not Track (DNT) header respect

## Architecture

### Hybrid Tracking Flow

1. **Server-side tracking** (`SA_Tracker::handle_server_side_tracking`) runs on `template_redirect` for uncached requests
2. Sets `window.sa_tracked = true` in footer JS
3. **JavaScript fallback** (`tracker.js`) only fires if `sa_tracked` is false (page served from cache)
4. JS pings REST endpoint `sa/v1/track` which records the hit

### Database Schema (Normalized Star Schema)

Four tables with lookup normalization to minimize storage:

| Table | Purpose |
|-------|---------|
| `wp_sa_pageviews` | Fact table: timestamps, foreign keys, device_type, is_unique |
| `wp_sa_paths` | Dimension: path_hash (MD5) → path_value |
| `wp_sa_referrers` | Dimension: ref_hash → ref_value (domain only) |
| `wp_sa_agents` | Dimension: agent_hash → agent_value (browser/bot name) |

**Device Types:** 1=Desktop, 2=Mobile, 3=Tablet, 4=Search Bot, 5=AI/Marketing Bot

### Class Responsibilities

| Class | Role |
|-------|------|
| `SA_Tracker` | Request tracking, uniqueness detection, data collection |
| `SA_Database` | Schema creation, normalized inserts, query methods, cleanup |
| `SA_REST_API` | `sa/v1/track` endpoint for JS fallback (with rate limiting) |
| `SA_Geo` | Country code lookup via CDN headers or local MMDB file |
| `SA_Admin` | Settings page, dashboard widget, admin assets |
| `SA_Activator` | Activation hooks, directory creation |

### Geo-IP Lookup

1. First checks CDN headers (`HTTP_CF_IPCOUNTRY` for Cloudflare)
2. Falls back to local DB-IP MMDB file at `wp-content/uploads/simplest-analytics/dbip-country-lite.mmdb`
3. Requires MaxMind DB Reader library for local lookups (optional dependency)

## Key Options (wp_options)

| Option | Default | Purpose |
|--------|---------|---------|
| `sa_tracking_enabled` | true | Master tracking toggle |
| `sa_retention_days` | 365 | Days before data cleanup |
| `sa_daily_salt` | random | Daily rotating salt for unique visitor hashing |
| `sa_salt_date` | today | Tracks when salt was last rotated |
| `sa_respect_dnt` | false | Honor Do Not Track headers |
| `sa_strip_query_params` | true | Remove query strings from paths |
| `sa_excluded_roles` | ['administrator'] | User roles to exclude from tracking |
| `sa_enable_geo` | true | Enable country code lookups |

## Cron Jobs

- `sa_daily_cleanup` - Runs daily to delete records older than retention period

## File Structure

```
simplest-analytics/
├── simplest-analytics.php     # Main plugin file
├── uninstall.php              # Cleanup on deletion
├── readme.txt                 # WordPress.org readme
├── CLAUDE.md                  # This file
├── includes/
│   ├── class-sa-activator.php
│   ├── class-sa-admin.php
│   ├── class-sa-database.php
│   ├── class-sa-geo.php
│   ├── class-sa-rest-api.php
│   └── class-sa-tracker.php
├── templates/
│   ├── stats-page.php
│   ├── dashboard-widget.php
│   └── partials/
│       ├── pages-table.php
│       ├── referrers-table.php
│       ├── bots-table.php
│       └── settings-view.php
└── assets/
    ├── css/admin.css
    └── js/
        ├── tracker.js
        └── admin.js
```

## Development Notes

- Plugin uses manual class loading (no Composer/autoloader)
- All SQL queries use `$wpdb->prepare()` for security
- REST endpoint uses rate limiting (10 req/min per IP) instead of nonce verification (sendBeacon limitation)
- Unique visitor tracking uses hash prefixes (16 chars) with 50k limit to prevent memory issues
- Bot detection patterns in `SA_Database::parse_user_agent()` - add new bot patterns there
