import { ChangeEvent, FormEvent, useRef, useState } from 'react';
import {
  importArticlePackage,
  MAX_JSON_FILE_SIZE,
  previewArticlePackage,
} from '../api/validation';
import {
  ImportResponse,
  PreviewResponse,
  ValidationFailure,
} from '../types/article-package';

function formatArticleType(type: string): string {
  return type.replace(/_/g, ' ').replace(/\b\w/g, (c) => c.toUpperCase());
}

function formatStatus(status: string): string {
  return status.charAt(0).toUpperCase() + status.slice(1);
}

export function ImportPage() {
  const [jsonInput, setJsonInput] = useState('');
  const [preview, setPreview] = useState<PreviewResponse | null>(null);
  const [importResult, setImportResult] = useState<ImportResponse | null>(null);
  const [parseError, setParseError] = useState<string | null>(null);
  const [isPreviewing, setIsPreviewing] = useState(false);
  const [isImporting, setIsImporting] = useState(false);
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
    setParseError(null);
  }

  const previewValid = preview?.valid === true ? preview : null;
  const previewInvalid = preview && !preview.valid ? (preview as ValidationFailure) : null;

  return (
    <div className="revit-publisher-panel">
      <h1>Import Article Package</h1>
      <p className="revit-publisher-muted">
        Paste or upload a <code>revit-article-v1</code> package to validate, preview, and import.
      </p>

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

      {previewValid && !importResult && (
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
            <p className="revit-publisher-muted">
              An article with this key already exists (Post ID: {previewValid.existing_post_id}).
              Phase 1 does not overwrite existing articles.
            </p>
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
    </div>
  );
}
