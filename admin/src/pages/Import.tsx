import { ChangeEvent, FormEvent, useRef, useState } from 'react';
import {
  importArticlePackage,
  MAX_JSON_FILE_SIZE,
  previewArticlePackage,
} from '../api/article-packages';
import { applyUpdate, updatePreview } from '../api/intelligence';
import {
  ImportResponse,
  PreviewResponse,
  UpdateApplyFailure,
  UpdateApplySuccess,
  UpdateMode,
  UpdatePreviewChanged,
  UpdatePreviewResponse,
  ValidationFailure,
} from '../types/article-package';

function formatArticleType(type: string): string {
  return type.replace(/_/g, ' ').replace(/\b\w/g, (c) => c.toUpperCase());
}

function formatStatus(status: string): string {
  return status.charAt(0).toUpperCase() + status.slice(1);
}

function formatDiffLabel(key: string): string {
  return key.replace(/_/g, ' ').replace(/\b\w/g, (c) => c.toUpperCase());
}

function summarizeDiff(diff: UpdatePreviewChanged['diff']): string[] {
  const lines: string[] = [];

  Object.entries(diff.seo ?? {}).forEach(([key, value]) => {
    if (value.changed) {
      lines.push(`${formatDiffLabel(key)} — Changed`);
    }
  });

  Object.entries(diff.article ?? {}).forEach(([key, value]) => {
    if (value.changed) {
      lines.push(`${formatDiffLabel(key)} — Changed`);
    }
  });

  if (diff.content?.changed) {
    const added = diff.content.blocks_added ?? 0;
    const removed = diff.content.blocks_removed ?? 0;
    lines.push(`Content Blocks — +${added} / -${removed}`);
  }

  if (diff.relationships?.internal_links?.changed) {
    const added = diff.relationships.internal_links.added ?? 0;
    lines.push(`Internal Links — +${added}`);
  }

  if (diff.vehicle?.changed === false) {
    lines.push('Vehicle Identity — Unchanged');
  } else if (diff.vehicle?.changed) {
    lines.push('Vehicle Identity — Changed');
  }

  return lines;
}

