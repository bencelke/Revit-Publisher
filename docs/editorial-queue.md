# Editorial Queue

Phase 7 introduces a work-planning queue separate from Needs Attention.

## Purpose

Needs Attention detects system problems. The Editorial Queue recommends **what to work on next** — create content, refresh articles, fix links, resolve indexing, complete clusters.

## Work types

| Type | Meaning |
|------|---------|
| `create_content` | Missing planned article |
| `refresh_content` | Search Console / SEO refresh opportunity |
| `fix_internal_links` | Orphans or unresolved links |
| `resolve_indexing` | GSC index/canonical issue |
| `resolve_topic_overlap` | High-risk overlap |
| `complete_cluster` | Missing pillar or cluster gap |
| `review_article` | Review due |
| `fix_metadata` | Missing SEO title/description |

## Priority levels

`urgent`, `high`, `medium`, `low` — most editorial work is high/medium. Urgent is reserved for structural indexing failures.

## Lifecycle

Statuses: `open`, `in_progress`, `deferred`, `completed`, `dismissed`

- **Defer** — hide from Today until date (1 week default via UI)
- **Complete** — records completion; 30-day cooldown before identical work is recreated
- **Manual items** — operator-created tasks via REST

## Reconciliation

Runs automatically after:

- Search Console sync
- RevIt audit completion

Manual: **Reconcile Queue** button or `POST /editorial-queue/reconcile`

## REST

- `GET /editorial-queue`
- `POST /editorial-queue`
- `PUT /editorial-queue/{id}`
- `POST /editorial-queue/reconcile`
- `GET /editorial-queue/today`

See [editorial-priority.md](./editorial-priority.md).
