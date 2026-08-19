import { ValidationResponse } from '../types/article-package';

export function getAdminConfig() {
  return window.revitPublisherAdmin;
}

export async function validateArticlePackage(
  payload: unknown,
): Promise<ValidationResponse> {
  const config = getAdminConfig();

  const response = await fetch(
    `${config.restUrl}/article-packages/validate`,
    {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-WP-Nonce': config.nonce,
      },
      body: JSON.stringify(payload),
    },
  );

  return (await response.json()) as ValidationResponse;
}
