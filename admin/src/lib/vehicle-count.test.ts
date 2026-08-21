import { describe, expect, it } from 'vitest';
import { vehicleArticleCount } from './vehicle-count';

describe('vehicleArticleCount', () => {
  it('uses backend articles for drafts', () => {
    expect(vehicleArticleCount({ articles: 1, published: 0, draft: 1 })).toBe(1);
  });

  it('falls back to published + draft when articles is zero', () => {
    expect(vehicleArticleCount({ articles: 0, published: 0, draft: 1 })).toBe(1);
  });

  it('falls back to article_count', () => {
    expect(vehicleArticleCount({ articles: 0, article_count: 2, published: 0, draft: 0 })).toBe(2);
  });
});
