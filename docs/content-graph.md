# Content Graph

Phase 2 derives an automotive content graph from existing post meta and taxonomies — no custom database tables.

## Article Resolution

`RevIt_Publisher_Article_Resolver` resolves stable `_revit_article_key` values to WordPress posts.

| Method | Purpose |
|--------|---------|
| `resolve( $article_key )` | Full post details (ID, title, permalink, status, edit URL) |
| `resolve_post( $post_id )` | Resolve by post ID (RevIt-managed only) |
| `exists( $article_key )` | Whether key is registered |
| `is_managed( $post_id )` | Whether post is RevIt-managed |
| `classify_target_status( $article_key )` | Link target status classification |

Resolution uses the article key registry only — never title matching. Results are cached with `wp_cache_*`.

### Link target statuses

| Status | Meaning |
|--------|---------|
| `resolved` | Target exists and is linkable (draft, pending, or publish) |
| `target_missing` | No post with that article key |
| `target_private` | Target is private |
| `unavailable` | Target is future-dated or otherwise unavailable |

## Content Graph Service

`RevIt_Publisher_Content_Graph` builds relationships from:

- `_revit_internal_links`
- `_revit_related_articles`
- `_revit_pillar_article_key`
- Cluster taxonomy assignments
- Vehicle taxonomy assignments

### Key methods

| Method | Returns |
|--------|---------|
| `get_outbound_relationships( $post_id )` | Planned links from this post with resolution status |
| `get_inbound_relationships( $post_id )` | Other posts planning to link here |
| `get_resolved_links( $post_id )` | Outbound links with resolved targets |
| `get_unresolved_links( $post_id )` | Missing, private, or unavailable targets |
| `get_cluster_articles( $post_id )` | Posts in the same cluster |
| `get_vehicle_articles( $post_id )` | Posts for the same vehicle |
| `get_pillar_article( $post_id )` | Pillar post or planned pillar key |
| `get_supporting_articles( $post_id )` | Supporting articles for a pillar |

## Pillar Relationships

Supporting articles store `_revit_pillar_article_key`. When the pillar article is imported later, the relationship resolves automatically without re-import.

If the pillar is not yet imported:

```text
Pillar planned — not imported yet
```

## Cluster Graph

Each `revit_cluster` term provides admin summaries:

- pillar article (resolved or planned)
- supporting article count
- resolved internal links within cluster
- missing planned links
- orphan articles (no resolved inbound links)

## Vehicle Graph

Vehicle grouping uses manufacturer, model, generation, trim, and engine taxonomies plus article type counts (problems, maintenance, modifications, etc.).

This is admin intelligence only — no public vehicle hub pages in Phase 2.

## Orphans

An orphan RevIt article has zero resolved inbound RevIt links. Exceptions:

- `vehicle_hub` and `pillar` article types are excluded from orphan detection.

## Admin UI

**RevIt Publisher → Content Graph** provides tabs for Vehicles, Clusters, Link Opportunities, and Orphans.

## REST Endpoints

| Endpoint | Purpose |
|----------|---------|
| `GET /content-graph/vehicles` | Vehicle summaries |
| `GET /content-graph/clusters` | Cluster summaries |
| `GET /content-graph/orphans` | Orphan articles |
| `GET /content-graph/link-opportunities` | Site-wide link suggestions |
| `GET /link-audit` | Cached audit |
| `POST /link-audit` | Fresh audit |

See [internal-linking.md](./internal-linking.md) for link suggestion and application behavior.
