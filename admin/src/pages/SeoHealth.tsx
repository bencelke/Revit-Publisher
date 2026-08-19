import { useEffect, useState } from 'react';
import { fetchTopicOverlaps } from '../api/intelligence';

interface OverlapRow {
  post_id_a: number;
  post_id_b: number;
  title_a: string;
  title_b: string;
  topic_a: string;
  topic_b: string;
  overlap_pct: number;
  same_vehicle: boolean;
  same_intent: boolean;
  same_type: boolean;
  same_cluster: boolean;
  risk: string;
  classification: string;
}

export function SeoHealthPage() {
  const [overlaps, setOverlaps] = useState<OverlapRow[]>([]);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    fetchTopicOverlaps()
      .then((rows) => setOverlaps(rows as OverlapRow[]))
      .catch((err: unknown) => setError(err instanceof Error ? err.message : 'Failed to load overlaps.'));
  }, []);

  return (
    <div className="revit-publisher-panel revit-publisher-dark">
      <h1>SEO Health</h1>
      <p className="revit-publisher-muted">Topic overlap analysis and content quality signals.</p>
      <p className="revit-publisher-disclaimer">RevIt SEO Health is an internal site-quality metric and is not a Google ranking score.</p>

      {error && <div className="revit-publisher-result revit-publisher-result--error"><p>{error}</p></div>}

      <div className="revit-publisher-card">
        <h2>Potential Topic Overlap</h2>
        {overlaps.length === 0 && <p>No significant overlaps detected.</p>}
        {overlaps.map((row) => (
          <div key={`${row.post_id_a}-${row.post_id_b}`} className="revit-publisher-list-item">
            <strong>{row.title_a}</strong>
            <div>vs {row.title_b}</div>
            <div>Overlap: {row.overlap_pct}% · Risk: {row.risk} · Vehicle: {row.same_vehicle ? 'Same' : 'Different'}</div>
            <div>Intent: {row.same_intent ? 'Same' : 'Different'} · Type: {row.same_type ? 'Same' : 'Different'} · Cluster: {row.same_cluster ? 'Same' : 'Different'}</div>
          </div>
        ))}
      </div>
    </div>
  );
}
