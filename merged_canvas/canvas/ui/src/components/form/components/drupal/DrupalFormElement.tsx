import clsx from 'clsx';

import { a2p } from '@/local_packages/utils.js';

import type { Attributes } from '@/types/DrupalAttribute';

// @todo Import styles in a standalone <FormElement> component.
// https://www.drupal.org/i/3491293
import styles from '../FormElement.module.css';

export type Description = {
  content?: string;
  attributes?: Attributes;
};

interface FormElementProps {
  attributes?: Attributes;
  errors: string | null;
  prefix: string | null;
  suffix: string | null;
  required: boolean | null;
  type: string | null;
  name: string | null;
  label: string | null;
  labelDisplay: string;
  description: Description;
  descriptionDisplay: string;
  disabled: string | null;
  titleDisplay: string;
  children: string | null;
  renderChildren: string | any[] | null;
}

const DrupalFormElement = ({
  attributes = {},
  errors = '',
  prefix = '',
  suffix = '',
  required = false,
  type = '',
  name,
  label = '',
  labelDisplay = '',
  description = {},
  descriptionDisplay = '',
  disabled = '',
  titleDisplay = '',
  children = '',
  renderChildren = '',
}: FormElementProps) => {
  const classes = clsx(
    'js-form-item',
    'form-item',
    `js-form-type-${type}`,
    `form-type-${type}`,
    `js-form-item-${name}`,
    `form-item-${name}`,
    !['after', 'before'].includes(titleDisplay) ? 'form-no-label' : '',
    disabled === 'disabled' ? 'form-disabled' : '',
    errors ? 'form-item--error' : '',
    // @todo Add styles below in a standalone <FormElement> component.
    // https://www.drupal.org/i/3491293
    styles.root,
    type === 'checkbox' && styles.checkbox,
    type === 'radio' && styles.radio,
  );

  const descriptionClasses = clsx(
    'description',
    descriptionDisplay === 'invisible' ? 'visually-hidden' : '',
  );

  return (
    // @todo Extract to a standalone <FormElement> component.
    // https://www.drupal.org/i/3491293
    <div {...a2p(attributes, { class: classes })}>
      {['before', 'invisible'].includes(labelDisplay) && label}
      {prefix && prefix.length > 0 && (
        <span className="field-prefix">{prefix}</span>
      )}
      {descriptionDisplay === 'before' &&
        description.content &&
        description.content && (
          // @todo Extract to a standalone <FormElementDescription> component.
          // @todo Pass as a prop to the <FormElement> component.
          // https://www.drupal.org/i/3491293
          <div
            {...a2p(description.attributes || {}, {
              class: descriptionClasses,
            })}
          >
            {description.content}
          </div>
        )}
      {renderChildren}
      {suffix && suffix.length > 0 && (
        <span className="field-suffix">{suffix}</span>
      )}
      {['after'].includes(labelDisplay) && label}
      {errors && (
        <div className="form-item--error-message form-item-errors">
          {errors}
        </div>
      )}
      {['after', 'invisible'].includes(descriptionDisplay) &&
        description.content &&
        description.content && (
          // @todo Extract to a standalone <FormElementDescription> component.
          // @todo Pass as a prop to the <FormElement> component.
          // https://www.drupal.org/i/3491293
          <div
            {...a2p(description.attributes || {}, {
              class: descriptionClasses,
            })}
          >
            {description.content || ''}
          </div>
        )}
    </div>
  );
};

export default DrupalFormElement;
