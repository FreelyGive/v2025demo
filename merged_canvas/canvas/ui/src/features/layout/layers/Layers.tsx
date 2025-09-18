import React, { useMemo } from 'react';
import { useParams } from 'react-router';
import { Box, Separator } from '@radix-ui/themes';

import { useAppSelector } from '@/app/hooks';
import RegionLayer from '@/features/layout/layers/RegionLayer';
import { selectLayout } from '@/features/layout/layoutModelSlice';
import { DEFAULT_REGION } from '@/features/ui/uiSlice';
import useExpandParentsOnSelection from '@/hooks/useExpandParentsOnSelection';
import useSyncCollapsedLayersLocalStorage from '@/hooks/useSyncCollapsedLayersLocalStorage';

interface LayersProps {}

const Layers: React.FC<LayersProps> = () => {
  const regions = useAppSelector(selectLayout);
  const { regionId: focusedRegion = DEFAULT_REGION } = useParams();
  useSyncCollapsedLayersLocalStorage();
  useExpandParentsOnSelection();

  const displayedRegions = useMemo(() => {
    let filteredRegions = regions.filter((region) => {
      return region.components.length > 0 || region.id === DEFAULT_REGION;
    });

    if (focusedRegion !== DEFAULT_REGION) {
      filteredRegions = filteredRegions.filter((region) => {
        return region.id === focusedRegion;
      });
    }

    return filteredRegions;
  }, [regions, focusedRegion]);

  return (
    <Box>
      {displayedRegions.map((region, index) => (
        <React.Fragment key={region.id}>
          {focusedRegion === region.id && region.id === DEFAULT_REGION ? (
            <Box>
              {index > 0 && (
                <Separator orientation="horizontal" size="4" my="2" />
              )}{' '}
              <RegionLayer
                region={region}
                isPage={region.id === DEFAULT_REGION}
              />
              {index < displayedRegions.length - 1 && (
                <Separator orientation="horizontal" size="4" my="2" />
              )}
            </Box>
          ) : (
            <RegionLayer region={region} />
          )}
        </React.Fragment>
      ))}
    </Box>
  );
};

export default Layers;
