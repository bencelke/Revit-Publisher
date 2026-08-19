# Content Planner

Phase 3 introduces `revit-content-plan-v1` — a planning document separate from article packages.

## Contract

Schema: `schemas/revit-content-plan-v1.schema.json`

A content plan describes an entire vehicle content ecosystem:

- vehicle identity
- clusters with pillar keys and recommended article lists
- planned article entries with priorities and topics
- optional relationship declarations

## Import Workflow

**RevIt Publisher → Content Planner**

1. Upload or paste content plan JSON
2. Validate & Preview — shows planned vs existing vs missing
3. Import Plan — stores plan in private `revit_content_plan` CPT

Content plans are roadmaps. They do **not** create WordPress articles automatically.

## Reconciliation

`RevIt_Publisher_Content_Plan_Service` classifies each planned `article_key`:

| Status | Meaning |
|--------|---------|
| `publish` / `draft` / `pending` | RevIt-managed post exists |
| `missing` | No post for article key |
| `unmanaged_collision` | Post exists but not RevIt-managed |

## Cluster Completeness

Per cluster metrics (not SEO scores):

- plan coverage %
- pillar status
- internal link coverage %
- meta completeness %
- orphan count

## Article Request Export

Missing articles can export `revit-article-request-v1` JSON for ChatGPT handoff:

```text
Publisher identifies gap → export request → ChatGPT generates revit-article-v1 → import
```

Scopes: single article, cluster, or full vehicle plan.

## REST API

| Endpoint | Purpose |
|----------|---------|
| `POST /content-plans/validate` | Validate plan JSON |
| `POST /content-plans/preview` | Preview reconciliation |
| `POST /content-plans/import` | Import plan |
| `GET /content-plans` | List plans |
| `GET /content-plans/{id}/coverage` | Full coverage report |
| `GET /content-plans/{id}/missing-articles` | Missing entries |
| `GET /content-plans/{id}/article-request` | Export request JSON |

See [examples/content-plan-valid.json](../examples/content-plan-valid.json).
