# Google Search Console Integration

RevIt Publisher connects to the official Google Search Console API to combine internal SEO intelligence with real search performance data.

## Important limitations

- Search Console data is **not real-time rank tracking**
- Query lists may be **incomplete** (API prioritizes top rows)
- Opportunities are **RevIt recommendations**, not guarantees
- **No automatic article changes** occur

## Architecture

Services live under `includes/integrations/google-search-console/`:

- `RevIt_Publisher_GSC_Client_Interface` — testable client contract
- `RevIt_Publisher_GSC_Fake_Client` — fixture client for tests
- `RevIt_Publisher_GSC_Google_Client` — official Google API client
- Sync, storage, mapping, opportunities, URL inspection, sitemaps

## Data storage

Time-series metrics use custom tables:

- `{prefix}revit_gsc_page_metrics`
- `{prefix}revit_gsc_query_metrics`
- `{prefix}revit_gsc_inspections`

OAuth tokens are stored encrypted in WordPress options (never exposed to the browser).

## Connection

**RevIt Publisher → Settings → Google Search Console**

Production: configure `REVIT_GSC_CLIENT_ID` and `REVIT_GSC_CLIENT_SECRET` in `wp-config.php`.

Tests: use fixture connection (`use_fixture: true` via REST).

## Scopes

- Default: read-only (`webmasters.readonly`)
- Sitemap submission: full `webmasters` scope (optional setting)
