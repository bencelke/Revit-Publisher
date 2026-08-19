# Vehicle Command Center

Multi-vehicle content health comparison.

## Metrics per vehicle

- RevIt SEO Health average
- Plan coverage
- Published / draft counts
- Missing articles
- Orphans
- Unresolved links
- High overlap issues
- Review-due count

### Search Console (v0.7.0)

When Google Search Console is connected and synced:

- Clicks, impressions, CTR, average position (7d / 28d)
- Trend vs previous comparable period
- Articles with impressions vs total
- Performance opportunity count

Columns appear on **RevIt Publisher → Vehicles** and aggregate via `RevIt_Publisher_GSC_Insights_Service`.

## Vehicle detail

**RevIt Publisher → Vehicles** provides drill-down with cluster breakdown, needs-attention summary, and Google Search metrics.

Vehicle hub editor panels show 28-day Search Console summary when data exists.

## Hub preparation (Phase 5)

Internal query methods (not public URLs yet):

- `get_published_by_type()`
- Vehicle articles, clusters, pillar articles

## REST

- `GET /revit-publisher/v1/vehicles`
- `GET /revit-publisher/v1/vehicles/detail?vehicle=...`
- `GET /revit-publisher/v1/search-console/vehicles?window=28d`
