import clsx from 'clsx';
import { Box } from '@radix-ui/themes';

import { AccordionDetails } from '@/components/form/components/Accordion';
import Details from '@/components/form/components/Details';
import { a2p } from '@/local_packages/utils.js';

import type { ReactNode } from 'react';
import type { Attributes } from '@/types/DrupalAttribute';

const DrupalDetails = ({
  attributes = {},
  errors = null,
  title = '',
  summaryAttributes = {},
  description = null,
  renderChildren = null,
  value = null,
  required = false,
  element = {},
}: {
  attributes: Attributes;
  errors: ReactNode;
  title: string;
  summaryAttributes?: Attributes;
  description: ReactNode;
  renderChildren?: ReactNode;
  value: ReactNode;
  required: boolean;
  element: { [key: string]: any };
}) => {
  if (element?.['#accordion_items']) {
    return (
      <AccordionDetails
        title={title}
        attributes={a2p(attributes, {}, { skipAttributes: ['class'] })}
        summaryAttributes={a2p(summaryAttributes, {
          class: clsx(required && ['js-form-required', 'form-required']),
        })}
      >
        {errors && <Box>{errors}</Box>}
        {description && <Box>{description}</Box>}
        <Box>{renderChildren}</Box>
        {value && <Box>{value}</Box>}
      </AccordionDetails>
    );
  } else {
    return (
      <Details
        title={title}
        attributes={a2p(attributes, {}, { skipAttributes: ['class'] })}
        summaryAttributes={a2p(summaryAttributes)}
      >
        {renderChildren}
      </Details>
    );
  }
};

export default DrupalDetails;
