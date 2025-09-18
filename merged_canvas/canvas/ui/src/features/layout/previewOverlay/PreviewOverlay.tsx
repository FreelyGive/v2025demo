import clsx from 'clsx';

import type React from 'react';

import styles from './PreviewOverlay.module.css';

/**
 * Renders a div at a level above where we scale the editor frame into which we can portal the UI and overlay that sits over
 * the iframes. Doing this means the UI doesn't scale when the user zooms the editor frame.
 */
const PreviewOverlay: React.FC = () => {
  return (
    <div
      id="canvasPreviewOverlay"
      className={clsx('previewOverlay', styles.overlay)}
    ></div>
  );
};

export default PreviewOverlay;
