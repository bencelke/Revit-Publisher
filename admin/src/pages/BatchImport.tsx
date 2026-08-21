import { useCallback, useState } from 'react';
import {
  importArticlePackage,
  MAX_JSON_FILE_SIZE,
  previewArticlePackage,
  recordImportBatch,
  validateArticlePackage,
} from '../api/article-packages';
import { ApiError } from '../lib/api-client';
import {
  BatchAnalysis,
  BatchFileItem,
  BatchImportResult,
  BatchOptimizeSummary,
  buildAnalysis,
  buildOptimizeSummary,
  deriveFileStatus,
} from '../lib/batch-utils';
import { DropZone } from '../components/DropZone';
import { EmptyState, LoadingBlock, SectionError } from '../components/EmptyState';
import { PageHeader } from '../components/PageHeader';
import { PrimaryButton, SecondaryButton } from '../components/PrimaryButton';
import { StepIndicator, WorkflowStep } from '../components/StepIndicator';
import { ArticleStatus } from '../components/StatusBadge';
import { ImportPage } from './Import';
import { adminUrl } from '../lib/api-client';
import { PreviewSuccess } from '../types/article-package';

function uid(): string {
  return `${Date.now()}-${Math.random().toString(36).slice(2, 8)}`;
}

export function BatchImportPage() {
  const [step, setStep] = useState<WorkflowStep>('upload');
  const [files, setFiles] = useState<BatchFileItem[]>([]);
  const [analysis, setAnalysis] = useState<BatchAnalysis | null>(null);
  const [optimize, setOptimize] = useState<BatchOptimizeSummary | null>(null);
  const [importResult, setImportResult] = useState<BatchImportResult | null>(null);
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [showManual, setShowManual] = useState(false);
  const [skipExisting, setSkipExisting] = useState<Record<string, 'skip' | 'update'>>({});

  const validItems = files.filter((f) => f.preview);

  async function processFiles(fileList: FileList) {
    setError(null);
    setStep('upload');
    setAnalysis(null);
    setOptimize(null);
    setImportResult(null);
    setBusy(true);

    try {
    const next: BatchFileItem[] = [];
    for (const file of Array.from(fileList)) {
      const item: BatchFileItem = {
        id: uid(),
        filename: file.name,
        payload: null,
        parseError: null,
        validation: null,
        preview: null,
        status: 'invalid',
        statusDetail: 'Pending',
      };

      if (!file.name.toLowerCase().endsWith('.json')) {
        item.status = 'unsupported';
        item.statusDetail = 'Unsupported file type';
        next.push(item);
        continue;
      }

      if (file.size > MAX_JSON_FILE_SIZE) {
        item.parseError = 'File exceeds 5 MB limit.';
        const derived = deriveFileStatus(item);
        item.status = derived.status;
        item.statusDetail = derived.detail;
        next.push(item);
        continue;
      }

      try {
        const text = await file.text();
        item.payload = JSON.parse(text);
      } catch (err) {
        item.parseError = err instanceof Error ? err.message : 'Invalid JSON';
        const derived = deriveFileStatus(item);
        item.status = derived.status;
        item.statusDetail = derived.detail;
        next.push(item);
        continue;
      }

      try {
        item.validation = await validateArticlePackage(item.payload);
        if (item.validation.valid) {
          const preview = await previewArticlePackage(item.payload);
          if (preview.valid) {
            item.preview = preview as PreviewSuccess;
          } else {
            item.statusDetail = preview.errors[0]?.message ?? 'Preview failed';
          }
        }
      } catch (err) {
        item.statusDetail = err instanceof ApiError ? err.message : 'Validation request failed';
      }

      const derived = deriveFileStatus(item);
      item.status = derived.status;
      item.statusDetail = derived.detail;
      next.push(item);
    }

    setFiles(next);
    if (next.some((f) => f.preview)) {
      setAnalysis(buildAnalysis(next));
      setStep('analyze');
    }
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Failed to process files.');
    } finally {
      setBusy(false);
    }
  }

  async function runAnalysis() {
    setBusy(true);
    setError(null);
    try {
      const result = buildAnalysis(files);
      setAnalysis(result);
      setStep('analyze');
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Analysis failed.');
    } finally {
      setBusy(false);
    }
  }

  function runOptimize() {
    if (!analysis) return;
    setOptimize(buildOptimizeSummary(analysis));
    setStep('optimize');
  }

  async function runImport() {
    setBusy(true);
    setError(null);
    const results: BatchImportResult['results'] = [];
    let created = 0;
    let skipped = 0;
    let failed = 0;
    let existing = 0;

    const batchId = uid();
    for (const item of validItems) {
      if (!item.payload || !item.preview) continue;
      const action = skipExisting[item.preview.article.article_key] ?? (item.preview.existing_article ? 'skip' : 'update');
      if (item.preview.existing_article && action === 'skip') {
        skipped += 1;
        existing += 1;
        results.push({
          filename: item.filename,
          response: {
            success: false,
            status: 'existing_article',
            article_key: item.preview.article.article_key,
            post_id: item.preview.existing_post_id ?? 0,
            edit_url: '',
          },
        });
        continue;
      }

      try {
        const response = await importArticlePackage(item.payload, batchId);
        results.push({ filename: item.filename, response });
        if ('success' in response && response.success) {
          created += 1;
        } else if (response.status === 'existing_article') {
          existing += 1;
          skipped += 1;
        } else {
          failed += 1;
        }
      } catch (err) {
        failed += 1;
        results.push({
          filename: item.filename,
          response: { success: false, status: 'error', errors: [{ path: '', message: err instanceof Error ? err.message : 'Import failed' }] },
        });
      }
    }

    const batchResult = { created, skipped, failed, existing, results };
    setImportResult(batchResult);
    setStep('complete');

    const status = failed > 0 ? 'Partial' : skipped > 0 ? 'Needs Review' : 'SEO Ready';
    try {
      await recordImportBatch({ id: batchId, status });
    } catch {
      /* Dashboard will still infer batches from post meta. */
    }

    setBusy(false);
  }

  const reset = useCallback(() => {
    setFiles([]);
    setAnalysis(null);
    setOptimize(null);
    setImportResult(null);
    setStep('upload');
    setError(null);
  }, []);

  return (
    <div className="revit-publisher-panel revit-publisher-dark">
      <PageHeader title="Batch Import" />
      <StepIndicator current={step} />

      {error && <SectionError message={error} onRetry={() => setError(null)} />}

      {step === 'upload' && (
        <>
          <div className="revit-publisher-card">
            <h2>Upload Article Packages</h2>
            <DropZone onFiles={processFiles} label="Drag article JSON files here" />
            {busy && <LoadingBlock label="Validating files…" />}
          </div>

          {files.length > 0 && (
            <div className="revit-publisher-card">
              <h3>File Validation</h3>
              <table className="revit-data-table">
                <thead>
                  <tr>
                    <th>File</th>
                    <th>Title</th>
                    <th>Vehicle</th>
                    <th>Type</th>
                    <th>Status</th>
                  </tr>
                </thead>
                <tbody>
                  {files.map((item) => (
                    <tr key={item.id}>
                      <td>{item.filename}</td>
                      <td>{item.preview?.article.title ?? '—'}</td>
                      <td>{item.preview?.vehicle ?? '—'}</td>
                      <td>{item.preview?.article.article_type ?? '—'}</td>
                      <td>
                        <ArticleStatus status={item.status} />
                        <span className="revit-publisher-muted revit-file-detail">{item.statusDetail}</span>
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
              {validItems.length > 0 && (
                <div className="revit-publisher-actions">
                  <PrimaryButton onClick={runAnalysis} disabled={busy}>
                    Analyze Batch
                  </PrimaryButton>
                </div>
              )}
            </div>
          )}

          <details className="revit-publisher-card revit-advanced-panel">
            <summary>Advanced — Paste JSON manually / Single article</summary>
            {showManual || files.length === 0 ? (
              <ImportPage embedded />
            ) : (
              <SecondaryButton onClick={() => setShowManual(true)}>Open single-article import</SecondaryButton>
            )}
          </details>
        </>
      )}

      {step === 'analyze' && analysis && (
        <div className="revit-publisher-card">
          <h2>{analysis.articleCount} articles · {analysis.vehicles.size} vehicles</h2>
          {[...analysis.vehicles.values()].map((vehicle) => (
            <div key={vehicle.label} className="revit-batch-vehicle-group">
              <h3>Vehicle: {vehicle.label}</h3>
              <h4>Clusters</h4>
              <ul className="revit-publisher-stats">
                {[...vehicle.clusters.entries()].map(([key, count]) => (
                  <li key={key}>
                    <span>{key.replace(/_/g, ' ')}</span>
                    <span>{count}</span>
                  </li>
                ))}
              </ul>
            </div>
          ))}
          <ul className="revit-publisher-stats">
            <li><span>SEO Metadata</span><span>{analysis.seoComplete} / {analysis.seoTotal} complete</span></li>
            <li><span>Internal Links</span><span>{analysis.plannedLinks} planned</span></li>
            <li><span>Potential Overlap</span><span>{analysis.overlapWarnings + analysis.duplicateKeys.length}</span></li>
            <li><span>Existing in WordPress</span><span>{analysis.existingArticles}</span></li>
            <li><span>New drafts</span><span>{analysis.newArticles}</span></li>
          </ul>
          {analysis.duplicateKeys.length > 0 && (
            <p className="revit-warning-inline">Duplicate article keys: {analysis.duplicateKeys.join(', ')}</p>
          )}
          <div className="revit-publisher-actions">
            <PrimaryButton onClick={runOptimize}>Continue to Optimize</PrimaryButton>
            <SecondaryButton onClick={() => setStep('upload')}>Back</SecondaryButton>
          </div>
        </div>
      )}

      {step === 'optimize' && optimize && (
        <div className="revit-publisher-card">
          <h2>Ready to Optimize</h2>
          <p>{analysis?.articleCount ?? 0} Articles</p>
          <h3>SEO Structure</h3>
          <p>✓ {optimize.seoGood} good · ⚠ {optimize.seoNeedsNormalization} need normalization</p>
          <h3>Internal Linking</h3>
          <p>{optimize.plannedLinks} planned · {optimize.resolvedLinks} resolved · {optimize.pendingLinks} pending</p>
          <h3>Metadata</h3>
          <p>{optimize.metadataComplete} complete · {optimize.metadataNeedsAttention} need attention</p>
          <h3>Topic Overlap</h3>
          <p>{optimize.overlapWarnings} warnings</p>
          <div className="revit-publisher-actions">
            <PrimaryButton onClick={() => setStep('import')}>Continue to Import</PrimaryButton>
          </div>
        </div>
      )}

      {step === 'import' && analysis && (
        <div className="revit-publisher-card">
          <h2>Ready to Import</h2>
          <p>{validItems.length} Articles</p>
          <p>{analysis.newArticles} New Drafts · {analysis.updateCandidates} Existing Article Updates</p>
          {[...analysis.vehicles.values()].map((v) => (
            <p key={v.label}>Vehicle: {v.label}</p>
          ))}

          {validItems.filter((i) => i.preview?.existing_article).map((item) => (
            <div key={item.id} className="revit-existing-row">
              <strong>{item.preview!.article.title}</strong>
              <select
                value={skipExisting[item.preview!.article.article_key] ?? 'skip'}
                onChange={(e) =>
                  setSkipExisting((prev) => ({
                    ...prev,
                    [item.preview!.article.article_key]: e.target.value as 'skip' | 'update',
                  }))
                }
              >
                <option value="skip">Skip</option>
                <option value="update">Review update (import as new draft attempt)</option>
              </select>
            </div>
          ))}

          <div className="revit-publisher-actions">
            <PrimaryButton onClick={runImport} disabled={busy}>
              {busy ? 'Importing…' : 'Import All as Drafts'}
            </PrimaryButton>
          </div>
        </div>
      )}

      {step === 'complete' && importResult && (
        <div className="revit-publisher-card revit-publisher-result--success">
          <h2>Import Complete</h2>
          <p>{importResult.created} Drafts Created</p>
          <p>{importResult.skipped} Existing Articles Skipped</p>
          <p>{importResult.failed} Failed</p>
          <ul className="revit-publisher-list">
            <li>SEO Metadata ✓</li>
            <li>Vehicle Taxonomy ✓</li>
            <li>Internal Link Plan ✓</li>
            <li>Cluster Structure ✓</li>
          </ul>
          <div className="revit-publisher-actions revit-action-row">
            <PrimaryButton href="edit.php?post_status=draft&post_type=post">Review Drafts</PrimaryButton>
            <SecondaryButton href={adminUrl('vehicles')}>View Vehicle</SecondaryButton>
            <SecondaryButton onClick={reset}>Import Another Batch</SecondaryButton>
          </div>
        </div>
      )}

      {files.length === 0 && step === 'upload' && (
        <EmptyState
          title="No article batches yet"
          description="Upload your first RevIt article package JSON files to get started."
        />
      )}
    </div>
  );
}
