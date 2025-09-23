import clsx from 'clsx';
import * as Tooltip from '@radix-ui/react-tooltip';

import type { ReactElement } from 'react';

import styles from './Tooltip.module.css';

const TooltipComponent = ({
  children,
  content,
  side = 'right',
}: {
  children: ReactElement;
  content: string;
  side?: 'right' | 'top' | 'bottom' | 'left' | undefined;
}) => {
  return (
    <Tooltip.Provider>
      <Tooltip.Root delayDuration={0}>
        <Tooltip.Trigger>{children}</Tooltip.Trigger>
        <Tooltip.Portal>
          <Tooltip.Content
            side={side}
            className={clsx('TooltipContent', styles.TooltipContent)}
          >
            {content}
            <Tooltip.Arrow
              className={clsx('TooltipArrow', styles.TooltipArrow)}
            />
          </Tooltip.Content>
        </Tooltip.Portal>
      </Tooltip.Root>
    </Tooltip.Provider>
  );
};

export default TooltipComponent;
