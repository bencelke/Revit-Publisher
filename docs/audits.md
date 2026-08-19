# Scheduled Audits

RevIt Publisher runs **detection-only** scheduled audits via WordPress Cron.

## What audits detect

- Orphan articles
- Unresolved internal links
- Missing pillars
- Content-plan gaps
- Review-due articles
- High-risk topic overlaps
- Missing SEO metadata
- Broken RevIt-managed targets

Audits **never modify content automatically**.

## Snapshots

Each completed audit stores a compact snapshot in the private `revit_audit_snapshot` CPT.

## Scheduling

Default: daily. Options: daily, twice daily, weekly.

Configure under **Settings → Content Maintenance**.

## Manual runs

Use **Audits → Run Audit Now** or `POST /revit-publisher/v1/audits/run`.

## Locking

Concurrent audits are blocked with a transient lock (15-minute stale timeout).
