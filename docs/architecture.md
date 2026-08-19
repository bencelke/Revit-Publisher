# RevIt Publisher — Architecture

RevIt Publisher is a private WordPress plugin for RevIt24 that ingests structured automotive article packages, validates them against a stable contract, and publishes interconnected SEO content at scale.

## Phase 4 Status (v0.5.0)

Phase 4 adds SEO operations and maintenance:

- `RevIt_Publisher_Audit_Service` — scheduled detection-only audits with snapshots
- `RevIt_Publisher_Issue_Service` — Needs Attention queue with reconciliation
- `RevIt_Publisher_Redirect_Service` + runtime — independent 301 redirects
- `RevIt_Publisher_Consolidation_Service` — operator-approved cannibalization workflow
- `RevIt_Publisher_Pillar_Link_Policy_Service` — cluster link suggestions/batch
- `RevIt_Publisher_Link_Change_Log` + undo
- `RevIt_Publisher_404_Monitor` — optional privacy-safe 404 aggregation
- `RevIt_Publisher_Vehicle_Health_Service` — multi-vehicle command center

See [audits.md](./audits.md), [needs-attention.md](./needs-attention.md), [redirects.md](./redirects.md), [consolidation.md](./consolidation.md), [404-monitor.md](./404-monitor.md), [vehicle-command-center.md](./vehicle-command-center.md).

## Phase 3 Status (v0.4.0)

Phase 3 adds content intelligence:

- `revit-content-plan-v1` schema and content planner
- Plan reconciliation, coverage, and gap detection
- Article update workflow with diff and manual edit protection
- Topic overlap analysis and RevIt SEO Health scoring
- Batch link approval and review-due signals

See [content-planner.md](./content-planner.md), [article-updates.md](./article-updates.md), [topic-intelligence.md](./topic-intelligence.md), [seo-health.md](./seo-health.md).

## Phase 2 Status (v0.3.0)

Phase 2 adds SEO intelligence — content graph, internal linking, and public SEO output:

- `RevIt_Publisher_Article_Resolver` — stable article key resolution
- `RevIt_Publisher_Content_Graph` — relationship graph from meta and taxonomies
- `RevIt_Publisher_Internal_Link_Service` — safe Gutenberg link suggestions and application
- `RevIt_Publisher_SEO_Health_Service` — orphan detection, duplicate topics, health signals
- `RevIt_Publisher_Link_Audit_Service` — site-wide link audit
- Public SEO output (meta, canonical, robots, JSON-LD)
- Yoast / Rank Math conflict detection
- Settings, Content Graph admin UI, graph REST endpoints

See [content-graph.md](./content-graph.md), [internal-linking.md](./internal-linking.md), and [seo-output.md](./seo-output.md).

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
Content Graph + Link Resolution     ← Phase 2
Internal Link Suggestions           ← Phase 2
Public SEO Output + JSON-LD         ← Phase 2
SEO Health Dashboard                ← Phase 2
      ↓
Future: vehicle hubs, re-import, bulk operations
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
