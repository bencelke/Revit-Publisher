# Performance

## Profiler

`RevIt_Publisher_Performance_Profiler` records admin operation durations:

- editorial reconcile
- GSC sync normalization
- audit batches
- fixture generation

View recent entries on System Health after running self-test.

## 5k fixture

`RevIt_Publisher_Fixture_Generator::generate_metadata_fixture()` creates managed article metadata stubs for benchmarking (no long bodies).

Run via WP-CLI eval in Docker acceptance — document local measurements; do not hard-fail on machine variance.

## Caching

- GSC metrics: custom DB tables (not transients)
- Hub cache: namespaced transients via `RevIt_Publisher_Hub_Cache`
- Sync/audit locks: short-lived transients
- Event log: WP option ring buffer (200 entries max)

Invalidate hub cache on article/hub changes via existing hooks.

## Targets

- Dashboard summaries: sub-second with local GSC data
- Queue reconciliation: seconds for hundreds of articles
- Audit: batched to avoid timeout

No Search Console API calls on frontend.
