import { FormEvent, useState } from 'react';
import { validateArticlePackage } from '../api/validation';
import { ValidationResponse } from '../types/article-package';

export function ImportPage() {
  const [jsonInput, setJsonInput] = useState('');
  const [result, setResult] = useState<ValidationResponse | null>(null);
  const [parseError, setParseError] = useState<string | null>(null);
  const [isSubmitting, setIsSubmitting] = useState(false);

  async function handleSubmit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    setParseError(null);
    setResult(null);

    let payload: unknown;

    try {
      payload = JSON.parse(jsonInput);
    } catch (error) {
      setParseError(
        error instanceof Error ? error.message : 'Unable to parse JSON input.',
      );
      return;
    }

    setIsSubmitting(true);

    try {
      const response = await validateArticlePackage(payload);
      setResult(response);
    } catch (error) {
      setParseError(
        error instanceof Error ? error.message : 'Validation request failed.',
      );
    } finally {
      setIsSubmitting(false);
    }
  }

  return (
    <div className="revit-publisher-panel">
      <h1>Import</h1>
      <p className="revit-publisher-muted">
        Paste a <code>revit-article-v1</code> package to validate the contract.
      </p>

      <form className="revit-publisher-card" onSubmit={handleSubmit}>
        <label htmlFor="revit-article-json">Article Package JSON</label>
        <textarea
          id="revit-article-json"
          rows={18}
          value={jsonInput}
          onChange={(event) => setJsonInput(event.target.value)}
          placeholder="Paste revit-article-v1 JSON here..."
        />

        <div className="revit-publisher-actions">
          <button type="submit" disabled={isSubmitting || jsonInput.trim() === ''}>
            {isSubmitting ? 'Validating…' : 'Validate Package'}
          </button>
        </div>
      </form>

      {parseError && (
        <div className="revit-publisher-result revit-publisher-result--error">
          <h2>Parse Error</h2>
          <p>{parseError}</p>
        </div>
      )}

      {result?.valid && (
        <div className="revit-publisher-result revit-publisher-result--success">
          <h2>Valid Package</h2>
          <ul className="revit-publisher-list">
            <li>
              <strong>Schema:</strong> {result.schema_version}
            </li>
            <li>
              <strong>Article Key:</strong> {result.article_key}
            </li>
          </ul>
          {result.warnings.length > 0 && (
            <>
              <h3>Warnings</h3>
              <ul className="revit-publisher-errors">
                {result.warnings.map((warning) => (
                  <li key={`${warning.path}-${warning.message}`}>
                    <code>{warning.path || '(root)'}</code> — {warning.message}
                  </li>
                ))}
              </ul>
            </>
          )}
        </div>
      )}

      {result && !result.valid && (
        <div className="revit-publisher-result revit-publisher-result--error">
          <h2>Validation Failed</h2>
          <ul className="revit-publisher-errors">
            {result.errors.map((error) => (
              <li key={`${error.path}-${error.message}`}>
                <code>{error.path || '(root)'}</code> — {error.message}
              </li>
            ))}
          </ul>
        </div>
      )}
    </div>
  );
}
