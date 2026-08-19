import { useEffect, useState } from 'react';
import { fetchStats } from '../api/article-packages';
import { StatsResponse } from '../types/article-package';

export function Dashboard() {
  const [stats, setStats] = useState<StatsResponse | null>(null);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    fetchStats()
      .then(setStats)
      .catch((err: unknown) => {
        setError(err instanceof Error ? err.message : 'Failed to load stats.');
      });
  }, []);

  return (
    <div className="revit-publisher-panel revit-publisher-dark">
      <h1>RevIt Publisher Command Center</h1>
      <p className="revit-publisher-muted">Automotive content intelligence for RevIt24</p>

      {error && (
        <div className="revit-publisher-result revit-publisher-result--error">
          <p>{error}</p>
        </div>
      )}

      {stats && (
        <>
          <div className="revit-publisher-grid">
            <div className="revit-publisher-card revit-publisher-metric">
              <span className="revit-publisher-stat-label">Articles</span>
              <span className="revit-publisher-stat-value">{stats.imported_articles}</span>
            </div>
            <div className="revit-publisher-card revit-publisher-metric">
              <span className="revit-publisher-stat-label">Vehicles</span>
              <span className="revit-publisher-stat-value">{stats.content_graph?.vehicles ?? stats.vehicle_models}</span>
            </div>
            <div className="revit-publisher-card revit-publisher-metric">
              <span className="revit-publisher-stat-label">Clusters</span>
              <span className="revit-publisher-stat-value">{stats.content_graph?.clusters ?? stats.clusters}</span>
            </div>
            <div className="revit-publisher-card revit-publisher-metric">
              <span className="revit-publisher-stat-label">Content Plans</span>
              <span className="revit-publisher-stat-value">{stats.content_plans ?? 0}</span>
            </div>
            <div className="revit-publisher-card revit-publisher-metric">
              <span className="revit-publisher-stat-label">Missing Content</span>
              <span className="revit-publisher-stat-value">{stats.intelligence?.missing_content ?? 0}</span>
            </div>
            <div className="revit-publisher-card revit-publisher-metric">
              <span className="revit-publisher-stat-label">Topic Overlaps</span>
              <span className="revit-publisher-stat-value">{stats.intelligence?.topic_overlaps ?? 0}</span>
            </div>
          </div>

          <div className="revit-publisher-card">
            <h3>Needs Attention</h3>
            <ul className="revit-publisher-stats">
              <li><span className="revit-publisher-stat-label">Orphan Articles</span><span className="revit-publisher-stat-value">{stats.intelligence?.needs_attention?.orphans ?? stats.seo_health?.orphan_articles ?? 0}</span></li>
              <li><span className="revit-publisher-stat-label">High-Risk Overlaps</span><span className="revit-publisher-stat-value">{stats.intelligence?.needs_attention?.topic_overlaps ?? 0}</span></li>
              <li><span className="revit-publisher-stat-label">Missing Meta</span><span className="revit-publisher-stat-value">{stats.intelligence?.needs_attention?.missing_meta ?? stats.seo_health?.missing_meta ?? 0}</span></li>
              <li><span className="revit-publisher-stat-label">Unresolved Links</span><span className="revit-publisher-stat-value">{stats.intelligence?.needs_attention?.unresolved_links ?? stats.seo_health?.unresolved_links ?? 0}</span></li>
            </ul>
          </div>

          <div className="revit-publisher-card">
            <h3>Content Graph</h3>
            <ul className="revit-publisher-stats">
              <li><span className="revit-publisher-stat-label">Resolved Links</span><span className="revit-publisher-stat-value">{stats.content_graph?.resolved_links ?? 0}</span></li>
              <li><span className="revit-publisher-stat-label">Pending Links</span><span className="revit-publisher-stat-value">{stats.content_graph?.pending_links ?? 0}</span></li>
              <li><span className="revit-publisher-stat-label">Schema</span><span className="revit-publisher-stat-value">{stats.schema_version}</span></li>
            </ul>
          </div>
        </>
      )}
    </div>
  );
}
