/**
 * Vehicle card article-count display helper.
 */

export function vehicleArticleCount(row: {
  articles?: number | null;
  article_count?: number | null;
  published?: number | null;
  draft?: number | null;
}): number {
  const counted = Number(row.articles || row.article_count || 0);
  if (counted > 0) {
    return counted;
  }
  return (row.published ?? 0) + (row.draft ?? 0);
}
