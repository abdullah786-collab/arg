# Changelog

## 1.0.0

Initial release.

- Ads: filtered HTML ad code, active/inactive, optional schedule.
- Groups: weighted random or ordered rotation.
- Placements: before/after content, after a paragraph or heading, at a percentage, or manual.
- Shortcode `[revguard_pro]` and the RevGuard Pro Ad block.
- Analytics: server-side impressions per ad per day, configurable retention.
- Settings: automatic insertion, unfiltered ad code (restricted to users with `unfiltered_html`), analytics, retention, data removal on uninstall.
- Cache awareness: asks page caches not to store pages whose ads depend on request details; `revguard_pro_cache_decision` action.
- Translation-ready (POT included).
