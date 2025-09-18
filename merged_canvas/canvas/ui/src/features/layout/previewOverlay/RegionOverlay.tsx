import { useEffect, useState } from 'react';
import clsx from 'clsx';
import { useParams } from 'react-router';

import { useAppDispatch, useAppSelector } from '@/app/hooks';
import { selectLayoutForRegion } from '@/features/layout/layoutModelSlice';
import { useDataToHtmlMapValue } from '@/features/layout/preview/DataToHtmlMapContext';
import { RegionNameTag } from '@/features/layout/preview/NameTag';
import RegionContextMenu from '@/features/layout/preview/RegionContextMenu';
import ComponentOverlay from '@/features/layout/previewOverlay/ComponentOverlay';
import EmptyRegionDropZone from '@/features/layout/previewOverlay/EmptyRegionDropZone';
import RegionDropZone from '@/features/layout/previewOverlay/RegionDropZone';
import {
  DEFAULT_REGION,
  selectDragging,
  selectEditorViewPortScale,
  selectIsComponentHovered,
  selectTargetSlot,
  setHoveredComponent,
  unsetHoveredComponent,
} from '@/features/ui/uiSlice';
import useEditorNavigation from '@/hooks/useEditorNavigation';
import useSyncPreviewElementSize from '@/hooks/useSyncPreviewElementSize';

import type React from 'react';
import type { RegionNode } from '@/features/layout/layoutModelSlice';

import styles from './PreviewOverlay.module.css';

interface RegionOverlayProps {
  iframeRef: React.RefObject<HTMLIFrameElement>;
  regionId: string;
  regionName: string;
  region: RegionNode;
}

const RegionOverlay: React.FC<RegionOverlayProps> = ({ iframeRef, region }) => {
  const layout = useAppSelector((state) =>
    selectLayoutForRegion(state, region.id),
  );
  const { regionsMap } = useDataToHtmlMapValue();
  const { regionId: focusedRegion = DEFAULT_REGION } = useParams();
  const { elementRect } = useSyncPreviewElementSize(
    regionsMap[region.id]?.elements,
  );
  const editorViewPortScale = useAppSelector(selectEditorViewPortScale);
  const [overlayStyles, setOverlayStyles] = useState({});
  const targetSlot = useAppSelector(selectTargetSlot);
  const disableRegion = focusedRegion !== region.id;
  const dispatch = useAppDispatch();
  const { isDragging } = useAppSelector(selectDragging);
  const isHovered = useAppSelector((state) => {
    return selectIsComponentHovered(state, region.id);
  });
  const { setSelectedRegion } = useEditorNavigation();

  const showHovered = isHovered && focusedRegion === DEFAULT_REGION;

  useEffect(() => {
    setOverlayStyles({
      top: `${elementRect.top * editorViewPortScale}px`,
      left: `${elementRect.left * editorViewPortScale}px`,
      width: `${elementRect.width * editorViewPortScale}px`,
      height: `${elementRect.height * editorViewPortScale}px`,
    });
  }, [elementRect, editorViewPortScale, region.id, disableRegion, regionsMap]);

  function handleItemMouseOver(event: React.MouseEvent<HTMLDivElement>) {
    event.stopPropagation();
    if (!isDragging) {
      dispatch(setHoveredComponent(region.id));
    }
  }

  function handleItemMouseOut(event: React.MouseEvent<HTMLDivElement>) {
    event.stopPropagation();
    dispatch(unsetHoveredComponent());
  }

  function handleRegionDblClick(event: React.MouseEvent<HTMLDivElement>) {
    event.stopPropagation();
    setSelectedRegion(region.id);
  }

  // If the DEFAULT_REGION is focused, then all regions should render otherwise only render if this is the focused region
  if (focusedRegion !== DEFAULT_REGION && focusedRegion !== region.id) {
    return null;
  }

  const isPage = region.id === DEFAULT_REGION;

  return (
    <div
      className={clsx(
        [isPage && styles.pageOverlay, !isPage && styles.regionOverlay],
        {
          [styles.dropTarget]: region.id === targetSlot,
          [styles.hovered]: showHovered,
        },
        `canvas--region-overlay__${region.id}`,
      )}
      style={overlayStyles}
      onMouseOver={handleItemMouseOver}
      onMouseOut={handleItemMouseOut}
      onDoubleClick={handleRegionDblClick}
    >
      {!isPage && (
        <RegionContextMenu region={region}>
          <div
            aria-label={`Global region ${region.name}`}
            className={styles.regionItem}
            data-canvas-overlay="true"
          />
        </RegionContextMenu>
      )}

      <div className={clsx(styles.canvasNameTag)}>
        <RegionNameTag
          name={region.name}
          id={region.id}
          nodeType={isPage ? 'page' : 'region'}
        />
      </div>

      {!disableRegion && (
        <>
          {layout.components.map((component, index) => (
            <ComponentOverlay
              key={component.uuid}
              iframeRef={iframeRef}
              component={component}
              parentRegion={layout}
              index={index}
            />
          ))}

          {!region.components.length && <EmptyRegionDropZone region={region} />}
          {!!region.components.length && (
            <>
              <RegionDropZone region={region} position="before" />
              <RegionDropZone region={region} position="after" />
            </>
          )}
        </>
      )}
    </div>
  );
};

export default RegionOverlay;
