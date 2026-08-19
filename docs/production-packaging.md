# Production Packaging

## Build command

```bash
npm run build
composer install --no-dev --optimize-autoloader
./scripts/package-plugin.sh
```

Produces: `build/revit-publisher-v0.8.0.zip`

## Validate

```bash
./scripts/validate-package.sh build/revit-publisher-v0.8.0.zip
```

Checks: main plugin file, vendor autoload, admin dist, schemas, no `.env`, no git/node_modules, no credential strings.

## Package contents

- Production PHP + vendor
- Built admin assets
- JSON schemas + templates

## Excluded

- tests, Docker files, node_modules, `.git`, `.env`, acceptance scripts
