import { useEffect, useState } from 'react';
import { fetchAudits, runAudit } from '../api/operations';

export function AuditsPage() {
  const [audits, setAudits] = useState<Array<Record<string, unknown>>>([]);
  const [running, setRunning] = useState(false);
  const [message, setMessage] = useState<string | null>(null);

  function load() {
    fetchAudits().then((data) => setAudits(data as Array<Record<string, unknown>>));
  }

  useEffect(() => { load(); }, []);

  async function handleRun() {
    setRunning(true);
    setMessage(null);
    try {
      const result = (await runAudit()) as { status?: string; message?: string };
      setMessage(result.message ?? `Audit ${result.status ?? 'complete'}.`);
      load();
    } catch (e) {
      setMessage(e instanceof Error ? e.message : 'Audit failed.');
    } finally {
      setRunning(false);
    }
  }

  return (
    <div className="revit-publisher-panel revit-publisher-dark">
      <h1>Audit History</h1>
      <p className="revit-publisher-muted">Scheduled detection only — no automatic content changes.</p>
      <div className="revit-publisher-actions">
        <button type="button" onClick={handleRun} disabled={running}>{running ? 'Running…' : 'Run Audit Now'}</button>
      </div>
      {message && <p>{message}</p>}
      {audits.map((audit) => {
        const summary = (audit.summary ?? {}) as Record<string, number>;
        const trends = (audit.trends ?? {}) as Record<string, string>;
        return (
          <div key={String(audit.snapshot_id)} className="revit-publisher-card">
            <h3>{String(audit.created_at ?? 'Audit')}</h3>
            <ul className="revit-publisher-list">
              <li>Articles: {summary.articles_scanned ?? 0}</li>
              <li>Orphans: {summary.orphan_count ?? 0} ({trends.orphans ?? 'unchanged'})</li>
              <li>Missing Content: {summary.missing_content_count ?? 0} ({trends.missing_content ?? 'unchanged'})</li>
              <li>Unresolved Links: {summary.unresolved_link_count ?? 0} ({trends.unresolved_links ?? 'unchanged'})</li>
              <li>High Topic Overlap: {summary.high_overlap_count ?? 0} ({trends.high_overlap ?? 'unchanged'})</li>
              <li>SEO Health Avg: {summary.overall_health_avg ?? 0} ({trends.seo_health ?? 'unchanged'})</li>
            </ul>
          </div>
        );
      })}
    </div>
  );
}
