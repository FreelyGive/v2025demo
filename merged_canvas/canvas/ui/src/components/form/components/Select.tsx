import clsx from 'clsx';

import { a2p } from '@/local_packages/utils';

import type { Attributes } from '@/types/DrupalAttribute';

import styles from './Select.module.css';

interface SelectProps {
  attributes?: Attributes;
  options?: Array<{
    value: string;
    label: string;
    selected: boolean;
    type: string;
  }>;
}
const Select: React.FC<SelectProps> = ({ attributes = {}, options = [] }) => {
  return (
    <select
      {...a2p(attributes)}
      className={clsx(attributes.class || '', styles.select)}
      ref={(element) => {
        if (element && attributes.onChange) {
          // Below is logic that overrides the native setter for the value
          // property, so any programmatic changes to it trigger a change event -
          // something that is needed to update the Redux store and the preview.
          const elementProto = Object.getPrototypeOf(element);
          const descriptor = Object.getOwnPropertyDescriptor(
            elementProto,
            'value',
          );
          if (!descriptor || !descriptor.set) {
            return;
          }
          const originalSetter = descriptor.set;

          Object.defineProperty(element, 'value', {
            get: descriptor.get,
            set: function (newValue) {
              originalSetter.call(this, newValue);
              const changeEvent = new Event('change');
              Object.defineProperty(changeEvent, 'target', {
                writable: false,
                value: this,
              });
              if (typeof attributes.onChange === 'function') {
                attributes.onChange(changeEvent);
                if (window.jQuery) {
                  const $target = window.jQuery(this);
                  if ($target.length) {
                    $target.trigger('change');
                  }
                }
              }
            },
            configurable: true,
          });
        }
      }}
    >
      {options.map((option, index) => (
        <option key={index} value={option.value}>
          {option.label}
        </option>
      ))}
    </select>
  );
};

export default Select;
