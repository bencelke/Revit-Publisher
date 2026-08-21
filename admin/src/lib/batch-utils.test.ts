import { describe, expect, it } from 'vitest';
import {
  BatchFileItem,
  batchSummaryFromAnalysis,
  buildAnalysis,
  buildOptimizeSummary,
  deriveFileStatus,
} from '../lib/batch-utils';

function file(partial: Partial<BatchFileItem>): BatchFileItem {
  return {
    id: '1',
    filename: 'test.json',
    payload: {},
    parseError: null,
    validation: null,
    preview: null,
    status: 'invalid',
    statusDetail: '',
    ...partial,
  };
}

describe('deriveFileStatus', () => {
  it('marks non-json as unsupported', () => {
    const result = deriveFileStatus({ filename: 'random.txt', parseError: null, validation: null, preview: null });
    expect(result.status).toBe('unsupported');
  });

  it('marks valid preview as valid', () => {
    const result = deriveFileStatus({
      filename: 'a.json',
      parseError: null,
      validation: { valid: true, schema_version: 'revit-article-v1', article_key: 'k', warnings: [] },
      preview: {
        valid: true,
        article: { title: 'T', article_key: 'k', article_type: 'problem' },
        vehicle: 'BMW X3',
        cluster: 'cooling',
        seo: { primary_topic: 'x', seo_title: 'y' },
        relationships: { internal_links: 3, related_articles: 0, pillar_article_key: null },
        publishing: { status: 'draft' },
        existing_article: false,
      },
    });
    expect(result.status).toBe('valid');
  });
});

describe('batchSummaryFromAnalysis', () => {
  it('labels multi-vehicle batches by vehicle count, not the first vehicle', () => {
    const items = [
      file({
        preview: {
          valid: true,
          article: { title: 'A', article_key: 'a', article_type: 'problem' },
          vehicle: 'BMW M340i',
          cluster: 'reliability',
          seo: { primary_topic: 'x', seo_title: 'y' },
          relationships: { internal_links: 1, related_articles: 0, pillar_article_key: null },
          publishing: { status: 'draft' },
          existing_article: false,
        },
        status: 'valid',
      }),
      file({
        preview: {
          valid: true,
          article: { title: 'B', article_key: 'b', article_type: 'problem' },
          vehicle: 'Toyota GR Supra',
          cluster: 'reliability',
          seo: { primary_topic: 'x', seo_title: 'y' },
          relationships: { internal_links: 1, related_articles: 0, pillar_article_key: null },
          publishing: { status: 'draft' },
          existing_article: false,
        },
        status: 'valid',
      }),
    ];
    const analysis = buildAnalysis(items);
    const summary = batchSummaryFromAnalysis(analysis, 'batch-1', 'SEO Ready');
    expect(summary.article_count).toBe(2);
    expect(summary.vehicle_count).toBe(2);
    expect(summary.vehicle_label).toBe('2 vehicles');
    expect(summary.vehicle_label).not.toBe('BMW M340i');
  });
});

describe('buildAnalysis', () => {
  it('aggregates vehicles and links', () => {
    const items = [
      file({
        preview: {
          valid: true,
          article: { title: 'A', article_key: 'a', article_type: 'problem' },
          vehicle: 'BMW X3',
          cluster: 'cooling',
          seo: { primary_topic: 'x', seo_title: 'y' },
          relationships: { internal_links: 5, related_articles: 0, pillar_article_key: null },
          publishing: { status: 'draft' },
          existing_article: false,
        },
        status: 'valid',
      }),
    ];
    const analysis = buildAnalysis(items);
    expect(analysis.articleCount).toBe(1);
    expect(analysis.plannedLinks).toBe(5);
    expect(analysis.vehicles.get('BMW X3')?.count).toBe(1);
    const optimize = buildOptimizeSummary(analysis);
    expect(optimize.plannedLinks).toBe(5);
  });
});
