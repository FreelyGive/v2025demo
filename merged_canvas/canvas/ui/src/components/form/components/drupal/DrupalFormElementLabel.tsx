import clsx from 'clsx';

import FormElementLabel from '@/components/form/components/FormElementLabel';
import { a2p } from '@/local_packages/utils.js';

import type { Attributes } from '@/types/DrupalAttribute';

const DrupalFormElementLabel = ({
  title = { '#markup': '' },
  titleDisplay = '',
  required = '',
  attributes = {},
}: {
  title:
    | {
        '#markup': string;
      }
    | string;
  titleDisplay?: string;
  required?: string;
  attributes?: Attributes;
}) => {
  const classes = clsx(
    titleDisplay === 'after' ? 'option' : '',
    titleDisplay === 'invisible' ? 'visually-hidden' : '',
    required ? 'js-form-required' : '',
    required ? 'form-required' : '',
  );
  const show = !!title || !!required;
  return (
    show && (
      <FormElementLabel
        attributes={a2p(attributes, {}, { skipAttributes: ['class'] })}
        className={classes}
      >
        {typeof title === 'string' ? title : title['#markup']}
      </FormElementLabel>
    )
  );
};

export default DrupalFormElementLabel;
