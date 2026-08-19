# Sitemaps

RevIt Publisher integrates with **WordPress core sitemaps** (`wp-sitemaps`), not a separate sitemap engine.

## Included

- Published `revit_vehicle` hubs
- Published RevIt-managed articles with index enabled

## Excluded

- Drafts and private posts
- Noindex managed articles
- Operational CPTs (issues, audits, redirects, link logs, 404 records, content plans)

## Admin view

**RevIt Publisher → SEO Health → Sitemap** shows indexable counts and excluded categories.

## Integrity signals

Sitemap health audits feed Needs Attention when indexable content is missing unexpectedly or noindex content appears included.

Search Console submission is future work (Phase 6+).
