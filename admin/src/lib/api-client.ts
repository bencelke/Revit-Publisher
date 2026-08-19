import { getAdminConfig } from '../api/article-packages';

import { RevitPublisherAdminConfig } from '../types/article-package';
import { humanMessageFromStatus } from './rest-errors';

export class ApiError extends Error {
  status: number;

  constructor(message: string, status: number) {
    super(message);
    this.status = status;
    this.name = 'ApiError';
  }
}

export async function apiRequest<T>(
  endpoint: string,
  options: RequestInit = {},
): Promise<T> {
  const config = getAdminConfig();
  if (!config?.restUrl) {
    throw new ApiError('RevIt Publisher admin configuration is missing.', 500);
  }

  const response = await fetch(`${config.restUrl}${endpoint}`, {
    ...options,
    headers: {
      'Content-Type': 'application/json',
      'X-WP-Nonce': config.nonce,
      ...(options.headers ?? {}),
    },
  });

  let data: unknown;
  try {
    data = await response.json();
  } catch {
    data = null;
  }

  if (!response.ok) {
    const payload = data as { message?: string; code?: string } | null;
    const message = humanMessageFromStatus(response.status, payload?.message, payload?.code);
    throw new ApiError(message, response.status);
  }

  return data as T;
}

export function adminPage(slug: string): string {
  return `admin.php?page=${slug}`;
}

export function adminUrl(key: keyof RevitPublisherAdminConfig['pages']): string {
  const pages = getAdminConfig()?.pages;
  return pages?.[key] ? adminPage(pages[key]) : '#';
}
