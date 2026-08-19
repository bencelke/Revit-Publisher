# RevIt Publisher

**Automotive SEO & Content Intelligence** — private WordPress plugin for [RevIt24](https://revit24.com).

RevIt Publisher ingests structured automotive article packages (`revit-article-v1`), validates them, and imports them as WordPress drafts with automotive taxonomies and structured metadata.

> **Phase 1 (v0.2.0):** Article import engine, automotive content model, preview/import REST endpoints, Gutenberg content rendering, Docker dev environment.

---

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

**RevIt Publisher** in WordPress admin sidebar:

- **Dashboard** — imported article counts, schema version
- **Import** — validate, preview, import workflow

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
| [Architecture](docs/architecture.md) | System design |
| [Importer](docs/importer.md) | Import workflow and storage mapping |
| [Article Package Schema](docs/article-package-schema.md) | Field reference |
| [Future Work](docs/future-work.md) | Planned features |
| [Development Log](docs/development-log.md) | Phase history |

## License

Private proprietary software for RevIt24 internal use. All rights reserved.

See [LICENSE](LICENSE).
