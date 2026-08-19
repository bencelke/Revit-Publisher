# Topic Intelligence

Phase 3 adds deterministic topic overlap detection — no embeddings or external AI.

## Topic Fingerprinting

`RevIt_Publisher_Topic_Fingerprint`:

- lowercase normalization
- punctuation removal
- stopword reduction
- simple singular/plural normalization
- Jaccard token similarity

## Classification

| Level | Criteria |
|-------|----------|
| `exact` | Normalized primary topic match |
| `high_overlap` | Jaccard ≥ 0.7 |
| `moderate_overlap` | Jaccard ≥ 0.4 |
| `distinct` | Below threshold |

## Risk Weighting

Potential overlap severity considers:

- same vehicle (stronger)
- same cluster (stronger)
- same search intent
- same article type
- same article type + cluster combination

Pillar vs supporting, or maintenance vs problem, lowers concern.

Risk levels: `low`, `medium`, `high`.

RevIt flags **Potential Topic Overlap** — not automatic cannibalization verdicts.

## Limitations

- No semantic embeddings
- No fuzzy AI similarity
- No SERP or search volume data
- Analysis cached for admin performance; refreshed on import/update

## Admin

**RevIt Publisher → SEO Health → Topic Overlap**

REST: `GET /topic-overlaps?risk=high`
