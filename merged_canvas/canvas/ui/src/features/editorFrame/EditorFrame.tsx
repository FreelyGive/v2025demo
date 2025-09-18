import {
  useCallback,
  useEffect,
  useLayoutEffect,
  useRef,
  useState,
} from 'react';
import clsx from 'clsx';
import { useHotkeys } from 'react-hotkeys-hook';
import { useParams } from 'react-router';
import { useDebouncedCallback } from 'use-debounce';

import { useAppDispatch, useAppSelector } from '@/app/hooks';
import ErrorBoundary from '@/components/error/ErrorBoundary';
import Preview from '@/features/layout/preview/Preview';
import ViewportToolbar from '@/features/layout/preview/ViewportToolbar';
import PreviewOverlay from '@/features/layout/previewOverlay/PreviewOverlay';
import {
  editorViewPortZoomIn,
  editorViewPortZoomOut,
  selectDragging,
  selectEditorViewPort,
  selectFirstLoadComplete,
  selectPanning,
  setEditorFrameModeEditing,
  setEditorFrameModeInteractive,
  setEditorFrameViewPort,
  setIsPanning,
  setIsZooming,
} from '@/features/ui/uiSlice';
import useComponentSelection from '@/hooks/useComponentSelection';
import useCopyPasteComponents from '@/hooks/useCopyPasteComponents';
import useResizeObserver from '@/hooks/useResizeObserver';
import { useUndoRedo } from '@/hooks/useUndoRedo';
import { getHalfwayScrollPosition } from '@/utils/function-utils';

import { deleteNode } from '../layout/layoutModelSlice';

import type React from 'react';

import styles from './EditorFrame.module.css';

