/* cspell:ignore Insertable */
import { useEffect, useMemo, useState } from 'react';
import { useErrorBoundary } from 'react-error-boundary';
import {
  CheckIcon,
  InfoCircledIcon,
  PinBottomIcon,
} from '@radix-ui/react-icons';
import {
  Box,
  Callout,
  Code,
  Flex,
  IconButton,
  Select,
  Tooltip,
} from '@radix-ui/themes';

import { useAppDispatch, useAppSelector } from '@/app/hooks';
import {
  selectCodeComponentProperty,
  setCodeComponentProperty,
} from '@/features/code-editor/codeEditorSlice';
import {
  FormElement,
  Label,
} from '@/features/code-editor/component-data/FormElement';
import { formatToValidImportName } from '@/features/code-editor/utils';
import { useGetCodeComponentsQuery } from '@/services/componentAndLayout';

import styles from '@/features/code-editor/Import.module.css';

const CodeComponentImports = () => {
  const currentComponentId = useAppSelector(
    selectCodeComponentProperty('machineName'),
  );
  const { data: codeComponents, error } = useGetCodeComponentsQuery();
  const { showBoundary } = useErrorBoundary();
  const [importName, setImportName] = useState('');
  const [importSource, setImportSource] = useState('');
  const importStatement = `import ${importName} from '@/components/${importSource}'`;

  // Filter out the current component from the list
  const filteredComponents = useMemo(() => {
    if (!codeComponents || !currentComponentId) return codeComponents || {};
    // eslint-disable-next-line @typescript-eslint/no-unused-vars
    const { [currentComponentId]: _, ...rest } = codeComponents;
    return rest;
  }, [codeComponents, currentComponentId]);

  useEffect(() => {
    if (error) {
      showBoundary(error);
    }
  }, [error, showBoundary]);

  function generateImportStatement(value: string) {
    setImportSource(value);
    // Find the selected component to get its display name
    if (codeComponents) {
      const selected = Object.values(codeComponents).find(
        (component) => component.machineName === value,
      );
      if (selected?.name) {
        // Format the name for use in import statements
        setImportName(formatToValidImportName(selected.name));
      } else {
        // Fallback default name.
        setImportName('MyComponent');
      }
    }
  }

  return (
    <>
      <Box flexGrow="1" pt="4" maxWidth="500px" mx="auto">
        <Callout.Root size="1" variant="surface" color="gray">
          <Callout.Icon>
            <InfoCircledIcon />
          </Callout.Icon>
          <Callout.Text>
            Insert an import statement into your code editor.
          </Callout.Text>
        </Callout.Root>
      </Box>
      <Flex direction="column" gap="4" py="4" mx="auto" maxWidth="500px">
        <FormElement>
          <Label htmlFor="imports-list">Available code components</Label>
          <Select.Root
            size="1"
            value={importSource}
            onValueChange={generateImportStatement}
          >
            <Select.Trigger id="imports-list" placeholder="Select component" />
            <Select.Content>
              {filteredComponents &&
                Object.entries(filteredComponents).map(([id, component]) => {
                  return (
                    <Select.Item key={id} value={component.machineName}>
                      {component.name}
                    </Select.Item>
                  );
                })}
            </Select.Content>
          </Select.Root>
        </FormElement>
        {importSource && (
          <InsertableCodeBlock>{importStatement}</InsertableCodeBlock>
        )}
      </Flex>
    </>
  );
};

const InsertableCodeBlock = ({ children }: { children: React.ReactNode }) => {
  const [inserted, setInserted] = useState(false);
  const dispatch = useAppDispatch();
  const sourceCodeJs = useAppSelector(
    selectCodeComponentProperty('sourceCodeJs'),
  );
  const iconResetDelay = 1500;

  /**
   * Inserts an import statement at the correct location.
   */
  const handleInsert = async () => {
    const importStatement = children as string;
    if (!sourceCodeJs.includes(importStatement)) {
      const isEmpty = sourceCodeJs.trim() === '';
      if (isEmpty) {
        dispatch(
          setCodeComponentProperty(['sourceCodeJs', importStatement + '\n']),
        );
      } else {
        // Find last import statement
        const lines = sourceCodeJs.split('\n');
        const lastImportIndex = lines.findIndex((line, index) => {
          const trimmed = line.trim();
          return (
            !trimmed.startsWith('//') &&
            !trimmed.startsWith('/*') &&
            trimmed.startsWith('import') &&
            (index === lines.length - 1 ||
              !lines[index + 1].trim().startsWith('import'))
          );
        });
        if (lastImportIndex >= 0) {
          // Insert after existing imports
          lines.splice(lastImportIndex + 1, 0, importStatement);
          dispatch(
            setCodeComponentProperty(['sourceCodeJs', lines.join('\n')]),
          );
        } else {
          // Add at beginning with an extra line between the import and the component.
          dispatch(
            setCodeComponentProperty([
              'sourceCodeJs',
              `${importStatement}\n\n${sourceCodeJs}`,
            ]),
          );
        }
      }
    }
    setInserted(true);
    setTimeout(() => setInserted(false), iconResetDelay);
  };

  return (
    <Flex>
      <Code variant="outline" className={styles.code}>
        <Flex align="center" justify="between">
          {children}
          <Tooltip content="Insert">
            <IconButton
              onClick={handleInsert}
              aria-label="Insert code"
              variant="soft"
              data-testid="canvas-insert-import-button"
              size="1"
            >
              {inserted ? <CheckIcon /> : <PinBottomIcon />}
            </IconButton>
          </Tooltip>
        </Flex>
      </Code>
    </Flex>
  );
};

export default CodeComponentImports;
