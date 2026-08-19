# Operator Workflow

RevIt Publisher is an **Automotive SEO Publishing Engine**. The normal operator path is intentionally simple:

```text
Dashboard → Batch Import → Analyze → Optimize → Import Drafts → Review
```

## 1. Dashboard

Start on **Dashboard** for a snapshot of articles, vehicles, SEO issues, and link opportunities. Use **Upload Articles** to begin a batch.

Recent batches and vehicles appear after import. Detailed Search Console charts and audit dumps live under **Advanced**.

## 2. Batch Import

1. **Upload** — drag multiple `.json` article packages or choose files.
2. **Analyze** — review vehicle grouping, clusters, SEO completeness, overlaps, and existing WordPress matches. No posts are created during analysis.
3. **Optimize** — prepare metadata and internal link relationships using existing RevIt services (no automatic rewriting in Phase A).
4. **Import** — create **drafts only**. Existing article keys can be skipped or reviewed; nothing is silently overwritten.

**Advanced → Paste JSON manually** preserves the single-article import path for debugging.

## 3. Review

- **Review Drafts** — WordPress post list filtered to drafts.
- **Vehicles** — per-vehicle articles, clusters, SEO health, hub status.
- **SEO** — site-wide overview, internal linking, topic overlap.

## Advanced features

Power tools remain available under **Advanced**:

- Content Planner, Content Graph, Needs Attention, Audits
- Search Performance (Google Search Console)
- Editorial Queue, Redirects, 404 Monitor, System Health, Settings

These screens are not part of the default publishing workflow but are fully supported.
