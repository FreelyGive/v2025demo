import {
  forwardRef,
  useCallback,
  useEffect,
  useImperativeHandle,
  useRef,
  useState,
} from 'react';
import clsx from 'clsx';

import { useAppSelector } from '@/app/hooks';
import { COMPONENT_PREVIEW_UPDATE_EVENT } from '@/components/form/formUtil';
import { selectDragging } from '@/features/ui/uiSlice';

import type { Dispatch, Ref, SetStateAction } from 'react';
import type { ComponentPreviewUpdateEvent } from '@/components/form/formUtil';

import styles from '@/features/layout/preview/Preview.module.css';

interface IFrameSwapperProps {
  srcDocument: string;
  setIsReloading: Dispatch<SetStateAction<boolean>>;
  interactive: boolean;
}

const IFrameSwapper = forwardRef<HTMLIFrameElement, IFrameSwapperProps>(
  (
    { srcDocument, setIsReloading, interactive },
    ref: Ref<HTMLIFrameElement>,
  ) => {
    const iFrameRefs = useRef<(HTMLIFrameElement | null)[]>([]);
    const whichActiveRef = useRef(0);
    const [whichActive, setWhichActive] = useState(0);
    const { isDragging } = useAppSelector(selectDragging);

    useImperativeHandle(ref, () => {
      if (!iFrameRefs.current[0] || !iFrameRefs.current[1]) {
        throw new Error(
          'Error passing iFrame ref to parent: One of the iframe refs is null',
        );
      }
      return iFrameRefs.current[whichActive] as HTMLIFrameElement;
    });

    const getIFrames = useCallback(() => {
      const activeIFrame = iFrameRefs.current[whichActiveRef.current];
      const inactiveIFrame = iFrameRefs.current[whichActiveRef.current ? 0 : 1];

      if (!activeIFrame || !inactiveIFrame) {
        throw new Error(
          'Error initializing iFrameSwapper. One of the iframe refs is null',
        );
      }
      return { activeIFrame, inactiveIFrame };
    }, []);

    const swapIFrames = useCallback((e: Event) => {
      // The event target is the iframe about to become active.
      const iframe = e.target as HTMLIFrameElement | null;
      if (!iframe?.srcdoc?.length) {
        // The load event in some browsers (e.g., Safari) fires on page load if the srcdoc is empty, but we don't want to swap in that case.
        return;
      }

      iframe.style.display = '';

      const startTime = Date.now();
      const checkIFrameContent = () => {
        // Find all <template>s pending hydration by Astro. Once hydration is complete, there should be no pending templates remaining.
        const pendingTemplates = iframe?.contentDocument?.querySelectorAll(
          'template[data-astro-template]',
        ).length;

        // Ensure there are no pending templates (non-hydrated astro-islands) before swapping in the iframe.
        if (pendingTemplates === 0) {
          setWhichActive((current) => 1 - current);
        } else if (Date.now() - startTime < 1000) {
          // If the hydration still hasn't finished and 1 second hasn't yet elapsed, try again after the next animation frame.
          requestAnimationFrame(checkIFrameContent);
        } else {
          // Fallback to ensure iframe is swapped after 1s in case the iframe content never loads (or is really slow).
          console.warn(
            'Astro hydration in iFrame did not complete within 1 second, swapping anyway.',
          );
          setWhichActive((current) => 1 - current);
        }
      };

      // Continuously monitor the iframe content for non-hydrated templates every 'tick'.
      // Only swap the iframe once all templates are hydrated or 1 second has elapsed.
      requestAnimationFrame(checkIFrameContent);
    }, []);

    // Sync the whichActiveRef to the whichActive state and then set isReloading back to false.
    useEffect(() => {
      const listener = (e: ComponentPreviewUpdateEvent) => {
        const { activeIFrame } = getIFrames();
        // Find the canvas-island elements for the updated component.
        const component = activeIFrame.contentDocument?.querySelector(
          `canvas-island[uid="${e.componentUuid}"]`,
        );
        if (!component) {
          // We need a round trip to update the preview here.
          e.setPreviewBackgroundUpdate(false);
          return;
        }
        // We can update the preview in the background.
        e.setPreviewBackgroundUpdate(true);

        component.setAttribute(
          'props',
          JSON.stringify({
            ...JSON.parse(component.getAttribute('props')!),
            [e.propName]: ['raw', e.propValue],
          }),
        );
      };
      document.addEventListener(
        COMPONENT_PREVIEW_UPDATE_EVENT,
        listener as any as EventListener,
      );
      return () => {
        document.removeEventListener(
          COMPONENT_PREVIEW_UPDATE_EVENT,
          listener as any as EventListener,
        );
      };
    }, [getIFrames]);

    useEffect(() => {
      whichActiveRef.current = whichActive;
      setIsReloading(false);
    }, [getIFrames, setIsReloading, whichActive]);

    // Run when the srcDocument changes
    useEffect(() => {
      // Important to change a state ensure parent re-renders (and re-calls hooks) once the iframe is swapped.
      setIsReloading(true);
      const { activeIFrame, inactiveIFrame } = getIFrames();

      // Initialize active iframe if not already initialized
      if (activeIFrame && !activeIFrame.srcdoc) {
        activeIFrame.style.display = 'block';
        activeIFrame.srcdoc = srcDocument;
      }

      // Immediately set the currently active iframe to not initialized
      if (activeIFrame) {
        activeIFrame.dataset.testCanvasContentInitialized = 'false';
      }

      // Set up load event listener and update content for inactive iframe. Once loaded, it will be swapped in.
      if (inactiveIFrame) {
        inactiveIFrame.removeEventListener('load', swapIFrames);
        // There is a flicker in Chrome when swapping in an iframe by changing the css display from none to block but the
        // flicker does not occur when swapping opacity from 0 to 1.
        // Here, we set the inactive iframe's display to block before updating its srcdoc (but the stylesheet still
        // maintains opacity: 0, so the iframe remains hidden until it has finished loading)
        // Once the iframe loads, its opacity changes to 1, making it visible while the newly inactive iframe becomes display: none.
        // This means that when the swap occurs, both iframes are display: block; and we are just swapping the opacity from 0/1
        inactiveIFrame.style.display = 'block';
        inactiveIFrame.addEventListener('load', swapIFrames);
        inactiveIFrame.srcdoc = srcDocument;
      }

      return () => {
        inactiveIFrame?.removeEventListener('load', swapIFrames);
        activeIFrame?.removeEventListener('load', swapIFrames);
      };
    }, [srcDocument, setIsReloading, swapIFrames, getIFrames]);

    const commonIFrameProps = {
      className: clsx(styles.preview, {
        [styles.interactable]: isDragging || interactive,
      }),
      'data-canvas-preview': 'true',
      'data-test-canvas-content-initialized': 'false',
    };

    return (
      <>
        <iframe
          // Set the tab index to 0 when the iframe is interactive, -1 when it is not.
          tabIndex={!interactive || whichActive === 1 ? -1 : 0}
          ref={(el) => (iFrameRefs.current[0] = el)}
          data-canvas-swap-active={whichActive === 0 ? 'true' : 'false'}
          title={whichActive === 0 ? 'Preview' : 'Inactive preview'}
          data-canvas-iframe="A"
          scrolling="no"
          {...commonIFrameProps}
        ></iframe>
        <iframe
          tabIndex={!interactive || whichActive === 0 ? -1 : 0}
          ref={(el) => (iFrameRefs.current[1] = el)}
          data-canvas-swap-active={whichActive === 1 ? 'true' : 'false'}
          title={whichActive === 1 ? 'Preview' : 'Inactive preview'}
          data-canvas-iframe="B"
          scrolling="no"
          {...commonIFrameProps}
        ></iframe>
      </>
    );
  },
);
export default IFrameSwapper;
