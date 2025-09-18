import { useEffect, useRef } from 'react';

/**
 * A hook that listens for transitionend events on a specific element and triggers a callback using `requestAnimationFrame`.
 *
 * @param element - The HTML element to listen for transitionend events.
 * @param callback - A function to be called when a transition ends on the specified element.
 */
function useTransitionEndListener(
  element: HTMLElement | null,
  callback: () => void,
): void {
  const requestRef = useRef<number | null>(null);

  useEffect(() => {
    if (!element) {
      return;
    }

    const handleTransitionEnd = (event: TransitionEvent) => {
      if (requestRef.current) {
        cancelAnimationFrame(requestRef.current);
      }
      requestRef.current = window.requestAnimationFrame(callback);
    };

    element.addEventListener('transitionend', handleTransitionEnd);

    return () => {
      element.removeEventListener('transitionend', handleTransitionEnd);
      if (requestRef.current) {
        cancelAnimationFrame(requestRef.current);
      }
    };
  }, [element, callback]);
}

export default useTransitionEndListener;
