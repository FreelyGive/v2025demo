import { useEffect, useState } from 'react';
import { useParams } from 'react-router';

import { useAppDispatch, useAppSelector } from '@/app/hooks';
import { Spotlight } from '@/components/spotlight/Spotlight';
import { useDataToHtmlMapValue } from '@/features/layout/preview/DataToHtmlMapContext';
import {
  clearSelection,
  DEFAULT_REGION,
  selectDragging,
} from '@/features/ui/uiSlice';
import useSyncPreviewElementSize from '@/hooks/useSyncPreviewElementSize';

export const RegionSpotlight = () => {
  const { regionsMap } = useDataToHtmlMapValue();
  const { regionId: focusedRegion = DEFAULT_REGION } = useParams();
  const [spotlight, setSpotlight] = useState(false);
  const { elementRect } = useSyncPreviewElementSize(
    regionsMap[focusedRegion]?.elements,
  );
  const { isDragging } = useAppSelector(selectDragging);
  const dispatch = useAppDispatch();

  useEffect(() => {
    // When focusing into a different region, clear the multi selection
    dispatch(clearSelection());
  }, [dispatch, focusedRegion]);

  useEffect(() => {
    if (focusedRegion && regionsMap) {
      if (focusedRegion !== DEFAULT_REGION) {
        setSpotlight(true);
        return;
      }
    }
    setSpotlight(false);
  }, [focusedRegion, regionsMap]);

  if (spotlight && elementRect) {
    return (
      <Spotlight
        top={elementRect.top}
        left={elementRect.left}
        width={elementRect.width}
        height={elementRect.height}
        blocking={!isDragging}
      />
    );
  }
  return null;
};
