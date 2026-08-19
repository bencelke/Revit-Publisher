export type ArticleType =
  | 'vehicle_hub'
  | 'pillar'
  | 'problem'
  | 'maintenance'
  | 'modification'
  | 'product'
  | 'fitment'
  | 'buying'
  | 'reliability'
  | 'comparison'
  | 'guide'
  | 'faq'
  | 'other';

export type SearchIntent =
  | 'informational'
  | 'commercial'
  | 'transactional'
  | 'navigational'
  | 'mixed';

export type InternalLinkRelationship =
  | 'parent'
  | 'child'
  | 'pillar'
  | 'supporting'
  | 'related_problem'
  | 'related_maintenance'
  | 'related_modification'
  | 'related_product'
  | 'related_vehicle'
  | 'contextual';

export type PublishingStatus = 'draft' | 'pending' | 'private';

export interface ValidationError {
  path: string;
  message: string;
}

export interface ValidationSuccess {
  valid: true;
  schema_version: string;
  article_key: string;
  warnings: ValidationError[];
}

export interface ValidationFailure {
  valid: false;
  errors: ValidationError[];
}

export type ValidationResponse = ValidationSuccess | ValidationFailure;

export interface PreviewSuccess {
  valid: true;
  article: {
    title: string;
    article_key: string;
    article_type: string;
  };
  vehicle: string;
  cluster: string;
  seo: {
    primary_topic: string;
    seo_title: string;
  };
  relationships: {
    internal_links: number;
    related_articles: number;
    pillar_article_key: string | null;
  };
  publishing: {
    status: PublishingStatus;
  };
  existing_article: boolean;
  existing_post_id?: number | null;
}

export type PreviewResponse = PreviewSuccess | ValidationFailure;

export interface ImportSuccess {
  success: true;
  status: 'created';
  article_key: string;
  post_id: number;
  edit_url: string;
  post_status: PublishingStatus;
}

export interface ImportExisting {
  success: false;
  status: 'existing_article';
  article_key: string;
  post_id: number;
  edit_url: string;
}

export interface ImportFailure {
  success: false;
  status: string;
  errors?: ValidationError[];
}

export type ImportResponse = ImportSuccess | ImportExisting | ImportFailure;

export type UpdateMode = 'full' | 'seo' | 'relationships';

export interface UpdatePreviewUnchanged {
  valid: true;
  status: 'unchanged';
  message: string;
}

export interface UpdatePreviewChanged {
  valid: true;
  status: 'changed';
  post_id: number;
  article_key: string;
  mode: UpdateMode;
  manual_edits: boolean;
  revision_note: string;
  diff: {
    article?: Record<string, { changed: boolean }>;
    seo?: Record<string, { changed: boolean }>;
    content?: { changed: boolean; blocks_added?: number; blocks_removed?: number };
    vehicle?: { changed: boolean };
    cluster?: { changed: boolean };
    relationships?: Record<string, { changed: boolean; added?: number }>;
    sources?: Record<string, unknown>;
  };
}

export type UpdatePreviewResponse = UpdatePreviewUnchanged | UpdatePreviewChanged | ValidationFailure;

export interface UpdateApplySuccess {
  success: true;
  status: 'updated' | 'unchanged';
  post_id: number;
  edit_url?: string;
}

export interface UpdateApplyFailure {
  success: false;
  message?: string;
}

export interface SeoHealthSummary {
  revit_articles: number;
  orphan_articles: number;
  unresolved_links: number;
  missing_pillars: number;
  missing_meta: number;
  duplicate_topics: number;
}

export interface ContentGraphSummary {
  vehicles: number;
  clusters: number;
  resolved_links: number;
  pending_links: number;
}

export interface IntelligenceSummary {
  missing_content: number;
  topic_overlaps: number;
  needs_attention?: {
    orphans: number;
    topic_overlaps: number;
    missing_meta: number;
    unresolved_links: number;
  };
}

export interface ContentPlanPreview {
  valid: boolean;
  plan_key?: string;
  vehicle?: string;
  summary?: {
    planned_articles: number;
    existing_articles: number;
    missing_articles: number;
    clusters: number;
    overall_coverage: number;
  };
  errors?: ValidationError[];
}

export interface ContentPlanSummary {
  plan_id: number;
  plan_key: string;
  vehicle: string;
  summary: {
    planned_articles: number;
    existing_articles: number;
    missing_articles: number;
    overall_coverage: number;
  };
}

export interface ContentPlanCoverage {
  plan_id: number;
  plan_key: string;
  vehicle: string;
  summary: {
    planned_articles: number;
    existing_articles: number;
    missing_articles: number;
    published: number;
    draft: number;
    overall_coverage: number;
  };
  clusters: Array<{
    cluster_key: string;
    name: string;
    planned: number;
    existing: number;
    published: number;
    missing: number;
    plan_coverage: number;
    pillar_status: string;
    internal_link_pct: number;
    meta_completeness: number;
    orphans: number;
  }>;
  missing: Array<{
    article_key: string;
    title: string;
    priority: number;
    cluster_key: string;
  }>;
  next_content: Array<{
    article_key: string;
    title: string;
    priority: number;
  }>;
}

export interface StatsResponse {
  version: string;
  schema_version: string;
  imported_articles: number;
  vehicle_models: number;
  clusters: number;
  content_plans?: number;
  seo_health?: SeoHealthSummary;
  content_graph?: ContentGraphSummary;
  intelligence?: IntelligenceSummary;
}

export interface SettingsResponse {
  enable_meta_description: boolean;
  enable_canonical: boolean;
  enable_robots: boolean;
  enable_article_schema: boolean;
  enable_breadcrumb_schema: boolean;
  internal_link_mode: string;
  max_suggested_links: number;
  avoid_duplicate_target: boolean;
  org_name: string;
  org_logo_url: string;
  public_seo_output_enabled: boolean;
  seo_plugin_conflict: string | null;
}

export interface VehicleSummary {
  label: string;
  articles: number;
  types: Record<string, number>;
  clusters: string[];
  unresolved_links: number;
}

export interface ClusterSummary {
  cluster_key: string;
  name: string;
  article_count: number;
  pillar: Record<string, unknown> | null;
  pillar_key: string;
  resolved_links: number;
  missing_links: number;
}

export interface OrphanEntry {
  post_id: number;
  title: string;
  edit_url: string;
}

export interface LinkOpportunity {
  target_article_key?: string;
  target_post_id?: number;
  target_title?: string;
  target_permalink?: string;
  anchor?: string;
  relationship?: string;
  status?: string;
  block_index?: number;
  paragraph_label?: string;
  source_post_id?: number;
  source_title?: string;
}

export interface LinkAuditResult {
  total_planned: number;
  resolved: number;
  unresolved: number;
  broken: number;
  orphan_posts: number;
  backlink_opportunities: number;
  audited_at: string;
}

export interface RevitPublisherAdminConfig {
  version: string;
  schemaVersion: string;
  restUrl: string;
  nonce: string;
  pages: {
    dashboard: string;
    import: string;
    planner: string;
    graph: string;
    seoHealth: string;
    settings: string;
  };
}

declare global {
  interface Window {
    revitPublisherAdmin: RevitPublisherAdminConfig;
  }
}

export {};
