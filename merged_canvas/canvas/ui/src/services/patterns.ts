// Need to use the React-specific entry point to import createApi
import { createApi } from '@reduxjs/toolkit/query/react';

import { baseQuery } from '@/services/baseQuery';

import type { LayoutModelPiece } from '@/features/layout/layoutModelSlice';
import type { PatternsList } from '@/types/Pattern';

interface SavePatternData extends LayoutModelPiece {
  name: string;
}

// Define a service using a base URL and expected endpoints
export const patternApi = createApi({
  reducerPath: 'patternsApi',
  baseQuery,
  tagTypes: ['Patterns'],
  endpoints: (builder) => ({
    getPatterns: builder.query<PatternsList, void>({
      query: () => `/canvas/api/v0/config/pattern`,
      providesTags: () => [{ type: 'Patterns', id: 'LIST' }],
    }),
    savePattern: builder.mutation<{ html: string }, SavePatternData>({
      query: (body) => ({
        url: '/canvas/api/v0/config/pattern',
        method: 'POST',
        body,
      }),
      invalidatesTags: () => [{ type: 'Patterns', id: 'LIST' }],
    }),
    deletePattern: builder.mutation<void, string>({
      query: (id) => ({
        url: `/canvas/api/v0/config/pattern/${id}`,
        method: 'DELETE',
      }),
      invalidatesTags: () => [{ type: 'Patterns', id: 'LIST' }],
    }),
  }),
});

// Export hooks for usage in functional patterns, which are
// auto-generated based on the defined endpoints
export const {
  useGetPatternsQuery,
  useSavePatternMutation,
  useDeletePatternMutation,
} = patternApi;
