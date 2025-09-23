import { useEffect, useState } from 'react';
import clsx from 'clsx';
import { useDroppable } from '@dnd-kit/core';

import { useAppSelector } from '@/app/hooks';
import { selectLayout } from '@/features/layout/layoutModelSlice';
import { findNodePathByUuid } from '@/features/layout/layoutUtils';

import type React from 'react';
import type {
  ComponentNode,
  RegionNode,
  SlotNode,
} from '@/features/layout/layoutModelSlice';

import styles from '@/features/layout/previewOverlay/PreviewOverlay.module.css';

export interface ComponentDropZoneProps {
  component: ComponentNode;
  position: 'top' | 'bottom' | 'left' | 'right';
  parentSlot?: SlotNode;
  parentRegion?: RegionNode;
}
const ComponentDropZone: React.FC<ComponentDropZoneProps> = (props) => {
  const { component, position, parentSlot, parentRegion } = props;
  const layout = useAppSelector(selectLayout);
  const [draggedItem, setDraggedItem] = useState('');

  const dropPath = findNodePathByUuid(layout, component.uuid);
  if (!dropPath) {
    throw new Error(
      `Unable to ascertain 'path' to component ${component.uuid}`,
    );
  }
  if (dropPath) {
    if (position === 'bottom' || position === 'right') {
      dropPath[dropPath.length - 1] += 1;
    }
  }

  const {
    setNodeRef: setDropRef,
    isOver,
    active,
  } = useDroppable({
    id: `${component.uuid}_${position}`,
    disabled: draggedItem === `${component.uuid}`,
    data: {
      component: component,
      parentSlot: parentSlot,
      parentRegion: parentRegion,
      path: dropPath,
    },
  });

  useEffect(() => {
    // use the id of the dragged to disable it's dropzone so you can't drop it inside itself.
    setDraggedItem((active?.id as string) || '');
  }, [active]);

  const dropzoneStyle = styles[position];

  return (
    <div
      className={clsx(styles.componentDropZone, dropzoneStyle, {
        [styles.isOver]: isOver,
      })}
      ref={setDropRef}
    ></div>
  );
};

export default ComponentDropZone;
