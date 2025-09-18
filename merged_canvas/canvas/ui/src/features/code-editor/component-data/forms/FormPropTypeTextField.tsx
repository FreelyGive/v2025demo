import { Flex, TextField } from '@radix-ui/themes';

import { useAppDispatch } from '@/app/hooks';
import { updateProp } from '@/features/code-editor/codeEditorSlice';
import {
  Divider,
  FormElement,
  Label,
} from '@/features/code-editor/component-data/FormElement';

import type { CodeComponentProp } from '@/types/CodeComponent';

export default function FormPropTypeTextField({
  id,
  example,
  type = 'string',
  isDisabled = false,
}: Pick<CodeComponentProp, 'id'> & {
  example: string;
  type?: 'string' | 'integer' | 'number';
  isDisabled?: boolean;
}) {
  const dispatch = useAppDispatch();

  return (
    <Flex direction="column" gap="4" flexGrow="1">
      <Divider />
      <FormElement>
        <Label htmlFor={`prop-example-${id}`}>Example value</Label>
        <TextField.Root
          autoComplete="off"
          id={`prop-example-${id}`}
          type={['integer', 'number'].includes(type) ? 'number' : 'text'}
          step={type === 'integer' ? 1 : undefined}
          placeholder={
            {
              string: 'Enter a text value',
              integer: 'Enter an integer',
              number: 'Enter a number',
            }[type]
          }
          value={example}
          size="1"
          onChange={(e) =>
            dispatch(
              updateProp({
                id,
                updates: { example: e.target.value },
              }),
            )
          }
          disabled={isDisabled}
        />
      </FormElement>
    </Flex>
  );
}
