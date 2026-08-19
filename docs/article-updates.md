# Article Updates

Phase 3 implements full update support for existing `article_key` posts.

## Hash Comparison

Each import stores `_revit_package_hash`. On update preview:

| Result | Meaning |
|--------|---------|
| `unchanged` | Incoming package identical to stored hash |
| `changed` | Differences detected — review required |

## Update Modes

| Mode | Updates |
|------|---------|
| `full` | Title, excerpt, content, SEO, relationships, sources, taxonomies |
| `seo` | SEO title, meta description, topics, canonical, index/follow |
| `relationships` | Internal links, related articles, pillar/cluster meta |

Updates require operator approval via REST or Import UI. Published posts are never auto-updated.

## Diff Preview

`RevIt_Publisher_Article_Update_Service` returns field-level diffs for article, SEO, content blocks, vehicle, cluster, relationships, and sources.

## Manual Edit Protection

`_revit_last_import_content_hash` stores a SHA-256 hash of content at last import.

If current WordPress content differs, update preview shows:

```text
Manual edits detected — choose update mode carefully.
```

Full updates do not silently overwrite manual editorial changes without operator action.

## WordPress Revisions

Before applying content/SEO updates, WordPress revisions are preserved via `wp_save_post_revision()`.

## Safety Rules

- WordPress post ID preserved
- `article_key` unchanged
- Publish status never set by import/update
- Featured image and author preserved unless package explicitly sets them
- Duplicate import still returns `existing_article` — use update endpoints instead

## REST API

```text
POST /article-packages/update-preview
POST /article-packages/update
```

Requires `edit_post` on target post. Payload includes `post_id`, `mode`, and full package JSON.
