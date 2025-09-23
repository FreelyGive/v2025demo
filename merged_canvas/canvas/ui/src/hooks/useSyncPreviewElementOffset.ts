import { useCallback, useLayoutEffect, useMemo, useRef, useState } from 'react';

import {
  elemIsVisible,
  getDistanceBetweenElements,
} from '@/utils/function-utils';

/**
 * This hook takes a target element(s) and a parent element(s) and returns the offset (left, top) between them.
 * It uses a mutation observer and resize observer to ensure that even if the element changes size or position at any point
 * the returned values are updated. Closely follows the pattern of useSyncPreviewElementSize.
 */

function isElementObservable(element: HTMLElement) {
  const style = window.getComputedStyle(element);
  if (style.display === 'contents') {
    return false;
  }
  return elemIsVisible(element);
}

function findParentBody(element: HTMLElement) {
  let currentElement = element;
  while (currentElement) {
    if (currentElement.nodeName.toLowerCase() === 'body') {
      return currentElement;
    }
    const parent = currentElement.parentElement;
    if (!parent) {
      break;
    }
    currentElement = parent;
  }
  return null;
}

function useSyncPreviewElementOffset(
  elements: HTMLElement[] | HTMLElement | null,
  parentElements: HTMLElement[] | HTMLElement | null,
) {
  // Normalize to arrays
  const targetElements = useMemo(() => {
    if (!elements) return null;
    return Array.isArray(elements) ? elements : [elements];
  }, [elements]);
  const parentEls = useMemo(() => {
    if (!parentElements) return null;
    return Array.isArray(parentElements) ? parentElements : [parentElements];
  }, [parentElements]);

  const [offset, setOffset] = useState({ offsetLeft: 0, offsetTop: 0 });
  const resizeObserverRef = useRef<ResizeObserver | null>(null);
  const mutationObserverRef = useRef<MutationObserver | null>(null);
  const elementsRef = useRef<HTMLElement[] | null>(null);
  const parentElementsRef = useRef<HTMLElement[] | null>(null);
  const removeAnimationEndListenerRef = useRef<(() => void) | null>(null);

  const recalculateOffset = useCallback(() => {
    const els = elementsRef.current;
    const parentEls = parentElementsRef.current;
    if (!els || !parentEls) return;

    requestAnimationFrame(() => {
      const { horizontalDistance, verticalDistance } =
        getDistanceBetweenElements(parentEls, els);
      setOffset((prev) => {
        if (
          prev.offsetLeft !== horizontalDistance ||
          prev.offsetTop !== verticalDistance
        ) {
          return {
            offsetLeft: horizontalDistance,
            offsetTop: verticalDistance,
          };
        }
        return prev;
      });
    });
  }, []);

  const forceRecalculateOffset = useCallback(() => {
    const els = elementsRef.current;
    const parentEls = parentElementsRef.current;
    if (!els || !parentEls) return;
    const { horizontalDistance, verticalDistance } = getDistanceBetweenElements(
      parentEls,
      els,
    );
    requestAnimationFrame(() => {
      setOffset({
        offsetLeft: horizontalDistance,
        offsetTop: verticalDistance,
      });
    });
  }, []);

  // Recalculate offset when the target elements or parent elements change
  useLayoutEffect(() => {
    elementsRef.current = targetElements;
    parentElementsRef.current = parentEls;
    recalculateOffset();
  }, [targetElements, parentEls, recalculateOffset]);

  const init = useCallback(() => {
    resizeObserverRef.current?.disconnect();
    mutationObserverRef.current?.disconnect();

    resizeObserverRef.current = new ResizeObserver(() => {
      recalculateOffset();
    });
    mutationObserverRef.current = new MutationObserver((mutationsList) => {
      mutationsList.forEach((mutation) => {
        recalculateOffset();
        if (
          mutation.type === 'attributes' &&
          mutation.attributeName === 'style'
        ) {
          const target = mutation.target;
          target.addEventListener('transitionend', recalculateOffset, {
            once: true,
          });
        }
      });
    });

    // Observe both target and parent elements
    [
      ...(elementsRef.current || []),
      ...(parentElementsRef.current || []),
    ].forEach((element) => {
      if (isElementObservable(element)) {
        resizeObserverRef.current?.observe(element);
      } else {
        let parent = element.parentElement;
        while (parent && !isElementObservable(parent)) {
          parent = parent.parentElement;
        }
        if (parent) {
          resizeObserverRef.current?.observe(parent);
        }
      }
      const parentBody = findParentBody(element);
      if (parentBody) {
        mutationObserverRef.current?.observe(parentBody, {
          attributes: true,
          childList: true,
          subtree: true,
        });
      }
      element.removeEventListener('animationend', forceRecalculateOffset);
      element.addEventListener('animationend', forceRecalculateOffset);
    });
    removeAnimationEndListenerRef.current = () => {
      [
        ...(elementsRef.current || []),
        ...(parentElementsRef.current || []),
      ].forEach((element) => {
        element.removeEventListener('animationend', forceRecalculateOffset);
      });
    };
  }, [forceRecalculateOffset, recalculateOffset]);

  // Initialize observers when the hook is first run, targetElements change or parentEls change
  useLayoutEffect(() => {
    if ((targetElements?.length || 0) && (parentEls?.length || 0)) {
      init();
    }
    return () => {
      resizeObserverRef.current?.disconnect();
      mutationObserverRef.current?.disconnect();
      removeAnimationEndListenerRef.current?.();
    };
  }, [init, targetElements, parentEls]);

  return useMemo(() => {
    return { offset, recalculateOffset };
  }, [offset, recalculateOffset]);
}

export default useSyncPreviewElementOffset;
