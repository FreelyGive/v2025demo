import { useState } from 'react';
import { Flex, Tabs } from '@radix-ui/themes';

import { useAppDispatch, useAppSelector } from '@/app/hooks';
import ComponentList from '@/components/list/ComponentList';
import PatternList from '@/components/list/PatternList';
import PermissionCheck from '@/components/PermissionCheck';
import LibraryToolbar from '@/components/sidePanel/LibraryToolbar';
import CodeComponentList from '@/features/code-editor/CodeComponentList';
import {
  selectManageLibraryTab,
  setManageLibraryTab,
} from '@/features/ui/primaryPanelSlice';

import styles from '@/components/sidePanel/ManageLibrary.module.css';

const ManageLibrary = () => {
  const [searchTerm, setSearchTerm] = useState('');
  const dispatch = useAppDispatch();
  const selectedTab = useAppSelector(selectManageLibraryTab);

  return (
    <div className="flex flex-col h-full">
      <Tabs.Root
        value={selectedTab || 'components'}
        onValueChange={(value) => dispatch(setManageLibraryTab(value))}
      >
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
          <PermissionCheck hasPermission="codeComponents">
            <Tabs.Trigger
              value="code"
              data-testid="canvas-manage-library-code-tab-select"
            >
              Code
            </Tabs.Trigger>
          </PermissionCheck>
        </Tabs.List>
        <Flex py="2" className={styles.tabWrapper}>
          <Tabs.Content
            value={'components'}
            className={styles.tabContent}
            data-testid="canvas-manage-library-components-tab-content"
          >
            <LibraryToolbar
              type="component"
              searchTerm={searchTerm}
              onSearch={setSearchTerm}
              showNewMenu={true}
            />
            <ComponentList searchTerm={searchTerm} />
          </Tabs.Content>
          <Tabs.Content
            value={'patterns'}
            className={styles.tabContent}
            data-testid="canvas-manage-library-patterns-tab-content"
          >
            <LibraryToolbar
              type="pattern"
              searchTerm={searchTerm}
              onSearch={setSearchTerm}
              showNewMenu={true}
            />
            <PatternList searchTerm={searchTerm} />
          </Tabs.Content>
          <PermissionCheck hasPermission="codeComponents">
            <Tabs.Content
              value={'code'}
              className={styles.tabContent}
              data-testid="canvas-manage-library-code-tab-content"
            >
              <LibraryToolbar
                type="js_component"
                searchTerm={searchTerm}
                onSearch={setSearchTerm}
                showNewMenu={true}
              />
              <CodeComponentList searchTerm={searchTerm} />
            </Tabs.Content>
          </PermissionCheck>
        </Flex>
      </Tabs.Root>
    </div>
  );
};

export default ManageLibrary;
