import { useEffect, useState } from 'react';
import { fetchStats } from '../api/validation';
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
        <div className="revit-publisher-card">
          <h2>RevIt Publisher {stats.version}</h2>
          <ul className="revit-publisher-stats">
            <li>
              <span className="revit-publisher-stat-label">Imported Articles</span>
              <span className="revit-publisher-stat-value">{stats.imported_articles}</span>
            </li>
            <li>
              <span className="revit-publisher-stat-label">Vehicle Models</span>
              <span className="revit-publisher-stat-value">{stats.vehicle_models}</span>
            </li>
            <li>
              <span className="revit-publisher-stat-label">Clusters</span>
              <span className="revit-publisher-stat-value">{stats.clusters}</span>
            </li>
            <li>
              <span className="revit-publisher-stat-label">Article Schema</span>
              <span className="revit-publisher-stat-value">{stats.schema_version}</span>
            </li>
          </ul>
        </div>
      )}

      <div className="revit-publisher-card">
        <h2>Phase 1 Scope</h2>
        <p>
          Validate, preview, and import <code>revit-article-v1</code> packages as WordPress drafts
          with automotive taxonomies and structured RevIt metadata.
        </p>
      </div>
    </div>
  );
}
