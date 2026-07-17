# Changelog

Notes for each [published release](../../releases). The release workflow copies a version's section below into its GitHub Release, and refuses to publish a version that has no section here.

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
