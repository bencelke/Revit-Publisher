# Refresh Request Export

Export format: `revit-refresh-request-v1`

Purpose: Publisher → operator → external editorial workflow (e.g. ChatGPT-assisted review).

## Export

From Search Performance opportunities or REST:

```
GET /revit-publisher/v1/search-console/posts/{id}/refresh-export?reason=page2_opportunity
```

## Privacy

Exports include:

- Article key and vehicle
- Search Console metrics and top queries
- RevIt SEO health breakdown

Exports **exclude**:

- OAuth tokens
- Google account identifiers
- Property ownership metadata

## No auto-update

Export does not modify article content.
