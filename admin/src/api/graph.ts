import {
  ClusterSummary,
  LinkAuditResult,
  LinkOpportunity,
  OrphanEntry,
  SettingsResponse,
  VehicleSummary,
} from '../types/article-package';
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
    body: payload !== undefined ? JSON.stringify(payload) : undefined,
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

export async function fetchSettings(): Promise<SettingsResponse> {
  return apiGet<SettingsResponse>('/settings');
}

export async function updateSettings(payload: Partial<SettingsResponse>): Promise<SettingsResponse> {
  return apiPut<SettingsResponse>('/settings', payload);
}

export async function fetchVehicles(): Promise<VehicleSummary[]> {
  return apiGet<VehicleSummary[]>('/content-graph/vehicles');
}

export async function fetchClusters(): Promise<ClusterSummary[]> {
  return apiGet<ClusterSummary[]>('/content-graph/clusters');
}

export async function fetchOrphans(): Promise<OrphanEntry[]> {
  return apiGet<OrphanEntry[]>('/content-graph/orphans');
}

export async function fetchLinkOpportunities(): Promise<LinkOpportunity[]> {
  return apiGet<LinkOpportunity[]>('/content-graph/link-opportunities');
}

export async function runLinkAudit(): Promise<LinkAuditResult> {
  return apiPost<LinkAuditResult>('/link-audit');
}

export async function applyLink(
  postId: number,
  suggestion: LinkOpportunity,
): Promise<{ success: boolean; message?: string }> {
  return apiPost(`/posts/${postId}/apply-link`, suggestion);
}
