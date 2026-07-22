# Changelog

Notes for each [published release](../../releases). The release workflow copies a version's section below into its GitHub Release, and refuses to publish a version that has no section here.

## 1.2.0 — 2026-07-22

### New

- **Direct ChMS API access.** Imports can now call the Pushpay ChMS v1 API (`public_calendar_listing`) directly with HTTP Basic Auth — no Cloudflare worker or other proxy needed. Enter the church subdomain and API credentials in Settings, or define `PE_CHMS_SUBDOMAIN` / `PE_CHMS_USERNAME` / `PE_CHMS_PASSWORD` in wp-config.php to keep the secret out of the database (constants win over settings). The custom feed URL remains as a fallback whenever the API fields aren't all filled in, so existing installs keep importing unchanged until credentials are entered — after which the URL can be cleared.
- ChMS API errors (bad credentials, unknown service) are reported by the API as a successful response with an error block; the importer now surfaces the actual error message in the run log and failure alerts instead of logging an empty feed. With neither credentials nor a custom URL configured, imports fail with a clear "no feed source configured" message.

## 1.2.0-beta.2 — 2026-07-22

Beta release — beta-channel sites only.

### Fixed

- The custom feed URL (legacy proxy) can now be cleared once direct ChMS API access is configured — saving an empty field previously failed with "API URL must use https". If neither the API credentials nor a custom URL are configured, imports fail with a clear "no feed source configured" message in the run log instead of a malformed request.

## 1.2.0-beta.1 — 2026-07-22

Beta release — beta-channel sites only.

### New

- **Direct ChMS API access.** Imports can now call the Pushpay ChMS v1 API (`public_calendar_listing`) directly with HTTP Basic Auth — no Cloudflare worker or other proxy needed. Enter the church subdomain and API credentials in Settings, or define `PE_CHMS_SUBDOMAIN` / `PE_CHMS_USERNAME` / `PE_CHMS_PASSWORD` in wp-config.php to keep the secret out of the database (constants win over settings). The custom feed URL remains as a fallback whenever the API fields aren't all filled in, so existing installs keep importing unchanged until credentials are entered.
- ChMS API errors (bad credentials, unknown service) are reported by the API as a successful response with an error block; the importer now surfaces the actual error message in the run log and failure alerts instead of logging an empty feed.

## 1.1.0 — 2026-07-21

### New

- **Featured events slider.** `[parish_events_featured]` is now a horizontal slider instead of a static three-card grid, showing up to 24 events (default 12). Cards are redesigned: portrait format with the image filling the card, the group name in a bubble at the top, and the title, date, and location overlaid on a gradient at the bottom. Arrow buttons, dot navigation, and swipe/scroll all work; without JavaScript it degrades to a native scroll strip. The `columns` attribute now means "cards visible at once" (narrow screens automatically show fewer), and card height is capped so wide cards settle toward square on large monitors.
- **Group-specific calendars.** Setting the group in the calendar shortcode — `[parish_events_calendar group="Youth Ministry"]` — locks the calendar to that group for ministry pages: the group dropdown disappears, the URL filter parameter is ignored, and list/month views and linked occurrences (like Mass) respect the lock.
- **The plugin now matches the site's colors.** Buttons, badges, calendar accents, month-grid headers, and slider controls draw from one accent color that automatically matches the theme's link color. An "Accent color" field in Settings overrides the automatic choice with a specific hex value; tints and hover shades derive from whichever color wins.

### Changed