export function ImportPage({ embedded = false }: { embedded?: boolean }) {
  const [jsonInput, setJsonInput] = useState('');
  const [preview, setPreview] = useState<PreviewResponse | null>(null);
  const [importResult, setImportResult] = useState<ImportResponse | null>(null);
  const [updatePreviewResult, setUpdatePreviewResult] = useState<UpdatePreviewResponse | null>(null);
  const [updateResult, setUpdateResult] = useState<UpdateApplySuccess | UpdateApplyFailure | null>(null);
  const [updateMode, setUpdateMode] = useState<UpdateMode>('full');
  const [parseError, setParseError] = useState<string | null>(null);
  const [isPreviewing, setIsPreviewing] = useState(false);
  const [isImporting, setIsImporting] = useState(false);
  const [isUpdatePreviewing, setIsUpdatePreviewing] = useState(false);
  const [isUpdating, setIsUpdating] = useState(false);
  const fileInputRef = useRef<HTMLInputElement>(null);

  function parsePayload(): unknown | null {
    setParseError(null);
    try {
      return JSON.parse(jsonInput);
    } catch (error) {
      setParseError(
        error instanceof Error ? error.message : 'Unable to parse JSON input.',
      );
      return null;
    }
  }

  async function handlePreview(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    setPreview(null);
    setImportResult(null);
    setUpdatePreviewResult(null);
    setUpdateResult(null);

    const payload = parsePayload();
    if (null === payload) {
      return;
    }

    setIsPreviewing(true);
    try {
      const response = await previewArticlePackage(payload);
      setPreview(response);
    } catch (error) {
      setParseError(
        error instanceof Error ? error.message : 'Preview request failed.',
      );
    } finally {
      setIsPreviewing(false);
    }
  }

  async function handleImport() {
    const payload = parsePayload();
    if (null === payload) {
      return;
    }

    setIsImporting(true);
    setImportResult(null);

    try {
      const response = await importArticlePackage(payload);
      setImportResult(response);
    } catch (error) {
      setParseError(
        error instanceof Error ? error.message : 'Import request failed.',
      );
    } finally {
      setIsImporting(false);
    }
  }

  async function handleUpdatePreview() {
    const payload = parsePayload();
    const previewValid = preview?.valid === true ? preview : null;
    if (null === payload || !previewValid?.existing_post_id) {
      return;
    }

    setIsUpdatePreviewing(true);
    setUpdatePreviewResult(null);
    setUpdateResult(null);

    try {
      const response = (await updatePreview(
        previewValid.existing_post_id,
        payload,
        updateMode,
      )) as UpdatePreviewResponse;
      setUpdatePreviewResult(response);
    } catch (error) {
      setParseError(
        error instanceof Error ? error.message : 'Update preview request failed.',
      );
    } finally {
      setIsUpdatePreviewing(false);
    }
  }

  async function handleApplyUpdate() {
    const payload = parsePayload();
    const previewValid = preview?.valid === true ? preview : null;
    if (null === payload || !previewValid?.existing_post_id) {
      return;
    }

    setIsUpdating(true);
    setUpdateResult(null);

    try {
      const response = (await applyUpdate(
        previewValid.existing_post_id,
        payload,
        updateMode,
      )) as UpdateApplySuccess | UpdateApplyFailure;
      setUpdateResult(response);
    } catch (error) {
      setParseError(
        error instanceof Error ? error.message : 'Update request failed.',
      );
    } finally {
      setIsUpdating(false);
    }
  }

  function handleFileChange(event: ChangeEvent<HTMLInputElement>) {
    const file = event.target.files?.[0];
    if (!file) {
      return;
    }

    if (!file.name.toLowerCase().endsWith('.json')) {
      setParseError('Please select a .json file.');
      event.target.value = '';
      return;
    }

    if (file.size > MAX_JSON_FILE_SIZE) {
      setParseError('JSON file exceeds the 5 MB limit.');
      event.target.value = '';
      return;
    }

    const reader = new FileReader();
    reader.onload = () => {
      setJsonInput(String(reader.result ?? ''));
      setPreview(null);
      setImportResult(null);
      setUpdatePreviewResult(null);
      setUpdateResult(null);
      setParseError(null);
    };
    reader.onerror = () => {
      setParseError('Unable to read the selected file.');
    };
    reader.readAsText(file);
    event.target.value = '';
  }

  function resetForm() {
    setJsonInput('');
    setPreview(null);
    setImportResult(null);
    setUpdatePreviewResult(null);
    setUpdateResult(null);
    setParseError(null);
  }

  const previewValid = preview?.valid === true ? preview : null;
  const previewInvalid = preview && !preview.valid ? (preview as ValidationFailure) : null;
  const updateChanged =
    updatePreviewResult?.valid === true && updatePreviewResult.status === 'changed'
      ? (updatePreviewResult as UpdatePreviewChanged)
      : null;
  const updateUnchanged =
    updatePreviewResult?.valid === true && updatePreviewResult.status === 'unchanged'
      ? updatePreviewResult
      : null;

  return (
    <div className={embedded ? 'revit-embedded-import' : 'revit-publisher-panel revit-publisher-dark'}>
      {!embedded && (
        <>
          <h1>Single Article JSON</h1>
          <p className="revit-publisher-muted">
            Paste or upload a <code>revit-article-v1</code> package to validate, preview, import, or update.
          </p>
        </>
      )}

      <form className="revit-publisher-card" onSubmit={handlePreview}>
        <label htmlFor="revit-article-json">Article Package JSON</label>
        <textarea
          id="revit-article-json"
          rows={16}
          value={jsonInput}
          onChange={(event) => {
            setJsonInput(event.target.value);
            setPreview(null);
            setImportResult(null);
            setUpdatePreviewResult(null);
            setUpdateResult(null);
          }}
          placeholder="Paste revit-article-v1 JSON here..."
        />

        <div className="revit-publisher-file-row">
          <input
            ref={fileInputRef}
            type="file"
            accept=".json,application/json"
            onChange={handleFileChange}
            className="revit-publisher-file-input"
          />
          <span className="revit-publisher-muted">JSON files up to 5 MB</span>
        </div>

        <div className="revit-publisher-actions">
          <button type="submit" disabled={isPreviewing || jsonInput.trim() === ''}>
            {isPreviewing ? 'Validating…' : 'Validate & Preview'}
          </button>
        </div>
      </form>

      {parseError && (
        <div className="revit-publisher-result revit-publisher-result--error">
          <h2>Error</h2>
          <p>{parseError}</p>
        </div>
      )}

      {previewInvalid && (
        <div className="revit-publisher-result revit-publisher-result--error">
          <h2>Validation Failed</h2>
          <ul className="revit-publisher-errors">
            {previewInvalid.errors.map((error) => (
              <li key={`${error.path}-${error.message}`}>
                <code>{error.path || '(root)'}</code> — {error.message}
              </li>
            ))}
          </ul>
        </div>
      )}

      {previewValid && !importResult && !updateResult?.success && (
        <div className="revit-publisher-card revit-publisher-preview">
          <h2>{previewValid.existing_article ? 'Existing Article Detected' : 'Ready to Import'}</h2>

          <dl className="revit-publisher-dl">
            <dt>Title</dt>
            <dd>{previewValid.article.title}</dd>
            <dt>Vehicle</dt>
            <dd>{previewValid.vehicle || '—'}</dd>
            <dt>Article Type</dt>
            <dd>{formatArticleType(previewValid.article.article_type)}</dd>
            <dt>Cluster</dt>
            <dd>{previewValid.cluster || '—'}</dd>
            <dt>Primary Topic</dt>
            <dd>{previewValid.seo.primary_topic}</dd>
            <dt>Planned Internal Links</dt>
            <dd>{previewValid.relationships.internal_links}</dd>
            <dt>Related Articles</dt>
            <dd>{previewValid.relationships.related_articles}</dd>
            <dt>Publishing Status</dt>
            <dd>{formatStatus(previewValid.publishing.status)}</dd>
          </dl>

          {previewValid.existing_article ? (
            <>
              <p className="revit-publisher-muted">
                An article with this key already exists (Post ID: {previewValid.existing_post_id}).
                Review changes before applying an update.
              </p>

              <div className="revit-publisher-actions">
                <label htmlFor="update-mode">Update mode</label>
                <select
                  id="update-mode"
                  value={updateMode}
                  onChange={(event) => setUpdateMode(event.target.value as UpdateMode)}
                >
                  <option value="full">Full package update</option>
                  <option value="seo">SEO only</option>
                  <option value="relationships">Relationships only</option>
                </select>
                <button type="button" onClick={handleUpdatePreview} disabled={isUpdatePreviewing}>
                  {isUpdatePreviewing ? 'Reviewing…' : 'Review Changes'}
                </button>
              </div>
            </>
          ) : (
            <div className="revit-publisher-actions">
              <button type="button" onClick={handleImport} disabled={isImporting}>
                {isImporting
                  ? 'Importing…'
                  : previewValid.publishing.status === 'draft'
                    ? 'Import as Draft'
                    : 'Import Article'}
              </button>
            </div>
          )}
        </div>
      )}

      {updateUnchanged && (
        <div className="revit-publisher-result revit-publisher-result--success">
          <h2>No Changes Detected</h2>
          <p>{updateUnchanged.message}</p>
        </div>
      )}

      {updateChanged && (
        <div className="revit-publisher-card">
          <h2>Article Update Available</h2>

          {updateChanged.manual_edits && (
            <div className="revit-publisher-result revit-publisher-result--error">
              <p>
                ⚠ Manual edits detected — this article appears to have been edited in WordPress after
                the last RevIt import. Choose update mode carefully.
              </p>
            </div>
          )}

          <ul className="revit-publisher-list">
            {summarizeDiff(updateChanged.diff).map((line) => (
              <li key={line}>{line}</li>
            ))}
          </ul>

          <p className="revit-publisher-muted">{updateChanged.revision_note}</p>

          <div className="revit-publisher-actions">
            <button type="button" onClick={handleApplyUpdate} disabled={isUpdating}>
              {isUpdating ? 'Applying…' : `Apply ${formatDiffLabel(updateMode)} Update`}
            </button>
          </div>
        </div>
      )}

      {importResult?.success && (
        <div className="revit-publisher-result revit-publisher-result--success">
          <h2>Article Imported</h2>
          <ul className="revit-publisher-list">
            <li>
              <strong>Post ID:</strong> {importResult.post_id}
            </li>
            <li>
              <strong>Status:</strong> {formatStatus(importResult.post_status)}
            </li>
          </ul>
          <div className="revit-publisher-actions">
            <a className="button button-primary" href={importResult.edit_url}>
              Edit Article
            </a>
            <button type="button" onClick={resetForm}>
              Import Another
            </button>
          </div>
        </div>
      )}

      {updateResult?.success && (
        <div className="revit-publisher-result revit-publisher-result--success">
          <h2>{updateResult.status === 'unchanged' ? 'No Changes Applied' : 'Article Updated'}</h2>
          <ul className="revit-publisher-list">
            <li>
              <strong>Post ID:</strong> {updateResult.post_id}
            </li>
          </ul>
          <div className="revit-publisher-actions">
            {updateResult.edit_url && (
              <a className="button button-primary" href={updateResult.edit_url}>
                Edit Article
              </a>
            )}
            <button type="button" onClick={resetForm}>
              Import Another
            </button>
          </div>
        </div>
      )}

      {importResult &&
        !importResult.success &&
        importResult.status === 'existing_article' &&
        'post_id' in importResult && (
        <div className="revit-publisher-result revit-publisher-result--error">
          <h2>Duplicate Article Key</h2>
          <p>
            Article key <code>{importResult.article_key}</code> already exists as Post ID{' '}
            {importResult.post_id}.
          </p>
          <div className="revit-publisher-actions">
            <a className="button" href={importResult.edit_url}>
              Edit Existing Article
            </a>
          </div>
        </div>
      )}

      {importResult && !importResult.success && importResult.status !== 'existing_article' && 'errors' in importResult && (
        <div className="revit-publisher-result revit-publisher-result--error">
          <h2>Import Failed</h2>
          {importResult.errors && (
            <ul className="revit-publisher-errors">
              {importResult.errors.map((error: { path: string; message: string }) => (
                <li key={`${error.path}-${error.message}`}>
                  <code>{error.path || '(root)'}</code> — {error.message}
                </li>
              ))}
            </ul>
          )}
        </div>
      )}

      {updateResult && !updateResult.success && (
        <div className="revit-publisher-result revit-publisher-result--error">
          <h2>Update Failed</h2>
          <p>{updateResult.message ?? 'Unable to apply update.'}</p>
        </div>
      )}
    </div>
  );
}
