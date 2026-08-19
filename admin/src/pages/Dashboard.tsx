import { getAdminConfig } from '../api/validation';

export function Dashboard() {
  const config = getAdminConfig();

  return (
    <div className="revit-publisher-panel">
      <h1>RevIt Publisher</h1>
      <p className="revit-publisher-muted">Automotive SEO &amp; Content Intelligence</p>

      <div className="revit-publisher-card">
        <h2>Foundation Status</h2>
        <ul className="revit-publisher-list">
          <li>
            <strong>Version:</strong> {config.version}
          </li>
          <li>
            <strong>Article Package Schema:</strong> {config.schemaVersion}
          </li>
          <li>
            <strong>Importer:</strong> Foundation Ready
          </li>
        </ul>
      </div>

      <div className="revit-publisher-card">
        <h2>Phase 0 Scope</h2>
        <p>
          This release validates <code>revit-article-v1</code> packages only.
          WordPress post creation and cluster automation are planned for later phases.
        </p>
      </div>
    </div>
  );
}
