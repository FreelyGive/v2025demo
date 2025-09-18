import { Button, Card, Flex, Text } from '@radix-ui/themes';

import styles from './DefaultSitePanel.module.css';

interface DefaultSitePanelProps {
  onClickEdit: () => void;
  onClickPreview: () => void;
}

const DefaultSitePanel = ({
  onClickEdit,
  onClickPreview,
}: DefaultSitePanelProps) => {
  return (
    <Card className={styles.defaultCard}>
      <Flex p="8" direction="column" gap="3" align="center">
        <Flex align="center" gap="0" direction="column">
          <Text size="2" weight="bold">
            Default Site
          </Text>
          <Text size="1" color="gray" align="center">
            The site as it appears when no personalization rules apply.
          </Text>
        </Flex>
        <Flex
          align="center"
          gap="4"
          direction={{ initial: 'column', xs: 'row' }}
        >
          <Button variant="outline" onClick={onClickEdit}>
            Edit Default
          </Button>
          <Button variant="ghost" onClick={onClickPreview}>
            Preview Default
          </Button>
        </Flex>
      </Flex>
    </Card>
  );
};

export default DefaultSitePanel;
