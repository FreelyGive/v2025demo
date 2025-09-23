import { useEffect, useMemo, useState } from 'react';
import { PlusIcon, TrashIcon } from '@radix-ui/react-icons';
import { Box, Button, Flex, Select, Text, TextField } from '@radix-ui/themes';

import { useAppDispatch } from '@/app/hooks';
import { updateProp } from '@/features/code-editor/codeEditorSlice';
import {
  Divider,
  FormElement,
  Label,
} from '@/features/code-editor/component-data/FormElement';

import type { CodeComponentProp } from '@/types/CodeComponent';

const NONE_VALUE = '_none_';

const validateValue = (value: string): boolean => {
  return value !== '';
};

export default function FormPropTypeEnum({
  id,
  enum: enumValues = [],
  example: defaultValue,
  required,
  type,
  isDisabled = false,
}: Pick<CodeComponentProp, 'id' | 'enum'> & {
  example: string;
  required: boolean;
  type: 'string' | 'integer' | 'number';
  isDisabled: boolean;
}) {
  const dispatch = useAppDispatch();
  const [localRequired, setLocalRequired] = useState(required);

  const validEnumValues = useMemo(() => {
    return enumValues.filter((value) => validateValue(value));
  }, [enumValues]);

  useEffect(() => {
    // Whether the prop is required is tracked in a local state, so we can update
    // the default value when it changes.
    setLocalRequired(required);

    // Update the default value when:
    // 1. Required status has changed (required !== localRequired);
    // 2. Prop is becoming required (required === true);
    // 3. We have valid values;
    // 4. No default value is set.
    if (
      required !== localRequired &&
      required &&
      validEnumValues.length > 0 &&
      !defaultValue
    ) {
      dispatch(
        updateProp({
          id,
          updates: { example: validEnumValues[0] },
        }),
      );
    }
  }, [defaultValue, dispatch, id, localRequired, required, validEnumValues]);

  const handleDefaultValueChange = (value: string) => {
    dispatch(
      updateProp({
        id,
        updates: { example: value === NONE_VALUE ? '' : value },
      }),
    );
  };

  return (
    <Flex direction="column" gap="4" flexGrow="1">
      <FormElement>
        <Label htmlFor={`prop-enum-values-${id}`}>Values</Label>
        <EnumValuesForm
          propId={id}
          values={enumValues || []}
          type={type}
          isDisabled={isDisabled}
          onChange={(values) => {
            dispatch(
              updateProp({
                id,
                updates: { enum: values },
              }),
            );

            const validNewValues = (values || []).filter((val: string) =>
              validateValue(val),
            );

            // Update default value if:
            // 1. Current default value doesn't exist in new values, OR
            // 2. Current default value is empty, prop is required, and there are valid values.
            if (
              !validNewValues.includes(defaultValue as string) ||
              (!defaultValue && validNewValues.length > 0)
            ) {
              if (required && validNewValues.length > 0) {
                handleDefaultValueChange(validNewValues[0]);
              } else {
                handleDefaultValueChange(NONE_VALUE);
              }
            }
          }}
        />
      </FormElement>
      {validEnumValues.length > 0 && (
        <>
          <Divider />
          <FormElement>
            <Label htmlFor={`prop-enum-default-${id}`}>Default value</Label>
            <Select.Root
              value={defaultValue === '' ? NONE_VALUE : defaultValue}
              onValueChange={handleDefaultValueChange}
              size="1"
              disabled={isDisabled}
            >
              <Select.Trigger id={`prop-enum-default-${id}`} />
              <Select.Content>
                {!required && (
                  <Select.Item value={NONE_VALUE}>- None -</Select.Item>
                )}
                {validEnumValues.map((value) => (
                  <Select.Item key={value} value={value}>
                    {value}
                  </Select.Item>
                ))}
              </Select.Content>
            </Select.Root>
          </FormElement>
        </>
      )}
    </Flex>
  );
}

function EnumValuesForm({
  propId,
  values = [],
  onChange,
  type,
  isDisabled,
}: {
  propId: string;
  values: CodeComponentProp['enum'];
  onChange: (values: CodeComponentProp['enum']) => void;
  type: 'string' | 'integer' | 'number';
  isDisabled: boolean;
}) {
  const handleAdd = () => {
    onChange([...values, '']);
  };

  const handleRemove = (index: number) => {
    const newValues = [...values];
    newValues.splice(index, 1);
    onChange(newValues);
  };

  const handleChange = (index: number, value: string) => {
    const newValues = [...values];
    newValues[index] = value;
    onChange(newValues);
  };

  return (
    <Flex mt="1" direction="column" gap="2">
      {values.map((value, index) => (
        <Flex key={index} gap="2" align="end">
          <Box flexGrow="1">
            <FormElement>
              <TextField.Root
                autoComplete="off"
                data-testid={`canvas-prop-enum-value-${propId}-${index}`}
                type={['integer', 'number'].includes(type) ? 'number' : 'text'}
                step={type === 'integer' ? 1 : undefined}
                value={value}
                size="1"
                onChange={(e) => handleChange(index, e.target.value)}
                placeholder={
                  {
                    string: 'Enter a text value',
                    integer: 'Enter an integer',
                    number: 'Enter a number',
                  }[type]
                }
                disabled={isDisabled}
              />
            </FormElement>
          </Box>
          <Button
            data-testid={`canvas-prop-enum-value-delete-${propId}-${index}`}
            size="1"
            color="red"
            variant="soft"
            onClick={() => handleRemove(index)}
            disabled={isDisabled}
          >
            <TrashIcon />
          </Button>
        </Flex>
      ))}
      <Button size="1" variant="soft" onClick={handleAdd} disabled={isDisabled}>
        <Flex gap="1" align="center">
          <PlusIcon />
          <Text size="1">Add value</Text>
        </Flex>
      </Button>
    </Flex>
  );
}
