import { createApi } from '@reduxjs/toolkit/query/react';

import { HOMEPAGE_CONFIG_ID } from '@/components/pageInfo/PageInfo';
import { baseQuery } from '@/services/baseQuery';

import type { StagedConfig } from '@/types/Config';
import type { ContentStub } from '@/types/Content';

export interface ContentListResponse {
  [key: string]: ContentStub;
}

export interface DeleteContentRequest {
  entityType: string;
  entityId: string;
}

export interface CreateContentResponse {
  entity_id: string;
  entity_type: string;
}

export interface CreateContentRequest {
  entity_id?: string;
  entity_type: string;
}

export interface ContentListParams {
  entityType: string;
  search?: string;
}

export const contentApi = createApi({
  reducerPath: 'contentApi',
  baseQuery,
  tagTypes: ['Content', 'StagedConfig'],
  endpoints: (builder) => ({
    getContentList: builder.query<ContentStub[], ContentListParams>({
      query: ({ entityType, search }) => {
        const params = new URLSearchParams();
        if (search) {
          const normalizedSearch = search.toLowerCase().trim();
          params.append('search', normalizedSearch);
        }
        return {
          url: `/canvas/api/v0/content/${entityType}`,
          params: search ? params : undefined,
        };
      },
      transformResponse: (response: ContentListResponse) => {
        return Object.values(response);
      },
      providesTags: [{ type: 'Content', id: 'LIST' }],
    }),
    deleteContent: builder.mutation<void, DeleteContentRequest>({
      query: ({ entityType, entityId }) => ({
        url: `/canvas/api/v0/content/${entityType}/${entityId}`,
        method: 'DELETE',
      }),
      invalidatesTags: [{ type: 'Content', id: 'LIST' }],
    }),
    createContent: builder.mutation<
      CreateContentResponse,
      CreateContentRequest
    >({
      query: ({ entity_type, entity_id }) => ({
        url: `/canvas/api/v0/content/${entity_type}`,
        method: 'POST',
        body: entity_id ? { entity_id } : {},
      }),
      invalidatesTags: [{ type: 'Content', id: 'LIST' }],
    }),
    getStagedConfig: builder.query<StagedConfig, string>({
      query: (entityId) => ({
        url: `/canvas/api/v0/config/auto-save/staged_config_update/${entityId}`,
        method: 'GET',
      }),
      providesTags: (_result, _error, entityId) => [
        { type: 'StagedConfig', id: entityId },
      ],
    }),
    setStagedConfig: builder.mutation<void, StagedConfig>({
      query: (body) => ({
        url: `/canvas/api/v0/staged-update/auto-save`,
        method: 'POST',
        body,
      }),
      // Hardcode HOMEPAGE_CONFIG_ID for now, as it is the only config we handle right now.
      // In the future we can generalize this.
      invalidatesTags: [
        { type: 'StagedConfig', id: HOMEPAGE_CONFIG_ID },
        { type: 'Content', id: 'LIST' },
      ],
    }),
  }),
});

export const {
  useGetContentListQuery,
  useDeleteContentMutation,
  useCreateContentMutation,
  useGetStagedConfigQuery,
  useSetStagedConfigMutation,
} = contentApi;
