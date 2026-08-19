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

export interface ArticlePackage {
  schema_version: 'revit-article-v1';
  article: {
    article_key: string;
    title: string;
    slug: string;
    article_type: ArticleType;
    summary: string;
    excerpt: string;
  };
  vehicle: {
    manufacturer: string | null;
    model: string | null;
    generation: string | null;
    trim: string | null;
    start_year: number | null;
    end_year: number | null;
    engines: string[];
  };
  cluster: {
    cluster_key: string;
    name: string;
    pillar_article_key: string | null;
    parent_cluster_key: string | null;
  };
  seo: {
    primary_topic: string;
    secondary_topics: string[];
    search_intent: SearchIntent;
    seo_title: string;
    meta_description: string;
    canonical: 'auto' | string;
    index: boolean;
    follow: boolean;
  };
  content: {
    intro: string;
    blocks: Array<Record<string, unknown>>;
    faq: Array<{ question: string; answer: string }>;
  };
  internal_links: Array<{
    target_article_key: string;
    preferred_anchor: string;
    relationship: InternalLinkRelationship;
    required: boolean;
  }>;
  related_articles: Array<{
    article_key: string;
    relationship: InternalLinkRelationship;
    priority: number;
  }>;
  sources: Array<{
    source_name: string;
    title: string;
    url: string;
    source_type: string;
    purpose: string;
  }>;
  structured_data: {
    article: boolean;
    breadcrumbs: boolean;
    faq: boolean;
  };
  publishing: {
    status: PublishingStatus;
    author: number | null;
    featured_image_id: number | null;
    allow_comments: boolean;
  };
}

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

export interface RevitPublisherAdminConfig {
  version: string;
  schemaVersion: string;
  restUrl: string;
  nonce: string;
  pages: {
    dashboard: string;
    import: string;
  };
}

declare global {
  interface Window {
    revitPublisherAdmin: RevitPublisherAdminConfig;
  }
}

export {};
