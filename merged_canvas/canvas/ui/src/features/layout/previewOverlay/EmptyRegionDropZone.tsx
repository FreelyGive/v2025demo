import { useEffect, useState } from 'react';
import clsx from 'clsx';
import TemplateIcon from '@assets/icons/template.svg?react';
import { useDroppable } from '@dnd-kit/core';
import { FileTextIcon } from '@radix-ui/react-icons';
import { Text } from '@radix-ui/themes';

import { useAppSelector } from '@/app/hooks';
import { selectLayout } from '@/features/layout/layoutModelSlice';
import { selectEditorFrameContext } from '@/features/ui/uiSlice';
import { useTemplateCaption } from '@/hooks/useTemplateCaption';

import type React from 'react';
import type { RegionNode } from '@/features/layout/layoutModelSlice';

import styles from '@/features/layout/previewOverlay/PreviewOverlay.module.css';

export interface EmptyRegionDropZoneProps {
  region: RegionNode;
}
const EmptyRegionDropZone: React.FC<EmptyRegionDropZoneProps> = (props) => {
  const { region } = props;
  const layout = useAppSelector(selectLayout);
  const [activeName, setActiveName] = useState('');
  const isTemplateRoute =
    useAppSelector(selectEditorFrameContext) === 'template';

  const regionIndex = layout.findIndex((r) => r.id === region.id);
  const regionPath = [regionIndex, 0];

  const {
    setNodeRef: setDropRef,
    isOver,
    active,
  } = useDroppable({
    id: region.id,
    data: {
      region: region,
      parentRegion: region,
      path: regionPath,
    },
  });

  const templateCaption = useTemplateCaption();

  useEffect(() => {
    if (isOver && active) {
      setActiveName(active.data?.current?.name);
    } else {
      setActiveName('');
    }
  }, [active, isOver]);

  return (
    <div className={styles.emptyPageContainer}>
      <div
        className={clsx(styles.emptyPageDropZone, {
          [styles.isOver]: isOver,
        })}
        ref={setDropRef}
      >
        {activeName ? (
          activeName
        ) : (
          <>
            {isTemplateRoute ? <TemplateIcon /> : <FileTextIcon />}
            <Text weight={'medium'} mt="2" trim="start">
              {isTemplateRoute ? templateCaption || 'Template' : 'Page content'}
            </Text>
            <div className={styles.regionMessage}>Place items here</div>
          </>
        )}
      </div>
    </div>
  );
};

export default EmptyRegionDropZone;
