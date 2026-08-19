import { useEffect, useState } from 'react';
import { fetchStats } from '../api/article-packages';
import { fetchEditorialToday, EditorialTodaySummary } from '../api/editorial';
import { fetchGscStatus, fetchGscSummary } from '../api/search-console';
import { ProductHeader } from '../components/ProductHeader';
import { StatsResponse } from '../types/article-package';
import { GscStatus, GscSummary } from '../types/search-console';

export function Dashboard() {
  const [stats, setStats] = useState<StatsResponse | null>(null);
  const [gscStatus, setGscStatus] = useState<GscStatus | null>(null);
  const [gscSummary, setGscSummary] = useState<GscSummary | null>(null);
  const [today, setToday] = useState<EditorialTodaySummary | null>(null);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    fetchStats()
      .then(setStats)
      .catch((err: unknown) => {
        setError(err instanceof Error ? err.message : 'Failed to load stats.');
      });
    fetchGscStatus()
      .then((status) => {
        setGscStatus(status);
        if (status.connected) {
          return fetchGscSummary('28d').then(setGscSummary);
        }
        return undefined;
      })
      .catch(() => {
        setGscStatus(null);
      });
    fetchEditorialToday().then(setToday).catch(() => undefined);
  }, []);

  const vehicles = stats?.intelligence?.vehicle_health ?? [];
  const latest = stats?.intelligence?.latest_audit as { summary?: Record<string, number> } | undefined;

  return (
    <div className="revit-publisher-panel revit-publisher-dark">
      <ProductHeader title="Command Center" />

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

          {today && (
            <div className="revit-publisher-card revit-today-card">
              <h3>What To Work On — Today</h3>
              <p>
                {(today.counts.high ?? 0) + (today.counts.urgent ?? 0)} high priority ·{' '}
                {today.counts.medium ?? 0} medium priority
              </p>
              <ol>
                {today.items.slice(0, 3).map((item) => (
                  <li key={item.id}>{item.title}</li>
                ))}
              </ol>
              <a className="button" href="admin.php?page=revit-publisher-editorial">Open Editorial Queue</a>
            </div>
          )}

          {gscStatus?.connected && gscSummary && (
            <div className="revit-publisher-card revit-gsc-dashboard-card">
              <h3>Search Console (28 days)</h3>
              <div className="revit-publisher-grid revit-gsc-summary-grid">
                <div className="revit-publisher-metric">
                  <span className="revit-publisher-stat-label">Clicks</span>
                  <span className="revit-publisher-stat-value">{gscSummary.current.clicks.toLocaleString()}</span>
                  {gscSummary.change.clicks_pct !== null && (
                    <span className={`revit-gsc-change ${gscSummary.change.clicks_pct >= 0 ? 'revit-gsc-change--up' : 'revit-gsc-change--down'}`}>
                      {gscSummary.change.clicks_pct > 0 ? '+' : ''}{gscSummary.change.clicks_pct}%
                    </span>
                  )}
                </div>
                <div className="revit-publisher-metric">
                  <span className="revit-publisher-stat-label">Impressions</span>
                  <span className="revit-publisher-stat-value">{gscSummary.current.impressions.toLocaleString()}</span>
                  {gscSummary.change.impressions_pct !== null && (
                    <span className={`revit-gsc-change ${gscSummary.change.impressions_pct >= 0 ? 'revit-gsc-change--up' : 'revit-gsc-change--down'}`}>
                      {gscSummary.change.impressions_pct > 0 ? '+' : ''}{gscSummary.change.impressions_pct}%
                    </span>
                  )}
                </div>
                <div className="revit-publisher-metric">
                  <span className="revit-publisher-stat-label">Avg Position</span>
                  <span className="revit-publisher-stat-value">{gscSummary.current.position}</span>
                  {gscSummary.change.position_delta !== null && (
                    <span className={`revit-gsc-change ${gscSummary.change.position_delta >= 0 ? 'revit-gsc-change--up' : 'revit-gsc-change--down'}`}>
                      {gscSummary.change.position_delta > 0 ? '+' : ''}{gscSummary.change.position_delta}
                    </span>
                  )}
                </div>
              </div>
            </div>
          )}

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
