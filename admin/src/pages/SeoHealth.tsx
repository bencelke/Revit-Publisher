import { useEffect, useState } from 'react';
import { applyConsolidation, previewConsolidation, recordOverlapDecision } from '../api/operations';
import { fetchSerpPreview, fetchSitemapHealth } from '../api/sitemap-health';
import { fetchVehicleHubs } from '../api/vehicle-hubs';
import { fetchTopicOverlaps } from '../api/intelligence';
import { SerpPreviewResponse, SitemapHealthCounts } from '../types/public-seo';

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

type SeoTab = 'overlaps' | 'sitemap' | 'preview';

export function SeoHealthPage() {
  const [tab, setTab] = useState<SeoTab>('overlaps');
  const [overlaps, setOverlaps] = useState<OverlapRow[]>([]);
  const [sitemap, setSitemap] = useState<SitemapHealthCounts | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [preview, setPreview] = useState<unknown>(null);
  const [loading, setLoading] = useState(false);

  const [previewPostId, setPreviewPostId] = useState('');
  const [previewType, setPreviewType] = useState<'article' | 'hub'>('article');
  const [serpPreview, setSerpPreview] = useState<SerpPreviewResponse | null>(null);
  const [hubOptions, setHubOptions] = useState<Array<{ hub_id: number; title: string }>>([]);

  useEffect(() => {
    fetchTopicOverlaps()
      .then((rows) => setOverlaps(rows as OverlapRow[]))
      .catch((err: unknown) => setError(err instanceof Error ? err.message : 'Failed to load overlaps.'));
    fetchVehicleHubs()
      .then((hubs) => setHubOptions(hubs.map((h) => ({ hub_id: h.hub_id, title: h.title }))))
      .catch(() => setHubOptions([]));
  }, []);

  async function loadTab(next: SeoTab) {
    setTab(next);
    setError(null);
    if (next === 'sitemap' && !sitemap) {
      setLoading(true);
      try {
        setSitemap(await fetchSitemapHealth());
      } catch (err) {
        setError(err instanceof Error ? err.message : 'Failed to load sitemap health.');
      } finally {
        setLoading(false);
      }
    }
  }

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

  async function loadSerpPreview() {
    const id = Number(previewPostId);
    if (!id) return;
    setLoading(true);
    setError(null);
    try {
      setSerpPreview(await fetchSerpPreview(id, previewType));
    } catch (err) {
      setSerpPreview(null);
      setError(err instanceof Error ? err.message : 'Failed to load search preview.');
    } finally {
      setLoading(false);
    }
  }

  return (
    <div className="revit-publisher-panel revit-publisher-dark">
      <h1>SEO Health</h1>
      <p className="revit-publisher-disclaimer">
        RevIt SEO Health is an internal site-quality metric and is not a Google ranking score.
      </p>

      <div className="revit-publisher-tabs">
        {(['overlaps', 'sitemap', 'preview'] as SeoTab[]).map((key) => (
          <button
            key={key}
            type="button"
            className={tab === key ? 'is-active' : ''}
            onClick={() => loadTab(key)}
          >
            {key === 'overlaps' ? 'Topic Overlap' : key === 'sitemap' ? 'Sitemap' : 'Preview'}
          </button>
        ))}
      </div>

      {error && (
        <div className="revit-publisher-result revit-publisher-result--error">
          <p>{error}</p>
        </div>
      )}
      {loading && tab !== 'overlaps' && <p className="revit-publisher-muted">Loading…</p>}

      {tab === 'overlaps' && (
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
          {overlaps.length === 0 && <p className="revit-publisher-muted">No topic overlaps detected.</p>}
        </div>
      )}

      {tab === 'sitemap' && sitemap && (
        <div className="revit-publisher-card">
          <h2>Sitemap Health</h2>
          <h3>Indexable Content</h3>
          <ul className="revit-publisher-stats">
            <li>
              <span className="revit-publisher-stat-label">Vehicle Hubs</span>
              <span className="revit-publisher-stat-value">{sitemap.indexable.vehicle_hubs}</span>
            </li>
            <li>
              <span className="revit-publisher-stat-label">Articles</span>
              <span className="revit-publisher-stat-value">{sitemap.indexable.articles}</span>
            </li>
          </ul>

          <h3>Excluded</h3>
          <ul className="revit-publisher-stats">
            <li>
              <span className="revit-publisher-stat-label">Drafts</span>
              <span className="revit-publisher-stat-value">{sitemap.excluded.drafts}</span>
            </li>
            <li>
              <span className="revit-publisher-stat-label">Noindex</span>
              <span className="revit-publisher-stat-value">{sitemap.excluded.noindex}</span>
            </li>
            <li>
              <span className="revit-publisher-stat-label">Operational CPTs</span>
              <span className="revit-publisher-stat-value">{sitemap.excluded.operational_cpts}</span>
            </li>
          </ul>

          {sitemap.audit_signals && sitemap.audit_signals.length > 0 && (
            <>
              <h3>Integrity Signals</h3>
              {sitemap.audit_signals.map((signal) => (
                <div key={signal.code} className="revit-publisher-list-item">
                  <span className={`revit-severity--${signal.severity}`}>{signal.severity}</span>
                  {' '}{signal.message}
                </div>
              ))}
            </>
          )}
        </div>
      )}

      {tab === 'preview' && (
        <div className="revit-publisher-card">
          <h2>Search Preview</h2>
          <p className="revit-publisher-disclaimer">
            Approximate search snippet preview — not a guarantee of Google display.
          </p>

          <div className="revit-publisher-actions">
            <label>
              Type
              <select
                value={previewType}
                onChange={(e) => setPreviewType(e.target.value as 'article' | 'hub')}
              >
                <option value="article">Article</option>
                <option value="hub">Vehicle Hub</option>
              </select>
            </label>
            {previewType === 'hub' && hubOptions.length > 0 ? (
              <label>
                Hub
                <select
                  value={previewPostId}
                  onChange={(e) => setPreviewPostId(e.target.value)}
                >
                  <option value="">Select hub…</option>
                  {hubOptions.map((hub) => (
                    <option key={hub.hub_id} value={hub.hub_id}>{hub.title}</option>
                  ))}
                </select>
              </label>
            ) : (
              <label>
                Post ID
                <input
                  type="number"
                  min={1}
                  value={previewPostId}
                  onChange={(e) => setPreviewPostId(e.target.value)}
                  placeholder="Post ID"
                />
              </label>
            )}
            <button type="button" onClick={loadSerpPreview} disabled={!previewPostId}>
              Load Preview
            </button>
          </div>

          {serpPreview && (
            <div className="revit-publisher-serp-preview">
              <div className="revit-publisher-serp-title">{serpPreview.title}</div>
              <div className="revit-publisher-serp-url">{serpPreview.url}</div>
              <div className="revit-publisher-serp-desc">{serpPreview.description}</div>
              {serpPreview.indexable === false && (
                <p className="revit-publisher-muted">Not indexable (noindex or unpublished).</p>
              )}
            </div>
          )}

          {serpPreview && serpPreview.warnings.length > 0 && (
            <>
              <h3>Validation Warnings</h3>
              {serpPreview.warnings.map((w) => (
                <div key={w.code} className="revit-publisher-list-item">
                  <span className={`revit-severity--${w.severity ?? 'medium'}`}>{w.code}</span>
                  {' '}{w.message}
                </div>
              ))}
            </>
          )}
        </div>
      )}

      {preview !== null && (
        <div className="revit-publisher-card">
          <h3>Consolidation Preview</h3>
          <pre>{JSON.stringify(preview, null, 2)}</pre>
          <button
            type="button"
            onClick={() => {
              const p = preview as { source_post_id: number; destination_post_id: number };
              confirmMerge(p.source_post_id, p.destination_post_id);
            }}
          >
            Confirm Consolidation
          </button>
        </div>
      )}
    </div>
  );
}
