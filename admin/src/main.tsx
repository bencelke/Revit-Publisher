import { StrictMode } from 'react';
import { createRoot } from 'react-dom/client';
import { ContentGraphPage } from './pages/ContentGraph';
import { Dashboard } from './pages/Dashboard';
import { ImportPage } from './pages/Import';
import { SettingsPage } from './pages/Settings';
import './styles.css';

function mount(id: string, node: JSX.Element) {
  const root = document.getElementById(id);
  if (root) {
    createRoot(root).render(<StrictMode>{node}</StrictMode>);
  }
}

mount('revit-publisher-dashboard', <Dashboard />);
mount('revit-publisher-import', <ImportPage />);
mount('revit-publisher-graph', <ContentGraphPage />);
mount('revit-publisher-settings', <SettingsPage />);
