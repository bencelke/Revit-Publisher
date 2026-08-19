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

## Vehicle detail

**RevIt Publisher → Vehicles** provides drill-down with cluster breakdown and needs-attention summary.

## Hub preparation (Phase 5)

Internal query methods (not public URLs yet):

- `get_published_by_type()`
- Vehicle articles, clusters, pillar articles

## REST

- `GET /revit-publisher/v1/vehicles`
- `GET /revit-publisher/v1/vehicles/detail?vehicle=...`
