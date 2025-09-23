import { useState } from 'react';
import clsx from 'clsx';
import _ from 'lodash';
import { DragOverlay, useDndMonitor } from '@dnd-kit/core';
import {
  restrictToFirstScrollableAncestor,
  restrictToWindowEdges,
} from '@dnd-kit/modifiers';

import { useAppDispatch, useAppSelector } from '@/app/hooks';
import {
  _addNewComponentToLayout,
  addNewPatternToLayout,
  moveNode,
  selectLayout,
} from '@/features/layout/layoutModelSlice';
import { findNodePathByUuid } from '@/features/layout/layoutUtils';
import {
  setListDragging,
  setPreviewDragging,
  setTargetSlot,
  setTreeDragging,
  setUpdatingComponent,
  unsetTargetSlot,
} from '@/features/ui/uiSlice';
import useComponentSelection from '@/hooks/useComponentSelection';

import {
  cleanupMouseTracking,
  initMouseTracking,
  snapRightToCursor,
} from './snapRightToCursor';

import type React from 'react';
import type {
  DragEndEvent,
  DragOverEvent,
  DragStartEvent,
} from '@dnd-kit/core';
import type { Pattern } from '@/types/Pattern';

import styles from './DragOverlay.module.css';

const DragEventsHandler: React.FC = () => {
  const layout = useAppSelector(selectLayout);
  const dispatch = useAppDispatch();
  const [componentName, setComponentName] = useState('...');
  const [dragOrigin, setDragOrigin] = useState('');
  const { setSelectedComponent } = useComponentSelection();

  const afterDrag = (
    elements: HTMLElement[] = [],
    successful?: boolean,
    componentUuid?: string,
  ) => {
    if (successful && componentUuid) {
      dispatch(setUpdatingComponent(componentUuid));
    }
  };

  // There is an edge case where if an item is dragged into the space immediately after itself,
  // it's from and to position is not exactly the same, but the result is still that it doesn't
  // actually move - because it moves down one space past itself.
  const isLastElementIncremented = (from: number[], to: number[]) => {
    if (from.length !== to.length) {
      return false;
    }
    const lastIndex = from.length - 1;
    return (
      from.slice(0, lastIndex).every((value, index) => value === to[index]) &&
      to[lastIndex] === from[lastIndex] + 1
    );
  };

  const getOrigin = (
    event: any,
  ): 'library' | 'overlay' | 'layers' | 'unknown' => {
    if (event.active?.data?.current?.origin) {
      return event.active.data.current.origin;
    } else {
      return 'unknown';
    }
  };

  const modifiers =
    dragOrigin === 'layers'
      ? [snapRightToCursor, restrictToFirstScrollableAncestor]
      : [snapRightToCursor, restrictToWindowEdges];

  function handleDragStart(event: DragStartEvent) {
    initMouseTracking();
    setComponentName(event.active.data?.current?.name);
    window.document.body.classList.add(styles.dragging);
    setDragOrigin(getOrigin(event));
    if (getOrigin(event) === 'overlay') {
      dispatch(setPreviewDragging(true));
    } else if (getOrigin(event) === 'library') {
      dispatch(setListDragging(true));
    } else if (getOrigin(event) === 'layers') {
      dispatch(setTreeDragging(true));
    }
  }

  function handleDragOver(event: DragOverEvent) {
    const parentSlot = event.over?.data?.current?.parentSlot;
    const parentRegion = event.over?.data?.current?.parentRegion;

    if (parentRegion) {
      dispatch(setTargetSlot(parentRegion.id));
    } else if (parentSlot) {
      dispatch(setTargetSlot(parentSlot.id));
    }
  }

  function handleDragEnd(event: DragEndEvent) {
    dispatch(setPreviewDragging(false));
    dispatch(setListDragging(false));
    dispatch(setTreeDragging(false));
    dispatch(unsetTargetSlot());
    window.document.body.classList.remove(styles.dragging);

    // Ensure the mouse tracking is cleaned up
    cleanupMouseTracking();

    const elementsInsideIframe =
      event.active.data?.current?.elementsInsideIframe || [];

    if (!event.over) {
      // If the dragged item wasn't dropped into a dropZone, do nothing.
      afterDrag(elementsInsideIframe, false);
      return;
    }

    if (
      getOrigin(event) === 'overlay' ||
      event.over.data.current?.destination === 'layers'
    ) {
      const activeComponent = event.active.data?.current?.component;
      const activeUuid = activeComponent.uuid;

      const dropPath = event.over.data?.current?.path;
      if (!dropPath) {
        // The component we are dropping onto was not found. I don't think this can happen, but if it does, do nothing.
        afterDrag(elementsInsideIframe, false);
        return;
      }
      const currentPath = findNodePathByUuid(layout, activeUuid);
      if (!currentPath) {
        throw new Error(`Unable to ascertain current path of dragged element.`);
      }

      if (
        _.isEqual(currentPath, dropPath) ||
        isLastElementIncremented(currentPath, dropPath)
      ) {
        // The dragged item was dropped back where it came from. Do nothing.
        afterDrag(elementsInsideIframe, false);
        return;
      }

      // if we got this far, then we have a valid location to move the dragged component to!
      // @todo We should optimistically move the elementsInsideIframe to the new location in the iFrames dom.
      // for now, we pass true here which will put the elementsInsideIframe into a 'pending move' state.
      afterDrag(elementsInsideIframe, true, activeUuid);

      dispatch(
        moveNode({
          uuid: activeUuid,
          to: dropPath,
        }),
      );
    } else if (getOrigin(event) === 'library') {
      const newItem = event.active.data?.current?.item;
      const dropPath = event.over.data?.current?.path;
      if (!dropPath) {
        // The component we are dropping onto was not found. I don't think this can happen, but if it does, do nothing.
        return;
      }
      const type = event.active.data?.current?.type;
      if (type === 'component' || type === 'dynamicComponent') {
        // @todo We should optimistically insert newItem.default_markup into to the new location in the iFrames dom.
        dispatch(
          _addNewComponentToLayout(
            {
              to: dropPath,
              component: newItem,
            },
            setSelectedComponent,
          ),
        );
      } else if (type === 'pattern') {
        dispatch(
          addNewPatternToLayout(
            {
              to: dropPath,
              layoutModel: (newItem as Pattern).layoutModel,
            },
            setSelectedComponent,
          ),
        );
      }
    }
  }

  function handleDragCancel() {
    dispatch(setPreviewDragging(false));
    dispatch(setListDragging(false));
    dispatch(unsetTargetSlot());
    window.document.body.classList.remove(styles.dragging);

    // Ensure the mouse tracking is cleaned up
    cleanupMouseTracking();
  }

  useDndMonitor({
    onDragStart: handleDragStart,
    onDragOver: handleDragOver,
    onDragEnd: handleDragEnd,
    onDragCancel: handleDragCancel,
  });

  return (
    <DragOverlay
      modifiers={modifiers}
      className={clsx(styles.dragOverlay)}
      dropAnimation={null}
    >
      <div>{componentName}</div>
    </DragOverlay>
  );
};

export default DragEventsHandler;
