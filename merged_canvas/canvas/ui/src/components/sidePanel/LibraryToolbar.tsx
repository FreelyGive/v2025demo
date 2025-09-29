import { useEffect, useState } from 'react';
import parse from 'html-react-parser';
import { Form } from 'radix-ui';
import FolderIcon from '@assets/icons/folder.svg?react';
import {
  ChevronDownIcon,
  MagnifyingGlassIcon,
  PlusIcon,
} from '@radix-ui/react-icons';
import {
  Box,
  Button,
  DropdownMenu,
  Flex,
  Text,
  TextField,
} from '@radix-ui/themes';

import Dialog, { DialogFieldLabel } from '@/components/Dialog';
import PermissionCheck from '@/components/PermissionCheck';
import AddCodeComponentButton from '@/features/code-editor/AddCodeComponentButton';
import { extractErrorMessageFromApiResponse } from '@/features/error-handling/error-handling';
import { validateFolderNameClientSide } from '@/features/validation/validation';
import { useCreateFolderMutation } from '@/services/componentAndLayout';
import { getCanvasSettings } from '@/utils/drupal-globals';

import type { FormEvent } from 'react';

type FolderType = 'component' | 'pattern' | 'js_component';

interface ManageLibraryToolbarProps {
  type: FolderType;
  searchTerm: string;
  onSearch: (term: string) => void;
  showNewMenu?: boolean;
}

const LibraryToolbar = ({
  type,
  searchTerm,
  onSearch,
  showNewMenu,
}: ManageLibraryToolbarProps) => {
  const [isOpen, setIsOpen] = useState(false);
  const [folderName, setFolderName] = useState('');
  const [validationError, setValidationError] = useState('');
  const [createFolder, { reset, isSuccess, isError, error, isLoading }] =
    useCreateFolderMutation();
  const canvasSettings = getCanvasSettings();

  const handleCreateFolder = async () => {
    await createFolder({
      name: folderName,
      type: type,
    });
  };

  useEffect(() => {
    if (isError) {
      console.error('Failed to add folder:', error);
    }
  }, [isError, error]);

  useEffect(() => {
    if (isSuccess) {
      setFolderName('');
      setIsOpen(false);
      reset();
    }
  }, [isSuccess, reset]);

  const handleOnChange = (newName: string) => {
    setFolderName(newName);
    setValidationError(
      newName.trim() ? validateFolderNameClientSide(newName) : '',
    );
  };

  return (
    <Flex direction="row" gap="2" mb="4">
      <form
        style={{
          flexGrow: '1',
        }}
        onSubmit={(event: FormEvent<HTMLFormElement>) => {
          event.preventDefault();
        }}
      >
        <TextField.Root
          autoComplete="off"
          id="canvas-navigation-search"
          placeholder="Search…"
          radius="medium"
          aria-label="Search content"
          size="1"
          value={searchTerm}
          onChange={(e) => onSearch(e.target.value)}
        >
          <TextField.Slot>
            <MagnifyingGlassIcon height="16" width="16" />
          </TextField.Slot>
        </TextField.Root>
      </form>
      {showNewMenu && (
        <DropdownMenu.Root>
          <DropdownMenu.Trigger>
            <Button
              variant="soft"
              data-testid="canvas-page-list-new-button"
              size="1"
            >
              <PlusIcon />
              New
              <ChevronDownIcon />
            </Button>
          </DropdownMenu.Trigger>
          <DropdownMenu.Content>
            <PermissionCheck hasPermission="codeComponents">
              <AddCodeComponentButton />
            </PermissionCheck>
            {canvasSettings.devMode && (
              <DropdownMenu.Item
                onClick={() => setIsOpen(true)}
                data-testid="canvas-library-new-folder-button"
              >
                <FolderIcon />
                Folder
              </DropdownMenu.Item>
            )}
          </DropdownMenu.Content>
        </DropdownMenu.Root>
      )}
      {isOpen && canvasSettings.devMode && (
        <Dialog
          open={isOpen}
          title="Add new folder"
          onOpenChange={(open) => setIsOpen(open)}
          error={
            isError
              ? {
                  title: 'Failed to add new folder',
                  message: parse(extractErrorMessageFromApiResponse(error)),
                  resetButtonText: 'Try again',
                  onReset: handleCreateFolder,
                }
              : undefined
          }
          footer={{
            cancelText: 'Cancel',
            confirmText: 'Add',
            onConfirm: handleCreateFolder,
            isConfirmDisabled: !folderName.trim() || !!validationError,
            isConfirmLoading: isLoading,
          }}
        >
          <Box pb="3" m="0" data-testid="xb-manage-library-add-folder-content">
            {isOpen && (
              <Form.Root
                onSubmit={(e) => {
                  e.preventDefault();
                  if (folderName.trim() && !validationError) {
                    handleCreateFolder();
                  }
                }}
                id="add-new-folder-in-tab-form"
              >
                <Form.Field name="folder-name">
                  <DialogFieldLabel htmlFor="folder-name">
                    Folder name
                  </DialogFieldLabel>
                  <TextField.Root
                    data-testid="canvas-manage-library-new-folder-name"
                    id="folder-name"
                    variant="surface"
                    onChange={(e) => handleOnChange(e.target.value)}
                    value={folderName}
                    size="1"
                  />
                  {validationError && (
                    <Text size="1" color="red" weight="medium">
                      {validationError}
                    </Text>
                  )}
                </Form.Field>
              </Form.Root>
            )}
          </Box>
        </Dialog>
      )}
    </Flex>
  );
};

export default LibraryToolbar;
