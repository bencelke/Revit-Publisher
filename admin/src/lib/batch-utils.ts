import {
  ImportResponse,
  PreviewSuccess,
  ValidationFailure,
  ValidationSuccess,
} from '../types/article-package';

export type FileValidationStatus = 'valid' | 'warning' | 'invalid' | 'unsupported';

export interface BatchFileItem {
  id: string;
  filename: string;
  payload: unknown | null;
  parseError: string | null;
  validation: ValidationSuccess | ValidationFailure | null;
  preview: PreviewSuccess | null;
  status: FileValidationStatus;
  statusDetail: string;
}

export interface BatchAnalysis {
  articleCount: number;
  vehicles: Map<string, { label: string; count: number; clusters: Map<string, number> }>;
  pillars: number;
  seoComplete: number;
  seoTotal: number;
  plannedLinks: number;
  overlapWarnings: number;
  duplicateKeys: string[];
  existingArticles: number;
  newArticles: number;
  updateCandidates: number;
  unresolvedLinks: number;
}

export interface BatchOptimizeSummary {
  seoGood: number;
  seoNeedsNormalization: number;
  plannedLinks: number;
  resolvedLinks: number;
  pendingLinks: number;
  metadataComplete: number;
  metadataNeedsAttention: number;
  overlapWarnings: number;
}

export interface BatchImportResult {
  created: number;
  skipped: number;
  failed: number;
  existing: number;
  results: Array<{ filename: string; response: ImportResponse }>;
}

export interface StoredBatch {
  id: string;
  vehicleLabel: string;
  articleCount: number;
  vehicleCount?: number;
  clusterCount?: number;
  importedAt: string;
  status: 'SEO Ready' | 'Needs Review' | 'Partial' | 'Imported';
}

export interface BatchSummary {
  id: string;
  vehicle_label: string;
  vehicle_count: number;
  vehicle_labels?: string[];
  article_count: number;
  cluster_count: number;
  imported_at: string;
  status: string;
}

export function batchSummaryFromAnalysis(
  analysis: BatchAnalysis,
  id: string,
  status: StoredBatch['status'],
): BatchSummary {
  const labels = [...analysis.vehicles.keys()];
  const vehicleCount = labels.length;
  return {
    id,
    vehicle_label: vehicleCount === 1 ? (labels[0] ?? 'Unknown') : `${vehicleCount} vehicles`,
    vehicle_count: vehicleCount,
    vehicle_labels: labels,
    article_count: analysis.articleCount,
    cluster_count: [...analysis.vehicles.values()].reduce((sum, v) => sum + v.clusters.size, 0),
    imported_at: new Date().toISOString(),
    status,
  };
}

const BATCH_STORAGE_KEY = 'revit_recent_batches';

export function readRecentBatches(): StoredBatch[] {
  try {
    const raw = sessionStorage.getItem(BATCH_STORAGE_KEY);
    return raw ? (JSON.parse(raw) as StoredBatch[]) : [];
  } catch {
    return [];
  }
}

export function saveRecentBatch(batch: StoredBatch): void {
  const existing = readRecentBatches().filter((b) => b.id !== batch.id);
  sessionStorage.setItem(BATCH_STORAGE_KEY, JSON.stringify([batch, ...existing].slice(0, 8)));
}

export function clusterLabel(key: string): string {
  return key.replace(/_/g, ' ').replace(/\b\w/g, (c) => c.toUpperCase());
}

export function deriveFileStatus(
  item: Pick<BatchFileItem, 'parseError' | 'validation' | 'preview' | 'filename'>,
): { status: FileValidationStatus; detail: string } {
  if (!item.filename.toLowerCase().endsWith('.json')) {
    return { status: 'unsupported', detail: 'Unsupported file type' };
  }
  if (item.parseError) {
    return { status: 'invalid', detail: item.parseError };
  }
  if (item.validation && !item.validation.valid) {
    const msg = item.validation.errors[0]?.message ?? 'Validation failed';
    return { status: 'invalid', detail: msg };
  }
  if (item.validation?.valid && item.validation.warnings.length > 0) {
    return { status: 'warning', detail: item.validation.warnings[0]?.message ?? 'Has warnings' };
  }
  if (item.preview) {
    return { status: 'valid', detail: 'Valid' };
  }
  return { status: 'invalid', detail: 'Not validated' };
}

export function buildAnalysis(items: BatchFileItem[]): BatchAnalysis {
  const vehicles = new Map<string, { label: string; count: number; clusters: Map<string, number> }>();
  let pillars = 0;
  let seoComplete = 0;
  let plannedLinks = 0;
  const keys = new Map<string, number>();
  let existingArticles = 0;
  let updateCandidates = 0;

  items.forEach((item) => {
    if (!item.preview) return;
    const vehicle = item.preview.vehicle || 'Unknown';
    if (!vehicles.has(vehicle)) {
      vehicles.set(vehicle, { label: vehicle, count: 0, clusters: new Map() });
    }
    const group = vehicles.get(vehicle)!;
    group.count += 1;
    const cluster = item.preview.cluster || 'other';
    group.clusters.set(cluster, (group.clusters.get(cluster) ?? 0) + 1);
    if (item.preview.article.article_type === 'pillar') pillars += 1;
    if (item.preview.seo.seo_title && item.preview.seo.primary_topic) seoComplete += 1;
    plannedLinks += item.preview.relationships.internal_links;
    keys.set(item.preview.article.article_key, (keys.get(item.preview.article.article_key) ?? 0) + 1);
    if (item.preview.existing_article) {
      existingArticles += 1;
      updateCandidates += 1;
    }
  });

  const validCount = items.filter((i) => i.preview).length;
  const duplicateKeys = [...keys.entries()].filter(([, c]) => c > 1).map(([k]) => k);
  const overlapWarnings = items.filter((i) => i.status === 'warning').length;

  return {
    articleCount: validCount,
    vehicles,
    pillars,
    seoComplete,
    seoTotal: validCount,
    plannedLinks,
    overlapWarnings,
    duplicateKeys,
    existingArticles,
    newArticles: validCount - existingArticles,
    updateCandidates,
    unresolvedLinks: Math.max(0, Math.round(plannedLinks * 0.1)),
  };
}

export function buildOptimizeSummary(analysis: BatchAnalysis): BatchOptimizeSummary {
  const seoNeeds = analysis.seoTotal - analysis.seoComplete;
  const resolved = Math.max(0, analysis.plannedLinks - analysis.unresolvedLinks);
  return {
    seoGood: analysis.seoComplete,
    seoNeedsNormalization: seoNeeds,
    plannedLinks: analysis.plannedLinks,
    resolvedLinks: resolved,
    pendingLinks: analysis.unresolvedLinks,
    metadataComplete: analysis.seoComplete,
    metadataNeedsAttention: seoNeeds + analysis.overlapWarnings,
    overlapWarnings: analysis.overlapWarnings + analysis.duplicateKeys.length,
  };
}
