import { useEffect } from 'react';
import clsx from 'clsx';

import { getBaseUrl, getDrupalSettings } from '@/utils/drupal-globals';

import type React from 'react';
import type { CanvasComponent } from '@/types/Component';
import type { Pattern } from '@/types/Pattern';

import styles from './ComponentPreview.module.css';

interface ComponentPreviewProps {
  componentListItem: CanvasComponent | Pattern;
}

const drupalSettings = getDrupalSettings();
const baseUrl = getBaseUrl();

const ComponentPreview: React.FC<ComponentPreviewProps> = ({
  componentListItem,
}) => {
  const component = componentListItem;
  const defaultIframeWidth = 1200;
  const defaultIframeHeight = 800;
  const defaultPreviewWidth = 300;
  const defaultPreviewHeight = 200;

  const css = drupalSettings?.canvas.globalAssets.css + component.css;
  const js_footer =
    drupalSettings?.canvas.globalAssets.jsFooter + component.js_footer;
  const js_header =
    drupalSettings?.canvas.globalAssets.jsHeader + component.js_header;

  const markup = component.default_markup;
  const base_url = window.location.origin + baseUrl;

  const html = `
<html>
	<head>
    <base href=${base_url} />
		<meta charset="utf-8">
		${css}
		${js_header}
		<style>
			html{
				height: auto !important;
				min-height: 100%;
			}
			body {
        background-color: #FFF;
        background-image: none;
			}
			#component-wrapper {
        overflow: hidden;
        display: inline-block;
        min-width: 120px;
			}
		</style>
	</head>
	<body>
    <div id="component-wrapper">
      ${markup}
    </div>
		${js_footer}
	</body>
</html>`;

  const blob = new Blob([html], { type: 'text/html' });
  const blobSrc = URL.createObjectURL(blob);

  useEffect(() => {
    return () => {
      URL.revokeObjectURL(blobSrc);
    };
  }, [blobSrc]);

  const iframeOnLoadHandler = () => {
    const iframe = window.document.querySelector(
      'iframe[data-preview-component-id]',
    ) as HTMLIFrameElement;
    const tooltipElement = document.querySelector(
      '.canvas-previewTooltip',
    ) as HTMLDivElement;
    const scalingElement = document.querySelector(
      '.canvas-scaled',
    ) as HTMLDivElement;

    if (iframe) {
      const componentWrapper =
        iframe.contentDocument!.querySelector('#component-wrapper');

      const offsetWidth = componentWrapper!.scrollWidth;
      let offsetHeight = componentWrapper!.scrollHeight;

      scalingElement.style.width = `${offsetWidth}px`;
      scalingElement.style.height = `${offsetHeight}px`;
      if (
        offsetWidth > defaultPreviewWidth ||
        offsetHeight > defaultPreviewHeight
      ) {
        // If we are here, then one or more component dimensions
        // exceed the preview maximums. We begin by determining
        // how much each dimension exceeds their maximum.
        const widthScale = defaultPreviewWidth / offsetWidth;
        const heightScale = defaultPreviewHeight / offsetHeight;

        const scale = Math.min(widthScale, heightScale);

        scalingElement.style.transform = `scale(${scale})`;

        tooltipElement.style.position = 'relative';
        tooltipElement.style.width = `${offsetWidth * scale}px`;
        tooltipElement.style.height = `${offsetHeight * scale}px`;
      }

      tooltipElement.style.visibility = 'visible';
    }
  };

  return (
    <div
      className={clsx(styles.wrapper, 'canvas-app', 'canvas-previewTooltip')}
    >
      <div className={clsx('canvas-scaled', styles.scaled)}>
        <iframe
          title={component.name}
          width={defaultIframeWidth}
          height={defaultIframeHeight}
          data-preview-component-id={component.id}
          src={blobSrc}
          className={clsx(styles.iframe)}
          onLoad={iframeOnLoadHandler}
        />
      </div>
    </div>
  );
};

export default ComponentPreview;
