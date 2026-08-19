# SEO Health Score

Phase 3 introduces **RevIt SEO Health** — a transparent 0–100 internal content-quality metric.

> RevIt SEO Health is an internal site-quality metric and is not a Google ranking score.

## Categories

| Category | Max Points | Signals |
|----------|------------|---------|
| Metadata | 20 | SEO title, meta description, primary topic, length ranges |
| Structure | 15 | H2 headings, paragraph count, content length |
| Internal Linking | 25 | Resolved outbound, inbound, unresolved count |
| Cluster Integration | 20 | Cluster, pillar, vehicle context |
| Topic Uniqueness | 10 | Overlap risk penalties |
| Source Support | 10 | Source reference count |

Each category exposes exactly why points were earned or lost.

## Per-Article Analysis

REST: `GET /posts/{id}/seo-analysis`

Returns total score, category breakdown, signals, and deterministic recommendations.

## Recommendations

Examples:

- Add SEO title / meta description
- Resolve planned internal links
- Address orphan status
- Review topic overlap with specific article
- Link pillar to supporting articles

No AI-generated prose. No keyword density scoring.

## Review Status

Derived `_revit_review_status` values:

- `healthy`
- `review_due` (based on `review_after_months` setting, default 12)
- `needs_attention`
- `update_available`

Review due is a reminder only — not a factual outdated claim.
