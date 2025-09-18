import { createApi } from '@reduxjs/toolkit/query/react';

import { baseQueryWithAutoSaves } from '@/services/baseQuery';
import { handleAutoSavesHashUpdate } from '@/utils/autoSaves';

import type { AutoSavesHash } from '@/types/AutoSaves';
import type { AssetLibrary } from '@/types/CodeComponent';

export const assetLibraryApi = createApi({
  reducerPath: 'assetLibraryApi',
  baseQuery: baseQueryWithAutoSaves,
  tagTypes: ['AssetLibraries', 'AssetLibrariesAutoSave'],
  endpoints: (builder) => ({
    getAssetLibraries: builder.query<Record<string, AssetLibrary>, void>({
      query: () => 'canvas/api/v0/config/asset_library',
      providesTags: () => [{ type: 'AssetLibraries', id: 'LIST' }],
    }),
    getAssetLibrary: builder.query<AssetLibrary, string>({
      query: (id) => `canvas/api/v0/config/asset_library/${id}`,
      providesTags: (result, error, id) => [{ type: 'AssetLibraries', id }],
    }),
    createAssetLibrary: builder.mutation<AssetLibrary, Partial<AssetLibrary>>({
      query: (body) => ({
        url: 'canvas/api/v0/config/asset_library',
        method: 'POST',
        body,
      }),
      invalidatesTags: [{ type: 'AssetLibraries', id: 'LIST' }],
    }),
    updateAssetLibrary: builder.mutation<
      AssetLibrary,
      { id: string; changes: Partial<AssetLibrary> }
    >({
      query: ({ id, changes }) => ({
        url: `canvas/api/v0/config/asset_library/${id}`,
        method: 'PATCH',
        body: changes,
      }),
      invalidatesTags: (result, error, { id }) => [
        { type: 'AssetLibraries', id },
        { type: 'AssetLibraries', id: 'LIST' },
      ],
    }),
    deleteAssetLibrary: builder.mutation<void, string>({
      query: (id) => ({
        url: `canvas/api/v0/config/asset_library/${id}`,
        method: 'DELETE',
      }),
      invalidatesTags: [{ type: 'AssetLibraries', id: 'LIST' }],
    }),
    getAutoSave: builder.query<
      { data: AssetLibrary; autoSaves: AutoSavesHash },
      string
    >({
      query: (id) => `canvas/api/v0/config/auto-save/asset_library/${id}`,
      providesTags: (result, error, id) => [
        { type: 'AssetLibrariesAutoSave', id },
      ],
      async onQueryStarted(id, { dispatch, queryFulfilled }) {
        try {
          const { data, meta } = await queryFulfilled;
          const { autoSaves } = data;
          handleAutoSavesHashUpdate(dispatch, autoSaves, meta);
        } catch (err) {
          console.error(err);
        }
      },
    }),
    updateAutoSave: builder.mutation<
      void,
      {
        id: string;
        data: Partial<AssetLibrary>;
      }
    >({
      query: ({ id, data }) => ({
        url: `canvas/api/v0/config/auto-save/asset_library/${id}`,
        method: 'PATCH',
        body: { data },
      }),
      invalidatesTags: (result, error, { id }) => [
        { type: 'AssetLibrariesAutoSave', id },
      ],
    }),
  }),
});

export const {
  useGetAssetLibrariesQuery,
  useGetAssetLibraryQuery,
  useCreateAssetLibraryMutation,
  useUpdateAssetLibraryMutation,
  useDeleteAssetLibraryMutation,
  useGetAutoSaveQuery,
  useUpdateAutoSaveMutation,
} = assetLibraryApi;
