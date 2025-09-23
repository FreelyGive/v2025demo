import { useRef } from 'react';
import { Switch } from '@radix-ui/themes';

import type { Attributes } from '@/types/DrupalAttribute';

const Toggle = ({
  checked = false,
  onCheckedChange,
  attributes = {},
}: {
  checked?: boolean;
  onCheckedChange?: (checked: boolean) => void;
  attributes?: Attributes;
}) => {
  const checkboxRef = useRef<HTMLInputElement | null>(null);
  return (
    <Switch
      ref={(node) => {
        if (node) {
          checkboxRef.current =
            node?.parentElement &&
            node.parentElement.querySelector('input[type="checkbox"]');
          if (checkboxRef.current && onCheckedChange) {
            // Below is logic that overrides the native setter for the checked
            // property, so any programmatic changes to it trigger a change event -
            // something that is needed to update the Redux store and the preview.
            const elementProto = Object.getPrototypeOf(checkboxRef.current);
            const descriptor = Object.getOwnPropertyDescriptor(
              elementProto,
              'checked',
            );
            if (!descriptor || !descriptor.set) {
              return;
            }
            const originalSetter = descriptor.set;
            Object.defineProperty(checkboxRef.current, 'checked', {
              get: descriptor.get,
              set: function (newValue) {
                originalSetter.call(this, newValue);
                onCheckedChange(newValue);
              },
              configurable: true,
            });
          }
        }
      }}
      checked={checked}
      onCheckedChange={onCheckedChange}
      {...attributes}
    />
  );
};

export default Toggle;
