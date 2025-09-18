import { Accordion } from 'radix-ui';
import { JSONTree } from 'react-json-tree';
import { ChevronDownIcon, InfoCircledIcon } from '@radix-ui/react-icons';
import { Box, Callout } from '@radix-ui/themes';

import { useAppSelector } from '@/app/hooks';
import { selectCodeComponentProperty } from '@/features/code-editor/codeEditorSlice';

import styles from './DataFetch.module.css';

const theme = {
  scheme: 'github-light-custom',
  author: 'canvas',
  base00: '#ffffff',
  base01: '#f6f8fa',
  base02: '#e1e4e8',
  base03: '#d1d5da',
  base04: '#6a737d',
  base05: '#24292e',
  base06: '#6e7781',
  base07: '#116329',
  base08: '#d73a49',
  base09: '#e36209',
  base0A: '#005cc5',
  base0B: '#6f42c1',
  base0C: '#22863a',
  base0D: '#0366d6',
  base0E: '#032f62',
  base0F: '#b31d28',
};

const DataFetch = () => {
  const fetchedData = useAppSelector(
    selectCodeComponentProperty('dataFetches'),
  );

  return (
    <>
      {Object.keys(fetchedData).length === 0 && (
        <Box flexGrow="1" pt="4" maxWidth="500px" mx="auto">
          <Callout.Root size="1" variant="surface" color="gray">
            <Callout.Icon>
              <InfoCircledIcon />
            </Callout.Icon>
            <Callout.Text>
              Results from <code>useSWR()</code> and data fetching with
              <code>'@/lib/drupal-utils'</code> functions will be shown here.
            </Callout.Text>
          </Callout.Root>
        </Box>
      )}

      <Accordion.Root
        type="multiple"
        defaultValue={Object.keys(fetchedData).map(
          (id, index) => `item-${index}`,
        )}
      >
        {Object.keys(fetchedData).map((id, index) => (
          <Accordion.Item key={id} value={`item-${index}`}>
            <Accordion.Trigger className={styles.accordionTrigger}>
              <Box mt="4">
                <Callout.Root color="gray" size="1">
                  <Callout.Text size="1" weight="medium">
                    <ChevronDownIcon
                      className={styles.accordionChevron}
                      aria-hidden
                    />
                    &nbsp;
                    {id}
                  </Callout.Text>
                </Callout.Root>
              </Box>
            </Accordion.Trigger>
            <Accordion.Content>
              <Box mt="4" mb="8">
                {fetchedData[id].error ? (
                  <Callout.Root color="red">
                    <Callout.Icon>
                      <InfoCircledIcon />
                    </Callout.Icon>
                    <Callout.Text>
                      {fetchedData[id].data?.message
                        ? fetchedData[id].data.message
                        : 'error'}
                    </Callout.Text>
                    <Box p="2" className={styles.errorWrapper}>
                      <JSONTree
                        data={fetchedData[id].data}
                        theme={{
                          extend: theme,
                          tree: {
                            fontSize: 'var(--font-size-1)',
                          },
                        }}
                        invertTheme={false}
                        hideRoot={true}
                      />
                    </Box>
                  </Callout.Root>
                ) : (
                  <JSONTree
                    data={fetchedData[id].data}
                    theme={{
                      extend: theme,
                      tree: {
                        fontSize: 'var(--font-size-1)',
                      },
                    }}
                    invertTheme={false}
                    hideRoot={true}
                  />
                )}
              </Box>
            </Accordion.Content>
          </Accordion.Item>
        ))}
      </Accordion.Root>
    </>
  );
};

export default DataFetch;
