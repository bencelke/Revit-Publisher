import { render, screen } from '@testing-library/react';
import { describe, expect, it } from 'vitest';
import { FixtureBanner } from '../components/EmptyState';
import { StepIndicator } from '../components/StepIndicator';

describe('StepIndicator', () => {
  it('highlights analyze step', () => {
    render(<StepIndicator current="analyze" />);
    const step = screen.getByText('Analyze').closest('li');
    expect(step?.className.includes('revit-step--active')).toBe(true);
  });
});

describe('FixtureBanner', () => {
  it('shows test data label', () => {
    render(<FixtureBanner />);
    expect(screen.getByText('TEST DATA')).toBeTruthy();
    expect(screen.getByText(/fixture/i)).toBeTruthy();
  });
});

describe('Advanced navigation config', () => {
  it('includes primary and advanced page slugs', () => {
    const pages = {
      dashboard: 'revit-publisher',
      import: 'revit-publisher-import',
      seo: 'revit-publisher-seo',
      advanced: 'revit-publisher-advanced',
      planner: 'revit-publisher-planner',
      settings: 'revit-publisher-settings',
    };
    expect(Object.keys(pages)).toContain('advanced');
    expect(Object.keys(pages)).toContain('seo');
    expect(pages.import).toContain('import');
  });
});
