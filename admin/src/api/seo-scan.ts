import { apiRequest } from '../lib/api-client';

export interface SiteSeoScanSummary {
  scanned_at?: string;
  articles_scanned: number;
  seo_compliant: number;
  needs_improvement: number;
  orphan_articles: number;
  internal_link_ideas: number;
  missing_metadata: number;
  heading_issues: number;
  media_issues: number;
  opportunities?: Array<Record<string, unknown>>;
  articles?: Array<Record<string, unknown>>;
}

export async function fetchSiteSeoScan(): Promise<SiteSeoScanSummary> {
  return apiRequest<SiteSeoScanSummary>('/seo-scan/site');
}

export async function runSiteSeoScan(): Promise<SiteSeoScanSummary> {
  return apiRequest<SiteSeoScanSummary>('/seo-scan/site', { method: 'POST' });
}

export async function applySeoLink(payload: {
  source_post_id: number;
  target_post_id: number;
  anchor: string;
  relationship?: string;
}): Promise<{ success: boolean; log_id?: number; post_status?: string }> {
  return apiRequest('/seo-scan/apply-link', {
    method: 'POST',
    body: JSON.stringify(payload),
  });
}

export async function undoSeoLink(logId: number): Promise<{ success?: boolean }> {
  return apiRequest(`/link-changes/${logId}/undo`, { method: 'POST' });
}
