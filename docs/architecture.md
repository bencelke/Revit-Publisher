# RevIt Publisher — Architecture

RevIt Publisher is a private WordPress plugin for RevIt24 that ingests structured automotive article packages, validates them against a stable contract, and publishes interconnected SEO content at scale.

## Phase 1 Status (v0.2.0)

Phase 1 adds the article import engine and automotive content model:

- Article importer (`RevIt_Publisher_Article_Importer`)
- Article key registry
- Automotive taxonomies (manufacturer, model, generation, trim, engine, article type, cluster)
- Vehicle and cluster synchronization services
- Gutenberg content renderer
- SEO, relationship, and source metadata storage
- REST validate / preview / import endpoints
- Enhanced admin Import workflow with file upload
- Post editor sidebar and posts list columns
- Docker local WordPress development environment

Schema version remains **`revit-article-v1`** (unchanged).

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
Preview
      ↓
WordPress Importer                    ← Phase 1
      ↓
Vehicle Taxonomy                      ← Phase 1
Article Clusters                      ← Phase 1
SEO Metadata (stored)                 ← Phase 1
Planned Internal Links (stored)       ← Phase 1
Structured Data Intent (stored)       ← Phase 1
      ↓
WordPress Draft
      ↓
Future: link resolution, schema output, SEO health, hubs
```

## Current Components

| Component | Status | Purpose |
|-----------|--------|---------|
| `revit-article-v1` schema | Implemented | Frozen editorial handoff contract |
| `ArticlePackageValidator` | Implemented | Schema + business rule validation |
| `ArticleImporter` | Implemented | Create WordPress posts from packages |
| `ArticleRegistry` | Implemented | Stable `article_key` → post mapping |
| `VehicleTaxonomyService` | Implemented | Vehicle term sync + post meta |
| `ClusterService` | Implemented | Cluster term sync + pillar metadata |
| `ContentRenderer` | Implemented | Structured blocks → Gutenberg markup |
| REST validate/preview/import | Implemented | Admin/API import workflow |
| Admin Dashboard + Import UI | Implemented | Stats, preview, import |
| Post editor meta box | Implemented | Read-only RevIt article info |
| Posts list columns | Implemented | Vehicle, type, cluster, topic |

## Data Storage (Phase 1)

No custom database tables. Uses:

| Storage | Contents |
|---------|----------|
| WordPress posts | Title, slug, content, excerpt, status |
| Post meta | Article key, SEO, vehicle, relationships, sources, hash |
| Taxonomies | Manufacturer, model, generation, trim, engine, article type, cluster |

See [importer.md](./importer.md) for full meta and taxonomy mapping.

## Taxonomy Visibility Strategy

Automotive taxonomies are registered with `public => false` and `show_ui => true`:

- Available for admin filtering and content intelligence
- No public taxonomy archive URLs during Phase 1
- Term identity stored via deterministic slugs + term meta

Cross-taxonomy hierarchy (manufacturer → model → generation → trim) uses separate taxonomies with within-taxonomy parent terms where applicable. See importer documentation for details.

## Future Components (Not Implemented)

| Component | Notes |
|-----------|-------|
| Article update/re-import | Explicit update workflow for existing article keys |
| Internal-link insertion | Resolve keys to permalinks in content |
| Orphan detection | Find articles without inbound links |
| Cannibalization detection | Topic overlap analysis |
| SEO health checks | Editorial/technical QA |
| Public meta tags / JSON-LD | Frontend SEO output |
| Breadcrumbs | Vehicle/cluster-aware navigation |
| Related article blocks | Frontend display components |
| Vehicle hub pages | Aggregate cluster content per vehicle |
| Content-plan import | Batch editorial planning |
| Rank Math / Yoast integration | Third-party SEO plugins |

## Security Model

- REST endpoints require `edit_posts`
- WordPress REST nonce in admin UI
- Imported packages cannot auto-publish
- Duplicate article keys blocked (409)
- Sanitized meta storage, escaped admin output
- JSON file upload is client-side only (no server-side arbitrary upload storage)

## Admin Architecture

```text
WordPress Admin
      ↓
RevIt Publisher menu
      ↓
React app (Vite build)
      ↓
REST validate / preview / import
      ↓
ArticleImporter
      ↓
Registry + Taxonomies + ContentRenderer
      ↓
WordPress post + meta + terms
```

## Local Development

Docker Compose provides WordPress + MariaDB + WP-CLI. See README for setup.

## Related Documentation

- [Article Package Schema](./article-package-schema.md)
- [Importer](./importer.md)
- [Future Work](./future-work.md)
- [Development Log](./development-log.md)
