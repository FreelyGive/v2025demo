import { useEffect, useMemo, useRef, useState } from 'react';
import clsx from 'clsx';
import { useDraggable } from '@dnd-kit/core';

import { useAppDispatch, useAppSelector } from '@/app/hooks';
import ComponentContextMenu from '@/features/layout/preview/ComponentContextMenu';
import { useDataToHtmlMapValue } from '@/features/layout/preview/DataToHtmlMapContext';
import { ComponentNameTag } from '@/features/layout/preview/NameTag';
import ComponentDropZone from '@/features/layout/previewOverlay/ComponentDropZone';
import SlotOverlay from '@/features/layout/previewOverlay/SlotOverlay';
import {
  selectComponentIsSelected,
  selectDragging,
  selectEditorViewPortScale,
  selectIsComponentHovered,
  selectIsComponentUpdating,
  setHoveredComponent,
  unsetHoveredComponent,
} from '@/features/ui/uiSlice';
import useComponentSelection from '@/hooks/useComponentSelection';
import useGetComponentName from '@/hooks/useGetComponentName';
import useSyncPreviewElementOffset from '@/hooks/useSyncPreviewElementOffset';
import useSyncPreviewElementSize from '@/hooks/useSyncPreviewElementSize';

import type React from 'react';
import type {
  ComponentNode,
  RegionNode,
  SlotNode,
} from '@/features/layout/layoutModelSlice';
import type { StackDirection } from '@/types/Annotations';

import styles from './PreviewOverlay.module.css';

export interface ComponentOverlayProps {
  component: ComponentNode;
  iframeRef: React.RefObject<HTMLIFrameElement>;
  parentSlot?: SlotNode;
  parentRegion?: RegionNode;
  index: number;
  disableDrop?: boolean;
  forceRecalculate?: number; // Increment this prop to trigger a re-calculation of the component overlay's border rect
}

