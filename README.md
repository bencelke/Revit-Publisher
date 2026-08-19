# RevIt Publisher

**Automotive SEO & Content Intelligence** — private WordPress plugin for [RevIt24](https://revit24.com).

RevIt Publisher ingests structured automotive article packages (`revit-article-v1`), validates them against a strict JSON contract, and will eventually publish interconnected SEO content at scale.

> **Phase 0 (v0.1.0):** Plugin foundation, article package contract, validator, REST validation endpoint, and minimal admin UI. No WordPress post creation yet.

---

## v0.1.0 Scope

- WordPress plugin bootstrap
- `revit-article-v1` JSON Schema
- PHP validation service
- REST validation endpoint
- Admin Dashboard + Import screens (React)
- Example packages and PHPUnit tests

## Prerequisites

- PHP 8.2+
- WordPress 6.0+
- [Composer](https://getcomposer.org/)
- [Node.js](https://nodejs.org/) 18+ and npm

## Installation

### 1. Copy plugin into WordPress

Copy or symlink this directory into your WordPress `wp-content/plugins/` folder:

```bash
ln -s "/path/to/RevIt Publisher" /path/to/wordpress/wp-content/plugins/revit-publisher
```

Alternatively, clone/copy the repo so the main plugin file is at:

```text
wp-content/plugins/revit-publisher/revit-publisher.php
```

### 2. Install PHP dependencies

```bash
cd wp-content/plugins/revit-publisher
composer install
```

### 3. Build admin assets

```bash
npm install
npm run build
```

For development with hot reload:

```bash
npm run dev
```

### 4. Activate the plugin

In WordPress admin: **Plugins → RevIt Publisher → Activate**

Or via WP-CLI:

```bash
wp plugin activate revit-publisher
```

## Admin Location

After activation, find **RevIt Publisher** in the WordPress admin sidebar:

- **Dashboard** — version, schema version, foundation status
- **Import** — paste JSON and validate packages

Requires `edit_posts` capability (editors and administrators).

## Validate a Sample Package

### Via Admin UI

1. Open **RevIt Publisher → Import**
2. Paste contents of `examples/article-valid.json`
3. Click **Validate Package**

### Via REST API

```bash
curl -X POST \
  'https://your-site.test/wp-json/revit-publisher/v1/article-packages/validate' \
  -H 'Content-Type: application/json' \
  -H 'X-WP-Nonce: YOUR_WP_REST_NONCE' \
  --cookie 'wordpress_logged_in_...=...' \
  -d @examples/article-valid.json
```

Authenticated users with `edit_posts` can obtain a REST nonce from `wpApiSettings.nonce` in admin or via the localized `revitPublisherAdmin.nonce` object on plugin pages.

### Via PHPUnit

```bash
composer install
composer test
```

## Repository Structure

```text
revit-publisher.php          # Plugin entry point
includes/                    # PHP classes
schemas/                     # revit-article-v1 JSON Schema
examples/                    # Valid and invalid sample packages
admin/src/                   # React admin source
admin/dist/                  # Built admin assets (after npm run build)
docs/                        # Architecture and schema documentation
tests/                       # PHPUnit tests
```

## Article Package Contract

The handoff format is **`revit-article-v1`**. See:

- `schemas/revit-article-v1.schema.json`
- `docs/article-package-schema.md`
- `examples/article-valid.json`

## Documentation

| Document | Description |
|----------|-------------|
| [Architecture](docs/architecture.md) | System design and future flow |
| [Article Package Schema](docs/article-package-schema.md) | Field-by-field contract reference |
| [Future Work](docs/future-work.md) | Planned features |
| [Development Log](docs/development-log.md) | Phase history |

## License

Private proprietary software for RevIt24 internal use. All rights reserved.

See [LICENSE](LICENSE).
