import { useEffect, useState } from 'react';
import { fetchStats } from '../api/article-packages';
import { fetchTopicOverlaps } from '../api/intelligence';
import { EmptyState, LoadingBlock, SectionError } from '../components/EmptyState';
import { PageHeader } from '../components/PageHeader';
import { StatCard } from '../components/StatCard';
import { adminUrl } from '../lib/api-client';
import { StatsResponse } from '../types/article-package';

interface OverlapRow {
  title_a: string;
  title_b: string;
  overlap_pct: number;
  risk: string;
}

export function SeoPage() {
  const [stats, setStats] = useState<StatsResponse | null>(null);
  const [overlaps, setOverlaps] = useState<OverlapRow[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    Promise.all([fetchStats(), fetchTopicOverlaps().catch(() => [])])
      .then(([statsData, overlapRows]) => {
        setStats(statsData);
        setOverlaps(overlapRows as OverlapRow[]);
      })
      .catch((err: unknown) => {
        setError(err instanceof Error ? err.message : 'Failed to load SEO overview.');
      })
      .finally(() => setLoading(false));
  }, []);

  const seo = stats?.seo_health;
  const graph = stats?.content_graph;
  const healthy = Math.max(0, (seo?.revit_articles ?? 0) - (seo?.orphan_articles ?? 0) - (seo?.missing_meta ?? 0));

  if ((stats?.imported_articles ?? 0) === 0 && !loading) {
    return (
      <div className="revit-publisher-panel revit-publisher-dark">
        <PageHeader title="SEO" />
        <EmptyState title="No SEO analysis yet" description="Import articles first." actionLabel="Batch Import" href={adminUrl('import')} />
      </div>
    );
  }

  return (
    <div className="revit-publisher-panel revit-publisher-dark">
      <PageHeader title="SEO" />

      {loading && <LoadingBlock />}
      {error && <SectionError message={error} onRetry={() => window.location.reload()} />}

      {stats && (
        <>
          <section className="revit-publisher-card">
            <h2>Overview</h2>
            <div className="revit-stat-grid">
              <StatCard label="Healthy articles" value={healthy} />
              <StatCard label="Needs attention" value={(seo?.missing_meta ?? 0) + (seo?.orphan_articles ?? 0)} />
              <StatCard label="Orphan articles" value={seo?.orphan_articles ?? 0} />
              <StatCard label="Topic overlap" value={seo?.duplicate_topics ?? overlaps.length} />
              <StatCard label="Missing metadata" value={seo?.missing_meta ?? 0} />
            </div>
          </section>

          <section className="revit-publisher-card">
            <h2>Internal Linking</h2>
            <ul className="revit-publisher-stats">
              <li><span>Planned</span><span>{(graph?.resolved_links ?? 0) + (graph?.pending_links ?? 0)}</span></li>
              <li><span>Resolved</span><span>{graph?.resolved_links ?? 0}</span></li>
              <li><span>Opportunities</span><span>{graph?.pending_links ?? seo?.unresolved_links ?? 0}</span></li>
            </ul>
          </section>

          <section className="revit-publisher-card">
            <h2>Article Quality</h2>
            <ul className="revit-publisher-stats">
              <li><span>Heading / structure</span><span>Tracked after import</span></li>
              <li><span>Metadata</span><span>{seo?.missing_meta ?? 0} gaps</span></li>
              <li><span>Schema</span><span>Enabled via settings</span></li>
              <li><span>Taxonomy</span><span>{stats.vehicle_models} vehicles · {stats.clusters} clusters</span></li>
            </ul>
            <a className="revit-link" href={adminUrl('seoHealth')}>Detailed SEO Health →</a>
          </section>

          <section className="revit-publisher-card">
            <h2>Topic Overlap</h2>
            {overlaps.length === 0 ? (
              <p className="revit-publisher-muted">No overlap warnings detected.</p>
            ) : (
              <table className="revit-data-table">
                <thead>
                  <tr>
                    <th>Article A</th>
                    <th>Article B</th>
                    <th>Overlap</th>
                    <th>Risk</th>
                  </tr>
                </thead>
                <tbody>
                  {overlaps.slice(0, 10).map((row, index) => (
                    <tr key={`${row.title_a}-${index}`}>
                      <td>{row.title_a}</td>
                      <td>{row.title_b}</td>
                      <td>{row.overlap_pct}%</td>
                      <td>{row.risk}</td>
                    </tr>
                  ))}
                </tbody>
              </table>
            )}
          </section>
        </>
      )}
    </div>
  );
}
