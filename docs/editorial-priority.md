# Editorial Priority

Deterministic explainable prioritization — no AI, no ranking guarantees.

## Score range

Internal score 0–100 for sorting. UI shows priority level.

## Factors

| Factor | Weight idea |
|--------|-------------|
| Action type base | indexing 85, overlap 65, cluster 60, refresh 55, links 45, review 40, create 40+plan priority |
| Search Console impressions | up to +20 (impressions / 1000) |
| High SEO Health + refresh | +10 (structurally healthy, alignment opportunity) |
| Content plan priority | up to +20 for create tasks |
| Urgent indexing | floor 90 |

## Deduplication

Multiple signals for one article merge into one work item (e.g. page-2 + unexpected query → single `refresh_content` with combined reasons).

Technical indexing issues remain separate from refresh tasks.

## Explainability

Every item includes:

- `explanation` — summary
- `reasons[]` — bullet list of why flagged
- `next_step` — recommended operator action

The score does **not** predict rankings or revenue.
