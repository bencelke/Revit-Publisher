# Needs Attention Queue

Unified operator queue for SEO maintenance issues.

## Issue types

`orphan`, `missing_pillar`, `unresolved_link`, `newly_resolvable_link`, `missing_meta`, `topic_overlap`, `review_due`, `missing_content`, `cluster_gap`, `broken_relationship`

## Statuses

- `open` — active issue
- `acknowledged` — operator aware
- `resolved` — no longer detected
- `ignored` — suppressed by operator

## Severity

| Level | Examples |
|-------|----------|
| Critical | Broken pillar target, duplicate key integrity |
| High | High-risk overlap, missing pillar |
| Medium | Unresolved link, missing meta, review due |
| Low | Minor cluster coverage gap |

## Reconciliation

When an issue disappears between audits, status becomes `resolved` (not deleted). Tracks `first_detected`, `last_detected`, `resolved_at`.
