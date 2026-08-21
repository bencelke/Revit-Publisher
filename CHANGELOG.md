# Changelog

## 0.9.0 — 2026-08-21

Live WordPress SEO workflow for RevIt-managed articles.

- Scan stored WordPress posts for Mechanical SEO Compliance (checklist items only; not writing quality or rankings)
- Correct vehicle article counts so draft imports show as Articles 1 instead of 0
- Multi-vehicle batch summaries count articles, vehicles, and clusters instead of naming a single car
- Imported draft timestamps include GMT values so REST no longer serializes zero dates
- Deterministic internal-link discovery from shared engine/cluster/topic evidence in article body
- Orphan detection uses inbound contextual links in managed article body; Dashboard and SEO share that count
- Safe-fix workflow for mechanical metadata/schema items; links are never auto-applied or auto-published
- Admin assets cache-bust with plugin version + filemtime; Publisher REST responses are `no-store`

Article schema remains `revit-article-v1`. Content-plan schema remains `revit-content-plan-v1`.
