import { useEffect, useState } from 'react';
import { applyConsolidation, previewConsolidation, recordOverlapDecision } from '../api/operations';
import { fetchTopicOverlaps } from '../api/intelligence';

interface OverlapRow {
  post_id_a: number;
  post_id_b: number;
  title_a: string;
  title_b: string;
  overlap_pct: number;
  same_vehicle: boolean;
  same_intent: boolean;
  same_type: boolean;
  same_cluster: boolean;
  risk: string;
}

export function SeoHealthPage() {
  const [overlaps, setOverlaps] = useState<OverlapRow[]>([]);
  const [error, setError] = useState<string | null>(null);
  const [preview, setPreview] = useState<unknown>(null);

  useEffect(() => {
    fetchTopicOverlaps()
      .then((rows) => setOverlaps(rows as OverlapRow[]))
      .catch((err: unknown) => setError(err instanceof Error ? err.message : 'Failed to load overlaps.'));
  }, []);

  async function handleDecision(row: OverlapRow, decision: string) {
    if (decision.startsWith('merge_into_')) {
      const dest = decision === 'merge_into_a' ? row.post_id_a : row.post_id_b;
      const source = decision === 'merge_into_a' ? row.post_id_b : row.post_id_a;
      const p = await previewConsolidation(source, dest);
      setPreview(p);
      return;
    }
    await recordOverlapDecision(row.post_id_a, row.post_id_b, decision);
  }

  async function confirmMerge(sourceId: number, destId: number) {
    await applyConsolidation(sourceId, destId, 'draft');
    setPreview(null);
  }

  return (
    <div className="revit-publisher-panel revit-publisher-dark">
      <h1>SEO Health</h1>
      <p className="revit-publisher-disclaimer">RevIt SEO Health is an internal site-quality metric and is not a Google ranking score.</p>
      {error && <div className="revit-publisher-result revit-publisher-result--error"><p>{error}</p></div>}

      <div className="revit-publisher-card">
        <h2>Potential Topic Overlap</h2>
        {overlaps.map((row) => (
          <div key={`${row.post_id_a}-${row.post_id_b}`} className="revit-publisher-list-item">
            <strong>{row.title_a}</strong> vs {row.title_b}
            <div>Overlap: {row.overlap_pct}% · Risk: {row.risk}</div>
            <div className="revit-publisher-actions">
              <button type="button" onClick={() => handleDecision(row, 'keep_both')}>Keep Both</button>
              <button type="button" onClick={() => handleDecision(row, 'different_intent')}>Mark Different Intent</button>
              <button type="button" onClick={() => handleDecision(row, 'merge_into_a')}>Merge Into A</button>
              <button type="button" onClick={() => handleDecision(row, 'merge_into_b')}>Merge Into B</button>
              <button type="button" onClick={() => handleDecision(row, 'ignore')}>Ignore</button>
            </div>
          </div>
        ))}
      </div>

      {preview !== null && (
        <div className="revit-publisher-card">
          <h3>Consolidation Preview</h3>
          <pre>{JSON.stringify(preview, null, 2)}</pre>
          <button type="button" onClick={() => {
            const p = preview as { source_post_id: number; destination_post_id: number };
            confirmMerge(p.source_post_id, p.destination_post_id);
          }}>Confirm Consolidation</button>
        </div>
      )}
    </div>
  );
}
