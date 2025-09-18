import type { ErrorInfo } from 'react';
import type { ErrorResponse } from 'react-router-dom';

const logApi = {
  // Dummy service to log errors. Only a placeholder for now that mimics how
  // other services are defined and used.
  // @todo Implement in #3467844: Log client-side errors
  // (https://www.drupal.org/project/canvas/issues/3467844)
  usePostLogEntryMutation: () => [
    // @see https://github.com/bvaughn/react-error-boundary?tab=readme-ov-file#logging-errors-with-onerror
    (error: Error | ErrorResponse, info?: ErrorInfo) => {
      console.error(error);
    },
  ],
};

export const { usePostLogEntryMutation } = logApi;
