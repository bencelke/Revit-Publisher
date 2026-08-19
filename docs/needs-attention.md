# Needs Attention Queue

Unified operator queue for SEO maintenance issues.

## Issue types

`orphan`, `missing_pillar`, `unresolved_link`, `newly_resolvable_link`, `missing_meta`, `topic_overlap`, `review_due`, `missing_content`, `cluster_gap`, `broken_relationship`

### Google Search Console (v0.7.0)

`gsc_index_issue`, `gsc_canonical_mismatch`, `gsc_page2_opportunity`, `gsc_low_ctr_opportunity`, `gsc_growing_page`, `gsc_declining_page`, `gsc_zero_visibility`, `gsc_unexpected_query`

Performance opportunities are explainable recommendations, not guarantees. Index/canonical issues use conservative severity.

Refresh-related GSC signals may also appear with recommended action `search_refresh_opportunity`.

## Statuses

- `open` — active issue
- `acknowledged` — operator aware
- `resolved` — no longer detected
- `ignored` — suppressed by operator

## Severity

| Level | Examples |
|-------|----------|
| Critical | Broken pillar target, duplicate key integrity |
| High | High-risk overlap, missing pillar, GSC index/canonical issues |
| Medium | Unresolved link, missing meta, review due, declining page, zero visibility |
| Low | Minor cluster coverage gap, page-2/CTR/growing opportunities, unexpected queries |

## Reconciliation

When an issue disappears between audits, status becomes `resolved` (not deleted). Tracks `first_detected`, `last_detected`, `resolved_at`.

See [search-opportunities.md](./search-opportunities.md) and [url-inspection.md](./url-inspection.md).
