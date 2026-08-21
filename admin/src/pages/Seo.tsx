import { useEffect, useState } from 'react';
import { fetchStats } from '../api/article-packages';
import { fetchTopicOverlaps } from '../api/intelligence';
import { applySeoLink, runSiteSeoScan, SiteSeoScanSummary, undoSeoLink } from '../api/seo-scan';
import { EmptyState, LoadingBlock, SectionError } from '../components/EmptyState';
import { PageHeader } from '../components/PageHeader';
import { PrimaryButton, SecondaryButton } from '../components/PrimaryButton';
import { StatCard } from '../components/StatCard';
import { adminUrl } from '../lib/api-client';
import { StatsResponse } from '../types/article-package';

interface OverlapRow {
  title_a: string;
  title_b: string;
  overlap_pct: number;
  risk: string;
}

type SeoTab = 'overview' | 'linking' | 'quality' | 'overlap';

interface LinkIdea {
  source_post_id: number;
  source_title: string;
  target_post_id: number;
  target_title: string;
  anchor: string;
  reason: string;
  confidence: string;
  safe_to_auto_apply: boolean;
}

export function SeoPage() {
  const [tab, setTab] = useState<SeoTab>('overview');
  const [stats, setStats] = useState<StatsResponse | null>(null);
  const [overlaps, setOverlaps] = useState<OverlapRow[]>([]);
  const [scan, setScan] = useState<SiteSeoScanSummary | null>(null);
  const [loading, setLoading] = useState(true);
  const [scanning, setScanning] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [applying, setApplying] = useState<string | null>(null);
  const [lastLogId, setLastLogId] = useState<number | null>(null);
  const [notice, setNotice] = useState<string | null>(null);

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

  async function scanSite() {
    setScanning(true);
    setError(null);
    setNotice(null);
    try {
      const result = await runSiteSeoScan();
      setScan(result);
      setTab('overview');
    } catch (err: unknown) {
      setError(err instanceof Error ? err.message : 'Site scan failed.');
    } finally {
      setScanning(false);
    }
  }

  async function applyIdea(idea: LinkIdea) {
    const key = `${idea.source_post_id}-${idea.target_post_id}`;
    setApplying(key);
    setNotice(null);
    try {
      const result = await applySeoLink({
        source_post_id: idea.source_post_id,
        target_post_id: idea.target_post_id,
        anchor: idea.anchor,
        relationship: 'related',
      });
      setLastLogId(result.log_id ?? null);
      setNotice(`Applied “${idea.anchor}”. Post remains ${result.post_status ?? 'draft'}.`);
      await scanSite();
    } catch (err: unknown) {
      setError(err instanceof Error ? err.message : 'Could not apply link.');
    } finally {
      setApplying(null);
    }
  }

  async function undoLast() {
    if (!lastLogId) return;
    try {
      await undoSeoLink(lastLogId);
      setNotice('Link undone. Original content restored.');
      setLastLogId(null);
      await scanSite();
    } catch (err: unknown) {
      setError(err instanceof Error ? err.message : 'Could not undo link.');
    }
  }

  const seo = stats?.seo_health;
  const ideas = (scan?.opportunities ?? []) as unknown as LinkIdea[];
  const empty = (stats?.imported_articles ?? 0) === 0 && !loading;

  if (empty) {
    return (
      <div className="revit-publisher-panel revit-publisher-dark">
        <PageHeader title="SEO" />
        <EmptyState title="No SEO analysis yet" description="Import articles first." actionLabel="Batch Import" href={adminUrl('import')} />
      </div>
    );
  }

  return (
    <div className="revit-publisher-panel revit-publisher-dark">
      <PageHeader
        title="SEO"
        action={
          <PrimaryButton onClick={scanSite} disabled={scanning}>
            {scanning ? 'Scanning…' : 'Scan Site'}
          </PrimaryButton>
        }
      />

      {loading && <LoadingBlock />}
      {error && <SectionError message={error} onRetry={() => window.location.reload()} />}
      {notice && <p className="revit-publisher-muted">{notice}</p>}

      <div className="revit-publisher-tabs">
        <button type="button" className={tab === 'overview' ? 'is-active' : ''} onClick={() => setTab('overview')}>Overview</button>
        <button type="button" className={tab === 'linking' ? 'is-active' : ''} onClick={() => setTab('linking')}>Internal Linking</button>
        <button type="button" className={tab === 'quality' ? 'is-active' : ''} onClick={() => setTab('quality')}>Article Quality</button>
        <button type="button" className={tab === 'overlap' ? 'is-active' : ''} onClick={() => setTab('overlap')}>Topic Overlap</button>
      </div>

      {tab === 'overview' && (
        <section className="revit-publisher-card">
          <h2>SEO Site Audit</h2>
          {scan ? (
            <>
              <div className="revit-stat-grid">
                <StatCard label="Articles scanned" value={scan.articles_scanned} />
                <StatCard label="SEO compliant" value={scan.seo_compliant} />
                <StatCard label="Needs improvement" value={scan.needs_improvement} />
                <StatCard label="Orphan articles" value={scan.orphan_articles} />
                <StatCard label="Internal link ideas" value={scan.internal_link_ideas} />
                <StatCard label="Missing metadata" value={scan.missing_metadata} />
                <StatCard label="Heading issues" value={scan.heading_issues} />
                <StatCard label="Media issues" value={scan.media_issues} />
              </div>
              <p className="revit-publisher-muted">Nothing is applied automatically after a scan. Review safe fixes below.</p>
              <SecondaryButton onClick={() => setTab('linking')}>Review Safe Fixes</SecondaryButton>
            </>
          ) : (
            <p className="revit-publisher-muted">
              Scan the live WordPress posts to measure mechanical SEO compliance, orphans, and natural internal-link ideas.
              Imported articles: {stats?.imported_articles ?? 0}. Orphan count uses inbound contextual links in article body after Scan Site.
            </p>
          )}
        </section>
      )}

      {tab === 'linking' && (
        <section className="revit-publisher-card">
          <h2>Internal Linking</h2>
          {lastLogId && (
            <p>
              <SecondaryButton onClick={undoLast}>Undo last applied link</SecondaryButton>
            </p>
          )}
          {ideas.length === 0 ? (
            <p className="revit-publisher-muted">Run Scan Site to discover natural internal-link opportunities.</p>
          ) : (
            <table className="revit-data-table">
              <thead>
                <tr>
                  <th>Source</th>
                  <th>Target</th>
                  <th>Anchor</th>
                  <th>Reason</th>
                  <th>Confidence</th>
                  <th>Safe</th>
                  <th></th>
                </tr>
              </thead>
              <tbody>
                {ideas.map((idea) => (
                  <tr key={`${idea.source_post_id}-${idea.target_post_id}-${idea.anchor}`}>
                    <td>{idea.source_title}</td>
                    <td>{idea.target_title}</td>
                    <td>{idea.anchor}</td>
                    <td>{idea.reason}</td>
                    <td>{idea.confidence}</td>
                    <td>{idea.safe_to_auto_apply ? 'Yes' : 'Review'}</td>
                    <td>
                      {idea.safe_to_auto_apply && (
                        <button
                          type="button"
                          className="button"
                          disabled={applying !== null}
                          onClick={() => applyIdea(idea)}
                        >
                          Apply
                        </button>
                      )}
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          )}
        </section>
      )}

      {tab === 'quality' && (
        <section className="revit-publisher-card">
          <h2>Article Quality</h2>
          <p className="revit-publisher-muted">Mechanical SEO only. Editorial writing quality is not scored.</p>
          <ul className="revit-publisher-stats">
            <li><span>Heading issues</span><span>{scan?.heading_issues ?? 'Scan to measure'}</span></li>
            <li><span>Metadata gaps</span><span>{scan?.missing_metadata ?? seo?.missing_meta ?? 0}</span></li>
            <li><span>Media issues</span><span>{scan?.media_issues ?? 'Scan to measure'}</span></li>
            <li><span>Taxonomy</span><span>{stats?.vehicle_models ?? 0} vehicles · {stats?.clusters ?? 0} clusters</span></li>
          </ul>
        </section>
      )}

      {tab === 'overlap' && (
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
      )}
    </div>
  );
}
