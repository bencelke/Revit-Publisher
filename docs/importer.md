# Article Importer

Phase 1 turns validated `revit-article-v1` packages into WordPress posts with durable RevIt metadata and automotive taxonomies.

## Workflow

```text
Article JSON
    ↓
POST /article-packages/validate   (optional)
    ↓
POST /article-packages/preview
    ↓
POST /article-packages/import
    ↓
WordPress draft/pending/private post
```

## REST Endpoints

All endpoints require authenticated users with `edit_posts`.

| Endpoint | Method | Purpose |
|----------|--------|---------|
| `/wp-json/revit-publisher/v1/article-packages/validate` | POST | Schema validation only |
| `/wp-json/revit-publisher/v1/article-packages/preview` | POST | Validation + import preview |
| `/wp-json/revit-publisher/v1/article-packages/import` | POST | Validate + create post |
| `/wp-json/revit-publisher/v1/stats` | GET | Dashboard counts |

## Import Success Response

```json
{
  "success": true,
  "status": "created",
  "article_key": "bmw-x3-g01-m40i-coolant-loss",
  "post_id": 123,
  "edit_url": "...",
  "post_status": "draft"
}
```

## Duplicate Article Key

If `article.article_key` already exists:

```json
{
  "success": false,
  "status": "existing_article",
  "article_key": "...",
  "post_id": 123,
  "edit_url": "..."
}
```

HTTP status: **409**

Phase 1 does **not** overwrite existing articles. Future phases will add explicit update support.

## Article Key Registry

- Meta key: `_revit_article_key`
- Managed marker: `_revit_managed = 1`
- Unique across RevIt imported posts
- Checked before post creation

## Post Metadata Stored

| Meta Key | Source |
|----------|--------|
| `_revit_article_key` | `article.article_key` |
| `_revit_schema_version` | `schema_version` |
| `_revit_imported_at` | Import timestamp (ISO 8601 UTC) |
| `_revit_package_hash` | SHA-256 of canonicalized package JSON |
| `_revit_vehicle_*` | Vehicle section fields |
| `_revit_cluster_key` | `cluster.cluster_key` |
| `_revit_pillar_article_key` | `cluster.pillar_article_key` |
| `_revit_internal_links` | Planned inline links (array) |
| `_revit_related_articles` | Related article display graph (array) |
| `_revit_primary_topic` | SEO fields |
| `_revit_secondary_topics` | SEO array |
| `_revit_search_intent` | SEO intent |
| `_revit_seo_title` | SEO title |
| `_revit_meta_description` | Meta description |
| `_revit_canonical` | Canonical instruction |
| `_revit_index` | Index flag |
| `_revit_follow` | Follow flag |
| `_revit_sources` | Source provenance array |
| `_revit_structured_data` | Structured data intent flags |

## Taxonomy Assignments

| Taxonomy | Source |
|----------|--------|
| `revit_manufacturer` | `vehicle.manufacturer` |
| `revit_model` | `vehicle.model` (hierarchical under manufacturer term when possible) |
| `revit_generation` | `vehicle.generation` |
| `revit_trim` | `vehicle.trim` |
| `revit_engine` | `vehicle.engines[]` |
| `revit_article_type` | `article.article_type` |
| `revit_cluster` | `cluster.cluster_key` / `cluster.name` |

Taxonomies are **not publicly queryable** (`public => false`) to avoid unintended archive URLs during Phase 1.

### Deterministic term identity

Because WordPress cannot parent terms across separate taxonomies, vehicle hierarchy uses:

1. Separate taxonomies per level
2. Hierarchical parentage within model/generation/trim taxonomies
3. Term meta `_revit_identity_slug` for deterministic find-or-create
4. Deterministic slugs: `bmw`, `bmw-x3`, `bmw-x3-g01`, `bmw-x3-g01-m40i`, `b58`

### Cluster term metadata

Cluster taxonomy terms store:

- `_revit_cluster_key`
- `_revit_pillar_article_key` (may reference not-yet-imported articles)
- `_revit_parent_cluster_key`

## Content Rendering

Structured `content` blocks translate to native Gutenberg block markup:

- `core/heading`, `core/paragraph`, `core/list`, `core/table`, `core/quote`
- Callouts use a styled `core/group` composition
- Image placeholders render `[RevIt image needed: ...]` text
- FAQ section appends H2 + H3/paragraph pairs

No internal hyperlinks are inserted in Phase 1.

## Publishing Defense in Depth

Schema allows: `draft`, `pending`, `private`

Importer additionally rejects `publish` even if schema validation is bypassed.

Imported packages **never** become publicly published automatically.

## Failure Behavior

Import flow:

1. Validate package
2. Check duplicate article key
3. Create post via `wp_insert_post()`
4. Store metadata
5. Sync vehicle taxonomies
6. Sync cluster taxonomy
7. Register article key

If post creation succeeds but a required later step fails:

- Post is deleted (`wp_delete_post(..., true)`)
- Error returned to caller
- No silent partial success

## Package Hash

SHA-256 hash of recursively key-sorted JSON representation.

Used for:

- Import traceability
- Future update comparison
- Duplicate package detection aid

Not a replacement for `article_key`.

## Admin UI

**RevIt Publisher → Import**

1. Paste JSON or choose `.json` file (max 5 MB, client-side read)
2. Validate & Preview
3. Import as Draft / Import Article

After success: Post ID, Edit Article link, Import Another.

## Post Editor Panel

Imported posts show read-only RevIt Publisher sidebar with article key, vehicle, cluster, SEO topic, planned links count, etc.

## Posts List Columns

For RevIt-managed posts only:

- Vehicle
- RevIt Type
- Cluster
- Primary Topic
