import { useEffect, useState } from 'react';
import {
  applyLink,
  fetchClusters,
  fetchLinkOpportunities,
  fetchOrphans,
  fetchVehicles,
  runLinkAudit,
} from '../api/graph';
import {
  ClusterSummary,
  LinkOpportunity,
  OrphanEntry,
  VehicleSummary,
} from '../types/article-package';

type Tab = 'vehicles' | 'clusters' | 'opportunities' | 'orphans';

export function ContentGraphPage() {
  const [tab, setTab] = useState<Tab>('vehicles');
  const [vehicles, setVehicles] = useState<VehicleSummary[]>([]);
  const [clusters, setClusters] = useState<ClusterSummary[]>([]);
  const [opportunities, setOpportunities] = useState<LinkOpportunity[]>([]);
  const [orphans, setOrphans] = useState<OrphanEntry[]>([]);
  const [error, setError] = useState<string | null>(null);
  const [loading, setLoading] = useState(false);

  async function loadTab(next: Tab) {
    setTab(next);
    setLoading(true);
    setError(null);
    try {
      if (next === 'vehicles') setVehicles(await fetchVehicles());
      if (next === 'clusters') setClusters(await fetchClusters());
      if (next === 'opportunities') setOpportunities(await fetchLinkOpportunities());
      if (next === 'orphans') setOrphans(await fetchOrphans());
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Failed to load data.');
    } finally {
      setLoading(false);
    }
  }

  useEffect(() => {
    loadTab('vehicles');
  }, []);

  async function handleApply(opp: LinkOpportunity) {
    const postId = opp.source_post_id;
    if (!postId) return;
    try {
      const result = await applyLink(postId, opp);
      if (!result.success) {
        setError(result.message ?? 'Failed to apply link.');
        return;
      }
      await runLinkAudit();
      await loadTab('opportunities');
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Failed to apply link.');
    }
  }

  return (
    <div className="revit-publisher-panel">
      <h1>Content Graph</h1>
      <p className="revit-publisher-muted">Vehicle groupings, clusters, link opportunities, and orphans.</p>

      <div className="revit-publisher-tabs">
        {(['vehicles', 'clusters', 'opportunities', 'orphans'] as Tab[]).map((key) => (
          <button
            key={key}
            type="button"
            className={tab === key ? 'is-active' : ''}
            onClick={() => loadTab(key)}
          >
            {key.charAt(0).toUpperCase() + key.slice(1)}
          </button>
        ))}
        <button type="button" onClick={() => runLinkAudit()}>Run Link Audit</button>
      </div>

      {error && <div className="revit-publisher-result revit-publisher-result--error"><p>{error}</p></div>}
      {loading && <p className="revit-publisher-muted">Loading…</p>}

      {tab === 'vehicles' && (
        <div className="revit-publisher-card">
          {vehicles.map((v) => (
            <div key={v.label} className="revit-publisher-list-item">
              <strong>{v.label}</strong>
              <div>{v.articles} articles · {v.clusters.length} clusters · {v.unresolved_links} unresolved links</div>
            </div>
          ))}
        </div>
      )}

      {tab === 'clusters' && (
        <div className="revit-publisher-card">
          {clusters.map((c) => (
            <div key={c.cluster_key} className="revit-publisher-list-item">
              <strong>{c.name}</strong>
              <div>{c.article_count} articles · {c.resolved_links} resolved · {c.missing_links} missing links</div>
            </div>
          ))}
        </div>
      )}

      {tab === 'opportunities' && (
        <div className="revit-publisher-card">
          {opportunities.map((opp, index) => (
            <div key={`${opp.source_post_id}-${opp.target_post_id}-${index}`} className="revit-publisher-list-item">
              <div><strong>{opp.source_title}</strong> → {opp.target_title}</div>
              <div>Suggested anchor: “{opp.anchor}” ({opp.paragraph_label})</div>
              <button type="button" onClick={() => handleApply(opp)}>Apply</button>
            </div>
          ))}
          {opportunities.length === 0 && !loading && <p>No link opportunities found.</p>}
        </div>
      )}

      {tab === 'orphans' && (
        <div className="revit-publisher-card">
          {orphans.map((o) => (
            <div key={o.post_id} className="revit-publisher-list-item">
              <strong>{o.title}</strong> (Post {o.post_id})
            </div>
          ))}
          {orphans.length === 0 && !loading && <p>No orphan articles detected.</p>}
        </div>
      )}
    </div>
  );
}
