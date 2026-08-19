import { PageHeader } from '../components/PageHeader';
import { adminUrl } from '../lib/api-client';

const LINKS = [
  { label: 'Content Planner', key: 'planner' as const, description: 'Plan clusters and coverage gaps.' },
  { label: 'Content Graph', key: 'graph' as const, description: 'Visualize article relationships.' },
  { label: 'Needs Attention', key: 'attention' as const, description: 'Open SEO and content issues.' },
  { label: 'Audits', key: 'audits' as const, description: 'Site-wide audit snapshots.' },
  { label: 'Search Performance', key: 'searchPerformance' as const, description: 'Google Search Console metrics.' },
  { label: 'Editorial Queue', key: 'editorial' as const, description: 'Prioritized editorial work.' },
  { label: 'Redirects', key: 'redirects' as const, description: 'Manage URL redirects.' },
  { label: '404 Monitor', key: 'notFound' as const, description: 'Track missing URLs.' },
  { label: 'System Health', key: 'systemHealth' as const, description: 'Environment and migration status.' },
  { label: 'Settings', key: 'settings' as const, description: 'Plugin configuration.' },
  { label: 'SEO Health (detailed)', key: 'seoHealth' as const, description: 'Deep diagnostics and SERP preview.' },
];

export function AdvancedPage() {
  return (
    <div className="revit-publisher-panel revit-publisher-dark">
      <PageHeader title="Advanced" />
      <p className="revit-publisher-muted">
        Power tools and diagnostics. Normal publishing workflow stays on Dashboard, Batch Import, Vehicles, and SEO.
      </p>
      <div className="revit-advanced-grid">
        {LINKS.map((link) => (
          <a key={link.key} className="revit-publisher-card revit-advanced-link" href={adminUrl(link.key)}>
            <strong>{link.label}</strong>
            <span className="revit-publisher-muted">{link.description}</span>
          </a>
        ))}
      </div>
    </div>
  );
}
