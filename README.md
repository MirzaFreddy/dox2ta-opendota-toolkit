# Dox2TA OpenDota Toolkit

A lightweight WordPress plugin that displays Dota 2 weekly records and hero statistics using the free OpenDota API. It provides clean, RTL-friendly, sortable tables and simple shortcodes so gaming and Dota community sites can show live data without any custom API code.

## Features

- **Record leaderboards** for multiple metrics (kills, GPM, XPM, duration, etc.)
- **Hero stats** for pro, public, and turbo with sortable, visual tables
- **One shortcode per view**, plus an "all tables" shortcode
- **Caching and daily warm-up** to reduce API calls and speed up pages
- **Zero configuration**; just paste shortcodes into posts or pages

## Requirements

- WordPress 5.8+
- PHP 7.4+

## Installation

1. Upload the plugin folder to `wp-content/plugins/`.
2. Activate “Dox2TA OpenDota Toolkit” from WordPress > Plugins.
3. Open the admin menu item “راهنمای OpenDota” for a Persian help page and examples.

## Shortcodes

### 1) Records

Render one records table for a chosen metric.

```
[opendota_records metric="duration" period="week" limit="10"]
```

- **metric** (required): `duration`, `kills`, `deaths`, `assists`, `gpm`, `xpm`, `last_hits`, `denies`, `hero_damage`, `tower_damage`, `hero_healing`
- **period**: `week` (last 7 days), `all` (all-time, where supported)
- **limit**: number of rows (default: 10)

Examples:

```
[opendota_records metric="kills" limit="5"]
[opendota_records metric="hero_damage" period="week" limit="12"]
```

### 2) All records tables

Render all metrics sequentially in one page.

```
[opendota_all_tables period="week" limit="10"]
```

### 3) Heroes — Pro

Pro level presence (pick+ban), pick, ban, and win rates.

```
[opendota_heroes_pro limit="10" sort_by="pb"]
```

- **limit**: number of heroes (default: 100)
- **sort_by**: `pb` (presence), `pp` (pick), `ban` (ban), `pw` (win)

### 4) Heroes — Public (all brackets)

Overall pick/win % plus bracket group breakdowns.

```
[opendota_heroes_public]
```

### 5) Heroes — Turbo

Turbo pick and win rates.

```
[opendota_heroes_turbo limit="5" sort_by="tw"]
```

- **limit**: number of heroes (default: 100)
- **sort_by**: `tp` (pick), `tw` (win)

## Caching and performance

- **Record tables**: cached in WordPress transients for up to 24 hours when data is found; fallback cache is 5 minutes when the API yields empty results.
- **Hero stats (heroStats)**: cached for 24 hours.
- **Daily warm-up**: a WP-Cron task `odr_refresh_daily_cache` runs daily to prefetch common views and keep pages fast.
- **Manual refresh tips**: change a shortcode parameter like `limit` temporarily to generate a new cache key.

## Styling

The plugin enqueues a minimal CSS file and a small JS for column sorting. Tables are RTL-friendly and display visual bars for percentages.

## Data source and API

This plugin uses the free, public **OpenDota API**. We do not redistribute or repackage their data; we only request and render it in your WordPress site. See OpenDota at:

- https://www.opendota.com/
- https://docs.opendota.com/

Please respect OpenDota’s rate limits and fair-use guidelines.

## License

Released under the **MIT License**. See `LICENSE` for details.

Attribution: This plugin simply helps WordPress sites display Dota 2 data via the free OpenDota API. OpenDota is a separate, third‑party service and all data is provided by them under their own terms.
