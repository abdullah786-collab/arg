=== RevGuard Pro ===
Author: Abdul Kadir
Requires at least: 6.0
Tested up to: 7.1
Requires PHP: 8.2
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Fast, cache-aware ad management for WordPress publishers: ads, groups, scheduling, placements, weighted rotation and lightweight analytics.

== Description ==

RevGuard Pro lets you manage ads from one clean admin area and decides everything on the server, adding no JavaScript of its own to your visitors' pages.

* **Ads** – ad code (filtered HTML), active/inactive, optional start and end time in your site time zone.
* **Groups** – rotate several ads in one spot, weighted random or in a fixed order.
* **Placements** – insert an ad or group before or after the content, after a paragraph or heading, or at a percentage of the content. Or place ads yourself with the `[revguard_pro]` shortcode or the RevGuard Pro Ad block.
* **Analytics** – server-side impressions per ad per day for 7, 30 or 90 days, with a configurable retention period. No visitor data is stored and clicks are not tracked.
* **Safe by default** – ad code is filtered with an allowlist when saved and when shown; scripts, frames and event handlers are removed. An optional unfiltered mode can be switched on only by users who hold WordPress's `unfiltered_html` capability, and ad code saved by anyone else is always filtered.
* **Cache awareness** – asks page caches not to store pages whose ads depend on request details (device, referrer, query parameters). This is best-effort; see the documentation.
* **Clean uninstall** – your data is kept unless you opt in to deleting it.
* Native WordPress admin controls (lists, forms, notices) and a responsive layout. Translation-ready.

Targeting rules (device, post type, taxonomy, login state, role, URL, referrer, query parameter) are evaluated by the plugin, but version 1.0 has no screen to create or edit them.

Full documentation is in the `documentation` folder of the plugin package.

== Installation ==

1. Upload the plugin ZIP via **Plugins → Add New → Upload Plugin**, or upload the `revguard-pro` folder to `/wp-content/plugins/`.
2. Activate the plugin. A **RevGuard Pro** menu appears.
3. Add an ad, then add a placement or use the shortcode or block.

Requires PHP 8.2 or newer and WordPress 6.0 or newer.

== Frequently Asked Questions ==

= Does it need a licence key or an external service? =
No. RevGuard Pro's own code makes no external requests, sets no cookies and stores no visitor personal data. Ad code you add is separate: in the default filtered mode scripts and frames are removed; in unfiltered mode (administrators only) ad code runs as written and may load third-party resources. See the documentation, section "Privacy and external requests".

= Are cached pages counted in Analytics? =
No. Pages served from a full-page cache do not run the plugin, so Analytics shows server-side impressions, not traffic totals.

= Does it purge my page cache? =
No. It only asks caches not to store pages whose ads depend on request details. Clear your cache after changing ads, placements or schedules.

= What happens when I delete the plugin? =
Your data is kept unless you turned on "Delete all data when the plugin is deleted" in Settings first.

== Changelog ==

= 1.0.0 =
* Initial release.
