import { useState } from 'react';
import { Form } from 'radix-ui';
import { PlusIcon } from '@radix-ui/react-icons';
import {
  Box,
  Button,
  Flex,
  Popover,
  Tabs,
  Text,
  TextField,
} from '@radix-ui/themes';

import ComponentList from '@/components/list/ComponentList';
import PatternList from '@/components/list/PatternList';
import PermissionCheck from '@/components/PermissionCheck';
import { DisplayContext } from '@/components/sidePanel/DisplayContext';
import CodeComponentList from '@/features/code-editor/CodeComponentList';
import { useCreateFolderMutation } from '@/services/componentAndLayout';

import styles from '@/components/sidePanel/ManageLibrary.module.css';

const ManageLibrary = () => {
  return (
    <DisplayContext.Provider value="manage-library">
      <div className="flex flex-col h-full">
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
              <AddFolderButton type="component" />
              <ComponentList />
            </Tabs.Content>
            <Tabs.Content
              value={'patterns'}
              className={styles.tabContent}
              data-testid="canvas-manage-library-patterns-tab-content"
            >
              <AddFolderButton type="pattern" />
              <PatternList />
            </Tabs.Content>
            <PermissionCheck hasPermission="codeComponents">
              <Tabs.Content
                value={'code'}
                className={styles.tabContent}
                data-testid="canvas-manage-library-code-tab-content"
              >
                <AddFolderButton type="js_component" />
                <CodeComponentList />
              </Tabs.Content>
            </PermissionCheck>
          </Flex>
        </Tabs.Root>
      </div>
    </DisplayContext.Provider>
  );
};

type FolderType = 'component' | 'pattern' | 'js_component';

const AddFolderButton = ({ type }: { type: FolderType }) => {
  const [isOpen, setIsOpen] = useState(false);
  const [folderName, setFolderName] = useState('');
  const [createFolder, { reset }] = useCreateFolderMutation();

  const handleCreateFolder = async (e: React.FormEvent<HTMLFormElement>) => {
    e.preventDefault();
    await createFolder({
      name: folderName,
      type: type,
    });
    setFolderName('');
    setIsOpen(false);
    reset();
  };

  return (
    <Flex className={styles.tabContent}>
      <Popover.Root open={isOpen} onOpenChange={setIsOpen}>
        <Popover.Trigger>
          <Button
            data-testid="add-new-folder-button"
            className={styles.addFolderButton}
            my="2"
            variant="soft"
            size="1"
          >
            <PlusIcon />
            Add new folder
          </Button>
        </Popover.Trigger>
        <Popover.Content data-testid="canvas-manage-library-add-folder-content">
          <Box py="3" px="2" m="0">
            {isOpen && (
              <Form.Root
                onSubmit={handleCreateFolder}
                id="add-new-folder-in-tab-form"
              >
                <Form.Field name="folder-name">
                  <Form.Label htmlFor="folder-name">
                    <Text weight="medium" size="1">
                      Folder name
                    </Text>
                  </Form.Label>
                  <TextField.Root
                    data-testid="canvas-manage-library-new-folder-name"
                    id="folder-name"
                    variant="surface"
                    onChange={(e) => setFolderName(e.target.value)}
                    value={folderName}
                    size="1"
                  />
                </Form.Field>
                <Form.Submit asChild>
                  <Button
                    data-testid="canvas-manage-library-new-folder-name-submit"
                    variant="solid"
                    size="1"
                    mt="2"
                    disabled={folderName.length === 0}
                  >
                    Add folder
                  </Button>
                </Form.Submit>
              </Form.Root>
            )}
          </Box>
        </Popover.Content>
      </Popover.Root>
    </Flex>
  );
};

export default ManageLibrary;
