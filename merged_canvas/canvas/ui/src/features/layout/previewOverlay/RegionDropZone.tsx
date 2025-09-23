import clsx from 'clsx';
import { useDroppable } from '@dnd-kit/core';

import { useAppSelector } from '@/app/hooks';
import { selectLayout } from '@/features/layout/layoutModelSlice';

import type React from 'react';
import type { RegionNode } from '@/features/layout/layoutModelSlice';

import styles from '@/features/layout/previewOverlay/PreviewOverlay.module.css';

export interface RegionDropZoneProps {
  region: RegionNode;
  position: 'before' | 'after';
}
const RegionDropZone: React.FC<RegionDropZoneProps> = (props) => {
  const { region, position } = props;
  const layout = useAppSelector(selectLayout);

  const regionIndex = layout.findIndex((r) => r.id === region.id);
  const regionPath = [regionIndex];

  if (position === 'after') {
    regionPath.push(layout[regionIndex].components.length);
  } else {
    regionPath.push(0);
  }

  const { setNodeRef: setDropRef, isOver } = useDroppable({
    id: `${region.id}_${position}`,
    data: {
      region: region,
      parentRegion: region,
      path: regionPath,
    },
  });
  const dropzoneStyle = styles[position];

  return (
    <div
      className={clsx(styles.regionDropZone, dropzoneStyle, {
        [styles.isOver]: isOver,
      })}
      ref={setDropRef}
    ></div>
  );
};

export default RegionDropZone;
