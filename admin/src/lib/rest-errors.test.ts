import { describe, expect, it } from 'vitest';
import { humanMessageFromStatus } from '../lib/rest-errors';

describe('REST error messages', () => {
  it('maps 409 to friendly duplicate message', () => {
    expect(humanMessageFromStatus(409, undefined, 'existing_article')).toContain('already exists');
  });

  it('maps 403 to permission message', () => {
    expect(humanMessageFromStatus(403)).toContain('permission');
  });
});
