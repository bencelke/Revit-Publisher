export interface VehicleHubRecord {
  hub_id: number;
  vehicle_key: string;
  vehicle_label?: string;
  title: string;
  status: string;
  edit_url: string;
  permalink?: string | null;
}

export interface HubCreatePreview {
  vehicle_label: string;
  vehicle_key: string;
  years: string;
  engines: string[];
  published_articles: number;
  clusters_count: number;
}

export interface CreateVehicleHubResponse {
  success: boolean;
  hub_id?: number;
  edit_url?: string;
  message?: string;
}

export interface VehicleHubHealth {
  hub_id: number;
  title: string;
  status: string;
  signals: Record<string, boolean | string | number>;
  warnings: string[];
  coverage?: {
    published_articles: number;
    clusters: number;
    plan_coverage: number;
    missing_articles: number;
  };
}

export interface SitemapHealthCounts {
  indexable: {
    vehicle_hubs: number;
    articles: number;
  };
  excluded: {
    drafts: number;
    noindex: number;
    operational_cpts: number;
  };
  audit_signals?: Array<{
    code: string;
    message: string;
    severity: string;
  }>;
}

export interface SerpPreviewResponse {
  post_id: number;
  post_type: string;
  title: string;
  url: string;
  description: string;
  warnings: Array<{
    code: string;
    message: string;
    severity?: string;
  }>;
  indexable?: boolean;
}

export interface LinkMatrixArticle {
  post_id: number;
  title: string;
  is_pillar: boolean;
  short_title?: string;
}

export interface LinkMatrixSuggestion {
  source_post_id: number;
  target_post_id: number;
  anchor?: string;
  relationship?: string;
  block_index?: number;
  cluster_key?: string;
}

export interface LinkMatrixAppliedLink {
  log_id: number;
  source_post_id: number;
  target_post_id: number;
  source_title?: string;
  target_title?: string;
}

export interface ClusterLinkMatrix {
  cluster_key: string;
  name: string;
  pillar_id: number;
  articles: LinkMatrixArticle[];
  /** source post_id → target post_id → linked */
  matrix: Record<string, Record<string, boolean>>;
  suggestions: LinkMatrixSuggestion[];
  revit_applied?: LinkMatrixAppliedLink[];
}

export interface VehicleRowWithHub {
  label: string;
  seo_health_avg: number;
  plan_coverage: number;
  published: number;
  missing_articles: number;
  orphans: number;
  unresolved_links?: number;
  high_overlaps: number;
  clusters_count?: number;
  hub?: VehicleHubRecord | null;
  hub_preview?: HubCreatePreview | null;
}
