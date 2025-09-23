import { Flex, Text } from '@radix-ui/themes';

import styles from './FormElement.module.css';

export function FormElement({ children }: { children: React.ReactNode }) {
  return (
    <Flex direction="column" gap="1">
      {children}
    </Flex>
  );
}

export function Label({
  children,
  htmlFor,
}: {
  children: React.ReactNode;
  htmlFor?: string;
}) {
  return (
    <Text htmlFor={htmlFor} as="label" size="1" weight="medium">
      {children}
    </Text>
  );
}

export function Divider() {
  return <hr className={styles.divider} />;
}
