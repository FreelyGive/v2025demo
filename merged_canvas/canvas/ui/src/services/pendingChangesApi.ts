// Need to use the React-specific entry point to import createApi
import { createApi } from '@reduxjs/toolkit/query/react';

import {
  setConflicts,
  setErrors,
  setPreviousPendingChanges,
} from '@/components/review/PublishReview.slice';
import { baseQuery } from '@/services/baseQuery';

interface Owner {
  name: string;
  avatar: string | null;
  uri: string;
  id: number;
}

export interface PendingChange {
  owner: Owner;
  entity_type: string;
  entity_id: string | number;
  data_hash: string;
  langcode: string;
  label: string;
  updated: number;
  hasConflict?: boolean;
}

export type PendingChanges = {
  [x: string]: PendingChange;
};

interface SuccessResponse {
  message: string;
}

export interface ConflictError {
  code: number;
  detail: string;
  source: {
    pointer: string;
  };
  meta?: {
    entity_type: string;
    entity_id: string | number;
    label: string;
  };
}

export interface ErrorResponse {
  errors: Array<ConflictError>;
}

export enum STATUS_CODE {
  CONFLICT = 409,
  UNPROCESSABLE_ENTITY = 422,
}

export enum CONFLICT_CODE {
  UNEXPECTED = 1,
  EXPECTED = 2,
}

// Define a service using a base URL and expected endpoints
export const pendingChangesApi = createApi({
  reducerPath: 'pendingChangesApi',
  baseQuery,
  tagTypes: ['PendingChanges'],
  endpoints: (builder) => ({
    getAllPendingChanges: builder.query<PendingChanges, void>({
      query: () => `/canvas/api/v0/auto-saves/pending`,
      providesTags: () => [{ type: 'PendingChanges', id: 'LIST' }],
    }),
    publishAllPendingChanges: builder.mutation<
      SuccessResponse | ErrorResponse,
      PendingChanges
    >({
      query: (body) => ({
        url: `/canvas/api/v0/auto-saves/publish`,
        method: 'POST',
        body,
      }),
      async onQueryStarted(body, { dispatch, queryFulfilled }) {
        try {
          await queryFulfilled;

          dispatch(
            pendingChangesApi.util.updateQueryData(
              'getAllPendingChanges',
              undefined,
              (draft) => {
                // Remove only the changes that were successfully published
                Object.keys(body).forEach((key) => {
                  delete draft[key];
                });
                return draft;
              },
            ),
          );
          dispatch(setPreviousPendingChanges());
          dispatch(setErrors());
        } catch (error: any) {
          dispatch(setErrors(error.error?.data));

          // Handle conflicts
          // @todo https://www.drupal.org/i/3503404
          if (error.error?.status === STATUS_CODE.CONFLICT) {
            // set previous response
            dispatch(setPreviousPendingChanges(body));
            // set conflicts
            dispatch(setConflicts(error?.error?.data?.errors));
          }
        }
      },
    }),
    discardPendingChange: builder.mutation<
      SuccessResponse | ErrorResponse,
      PendingChange
    >({
      query: (change: PendingChange) => ({
        url: `/canvas/api/v0/auto-saves/${change.entity_type}/${change.entity_id}`,
        method: 'DELETE',
      }),
      async onQueryStarted(change, { dispatch, queryFulfilled }) {
        try {
          await queryFulfilled;

          // Given the nature of the discard operation, we need to
          // reload the page here to ensure that the UI reflects the discarded
          // changes if the operation was successful.
          // This is a temporary solution to ensure the UI is in sync with the
          // server state after publishing changes.
          // A better solution will be implemented in the future.
          window.location.reload();

          // Reset errors
          dispatch(setErrors());
        } catch (error: any) {
          dispatch(
            setErrors({
              errors: [
                {
                  code: 0,
                  detail:
                    error?.error?.data?.message ??
                    'Failed to discard pending change',
                  source: { pointer: '' },
                  meta: change,
                },
              ],
            }),
          );
        }
      },
    }),
  }),
});

// Export hooks for usage in functional layout, which are
// auto-generated based on the defined endpoints
export const {
  useGetAllPendingChangesQuery,
  usePublishAllPendingChangesMutation,
  useDiscardPendingChangeMutation,
} = pendingChangesApi;
