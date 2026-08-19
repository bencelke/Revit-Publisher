# System Health

**RevIt Publisher → System Health** provides production diagnostics.

## Sections

- WordPress / PHP versions, permalink mode
- Plugin version, DB migration version
- Composer dependencies (Google API, JSON Schema)
- Cron schedules (audit, GSC sync, issue retention)
- Search Console connection diagnostic
- Storage counts (GSC tables, plans, articles, queue)
- Self-test checks (pass/warning/fail)
- Recent system events (sanitized)

## GSC credential diagnostics

| State | Meaning |
|-------|---------|
| `credentials_missing` | No OAuth client configured |
| `disconnected` | Not connected |
| `property_not_selected` | Connected but no property |
| `connected` | Healthy |

No secrets are displayed.

## REST

- `GET /system-health`
- `POST /system-health/run`
