import { InfoCircledIcon } from '@radix-ui/react-icons';
import { Button, Callout, Flex, Text } from '@radix-ui/themes';

import type React from 'react';

const ConflictWarning: React.FC<{ children?: React.ReactNode }> = () => {
  const handleRefreshClick = () => {
    window.location.reload();
  };
  return (
    <Flex align="center" justify="center" width="100%">
      <Flex maxWidth="400px" width="">
        <Callout.Root color="blue">
          <Callout.Icon>
            <InfoCircledIcon />
          </Callout.Icon>
          <Callout.Text>
            Your latest change was not saved because the content was modified
            elsewhere since you loaded the page. Please refresh your browser to
            receive the latest changes and continue.
          </Callout.Text>
          <Button mt="2" onClick={handleRefreshClick}>
            Refresh
          </Button>
        </Callout.Root>
      </Flex>
    </Flex>
  );
};

export default ConflictWarning;
