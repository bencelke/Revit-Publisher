# Public Blocks

Server-rendered dynamic Gutenberg blocks (theme-compatible, scoped CSS):

## `revit/vehicle-content`

Lists published articles for a vehicle hub.

Attributes: `vehicleKey`, `allowedTypes`, `cluster`, `max`, `order`

## `revit/related-articles`

Related articles for the current post.

Priority: explicit `_revit_related_articles` → same cluster → same vehicle.

Default limit: 4–6. Never shows random sitewide articles.

## `revit/cluster-navigation`

Pillar and supporting article index for a cluster. Updates automatically as the cluster grows.

Only published posts are displayed.
