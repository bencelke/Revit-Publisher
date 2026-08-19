import {
  ContentPlanCoverage,
  ContentPlanPreview,
  ContentPlanSummary,
} from '../types/article-package';
import { getAdminConfig } from './article-packages';

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
    headers: { 'X-WP-Nonce': config.nonce },
  });
  return (await response.json()) as T;
}

export async function previewContentPlan(payload: unknown): Promise<ContentPlanPreview> {
  return apiPost<ContentPlanPreview>('/content-plans/preview', payload);
}

export async function importContentPlan(payload: unknown): Promise<{ success: boolean; plan_id?: number }> {
  return apiPost('/content-plans/import', payload);
}

export async function fetchContentPlans(): Promise<ContentPlanSummary[]> {
  return apiGet<ContentPlanSummary[]>('/content-plans');
}

export async function fetchPlanCoverage(planId: number): Promise<ContentPlanCoverage> {
  return apiGet<ContentPlanCoverage>(`/content-plans/${planId}/coverage`);
}

export async function downloadArticleRequest(planId: number, articleKey: string): Promise<unknown> {
  return apiGet(`/content-plans/${planId}/article-request?article_key=${encodeURIComponent(articleKey)}`);
}

export async function updatePreview(postId: number, payload: unknown, mode: string): Promise<unknown> {
  return apiPost('/article-packages/update-preview', { ...(payload as object), post_id: postId, mode });
}

export async function applyUpdate(postId: number, payload: unknown, mode: string): Promise<unknown> {
  return apiPost('/article-packages/update', { ...(payload as object), post_id: postId, mode });
}

export async function fetchTopicOverlaps(): Promise<unknown[]> {
  return apiGet('/topic-overlaps');
}

export async function fetchSeoAnalysis(postId: number): Promise<unknown> {
  return apiGet(`/posts/${postId}/seo-analysis`);
}

export async function applyBatchLinks(suggestions: unknown[]): Promise<unknown> {
  return apiPost('/link-opportunities/apply-batch', { suggestions });
}
