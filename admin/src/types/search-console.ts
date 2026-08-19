export type GscWindow = '7d' | '28d';

export interface GscMetrics {
  clicks: number;
  impressions: number;
  ctr: number;
  position: number;
}

export interface GscMetricsChange {
  clicks_pct: number | null;
  impressions_pct: number | null;
  position_delta: number | null;
}

export interface GscStatus {
  connected: boolean;
  property: string;
  fixture_mode: boolean;
  last_sync: string;
  last_sync_stats: Record<string, unknown>;
  last_error: string;
  credentials: {
    client_id_configured: boolean;
  };
}

export interface GscConnectResponse {
  oauth_url?: string;
  message?: string;
  connected?: boolean;
  property?: string;
  fixture_mode?: boolean;
  last_sync?: string;
  last_sync_stats?: Record<string, unknown>;
  last_error?: string;
  credentials?: GscStatus['credentials'];
}

export interface GscProperty {
  site_url: string;
  permission_level: string;
}

export interface GscSummary {
  window: GscWindow;
  current: GscMetrics;
  previous: GscMetrics;
  change: GscMetricsChange;
}

export interface GscPageRow {
  post_id: number;
  page_url: string;
  title?: string;
  clicks: number;
  impressions: number;
  ctr: number;
  position: number;
}

export interface GscQueryRow {
  query: string;
  clicks: number;
  impressions: number;
  ctr: number;
  position: number;
}

export interface GscVehicleRow extends GscMetrics {
  vehicle: string;
  articles_total: number;
  articles_with_impressions: number;
  plan_coverage: number;
  seo_health_avg: number;
}

export interface GscClusterRow extends GscMetrics {
  cluster_key: string;
  name: string;
  articles: number;
}

export interface GscOpportunity {
  issue_type: string;
  title: string;
  post_id: number;
  vehicle: string;
  article_key: string;
  cluster_key: string;
  explanation: string;
  recommended_action: string;
  context: Record<string, unknown>;
}

export interface GscSitemapEntry {
  path: string;
  lastSubmitted?: string;
  lastDownloaded?: string;
  isPending?: boolean;
  isSitemapsIndex?: boolean;
  type?: string;
  warnings?: number;
  errors?: number;
}

export interface GscSitemapsResponse {
  sitemaps: GscSitemapEntry[];
  submitted: boolean;
}

export interface GscPostPerformance {
  metrics: GscMetrics;
  trend: GscMetricsChange;
  queries: GscQueryRow[];
  seo_health: number;
  opportunity: string | null;
  opportunity_detail: GscOpportunity | null;
}

export interface GscInspectionResult {
  indexed: boolean;
  last_crawl: string;
  google_canonical: string;
  user_canonical: string;
  coverage_state: string;
  verdict: string;
}

export interface GscSyncResult {
  success: boolean;
  pages_updated?: number;
  query_rows?: number;
  duration_ms?: number;
  message?: string;
}

export interface GscRefreshExport {
  request_type: string;
  article_key: string;
  vehicle: Record<string, string>;
  reason: string;
  current_metrics: {
    clicks: number;
    impressions: number;
    ctr: number;
    position: number;
  };
  top_queries: GscQueryRow[];
  revit_seo_health: {
    total_score: number;
    categories: Record<string, unknown>;
  };
}
