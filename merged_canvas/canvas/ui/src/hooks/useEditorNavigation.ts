import { useCallback } from 'react';
import { useLocation, useNavigate } from 'react-router-dom';

import { DEFAULT_REGION } from '@/features/ui/uiSlice';
import { getBaseUrl, getCanvasSettings } from '@/utils/drupal-globals';
import {
  removeComponentFromPathname,
  setRegionInPathname,
} from '@/utils/route-utils';

const canvasSettings = getCanvasSettings();

/**
 * Hook for editor navigation functions
 * Handles URL/route based navigation for regions and entities
 */
export function useEditorNavigation() {
  const navigate = useNavigate();
  const location = useLocation();
  const drupalBaseUrl = getBaseUrl();

  const setSelectedRegion = useCallback(
    (regionId?: string) => {
      // Remove any /component/:componentId from the path first
      const basePath = removeComponentFromPathname(location.pathname);
      // Use the utility to robustly set /region/:regionId
      const newPath = setRegionInPathname(basePath, regionId, DEFAULT_REGION);
      navigate(newPath);
    },
    [navigate, location.pathname],
  );

  const setEditorEntity = useCallback(
    (entityType: string, entityId: string) => {
      // @todo revisit approach (like using FE routing) and see if timeout can be removed in follow up to https://www.drupal.org/i/3502887
      // For now, we are using window.location.href to force a full page reload
      // to ensure all state is reset when switching entities. Later we can use navigate:
      // navigate(`${baseUrl}editor/${entityType}/${entityId}`);
      setTimeout(() => {
        // Use a timeout to ensure that RTK query cleans up its subscriptions first before navigating away.
        // Without this timeout, RTK throws an error because it tries to make a request following cache invalidation while
        // the window.location.href is in progress.
        window.location.href = `${drupalBaseUrl}canvas/editor/${entityType}/${entityId}`;
      }, 100);
    },
    [drupalBaseUrl],
  );

  const editorNavUtils = {
    setSelectedRegion,
    setEditorEntity,
  };

  canvasSettings.navUtils = editorNavUtils;

  return editorNavUtils;
}

export default useEditorNavigation;
