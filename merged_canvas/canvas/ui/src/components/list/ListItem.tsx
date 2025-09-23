import { useState } from 'react';
import clsx from 'clsx';
import { useParams } from 'react-router';
import { useDraggable } from '@dnd-kit/core';
import * as Tooltip from '@radix-ui/react-tooltip';
import { Theme } from '@radix-ui/themes';

import { useAppDispatch, useAppSelector } from '@/app/hooks';
import ComponentPreview from '@/components/ComponentPreview';
import ExposedJsComponent from '@/components/list/ExposedJsComponent';
import PatternNode from '@/components/list/PatternNode';
import { useDisplayContext } from '@/components/sidePanel/DisplayContext';
import SidebarNode from '@/components/sidePanel/SidebarNode';
import {
  _addNewComponentToLayout,
  addNewPatternToLayout,
  selectLayout,
} from '@/features/layout/layoutModelSlice';
import { findNodePathByUuid } from '@/features/layout/layoutUtils';
import { DEFAULT_REGION } from '@/features/ui/uiSlice';
import useComponentSelection from '@/hooks/useComponentSelection';

import type React from 'react';
import type { LayoutItemType } from '@/features/ui/primaryPanelSlice';
import type { CanvasComponent, JSComponent } from '@/types/Component';
import type { Pattern } from '@/types/Pattern';

import styles from '@/components/list/List.module.css';

const ListItem: React.FC<{
  item: CanvasComponent | Pattern;
  type:
    | LayoutItemType.COMPONENT
    | LayoutItemType.PATTERN
    | LayoutItemType.DYNAMIC;
}> = (props) => {
  const { item, type } = props;
  const dispatch = useAppDispatch();
  const [isMenuOpen, setIsMenuOpen] = useState(false);
  const layout = useAppSelector(selectLayout);
  const [previewingComponent, setPreviewingComponent] = useState<
    CanvasComponent | Pattern
  >();
  const {
    componentId: selectedComponent,
    regionId: focusedRegion = DEFAULT_REGION,
  } = useParams();
  const { setSelectedComponent } = useComponentSelection();
  const { attributes, listeners, setNodeRef, isDragging } = useDraggable({
    id: item.id,
    data: {
      origin: 'library',
      type,
      item: item,
      name: item.name,
    },
  });
  const displayContext = useDisplayContext();

  const makeDraggable = () => displayContext !== 'manage-library';
  const includeDropdown = () => displayContext !== 'manage-library';

  const clickToInsertHandler = (newId: string) => {
    let path: number[] | null = [0];
    if (selectedComponent) {
      path = findNodePathByUuid(layout, selectedComponent);
    } else if (focusedRegion) {
      path = [layout.findIndex((region) => region.id === focusedRegion), 0];
    }
    if (path) {
      const newPath = [...path];
      newPath[newPath.length - 1] += 1;

      if (type === 'component' || type === 'dynamicComponent') {
        dispatch(
          _addNewComponentToLayout(
            {
              to: newPath,
              component: item as CanvasComponent,
            },
            setSelectedComponent,
          ),
        );
      } else if (type === 'pattern') {
        dispatch(
          addNewPatternToLayout(
            {
              to: newPath,
              layoutModel: (item as Pattern).layoutModel,
            },
            setSelectedComponent,
          ),
        );
      }
    }
  };

  const handleMouseEnter = (component: CanvasComponent | Pattern) => {
    if (!isMenuOpen) {
      setPreviewingComponent(component);
    }
  };

  const renderItem = () => {
    if (type === 'pattern') {
      return (
        <PatternNode
          pattern={item as Pattern}
          onMenuOpenChange={setIsMenuOpen}
          disabled={isDragging}
        />
      );
    }
    if (
      type === 'component' &&
      (item as JSComponent).source === 'Code component'
    ) {
      return (
        <ExposedJsComponent
          component={item as JSComponent}
          onMenuOpenChange={setIsMenuOpen}
          disabled={isDragging}
        />
      );
    }
    return (
      <SidebarNode
        title={item.name}
        disabled={isDragging}
        variant={
          type === 'component' && (item as CanvasComponent).source === 'Blocks'
            ? 'dynamicComponent'
            : type
        }
        includeDropdown={includeDropdown()}
        draggable={makeDraggable()}
      />
    );
  };

  let wrapperProps: React.HTMLAttributes<HTMLDivElement> &
    React.RefAttributes<HTMLDivElement> & {
      'data-canvas-component-id': string;
      'data-canvas-name': string;
      'data-canvas-type':
        | LayoutItemType.PATTERN
        | LayoutItemType.COMPONENT
        | LayoutItemType.DYNAMIC;
    } = {
    role: 'listitem',
    'data-canvas-component-id': item.id,
    'data-canvas-name': item.name,
    'data-canvas-type': type,
    className: clsx(styles.listItem),
  };

  if (makeDraggable()) {
    wrapperProps = {
      ...wrapperProps,
      ...attributes,
      ...listeners,
      ref: setNodeRef,
      onClick: () => clickToInsertHandler(item.id),
      onMouseEnter: () => handleMouseEnter(item),
    };
  }

  return (
    <div key={item.id} {...wrapperProps}>
      <Tooltip.Provider>
        <Tooltip.Root delayDuration={0}>
          <Tooltip.Trigger asChild={true} style={{ width: '100%' }}>
            <div>{renderItem()}</div>
          </Tooltip.Trigger>
          <Tooltip.Portal>
            <Tooltip.Content
              side="right"
              sideOffset={24}
              align="start"
              className={styles.componentPreviewTooltipContent}
              onClick={(e) => e.stopPropagation()}
              style={{ pointerEvents: 'none' }}
            >
              <Theme>
                {previewingComponent && !isMenuOpen && (
                  <ComponentPreview componentListItem={previewingComponent} />
                )}
              </Theme>
            </Tooltip.Content>
          </Tooltip.Portal>
        </Tooltip.Root>
      </Tooltip.Provider>
    </div>
  );
};

export default ListItem;
