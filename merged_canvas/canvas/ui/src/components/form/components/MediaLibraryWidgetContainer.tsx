import clsx from 'clsx';

import { a2p } from '@/local_packages/utils';

import styles from './MediaLibraryWidgetContainer.module.css';

interface MediaLibraryWidgetContainerProps {
  attributes: {
    class?: string;
    [key: string]: any;
  };
  renderChildren: React.ReactNode;
}
const MediaLibraryWidgetContainer = ({
  attributes,
  renderChildren,
}: MediaLibraryWidgetContainerProps) => {
  const classes = clsx(attributes.class, styles.container);
  return (
    <div
      {...a2p(attributes, {}, { skipAttributes: ['class'] })}
      className={classes}
    >
      {renderChildren}
    </div>
  );
};

export default MediaLibraryWidgetContainer;
