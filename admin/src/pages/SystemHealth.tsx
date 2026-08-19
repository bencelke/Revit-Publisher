import { useEffect, useState } from 'react';
import { exportBackup, fetchSystemHealth, runSystemHealthChecks, SystemHealthResponse } from '../api/system-health';
import { ProductHeader } from '../components/ProductHeader';

export function SystemHealthPage() {
  const [health, setHealth] = useState<SystemHealthResponse | null>(null);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    fetchSystemHealth().then(setHealth).catch((err: unknown) => {
      setError(err instanceof Error ? err.message : 'Failed to load system health.');
    });
  }, []);

  async function handleRunChecks() {
    await runSystemHealthChecks();
    setHealth(await fetchSystemHealth());
  }

  async function handleExportBackup() {
    const data = await exportBackup();
    const blob = new Blob([JSON.stringify(data, null, 2)], { type: 'application/json' });
    const url = URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.href = url;
    link.download = `revit-publisher-backup-${new Date().toISOString().slice(0, 10)}.json`;
    link.click();
    URL.revokeObjectURL(url);
  }

  return (
    <div className="revit-publisher-panel revit-publisher-dark">
      <ProductHeader title="System Health" />
      {error && <div className="revit-publisher-result revit-publisher-result--error"><p>{error}</p></div>}

      {health && (
        <>
          <div className="revit-publisher-toolbar">
            <button type="button" onClick={handleRunChecks}>Run Self-Test</button>
            <button type="button" onClick={handleExportBackup}>Export Backup</button>
          </div>

          <div className="revit-publisher-grid">
            <div className="revit-publisher-card">
              <h3>WordPress</h3>
              <div>WP {health.wordpress.version} · PHP {health.wordpress.php}</div>
              <div className="revit-publisher-muted">Permalink: {health.wordpress.permalink || 'default'}</div>
            </div>
            <div className="revit-publisher-card">
              <h3>Plugin</h3>
              <div>v{health.plugin.version} · DB v{health.plugin.db_version}</div>
            </div>
            <div className="revit-publisher-card">
              <h3>Search Console</h3>
              <div>{health.search_console.connected ? 'Connected' : health.search_console.diagnostic}</div>
              <div className="revit-publisher-muted">{health.search_console.property || 'No property'}</div>
            </div>
          </div>

          <div className="revit-publisher-card">
            <h3>Self-Test Checks</h3>
            {health.checks.map((check) => (
              <div key={check.id} className={`revit-health-check revit-health-check--${check.status}`}>
                <strong>{check.label}</strong>
                <span>{check.detail}</span>
              </div>
            ))}
          </div>

          <div className="revit-publisher-card">
            <h3>Storage</h3>
            <div className="revit-publisher-grid">
              {Object.entries(health.storage).map(([key, value]) => (
                <div key={key} className="revit-publisher-metric">
                  <span className="revit-publisher-stat-label">{key.replace(/_/g, ' ')}</span>
                  <span className="revit-publisher-stat-value">{value}</span>
                </div>
              ))}
            </div>
          </div>

          <div className="revit-publisher-card">
            <h3>Recent System Events</h3>
            {health.recent_events.slice(0, 10).map((event, index) => (
              <div key={`${event.event}-${index}`} className="revit-publisher-list-item">
                <strong>{event.event}</strong>
                <div className="revit-publisher-muted">{event.timestamp}</div>
              </div>
            ))}
          </div>
        </>
      )}
    </div>
  );
}
