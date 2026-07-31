# Changelog

Notes for each [published release](../../releases). The release workflow copies a version's section below into its GitHub Release, and refuses to publish a version that has no section here.

## 1.3.0-beta.8 — 2026-07-31

Beta release — beta-channel sites only.

### Changed

- **The celestial page-header treatment is removed entirely.** The header module is back to stock theme behavior at every viewport; sizing the banner to the header is better handled by choosing an appropriately shaped image.

## 1.3.0-beta.7 — 2026-07-31

Beta release — beta-channel sites only.

### Fixed

- **The celestial full-height header is now desktop-only** (viewports over 1024px). On smaller screens the theme's header module is untouched — the beta.6 change interfered with the mobile menu and the logged-in admin bar there, and phones already show the photo's full height. The side crop is also now explicitly centered, taking evenly from both edges.

## 1.3.0-beta.6 — 2026-07-31

Beta release — beta-channel sites only.

### Changed

- **Celestial page headers show the photo's full height.** The header follows the banner photo's aspect (16:9 by default, `--pp-header-ratio` to override) instead of a fixed 50vh strip, capped at 90vh and never shorter than the theme's default. When the box is narrower than the photo, the sides crop — the top and bottom never do.

## 1.3.0-beta.5 — 2026-07-31

Beta release — beta-channel sites only.

### Changed

- **Buttons on celestial pages start dark, hover light.** The theme's outline buttons read light-on-light against the gradient until hovered; they now start filled navy with white text and flip to white with navy text on hover.

## 1.3.0-beta.4 — 2026-07-31

Beta release — beta-channel sites only.

### Changed

- **Scripture quotes on celestial pages.** Quote and pullquote blocks get a light-on-dark treatment: centered serif italic in white between thin gold hairlines, with the citation in soft gold. Replaces the theme's pullquote look (heavy gray bars, dark gray text) that disappeared against the navy gradient, and renders the pullquote-inside-quote nesting the editor sometimes produces identically to a plain pullquote.

## 1.3.0-beta.3 — 2026-07-31

Beta release — beta-channel sites only. Display refinements from first staging review of the programs feature.

### Changed

- **Homepage injection matches the theme.** The injected display now follows the theme's content width (via the theme's own `--limit-width` variable) instead of spanning full width, and its heading picks up the theme's heading font, size, and color (`--font-heading`, `--fs-900`, `--clr-primary`). Themes without those variables keep the previous look.
- **Optional link below the homepage cards.** New settings fields add a centered link under the injected display — e.g. to the full programs page. Blank text reads "See all programs".
- **Group sections breathe.** Consecutive group sections (`[parish_programs groups="..."]`) get clear space before the next group's heading.
- **Celestial pages style their own content.** The celestial background stylesheet now flips the page's editor content to light-on-dark (headings, text, links), keeps dark text inside groups that have their own background, and renders details/summary blocks as frosted panels — no per-page inline CSS needed. Program cards are exempt: the light-on-dark rules stop at the card boundary, so card titles, schedule lines, and links keep their own palette.

## 1.3.0-beta.2 — 2026-07-31

Beta release — beta-channel sites only.

### Changed

- **Program card fields are writable over the REST API.** The schedule line, link URL, and link text meta now appear in the `meta` object of `wp/v2/parish_program` responses and accept authenticated writes from anyone who can edit the program (Application Passwords work). This lets an initial card set be created remotely on hosts without CLI access — the same sanitization as the editor meta box applies. The values were already public on every rendered card, so nothing new is exposed to readers.

## 1.3.0-beta.1 — 2026-07-31

Beta release — beta-channel sites only.

### New

- **Parish Programs.** Faith-formation programs and ministries — a set of events packaged together for promotion, like "Financial Peace University" or "That Man is You!" — are now a second post type in this plugin, folded in from the standalone `parish-programs` plugin so the site runs two custom plugins instead of three. Add them under **Programs**, group them with the **Program Groups** taxonomy, order them with the Order field, and display them with the `[parish_programs]` shortcode, the Parish Programs block, or automatic homepage injection for themes whose homepage template can't be edited. Each card links out to a ministry or registration page; programs have no single pages of their own. Settings live under **Programs → Settings**, separate from calendar settings.
- **`wp parish-programs import --from=<url-or-path>`** imports programs from the legacy Come to Me `programs.json`, sideloading card images into the media library. A one-time migration helper, distinct from `wp parish-events import`.

### Notes

- Programs are a separate post type from parish events on purpose. Events are feed-owned — the importer reconciles them against ChMS on every run and marks rows *Removed upstream* when they disappear. Programs are editor-owned with no upstream, so hand-authored rows in the synced post type would be reconciled away. Nothing in the event import path changes: every importer query is scoped to the event post type, so a second post type is invisible to sync and removal.
- Existing calendar behavior, settings, shortcodes, and CLI commands are unchanged. Sites that add no programs see only a new, empty **Programs** menu.

## 1.2.1 — 2026-07-31

Server-load release: every change reduces uncached PHP work per request, prompted by a brief origin overload behind Cloudflare.

### Changed

- **The ICS subscribe feed is now cached.** `/?pe_ics=feed` was rebuilt from the database on every poll and sent with no-cache headers — and subscribed calendar apps poll it indefinitely. The feed body is now held in a transient (invalidated by imports and content edits, and at each day boundary) and served with `Cache-Control: public, max-age=900, s-maxage=3600` plus an `ETag`, answering unchanged polls with `304 Not Modified`. See the new launch-checklist step for caching it at the CDN edge. Per-event `.ics` downloads are unchanged.
- **Calendar URL parameters are normalized before fragment caching.** Out-of-range `pe_month` values and unknown `pe_group` values (from stale or crafted URLs) previously each minted a fresh cache entry and paid a full uncached render; they now collapse onto the existing clamped/unfiltered fragment. An unknown group shows the full calendar instead of an empty one. The group list used by the filter dropdown is also cached.
- **Imports issue far fewer queries.** The importer now resolves all existing events with one query up front (instead of one lookup per feed row), warms the meta cache for the posts it will touch in one pass, and skips redundant "last seen" bookkeeping writes on back-to-back runs. No behavior change to what gets created, updated, removed, or restored.

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
