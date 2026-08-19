# Backups

Export/restore RevIt-owned configuration and intelligence.

## Contract

`revit-publisher-backup-v1` — schema at `schemas/revit-publisher-backup-v1.schema.json`

## Included

- Settings (excluding secrets)
- Content plans, article keys/metadata, redirects
- Editorial queue items

## Excluded

- OAuth tokens, client secrets
- WordPress passwords
- Article post bodies

## REST

- `POST /backups/export`
- `POST /backups/validate`
- `POST /backups/import-preview`
- `POST /backups/import` — safe settings restore only

Restore never overwrites article content by default.
