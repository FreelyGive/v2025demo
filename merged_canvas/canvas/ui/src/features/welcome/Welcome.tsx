import { InfoCircledIcon } from '@radix-ui/react-icons';
import { Callout, Flex } from '@radix-ui/themes';

import type React from 'react';

const Welcome: React.FC<{ children?: React.ReactNode }> = () => {
  return (
    <Flex align="center" justify="center" width="100%">
      <Flex maxWidth="400px" width="">
        <Callout.Root color="blue">
          <Callout.Icon>
            <InfoCircledIcon />
          </Callout.Icon>
          <Callout.Text>
            Welcome to Experience Builder! This tool allows you to create and
            manage your content experiences with ease. You can add components,
            customize layouts, and preview your changes in real-time.
          </Callout.Text>
          <Callout.Text>
            Use the navigator at the top of the page to create a page or edit an
            existing one from the list.
          </Callout.Text>
        </Callout.Root>
      </Flex>
    </Flex>
  );
};

export default Welcome;
