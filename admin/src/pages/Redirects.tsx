import { FormEvent, useEffect, useState } from 'react';
import { createRedirect, fetchRedirects } from '../api/operations';

interface RedirectRow {
  redirect_id: number;
  source_path: string;
  target_post_id: number;
  target_url: string;
  target_permalink?: string;
  status: string;
  reason: string;
}

export function RedirectsPage() {
  const [redirects, setRedirects] = useState<RedirectRow[]>([]);
  const [source, setSource] = useState('');
  const [targetPostId, setTargetPostId] = useState('');
  const [reason, setReason] = useState('');

  function load() {
    fetchRedirects().then((data) => setRedirects(data as RedirectRow[]));
  }

  useEffect(() => { load(); }, []);

  async function handleSubmit(e: FormEvent) {
    e.preventDefault();
    await createRedirect({
      source_path: source,
      target_post_id: Number(targetPostId),
      reason,
      status: 'active',
    });
    setSource('');
    setTargetPostId('');
    setReason('');
    load();
  }

  return (
    <div className="revit-publisher-panel revit-publisher-dark">
      <h1>Redirects</h1>
      <p className="revit-publisher-muted">RevIt 301 redirects are independent of Yoast/Rank Math redirect systems.</p>
      <form className="revit-publisher-card" onSubmit={handleSubmit}>
        <label>Source path<input value={source} onChange={(e) => setSource(e.target.value)} placeholder="/old-path" required /></label>
        <label>Target post ID<input value={targetPostId} onChange={(e) => setTargetPostId(e.target.value)} required /></label>
        <label>Reason<input value={reason} onChange={(e) => setReason(e.target.value)} /></label>
        <button type="submit">Create Redirect</button>
      </form>
      <table className="revit-publisher-table">
        <thead><tr><th>Source</th><th>Target</th><th>Status</th><th>Reason</th></tr></thead>
        <tbody>
          {redirects.map((r) => (
            <tr key={r.redirect_id}>
              <td>{r.source_path}</td>
              <td>{r.target_permalink || r.target_url || r.target_post_id}</td>
              <td>{r.status}</td>
              <td>{r.reason}</td>
            </tr>
          ))}
        </tbody>
      </table>
    </div>
  );
}
