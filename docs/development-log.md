# Development Log

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
