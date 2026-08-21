# RevIt Publisher

**Automotive SEO Publishing Engine** — private WordPress plugin for [RevIt24](https://revit24.com).

## Normal operator workflow

```text
Dashboard → Batch Import → Analyze → Optimize → Import Drafts → Review
```

1. **Dashboard** — snapshot of articles, vehicles, SEO issues, and link opportunities.
2. **Batch Import** — upload multiple `revit-article-v1` JSON packages; validate, analyze, optimize, import as drafts.
3. **Vehicles** — per-vehicle articles, clusters, SEO health, hub status.
4. **SEO** — site-wide health, internal linking, topic overlap.
5. **Advanced** — Content Planner, Search Performance, audits, settings, and other power tools.

See [docs/operator-workflow.md](docs/operator-workflow.md) for details.

> **Phase B (v0.9.0):** Live WordPress SEO scan, mechanical checklist, natural internal-link discovery, Optimize Article / Scan Site.

> **Phase A (v0.8.1):** Product reset and UI simplification — streamlined navigation, batch-first import, design system, error boundaries.

> **Phase 7 (v0.8.0):** Editorial prioritization queue, system health, backups, production packaging.

> **Phase 6 (v0.7.0):** Google Search Console intelligence, search performance dashboard, opportunity engine.

> **Phase 5 (v0.6.0):** Public vehicle hubs, crawlable content architecture, sitemaps, breadcrumbs, public blocks, SERP preview.

> **Phase 4 (v0.5.0):** SEO operations, scheduled audits, redirect manager, consolidation workflow, 404 monitor, vehicle command center.

> **Phase 3 (v0.4.0):** Content intelligence, content plans, article updates, topic overlap, RevIt SEO Health.

> **Phase 2 (v0.3.0):** SEO content graph, internal linking engine, public SEO output, structured data.

---

## v0.9.0 Scope

- Mechanical SEO Compliance scan of live WordPress posts
- Corrected vehicle article counts and multi-vehicle batch summaries
- Repaired imported draft timestamps
- Deterministic internal-link discovery and contextual orphan detection
- Safe-fix workflow without auto-publish
- Consistent Dashboard/SEO data, version+filemtime cache busting, no-store REST

## v0.8.0 Scope

- Editorial Priority Engine with explainable scoring
- Editorial Queue (Today, Create, Refresh, Technical, Linking, Review)
- Vehicle and cluster opportunity indexes
- Dashboard "What To Work On" today view
- Enhanced refresh/article request exports with editorial context
- DB migration framework, System Health diagnostics
- Backup export/restore (`revit-publisher-backup-v1`)
- Performance profiler and production ZIP packaging

## v0.7.0 Scope

- Google Search Console API integration (Search Analytics, URL Inspection, Sitemaps)
- OAuth 2.0 connection with secure token storage
- Custom tables for page/query metrics and inspections
- Search Performance admin dashboard (Overview, Pages, Vehicles, Clusters, Opportunities, Indexing, Sitemaps)
- Explainable opportunity engine (page 2, low CTR, declining, zero visibility, unexpected queries)
- Needs Attention integration for GSC issues
- Refresh request export (`revit-refresh-request-v1`)
- Fixture client for automated testing
- Daily sync cron with locking and failure preservation
- Vehicle Command Center and article editor GSC panels

## v0.6.0 Scope

- Public `revit_vehicle` hub CPT with deterministic vehicle identity keys
- Vehicle hub registry, draft creation workflow, and admin editor panel
- Dynamic hub templates with theme override support
- Server-rendered blocks: `revit/vehicle-content`, `revit/related-articles`, `revit/cluster-navigation`
- Public breadcrumbs (HTML + JSON-LD) with real navigable URLs
- WordPress core sitemap integration and sitemap health admin view
- SERP preview and snippet validation
- Vehicle hub and cluster SEO health signals
- Cluster link matrix UI in Content Graph
- Issue retention purge cron
- Vehicle index (`/vehicles/`) and manufacturer pages (threshold: 2 hubs)
- REST endpoints for vehicle hubs, sitemap health, cluster link matrix

## v0.5.0 Scope

- Scheduled audit engine (WP-Cron, batched, locked)
- Audit snapshots and history with trends
- Needs Attention unified issue queue with reconciliation
- Deterministic severity model
- Pillar-to-supporting link policy and cluster batch apply
- Link change log and undo
- Redirect manager (301) with runtime and safety checks
- Cannibalization consolidation workflow (no body merge)
- Optional 404 monitor (privacy-safe, off by default)
- Multi-vehicle command center and vehicle detail
- Settings UI completion (audit, 404, redirects, batch limits)
- Admin notifications and issue badge

## v0.4.0 Scope

- Content plan contract (`revit-content-plan-v1`) and validator
- Content plan import and private `revit_content_plan` registry
- Plan-to-site reconciliation and vehicle/cluster coverage
- Content gap detection and `revit-article-request-v1` export
- Full article update workflow with diff preview and modes (full/seo/relationships)
- Manual edit protection and WordPress revision preservation
- Topic fingerprinting and overlap analysis
- RevIt SEO Health score (0–100) with transparent category breakdown
- Optimization recommendations
- Content Planner and SEO Health admin UI
- Dashboard command center redesign
- Batch link approval (max 50)
- Review-due signals

## v0.3.0 Scope

- Article resolver (`article_key` → post ID, permalink, title)
- Content graph service (inbound/outbound, pillar, cluster, vehicle)
- Planned link resolution with status classification
- Safe Gutenberg internal link insertion (suggest + apply)
- Backlink opportunity detection
- SEO health signals (orphans, missing meta, duplicate topics)
- Public meta description, canonical, robots output
- Article + BreadcrumbList JSON-LD
- Yoast / Rank Math conflict detection
- Settings page, Content Graph admin UI, upgraded editor panel
- Graph REST endpoints and link audit
- Graph example packages and acceptance test

## v0.2.0 Scope

- Article importer (validate → preview → import as draft)
- Article key registry with duplicate blocking
- Automotive taxonomies (manufacturer, model, generation, trim, engine, cluster, article type)
- Vehicle + cluster synchronization
- Gutenberg content renderer
- SEO, relationship, and source metadata storage
- REST validate / preview / import endpoints
- Enhanced Import admin UI with JSON file upload
- Post editor sidebar + posts list columns
- Docker local WordPress environment
- PHPUnit unit + integration tests

Article schema version remains **`revit-article-v1`**.

## Prerequisites

- PHP 8.2+
- WordPress 6.0+
- [Composer](https://getcomposer.org/)
- [Node.js](https://nodejs.org/) 18+ and npm
- [Docker](https://www.docker.com/) (for local WordPress development)

## Docker Local WordPress Setup

```bash
cp .env.example .env
docker compose up -d
```

WordPress: http://localhost:8080

Install WordPress on first run:

```bash
docker compose run --rm wpcli wp core install \
  --url="http://localhost:8080" \
  --title="RevIt Publisher Dev" \
  --admin_user="admin" \
  --admin_password="adminpass" \
  --admin_email="admin@example.com" \
  --skip-email
```

Install plugin dependencies and activate:

```bash
docker compose run --rm --entrypoint sh wpcli -c "
  cd /var/www/html/wp-content/plugins/revit-publisher &&
  curl -sS https://getcomposer.org/installer | php &&
  php composer.phar install --no-interaction
"
npm install && npm run build
docker compose run --rm wpcli wp plugin activate revit-publisher
```

## Manual Installation (existing WordPress)

```bash
ln -s "/path/to/RevIt Publisher" /path/to/wordpress/wp-content/plugins/revit-publisher
cd wp-content/plugins/revit-publisher
composer install
npm install && npm run build
wp plugin activate revit-publisher
```

## Article Import Workflow

### Admin UI

1. Open **RevIt Publisher → Import**
2. Paste JSON or choose a `.json` file (max 5 MB)
3. Click **Validate & Preview**
4. Review preview (title, vehicle, cluster, links, status)
5. Click **Import as Draft**

### REST API

| Endpoint | Method | Purpose |
|----------|--------|---------|
| `/wp-json/revit-publisher/v1/article-packages/validate` | POST | Validation only |
| `/wp-json/revit-publisher/v1/article-packages/preview` | POST | Validation + preview |
| `/wp-json/revit-publisher/v1/article-packages/import` | POST | Validation + import |
| `/wp-json/revit-publisher/v1/stats` | GET | Dashboard counts |

Requires authenticated user with `edit_posts` and `X-WP-Nonce` header.

Example:

```bash
curl -X POST \
  'http://localhost:8080/wp-json/revit-publisher/v1/article-packages/import' \
  -H 'Content-Type: application/json' \
  -H 'X-WP-Nonce: YOUR_NONCE' \
  --cookie 'wordpress_logged_in_...=...' \
  -d @examples/article-valid.json
```

## Testing

### Unit tests (validator, no WordPress)

```bash
composer install
composer test
```

### Integration tests (requires WordPress test suite)

Inside Docker:

```bash
docker compose run --rm --entrypoint sh wpcli -c "
  cd /var/www/html/wp-content/plugins/revit-publisher
  apk add --no-cache subversion curl
  bash bin/install-wp-tests.sh wordpress_test wordpress wordpress db latest
  vendor/bin/phpunit -c phpunit.integration.xml
"
```

### Runtime acceptance test

```bash
chmod +x scripts/acceptance-test.sh
./scripts/acceptance-test.sh
```

This script:

1. Starts Docker WordPress
2. Activates the plugin
3. Previews and imports `examples/article-valid.json`
4. Verifies post, meta, taxonomies
5. Confirms duplicate import returns HTTP 409

## Admin Location

**RevIt Publisher** in WordPress admin sidebar (primary menu):

- **Dashboard** — articles, vehicles, SEO issues, recent batches
- **Batch Import** — multi-file upload, analyze, optimize, import drafts
- **Vehicles** — vehicle cards, clusters, SEO health, hub status
- **SEO** — overview, internal linking, topic overlap
- **Advanced** — Content Planner, Search Performance, audits, settings, and other power tools

See [Operator Workflow](docs/operator-workflow.md).

### Advanced features

All prior capabilities remain available under **Advanced**: Content Planner, Content Graph, Needs Attention, Audits, Search Performance (Google Search Console), Editorial Queue, Redirects, 404 Monitor, System Health, Settings, and detailed SEO Health.

Imported posts show a **RevIt Publisher** sidebar panel and extra columns in the Posts list.

## Repository Structure

```text
revit-publisher.php          # Plugin entry point
includes/                    # PHP services
schemas/                     # revit-article-v1 JSON Schema
examples/                    # Sample packages
admin/src/                   # React admin source
docker-compose.yml           # Local WordPress environment
bin/install-wp-tests.sh      # WordPress test suite installer
scripts/acceptance-test.sh   # Runtime acceptance test
docs/                        # Documentation
tests/                       # PHPUnit tests
```

## Documentation

| Document | Description |
|----------|-------------|
| [Operator Workflow](docs/operator-workflow.md) | Normal publishing workflow |
| [Architecture](docs/architecture.md) | System design |
| [Importer](docs/importer.md) | Import workflow and storage mapping |
| [Article Package Schema](docs/article-package-schema.md) | Field reference |
| [Future Work](docs/future-work.md) | Planned features |
| [Development Log](docs/development-log.md) | Phase history |

## License

Private proprietary software for RevIt24 internal use. All rights reserved.

See [LICENSE](LICENSE).
