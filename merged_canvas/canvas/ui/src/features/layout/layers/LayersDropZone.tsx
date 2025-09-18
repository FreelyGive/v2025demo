import React, { useEffect, useMemo, useState } from 'react';
import clsx from 'clsx';
import { useDroppable } from '@dnd-kit/core';

import { useAppSelector } from '@/app/hooks';
import { selectLayout } from '@/features/layout/layoutModelSlice';
import { findNodePathByUuid } from '@/features/layout/layoutUtils';

import type {
  ComponentNode,
  RegionNode,
  SlotNode,
} from '@/features/layout/layoutModelSlice';

import styles from '@/features/layout/layers/LayersDropZone.module.css';

export interface LayersDropZoneProps {
  layer: ComponentNode | SlotNode;
  position: 'top' | 'bottom' | 'left' | 'right';
  parentSlot?: SlotNode;
  parentRegion?: RegionNode;
  indent: number;
}

const LayersDropZone: React.FC<LayersDropZoneProps> = (props) => {
  const { layer, position, indent, parentSlot, parentRegion } = props;
  const layout = useAppSelector(selectLayout);
  const [draggedItem, setDraggedItem] = useState('');
  const type = 'uuid' in layer ? 'component' : 'slot';
  const id = 'uuid' in layer ? layer.uuid : layer.id;

  // Memoize the path calculation to prevent recalculating on every render
  const dropPath = useMemo(() => {
    const path = findNodePathByUuid(layout, id);
    if (!path) {
      return null;
    }
    // Create a copy to avoid modifying the original
    const finalPath = [...path];

    if (type === 'slot') {
      finalPath.push(0);
    } else if (position === 'bottom' || position === 'right') {
      finalPath[finalPath.length - 1] += 1;
    }

    return finalPath;
  }, [layout, id, type, position]);

  if (!dropPath) {
    throw new Error(`Unable to ascertain 'path' to component ${id}`);
  }

  const {
    setNodeRef: setDropRef,
    isOver,
    active,
  } = useDroppable({
    id: `${id}_${position}_layers`,
    disabled: draggedItem === `${id}`,
    data: {
      component: layer,
      parentSlot: parentSlot,
      parentRegion: parentRegion,
      path: dropPath,
      destination: 'layers',
    },
  });

  useEffect(() => {
    // use the id of the dragged to disable it's dropzone so you can't drop it inside itself.
    setDraggedItem((active?.id as string) || '');
  }, [active]);

  const dropzoneStyle = styles[position];

  return (
    <div
      className={clsx(styles.layersDropZone, dropzoneStyle, {
        [styles.isOver]: isOver,
      })}
      // @ts-ignore
      style={{ '--indent-depth': `${indent}` }}
      ref={setDropRef}
    ></div>
  );
};

// Wrap the component with React.memo to prevent unnecessary re-renders
export default React.memo(LayersDropZone);
