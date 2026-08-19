import { FormEvent, useEffect, useState } from 'react';
import {
  downloadArticleRequest,
  fetchContentPlans,
  fetchPlanCoverage,
  importContentPlan,
  previewContentPlan,
} from '../api/intelligence';
import { ContentPlanCoverage, ContentPlanPreview, ContentPlanSummary } from '../types/article-package';
import { MAX_JSON_FILE_SIZE } from '../api/article-packages';

export function ContentPlannerPage() {
  const [json, setJson] = useState('');
  const [preview, setPreview] = useState<ContentPlanPreview | null>(null);
  const [plans, setPlans] = useState<ContentPlanSummary[]>([]);
  const [coverage, setCoverage] = useState<ContentPlanCoverage | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [saved, setSaved] = useState(false);

  useEffect(() => {
    fetchContentPlans().then(setPlans).catch(() => undefined);
  }, [saved]);

  async function handlePreview(event: FormEvent) {
    event.preventDefault();
    setError(null);
    try {
      const payload = JSON.parse(json);
      setPreview(await previewContentPlan(payload));
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Preview failed.');
    }
  }

  async function handleImport() {
    setError(null);
    try {
      const payload = JSON.parse(json);
      const result = await importContentPlan(payload);
      if (!result.success) {
        setError('Import failed.');
        return;
      }
      setSaved(true);
      if (result.plan_id) {
        setCoverage(await fetchPlanCoverage(result.plan_id));
      }
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Import failed.');
    }
  }

  async function loadCoverage(planId: number) {
    setCoverage(await fetchPlanCoverage(planId));
  }

  async function handleDownloadRequest(articleKey: string) {
    if (!coverage?.plan_id) return;
    const data = await downloadArticleRequest(coverage.plan_id, articleKey);
    const blob = new Blob([JSON.stringify(data, null, 2)], { type: 'application/json' });
    const url = URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.href = url;
    link.download = `${articleKey}-request.json`;
    link.click();
    URL.revokeObjectURL(url);
  }

  return (
    <div className="revit-publisher-panel revit-publisher-dark">
      <h1>Content Planner</h1>
      <p className="revit-publisher-muted">Import vehicle content plans and track coverage gaps.</p>

      <form className="revit-publisher-card" onSubmit={handlePreview}>
        <label>
          Content Plan JSON
          <textarea rows={12} value={json} onChange={(e) => setJson(e.target.value)} />
        </label>
        <input
          type="file"
          accept="application/json,.json"
          onChange={async (e) => {
            const file = e.target.files?.[0];
            if (!file || file.size > MAX_JSON_FILE_SIZE) return;
            setJson(await file.text());
          }}
        />
        <div className="revit-publisher-actions">
          <button type="submit">Validate &amp; Preview</button>
          <button type="button" onClick={handleImport}>Import Plan</button>
        </div>
      </form>

      {error && <div className="revit-publisher-result revit-publisher-result--error"><p>{error}</p></div>}

      {preview?.valid && (
        <div className="revit-publisher-card">
          <h2>{preview.vehicle}</h2>
          <p>Planned Articles: {preview.summary?.planned_articles} · Existing: {preview.summary?.existing_articles} · Missing: {preview.summary?.missing_articles}</p>
          <p>Clusters: {preview.summary?.clusters} · Coverage: {preview.summary?.overall_coverage}%</p>
        </div>
      )}

      {plans.length > 0 && (
        <div className="revit-publisher-card">
          <h3>Imported Plans</h3>
          {plans.map((plan) => (
            <div key={plan.plan_id} className="revit-publisher-list-item">
              <strong>{plan.vehicle}</strong>
              <div>{plan.summary.planned_articles} planned · {plan.summary.existing_articles} existing · {plan.summary.missing_articles} missing</div>
              <button type="button" onClick={() => loadCoverage(plan.plan_id)}>View Coverage</button>
            </div>
          ))}
        </div>
      )}

      {coverage && (
        <div className="revit-publisher-card">
          <h3>{coverage.vehicle} — {coverage.summary.overall_coverage}% Coverage</h3>
          {coverage.clusters.map((cluster) => (
            <div key={cluster.cluster_key} className="revit-publisher-list-item">
              <strong>{cluster.name}</strong>
              <div>{cluster.existing}/{cluster.planned} articles · Pillar: {cluster.pillar_status} · Links: {cluster.internal_link_pct}%</div>
              <div className="revit-publisher-progress"><span style={{ width: `${cluster.plan_coverage}%` }} /></div>
            </div>
          ))}
          <h4>Missing Content</h4>
          {coverage.missing.map((item) => (
            <div key={item.article_key} className="revit-publisher-list-item">
              <strong>{item.title}</strong>
              <div>Priority {item.priority} · {item.cluster_key}</div>
              <button type="button" onClick={() => handleDownloadRequest(item.article_key)}>Download Article Request</button>
            </div>
          ))}
          <h4>Next Content</h4>
          <ol>
            {coverage.next_content.map((item) => (
              <li key={item.article_key}>{item.title} (Priority {item.priority})</li>
            ))}
          </ol>
        </div>
      )}
    </div>
  );
}
