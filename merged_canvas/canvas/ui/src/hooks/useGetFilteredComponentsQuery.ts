import { useMemo } from 'react';

import { useGetComponentsQuery } from '@/services/componentAndLayout';

import type { ComponentsList, libraryTypes } from '@/types/Component';

type getComponentsQueryOptions = {
  libraries: libraryTypes[];
  mode: 'include' | 'exclude';
};

/**
 * This hook retrieves the complete list of components using RTK Query's caching mechanism
 * and applies client-side filtering to return only the relevant components as per the
 * provided filtering options.
 *
 * @param filterOptions - Options to filter the components.
 * @param {'include' | 'exclude'} filterOptions.mode - Determines if the specified library types will be included or excluded.
 * @param {string[]} filterOptions.libraries - The list of library types to be included/excluded.
 *
 * @returns An object closely matching the RTK Query pattern containing:
 *   - `filteredComponents`: A `ComponentsList` object with components filtered based on the specified criteria.
 *   - `error`: Any errors encountered during the fetch process.
 *   - `isLoading`: A boolean indicating if the data is currently being loaded.
 */

export const useGetFilteredComponentsQuery = (
  filterOptions: getComponentsQueryOptions,
) => {
  const { data: components, error, isLoading } = useGetComponentsQuery();

  const filteredComponents = useMemo((): ComponentsList => {
    if (!components) return {};

    const { mode, libraries } = filterOptions;

    return Object.fromEntries(
      Object.entries(components)
        .filter(([, value]) => {
          const isIncluded = libraries.includes(value.library);
          return mode === 'include' ? isIncluded : !isIncluded;
        })
        .sort(([, a], [, b]) => a.name.localeCompare(b.name)),
    );
  }, [components, filterOptions]);

  return { filteredComponents, error, isLoading };
};
