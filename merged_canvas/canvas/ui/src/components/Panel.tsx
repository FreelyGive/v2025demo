import React from 'react';
import clsx from 'clsx';
import { Slot } from '@radix-ui/react-slot';
import { Box } from '@radix-ui/themes';

import styles from './Panel.module.css';

const Panel = React.forwardRef<
  React.ElementRef<typeof Box>,
  React.ComponentProps<typeof Box>
>((props, ref) => {
  const { asChild, className } = props;
  const Comp = asChild ? Slot : Box;

  return (
    <Comp {...props} ref={ref} className={clsx(styles.panel, className)} />
  );
});

export default Panel;
