# Internal Linking

Phase 2 adds a safe internal linking engine with operator approval — no automatic bulk rewriting.

## Modes

| Mode | Behavior |
|------|----------|
| **Suggest Only** (default) | Reports link opportunities; operator applies individually |
| **Apply Approved Links** | Operator explicitly applies selected suggestions via REST or admin UI |

Articles are never automatically modified on import. Published content is never rewritten without explicit user action.

## Planned Links

Phase 1 stores planned links in `_revit_internal_links`:

```json
[
  {
    "target_article_key": "bmw-x3-g01-m40i-water-pump",
    "preferred_anchor": "BMW X3 M40i water pump failure",
    "relationship": "related_problem",
    "required": false
  }
]
```

Each link is classified as resolved, target_missing, target_private, or unavailable.

## Suggestion Engine

`RevIt_Publisher_Internal_Link_Service::get_suggestions()`:

1. Reads outbound planned links with resolved targets
2. Skips targets already linked in content or previously applied
3. Locates natural anchor text occurrence in eligible Gutenberg blocks
4. Returns suggestions with block index and paragraph label

Settings control max suggestions per article (default: 5) and duplicate target avoidance (default: yes).

## Anchor Matching

The preferred anchor is used only when a natural, unlinked occurrence exists in paragraph or list blocks.

Safety rules:

- Never modify headings
- Never modify existing links
- Never modify code or preformatted blocks
- One contextual link per target by default
- Do not invent anchor text if no reasonable occurrence exists

## Safe Gutenberg Modification

Link insertion uses WordPress block APIs:

1. `parse_blocks()` on post content
2. Inspect eligible `core/paragraph` and `core/list` blocks
3. Wrap first case-insensitive anchor occurrence in `<a href="...">`
4. `serialize_blocks()` to preserve block validity

No blind regex over full `post_content`.

## Applying Links

`POST /wp-json/revit-publisher/v1/posts/{id}/apply-link`

Requires `edit_post` capability. Target must resolve to a RevIt-managed post — no arbitrary URL injection.

Applied links are recorded in `_revit_applied_links` post meta.

## Backlink Opportunities

When article B is imported, `get_backlink_opportunities( $post_id )` finds existing articles that planned links to B before it existed.

Example admin message:

```text
New backlink opportunities

3 existing articles can now link to this article.
```

Operators resolve via **Resolve Opportunities** or per-item Apply actions.

## Link Audit

`RevIt_Publisher_Link_Audit_Service::audit_all_links()` scans all RevIt-managed posts and returns:

- total planned relationships
- resolved / unresolved / broken counts
- orphan posts
- newly available backlink opportunities

Manual audit only in Phase 2 — no scheduled background jobs.

## Example Workflow

1. Import supporting article (coolant loss) — pillar link shows as planned
2. Import pillar article — pillar relationship resolves
3. Import water pump article — backlink opportunities appear for coolant loss
4. Review link suggestions in editor panel or Content Graph
5. Apply approved contextual link
6. Verify Gutenberg content remains valid

See `examples/graph/` for related test packages.
