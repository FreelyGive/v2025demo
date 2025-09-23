// cspell:ignore CCMR
import { useCallback, useMemo } from 'react';

import { useAppDispatch, useAppSelector } from '@/app/hooks';
import { UnifiedMenu } from '@/components/UnifiedMenu';
import { moveNode, selectLayout } from '@/features/layout/layoutModelSlice';
import { findParentRegion } from '@/features/layout/layoutUtils';
import useComponentSelection from '@/hooks/useComponentSelection';

import type React from 'react';
import type {
  ComponentNode,
  RegionNode,
} from '@/features/layout/layoutModelSlice';

interface CCMRProps {
  component: ComponentNode;
}

const ComponentUnifiedMenuRegions: React.FC<CCMRProps> = (props) => {
  const { component } = props;
  const dispatch = useAppDispatch();
  const { unsetSelectedComponent } = useComponentSelection();
  const layout = useAppSelector(selectLayout);

  const parentRegion = useMemo(() => {
    return findParentRegion(layout, component.uuid);
  }, [layout, component.uuid]);

  const handleMoveClick = useCallback(
    (
      event: React.MouseEvent<HTMLElement>,
      destinationRegionIndex: number,
      destinationRegion: RegionNode,
    ) => {
      event.stopPropagation();
      const currentRegionIndex = layout.findIndex(
        (region) => region.id === parentRegion?.id,
      );

      // When moving to a region below (based on layers panel), it’s added to the beginning of the region
      // When moving to a region above (based on layers panel), it’s added to the end of the region
      let componentIndex = 0;
      if (currentRegionIndex > destinationRegionIndex) {
        componentIndex = destinationRegion.components.length;
      }
      dispatch(
        moveNode({
          uuid: component.uuid,
          to: [destinationRegionIndex, componentIndex],
        }),
      );
      // After sending something to another region, the URL is no longer valid because the selected :componentId is no
      // longer in the focused :regionId so this removes :componentId from the URL
      unsetSelectedComponent();
    },
    [
      layout,
      dispatch,
      component.uuid,
      unsetSelectedComponent,
      parentRegion?.id,
    ],
  );

  return (
    <UnifiedMenu.Sub>
      <UnifiedMenu.SubTrigger>Move to global region</UnifiedMenu.SubTrigger>
      <UnifiedMenu.SubContent>
        {layout.map((region, ix) => (
          <UnifiedMenu.Item
            key={region.id}
            onClick={(event) => handleMoveClick(event, ix, region)}
            disabled={region.id === parentRegion?.id}
            data-region-name={region.name}
          >
            {region.name}
          </UnifiedMenu.Item>
        ))}
      </UnifiedMenu.SubContent>
    </UnifiedMenu.Sub>
  );
};

export default ComponentUnifiedMenuRegions;
