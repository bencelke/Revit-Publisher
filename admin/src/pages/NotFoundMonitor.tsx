import { useEffect, useState } from 'react';
import { createRedirect, fetch404s } from '../api/operations';

interface EntryRow {
  entry_id: number;
  path: string;
  hits: number;
  last_seen: string;
  referrer: string;
  status: string;
}

export function NotFoundPage() {
  const [enabled, setEnabled] = useState(false);
  const [entries, setEntries] = useState<EntryRow[]>([]);

  function load() {
    fetch404s().then((data) => {
      setEnabled(data.enabled);
      setEntries(data.entries as EntryRow[]);
    });
  }

  useEffect(() => { load(); }, []);

  async function createFrom404(entry: EntryRow) {
    const target = prompt('Target post ID for redirect:');
    if (!target) return;
    await createRedirect({ source_path: entry.path, target_post_id: Number(target), reason: 'From 404 monitor', status: 'active' });
    load();
  }

  if (!enabled) {
    return (
      <div className="revit-publisher-panel revit-publisher-dark">
        <h1>404 Monitor</h1>
        <p className="revit-publisher-muted">404 monitoring is disabled. Enable it in Settings.</p>
      </div>
    );
  }

  return (
    <div className="revit-publisher-panel revit-publisher-dark">
      <h1>404 Monitor</h1>
      <p className="revit-publisher-muted">Aggregated paths only — no IP addresses stored.</p>
      <table className="revit-publisher-table">
        <thead><tr><th>Path</th><th>Hits</th><th>Last Seen</th><th>Referrer</th><th></th></tr></thead>
        <tbody>
          {entries.map((entry) => (
            <tr key={entry.entry_id}>
              <td>{entry.path}</td>
              <td>{entry.hits}</td>
              <td>{entry.last_seen}</td>
              <td>{entry.referrer || '—'}</td>
              <td><button type="button" onClick={() => createFrom404(entry)}>Create Redirect</button></td>
            </tr>
          ))}
        </tbody>
      </table>
    </div>
  );
}
