import { javascript } from '@codemirror/lang-javascript';
import { Flex } from '@radix-ui/themes';
import CodeMirror from '@uiw/react-codemirror';

import { useAppDispatch } from '@/app/hooks';
import { updateSlot } from '@/features/code-editor/codeEditorSlice';
import {
  Divider,
  FormElement,
  Label,
} from '@/features/code-editor/component-data/FormElement';

import type { CodeComponentSlot } from '@/types/CodeComponent';

import styles from './FormPropTypeSlot.module.css';

export default function FormPropTypeSlot({
  id,
  example,
  isDisabled = false,
}: Pick<CodeComponentSlot, 'id' | 'example'> & { isDisabled?: boolean }) {
  const dispatch = useAppDispatch();

  return (
    <Flex direction="column" gap="4" flexGrow="1">
      <Divider />
      <FormElement>
        <Label>Example HTML/JSX value</Label>
        <div className={styles.editorWrapper}>
          <CodeMirror
            data-testid={`slot-example-${id}`}
            value={example}
            height="100px"
            className={styles.codeMirror}
            extensions={[javascript({ jsx: true })]}
            onChange={(value) =>
              dispatch(
                updateSlot({
                  id,
                  updates: { example: value },
                }),
              )
            }
            editable={!isDisabled}
          />
        </div>
      </FormElement>
    </Flex>
  );
}
