import type { SerializedError } from '@reduxjs/toolkit';
import type { FetchBaseQueryError } from '@reduxjs/toolkit/query';

export function getQueryErrorMessage(
  error: FetchBaseQueryError | SerializedError,
): string {
  if ('status' in error) {
    if (error.status === 'PARSING_ERROR') {
      return 'The server returned an unexpected response format.';
    }
    if (error.status === 404) {
      return 'Resource not found.';
    }
    const errorData = error.data as { message?: string };
    return `HTTP ${error.status}: ${errorData?.message || 'No additional information'}`;
  }
  return error.message || 'Unknown error occurred';
}
