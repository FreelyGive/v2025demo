import { useEffect, useMemo, useState } from 'react';
import clsx from 'clsx';
import { useParams } from 'react-router';

import { useAppSelector } from '@/app/hooks';
import { useDataToHtmlMapValue } from '@/features/layout/preview/DataToHtmlMapContext';
import { SlotNameTag } from '@/features/layout/preview/NameTag';
import ComponentOverlay from '@/features/layout/previewOverlay/ComponentOverlay';
import EmptySlotDropZone from '@/features/layout/previewOverlay/EmptySlotDropZone';
import {
  selectEditorViewPortScale,
  selectIsComponentHovered,
  selectTargetSlot,
} from '@/features/ui/uiSlice';
import useGetComponentName from '@/hooks/useGetComponentName';
import useSyncPreviewElementOffset from '@/hooks/useSyncPreviewElementOffset';
import useSyncPreviewElementSize from '@/hooks/useSyncPreviewElementSize';

import type React from 'react';
import type {
  ComponentNode,
  SlotNode,
} from '@/features/layout/layoutModelSlice';

import styles from './PreviewOverlay.module.css';

// import SlotDropZone from '@/features/layout/previewOverlay/SlotDropZone';

export interface SlotOverlayProps {
  slot: SlotNode;
  iframeRef: React.RefObject<HTMLIFrameElement>;
  parentComponent: ComponentNode;
  disableDrop: boolean;
  forceRecalculate?: number; // Increment this prop to trigger a re-calculation of the slot overlay's border rect
}

const SlotOverlay: React.FC<SlotOverlayProps> = (props) => {
  const {
    slot,
    parentComponent,
    iframeRef,
    disableDrop,
    forceRecalculate = 0,
  } = props;
  const { componentsMap, slotsMap } = useDataToHtmlMapValue();
  const slotId = slot.id;
  const slotElementArray = useMemo(() => {
    const element = slotsMap[slot.id]?.element;
    return element ? [element] : null;
  }, [slotsMap, slot.id]);
  const { elementRect, recalculateBorder } =
    useSyncPreviewElementSize(slotElementArray);
  const parentElementsInsideIframe =
    componentsMap[parentComponent.uuid]?.elements;
  const { offset, recalculateOffset } = useSyncPreviewElementOffset(
    slotElementArray,
    parentElementsInsideIframe ? parentElementsInsideIframe : null,
  );
  // Padding calculation (if needed for visual reasons)
  const [padding, setPadding] = useState({
    paddingTop: '0px',
    paddingBottom: '0px',
  });
  useEffect(() => {
    const elementInsideIframe = slotsMap[slotId]?.element;
    if (elementInsideIframe) {
      const computedStyle = window.getComputedStyle(elementInsideIframe);
      setPadding({
        paddingTop: computedStyle.paddingTop,
        paddingBottom: computedStyle.paddingBottom,
      });
    }
  }, [slotsMap, slotId]);

  const { componentId: selectedComponent } = useParams();
  const isHovered = useAppSelector((state) => {
    return selectIsComponentHovered(state, slotId);
  });
  const targetSlot = useAppSelector(selectTargetSlot);
  const editorViewPortScale = useAppSelector(selectEditorViewPortScale);
  const slotName = useGetComponentName(slot, parentComponent);
  const parentComponentName = useGetComponentName(parentComponent);
  const [forceRecalculateChildren, setForceRecalculateChildren] = useState(0);

  // Recalculate the children's borders when the elementRect changes
  useEffect(() => {
    setForceRecalculateChildren((prev) => prev + 1);
  }, [elementRect]);

  // Recalculate the border when the parent increments the forceRecalculate prop
  useEffect(() => {
    recalculateBorder();
    recalculateOffset();
  }, [forceRecalculate, recalculateBorder, recalculateOffset]);

  const style: React.CSSProperties = useMemo(
    () => ({
      height: elementRect.height * editorViewPortScale,
      width: elementRect.width * editorViewPortScale,
      top: (offset.offsetTop || 0) * editorViewPortScale,
      left: (offset.offsetLeft || 0) * editorViewPortScale,
      pointerEvents: 'none',
      ...padding,
    }),
    [
      elementRect.height,
      elementRect.width,
      editorViewPortScale,
      offset.offsetTop,
      offset.offsetLeft,
      padding,
    ],
  );

  return (
    <div
      aria-label={`${slotName} (${parentComponentName})`}
      className={clsx('slotOverlay', styles.slotOverlay, {
        [styles.selected]: slotId === selectedComponent,
        [styles.hovered]: isHovered,
        [styles.dropTarget]: slotId === targetSlot,
      })}
      data-canvas-type="slot"
      style={style}
    >
      {(targetSlot === slotId || isHovered) && (
        <div className={clsx(styles.canvasNameTag, styles.canvasNameTagSlot)}>
          <SlotNameTag
            name={`${slotName} (${parentComponentName})`}
            id={slotId}
            nodeType={slot.nodeType}
          />
        </div>
      )}
      {!slot.components.length && !disableDrop && (
        <EmptySlotDropZone
          slot={slot}
          slotName={slotName}
          parentComponent={parentComponent}
        />
      )}

      {slot.components.map((childComponent: ComponentNode, index) => (
        <ComponentOverlay
          key={childComponent.uuid}
          iframeRef={iframeRef}
          parentSlot={slot}
          component={childComponent}
          index={index}
          disableDrop={disableDrop}
          forceRecalculate={forceRecalculateChildren}
        />
      ))}

      {/* @todo - these SlotDropZones might become useful in future for handling more complex nested "container" components */}
      {/*{!disableDrop && (*/}
      {/*<SlotDropZone*/}
      {/*  slot={slot}*/}
      {/*  position="before"*/}
      {/*  size={size}*/}
      {/*  parentComponent={parentComponent}*/}
      {/*/>*/}
      {/*<SlotDropZone*/}
      {/*  slot={slot}*/}
      {/*  position="after"*/}
      {/*  size={size}*/}
      {/*  parentComponent={parentComponent}*/}
      {/*/>*/}
      {/*)}*/}
    </div>
  );
};

export default SlotOverlay;
