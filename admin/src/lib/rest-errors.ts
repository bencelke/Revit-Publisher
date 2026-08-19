export function humanMessageFromStatus(status: number, raw?: string, code?: string): string {
  if (raw && !raw.includes('rest_') && raw.length < 200) {
    return raw;
  }
  switch (status) {
    case 400:
      return 'The request was invalid. Check your input and try again.';
    case 401:
      return 'Your session expired. Refresh the page and sign in again.';
    case 403:
      return 'You do not have permission to perform this action.';
    case 404:
      return 'The requested resource was not found.';
    case 409:
      return code === 'existing_article' ? 'Article already exists.' : 'This action conflicts with existing data.';
    case 500:
      return 'A server error occurred. Try again or check System Health under Advanced.';
    default:
      return raw ?? 'Request failed. Please try again.';
  }
}
