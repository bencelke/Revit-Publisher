import { StrictMode } from 'react';
import { createRoot } from 'react-dom/client';
import { Dashboard } from './pages/Dashboard';
import { ImportPage } from './pages/Import';
import './styles.css';

function mountApp() {
  const dashboardRoot = document.getElementById('revit-publisher-dashboard');
  const importRoot = document.getElementById('revit-publisher-import');

  if (dashboardRoot) {
    createRoot(dashboardRoot).render(
      <StrictMode>
        <Dashboard />
      </StrictMode>,
    );
  }

  if (importRoot) {
    createRoot(importRoot).render(
      <StrictMode>
        <ImportPage />
      </StrictMode>,
    );
  }
}

mountApp();
