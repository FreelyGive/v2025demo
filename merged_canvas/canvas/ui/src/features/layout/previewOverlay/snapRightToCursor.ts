import { getEventCoordinates } from '@dnd-kit/utilities';

import type { Modifier } from '@dnd-kit/core';

// Store the most recent mouse/touch position while dragging
let currentMousePosition: { x: number; y: number } | null = null;
let haveOffsetPosition: boolean = false;
let trackMouseFunction: ((e: MouseEvent | TouchEvent) => void) | null = null;
let mouseDiff = {
  x: 0,
  y: 0,
};

/**
 * Clean up all event listeners and reset state
 */
export function cleanupMouseTracking() {
  haveOffsetPosition = false;
  currentMousePosition = null;
  mouseDiff.x = 0;
  mouseDiff.y = 0;

  // Remove event listeners
  if (trackMouseFunction) {
    document.removeEventListener('mousemove', trackMouseFunction);
    document.removeEventListener('touchmove', trackMouseFunction);
    trackMouseFunction = null;
  }
}

/**
 * Set up tracking of the current mouse position
 */
export function initMouseTracking() {
  // First clean up any existing tracking
  cleanupMouseTracking();
  haveOffsetPosition = false;
  mouseDiff.x = 0;
  mouseDiff.y = 0;

  // Set up new tracking
  trackMouseFunction = (e: MouseEvent | TouchEvent) => {
    const coords =
      'touches' in e && e.touches.length > 0
        ? { x: e.touches[0].clientX, y: e.touches[0].clientY }
        : 'clientX' in e
          ? { x: e.clientX, y: e.clientY }
          : null;

    if (coords) {
      currentMousePosition = coords;
    }
  };

  document.addEventListener('mousemove', trackMouseFunction);
  document.addEventListener('touchmove', trackMouseFunction);
}

/**
 * A custom DnD modifier that positions the dragged element at the bottom right of the cursor.
 *
 * This positions the draggable element so that it appears below and to the right of the cursor
 * instead of centered on it.
 *
 * @type {Modifier}
 */
export const snapRightToCursor: Modifier = ({
  activatorEvent,
  draggingNodeRect,
  transform,
}) => {
  if (draggingNodeRect && activatorEvent) {
    const activatorCoordinates = getEventCoordinates(activatorEvent);

    if (!activatorCoordinates) {
      return transform;
    }

    // The coordinates we get from the activatorEvent are the position of the mouse when mouseDown fired.
    // because we have a distance based activationConstraint (https://docs.dndkit.com/api-documentation/sensors/pointer#activation-constraints)
    // the mouse cursor can have moved some distance by the time we get here. We use a mouse/touchmove event listener
    // on dragStart to keep track of the mouse position so we can take this offset into account.
    if (!haveOffsetPosition) {
      if (currentMousePosition) {
        // Only calculate diff if we have a valid current position
        mouseDiff.x = activatorCoordinates.x - currentMousePosition.x;
        mouseDiff.y = activatorCoordinates.y - currentMousePosition.y;
      }
      haveOffsetPosition = true;
    }

    // Offset from cursor to element corner (positive = right/down)
    const cursorOffsetX = 20;
    const cursorOffsetY = 0;

    // Calculate the position where the top-left corner of the element should be
    // This will be the cursor position + our offset + the mouseDiff between dragStart and the drag activation.
    const desiredLeft = activatorCoordinates.x + cursorOffsetX - mouseDiff.x;
    const desiredTop = activatorCoordinates.y + cursorOffsetY - mouseDiff.y;

    return {
      ...transform,
      x: transform.x + desiredLeft - draggingNodeRect.left,
      y: transform.y + desiredTop - draggingNodeRect.top,
    };
  }

  return transform;
};
