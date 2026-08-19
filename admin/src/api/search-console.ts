import { getAdminConfig } from './article-packages';
import {
  GscClusterRow,
  GscConnectResponse,
  GscInspectionResult,
  GscOpportunity,
  GscPageRow,
  GscPostPerformance,
  GscProperty,
  GscQueryRow,
  GscRefreshExport,
  GscSitemapsResponse,
  GscStatus,
  GscSummary,
  GscSyncResult,
  GscVehicleRow,
  GscWindow,
} from '../types/search-console';

async function parseError(response: Response): Promise<string> {
  const body = (await response.json().catch(() => ({}))) as { message?: string };
  return body.message ?? `Request failed (${response.status}).`;
}

async function apiGet<T>(endpoint: string): Promise<T> {
  const config = getAdminConfig();
  const response = await fetch(`${config.restUrl}${endpoint}`, {
    headers: { 'X-WP-Nonce': config.nonce },
  });
  if (!response.ok) {
    throw new Error(await parseError(response));
  }
  return (await response.json()) as T;
}

async function apiPost<T>(endpoint: string, payload?: unknown): Promise<T> {
  const config = getAdminConfig();
  const response = await fetch(`${config.restUrl}${endpoint}`, {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      'X-WP-Nonce': config.nonce,
    },
    body: payload !== undefined ? JSON.stringify(payload) : undefined,
  });
  if (!response.ok) {
    throw new Error(await parseError(response));
  }
  return (await response.json()) as T;
}

async function apiPut<T>(endpoint: string, payload: unknown): Promise<T> {
  const config = getAdminConfig();
  const response = await fetch(`${config.restUrl}${endpoint}`, {
    method: 'PUT',
    headers: {
      'Content-Type': 'application/json',
      'X-WP-Nonce': config.nonce,
    },
    body: JSON.stringify(payload),
  });
  if (!response.ok) {
    throw new Error(await parseError(response));
  }
  return (await response.json()) as T;
}

export async function fetchGscStatus(): Promise<GscStatus> {
  return apiGet<GscStatus>('/search-console/status');
}

export async function connectGsc(useFixture = false): Promise<GscConnectResponse> {
  return apiPost<GscConnectResponse>('/search-console/connect', { use_fixture: useFixture });
}

export async function disconnectGsc(): Promise<{ success: boolean }> {
  return apiPost<{ success: boolean }>('/search-console/disconnect');
}

export async function fetchGscProperties(): Promise<GscProperty[]> {
  return apiGet<GscProperty[]>('/search-console/properties');
}

export async function setGscProperty(property: string): Promise<GscStatus> {
  return apiPut<GscStatus>('/search-console/property', { property });
}

export async function syncGsc(): Promise<GscSyncResult> {
  return apiPost<GscSyncResult>('/search-console/sync');
}

export async function fetchGscSummary(window: GscWindow = '28d'): Promise<GscSummary> {
  return apiGet<GscSummary>(`/search-console/summary?window=${window}`);
}

export async function fetchGscPages(
  window: GscWindow = '28d',
  filters?: { vehicle?: string; article_type?: string },
): Promise<GscPageRow[]> {
  const params = new URLSearchParams({ window });
  if (filters?.vehicle) params.set('vehicle', filters.vehicle);
  if (filters?.article_type) params.set('article_type', filters.article_type);
  return apiGet<GscPageRow[]>(`/search-console/pages?${params.toString()}`);
}

export async function fetchGscVehicles(window: GscWindow = '28d'): Promise<GscVehicleRow[]> {
  return apiGet<GscVehicleRow[]>(`/search-console/vehicles?window=${window}`);
}

export async function fetchGscClusters(window: GscWindow = '28d'): Promise<GscClusterRow[]> {
  return apiGet<GscClusterRow[]>(`/search-console/clusters?window=${window}`);
}

export async function fetchGscOpportunities(window: GscWindow = '28d'): Promise<GscOpportunity[]> {
  return apiGet<GscOpportunity[]>(`/search-console/opportunities?window=${window}`);
}

export async function fetchGscSitemaps(): Promise<GscSitemapsResponse> {
  return apiGet<GscSitemapsResponse>('/search-console/sitemaps');
}

export async function submitGscSitemap(): Promise<{ success?: boolean; message?: string }> {
  return apiPost<{ success?: boolean; message?: string }>('/search-console/sitemaps/submit');
}

export async function fetchGscPostPerformance(
  postId: number,
  window: GscWindow = '28d',
): Promise<GscPostPerformance> {
  return apiGet<GscPostPerformance>(`/search-console/posts/${postId}?window=${window}`);
}

export async function fetchGscPostQueries(
  postId: number,
  window: GscWindow = '28d',
): Promise<GscQueryRow[]> {
  return apiGet<GscQueryRow[]>(`/search-console/posts/${postId}/queries?window=${window}`);
}

export async function inspectGscPost(postId: number): Promise<GscInspectionResult> {
  return apiPost<GscInspectionResult>(`/search-console/posts/${postId}/inspect`);
}

export async function fetchGscRefreshExport(
  postId: number,
  reason = 'page2_opportunity',
): Promise<GscRefreshExport> {
  return apiGet<GscRefreshExport>(
    `/search-console/posts/${postId}/refresh-export?reason=${encodeURIComponent(reason)}`,
  );
}
