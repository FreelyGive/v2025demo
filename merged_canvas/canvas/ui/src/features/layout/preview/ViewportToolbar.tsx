import { useLayoutEffect } from 'react';
import clsx from 'clsx';
import ScaleToFitIcon from '@assets/icons/justify-stretch.svg?react';
import { Button, DropdownMenu, Flex, Tooltip } from '@radix-ui/themes';

import { useAppDispatch, useAppSelector } from '@/app/hooks';
import BreakpointIcon from '@/components/BreakpointIcon';
import ZoomControl from '@/components/zoom/ZoomControl';
import {
  scaleValues,
  selectViewportWidth,
  setEditorFrameViewPort,
  setViewportMinHeight,
  setViewportWidth,
} from '@/features/ui/uiSlice';
import { viewportSizes } from '@/types/Preview';
import { getHalfwayScrollPosition } from '@/utils/function-utils';

import type React from 'react';
import type { RefObject } from 'react';
import type { ScaleValue } from '@/features/ui/uiSlice';
import type { viewportSize } from '@/types/Preview';

import styles from './ViewportToolbar.module.css';

interface ViewportToolbarProps {
  editorPaneRef: RefObject<HTMLElement>;
  scalingContainerRef: RefObject<HTMLElement>;
}

const findClosestScaleValue = (desiredScale: number): ScaleValue => {
  // Filter the list to find all scales less than or equal to desiredScale. Remove an extra 0.01 from the scale to bias
  // towards dropping down a scale in case of an almost exact fit in the viewport (it looks nicer to have a bit of gap).
  const filteredScales = scaleValues.filter(
    (value) => value.scale <= desiredScale - 0.01,
  );

  // If there's any valid scale in the filtered list, return largest one.
  if (filteredScales.length > 0) {
    return filteredScales.reduce((prev, curr) =>
      curr.scale > prev.scale ? curr : prev,
    );
  }

  // If no scales are less than or equal to desiredScale, return the smallest available scale.
  return scaleValues[0];
};

const ViewportToolbar: React.FC<ViewportToolbarProps> = (props) => {
  const { editorPaneRef, scalingContainerRef } = props;
  const dispatch = useAppDispatch();
  const currentWidth = useAppSelector(selectViewportWidth);
  const handleWidthClick = (viewportSize: viewportSize) => {
    dispatch(setViewportWidth(viewportSize.width));
    dispatch(setViewportMinHeight(viewportSize.height));
  };

  const getViewportByWidth = (width: number) => {
    return viewportSizes.find((vw) => vw.width === width);
  };

  const getViewportByName = (name: string) => {
    return viewportSizes.find((vw) => vw.name === name);
  };

  const handleScaleToFit = () => {
    if (editorPaneRef.current) {
      const editorFrameContainerWidth =
        editorPaneRef.current.getBoundingClientRect().width;
      const scaleToFit = editorFrameContainerWidth / currentWidth;
      const closestScale = findClosestScaleValue(scaleToFit);
      dispatch(
        setEditorFrameViewPort({
          scale: closestScale.scale < 1 ? closestScale.scale : 1,
        }),
      );

      requestAnimationFrame(() => {
        if (editorPaneRef.current && scalingContainerRef.current) {
          // Calculate the height of the preview (getBoundingClientRect takes into account scaling).
          const previewHeight =
            scalingContainerRef.current.getBoundingClientRect().height;

          // Calculate the center offset inside the editor frame.
          const editorFrameHeight = editorPaneRef.current.scrollHeight;
          const centerOffset = (editorFrameHeight - previewHeight) / 2;

          const y = centerOffset - 50;
          dispatch(
            setEditorFrameViewPort({
              x: getHalfwayScrollPosition(editorPaneRef.current),
              y,
            }),
          );
        }
      });
    }
  };

  useLayoutEffect(() => {
    const defaultVs = getViewportByName('Tablet') as viewportSize;
    dispatch(setViewportWidth(defaultVs.width));
    dispatch(setViewportMinHeight(defaultVs.height));
  }, [dispatch]);

  return (
    <Flex
      className={styles.toolbar}
      gap="2"
      data-testid="canvas-editor-frame-controls"
    >
      <DropdownMenu.Root>
        <DropdownMenu.Trigger>
          <Button
            variant="surface"
            size="1"
            color="gray"
            className={clsx(styles.toolbarButton, styles.viewportSelect)}
          >
            <BreakpointIcon width={currentWidth} />
            {getViewportByWidth(currentWidth)?.name}
            <DropdownMenu.TriggerIcon />
          </Button>
        </DropdownMenu.Trigger>
        <DropdownMenu.Content size="1">
          {viewportSizes.map((vs) => (
            <DropdownMenu.Item
              key={vs.name}
              onClick={() => handleWidthClick(vs)}
              color={vs.width === currentWidth ? 'blue' : undefined}
            >
              <BreakpointIcon width={vs.width} />
              {vs.name} ({vs.width}px)
            </DropdownMenu.Item>
          ))}
        </DropdownMenu.Content>
      </DropdownMenu.Root>
      <Tooltip side="bottom" content={'Scale to fit'}>
        <Button
          size="1"
          onClick={handleScaleToFit}
          color="gray"
          variant="surface"
          highContrast
          className={styles.toolbarButton}
          data-testid="scale-to-fit"
        >
          <ScaleToFitIcon />
        </Button>
      </Tooltip>
      <ZoomControl buttonClass={styles.toolbarButton} />
    </Flex>
  );
};

export default ViewportToolbar;
