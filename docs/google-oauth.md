# Google OAuth Setup

## Production (recommended)

Define credentials in `wp-config.php`:

```php
define( 'REVIT_GSC_CLIENT_ID', 'your-client-id.apps.googleusercontent.com' );
define( 'REVIT_GSC_CLIENT_SECRET', 'your-client-secret' );
```

Redirect URI in Google Cloud Console:

```
https://yoursite.com/wp-admin/admin.php?page=revit-publisher-settings&revit_gsc_oauth=1
```

## WordPress options (alternative)

Enter client ID and secret in **Settings** (stored in options — less ideal for production).

## Security

- OAuth state verified with nonces/transients
- Tokens encrypted at rest
- Tokens never logged or exposed to React bundle
- `manage_options` required for connection

## Testing

Use fixture mode — no real Google credentials required:

```
POST /revit-publisher/v1/search-console/connect
{ "use_fixture": true }
```

Or set `REVIT_PUBLISHER_GSC_USE_FAKE=true`.

See `.env.example` for Docker placeholders.