const ComponentOverlay: React.FC<ComponentOverlayProps> = (props) => {
  const {
    component,
    parentSlot,
    parentRegion,
    iframeRef,
    index,
    disableDrop = false,
    forceRecalculate = 0,
  } = props;

  const { componentsMap, slotsMap, regionsMap } = useDataToHtmlMapValue();
  const { elementRect, recalculateBorder } = useSyncPreviewElementSize(
    componentsMap[component.uuid]?.elements,
  );

  let parentElementInsideIframe = null;
  if (parentRegion?.id) {
    parentElementInsideIframe = regionsMap[parentRegion.id]?.elements;
  }
  if (parentSlot?.id) {
    parentElementInsideIframe = slotsMap[parentSlot.id]?.element;
  }
  const { offset, recalculateOffset } = useSyncPreviewElementOffset(
    componentsMap[component.uuid]?.elements,
    parentElementInsideIframe ? parentElementInsideIframe : null,
  );
  const [initialized, setInitialized] = useState(false);
  const isHovered = useAppSelector((state) => {
    return selectIsComponentHovered(state, component.uuid);
  });
  const isUpdating = useAppSelector((state) => {
    return selectIsComponentUpdating(state, component.uuid);
  });
  const editorViewPortScale = useAppSelector(selectEditorViewPortScale);
  const dispatch = useAppDispatch();
  const { setSelectedComponent, handleComponentSelection } =
    useComponentSelection();
  const { isDragging } = useAppSelector(selectDragging);
  const elementsInsideIframe = useRef<HTMLElement[] | []>([]);
  const name = useGetComponentName(component);
  const {
    attributes,
    listeners,
    setNodeRef,
    isDragging: isComponentDragged,
  } = useDraggable({
    id: `${component.uuid}`,
    data: {
      origin: 'overlay',
      component: component,
      name: name,
      elementsInsideIframe: elementsInsideIframe.current,
    },
  });
  const [forceRecalculateChildren, setForceRecalculateChildren] = useState(0);

  const isSelected = useAppSelector((state) =>
    selectComponentIsSelected(state, component.uuid),
  );

  useEffect(() => {
    const iframeDocument = iframeRef.current?.contentDocument;
    if (!iframeDocument || !componentsMap[component.uuid]) {
      return;
    }

    elementsInsideIframe.current = componentsMap[component.uuid]?.elements;
  }, [slotsMap, componentsMap, elementRect, component.uuid, iframeRef]);

  useEffect(() => {
    if (offset.offsetLeft !== undefined || offset.offsetTop !== undefined) {
      setInitialized(true);
    }
  }, [offset.offsetLeft, offset.offsetTop]);

  // Recalculate the children's borders when the elementRect changes
  useEffect(() => {
    setForceRecalculateChildren((prev) => prev + 1);
  }, [elementRect]);

  // Recalculate the border when the parent increments the forceRecalculate prop
  useEffect(() => {
    recalculateBorder();
    recalculateOffset();
  }, [forceRecalculate, recalculateBorder, recalculateOffset]);

  function handleComponentClick(event: React.MouseEvent<HTMLElement>) {
    event.stopPropagation();
    handleComponentSelection(component.uuid, event.metaKey);
  }

  function handleItemMouseOver(event: React.MouseEvent<HTMLDivElement>) {
    event.stopPropagation();
    if (!isDragging) {
      dispatch(setHoveredComponent(component.uuid));
    }
  }

  function handleItemMouseOut(event: React.MouseEvent<HTMLDivElement>) {
    event.stopPropagation();
    dispatch(unsetHoveredComponent());
  }

  function handleKeyDown(event: React.KeyboardEvent<HTMLDivElement>) {
    if (event.key === 'Enter' || event.key === ' ') {
      event.preventDefault(); // Prevents scrolling when space is pressed
      event.stopPropagation(); // Prevents key firing on a parent component
      setSelectedComponent(component.uuid);
    }
  }

  const style: React.CSSProperties = useMemo(
    () => ({
      opacity: initialized ? '1' : '0',
      height: elementRect.height * editorViewPortScale,
      width: elementRect.width * editorViewPortScale,
      top: (offset.offsetTop || 0) * editorViewPortScale,
      left: (offset.offsetLeft || 0) * editorViewPortScale,
    }),
    [
      initialized,
      elementRect.height,
      elementRect.width,
      editorViewPortScale,
      offset,
    ],
  );

  let stackDirection: StackDirection = 'vertical';
  if (parentSlot && slotsMap) {
    stackDirection = slotsMap[parentSlot.id]?.stackDirection || 'vertical';
  }

  const [componentType] = component.type.split('@');

  return (
    <div
      aria-label={`${name}`}
      tabIndex={0}
      onMouseOver={handleItemMouseOver}
      onMouseOut={handleItemMouseOut}
      onClick={handleComponentClick}
      onKeyDown={handleKeyDown}
      data-canvas-selected={isSelected}
      className={clsx('componentOverlay', styles.componentOverlay, {
        [styles.selected]: isSelected,
        [styles.hovered]: isHovered,
        [styles.dragging]: isComponentDragged,
        [styles.updating]: isUpdating,
      })}
      style={style}
    >
      <button className="visually-hidden" onClick={handleComponentClick}>
        Select component
      </button>

      <ComponentContextMenu component={component}>
        <div
          aria-label={`Draggable component ${name}`}
          ref={setNodeRef}
          {...listeners}
          {...attributes}
          className={clsx('canvas--sortable-item', styles.sortableItem)}
          data-canvas-component-id={componentType}
          data-canvas-uuid={component.uuid}
          data-canvas-type={component.nodeType}
          data-canvas-overlay="true"
        />
      </ComponentContextMenu>
      {(isHovered || isSelected) && (
        <div className={clsx(styles.canvasNameTag)}>
          <ComponentNameTag
            name={name}
            id={component.uuid}
            nodeType={component.nodeType}
          />
        </div>
      )}
      {component.slots.map((slot: SlotNode) => (
        <SlotOverlay
          key={slot.name}
          iframeRef={iframeRef}
          parentComponent={component}
          slot={slot}
          disableDrop={disableDrop || isComponentDragged}
          forceRecalculate={forceRecalculateChildren}
        />
      ))}

      {!isComponentDragged && !disableDrop && !isUpdating && (
        <>
          {index === 0 && (
            <ComponentDropZone
              component={component}
              position={stackDirection.startsWith('v') ? 'top' : 'left'}
              parentSlot={parentSlot}
              parentRegion={parentRegion}
            />
          )}
          <ComponentDropZone
            component={component}
            position={stackDirection.startsWith('v') ? 'bottom' : 'right'}
            parentSlot={parentSlot}
            parentRegion={parentRegion}
          />
        </>
      )}
    </div>
  );
};

export default ComponentOverlay;
