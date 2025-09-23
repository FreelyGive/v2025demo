import clsx from 'clsx';

import { a2p } from '@/local_packages/utils.js';

import type { Attributes } from '@/types/DrupalAttribute';

import styles from './Radio.module.css';

const RadioGroup = ({
  attributes = {},
  children,
}: {
  attributes?: Attributes;
  children?: React.ReactNode;
}) => {
  return <div {...attributes}>{children}</div>;
};

const RadioItem = ({
  attributes = {},
  onChange,
}: {
  attributes?: Attributes;
  onChange?: Function;
}) => {
  return (
    <input
      type="radio"
      {...a2p(attributes, {}, { skipAttributes: ['class'] })}
      className={clsx(attributes.class || '', styles.radio)}
      onChange={onChange}
    />
  );
};

export { RadioItem, RadioGroup };
