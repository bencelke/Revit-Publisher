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
    <div className="revit-publisher-panel">
      <h1>RevIt Publisher</h1>
      <p className="revit-publisher-muted">Automotive SEO &amp; Content Intelligence</p>

      {error && (
        <div className="revit-publisher-result revit-publisher-result--error">
          <p>{error}</p>
        </div>
      )}

      {stats && (
        <>
          <div className="revit-publisher-card">
            <h2>RevIt Publisher {stats.version}</h2>
            <h3>SEO Health</h3>
            <ul className="revit-publisher-stats">
              <li>
                <span className="revit-publisher-stat-label">RevIt Articles</span>
                <span className="revit-publisher-stat-value">{stats.seo_health?.revit_articles ?? stats.imported_articles}</span>
              </li>
              <li>
                <span className="revit-publisher-stat-label">Orphan Articles</span>
                <span className="revit-publisher-stat-value">{stats.seo_health?.orphan_articles ?? 0}</span>
              </li>
              <li>
                <span className="revit-publisher-stat-label">Unresolved Links</span>
                <span className="revit-publisher-stat-value">{stats.seo_health?.unresolved_links ?? 0}</span>
              </li>
              <li>
                <span className="revit-publisher-stat-label">Missing Pillars</span>
                <span className="revit-publisher-stat-value">{stats.seo_health?.missing_pillars ?? 0}</span>
              </li>
              <li>
                <span className="revit-publisher-stat-label">Missing Meta</span>
                <span className="revit-publisher-stat-value">{stats.seo_health?.missing_meta ?? 0}</span>
              </li>
              <li>
                <span className="revit-publisher-stat-label">Duplicate Topics</span>
                <span className="revit-publisher-stat-value">{stats.seo_health?.duplicate_topics ?? 0}</span>
              </li>
            </ul>
          </div>

          <div className="revit-publisher-card">
            <h3>Content Graph</h3>
            <ul className="revit-publisher-stats">
              <li>
                <span className="revit-publisher-stat-label">Vehicles</span>
                <span className="revit-publisher-stat-value">{stats.content_graph?.vehicles ?? stats.vehicle_models}</span>
              </li>
              <li>
                <span className="revit-publisher-stat-label">Clusters</span>
                <span className="revit-publisher-stat-value">{stats.content_graph?.clusters ?? stats.clusters}</span>
              </li>
              <li>
                <span className="revit-publisher-stat-label">Resolved Links</span>
                <span className="revit-publisher-stat-value">{stats.content_graph?.resolved_links ?? 0}</span>
              </li>
              <li>
                <span className="revit-publisher-stat-label">Pending Links</span>
                <span className="revit-publisher-stat-value">{stats.content_graph?.pending_links ?? 0}</span>
              </li>
              <li>
                <span className="revit-publisher-stat-label">Article Schema</span>
                <span className="revit-publisher-stat-value">{stats.schema_version}</span>
              </li>
            </ul>
          </div>
        </>
      )}
    </div>
  );
}
