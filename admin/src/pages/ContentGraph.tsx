import { useEffect, useState } from 'react';
import {
  applyClusterLinks,
  fetchClusterLinks,
  undoLink,
} from '../api/operations';
import {
  applyLink,
  fetchClusterLinkMatrix,
  fetchClusters,
  fetchLinkOpportunities,
  fetchOrphans,
  fetchVehicles,
  runLinkAudit,
} from '../api/graph';
import { applyBatchLinks } from '../api/intelligence';
import {
  ClusterSummary,
  LinkOpportunity,
  OrphanEntry,
  VehicleSummary,
} from '../types/article-package';
import {
  ClusterLinkMatrix,
  LinkMatrixAppliedLink,
  LinkMatrixSuggestion,
} from '../types/public-seo';

type Tab = 'vehicles' | 'clusters' | 'opportunities' | 'orphans';

function suggestionKey(s: LinkMatrixSuggestion): string {
  return `${s.source_post_id}-${s.target_post_id}`;
}

export function ContentGraphPage() {
  const [tab, setTab] = useState<Tab>('vehicles');
  const [vehicles, setVehicles] = useState<VehicleSummary[]>([]);
  const [clusters, setClusters] = useState<ClusterSummary[]>([]);
  const [opportunities, setOpportunities] = useState<LinkOpportunity[]>([]);
  const [orphans, setOrphans] = useState<OrphanEntry[]>([]);
  const [error, setError] = useState<string | null>(null);
  const [loading, setLoading] = useState(false);
  const [selected, setSelected] = useState<number[]>([]);

  const [activeCluster, setActiveCluster] = useState<string | null>(null);
  const [linkMatrix, setLinkMatrix] = useState<ClusterLinkMatrix | null>(null);
  const [matrixSelected, setMatrixSelected] = useState<string[]>([]);
  const [matrixLoading, setMatrixLoading] = useState(false);

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

  async function loadClusterMatrix(clusterKey: string) {
    setActiveCluster(clusterKey);
    setMatrixLoading(true);
    setError(null);
    setMatrixSelected([]);
    try {
      setLinkMatrix(await fetchClusterLinkMatrix(clusterKey));
    } catch (err) {
      setLinkMatrix(null);
      setError(err instanceof Error ? err.message : 'Failed to load link matrix.');
    } finally {
      setMatrixLoading(false);
    }
  }

  async function handleBatchApply() {
    const batch = opportunities.filter((_, index) => selected.includes(index));
    if (batch.length === 0) return;
    try {
      await applyBatchLinks(batch);
      setSelected([]);
      await loadTab('opportunities');
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Batch apply failed.');
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

  async function handleMatrixApplySelected() {
    if (!linkMatrix || matrixSelected.length === 0) return;
    const suggestions = linkMatrix.suggestions.filter((s) =>
      matrixSelected.includes(suggestionKey(s)),
    );
    try {
      await applyClusterLinks(suggestions);
      setMatrixSelected([]);
      if (activeCluster) await loadClusterMatrix(activeCluster);
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Failed to apply selected links.');
    }
  }

  async function handleSuggestMissing() {
    if (!activeCluster) return;
    setMatrixLoading(true);
    try {
      const data = await fetchClusterLinks(activeCluster) as {
        suggestions: LinkMatrixSuggestion[];
      };
      if (linkMatrix) {
        setLinkMatrix({ ...linkMatrix, suggestions: data.suggestions ?? [] });
      }
      const missingKeys = (data.suggestions ?? []).map(suggestionKey);
      setMatrixSelected(missingKeys);
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Failed to load suggestions.');
    } finally {
      setMatrixLoading(false);
    }
  }

  async function handleUndoApplied(entry: LinkMatrixAppliedLink) {
    try {
      await undoLink(entry.log_id);
      if (activeCluster) await loadClusterMatrix(activeCluster);
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Failed to undo link.');
    }
  }

  function toggleMatrixSuggestion(s: LinkMatrixSuggestion) {
    const key = suggestionKey(s);
    setMatrixSelected((prev) =>
      prev.includes(key) ? prev.filter((k) => k !== key) : [...prev, key],
    );
  }

  function isLinked(sourceId: number, targetId: number): boolean {
    if (!linkMatrix) return false;
    return Boolean(linkMatrix.matrix[String(sourceId)]?.[String(targetId)]);
  }

  function hasSuggestion(sourceId: number, targetId: number): boolean {
    if (!linkMatrix) return false;
    return linkMatrix.suggestions.some(
      (s) => s.source_post_id === sourceId && s.target_post_id === targetId,
    );
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
        <>
          <div className="revit-publisher-card">
            {clusters.map((c) => (
              <div key={c.cluster_key} className="revit-publisher-list-item">
                <button
                  type="button"
                  className={activeCluster === c.cluster_key ? 'is-active' : ''}
                  onClick={() => loadClusterMatrix(c.cluster_key)}
                >
                  <strong>{c.name}</strong>
                </button>
                <div>{c.article_count} articles · {c.resolved_links} resolved · {c.missing_links} missing links</div>
              </div>
            ))}
          </div>

          {activeCluster && (
            <div className="revit-publisher-card">
              <h2>{linkMatrix?.name ?? activeCluster} — Link Matrix</h2>
              {matrixLoading && <p className="revit-publisher-muted">Loading matrix…</p>}

              {linkMatrix && linkMatrix.articles.length > 0 && (
                <>
                  <div className="revit-publisher-actions">
                    <button
                      type="button"
                      disabled={matrixSelected.length === 0}
                      onClick={handleMatrixApplySelected}
                    >
                      Apply Selected ({matrixSelected.length})
                    </button>
                    <button type="button" onClick={handleSuggestMissing}>Suggest Missing</button>
                  </div>

                  <div className="revit-publisher-table-wrap">
                    <table className="revit-publisher-table">
                      <thead>
                        <tr>
                          <th />
                          {linkMatrix.articles.map((col) => (
                            <th key={col.post_id} title={col.title}>
                              {col.short_title ?? col.title.slice(0, 12)}
                              {col.is_pillar ? ' ★' : ''}
                            </th>
                          ))}
                        </tr>
                      </thead>
                      <tbody>
                        {linkMatrix.articles.map((row) => (
                          <tr key={row.post_id}>
                            <th title={row.title}>
                              {row.short_title ?? row.title.slice(0, 20)}
                              {row.is_pillar ? ' ★' : ''}
                            </th>
                            {linkMatrix.articles.map((col) => {
                              if (row.post_id === col.post_id) {
                                return <td key={col.post_id} className="revit-publisher-matrix-dash">—</td>;
                              }
                              const linked = isLinked(row.post_id, col.post_id);
                              const suggested = hasSuggestion(row.post_id, col.post_id);
                              const key = suggestionKey({
                                source_post_id: row.post_id,
                                target_post_id: col.post_id,
                              });
                              return (
                                <td key={col.post_id}>
                                  {linked ? (
                                    <span title="Linked">✓</span>
                                  ) : suggested ? (
                                    <label title="Suggested link">
                                      <input
                                        type="checkbox"
                                        checked={matrixSelected.includes(key)}
                                        onChange={() => toggleMatrixSuggestion({
                                          source_post_id: row.post_id,
                                          target_post_id: col.post_id,
                                        })}
                                      />
                                      ✗
                                    </label>
                                  ) : (
                                    <span className="revit-publisher-muted">·</span>
                                  )}
                                </td>
                              );
                            })}
                          </tr>
                        ))}
                      </tbody>
                    </table>
                  </div>

                  {linkMatrix.revit_applied && linkMatrix.revit_applied.length > 0 && (
                    <>
                      <h3>RevIt-Applied Links</h3>
                      {linkMatrix.revit_applied.map((entry) => (
                        <div key={entry.log_id} className="revit-publisher-list-item">
                          {entry.source_title ?? entry.source_post_id} → {entry.target_title ?? entry.target_post_id}
                          <button type="button" onClick={() => handleUndoApplied(entry)}>Undo</button>
                        </div>
                      ))}
                    </>
                  )}
                </>
              )}

              {linkMatrix && linkMatrix.articles.length === 0 && (
                <p className="revit-publisher-muted">No articles in this cluster.</p>
              )}
            </div>
          )}
        </>
      )}

      {tab === 'opportunities' && (
        <div className="revit-publisher-card">
          {selected.length > 0 && (
            <div className="revit-publisher-actions">
              <button type="button" onClick={handleBatchApply}>Apply Selected ({selected.length})</button>
            </div>
          )}
          {opportunities.map((opp, index) => (
            <div key={`${opp.source_post_id}-${opp.target_post_id}-${index}`} className="revit-publisher-list-item">
              <label>
                <input
                  type="checkbox"
                  checked={selected.includes(index)}
                  onChange={(e) => {
                    setSelected((prev) => e.target.checked ? [...prev, index] : prev.filter((i) => i !== index));
                  }}
                />
                <strong>{opp.source_title}</strong> → {opp.target_title}
              </label>
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
