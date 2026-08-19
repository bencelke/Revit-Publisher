# Redirect Manager

RevIt-managed **301 redirects** stored in private CPT `revit_redirect`.

## Safety

- Loop detection
- Source ≠ destination
- Path normalization
- External targets blocked unless enabled in Settings

RevIt redirects are **independent** of Yoast/Rank Math redirect systems.

## Runtime

Frontend requests lookup by normalized path with transient caching. Admin, REST, login, and wp-cron paths are excluded.

## REST

- `GET/POST /revit-publisher/v1/redirects`
- `PUT/DELETE /revit-publisher/v1/redirects/{id}` (soft disable via status)
