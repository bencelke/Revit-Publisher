import { getAdminConfig } from './article-packages';

async function apiGet<T>(endpoint: string): Promise<T> {
  const config = getAdminConfig();
  const response = await fetch(`${config.restUrl}${endpoint}`, {
    headers: { 'X-WP-Nonce': config.nonce },
  });
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
    body: JSON.stringify(payload ?? {}),
  });
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
  return (await response.json()) as T;
}

export async function runAudit(): Promise<unknown> {
  return apiPost('/audits/run');
}

export async function fetchAudits(): Promise<unknown[]> {
  return apiGet('/audits');
}

export async function fetchIssues(params = ''): Promise<unknown[]> {
  return apiGet(`/issues${params}`);
}

export async function updateIssueStatus(issueId: number, status: string): Promise<unknown> {
  return apiPut(`/issues/${issueId}`, { status });
}

export async function fetchRedirects(): Promise<unknown[]> {
  return apiGet('/redirects');
}

export async function createRedirect(payload: unknown): Promise<unknown> {
  return apiPost('/redirects', payload);
}

export async function previewConsolidation(sourceId: number, destId: number): Promise<unknown> {
  return apiPost('/consolidations/preview', { source_post_id: sourceId, destination_post_id: destId });
}

export async function applyConsolidation(sourceId: number, destId: number, sourceStatus = 'draft'): Promise<unknown> {
  return apiPost('/consolidations/apply', { source_post_id: sourceId, destination_post_id: destId, source_status: sourceStatus });
}

export async function recordOverlapDecision(postA: number, postB: number, decision: string): Promise<unknown> {
  return apiPost('/overlap-decisions', { post_id_a: postA, post_id_b: postB, decision });
}

export async function fetch404s(): Promise<{ enabled: boolean; entries: unknown[] }> {
  return apiGet('/404s');
}

export async function fetchVehicles(): Promise<unknown[]> {
  return apiGet('/vehicles');
}

export async function fetchVehicleDetail(vehicle: string): Promise<unknown> {
  return apiGet(`/vehicles/detail?vehicle=${encodeURIComponent(vehicle)}`);
}

export async function fetchClusterLinks(clusterKey: string): Promise<unknown> {
  return apiGet(`/cluster-links/${encodeURIComponent(clusterKey)}`);
}

export async function applyClusterLinks(suggestions: unknown[]): Promise<unknown> {
  return apiPost('/cluster-links/apply', { suggestions });
}

export async function undoLink(logId: number): Promise<unknown> {
  return apiPost(`/link-changes/${logId}/undo`);
}
