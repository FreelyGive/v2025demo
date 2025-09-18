import { forwardRef } from 'react';
import { TextArea as TextAreaRadixThemes } from '@radix-ui/themes';

import type { ForwardedRef } from 'react';
import type { Attributes } from '@/types/DrupalAttribute';

import styles from './TextArea.module.css';

const TextArea = forwardRef(function TextArea(
  {
    value,
    attributes = {},
  }: {
    value: string;
    attributes?: Attributes;
  },
  ref: ForwardedRef<HTMLTextAreaElement>,
) {
  return (
    <TextAreaRadixThemes
      value={value}
      className={styles.root}
      {...attributes}
      ref={ref}
    />
  );
});

export default TextArea;
