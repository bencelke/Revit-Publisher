# Development Log

## Phase B — Live WordPress SEO workflow

**Status:** Implemented  
**Version:** 0.9.0  
**Date:** 2026-08-21

### Delivered

- Site scan against stored WordPress posts (Mechanical SEO Compliance)
- Vehicle cards use real article counts including drafts
- Multi-vehicle import batch summaries
- GMT timestamps on imported drafts
- Deterministic internal-link discovery with existing-link and irrelevant-pair suppression
- Contextual orphan detection aligned across Dashboard and SEO
- Safe mechanical fixes; no auto-publish
- Admin asset filemtime cache-busting and no-store REST responses

---

## Phase A — Product Reset & UI Simplification

**Status:** Implemented  
**Version:** 0.8.1  
**Date:** 2026-08-20

### Delivered

- Simplified primary navigation: Dashboard, Batch Import, Vehicles, SEO, Advanced
- Batch-first import workflow (Upload → Analyze → Optimize → Import) with multi-file drop zone
- Redesigned operator dashboard without GSC/audit dominance
- Unified SEO overview screen; advanced diagnostics moved under Advanced hub
- RevIt admin design system (PageHeader, StatCard, DropZone, StepIndicator, badges, buttons)
- Application error boundary, normalized REST errors, loading and empty states
- Fixture banner for Search Console test data
- Operator workflow documentation

---

## Phase 7 — Editorial Prioritization + Production Hardening

**Status:** Implemented  
**Version:** 0.8.0  
**Date:** 2026-08-19

### Delivered

- Editorial Priority Engine with explainable scoring and deduplication
- Editorial Queue CPT, lifecycle (defer/complete/cooldown), manual items
- Vehicle and cluster opportunity indexes
- Dashboard "What To Work On" today view
- Enhanced refresh/article request exports with editorial context
- Migration framework, System Health page, backup export/restore
- Performance profiler, fixture generator, production packaging scripts
- REST APIs for editorial queue, system health, backups

---

## Phase 6 — Google Search Console Intelligence

**Status:** Implemented  
**Version:** 0.7.0  
**Date:** 2026-08-19

### Delivered

- GSC integration layer with interface, fake client, and Google API client
- OAuth 2.0 auth, encrypted token storage, property selection
- Custom DB tables for page metrics, query metrics, URL inspections
- Search Analytics sync with cron, locking, failure preservation
- Page/article mapping, vehicle and cluster aggregations
- Explainable opportunity engine and query coverage intelligence
- URL Inspection with daily quota and caching
- Sitemap list/submit integration
- Search Performance admin UI and REST API
- Needs Attention GSC issue types
- Refresh request export (`revit-refresh-request-v1`)
- Fixture dataset and Docker acceptance test

### Explicitly Deferred

- Google Analytics, Ads, Trends
- SERP scraping and rank tracking APIs
- Automatic article rewriting
- Bing Webmaster Tools

---

## Phase 5 — Public Automotive SEO Architecture

**Status:** Implemented  
**Version:** 0.6.0  
**Date:** 2026-08-19

### Delivered

- Public `revit_vehicle` hub CPT and deterministic identity registry
- Vehicle hub creation workflow (draft-only) and admin editor panel
- Dynamic public hub templates with theme override support
- Gutenberg blocks: vehicle-content, related-articles, cluster-navigation
- Public breadcrumbs (HTML + JSON-LD) with real navigable URLs
- WordPress core sitemap integration and sitemap health view
- SERP preview and snippet validation
- Vehicle hub and cluster SEO health signals
- Cluster link matrix UI in Content Graph
- Issue retention purge cron
- Vehicle index and manufacturer landing pages
- REST API for vehicle hubs, sitemap health, cluster link matrix
- Integration tests and Docker acceptance script

### Explicitly Deferred

- Google Search Console integration
- Rank tracking and external SEO APIs
- Auto-publishing of hubs or articles

---

## Phase 4 — SEO Operations + Redirect Management + Automated Content Maintenance

**Status:** Implemented  
**Version:** 0.5.0  
**Date:** 2026-08-19

### Delivered

- Scheduled audit engine with batched processing and locking
- Audit snapshots (`revit_audit_snapshot`) and trend history
- Needs Attention queue (`revit_issue`) with reconciliation
- Severity model and operator statuses
- Pillar link policy and cluster batch apply with change log
- Link undo from change log entries
- Redirect manager (`revit_redirect`) with frontend runtime
- Consolidation workflow (preview/apply, no body merge)
- Optional 404 monitor (aggregated, privacy-safe)
- Vehicle command center and hub-prep query methods
- Settings UI for all previously API-only options
- Operations REST API and admin pages
- Integration tests and Docker acceptance script

