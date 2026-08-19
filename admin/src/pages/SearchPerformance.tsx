import { useCallback, useEffect, useState } from 'react';
import {
  connectGsc,
  disconnectGsc,
  fetchGscClusters,
  fetchGscOpportunities,
  fetchGscPages,
  fetchGscPostQueries,
  fetchGscSitemaps,
  fetchGscStatus,
  fetchGscSummary,
  fetchGscVehicles,
  inspectGscPost,
  submitGscSitemap,
  syncGsc,
} from '../api/search-console';
import { FixtureBanner } from '../components/EmptyState';
import { PageHeader } from '../components/PageHeader';
import {
  GscClusterRow,
  GscOpportunity,
  GscPageRow,
  GscQueryRow,
  GscSitemapsResponse,
  GscStatus,
  GscSummary,
  GscVehicleRow,
} from '../types/search-console';

type GscTab =
  | 'overview'
  | 'pages'
  | 'vehicles'
  | 'clusters'
  | 'opportunities'
  | 'indexing'
  | 'sitemaps';

function formatNumber(value: number): string {
  return value.toLocaleString();
}

function formatPct(value: number): string {
  return `${value.toFixed(2)}%`;
}

function formatChange(value: number | null, suffix = '%'): string {
  if (value === null) return '—';
  const sign = value > 0 ? '+' : '';
  return `${sign}${value}${suffix}`;
}

function changeClass(value: number | null, invert = false): string {
  if (value === null || value === 0) return 'revit-gsc-change--neutral';
  const positive = invert ? value < 0 : value > 0;
  return positive ? 'revit-gsc-change--up' : 'revit-gsc-change--down';
}

function ConnectionPrompt({
  status,
  onConnect,
  connecting,
}: {
  status: GscStatus | null;
  onConnect: (fixture: boolean) => void;
  connecting: boolean;
}) {
  return (
    <div className="revit-publisher-card revit-gsc-connect">
      <h2>Connect Google Search Console</h2>
      <p className="revit-publisher-muted">
        Link Search Console to view clicks, impressions, queries, and optimization opportunities.
      </p>
      {status?.last_error && (
        <div className="revit-publisher-result revit-publisher-result--error">
          <p>{status.last_error}</p>
        </div>
      )}
      <div className="revit-publisher-actions">
        {status?.credentials.client_id_configured && (
          <button type="button" disabled={connecting} onClick={() => onConnect(false)}>
            {connecting ? 'Connecting…' : 'Connect with Google'}
          </button>
        )}
        <button type="button" disabled={connecting} onClick={() => onConnect(true)}>
          {connecting ? 'Connecting…' : 'Connect with Fixture Data (Dev)'}
        </button>
      </div>
      {!status?.credentials.client_id_configured && (
        <p className="revit-publisher-disclaimer">
          OAuth credentials are not configured. Use fixture data for development, or add credentials in Settings.
        </p>
      )}
    </div>
  );
}

function SummaryCards({ summary }: { summary: GscSummary }) {
  const metrics = [
    { label: 'Clicks', key: 'clicks' as const, changeKey: 'clicks_pct' as const, invert: false },
    { label: 'Impressions', key: 'impressions' as const, changeKey: 'impressions_pct' as const, invert: false },
    { label: 'CTR', key: 'ctr' as const, changeKey: null as null, invert: false, format: formatPct },
    { label: 'Avg Position', key: 'position' as const, changeKey: 'position_delta' as const, invert: true, suffix: '' },
  ];

  return (
    <div className="revit-publisher-grid revit-gsc-summary-grid">
      {metrics.map((m) => {
        const current = summary.current[m.key];
        const change = m.changeKey ? summary.change[m.changeKey] : null;
        const display = m.format ? m.format(current) : formatNumber(current);
        const changeDisplay = m.key === 'position'
          ? formatChange(change, '')
          : formatChange(change);
        return (
          <div key={m.label} className="revit-publisher-card revit-publisher-metric revit-gsc-metric">
            <span className="revit-publisher-stat-label">{m.label}</span>
            <span className="revit-publisher-stat-value">{display}</span>
            <span className={`revit-gsc-change ${changeClass(change, m.invert)}`}>
              {changeDisplay} vs prior period
            </span>
          </div>
        );
      })}
    </div>
  );
}

