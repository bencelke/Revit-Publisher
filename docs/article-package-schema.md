# `revit-article-v1` Article Package Schema

`revit-article-v1` is the **contract between ChatGPT/editorial generation and RevIt Publisher**.

Every imported article must explicitly declare its identity, vehicle context, cluster membership, SEO intent, structured content, planned links, sources, and publishing controls. The plugin must not guess any of these values.

Schema file: `schemas/revit-article-v1.schema.json`

Example valid package: `examples/article-valid.json`

---

## Top-Level Structure

| Field | Required | Description |
|-------|----------|-------------|
| `schema_version` | Yes | Must be exactly `revit-article-v1` |
| `article` | Yes | Article identity and editorial metadata |
| `vehicle` | Yes | Automotive context |
| `cluster` | Yes | Content cluster membership |
| `seo` | Yes | Search and indexing metadata |
| `content` | Yes | Structured article body |
| `internal_links` | Yes | Planned inline link relationships |
| `related_articles` | Yes | Display/list relationships |
| `sources` | Yes | Editorial source references |
| `structured_data` | Yes | Future schema generation flags |
| `publishing` | Yes | WordPress publishing controls |

Unknown top-level fields are rejected.

---

## `schema_version`

Fixed identifier for the contract version.

```json
"schema_version": "revit-article-v1"
```

Future breaking changes will use `revit-article-v2`, etc.

---

## `article`

Defines what the article is.

| Field | Type | Description |
|-------|------|-------------|
| `article_key` | string | **Stable unique external ID.** Lowercase slug format (`a-z`, `0-9`, hyphens). Used across imports, links, and updates. Never change once published. |
| `title` | string | Human-readable article title |
| `slug` | string | Intended WordPress post slug |
| `article_type` | enum | Editorial classification (see below) |
| `summary` | string | Longer editorial summary |
| `excerpt` | string | Short excerpt for listings/cards |

### Article types

| Value | Typical use |
|-------|-------------|
| `vehicle_hub` | Top-level vehicle landing content |
| `pillar` | Cluster pillar/guide article |
| `problem` | Diagnostic/repair problem article |
| `maintenance` | Service/maintenance guide |
| `modification` | Mod/upgrade content |
| `product` | Product-focused article |
| `fitment` | Compatibility/fitment guide |
| `buying` | Purchase/buyer's guide |
| `reliability` | Reliability/ownership analysis |
| `comparison` | Model/trim/product comparison |
| `guide` | General how-to guide |
| `faq` | FAQ-focused article |
| `other` | Fallback when none apply |

---

## `vehicle`

Automotive identity for taxonomy and hub organization. Fields may be `null` when not applicable.

| Field | Type | Description |
|-------|------|-------------|
| `manufacturer` | string \| null | e.g. `BMW` |
| `model` | string \| null | e.g. `X3` |
| `generation` | string \| null | e.g. `G01` |
| `trim` | string \| null | e.g. `M40i` |
| `start_year` | integer \| null | Model year range start (1900–2100) |
| `end_year` | integer \| null | Model year range end; must be ≥ `start_year` when both set |
| `engines` | string[] | Engine codes, e.g. `["B58"]` |

Articles are not forced to a single model year. Ranges support generation-level content.

---

## `cluster`

Defines content cluster membership and hierarchy.

| Field | Type | Description |
|-------|------|-------------|
| `cluster_key` | string | Stable cluster identifier |
| `name` | string | Human-readable cluster name |
| `pillar_article_key` | string \| null | Pillar article for this cluster |
| `parent_cluster_key` | string \| null | Parent cluster for hierarchy |

Future Publisher behavior:

- article → cluster
- cluster → pillar
- pillar → supporting articles
- nested cluster trees via `parent_cluster_key`

---

## `seo`

Search intent and indexing metadata. No keyword density or scoring in V1.

| Field | Type | Description |
|-------|------|-------------|
| `primary_topic` | string | Main topic phrase |
| `secondary_topics` | string[] | Supporting topic phrases |
| `search_intent` | enum | `informational`, `commercial`, `transactional`, `navigational`, `mixed` |
| `seo_title` | string | Title tag (max 70 chars in schema) |
| `meta_description` | string | Meta description |
| `canonical` | `"auto"` \| URL | Canonical URL or auto-generate |
| `index` | boolean | Allow indexing |
| `follow` | boolean | Allow following links |