### Explicitly Deferred

- Google Search Console / Analytics
- Email/Slack notifications
- Public vehicle hubs
- Automatic redirect/merge decisions
- XML sitemap engine

---

## Phase 3 — Content Intelligence + Cluster Coverage + Article Update Workflow

**Status:** Implemented  
**Version:** 0.4.0  
**Date:** 2026-08-19

### Delivered

- `revit-content-plan-v1` schema, validator, examples
- Private `revit_content_plan` CPT registry
- Plan reconciliation, cluster completeness, vehicle coverage
- Content gap detection and `revit-article-request-v1` export
- Full article update service with diff preview and modes
- Manual edit protection via content hash
- Topic fingerprinting and overlap analysis
- RevIt SEO Health score with category explainability
- Optimization recommendations and review status
- Content Planner and SEO Health admin UI
- Dashboard command center redesign
- Batch link approval REST and UI
- Resolver cache invalidation on register
- Integration tests and acceptance script

### Explicitly Deferred

- SERP/search volume APIs
- Embeddings/semantic similarity
- Auto-publish and bulk auto-rewrite
- XML sitemap, redirects, 404 monitor
- External SEO plugin deep integration

---

## Phase 2 — SEO Graph + Internal Linking + Public SEO Output

**Status:** Implemented  
**Version:** 0.3.0  
**Date:** 2026-08-19

### Purpose

Build an interconnected automotive knowledge graph from imported articles and output conservative public SEO metadata.

### Delivered

- Article resolver with caching
- Content graph service (inbound/outbound, pillar, cluster, vehicle)
- Planned link resolution and status classification
- Safe Gutenberg internal link suggestion and application engine
- Backlink opportunity detection
- SEO health signals (orphans, missing meta/pillar, duplicate topics)
- Public meta description, canonical, robots output
- Article + BreadcrumbList JSON-LD
- Yoast / Rank Math conflict detection and admin warning
- Settings page and Content Graph admin UI
- Upgraded post editor RevIt panel
- Graph REST endpoints and link audit service
- Graph example packages (`examples/graph/`)
- Integration tests and graph acceptance script

### Explicitly Deferred

- Full article update/re-import workflow
- FAQ JSON-LD
- Semantic duplicate detection / embeddings
- Automatic bulk link rewriting
- Public vehicle hub pages
- Custom database tables
- External SEO APIs

---

## Phase 1 — Article Import Engine + Automotive Content Model

**Status:** Implemented  
**Version:** 0.2.0  
**Date:** 2026-08-19

### Purpose

Turn validated `revit-article-v1` packages into WordPress drafts with durable automotive content relationships for future SEO automation.

### Delivered

- `RevIt_Publisher_Article_Importer` with failure compensation
- Article key registry (`_revit_article_key`, duplicate blocking)
- Automotive taxonomies (manufacturer, model, generation, trim, engine, article type, cluster)
- Vehicle and cluster synchronization services
- Gutenberg content renderer with FAQ section
- SEO, relationship, source, and structured data intent post meta
- Package hash traceability (`_revit_package_hash`)
- REST preview and import endpoints
- Enhanced Import admin UI with JSON file upload
- Dashboard stats (imported articles, models, clusters)
- Post editor RevIt sidebar and posts list columns
- Docker Compose local WordPress environment
- PHPUnit unit + WordPress integration tests
- Runtime acceptance test script

### Explicitly Deferred

- Article update/re-import
- Internal link insertion
- Public SEO output / JSON-LD
- SEO scoring and health checks
- Vehicle hub pages
- Custom database tables

---

## Phase 0 — Plugin Foundation & Article Package Contract

**Status:** Implemented  
**Version:** 0.1.0  
**Date:** 2026-08-19

### Purpose

Lock the structured article handoff (`revit-article-v1`) before building WordPress publishing automation.

### Delivered

- WordPress plugin bootstrap (activate/deactivate, hooks, version constant)
- `revit-article-v1` JSON Schema with strict top-level structure
- `ArticlePackageValidator` (JSON Schema + business rules)
- REST endpoint: `POST /wp-json/revit-publisher/v1/article-packages/validate`
- Admin menu: Dashboard + Import (React/TypeScript + Vite)
- Example valid/invalid packages
- PHPUnit validator tests
- Architecture and schema documentation
