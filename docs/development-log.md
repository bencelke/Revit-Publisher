# Development Log

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

### Explicitly Deferred

- WordPress post creation
- Vehicle taxonomies and cluster engine
- Internal link insertion
- SEO scoring, schema output, Gutenberg blocks
- Custom database tables

### Next

See [future-work.md](./future-work.md) for Phase 1 candidates.
