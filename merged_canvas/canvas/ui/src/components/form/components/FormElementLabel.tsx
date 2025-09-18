import clsx from 'clsx';
import { Text } from '@radix-ui/themes';

import type { ReactNode } from 'react';
import type { Attributes } from '@/types/DrupalAttribute';

import styles from './FormElementLabel.module.css';

const FormElementLabel = ({
  children = null,
  attributes = {},
  className = '',
}: {
  children: ReactNode;
  attributes?: Attributes;
  className?: string | null;
}) => {
  return (
    <Text
      as="label"
      size="1"
      weight="medium"
      {...attributes}
      className={clsx(styles.root, className)}
    >
      {children}
    </Text>
  );
};

export default FormElementLabel;
