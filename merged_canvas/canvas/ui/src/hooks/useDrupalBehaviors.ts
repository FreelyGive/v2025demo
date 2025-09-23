import { useEffect } from 'react';

import { getDrupal } from '@/utils/drupal-globals';

import type { RefObject } from 'react';

const Drupal = getDrupal();

export function useDrupalBehaviors(
  ref: RefObject<HTMLElement>,
  dependency: React.ReactNode | null | HTMLDivElement,
) {
  useEffect(() => {
    let element: HTMLElement | null = null;

    const timeout = setTimeout(() => {
      if (dependency && ref.current) {
        Drupal.attachBehaviors(ref.current);
        element = ref.current;
      }
    });

    return () => {
      clearTimeout(timeout);
      if (element) {
        Drupal.detachBehaviors(element);
      }
    };
  }, [ref, dependency]);
}
