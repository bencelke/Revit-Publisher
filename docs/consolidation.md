# Consolidation Workflow

Operator-approved cannibalization resolution **without automatic body merge**.

## Flow

1. Select source and destination articles
2. Review inbound links, cluster/plan references
3. Confirm consolidation
4. Create 301 redirect source → destination
5. Retarget RevIt-managed internal link metadata
6. Set source to draft/private (operator choice)

Article body content is **never merged automatically**.

## Topic overlap decisions

From SEO Health → Topic Overlap:

- Keep Both
- Mark Different Intent
- Merge Into A / B (opens consolidation preview)
- Ignore

## REST

- `POST /revit-publisher/v1/consolidations/preview`
- `POST /revit-publisher/v1/consolidations/apply`
