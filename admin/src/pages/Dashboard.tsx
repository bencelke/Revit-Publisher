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

  const vehicles = stats?.intelligence?.vehicle_health ?? [];
  const latest = stats?.intelligence?.latest_audit as { summary?: Record<string, number> } | undefined;

  return (
    <div className="revit-publisher-panel revit-publisher-dark">
      <h1>RevIt Publisher Command Center</h1>
      <p className="revit-publisher-muted">SEO operations and content maintenance for RevIt24</p>

      {error && (
        <div className="revit-publisher-result revit-publisher-result--error">
          <p>{error}</p>
        </div>
      )}

      {stats && (
        <>
          {latest?.summary && (
            <div className="revit-publisher-card revit-publisher-notice">
              <strong>Latest audit:</strong>{' '}
              {stats.intelligence?.needs_attention?.open_issues ?? 0} open issues ·{' '}
              {latest.summary.unresolved_link_count ?? 0} unresolved links ·{' '}
              {latest.summary.missing_content_count ?? 0} content gaps
            </div>
          )}

          <div className="revit-publisher-grid">
            <div className="revit-publisher-card revit-publisher-metric">
              <span className="revit-publisher-stat-label">Articles</span>
              <span className="revit-publisher-stat-value">{stats.imported_articles}</span>
            </div>
            <div className="revit-publisher-card revit-publisher-metric">
              <span className="revit-publisher-stat-label">Open Issues</span>
              <span className="revit-publisher-stat-value">{stats.intelligence?.needs_attention?.open_issues ?? 0}</span>
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
            <h3>Vehicle Content Health</h3>
            {vehicles.length === 0 && <p className="revit-publisher-muted">Import articles to populate vehicle metrics.</p>}
            {vehicles.map((v) => (
              <div key={v.label} className="revit-publisher-list-item">
                <strong>{v.label}</strong>
                <span className="revit-publisher-stat-value">{v.seo_health_avg}</span>
                <div className="revit-publisher-muted">
                  Coverage {v.plan_coverage}% · Published {v.published} · Missing {v.missing_articles}
                </div>
              </div>
            ))}
          </div>

          <div className="revit-publisher-card">
            <h3>Needs Attention</h3>
            <ul className="revit-publisher-stats">
              <li><span className="revit-publisher-stat-label">Orphan Articles</span><span className="revit-publisher-stat-value">{stats.intelligence?.needs_attention?.orphans ?? 0}</span></li>
              <li><span className="revit-publisher-stat-label">High-Risk Overlaps</span><span className="revit-publisher-stat-value">{stats.intelligence?.needs_attention?.topic_overlaps ?? 0}</span></li>
              <li><span className="revit-publisher-stat-label">Missing Meta</span><span className="revit-publisher-stat-value">{stats.intelligence?.needs_attention?.missing_meta ?? 0}</span></li>
              <li><span className="revit-publisher-stat-label">Unresolved Links</span><span className="revit-publisher-stat-value">{stats.intelligence?.needs_attention?.unresolved_links ?? 0}</span></li>
            </ul>
          </div>
        </>
      )}
    </div>
  );
}
