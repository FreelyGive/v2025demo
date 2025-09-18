import { PlusIcon } from '@radix-ui/react-icons';
import { IconButton, Tooltip } from '@radix-ui/themes';

import type * as React from 'react';

export interface PropsForWithTooltip {
  attributes: {
    id: string;
    [x: string | number | symbol]: unknown;
  };
  [x: string | number | symbol]: unknown;
}
// Higher order component to add a tooltip.
const WithTooltip = (WrappedComponent: typeof React.Component) => {
  return function (props: PropsForWithTooltip) {
    const id = props.attributes.id;
    return (
      <>
        <Tooltip content={id}>
          <IconButton radius="full">
            <PlusIcon />
          </IconButton>
        </Tooltip>
        <WrappedComponent />
      </>
    );
  };
};

export default WithTooltip;
