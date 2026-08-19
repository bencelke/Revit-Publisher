# Future Work

Items planned for post–Phase 0 development. **Not implemented in v0.1.0.**

## Import & Publishing

- [ ] Article importer (validated package → WordPress draft post)
- [ ] Article update/re-import workflow
- [ ] Content-plan batch import
- [ ] Featured image resolution from placeholders

## Content Model

- [ ] Vehicle taxonomies (manufacturer, model, generation, trim, engine)
- [ ] Article cluster engine
- [ ] Pillar/supporting article relationship management
- [ ] Article key registry (`article_key` → post ID)

## Linking & Graph

- [ ] Internal-link suggestions and resolution
- [ ] Automatic backlink insertion
- [ ] Orphan article detection
- [ ] Topic overlap / cannibalization detection
- [ ] Related article blocks (frontend)

## SEO & Structured Data

- [ ] SEO health checks
- [ ] Breadcrumb generation
- [ ] Schema.org output (Article, FAQ, BreadcrumbList)
- [ ] Canonical URL management

## WordPress Integration

- [ ] Custom Gutenberg blocks for structured content
- [ ] Vehicle hub pages
- [ ] RankMath/Yoast integration (if needed)
- [ ] Public-facing Publisher features

## External Integrations

- [ ] RevIt24 scraper / dossier.json pipeline
- [ ] ChatGPT editorial workflow tooling
- [ ] Smart Quote CTA integration
- [ ] Shop/map integrations

## Infrastructure

- [ ] Custom database tables (if relationship graph requires it)
- [ ] Audit log for imports and updates
- [ ] WP-CLI commands for batch validation/import

## Testing & Tooling

- [ ] Full WordPress integration test suite (wp-phpunit)
- [ ] REST permission tests in WP environment
- [ ] CI pipeline for PHPUnit + frontend build
