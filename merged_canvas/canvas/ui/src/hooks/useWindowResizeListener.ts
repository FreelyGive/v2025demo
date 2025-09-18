import { useEffect, useRef } from 'react';

/**
 * A hook that listens for window resize events and triggers a callback using `requestAnimationFrame`.
 *
 * @param callback - A function to be called when the window is resized.
 */
function useWindowResizeListener(callback: () => void): void {
  const requestRef = useRef<number | null>(null);

  useEffect(() => {
    const handleResize = () => {
      if (requestRef.current) {
        cancelAnimationFrame(requestRef.current);
      }
      requestRef.current = window.requestAnimationFrame(callback);
    };

    window.addEventListener('resize', handleResize);

    return () => {
      window.removeEventListener('resize', handleResize);
      if (requestRef.current) {
        cancelAnimationFrame(requestRef.current);
      }
    };
  }, [callback]);
}

export default useWindowResizeListener;
