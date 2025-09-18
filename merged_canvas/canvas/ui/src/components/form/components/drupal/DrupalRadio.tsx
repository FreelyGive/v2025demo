import { createContext, useContext, useRef, useState } from 'react';

import { RadioGroup, RadioItem } from '@/components/form/components/Radio';
import InputBehaviors from '@/components/form/inputBehaviors';
import { a2p } from '@/local_packages/utils.js';

import type { Attributes } from '@/types/DrupalAttribute';

interface RadioContextType {
  selected: string | number | null;
  updateSelected: (value: string | number | undefined) => void;
}

interface DrupalElement {
  '#default_value'?: string | number;
  [key: string]: any;
}

const RadioContext = createContext<RadioContextType>({
  selected: null,
  updateSelected: () => {},
});
const DrupalRadioGroup = ({
  attributes = {},
  renderChildren,
  element = {},
}: {
  attributes?: Attributes;
  renderChildren?: React.ReactNode;
  element?: DrupalElement;
}) => {
  const [selected, setSelected] = useState<string | number | null>(
    element['#default_value'] ?? null,
  );

  // Callback provided to each radio item in the group, which will update the
  // selected value that is kept track of within this component.
  const updateSelected = (value: string | number | undefined) => {
    const syntheticEvent = {
      target: {
        value,
        name: attributes['data-canvas-name'],
      },
    } as unknown as React.ChangeEvent<HTMLInputElement>;

    if (typeof attributes.onChange === 'function') {
      attributes.onChange(syntheticEvent);
    }
    setSelected(value ?? null);
  };

  return (
    // Wrap the radios group in a context that keeps track of the selected value.
    <RadioContext.Provider value={{ selected, updateSelected }}>
      <RadioGroup
        attributes={a2p(attributes, {}, { skipAttributes: ['onChange'] })}
      >
        {renderChildren}
      </RadioGroup>
    </RadioContext.Provider>
  );
};

const DrupalRadioItem = ({ attributes = {} }: { attributes?: Attributes }) => {
  // Somewhere in the hyperscriptify process, a value of 0 *or* '0' is
  // being converted into an empty string. This is a quick fix to get this
  // working, and the underlying cause can be addressed later as it has proven
  // to not be a quick fix.
  if (attributes.value === '') {
    attributes.value = '0';
  }

  const { selected, updateSelected } = useContext(RadioContext);

  // Store the initial value in a ref to ensure it does not change. The parent
  // `DrupalRadioGroup` will handle value changes, and these radio items
  // represent an available option.
  const valueRef = useRef<string | number | undefined>(
    typeof attributes.value === 'string' || typeof attributes.value === 'number'
      ? attributes.value
      : undefined,
  );

  return (
    <RadioItem
      attributes={{
        ...attributes,
        value: `${valueRef.current}`,
        checked: `${selected}` === `${valueRef.current}`,
        // The checked attribute does not reliably appear in the DOM despite
        // the element itself having the checked property as `true` and the
        // input rendering as checked. This has no functional impact outside
        // of tests that are looking to confirm the input is set as checked, so
        // we add this attribute to make the checked status known via DOM query.
        'data-drupal-canvas-checked': `${selected}` === `${valueRef.current}`,
      }}
      onChange={() => updateSelected(valueRef.current)}
    />
  );
};

const WrappedDrupalRadioGroup = InputBehaviors(DrupalRadioGroup);

export { WrappedDrupalRadioGroup as DrupalRadioGroup, DrupalRadioItem };
