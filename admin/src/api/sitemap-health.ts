import { getAdminConfig } from './article-packages';
import { SerpPreviewResponse, SitemapHealthCounts } from '../types/public-seo';

async function apiGet<T>(endpoint: string): Promise<T> {
  const config = getAdminConfig();
  const response = await fetch(`${config.restUrl}${endpoint}`, {
    headers: { 'X-WP-Nonce': config.nonce },
  });
  if (!response.ok) {
    const body = (await response.json().catch(() => ({}))) as { message?: string };
    throw new Error(body.message ?? `Request failed (${response.status}).`);
  }
  return (await response.json()) as T;
}

export async function fetchSitemapHealth(): Promise<SitemapHealthCounts> {
  return apiGet<SitemapHealthCounts>('/sitemap-health');
}

export async function fetchSerpPreview(
  postId: number,
  postType: 'article' | 'hub' = 'article',
): Promise<SerpPreviewResponse> {
  return apiGet<SerpPreviewResponse>(
    `/serp-preview?post_id=${postId}&post_type=${postType}`,
  );
}
