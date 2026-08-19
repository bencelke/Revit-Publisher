import { getAdminConfig } from '../api/article-packages';

export function PageHeader({ title, action }: { title?: string; action?: React.ReactNode }) {
  const config = getAdminConfig();
  return (
    <header className="revit-page-header">
      <div className="revit-page-header__brand">
        <div className="revit-page-header__mark" aria-hidden="true">R</div>
        <div>
          <h1 className="revit-page-header__title">RevIt Publisher</h1>
          <p className="revit-page-header__subtitle">Automotive SEO &amp; Content Intelligence</p>
          {title && <p className="revit-page-header__screen">{title}</p>}
        </div>
      </div>
      <div className="revit-page-header__meta">
        {action}
        <span className="revit-version-badge">v{config?.version ?? '—'}</span>
      </div>
    </header>
  );
}
