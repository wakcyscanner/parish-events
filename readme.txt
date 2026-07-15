=== Parish Events ===
Contributors: stpacc
Tags: events, calendar, import, structured data
Requires at least: 6.0
Tested up to: 6.8
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Imports parish calendar events from a CCB XML feed into a custom post type with scheduled sync, manual overrides, structured data, and display shortcodes.

== Description ==

Parish Events replaces a client-side calendar embed with real WordPress content:

* Scheduled import of the current month plus the next two months from a configurable XML feed endpoint.
* One `parish_event` post per event occurrence, keyed by CCB event ID + date, so upstream edits update the same post (and its URL never changes).
* Suppression rules (by CCB event ID, exact title, or title keyword) so recurring events like daily Mass never create posts. Suppressed occurrences still appear on the calendar, linking to a single URL you choose per rule (e.g. the Mass times page).
* Events deleted upstream move to a "Removed upstream" status; their pages stay reachable with a neutral "no longer listed" notice for a grace period, then return 410 Gone. Marking an event cancelled is an explicit editorial action (a checkbox on the event) that switches the notice and emits schema.org EventCancelled markup — absence from the feed is never assumed to mean cancelled.
* Manual override flag per event: imports stop touching every field. Featured images are always yours to set, override or not.
* Only text wrapped in [public]...[/public] in the CCB description is published; internal notes are never stored in WordPress.
* Schema.org Event JSON-LD on single event pages for search engines.
* `[parish_events_calendar]` — server-rendered list and month views with group filter.
* `[parish_events_featured]` — cards of featured upcoming events.

== Installation / Launch checklist ==

1. Identify the plugin powering the current /events/ page (Plugins screen on the production site). Check whether it registers an events post type or claims the /events/ URL; deactivate it before proceeding so there is no rewrite collision.
2. Upload and activate Parish Events (it flushes rewrite rules on activation; if URLs 404, re-save Settings → Permalinks once).
3. Review Parish Events → Settings: API URL, suppression rules (defaults cover Mass, Solemn Mass, Mass (anticipated) → mass-times page, and baptisms), location directory, and schedule.
4. Run "Run import now" and spot-check a few event pages and the admin list.
5. Replace the /events/ page content with the [parish_events_calendar] shortcode (the URL stays the same).
6. Add [parish_events_featured] wherever featured cards should appear.
7. Ask the host to set up a real cron job hitting wp-cron.php every 15 minutes and define DISABLE_WP_CRON (WordPress visit-driven cron is unreliable on low-traffic sites).
8. Once satisfied, remove the old embed.js block from the /calendar/ page.
9. Theme note (Celine): the theme self-updates from Diocesan — keep all calendar customization in this plugin, never in theme files.

== Shortcodes ==

`[parish_events_calendar view="list" months="2" group="" show_filter="1" show_toggle="1"]`

`[parish_events_featured count="3" order="date" columns="3" show_excerpt="1"]`

`[parish_events_upcoming count="5" show_location="0"]` — compact list of the next events (also available as the "Upcoming Parish Events" widget).

`[parish_events_subscribe label="Subscribe to calendar"]` — webcal subscribe button; the calendar shortcode shows one automatically.

== Calendar integration ==

* Every upcoming event page has "Add to calendar (.ics)" and "Google Calendar" buttons.
* The subscribable feed lives at /?pe_ics=feed (webcal://) and contains all published upcoming events. Suppressed series (Mass) are not included — they live on the Mass times page.
* `wp parish-events import` runs an import from the command line (non-zero exit on failure) — point the host's real cron job at this instead of wp-cron for the most reliable setup. `wp parish-events status` shows recent runs.

== Frequently Asked Questions ==

= Imports aren't running on schedule =

WordPress cron only fires when the site gets visits. Ask your host to add a real cron job that requests wp-cron.php every 15 minutes, and set `define( 'DISABLE_WP_CRON', true );` in wp-config.php.

= An event I edited got flagged "missing upstream" =

The event has the manual-override flag and disappeared from the parish calendar feed. Decide whether to unpublish it yourself or leave it; imports won't touch it either way.

== Changelog ==

= 1.0.0 =
* Initial release.