function MetricsTable({
  headers,
  rows,
}: {
  headers: string[];
  rows: Array<Array<string | number>>;
}) {
  return (
    <div className="revit-publisher-table-wrap">
      <table className="revit-publisher-table">
        <thead>
          <tr>
            {headers.map((h) => (
              <th key={h}>{h}</th>
            ))}
          </tr>
        </thead>
        <tbody>
          {rows.map((row, i) => (
            <tr key={i}>
              {row.map((cell, j) => (
                <td key={j}>{cell}</td>
              ))}
            </tr>
          ))}
        </tbody>
      </table>
    </div>
  );
}

export function SearchPerformancePage() {
  const [status, setStatus] = useState<GscStatus | null>(null);
  const [tab, setTab] = useState<GscTab>('overview');
  const [summary, setSummary] = useState<GscSummary | null>(null);
  const [pages, setPages] = useState<GscPageRow[]>([]);
  const [vehicles, setVehicles] = useState<GscVehicleRow[]>([]);
  const [clusters, setClusters] = useState<GscClusterRow[]>([]);
  const [opportunities, setOpportunities] = useState<GscOpportunity[]>([]);
  const [sitemaps, setSitemaps] = useState<GscSitemapsResponse | null>(null);
  const [selectedPage, setSelectedPage] = useState<GscPageRow | null>(null);
  const [queries, setQueries] = useState<GscQueryRow[]>([]);
  const [error, setError] = useState<string | null>(null);
  const [loading, setLoading] = useState(true);
  const [connecting, setConnecting] = useState(false);
  const [syncing, setSyncing] = useState(false);
  const [submitting, setSubmitting] = useState(false);

  const loadStatus = useCallback(async () => {
    const s = await fetchGscStatus();
    setStatus(s);
    return s;
  }, []);

  const loadTabData = useCallback(async (next: GscTab) => {
    setError(null);
    setLoading(true);
    try {
      switch (next) {
        case 'overview':
          setSummary(await fetchGscSummary('28d'));
          break;
        case 'pages':
          setPages(await fetchGscPages('28d'));
          break;
        case 'vehicles':
          setVehicles(await fetchGscVehicles('28d'));
          break;
        case 'clusters':
          setClusters(await fetchGscClusters('28d'));
          break;
        case 'opportunities':
          setOpportunities(await fetchGscOpportunities('28d'));
          break;
        case 'indexing':
          setOpportunities(await fetchGscOpportunities('28d'));
          break;
        case 'sitemaps':
          setSitemaps(await fetchGscSitemaps());
          break;
      }
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Failed to load data.');
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    loadStatus()
      .then((s) => {
        if (s.connected) {
          return loadTabData('overview');
        }
        setLoading(false);
      })
      .catch((err: unknown) => {
        setError(err instanceof Error ? err.message : 'Failed to load status.');
        setLoading(false);
      });
  }, [loadStatus, loadTabData]);

  async function handleTabChange(next: GscTab) {
    setTab(next);
    setSelectedPage(null);
    setQueries([]);
    if (status?.connected) {
      await loadTabData(next);
    }
  }

  async function handleConnect(fixture: boolean) {
    setConnecting(true);
    setError(null);
    try {
      const result = await connectGsc(fixture);
      if (result.oauth_url) {
        window.location.href = result.oauth_url;
        return;
      }
      const s = await loadStatus();
      if (s.connected) {
        setTab('overview');
        await loadTabData('overview');
      }
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Connection failed.');
    } finally {
      setConnecting(false);
    }
  }

  async function handleDisconnect() {
    try {
      await disconnectGsc();
      setStatus(await loadStatus());
      setSummary(null);
      setPages([]);
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Disconnect failed.');
    }
  }

  async function handleSync() {
    setSyncing(true);
    setError(null);
    try {
      await syncGsc();
      setStatus(await loadStatus());
      await loadTabData(tab);
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Sync failed.');
    } finally {
      setSyncing(false);
    }
  }

  async function handlePageSelect(page: GscPageRow) {
    setSelectedPage(page);
    setQueries([]);
    try {
      setQueries(await fetchGscPostQueries(page.post_id, '28d'));
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Failed to load queries.');
    }
  }

  async function handleInspect(postId: number) {
    try {
      await inspectGscPost(postId);
      await loadTabData('indexing');
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Inspection failed.');
    }
  }

  async function handleSubmitSitemap() {
    setSubmitting(true);
    setError(null);
    try {
      await submitGscSitemap();
      setSitemaps(await fetchGscSitemaps());
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Sitemap submission failed.');
    } finally {
      setSubmitting(false);
    }
  }

  const indexIssues = opportunities.filter((o) =>
    o.issue_type === 'gsc_index_issue' || o.issue_type === 'gsc_canonical_mismatch',
  );

  const perfOpportunities = opportunities.filter((o) =>
    o.issue_type !== 'gsc_index_issue' && o.issue_type !== 'gsc_canonical_mismatch',
  );

  return (
    <div className="revit-publisher-panel revit-publisher-dark">
      <PageHeader title="Search Performance" />
      {status?.fixture_mode && <FixtureBanner />}

      {status?.connected && (
        <div className="revit-publisher-card revit-gsc-status-bar">
          <span>
            Connected to <strong>{status.property}</strong>
          </span>
          {status.last_sync && (
            <span className="revit-publisher-muted">Last sync: {status.last_sync}</span>
          )}
          <div className="revit-publisher-actions">
            <button type="button" disabled={syncing} onClick={handleSync}>
              {syncing ? 'Syncing…' : 'Sync Now'}
            </button>
            <button type="button" onClick={handleDisconnect}>Disconnect</button>
          </div>
        </div>
      )}

      {error && (
        <div className="revit-publisher-result revit-publisher-result--error">
          <p>{error}</p>
        </div>
      )}

      {!status?.connected && !loading && (
        <ConnectionPrompt status={status} onConnect={handleConnect} connecting={connecting} />
      )}

      {status?.connected && (
        <>
          <div className="revit-publisher-tabs">
            {([
              ['overview', 'Overview'],
              ['pages', 'Pages'],
              ['vehicles', 'Vehicles'],
              ['clusters', 'Clusters'],
              ['opportunities', 'Opportunities'],
              ['indexing', 'Indexing'],
              ['sitemaps', 'Sitemaps'],
            ] as [GscTab, string][]).map(([key, label]) => (
              <button
                key={key}
                type="button"
                className={tab === key ? 'is-active' : ''}
                onClick={() => handleTabChange(key)}
              >
                {label}
              </button>
            ))}
          </div>

          {loading && <p className="revit-publisher-muted">Loading…</p>}

          {tab === 'overview' && summary && !loading && (
            <>
              <SummaryCards summary={summary} />
              <div className="revit-publisher-card">
                <h2>Period Comparison</h2>
                <p className="revit-publisher-muted">
                  Current {summary.window} vs previous period.
                </p>
                <MetricsTable
                  headers={['Metric', 'Current', 'Previous', 'Change']}
                  rows={[
                    ['Clicks', formatNumber(summary.current.clicks), formatNumber(summary.previous.clicks), formatChange(summary.change.clicks_pct)],
                    ['Impressions', formatNumber(summary.current.impressions), formatNumber(summary.previous.impressions), formatChange(summary.change.impressions_pct)],
                    ['CTR', formatPct(summary.current.ctr), formatPct(summary.previous.ctr), '—'],
                    ['Avg Position', summary.current.position, summary.previous.position, formatChange(summary.change.position_delta, '')],
                  ]}
                />
              </div>
            </>
          )}

          {tab === 'pages' && !loading && (
            <div className="revit-publisher-card">
              <h2>Top Pages</h2>
              {pages.length === 0 && <p className="revit-publisher-muted">No page data yet. Run a sync.</p>}
              {pages.map((page) => (
                <div
                  key={page.post_id}
                  className={`revit-publisher-list-item revit-gsc-page-row${selectedPage?.post_id === page.post_id ? ' is-active' : ''}`}
                >
                  <button type="button" className="revit-gsc-page-link" onClick={() => handlePageSelect(page)}>
                    <strong>{page.title ?? `Post #${page.post_id}`}</strong>
                  </button>
                  <div className="revit-gsc-inline-metrics">
                    <span>{formatNumber(page.clicks)} clicks</span>
                    <span>{formatNumber(page.impressions)} impr</span>
                    <span>Pos {Number(page.position).toFixed(1)}</span>
                  </div>
                </div>
              ))}

              {selectedPage && (
                <div className="revit-publisher-card revit-gsc-queries-panel">
                  <h3>Queries — {selectedPage.title ?? `Post #${selectedPage.post_id}`}</h3>
                  {queries.length === 0 && <p className="revit-publisher-muted">No queries for this page.</p>}
                  {queries.length > 0 && (
                    <MetricsTable
                      headers={['Query', 'Clicks', 'Impressions', 'CTR', 'Position']}
                      rows={queries.map((q) => [
                        q.query,
                        formatNumber(q.clicks),
                        formatNumber(q.impressions),
                        formatPct(Number(q.ctr) * 100),
                        Number(q.position).toFixed(1),
                      ])}
                    />
                  )}
                </div>
              )}
            </div>
          )}

          {tab === 'vehicles' && !loading && (
            <div className="revit-publisher-card">
              <h2>Vehicle Performance</h2>
              {vehicles.length === 0 && <p className="revit-publisher-muted">No vehicle data.</p>}
              {vehicles.length > 0 && (
                <MetricsTable
                  headers={['Vehicle', 'Clicks', 'Impressions', 'CTR', 'Position', 'Articles w/ Impr']}
                  rows={vehicles.map((v) => [
                    v.vehicle,
                    formatNumber(v.clicks),
                    formatNumber(v.impressions),
                    formatPct(Number(v.ctr) * 100),
                    Number(v.position).toFixed(1),
                    `${v.articles_with_impressions}/${v.articles_total}`,
                  ])}
                />
              )}
            </div>
          )}

          {tab === 'clusters' && !loading && (
            <div className="revit-publisher-card">
              <h2>Cluster Performance</h2>
              {clusters.length === 0 && <p className="revit-publisher-muted">No cluster data.</p>}
              {clusters.length > 0 && (
                <MetricsTable
                  headers={['Cluster', 'Articles', 'Clicks', 'Impressions', 'Position']}
                  rows={clusters.map((c) => [
                    c.name || c.cluster_key,
                    c.articles,
                    formatNumber(c.clicks),
                    formatNumber(c.impressions),
                    Number(c.position).toFixed(1),
                  ])}
                />
              )}
            </div>
          )}

          {tab === 'opportunities' && !loading && (
            <div className="revit-publisher-card">
              <h2>Search Opportunities</h2>
              {perfOpportunities.length === 0 && <p className="revit-publisher-muted">No opportunities detected.</p>}
              {perfOpportunities.map((opp) => (
                <div key={`${opp.issue_type}-${opp.post_id}`} className="revit-publisher-list-item">
                  <strong>{opp.title}</strong>
                  <div className="revit-publisher-muted">{opp.issue_type.replace('gsc_', '')} · {opp.vehicle}</div>
                  <p>{opp.explanation}</p>
                  <p className="revit-publisher-muted">{opp.recommended_action}</p>
                </div>
              ))}
            </div>
          )}

          {tab === 'indexing' && !loading && (
            <div className="revit-publisher-card">
              <h2>Indexing Issues</h2>
              <p className="revit-publisher-disclaimer">
                Based on URL Inspection results. Daily inspection quota applies.
              </p>
              {indexIssues.length === 0 && <p className="revit-publisher-muted">No indexing issues detected.</p>}
              {indexIssues.map((opp) => (
                <div key={`${opp.issue_type}-${opp.post_id}`} className="revit-publisher-list-item">
                  <strong>{opp.title}</strong>
                  <div className="revit-publisher-muted">{opp.issue_type.replace('gsc_', '')}</div>
                  <p>{opp.explanation}</p>
                  <div className="revit-publisher-actions">
                    <button type="button" onClick={() => handleInspect(opp.post_id)}>
                      Re-inspect URL
                    </button>
                  </div>
                </div>
              ))}
            </div>
          )}

          {tab === 'sitemaps' && sitemaps && !loading && (
            <div className="revit-publisher-card">
              <h2>Sitemaps</h2>
              <p className="revit-publisher-muted">
                WordPress sitemap submitted: {sitemaps.submitted ? 'Yes' : 'No'}
              </p>
              <div className="revit-publisher-actions">
                <button type="button" disabled={submitting} onClick={handleSubmitSitemap}>
                  {submitting ? 'Submitting…' : 'Submit wp-sitemap.xml'}
                </button>
              </div>
              {sitemaps.sitemaps.length === 0 && <p className="revit-publisher-muted">No sitemaps listed.</p>}
              {sitemaps.sitemaps.length > 0 && (
                <MetricsTable
                  headers={['Path', 'Last Submitted', 'Errors', 'Warnings']}
                  rows={sitemaps.sitemaps.map((s) => [
                    s.path,
                    s.lastSubmitted ?? '—',
                    s.errors ?? 0,
                    s.warnings ?? 0,
                  ])}
                />
              )}
            </div>
          )}
        </>
      )}
    </div>
  );
}
