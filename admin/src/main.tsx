import { StrictMode } from 'react';
import { createRoot } from 'react-dom/client';
import { AppShell } from './components/ErrorBoundary';
import { AdvancedPage } from './pages/Advanced';
import { AuditsPage } from './pages/Audits';
import { BatchImportPage } from './pages/BatchImport';
import { ContentGraphPage } from './pages/ContentGraph';
import { ContentPlannerPage } from './pages/ContentPlanner';
import { Dashboard } from './pages/Dashboard';
import { EditorialQueuePage } from './pages/EditorialQueue';
import { NeedsAttentionPage } from './pages/NeedsAttention';
import { NotFoundPage } from './pages/NotFoundMonitor';
import { RedirectsPage } from './pages/Redirects';
import { SearchPerformancePage } from './pages/SearchPerformance';
import { SeoPage } from './pages/Seo';
import { SeoHealthPage } from './pages/SeoHealth';
import { SettingsPage } from './pages/Settings';
import { SystemHealthPage } from './pages/SystemHealth';
import { VehiclesPage } from './pages/Vehicles';
import './styles.css';

function mount(id: string, node: JSX.Element) {
  const root = document.getElementById(id);
  if (root) {
    createRoot(root).render(<StrictMode><AppShell>{node}</AppShell></StrictMode>);
  }
}

mount('revit-publisher-dashboard', <Dashboard />);
mount('revit-publisher-import', <BatchImportPage />);
mount('revit-publisher-seo', <SeoPage />);
mount('revit-publisher-advanced', <AdvancedPage />);
mount('revit-publisher-planner', <ContentPlannerPage />);
mount('revit-publisher-graph', <ContentGraphPage />);
mount('revit-publisher-seo-health', <SeoHealthPage />);
mount('revit-publisher-search-performance', <SearchPerformancePage />);
mount('revit-publisher-editorial', <EditorialQueuePage />);
mount('revit-publisher-system-health', <SystemHealthPage />);
mount('revit-publisher-attention', <NeedsAttentionPage />);
mount('revit-publisher-audits', <AuditsPage />);
mount('revit-publisher-vehicles', <VehiclesPage />);
mount('revit-publisher-redirects', <RedirectsPage />);
mount('revit-publisher-404', <NotFoundPage />);
mount('revit-publisher-settings', <SettingsPage />);
