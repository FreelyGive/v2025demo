import { DesktopIcon, LaptopIcon, MobileIcon } from '@radix-ui/react-icons';

import type React from 'react';

interface BreakpointIconProps {
  width: number;
}

const BreakpointIcon: React.FC<BreakpointIconProps> = (props) => {
  const { width } = props;

  if (width <= 468) {
    return <MobileIcon />;
  }
  if (width <= 1024) {
    return <LaptopIcon />;
  }
  return <DesktopIcon />;
};

export default BreakpointIcon;
