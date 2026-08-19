# Search Opportunities

RevIt Publisher generates explainable performance opportunities from Search Console data.

## Types

| Type | Signal |
|------|--------|
| `gsc_page2_opportunity` | Position ~11–20 with meaningful impressions |
| `gsc_low_ctr_opportunity` | High impressions, low CTR in top positions |
| `gsc_declining_page` | Impressions/clicks declining vs previous period |
| `gsc_zero_visibility` | Published indexable article with zero impressions after grace period |
| `gsc_unexpected_query` | Query impressions not matching planned topics |
| `gsc_index_issue` | URL inspection: not indexed |
| `gsc_canonical_mismatch` | Google canonical differs from user canonical |

Each opportunity includes explicit **reasons** — no black-box scoring.

## RevIt SEO Health integration

High internal SEO health + page-2 opportunity suggests reviewing query alignment before rewriting structure.

## Needs Attention

Opportunities feed the issue queue with conservative severity (usually low/medium).

Refresh opportunities appear as `search_refresh_opportunity` context.
