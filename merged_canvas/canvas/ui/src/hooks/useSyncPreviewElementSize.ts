import { useCallback, useLayoutEffect, useMemo, useRef, useState } from 'react';

import { calculateBoundingRect, elemIsVisible } from '@/utils/function-utils';

/**
 * This hook takes an HTML element or array of HTML elements and returns a state containing the elements' dimensions and position.
 * It uses a mutation observer and resize observer to ensure that even if the element changes size or position at any point
 * the returned values are updated.
 */

interface Rect {
  top: number;
  left: number;
  width: number;
  height: number;
}

function findParentBody(element: HTMLElement) {
  let currentElement = element;

  while (currentElement) {
    if (currentElement.nodeName.toLowerCase() === 'body') {
      return currentElement; // Found the body element
    }
    const parent = currentElement.parentElement;
    if (!parent) {
      break;
    }
    currentElement = parent;
  }

  return null; // Return null if no <body> is found
}

function isElementObservable(element: HTMLElement) {
  const style = window.getComputedStyle(element);

  // display: contents; elements (e.g. <astro-* />) do not fire resize events.
  if (style.display === 'contents') {
    return false;
  }

  return elemIsVisible(element);
}

function useSyncPreviewElementSize(input: HTMLElement[] | HTMLElement | null) {
  // Normalize the input to always be an array
  const elements = useMemo(() => {
    if (!input) {
      return null;
    }
    return Array.isArray(input) ? input : [input];
  }, [input]);

  const [elementRect, setElementRect] = useState<Rect>({
    top: 0,
    left: 0,
    width: 0,
    height: 0,
  });

  const resizeObserverRef = useRef<ResizeObserver | null>(null);
  const mutationObserverRef = useRef<MutationObserver | null>(null);
  const elementsRef = useRef<HTMLElement[] | null>(null);
  const removeAnimationEndListenerRef = useRef<(() => void) | null>(null);

  const recalculateBorder = useCallback(() => {
    const elements = elementsRef.current;
    const newRect = calculateBoundingRect(elements);

    if (newRect && elements) {
      requestAnimationFrame(() => {
        setElementRect((prevRect) => {
          // Only update if the values have changed so the hook returns the same object preventing components that use
          // it from re-rendering. Don't update if the height/width is 0 to stop border flickering
          if (
            (prevRect.top !== newRect.top ||
              prevRect.left !== newRect.left ||
              prevRect.width !== newRect.width ||
              prevRect.height !== newRect.height) &&
            newRect.width !== 0 &&
            newRect.height !== 0
          ) {
            return newRect;
          }
          return prevRect;
        });
      });
    }
  }, []);

  const forceRecalculateBorder = useCallback(() => {
    // updates the elementRect state immediately, which will trigger a re-render even if the dimensions haven't changed
    // to catch situations where the position HAS changed due to transforms/translations/scale etc.
    const elements = elementsRef.current;
    const newRect = calculateBoundingRect(elements);

    if (newRect && elements) {
      requestAnimationFrame(() => {
        setElementRect(newRect);
      });
    }
  }, []);

  // Update the elementsRef whenever the elements change
  useLayoutEffect(() => {
    elementsRef.current = elements;
    recalculateBorder();
  }, [elements, recalculateBorder]);

  const init = useCallback(() => {
    // Disconnect existing observers
    resizeObserverRef.current?.disconnect();
    mutationObserverRef.current?.disconnect();

    resizeObserverRef.current = new ResizeObserver((entries) => {
      entries.forEach(() => {
        recalculateBorder();
      });
    });

    mutationObserverRef.current = new MutationObserver((mutationsList) => {
      mutationsList.forEach((mutation) => {
        // Calculate the borders immediately
        recalculateBorder();
        if (
          mutation.type === 'attributes' &&
          mutation.attributeName === 'style'
        ) {
          const target = mutation.target;
          // Calculate borders again after transitions to take into account the final result/position of css animations
          // that may have been applied by the mutation.
          target.addEventListener('transitionend', recalculateBorder, {
            once: true,
          });
        }
      });
    });

    elementsRef.current?.forEach((element) => {
      /**
       * <canvas-island> elements (Canvas Code Components) are display: contents; and that means you can't observe them with
       * resizeObserver. Here, if the element we're syncing with can't be observed we traverse up the DOM to find the
       * first parent that can be and watch that instead
       */
      if (isElementObservable(element)) {
        resizeObserverRef.current?.observe(element);
      } else {
        // Traverse up to find an observable parent
        let parent = element.parentElement;
        while (parent && !isElementObservable(parent)) {
          parent = parent.parentElement;
        }

        if (parent) {
          resizeObserverRef.current?.observe(parent);
        } else {
          console.warn(
            'Element size cannot be observed because it does not have a valid/observable content rect.',
          );
        }
      }

      // Observe mutations on the body to account for other elements updating that may affect the position of this one
      const parentBody = findParentBody(element);
      if (parentBody) {
        mutationObserverRef.current?.observe(parentBody, {
          attributes: true,
          childList: true,
          subtree: true,
        });
      }
      element.removeEventListener('animationend', forceRecalculateBorder);

      // Bind animationend listener
      element.addEventListener('animationend', forceRecalculateBorder);
    });

    // Store cleanup logic for animationend listeners
    removeAnimationEndListenerRef.current = () => {
      elementsRef.current?.forEach((element) => {
        element.removeEventListener('animationend', forceRecalculateBorder);
      });
    };
  }, [forceRecalculateBorder, recalculateBorder]);

  // Initialize the observers and listeners when the component mounts
  useLayoutEffect(() => {
    if (elements?.length) {
      init();
    }

    return () => {
      // Cleanup observers
      resizeObserverRef.current?.disconnect();
      mutationObserverRef.current?.disconnect();

      // Cleanup animationend listeners
      removeAnimationEndListenerRef.current?.();
    };
  }, [init, elements]);

  // Use useMemo to return a stable reference
  return useMemo(() => {
    return { elementRect, recalculateBorder };
  }, [elementRect, recalculateBorder]);
}

export default useSyncPreviewElementSize;
