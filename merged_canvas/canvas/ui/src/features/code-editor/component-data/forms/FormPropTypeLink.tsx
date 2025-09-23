/* eslint-disable */
// @ts-nocheck
import { useState } from 'react';
import clsx from 'clsx';
import { Box, Flex, Select, Text, TextField } from '@radix-ui/themes';

import { useAppDispatch } from '@/app/hooks';
import { jsonSchemaValidate } from '@/components/form/formUtil';
import { updateProp } from '@/features/code-editor/codeEditorSlice';
import {
  Divider,
  FormElement,
  Label,
} from '@/features/code-editor/component-data/FormElement';

import type { CodeComponentProp } from '@/types/CodeComponent';

import styles from '@/features/code-editor/component-data/FormElement.module.css';

const BASE_URL = window.location.origin;

export default function FormPropTypeLink({
  id,
  example,
  isDisabled = false,
}: Pick<CodeComponentProp, 'id'> & {
  example: string;
  isDisabled?: boolean;
}) {
  const dispatch = useAppDispatch();
  const [linkType, setLinkType] = useState<'relative' | 'full'>('relative');
  const [isExampleValueValid, setIsExampleValueValid] = useState(true);

  return (
    <Flex direction="column" gap="4" flexGrow="1">
      <FormElement>
        <Label htmlFor={`prop-link-type-${id}`}>Link type</Label>
        <Select.Root
          value={linkType}
          onValueChange={(value: 'relative' | 'full') => {
            setIsExampleValueValid(true);
            setLinkType(value);
          }}
          size="1"
          disabled={isDisabled}
        >
          <Select.Trigger id={`prop-link-type-${id}`} />
          <Select.Content>
            <Select.Item value="relative">Relative path</Select.Item>
            <Select.Item value="full">Full URL</Select.Item>
          </Select.Content>
        </Select.Root>
      </FormElement>
      <Divider />
      <FormElement>
        <Label htmlFor={`prop-example-${id}`}>Example value</Label>
        <Flex align="center" gap="1" width="100%">
          {linkType === 'relative' && (
            <Flex flexShrink="0" align="center">
              <Text size="1" color="gray">
                {BASE_URL}/
              </Text>
            </Flex>
          )}
          <Box flexGrow="1">
            <TextField.Root
              autoComplete="off"
              id={`prop-example-${id}`}
              type="text"
              placeholder={
                linkType === 'relative' ? 'Enter a path' : 'Enter a URL'
              }
              value={example}
              size="1"
              onChange={(e) => {
                const input = e.target;
                setIsExampleValueValid(true); // Reset validation state on change
                dispatch(
                  updateProp({
                    id,
                    updates: {
                      example: input.value,
                      format: linkType === 'full' ? 'uri' : 'uri-reference',
                    },
                  }),
                );
              }}
              onBlur={(e) => {
                if (e.target.value === '') {
                  setIsExampleValueValid(true);
                  return;
                }
                const [isValidValue, validate] = jsonSchemaValidate(
                  e.target.value,
                  {
                    type: 'string',
                    format: linkType === 'full' ? 'uri' : 'uri-reference',
                  },
                );
                setIsExampleValueValid(isValidValue);
              }}
              disabled={isDisabled}
              className={clsx({
                [styles.error]: !isExampleValueValid,
              })}
              {...(!isExampleValueValid
                ? { 'data-invalid-prop-value': true }
                : {})}
            />
          </Box>
        </Flex>
      </FormElement>
    </Flex>
  );
}
