import {
  ImportResponse,
  PreviewResponse,
  StatsResponse,
  ValidationResponse,
} from '../types/article-package';

export function getAdminConfig() {
  return window.revitPublisherAdmin;
}

async function apiPost<T>(endpoint: string, payload: unknown): Promise<T> {
  const config = getAdminConfig();

  const response = await fetch(`${config.restUrl}${endpoint}`, {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      'X-WP-Nonce': config.nonce,
    },
    body: JSON.stringify(payload),
  });

  return (await response.json()) as T;
}

async function apiGet<T>(endpoint: string): Promise<T> {
  const config = getAdminConfig();

  const response = await fetch(`${config.restUrl}${endpoint}`, {
    headers: {
      'X-WP-Nonce': config.nonce,
    },
  });

  return (await response.json()) as T;
}

export async function validateArticlePackage(
  payload: unknown,
): Promise<ValidationResponse> {
  return apiPost<ValidationResponse>('/article-packages/validate', payload);
}

export async function previewArticlePackage(
  payload: unknown,
): Promise<PreviewResponse> {
  return apiPost<PreviewResponse>('/article-packages/preview', payload);
}

export async function importArticlePackage(
  payload: unknown,
): Promise<ImportResponse> {
  return apiPost<ImportResponse>('/article-packages/import', payload);
}

export async function fetchStats(): Promise<StatsResponse> {
  return apiGet<StatsResponse>('/stats');
}

export const MAX_JSON_FILE_SIZE = 5 * 1024 * 1024;
