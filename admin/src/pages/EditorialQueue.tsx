import { useEffect, useState } from 'react';
import {
  EditorialItem,
  fetchEditorialQueue,
  fetchRefreshExport,
  reconcileEditorialQueue,
  updateEditorialItem,
} from '../api/editorial';
import { ProductHeader } from '../components/ProductHeader';

const TABS = [
  { id: 'today', label: 'Today' },
  { id: 'create_content', label: 'Create' },
  { id: 'refresh_content', label: 'Refresh' },
  { id: 'technical', label: 'Technical' },
  { id: 'fix_internal_links', label: 'Linking' },
  { id: 'review_article', label: 'Review' },
  { id: 'completed', label: 'Completed' },
] as const;

function actionLabel(type: string): string {
  return type.replace(/_/g, ' ').replace(/\b\w/g, (c) => c.toUpperCase());
}

export function EditorialQueuePage() {
  const [tab, setTab] = useState<string>('today');
  const [items, setItems] = useState<EditorialItem[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  async function load() {
    setLoading(true);
    setError(null);
    try {
      const params: Record<string, string> = { limit: '100' };
      if (tab === 'today') params.today = '1';
      else if (tab === 'completed') params.status = 'completed';
      else if (tab === 'technical') params.action_type = 'resolve_indexing';
      else params.action_type = tab;
      setItems(await fetchEditorialQueue(params));
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Failed to load queue.');
    } finally {
      setLoading(false);
    }
  }

  useEffect(() => {
    load();
  }, [tab]);

  async function handleReconcile() {
    await reconcileEditorialQueue();
    await load();
  }

  async function handleAction(item: EditorialItem, action: 'defer' | 'complete' | 'progress') {
    const payload: Record<string, unknown> = {};
    if (action === 'defer') {
      payload.status = 'deferred';
      payload.deferred_until = new Date(Date.now() + 7 * 86400000).toISOString().slice(0, 10);
    } else if (action === 'complete') {
      payload.status = 'completed';
    } else {
      payload.status = 'in_progress';
    }
    await updateEditorialItem(item.id, payload);
    await load();
  }

  async function handleExport(item: EditorialItem) {
    if (!item.post_id) return;
    const data = await fetchRefreshExport(item.post_id, item.action_type === 'refresh_content' ? 'page2_opportunity' : item.action_type);
    const blob = new Blob([JSON.stringify(data, null, 2)], { type: 'application/json' });
    const url = URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.href = url;
    link.download = `${item.article_key || item.id}-refresh-request.json`;
    link.click();
    URL.revokeObjectURL(url);
  }

  return (
    <div className="revit-publisher-panel revit-publisher-dark">
      <ProductHeader title="Editorial Queue" />
      <p className="revit-publisher-muted">Prioritized editorial work from content plans, SEO health, and Search Console.</p>

      <div className="revit-publisher-toolbar">
        <button type="button" onClick={handleReconcile}>Reconcile Queue</button>
      </div>

      <div className="revit-tab-row">
        {TABS.map((t) => (
          <button
            key={t.id}
            type="button"
            className={tab === t.id ? 'revit-tab revit-tab--active' : 'revit-tab'}
            onClick={() => setTab(t.id)}
          >
            {t.label}
          </button>
        ))}
      </div>

      {error && <div className="revit-publisher-result revit-publisher-result--error"><p>{error}</p></div>}
      {loading && <p>Loading…</p>}

      {!loading && items.map((item) => (
        <div key={item.id} className="revit-publisher-card revit-editorial-item">
          <div className="revit-editorial-item__header">
            <strong>{item.title}</strong>
            <span className={`revit-priority revit-priority--${item.priority_level}`}>{item.priority_level}</span>
          </div>
          <div className="revit-editorial-item__action">{actionLabel(item.action_type)}</div>
          {item.gsc_metrics && (
            <div className="revit-publisher-muted">
              Google: {item.gsc_metrics.impressions?.toLocaleString()} impressions · Position {item.gsc_metrics.position}
            </div>
          )}
          {item.cluster_key && <div className="revit-publisher-muted">Cluster: {item.cluster_key}</div>}
          <p>{item.explanation}</p>
          {item.reasons.length > 0 && (
            <ul className="revit-reason-list">
              {item.reasons.map((reason) => <li key={reason}>{reason}</li>)}
            </ul>
          )}
          <p className="revit-publisher-muted">{item.next_step}</p>
          <div className="revit-publisher-toolbar">
            {item.edit_url && <a href={item.edit_url} className="button">Open</a>}
            {item.post_id > 0 && <button type="button" onClick={() => handleExport(item)}>Export Request</button>}
            <button type="button" onClick={() => handleAction(item, 'defer')}>Defer</button>
            <button type="button" onClick={() => handleAction(item, 'complete')}>Complete</button>
          </div>
        </div>
      ))}

      {!loading && items.length === 0 && (
        <div className="revit-publisher-card">
          <p>No editorial items in this view. Run reconcile after audit or Search Console sync.</p>
        </div>
      )}
    </div>
  );
}
