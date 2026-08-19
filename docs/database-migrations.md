# Database Migrations

`RevIt_Publisher_Migration_Service` manages schema versioning.

## Version constant

`REVIT_PUBLISHER_DB_VERSION` in plugin bootstrap; stored option `revit_publisher_db_version`.

## Behavior

- Runs on plugin init via `maybe_upgrade()`
- Idempotent — safe to run repeatedly
- Logs migrations to `revit_publisher_migration_log`
- Never destructive without explicit logic

## Current migrations

| Version | Action |
|---------|--------|
| 1 | Install GSC custom tables |

## Upgrade safety

Failed migrations report cleanly; existing data is preserved.
