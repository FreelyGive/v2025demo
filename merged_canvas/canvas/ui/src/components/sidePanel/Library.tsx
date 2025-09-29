import { useState } from 'react';
import { Flex, Tabs } from '@radix-ui/themes';

import ErrorBoundary from '@/components/error/ErrorBoundary';
import ComponentList from '@/components/list/ComponentList';
import PatternList from '@/components/list/PatternList';
import LibraryToolbar from '@/components/sidePanel/LibraryToolbar';
import useDebounce from '@/hooks/useDebounce';

import styles from './Library.module.css';

const Library = () => {
  const [searchTerm, setSearchTerm] = useState('');
  const debouncedSearchTerm = useDebounce(searchTerm, 300);
  return (
    <>
      <Tabs.Root defaultValue="components">
        <Tabs.List justify="start" mt="-2" size="1">
          <Tabs.Trigger
            value="components"
            data-testid="canvas-manage-library-components-tab-select"
          >
            Components
          </Tabs.Trigger>
          <Tabs.Trigger
            value="patterns"
            data-testid="canvas-manage-library-patterns-tab-select"
          >
            Patterns
          </Tabs.Trigger>
        </Tabs.List>
        <Flex py="2" className={styles.tabWrapper}>
          <Tabs.Content
            value={'components'}
            className={styles.tabContent}
            data-testid="canvas-manage-library-components-tab-content"
          >
            <ErrorBoundary title="An unexpected error has occurred while fetching components.">
              <LibraryToolbar
                type={'component'}
                searchTerm={searchTerm}
                onSearch={setSearchTerm}
              />
              <ComponentList searchTerm={debouncedSearchTerm} />
            </ErrorBoundary>
          </Tabs.Content>
          <Tabs.Content
            value={'patterns'}
            className={styles.tabContent}
            data-testid="canvas-manage-library-patterns-tab-content"
          >
            <ErrorBoundary title="An unexpected error has occurred while fetching patterns.">
              <LibraryToolbar
                type={'pattern'}
                searchTerm={searchTerm}
                onSearch={setSearchTerm}
              />
              <PatternList searchTerm={debouncedSearchTerm} />
            </ErrorBoundary>
          </Tabs.Content>
        </Flex>
      </Tabs.Root>
    </>
  );
};

export default Library;
