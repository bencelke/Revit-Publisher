# Structured Data

Conservative schema output — no unsupported rich-result types.

## Articles

When enabled: `Article` JSON-LD with headline, description, dates, author (real WordPress author), publisher (organization settings), and featured image when a real featured image exists. Empty/fake fields are omitted.

## Vehicle hubs

`WebPage` / `CollectionPage` plus `BreadcrumbList`. Hubs are not marked as `Product`.

## Breadcrumbs

Visible breadcrumb nav and JSON-LD use the same hierarchy with real navigable URLs only.

## FAQ

FAQ JSON-LD defaults **off**. Visible FAQ content remains normal page content; FAQ schema is not marketed as a ranking feature.