- The featured-card excerpt is gone — the overlay design has no room for it, and the card links straight to the full event page. (`show_excerpt` is still accepted so existing shortcodes don't break.)

## 1.1.0-beta.2 — 2026-07-21

Beta release — beta-channel sites only.

### New

- **The plugin now matches the site's colors.** Buttons, badges, calendar accents, month-grid headers, and slider controls draw from one accent color that automatically matches the theme's link color — no more off-brand green on a Diocesan-themed site. An "Accent color" field in Settings overrides the automatic choice with a specific hex value; tints and hover shades derive from whichever color wins.

### Fixed

- Featured slider cards no longer tower on large monitors: card height is capped, so wide cards settle toward a square shape instead of scaling the 3:4 ratio up indefinitely.

## 1.1.0-beta.1 — 2026-07-21

Beta release — beta-channel sites only.

### New

- **Featured events slider.** `[parish_events_featured]` is now a horizontal slider instead of a static three-card grid, showing up to 24 events (default 12). Cards are redesigned: portrait format with the image filling the card, the group name in a bubble at the top, and the title, date, and location overlaid on a gradient at the bottom. Arrow buttons, dot navigation, and swipe/scroll all work; without JavaScript it degrades to a native scroll strip. The `columns` attribute now means "cards visible at once" (narrow screens automatically show fewer).
- **Group-specific calendars.** Setting the group in the calendar shortcode — `[parish_events_calendar group="Youth Ministry"]` — now locks the calendar to that group for ministry pages: the group dropdown disappears, the URL filter parameter is ignored, and list/month views and linked occurrences (like Mass) respect the lock.

### Changed

- The featured-card excerpt is gone — the overlay design has no room for it, and the card links straight to the full event page. (`show_excerpt` is still accepted so existing shortcodes don't break.)

## 1.0.13 — 2026-07-17

### New

- **Release channels.** A "Receive beta updates" checkbox in Settings opts a site into pre-release versions — meant for staging, so new features can soak there before production sees them. Unchecked (the default), a site only ever receives stable releases; a stable release newer than the newest beta always wins. Site-level override available via the `pe_update_channel` filter.

## 1.0.12 — 2026-07-17

### New

- **Registration / RSVP link.** The parish calendar feed has no field for sign-ups, so each event now has its own slot: paste an Eventbrite, SignUpGenius, form, or any other link, and the event page shows a prominent "Register / RSVP" button ahead of the add-to-calendar buttons (upcoming events only — it disappears once the event has passed). Like the featured image, imports never touch it, override or not.
- **Optional cost field.** Free-text ("$10", "$25 per family", "Free-will offering") shown as a Cost row in the event details. Search engines get honest pricing too: a dollar amount becomes the structured-data price, "free" wording marks the event free, and events with no cost stay marked free as before. The registration link doubles as the structured-data offer URL.
- Events with a registration link show a ticket icon in the admin list's Flags column.

## 1.0.11 — 2026-07-16

### Fixed

- **The "Enable auto-updates" link now appears** on the Plugins screen. WordPress only offers the toggle for plugins whose update state it knows; when the site was already on the latest version, the update check reported nothing, so the row showed no auto-update control. The check now always reports the latest release and lets WordPress decide whether it's an update.

## 1.0.10 — 2026-07-16

### New

- **Automatic updates from GitHub.** WordPress now discovers new releases of this plugin on the normal Plugins screen, using core's native `Update URI` mechanism pointed at this repository — no external update service, no more manual zip uploads. Release notes appear in the update's "View details" window, and WordPress's per-plugin auto-update toggle works too. (This is the last version that needs to be installed by hand.)

### Changed

- The README is rewritten in Markdown for GitHub display and made host- and theme-agnostic — the plugin runs on any WordPress 6.0+ site.

## 1.0.9 — 2026-07-16

### New

- **Page-cache purging.** When calendar content actually changes — an import that created, updated, or removed events; an event edited in admin; plugin settings saved — the plugin now asks the active caching plugin to drop its page cache, so visitors never see a stale calendar. Supported out of the box: WP Rocket and derivatives such as AccelerateWP, LiteSpeed Cache, W3 Total Cache, WP Super Cache, WP Fastest Cache, SiteGround Speed Optimizer, Cache Enabler, Autoptimize, Hummingbird, Breeze, and WP Engine, with `parish_events_purge_page_cache` / `parish_events_purge_asset_cache` hooks for anything else.
- **Upgrade-aware asset busting.** A plugin version change (zip upload, deploy, or update — however the files arrive) additionally clears minified and aggregated CSS/JS caches, so cached pages stop referencing stale stylesheet bundles.

### Changed

- Imports that change nothing no longer invalidate anything — the routine twice-daily runs leave page caches warm instead of purging the whole site.

## 1.0.8 — 2026-07-16

First packaged release. Highlights of what's new since the plugin was first deployed to staging:

### New

- **Event flyer slot.** Each event can carry a flyer or bulletin-snippet image (separate from the featured image), picked from the media library and shown below the event details, linked to full size. Like the featured image, imports never touch it and it's editable regardless of override.
- **Admin list filters.** Filter the events list by event month, group, and upcoming/past, with WordPress's publish-date filter (meaningless for imported events) removed.
- **Suppression-rule redirects.** When a linked suppression rule later covers an already-imported series, those posts' URLs permanently redirect to the rule's destination instead of showing a removal notice.
- **Removed is not cancelled.** Events missing from the feed show a neutral "no longer listed" notice; the red cancelled banner and `EventCancelled` markup now require an explicit checkbox.

### Fixed

- The flyer picker button did nothing: the media-library scripts load after the meta box renders, so the availability check now happens at click time.
- Removed-upstream events no longer leak into the admin "All" events list.
- Settings could not be saved on a fresh install (WordPress runs the sanitizer twice when an option is first created).
- List/Month calendar toggles could link to raw REST API responses when rendered inside the block editor.
