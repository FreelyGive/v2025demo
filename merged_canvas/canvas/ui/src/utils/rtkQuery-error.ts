import { isRejectedWithValue } from '@reduxjs/toolkit';

import { setLatestError } from '@/features/error-handling/queryErrorSlice';

import type {
  Middleware,
  MiddlewareAPI,
  SerializedError,
} from '@reduxjs/toolkit';
import type { FetchBaseQueryError } from '@reduxjs/toolkit/query';
import type { queryError } from '@/features/error-handling/queryErrorSlice';

/**
 * Normalizes various API error formats into a standard error object. Written by Claude AI.
 */
const normalizeError = (
  error: FetchBaseQueryError | SerializedError,
): queryError => {
  // Default values
  let status = 'unknown';
  let message = 'An unknown error occurred';
  let errors: any = undefined;

  // Handle RTK Query specific errors (FETCH_ERROR, PARSING_ERROR, TIMEOUT_ERROR)
  if ('status' in error) {
    if (typeof error.status === 'string') {
      status = error.status;

      if (error.status === 'FETCH_ERROR') {
        message = 'Network error: Failed to connect to server';
      } else if (error.status === 'PARSING_ERROR') {
        message = 'Failed to parse server response';
      } else if (error.status === 'TIMEOUT_ERROR') {
        message = 'Request timed out';
      }

      errors = error.error;
    }
    // Handle HTTP errors
    else {
      status = error.status.toString();
      errors = error.data;

      if (error.status === 409) {
        // Conflict errors
        message = Array.isArray((error.data as any)?.errors)
          ? (error.data as any).errors[0]
          : 'A conflict occurred. Please refresh and try again.';
      } else if (error.status === 400) {
        // Bad request errors
        message = Array.isArray((error.data as any)?.errors)
          ? (error.data as any).errors[0]
          : 'Invalid request';
      } else if (error.status === 404) {
        // Not found errors
        message = Array.isArray((error.data as any)?.errors)
          ? (error.data as any).errors[0]
          : 'Resource not found';
      } else if (error.status === 403) {
        // Access denied errors
        message = Array.isArray((error.data as any)?.errors)
          ? (error.data as any).errors[0]
          : 'Access denied';
      } else if (error.status === 422) {
        // Validation errors
        if (
          Array.isArray((error.data as any)?.errors) &&
          (error.data as any).errors[0]?.detail
        ) {
          message = (error.data as any).errors
            .map((e: any) => e.detail)
            .join(', ');
        } else if (Array.isArray((error.data as any)?.errors)) {
          message = (error.data as any).errors.join(', ');
        } else {
          message = 'Validation error';
        }
      } else if (error.status === 500) {
        // Server errors
        message = (error.data as any)?.message || 'Internal server error';
      } else {
        // Generic HTTP error
        message =
          `Error ${error.status}` +
          ((error.data as any)?.message
            ? `: ${(error.data as any).message}`
            : '');
      }
    }
  }
  // Handle serialized errors (typically from rejected thunks)
  else if ('message' in error) {
    status = 'REJECTED';
    message = error.message || 'Request failed';
    errors = error;
  }

  return {
    status,
    errors,
    message,
  };
};

/**
 * Middleware to handle RTK Query errors
 * - Normalizes errors into a standard format
 * - Dispatches to the error slice
 */
export const rtkQueryErrorHandler: Middleware =
  (api: MiddlewareAPI) => (next) => (action) => {
    // RTK Query uses `createAsyncThunk` from redux-toolkit under the hood, so we're able to utilize these matchers.
    if (isRejectedWithValue(action)) {
      const rawError = action.payload as FetchBaseQueryError | SerializedError;
      const normalizedError = normalizeError(rawError);

      console.error('RTK Query error:', normalizedError);

      // Dispatch to the error slice so we can handle specific errors.
      api.dispatch(setLatestError(normalizedError));
    }

    return next(action);
  };
