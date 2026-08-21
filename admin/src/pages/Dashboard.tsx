import { useEffect, useState } from 'react';
import { fetchStats } from '../api/article-packages';
import { EmptyState, LoadingBlock, SectionError } from '../components/EmptyState';
import { PageHeader } from '../components/PageHeader';
import { PrimaryButton } from '../components/PrimaryButton';
import { StatCard } from '../components/StatCard';
import { StatusBadge } from '../components/StatusBadge';
import { adminUrl } from '../lib/api-client';
import { vehicleArticleCount } from '../lib/vehicle-count';
import { RecentBatchSummary, StatsResponse } from '../types/article-package';

function formatBatchDate(value: string): string {
  const date = new Date(value);
  if (Number.isNaN(date.getTime())) {
    return value;
  }
  return date.toLocaleDateString(undefined, { month: 'short', day: 'numeric' });
}

function batchTone(status: string): 'success' | 'warning' | 'error' {
  if (status === 'SEO Ready' || status === 'Imported') return 'success';
  if (status === 'Partial') return 'error';
  return 'warning';
}

export function Dashboard() {
  const [stats, setStats] = useState<StatsResponse | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    fetchStats()
      .then(setStats)
      .catch((err: unknown) => {
        setError(err instanceof Error ? err.message : 'Failed to load dashboard.');
      })
      .finally(() => setLoading(false));
  }, []);

  const seo = stats?.seo_health;
  const graph = stats?.content_graph;
  const vehicles = stats?.intelligence?.vehicle_health ?? [];
  const needs = stats?.intelligence?.needs_attention;
  const batches: RecentBatchSummary[] = stats?.recent_batches ?? [];

  return (
    <div className="revit-publisher-panel revit-publisher-dark">
      <PageHeader
        action={<PrimaryButton href={adminUrl('import')}>Upload Articles</PrimaryButton>}
      />

      {loading && <LoadingBlock label="Loading dashboard…" />}
      {error && <SectionError message={error} onRetry={() => window.location.reload()} />}

      {stats && (
        <>
          <div className="revit-stat-grid">
            <StatCard label="Articles" value={stats.imported_articles} />
            <StatCard label="Vehicles" value={stats.vehicle_models} />
            <StatCard
              label="SEO Issues"
              value={(needs?.missing_meta ?? 0) + (needs?.orphans ?? 0) + (needs?.topic_overlaps ?? 0)}
            />
            <StatCard label="Link Opportunities" value={graph?.link_opportunities ?? graph?.pending_links ?? needs?.unresolved_links ?? 0} />
          </div>

          <section className="revit-publisher-card">
            <h2>Recent Batches</h2>
            {batches.length === 0 ? (
              <EmptyState
                title="No article batches yet"
                description="Upload your first RevIt article package to get started."
                actionLabel="Upload Articles"
                href={adminUrl('import')}
              />
            ) : (
              <ul className="revit-batch-list">
                {batches.slice(0, 5).map((batch) => (
                  <li key={batch.id} className="revit-batch-list__item">
                    <div>
                      <strong>{batch.vehicle_label}</strong>
                      <span className="revit-publisher-muted">
                        {batch.article_count} articles · {batch.vehicle_count} vehicles · {batch.cluster_count} clusters
                        · Imported {formatBatchDate(batch.imported_at)}
                      </span>
                    </div>
                    <StatusBadge tone={batchTone(batch.status)}>
                      {batch.status}
                    </StatusBadge>
                  </li>
                ))}
              </ul>
            )}
          </section>

          <section className="revit-publisher-card">
            <h2>Needs Review</h2>
            {(needs?.open_issues ?? 0) === 0 && (seo?.orphan_articles ?? 0) === 0 ? (
              <p className="revit-publisher-muted">No urgent items — you're caught up.</p>
            ) : (
              <ul className="revit-publisher-list">
                {(needs?.missing_meta ?? 0) > 0 && (
                  <li>{needs?.missing_meta} articles missing metadata</li>
                )}
                {(needs?.unresolved_links ?? 0) > 0 && (
                  <li>{needs?.unresolved_links} unresolved internal links</li>
                )}
                {(needs?.topic_overlaps ?? 0) > 0 && (
                  <li>{needs?.topic_overlaps} topic overlap warnings</li>
                )}
                {(seo?.orphan_articles ?? 0) > 0 && (
                  <li>{seo?.orphan_articles} orphan articles</li>
                )}
              </ul>
            )}
            <a className="revit-link" href={adminUrl('attention')}>View all in Needs Attention →</a>
          </section>

          <section className="revit-publisher-card">
            <h2>Recent Vehicles</h2>
            {vehicles.length === 0 ? (
              <EmptyState
                title="No vehicles yet"
                description="Vehicles appear automatically after article import."
              />
            ) : (
              <div className="revit-vehicle-grid">
                {vehicles.slice(0, 6).map((vehicle) => (
                  <div key={vehicle.label} className="revit-vehicle-card">
                    <h3>{vehicle.label}</h3>
                    <p>Articles {vehicleArticleCount(vehicle)}</p>
                    <p>SEO Health {vehicle.seo_health_avg}</p>
                    <p className="revit-publisher-muted">Link Coverage {vehicle.plan_coverage}%</p>
                    <a className="revit-link" href={adminUrl('vehicles')}>Open</a>
                  </div>
                ))}
              </div>
            )}
          </section>
        </>
      )}
    </div>
  );
}
