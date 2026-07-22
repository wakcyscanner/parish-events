# Parish Events

A WordPress plugin that turns a parish calendar feed into real WordPress content. It imports events from a Church Community Builder (CCB) XML feed into a custom post type on a schedule, giving every event a permanent URL, search-engine structured data, and full editorial control — replacing client-side calendar embeds.

**Requires:** WordPress 6.0+, PHP 7.4+ · **License:** [GPLv2 or later](https://www.gnu.org/licenses/gpl-2.0.html)

Installable zips are published under [Releases](../../releases).

## Features

### Import & sync

- Scheduled import (hourly to daily, or on demand) of the current month plus the next two, directly from the Pushpay ChMS v1 API (`public_calendar_listing`, HTTP Basic Auth) — or from any custom proxy endpoint returning the same XML. Credentials live in Settings or, preferably on production, in `PE_CHMS_SUBDOMAIN` / `PE_CHMS_USERNAME` / `PE_CHMS_PASSWORD` wp-config constants that keep the secret out of the database.
- One `parish_event` post per occurrence, keyed by CCB event ID + date — upstream edits update the same post and its URL never changes.
- Safe by design: a failed, empty, or malformed fetch never removes anything; unchanged feeds produce zero writes; past events are never touched.
- Import failure alerts by email (after three consecutive failed runs, with a recovery notice), plus a run log in the settings screen.
- Only text wrapped in `[public]...[/public]` in the feed description is published. Internal staff notes are never stored in WordPress, and leader contact details are visible to admins only.

### Event lifecycle

- Events deleted upstream move to a **Removed upstream** status — never deleted. Their pages show a neutral "no longer listed" notice for a grace period, then return `410 Gone`.
- Removed is not cancelled: marking an event cancelled is an explicit checkbox that switches the notice and emits schema.org `EventCancelled` markup. Absence from the feed is never assumed to mean cancelled.
- If a suppression rule with a link later covers an already-imported series, those posts' URLs permanently redirect (301) to the rule's destination, so saved bookmarks and indexed links keep working.
- Events that return to the feed are restored automatically.

### Editorial control

- **Manual override** flag per event: imports stop touching every field, and the date, times, location, group, and type become editable alongside the content (block editor supported).
- Always editable, override or not: featured image, featured flag, cancelled flag, a **featured video** slot (YouTube/Vimeo URL shown above the details), an **event flyer** image slot (shown below the details, linked to full size), a **registration/RSVP link** (shown as a button; the feed has no sign-up field), and an optional **cost** field (shown in the details and reflected in structured data).
- Admin list table with event date sorting, sync-status column, flag icons, and filters by event month, group, and upcoming/past.

### Suppression & locations

- Suppression rules (by CCB event ID, exact title, or title keyword) stop recurring events like daily Mass from generating hundreds of posts. Suppressed occurrences still appear on the calendar, linking to a single URL you choose per rule (for example, a Mass times page).
- Location directory: give each location a link to its own page, or an expandable "where is this?" description shown wherever the location appears. Global and per-event location substitutions clean up raw feed values.

### Display

- `[parish_events_calendar]` — server-rendered list and month views with a group filter and month navigation. No JavaScript framework, crawlable, cached.
- `[parish_events_featured]` — card grid of featured upcoming events.
- `[parish_events_upcoming]` — compact list of the next events (also available as a widget).
- `[parish_events_subscribe]` — calendar subscribe button.

### Calendar integration & SEO

- "Add to calendar (.ics)" and "Google Calendar" buttons on every upcoming event page.
- Subscribable webcal/ICS feed of all published events at `/?pe_ics=feed`.
- Schema.org Event JSON-LD, Open Graph, and Twitter meta tags on single event pages.
- `wp parish-events import` WP-CLI command (non-zero exit on failure) and `wp parish-events status` for recent runs.

## Installation

1. Download the latest `parish-events-x.y.z.zip` from [Releases](../../releases).
2. In wp-admin, go to Plugins → Add New Plugin → Upload Plugin, and upload the zip (uploading a newer zip over an existing install upgrades it in place).
3. Activate. The plugin flushes rewrite rules on activation; if event URLs 404, re-save Settings → Permalinks once.
4. Configure Parish Events → Settings: ChMS subdomain + API credentials (or a legacy custom feed URL), schedule, suppression rules, location directory, and alert emails.

After the initial install, updates arrive like any other plugin: the plugin checks this repo's Releases and offers new versions on the Plugins screen, where they can be installed in one click (or auto-updated, if enabled).

## Release channels

There are two update channels:

- **Stable** (the default) — sees full releases only. Production sites stay here.
- **Beta** — additionally sees pre-releases. Enable it per site with the "Receive beta updates" checkbox in Parish Events → Settings. Meant for staging sites; a stable release newer than the newest beta always wins.

The development flow behind that:

1. Feature work lands on the **`beta` branch** and ships as a pre-release: version like `1.1.0-beta.1`, tag `v1.1.0-beta.1`. Any tag containing a hyphen publishes as a GitHub *prerelease*, which the stable channel never sees.
2. Staging (on the beta channel) receives it as a normal plugin update and soaks it.
3. When it's proven, `beta` merges to **`main`** and ships as a stable release (`1.1.0`, tag `v1.1.0`) — production picks it up.

Both channels use the same release workflow: bump the plugin `Version` header, add a `CHANGELOG.md` section for the version, and push the matching tag.

## Launch checklist

1. The plugin claims the `/events/` URL base for event posts. If another plugin or page already serves `/events/`, resolve that first: deactivate the conflicting plugin, or keep a page at `/events/` and put the `[parish_events_calendar]` shortcode in it — the page URL and the post type coexist.
2. Review the settings, then click **Run import now** and spot-check a few event pages and the admin list.
3. Place `[parish_events_calendar]` on your calendar page and `[parish_events_featured]` wherever featured cards should appear.
4. Set up a real cron job: WordPress cron only fires on site visits, which is unreliable on low-traffic sites. Either have the host request `wp-cron.php` every 15 minutes and set `define( 'DISABLE_WP_CRON', true );` in `wp-config.php`, or point a system cron job at `wp parish-events import` directly.
5. Once satisfied, remove any old calendar embed the plugin replaces.
6. Keep calendar customizations in this plugin (or a site plugin), not in theme files, so theme updates can't overwrite them.

## Shortcodes

```text
[parish_events_calendar view="list" months="2" group="" show_filter="1" show_toggle="1"]
[parish_events_featured count="3" order="date" columns="3" show_excerpt="1"]
[parish_events_upcoming count="5" show_location="0"]
[parish_events_subscribe label="Subscribe to calendar"]
```

## FAQ

**Imports aren't running on schedule.**
See step 4 of the launch checklist — use a real cron job instead of visit-driven WordPress cron.

**An event I edited got flagged "missing upstream".**
The event has the manual-override flag and disappeared from the feed. Decide whether to unpublish it yourself or leave it; imports won't touch it either way.

**Why did a suppressed event's old page start redirecting?**
A suppression rule with a link URL now covers it. The redirect preserves saved bookmarks and search results by sending them to the rule's destination.

## Changelog

See [Releases](../../releases) for per-version notes.