---

## `content`

Structured content — **not** a single HTML blob. The future importer translates blocks into WordPress/Gutenberg content.

| Field | Type | Description |
|-------|------|-------------|
| `intro` | string | Opening paragraph(s) |
| `blocks` | array | Ordered content blocks |
| `faq` | array | FAQ items with `question` and `answer` |

### Block types (V1)

| Type | Required fields |
|------|-----------------|
| `heading` | `level` (2–4), `text` |
| `paragraph` | `text` |
| `bullet_list` | `items[]` |
| `numbered_list` | `items[]` |
| `table` | `headers[]`, `rows[][]` |
| `callout` | `variant` (`info`, `warning`, `tip`, `note`), `text` |
| `quote` | `text`; optional `attribution` |
| `image_placeholder` | `alt`, `caption`; optional `suggested_filename` |

---

## `internal_links`

Planned inline links resolved by `target_article_key`. One of the most important schema sections.

| Field | Type | Description |
|-------|------|-------------|
| `target_article_key` | string | Destination article key |
| `preferred_anchor` | string | Preferred anchor text |
| `relationship` | enum | Link semantic (see below) |
| `required` | boolean | Whether link insertion is mandatory |

### Relationship values

`parent`, `child`, `pillar`, `supporting`, `related_problem`, `related_maintenance`, `related_modification`, `related_product`, `related_vehicle`, `contextual`

Future behavior: resolve keys to WordPress URLs and insert/manage links.

---

## `related_articles`

Display relationships separate from inline internal links. Powers related cards, hub lists, and cluster navigation.

| Field | Type | Description |
|-------|------|-------------|
| `article_key` | string | Related article key |
| `relationship` | enum | Same values as internal links |
| `priority` | integer | Sort priority (0–100, lower = higher priority) |

---

## `sources`

Editorial provenance. **Do not include copied full source content.**

| Field | Type | Description |
|-------|------|-------------|
| `source_name` | string | Publisher or site name |
| `title` | string | Source document title |
| `url` | string (URI) | Source URL |
| `source_type` | enum | `technical_reference`, `oem_documentation`, `forum`, `review`, `news`, `video`, `other` |
| `purpose` | string | Why this source was used |

Some sources may remain private in future rendering strategies.

---

## `structured_data`

Conservative instruction flags for future JSON-LD generation.

| Field | Type | Description |
|-------|------|-------------|
| `article` | boolean | Output Article schema |
| `breadcrumbs` | boolean | Output breadcrumb schema |
| `faq` | boolean | Output FAQ schema when FAQ content exists |

---

## `publishing`

WordPress publishing controls for import.

| Field | Type | Description |
|-------|------|-------------|
| `status` | enum | **`draft`**, **`pending`**, or **`private` only** — `publish` is rejected in V1 |
| `author` | integer \| null | WordPress user ID |
| `featured_image_id` | integer \| null | WordPress attachment ID |
| `allow_comments` | boolean | Comment status |

Human review is required before public publication.

---

## Validation

Packages are validated via:

1. **JSON Schema** — structure, types, enums, formats
2. **Business rules** — e.g. `end_year >= start_year`
3. **REST endpoint** — `POST /wp-json/revit-publisher/v1/article-packages/validate`

Invalid packages return normalized errors:

```json
{
  "valid": false,
  "errors": [
    { "path": "seo.meta_description", "message": "..." }
  ]
}
```

Valid packages return:

```json
{
  "valid": true,
  "schema_version": "revit-article-v1",
  "article_key": "bmw-x3-g01-m40i-coolant-loss",
  "warnings": []
}
```

---

## ChatGPT / Editorial Handoff Checklist

When generating a package, ensure:

1. `schema_version` is `revit-article-v1`
2. `article_key` is stable and unique
3. Vehicle fields match the target vehicle
4. Cluster and pillar keys are consistent with the content plan
5. Internal links use valid `article_key` references
6. Sources include URLs but not copied content
7. `publishing.status` is `draft`, `pending`, or `private`
