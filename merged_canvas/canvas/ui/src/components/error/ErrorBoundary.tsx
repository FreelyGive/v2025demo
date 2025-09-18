import React, { useEffect } from 'react';
import { ErrorBoundary as ReactErrorBoundary } from 'react-error-boundary';
import {
  isRouteErrorResponse,
  useAsyncError,
  useRouteError,
} from 'react-router-dom';

import ErrorAlert from '@/components/error/ErrorAlert';
import ErrorCard from '@/components/error/ErrorCard';
import ErrorPage from '@/components/error/ErrorPage';
import { usePostLogEntryMutation } from '@/services/log';
import { getDrupalSettings } from '@/utils/drupal-globals';

const drupalSettings = getDrupalSettings();

/**
 * Error boundary component that catches errors in its child component tree.
 * @see https://react.dev/reference/react/Component#catching-rendering-errors-with-an-error-boundary
 * @see https://github.com/bvaughn/react-error-boundary
 */
const ErrorBoundary: React.FC<{
  title?: string;
  resetButtonText?: string;
  onReset?: () => void;
  variant?: 'page' | 'card' | 'alert';
  children: React.ReactNode;
}> = ({ title, resetButtonText, onReset, variant = 'card', children }) => {
  const [postLogEntryMutation] = usePostLogEntryMutation();

  return (
    <ReactErrorBoundary
      fallbackRender={({ error, resetErrorBoundary }) => {
        const status = error?.status || error?.data?.status;
        let message =
          error.message ||
          error.data?.message ||
          error.error ||
          (Array.isArray(error?.data?.errors) && error.data.errors.join(`\n`));
        if (status) {
          message = `Error ${status}: ${message}`;
        }
        if (variant === 'alert') {
          return (
            <ErrorAlert
              title={title}
              error={message}
              resetErrorBoundary={
                status === 401
                  ? () =>
                      (window.location.href = drupalSettings.canvas.loginUrl)
                  : resetErrorBoundary
              }
              resetButtonText={status === 401 ? 'Go to login' : resetButtonText}
            />
          );
        }
        const Wrapper = variant === 'page' ? ErrorPage : React.Fragment;
        return (
          <Wrapper>
            <ErrorCard
              title={title}
              error={message}
              resetErrorBoundary={resetErrorBoundary}
              resetButtonText={resetButtonText}
            />
          </Wrapper>
        );
      }}
      onError={postLogEntryMutation}
      onReset={onReset}
    >
      {children}
    </ReactErrorBoundary>
  );
};

export default ErrorBoundary;

const getRouteErrorMessage = (error: unknown): string => {
  if (isRouteErrorResponse(error)) {
    return `${error.status} ${error.statusText}`;
  } else if (error instanceof Error) {
    return error.message;
  } else if (typeof error === 'string') {
    return error;
  } else {
    console.error(error);
    return 'Unknown error';
  }
};

/**
 * Error element for React Router.
 * @see https://reactrouter.com/en/main/route/error-element
 */
export const RouteErrorBoundary: React.FC = () => {
  const error = useRouteError();
  const [postLogEntryMutation] = usePostLogEntryMutation();

  useEffect(() => {
    error && postLogEntryMutation(error as Error, {});
  }, [error, postLogEntryMutation]);

  return (
    <ErrorPage>
      <ErrorCard
        title="An unexpected error has occurred in a route."
        error={getRouteErrorMessage(error)}
      />
    </ErrorPage>
  );
};

/**
 * Async error element for React Router's deferred data loading.
 * @see https://reactrouter.com/en/main/guides/deferred
 * @see https://reactrouter.com/en/main/components/await
 */
export const RouteAsyncErrorBoundary: React.FC = () => {
  const error = useAsyncError();
  const [postLogEntryMutation] = usePostLogEntryMutation();

  useEffect(() => {
    error && postLogEntryMutation(error as Error, {});
  }, [error, postLogEntryMutation]);

  return (
    <ErrorCard
      title="An unexpected async error has occurred in a route."
      error={getRouteErrorMessage(error)}
    />
  );
};
