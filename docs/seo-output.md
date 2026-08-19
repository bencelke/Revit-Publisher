# Public SEO Output

Phase 2 adds conservative public SEO metadata and JSON-LD for RevIt-managed posts only.

## Title

Uses `_revit_seo_title` via the WordPress `document_title_parts` filter on singular posts.

RevIt does not fight theme title behavior — it replaces the title part when a RevIt SEO title is stored.

## Meta Description

When enabled, outputs:

```html
<meta name="description" content="...">
```

Uses `_revit_meta_description` from the imported package.

## Canonical

| `_revit_canonical` value | Behavior |
|--------------------------|----------|
| `auto` | Uses the post permalink |
| Explicit URL | Validated and used as canonical |

Conflict detection skips output if another canonical tag is already present where practical.

## Robots

Uses `_revit_index` and `_revit_follow` to output appropriate robots directives via WordPress-supported mechanisms.

## Structured Data

### Article JSON-LD

Output when Article schema is enabled and structured data intent includes article:

- headline
- description
- datePublished / dateModified
- mainEntityOfPage
- author (if available)
- publisher / site identity (from settings when configured)

### BreadcrumbList JSON-LD

Generated from automotive taxonomy structure where valid navigable URLs exist.

Example path:

```text
Home → BMW → X3 → G01 → M40i → Article
```

Breadcrumb entries without public archive URLs are omitted — no fake URLs.

FAQ JSON-LD is deferred.

## Settings

**RevIt Publisher → Settings → Public SEO Output**

| Setting | Default |
|---------|---------|
| Enable meta descriptions | On (unless conflict) |
| Enable canonical | On |
| Enable robots directives | On |
| Enable Article schema | On |
| Enable Breadcrumb schema | On |

## SEO Plugin Compatibility

RevIt detects active conflicting plugins:

- Yoast SEO
- Rank Math

When detected:

- Public RevIt SEO output is disabled automatically
- Admin warning is shown with link to Settings
- Third-party plugins are not modified or uninstalled

Preferred default: avoid duplicate metadata.

## Performance

SEO output hooks run only on singular RevIt-managed posts. No site-wide graph rebuilding on frontend requests.

## REST

Settings are readable/updatable via:

```text
GET  /wp-json/revit-publisher/v1/settings
PUT  /wp-json/revit-publisher/v1/settings
```

Requires `manage_options`.
