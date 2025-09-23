import { useCallback, useLayoutEffect, useRef } from 'react';

/**
 * This hook takes preview iFrame and ensures that the height of the iFrame html element matches the height of the
 * content being rendered in the iFrame. It uses a mutation observer to keep it in sync
 */
function useSyncIframeHeightToContent(
  iframe: HTMLIFrameElement | null,
  previewContainer: HTMLDivElement | null,
  height: number,
) {
  const mutationObserverRef = useRef<MutationObserver | null>(null);
  const resizeObserverRef = useRef<ResizeObserver | null>(null);

  const resizeIframe = useCallback(() => {
    if (iframe && iframe.contentDocument) {
      const iframeHTML = iframe.contentDocument.documentElement;
      const iframeBody = iframe.contentDocument.body;
      window.requestAnimationFrame(() => {
        if (previewContainer?.style) {
          // set the iFrame container height to the height of the content inside the iFrame.
          if (iframeHTML?.offsetHeight) {
            previewContainer.style.height = `${iframeHTML.offsetHeight}px`;
          }
        }
        if (iframeHTML?.style) {
          iframeHTML.style.minHeight = height + 'px';
        }
        if (iframeBody?.style) {
          iframeBody.style.minHeight = height + 'px';
        }
      });
    }
  }, [iframe, height, previewContainer]);

  useLayoutEffect(() => {
    if (iframe) {
      const handleLoad = () => {
        const iframeContentDoc = iframe.contentDocument;

        if (iframeContentDoc) {
          const iframeHTML = iframeContentDoc.documentElement;

          // initially set the iFrame height to the height passed in to the hook
          iframe.style.height = height + 'px';
          iframeHTML.style.overflow = 'hidden';
          // Set up a MutationObserver to watch for changes in the content of the iframe
          mutationObserverRef.current = new MutationObserver(resizeIframe);
          mutationObserverRef.current.observe(iframeHTML, {
            attributes: true,
            childList: true,
            subtree: true,
          });
          resizeObserverRef.current = new ResizeObserver(resizeIframe);
          resizeObserverRef.current.observe(iframeHTML);

          // Apply a max-height to elements with vh units in their height - otherwise an infinite loop can occur where a component's
          // height is based on the height of the iFrame and the iFrame's height is based on that component leading
          // to an ever-increasing iFrame height!
          const elements: NodeListOf<HTMLElement> =
            iframeHTML.querySelectorAll('*');

          const previewIframe = document.querySelector(
            'iframe[data-canvas-swap-active="true"]',
          ) as HTMLIFrameElement | null;
          const multipliers = [1, 3, 8];
          const heightRatios = new WeakMap<HTMLElement, number[]>();

          // Set the iframe to multiple heights, then check every element to see
          // if its height increases at the same ratio as the iframe. In those
          // instances, the element height is likely styled with VH units, and
          // will require special handling inside in the elements.forEach found
          // after this block.
          multipliers.forEach((multi) => {
            iframe.style.height = height * multi + 'px';
            iframe.style.overflow = 'visible';
            elements.forEach((element) => {
              const ratios: number[] = heightRatios.get(element) || [];
              if (element.clientHeight > 10) {
                ratios.push(Math.floor(element.clientHeight / multi));
                heightRatios.set(element, ratios);
              }
            });
          });
          iframe.style.height = '';
          iframe.style.overflow = '';

          elements.forEach((element) => {
            if (previewIframe) {
              // These height ratios were determined in the prior forEach block,
              // and are used to identify element heights with VH units.
              const ratios: Number[] = heightRatios.get(element) || [];

              // If the element height consistently changed at the same ratio
              // as the container iframe (all 3 numbers in ratios are the same),
              // we can use the value in the ratios map to set a max height and
              // avoid infinitely growing vh elements.
              if (
                !['HTML', 'BODY'].includes(element.tagName) &&
                ratios.length === multipliers.length &&
                ratios.every((ratio) => ratio === ratios[0])
              ) {
                const maxHeight = ratios[0];
                element.style.maxHeight = maxHeight ? `${maxHeight}px` : '';
                element.setAttribute(
                  'data-canvas-preview-max-height',
                  `${maxHeight}`,
                );
              }
            }
          });

          resizeIframe();
        }
      };

      // Assign the load event listener
      iframe.addEventListener('load', handleLoad);

      // Check if the iFrame is already loaded
      if (iframe.contentDocument?.readyState === 'complete') {
        handleLoad();
      }

      return () => {
        iframe.removeEventListener('load', handleLoad);
        mutationObserverRef.current?.disconnect();
        resizeObserverRef.current?.disconnect();
      };
    }
  }, [iframe, height, resizeIframe]);
}

export default useSyncIframeHeightToContent;
