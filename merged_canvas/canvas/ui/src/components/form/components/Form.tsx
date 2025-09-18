import clsx from 'clsx';

import type { ReactNode } from 'react';
import type { Attributes } from '@/types/DrupalAttribute';

import styles from './Form.module.css';

const Form = ({
  attributes = {},
  children = null,
  className = '',
}: {
  children?: ReactNode;
  attributes?: Attributes;
  className?: string;
}) => {
  return (
    <form className={clsx(styles.root, className)} {...attributes}>
      {children}
    </form>
  );
};

export default Form;
