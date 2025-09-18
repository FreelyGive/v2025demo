import { useEffect } from 'react';
import { useNavigate, useParams } from 'react-router-dom';

import { useAppSelector } from '@/app/hooks';
import { selectLayoutForRegion } from '@/features/layout/layoutModelSlice';
import { DEFAULT_REGION, selectFirstLoadComplete } from '@/features/ui/uiSlice';

/**
 * This hook watches the layout array of the currently selected region. If the region's list of components
 * becomes empty, it will navigate the user out of the region.
 */
const useLayoutWatcher = () => {
  const navigate = useNavigate();
  const { regionId, entityId, entityType } = useParams();
  const currentRegion = regionId || DEFAULT_REGION;
  const regionLayout = useAppSelector((state) =>
    selectLayoutForRegion(state, currentRegion),
  );
  const firstLoadComplete = useAppSelector(selectFirstLoadComplete);

  useEffect(() => {
    if (
      firstLoadComplete && // Only navigate if data has finished loading
      regionLayout.components.length === 0 &&
      currentRegion !== DEFAULT_REGION
    ) {
      // We are focused into a region that is empty, navigate the user back to the DEFAULT_REGION
      navigate(`/editor/${entityType}/${entityId}`);
    }
  }, [
    regionLayout,
    navigate,
    currentRegion,
    firstLoadComplete,
    entityType,
    entityId,
  ]);
};

export default useLayoutWatcher;
