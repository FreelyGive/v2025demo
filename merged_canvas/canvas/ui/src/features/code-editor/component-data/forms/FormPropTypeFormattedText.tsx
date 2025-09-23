import { Flex, TextArea } from '@radix-ui/themes';

import { useAppDispatch } from '@/app/hooks';
import { updateProp } from '@/features/code-editor/codeEditorSlice';
import {
  Divider,
  FormElement,
  Label,
} from '@/features/code-editor/component-data/FormElement';

import type { CodeComponentProp } from '@/types/CodeComponent';

export default function FormPropTypeFormattedText({
  id,
  example,
  isDisabled = false,
}: Pick<CodeComponentProp, 'id' | 'example'> & {
  isDisabled?: boolean;
}) {
  const dispatch = useAppDispatch();

  return (
    <Flex direction="column" gap="4" flexGrow="1">
      <Divider />
      <FormElement>
        <Label htmlFor={`prop-example-${id}`}>Example value</Label>
        <TextArea
          id={`prop-example-${id}`}
          placeholder="Enter a text value"
          value={example as string}
          size="1"
          onChange={(e) =>
            dispatch(
              updateProp({
                id,
                updates: {
                  example: e.target.value,
                },
              }),
            )
          }
          disabled={isDisabled}
        />
      </FormElement>
    </Flex>
  );
}
