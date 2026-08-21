import {
  ImportResponse,
  PreviewResponse,
  StatsResponse,
  ValidationResponse,
} from '../types/article-package';
import { apiRequest } from '../lib/api-client';

export function getAdminConfig() {
  return window.revitPublisherAdmin;
}

export async function validateArticlePackage(payload: unknown): Promise<ValidationResponse> {
  return apiRequest<ValidationResponse>('/article-packages/validate', {
    method: 'POST',
    body: JSON.stringify(payload),
  });
}

export async function previewArticlePackage(payload: unknown): Promise<PreviewResponse> {
  return apiRequest<PreviewResponse>('/article-packages/preview', {
    method: 'POST',
    body: JSON.stringify(payload),
  });
}

export async function importArticlePackage(payload: unknown, batchId?: string): Promise<ImportResponse> {
  const suffix = batchId ? `?batch_id=${encodeURIComponent(batchId)}` : '';
  return apiRequest<ImportResponse>(`/article-packages/import${suffix}`, {
    method: 'POST',
    body: JSON.stringify(payload),
  });
}

export async function recordImportBatch(payload: { id: string; status: string }): Promise<unknown> {
  return apiRequest('/import-batches', {
    method: 'POST',
    body: JSON.stringify(payload),
  });
}

export async function fetchStats(): Promise<StatsResponse> {
  return apiRequest<StatsResponse>('/stats');
}

export const MAX_JSON_FILE_SIZE = 5 * 1024 * 1024;
