import { Flex, Tabs } from '@radix-ui/themes';

import ErrorBoundary from '@/components/error/ErrorBoundary';
import ComponentList from '@/components/list/ComponentList';
import PatternList from '@/components/list/PatternList';
import PermissionCheck from '@/components/PermissionCheck';
import AddCodeComponentButton from '@/features/code-editor/AddCodeComponentButton';

import styles from './Library.module.css';

const Library = () => {
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
            <PermissionCheck hasPermission="codeComponents">
              <Flex direction="column">
                <AddCodeComponentButton />
              </Flex>
            </PermissionCheck>
            <ErrorBoundary title="An unexpected error has occurred while fetching components.">
              <ComponentList />
            </ErrorBoundary>
          </Tabs.Content>
          <Tabs.Content
            value={'patterns'}
            className={styles.tabContent}
            data-testid="canvas-manage-library-patterns-tab-content"
          >
            <ErrorBoundary title="An unexpected error has occurred while fetching patterns.">
              <PatternList />
            </ErrorBoundary>
          </Tabs.Content>
        </Flex>
      </Tabs.Root>
    </>
  );
};

export default Library;
