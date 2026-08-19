import { getAdminConfig } from '../api/article-packages';

export function ProductHeader({ title }: { title?: string }) {
  const config = getAdminConfig();
  return (
    <header className="revit-product-header">
      <div className="revit-product-brand">
        <div className="revit-product-logo" aria-hidden="true">R</div>
        <div>
          <strong>RevIt Publisher</strong>
          <span className="revit-publisher-muted">Automotive SEO &amp; Content Intelligence</span>
        </div>
      </div>
      <div className="revit-product-meta">
        {title && <span>{title}</span>}
        <span className="revit-version-badge">v{config.version}</span>
      </div>
    </header>
  );
}