const EditorFrame = () => {
  const dispatch = useAppDispatch();
  const editorFrameRef = useRef<HTMLDivElement | null>(null);
  const editorPaneRef = useRef<HTMLDivElement | null>(null);
  const animFrameScrollRef = useRef<number | null>(null);
  const animFrameScaleRef = useRef<number | null>(null);
  const scalingContainerRef = useRef<HTMLDivElement | null>(null);
  const [startPos, setStartPos] = useState({ x: 0, y: 0 });
  const editorViewPort = useAppSelector(selectEditorViewPort);
  const firstLoadComplete = useAppSelector(selectFirstLoadComplete);
  const [isVisible, setIsVisible] = useState(false);
  const [middleMouseDown, setMiddleMouseDown] = useState(false);
  const isPanning = useAppSelector(selectPanning);
  const [modifierKeyPressed, setModifierKeyPressed] = useState(false);
  const modifierKeyPressedRef = useRef(false);
  const { componentId: selectedComponent } = useParams();
  const { unsetSelectedComponent } = useComponentSelection();
  const middleMouseDownRef = useRef(middleMouseDown);
  const { copySelectedComponent, pasteAfterSelectedComponent } =
    useCopyPasteComponents();
  const { isUndoable, dispatchUndo } = useUndoRedo();
  const { isDragging } = useAppSelector(selectDragging);

  useHotkeys(['NumpadAdd', 'Equal'], () => dispatch(editorViewPortZoomIn()));
  useHotkeys(['Minus', 'NumpadSubtract'], () =>
    dispatch(editorViewPortZoomOut()),
  );
  useHotkeys('ctrl', () => setModifierKeyPressed(true), {
    keydown: true,
    keyup: false,
  });
  useHotkeys('ctrl', () => setModifierKeyPressed(false), {
    keydown: false,
    keyup: true,
  });

  // TODO This should have a better keyboard shortcut, but as the Interactive mode is still
  // in development/buggy, leaving it as something obscure for now.
  useHotkeys('v', () => dispatch(setEditorFrameModeInteractive()), {
    keydown: true,
    keyup: false,
  });
  useHotkeys('v', () => dispatch(setEditorFrameModeEditing()), {
    keydown: false,
    keyup: true,
  });
  useHotkeys(['Backspace', 'Delete'], () => {
    if (selectedComponent) {
      dispatch(deleteNode(selectedComponent));
      unsetSelectedComponent();
    }
  });
  useHotkeys('mod+c', () => {
    copySelectedComponent();
  });
  useHotkeys('mod+v', () => {
    pasteAfterSelectedComponent();
  });

  // Update the width/height of the editorFrame to accommodate the scaled viewport (scalingContainerRef).
  const updateEditorFrameSize = useCallback(() => {
    if (!scalingContainerRef.current || !editorFrameRef.current) {
      return;
    }

    // because the editorFrame is scaled with CSS transform, we need to get/set the width/height of the parent manually
    const rect = scalingContainerRef.current?.getBoundingClientRect();

    editorFrameRef.current.style.width = rect.width ? `${rect.width}px` : '';
    editorFrameRef.current.style.height = rect.height ? `${rect.height}px` : '';
  }, []);

  useResizeObserver(scalingContainerRef, updateEditorFrameSize);

  const debouncedScrollPosUpdate = useDebouncedCallback(() => {
    if (!isDragging) {
      dispatch(
        setEditorFrameViewPort({
          x: editorPaneRef.current?.scrollLeft,
          y: editorPaneRef.current?.scrollTop,
        }),
      );
    }
  }, 250);

  const debouncedIsPanningUpdate = useDebouncedCallback(() => {
    dispatch(setIsPanning(false));
  }, 250);

  const debouncedIsZoomingUpdate = useDebouncedCallback(() => {
    dispatch(setIsZooming(false));
  }, 250);

  useEffect(() => {
    middleMouseDownRef.current = middleMouseDown;
  }, [middleMouseDown]);

  useEffect(() => {
    modifierKeyPressedRef.current = modifierKeyPressed;
  }, [modifierKeyPressed]);

  useEffect(() => {
    if (!firstLoadComplete) {
      return;
    }
    if (scalingContainerRef.current && editorPaneRef.current) {
      // hardcoded value of 68 to account for the height of the UI (top bar 48px) + 20px gap.
      const previewHeight =
        scalingContainerRef.current.getBoundingClientRect().height;

      // Calculate the center offset inside the editorFrame.
      const editorFrameHeight = editorPaneRef.current.scrollHeight;
      const centerOffset = (editorFrameHeight - previewHeight) / 2;

      const y = centerOffset - 50;
      const x = getHalfwayScrollPosition(editorPaneRef.current);

      // Scroll the preview to the middle top.
      dispatch(setEditorFrameViewPort({ x: x, y: y }));
      setIsVisible(true);
    }
  }, [dispatch, firstLoadComplete]);

  useEffect(() => {
    // We can't update the scroll position while dragging is happening because DNDKit seems to cancel the drag operation
    // as soon as we do. So, when isDragging becomes false, we update the scroll position to make sure it's updated after.
    if (!isDragging) {
      debouncedScrollPosUpdate();
    }
  }, [debouncedScrollPosUpdate, isDragging]);

  const handlePaneScroll = useCallback(
    (event: React.UIEvent<HTMLDivElement>) => {
      if (event.currentTarget) {
        dispatch(setIsPanning(true));
        debouncedScrollPosUpdate();
        debouncedIsPanningUpdate();
      }
    },
    [debouncedIsPanningUpdate, debouncedScrollPosUpdate, dispatch],
  );

  const handleMouseDown = (e: React.MouseEvent<HTMLDivElement>) => {
    // Reset modifierKeyPressed to false if left button is clicked along with ctrl key press
    if (e.ctrlKey && e.button === 0) {
      setModifierKeyPressed(false);
      e.preventDefault();
      return;
    }
    if (e.button === 1) {
      const { clientX, clientY } = e;
      setMiddleMouseDown(true);
      dispatch(setIsPanning(true));
      if (editorPaneRef.current) {
        setStartPos({
          x: clientX + editorPaneRef.current.scrollLeft,
          y: clientY + editorPaneRef.current.scrollTop,
        });
      }
      e.preventDefault();
    }
  };

  const handleMouseMove = (e: React.MouseEvent<HTMLDivElement>) => {
    if (middleMouseDownRef.current) {
      const { clientX, clientY } = e;
      const translationX = startPos.x - clientX;
      const translationY = startPos.y - clientY;

      if (animFrameScrollRef.current) {
        cancelAnimationFrame(animFrameScrollRef.current);
      }

      animFrameScrollRef.current = requestAnimationFrame(() => {
        if (scalingContainerRef.current) {
          scalingContainerRef.current.style.transform = `scale(${editorViewPort.scale})`;
        }
        if (editorPaneRef.current) {
          editorPaneRef.current.scrollLeft = translationX;
          editorPaneRef.current.scrollTop = translationY;
        }
        debouncedScrollPosUpdate();
      });
    }
  };

  const handleMouseUp = useCallback(() => {
    setMiddleMouseDown(false);
    debouncedIsPanningUpdate();
  }, [debouncedIsPanningUpdate]);

  // Track the last time we processed a wheel event.
  const lastWheelEventTimeRef = useRef<number>(0);
  const wheelEventBufferTimeMs = 50; // Only process wheel events every 50ms.

  const handleWheel = useCallback(
    (e: WheelEvent) => {
      if (e.ctrlKey || e.metaKey) {
        e.preventDefault();

        // Throttle wheel events to avoid too rapid scaling.
        const now = Date.now();
        if (now - lastWheelEventTimeRef.current > wheelEventBufferTimeMs) {
          dispatch(setIsZooming(true));
          lastWheelEventTimeRef.current = now;
          // Only care about the direction, not the magnitude.
          if (e.deltaY > 0) {
            dispatch(editorViewPortZoomOut());
          } else {
            dispatch(editorViewPortZoomIn());
          }

          debouncedIsZoomingUpdate();
        }
      }
    },
    [debouncedIsZoomingUpdate, dispatch],
  );

  useEffect(() => {
    if (animFrameScrollRef.current) {
      cancelAnimationFrame(animFrameScrollRef.current);
    }

    animFrameScrollRef.current = requestAnimationFrame(() => {
      if (editorPaneRef.current) {
        editorPaneRef.current.scrollLeft = editorViewPort.x;
        editorPaneRef.current.scrollTop = editorViewPort.y;
      }
    });
  }, [editorViewPort.x, editorViewPort.y]);

  useEffect(() => {
    if (animFrameScaleRef.current) {
      cancelAnimationFrame(animFrameScaleRef.current);
    }

    animFrameScaleRef.current = requestAnimationFrame(() => {
      if (scalingContainerRef.current) {
        scalingContainerRef.current.style.transform = `scale(${editorViewPort.scale})`;
      }
    });
  }, [editorViewPort.scale]);

  useEffect(() => {
    window.addEventListener('mouseup', handleMouseUp);
    window.addEventListener('wheel', handleWheel, { passive: false });

    return () => {
      window.removeEventListener('mouseup', handleMouseUp);
      window.removeEventListener('wheel', handleWheel);
    };
  }, [handleWheel, handleMouseUp]);

  // Update the editorFrame size when the scale changes or on initial render.
  useLayoutEffect(() => {
    updateEditorFrameSize();
  }, [editorViewPort.scale, updateEditorFrameSize]);

  return (
    <div className={styles.editorFrameContainer}>
      <div
        className={clsx(styles.editorPane, {
          [styles.modifierKeyPressed]: modifierKeyPressed,
          [styles.isPanning]: isPanning,
        })}
        onMouseDown={handleMouseDown}
        onMouseMove={handleMouseMove}
        onScroll={handlePaneScroll}
        onMouseUp={handleMouseUp}
        onMouseLeave={handleMouseUp}
        ref={editorPaneRef}
      >
        <div
          className={clsx(styles.editorFrame, {
            [styles.visible]: isVisible,
          })}
          // @ts-ignore
          style={{ '--editor-frame-scale': editorViewPort.scale }}
          ref={editorFrameRef}
          data-testid="canvas-editor-frame"
        >
          <div style={{ position: 'relative' }} id="positionAnchor">
            <div
              className={clsx(
                'canvasEditorFrameScalingContainer',
                styles.canvasEditorFrameScalingContainer,
              )}
              data-testid="canvas-editor-frame-scaling"
              style={{
                transform: `scale(${editorViewPort.scale})`,
              }}
              ref={scalingContainerRef}
            >
              <ErrorBoundary
                title="An unexpected error has occurred while rendering preview."
                variant="alert"
                onReset={isUndoable ? dispatchUndo : undefined}
                resetButtonText={isUndoable ? 'Undo last action' : undefined}
              >
                <Preview />
              </ErrorBoundary>
            </div>

            <PreviewOverlay />
          </div>
        </div>
      </div>
      <ViewportToolbar
        editorPaneRef={editorPaneRef}
        scalingContainerRef={scalingContainerRef}
      />
    </div>
  );
};

export default EditorFrame;
