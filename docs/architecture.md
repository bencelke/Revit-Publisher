# RevIt Publisher — Architecture

RevIt Publisher is a private WordPress plugin for RevIt24 that ingests structured automotive article packages, validates them against a stable contract, and will eventually publish interconnected SEO content at scale.

## Phase 0 Status

Phase 0 implements only the foundation:

- WordPress plugin bootstrap
- `revit-article-v1` JSON Schema contract
- PHP validation service
- REST validation endpoint
- Minimal React admin UI (Dashboard + Import)

No WordPress posts, taxonomies, clusters, or internal-link automation exist yet.

## Conceptual Flow

```text
RevIt24 Scraper
      ↓
dossier.json
      ↓
ChatGPT Editorial Process
      ↓
revit-article-v1
      ↓
RevIt Publisher
      ↓
Validation
      ↓
Future WordPress Importer
      ↓
Vehicle Taxonomy
Article Clusters
Internal Links
SEO Metadata
Structured Data
      ↓
WordPress Draft
```

## Current Components

| Component | Status | Purpose |
|-----------|--------|---------|
| `revit-publisher.php` | Implemented | Plugin bootstrap |
| `schemas/revit-article-v1.schema.json` | Implemented | Article package contract |
| `ArticlePackageValidator` | Implemented | Schema + business rule validation |
| REST `POST /article-packages/validate` | Implemented | Admin/API validation |
| Admin Dashboard | Implemented | Foundation status |
| Admin Import screen | Implemented | Manual package validation |
| React admin app | Implemented | UI for validation workflow |

## Future Components (Not Implemented)

| Component | Notes |
|-----------|-------|
| Article importer | Create/update WordPress posts from packages |
| Vehicle taxonomies | Manufacturer, model, generation, trim, engine |
| Cluster engine | Pillar/supporting relationships |
| Internal-link manager | Resolve `article_key` references to URLs |
| Orphan detection | Find articles without inbound links |
| Cannibalization detection | Topic overlap analysis |
| SEO health checks | Editorial/technical QA |
| Breadcrumbs | Vehicle/cluster-aware navigation |
| Schema output | Article, FAQ, breadcrumb JSON-LD |
| Gutenberg blocks | Render structured content blocks |
| Related article blocks | Display graph from `related_articles` |
| Vehicle hub pages | Aggregate cluster content per vehicle |
| Content-plan import | Batch editorial planning |
| Article update workflow | Re-import revisions safely |

## Data Storage Strategy (Deferred)

Phase 0 intentionally avoids custom database tables. Future phases must decide what belongs in:

- WordPress posts (canonical article content)
- Custom taxonomies (vehicle identity, clusters)
- Post meta (SEO fields, article keys, relationships)
- Options (global plugin settings)
- Custom tables (relationship graphs, audit logs)

Document decisions before Phase 1 implementation.

## Security Model

- REST validation requires authenticated users with `edit_posts`
- Admin screens require `edit_posts`
- No public endpoints expose article package payloads
- Imported packages cannot request `publish` status in V1

## Admin Architecture

```text
WordPress Admin
      ↓
RevIt Publisher menu
      ↓
React app (Vite build)
      ↓
REST validation endpoint
      ↓
ArticlePackageValidator
      ↓
JSON Schema (revit-article-v1)
```

Assets load only on RevIt Publisher admin pages.

## Extension Points for Phase 1

1. **Importer service** — translate validated packages into draft posts
2. **Article registry** — map `article_key` → post ID
3. **Taxonomy sync** — upsert vehicle terms from `vehicle`
4. **Cluster registry** — map `cluster_key` → taxonomy/meta structure
5. **Link resolver** — deferred link insertion using `internal_links`

## Related Documentation

- [Article Package Schema](./article-package-schema.md)
- [Future Work](./future-work.md)
- [Development Log](./development-log.md)
