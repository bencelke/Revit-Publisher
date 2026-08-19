# Public Content Architecture

Phase 5 turns the internal automotive content graph into crawlable public site architecture.

## Layers

1. **Vehicle hubs** — canonical vehicle landing pages
2. **Pillar articles** — canonical cluster landing pages when a pillar exists
3. **Supporting articles** — self-canonical distinct articles linked to pillars/clusters
4. **Vehicle index** — `/vehicles/` lists published hubs by manufacturer
5. **Manufacturer pages** — `/vehicles/manufacturer/{slug}/` when ≥2 published hubs (configurable threshold)

## Cluster exposure rules

- If a cluster has a published pillar → pillar URL is the canonical cluster page
- If no pillar → cluster remains non-public until operator designates one or article threshold (default 3) is met
- No thin duplicate cluster archive pages competing with pillars

## Canonical policy

- Articles: self-canonical unless explicit validated canonical exists
- Hubs and pillars: self-canonical
- Supporting articles are **not** canonicalized to pillars merely because they are related
- Consolidated source articles should not remain indexable after consolidation completes

## Public safety

Public templates never expose drafts, operational CPTs, internal keys, or private taxonomies.
