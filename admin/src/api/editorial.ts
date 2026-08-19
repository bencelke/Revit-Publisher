import { getAdminConfig } from './article-packages';

async function apiRequest<T>(path: string, init?: RequestInit): Promise<T> {
  const config = getAdminConfig();
  const response = await fetch(`${config.restUrl}${path}`, {
    ...init,
    headers: {
      'Content-Type': 'application/json',
      'X-WP-Nonce': config.nonce,
      ...(init?.headers ?? {}),
    },
  });
  const data = await response.json();
  if (!response.ok) {
    throw new Error(data?.message ?? 'Request failed.');
  }
  return data as T;
}

export interface EditorialItem {
  id: number;
  title: string;
  action_type: string;
  priority_level: string;
  priority_score: number;
  status: string;
  vehicle: string;
  post_id: number;
  article_key: string;
  cluster_key: string;
  explanation: string;
  reasons: string[];
  next_step: string;
  notes: string;
  deferred_until: string;
  edit_url: string | null;
  gsc_metrics: { impressions?: number; clicks?: number; position?: number; ctr?: number } | null;
}

export interface EditorialTodaySummary {
  counts: Record<string, number>;
  items: EditorialItem[];
}

export function fetchEditorialQueue(params: Record<string, string> = {}) {
  const query = new URLSearchParams(params).toString();
  return apiRequest<EditorialItem[]>(`/editorial-queue${query ? `?${query}` : ''}`);
}

export function fetchEditorialToday() {
  return apiRequest<EditorialTodaySummary>('/editorial-queue/today');
}

export function reconcileEditorialQueue() {
  return apiRequest<{ success: boolean; candidates: number }>('/editorial-queue/reconcile', { method: 'POST' });
}

export function updateEditorialItem(id: number, payload: Record<string, unknown>) {
  return apiRequest<EditorialItem>(`/editorial-queue/${id}`, {
    method: 'PUT',
    body: JSON.stringify(payload),
  });
}

export function createEditorialItem(payload: Record<string, unknown>) {
  return apiRequest<EditorialItem>('/editorial-queue', {
    method: 'POST',
    body: JSON.stringify(payload),
  });
}

export function fetchRefreshExport(postId: number, reason = 'page2_opportunity') {
  return apiRequest<Record<string, unknown>>(`/search-console/posts/${postId}/refresh-export?reason=${reason}`);
}
