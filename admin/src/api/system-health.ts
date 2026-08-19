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

export interface HealthCheck {
  id: string;
  status: 'pass' | 'warning' | 'fail';
  label: string;
  detail: string;
}

export interface SystemHealthResponse {
  wordpress: { version: string; php: string; permalink: string };
  plugin: { version: string; db_version: number; db_target: number };
  composer: { autoload: boolean; google_api: boolean; json_schema: boolean };
  cron: { audit_next: string | null; gsc_next: string | null; retention_next: string | null };
  search_console: { connected: boolean; property: string; diagnostic: string; last_sync: string };
  storage: Record<string, number>;
  recent_events: Array<{ event: string; timestamp: string; context: Record<string, unknown> }>;
  checks: HealthCheck[];
}

export function fetchSystemHealth() {
  return apiRequest<SystemHealthResponse>('/system-health');
}

export function runSystemHealthChecks() {
  return apiRequest<{ checks: HealthCheck[] }>('/system-health/run', { method: 'POST' });
}

export function exportBackup(sections?: Record<string, boolean>) {
  return apiRequest<Record<string, unknown>>('/backups/export', {
    method: 'POST',
    body: JSON.stringify({ sections: sections ?? {} }),
  });
}

export function validateBackup(data: Record<string, unknown>) {
  return apiRequest<{ valid: boolean }>('/backups/validate', {
    method: 'POST',
    body: JSON.stringify(data),
  });
}

export function previewBackupImport(data: Record<string, unknown>) {
  return apiRequest<Record<string, unknown>>('/backups/import-preview', {
    method: 'POST',
    body: JSON.stringify(data),
  });
}

export function importBackup(data: Record<string, unknown>) {
  return apiRequest<{ success: boolean; restored: string[] }>('/backups/import', {
    method: 'POST',
    body: JSON.stringify(data),
  });
}
