import { useEffect } from 'react';

import type { MutableRefObject } from 'react';

interface ObserverOptions {
  attributes?: boolean;
  characterData?: boolean;
  childList?: boolean;
  subtree?: boolean;
}

// This is largely pulled from
// https://www.30secondsofcode.org/react/s/use-mutation-observer/
const useMutationObserver = (
  ref: MutableRefObject<undefined | null | any>,
  callback: MutationCallback,
  options: ObserverOptions = {
    attributes: true,
    characterData: true,
    childList: true,
    subtree: true,
  },
) => {
  useEffect(() => {
    if (ref.current) {
      const observer = new MutationObserver(callback);
      observer.observe(ref.current, options);
      return () => observer.disconnect();
    }
  }, [callback, options, ref]);
};

export default useMutationObserver;
