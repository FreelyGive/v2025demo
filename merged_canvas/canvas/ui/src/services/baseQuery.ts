import { fetchBaseQuery } from '@reduxjs/toolkit/query/react';

import type {
  BaseQueryApi,
  BaseQueryFn,
  FetchArgs,
  FetchBaseQueryError,
} from '@reduxjs/toolkit/query/react';
import type { RootState } from '@/app/store';
import type { AppConfiguration } from '@/features/configuration/configurationSlice';

export const baseQuery: BaseQueryFn<
  string | FetchArgs,
  unknown,
  FetchBaseQueryError
> = async (args, api, extraOptions) => {
  const state = api.getState() as RootState;
  return rawBaseQuery(state.configuration)(args, api, extraOptions);
};
/**
 * Extracts entityType and entityId from a URL
 * @param url - The URL string to parse.
 * @returns An object with entityType and entityId, or undefined values if not found.
 */
const extractEntityParams = (url: string) => {
  // Match /canvas/(editor||preview)/:entityType/:entityId/
  const matchPageEditor = url.match(
    /\/canvas\/(editor|preview)\/([^/]+)\/([^/]+)\/?/,
  );
  // /template/:entityType/:bundle/:viewMode/:previewEntityId/
  // /canvas/api/v0/layout-content-template/{entity_type}.{template_bundle}.{template_view_mode}/{entity_id}
  const matchTemplateEditor = url.match(
    /\/canvas\/template\/([^/]+)\/([^/]+)\/([^/]+)\/([^/]+)\/?/,
  );
  if (matchPageEditor) {
    return { entityType: matchPageEditor[2], entityId: matchPageEditor[3] };
  } else if (matchTemplateEditor) {
    return {
      entityType: matchTemplateEditor[1],
      templateBundle: matchTemplateEditor[2],
      templateViewMode: matchTemplateEditor[3],
      entityId: matchTemplateEditor[4],
    };
  }
  return { entityType: undefined, entityId: undefined };
};

/**
 * Replaces {entity_type} and {entity_id} in a URL string with extracted values.
 * Throws an error if a required value is missing.
 */
const replaceEntityParamsInUrl = (
  url: string,
  entityType?: string,
  entityId?: string,
  templateBundle?: string,
  templateViewMode?: string,
): string => {
  let newUrl = url;
  if (url.includes('{entity_type}')) {
    if (entityType !== undefined) {
      newUrl = newUrl.replace('{entity_type}', entityType);
    } else {
      throw new Error(
        `The URL "${url}" requires an Entity type, but it could not be extracted from the current location.`,
      );
    }
  }
  if (url.includes('{entity_id}')) {
    if (entityId !== undefined) {
      newUrl = newUrl.replace('{entity_id}', entityId);
    } else {
      throw new Error(
        `The URL "${url}" requires an Entity ID, but it could not be extracted from the current location.`,
      );
    }
  }
  if (url.includes('{template_bundle}')) {
    if (templateBundle !== undefined) {
      newUrl = newUrl.replace('{template_bundle}', templateBundle);
    } else {
      throw new Error(
        `The URL "${url}" requires a Template Bundle, but it could not be extracted from the current location.`,
      );
    }
  }
  if (url.includes('{template_view_mode}')) {
    if (templateViewMode !== undefined) {
      newUrl = newUrl.replace('{template_view_mode}', templateViewMode);
    } else {
      throw new Error(
        `The URL "${url}" requires a Template View Mode, but it could not be extracted from the current location.`,
      );
    }
  }
  return newUrl;
};

const rawBaseQuery = (appConfiguration: AppConfiguration) => {
  const { baseUrl } = appConfiguration;
  const defaultQuery = fetchBaseQuery({
    baseUrl,
    prepareHeaders: async (headers, api) => {
      if (api.type === 'mutation') {
        const csrfResponse = await fetch(`${baseUrl}session/token`);
        if (csrfResponse.ok) {
          const csrfToken = await csrfResponse.text();
          headers.set('X-CSRF-Token', csrfToken);
        } else {
          console.error('Failed to generate the CSRF token.');
        }
      }
    },
  });
  return async (
    arg: string | FetchArgs,
    api: BaseQueryApi,
    extraOptions: object = {},
  ) => {
    const url = typeof arg == 'string' ? arg : arg.url;
    const { entityType, entityId, templateBundle, templateViewMode } =
      extractEntityParams(window.location.href);
    const newUrl = replaceEntityParamsInUrl(
      url,
      entityType,
      entityId,
      templateBundle,
      templateViewMode,
    );
    const newArg = typeof arg == 'string' ? newUrl : { ...arg, url: newUrl };
    return defaultQuery(newArg, api, extraOptions);
  };
};

// Higher-order base query to inject the current autoSavesHash and clientInstanceId for all mutations (POST, PATCH, DELETE)
// to allow the backend to recognize potential conflicts where the client is updating potentially out of date data.
export const withAutoSavesInjection: (
  baseQuery: BaseQueryFn<string | FetchArgs, unknown, FetchBaseQueryError>,
) => BaseQueryFn<string | FetchArgs, unknown, FetchBaseQueryError> = (
  baseQuery,
) => {
  return (args, api, extraOptions) => {
    if (typeof args === 'object') {
      const { url } = args;
      if (
        url &&
        api.type === 'mutation' &&
        // Skip autoSaves injection for mutations that do not impact data that
        // is autosaved, such as creating folders.
        !['createFolder', 'createContentTemplate'].includes(api.endpoint)
      ) {
        const state = api.getState() as RootState;
        const { publishReview } = state;
        const { entityType, entityId, templateBundle, templateViewMode } =
          extractEntityParams(window.location.href);
        const autoSaveKey = replaceEntityParamsInUrl(
          url,
          entityType,
          entityId,
          templateBundle,
          templateViewMode,
        );
        // We want to send back the specific autoSave hash for the particular URL being updated
        const autoSaves =
          autoSaveKey && publishReview.autoSavesHash[autoSaveKey]
            ? publishReview.autoSavesHash[autoSaveKey]
            : undefined;
        return baseQuery(
          {
            ...args,
            body: {
              ...args.body,
              ...(autoSaves && { autoSaves }),
              clientInstanceId: publishReview.clientInstanceId,
            },
          },
          api,
          extraOptions,
        );
      }
    }
    return baseQuery(args, api, extraOptions);
  };
};

// Export a baseQuery with autoSaves injection by default
export const baseQueryWithAutoSaves: BaseQueryFn<
  string | FetchArgs,
  unknown,
  FetchBaseQueryError
> = withAutoSavesInjection(baseQuery);
