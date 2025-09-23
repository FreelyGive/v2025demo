// Need to use the React-specific entry point to import createApi
import { createApi } from '@reduxjs/toolkit/query/react';

import addAjaxPageState from '@/services/addAjaxPageState';
import { baseQuery } from '@/services/baseQuery';
import processResponseAssets from '@/services/processResponseAssets';

import type { EditorFrameContext } from '@/features/ui/uiSlice';
import type { TransformConfig } from '@/utils/transforms';

let lastArgInUseByAnyComponent: string | undefined = '';

export const componentInstanceFormApi = createApi({
  reducerPath: 'componentInstanceFormApi',
  baseQuery,
  endpoints: (builder) => ({
    getComponentInstanceForm: builder.query<
      { html: string; transforms: TransformConfig },
      { queryString: string; type: EditorFrameContext }
    >({
      query: ({ queryString, type }) => {
        const fullQueryString = addAjaxPageState(queryString);
        let url = '';
        if (type === 'entity') {
          url = `canvas/api/v0/form/component-instance/{entity_type}/{entity_id}`;
        } else if (type === 'template') {
          url = `canvas/api/v0/form/component-instance/content_template/{entity_type}.{template_bundle}.{template_view_mode}/{entity_id}`;
        } else {
          throw new Error(
            `Cannot render component instance form for unknown type: ${type}. Type must be one of 'entity' or 'template'.`,
          );
        }
        return {
          url,
          // We use PATCH to keep this distinct from AJAX form submissions which
          // use POST.
          method: 'PATCH',
          body: fullQueryString,
          headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
          },
        };
      },
      forceRefetch: ({ currentArg, previousArg, endpointState }) => {
        // When true, this will fetch new data on the request, but will use
        // cached data until the new data is available.
        const noChangesFound =
          currentArg?.queryString === previousArg?.queryString &&
          lastArgInUseByAnyComponent === currentArg?.queryString;

        lastArgInUseByAnyComponent = currentArg?.queryString;
        return !noChangesFound;
      },
      transformResponse: processResponseAssets(['html', 'transforms']),
    }),
  }),
});

// Export hooks for usage in functional layout, which are
// auto-generated based on the defined endpoints
export const { useGetComponentInstanceFormQuery } = componentInstanceFormApi;
