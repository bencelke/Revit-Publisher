type BadgeTone = 'success' | 'warning' | 'error' | 'info' | 'neutral';

const toneClass: Record<BadgeTone, string> = {
  success: 'revit-badge--success',
  warning: 'revit-badge--warning',
  error: 'revit-badge--error',
  info: 'revit-badge--info',
  neutral: 'revit-badge--neutral',
};

export function StatusBadge({ tone = 'neutral', children }: { tone?: BadgeTone; children: React.ReactNode }) {
  return <span className={`revit-badge ${toneClass[tone]}`}>{children}</span>;
}

export function ArticleStatus({ status }: { status: 'valid' | 'warning' | 'invalid' | 'unsupported' }) {
  const map = {
    valid: { tone: 'success' as const, label: '✓ Valid' },
    warning: { tone: 'warning' as const, label: '⚠ Warning' },
    invalid: { tone: 'error' as const, label: '✗ Invalid' },
    unsupported: { tone: 'error' as const, label: '✗ Unsupported' },
  };
  const item = map[status];
  return <StatusBadge tone={item.tone}>{item.label}</StatusBadge>;
}
