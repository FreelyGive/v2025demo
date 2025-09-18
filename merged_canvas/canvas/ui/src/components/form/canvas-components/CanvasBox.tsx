import { Box } from '@radix-ui/themes';

import { a2p } from '@/local_packages/utils';

import type { PropsValues } from '@/types/Form';

// This renders `<canvas-box>` components used in Twig templates.
const CanvasBox = (props: PropsValues) => {
  const { children, ...remainingProps } = props;
  return <Box {...a2p(remainingProps)}>{children}</Box>;
};

export default CanvasBox;
