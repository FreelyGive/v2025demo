import { useEffect } from 'react';
import {
  ChevronDownIcon,
  FileTextIcon,
  InfoCircledIcon,
  MagnifyingGlassIcon,
  PlusIcon,
} from '@radix-ui/react-icons';
import {
  AlertDialog,
  Box,
  Button,
  Callout,
  ContextMenu,
  DropdownMenu,
  Flex,
  Skeleton,
  TextField,
} from '@radix-ui/themes';

import ErrorCard from '@/components/error/ErrorCard';
import SidebarNode from '@/components/sidePanel/SidebarNode';
import UnifiedMenu from '@/components/UnifiedMenu';

import type { FormEvent } from 'react';
import type { ContentStub } from '@/types/Content';

const hasPermission = (
  permission: 'edit' | 'duplicate' | 'homepage' | 'delete',
  item: ContentStub,
) => {
  const links = item.links || {};
  switch (permission) {
    case 'edit':
      return !!links['edit-form'];
    case 'duplicate':
      return !!links['https://drupal.org/project/canvas#link-rel-duplicate'];
    case 'homepage':
      return !!links[
        'https://drupal.org/project/canvas#link-rel-set-as-homepage'
      ];
    case 'delete':
      return !!links['delete-form'];
    default:
      return false;
  }
};

// Helper function to create dropdown menu content for a page item
const createPageMenuContent = (
  item: ContentStub,
  onDuplicate?: (page: ContentStub) => void,
  onSetHomepage?: (page: ContentStub) => void,
  onDelete?: (page: ContentStub) => void,
  homepagePath?: string,
) => {
  const hasDuplicate = hasPermission('duplicate', item);
  const hasHomepage =
    hasPermission('homepage', item) && item.internalPath !== homepagePath;
  const hasDelete = hasPermission('delete', item);

  // If no permissions, don't render dropdown
  if (!hasDuplicate && !hasHomepage && !hasDelete) {
    return null;
  }

  return (
    <>
      <UnifiedMenu.Label>{item.autoSaveLabel || item.title}</UnifiedMenu.Label>
      <UnifiedMenu.Separator />
      {hasDuplicate && (
        <UnifiedMenu.Item
          onClick={(event) => event.stopPropagation()}
          onSelect={onDuplicate ? () => onDuplicate(item) : undefined}
        >
          Duplicate page
        </UnifiedMenu.Item>
      )}
      {hasHomepage && (
        <>
          {hasDuplicate && <UnifiedMenu.Separator />}
          <UnifiedMenu.Item
            onClick={(event) => event.stopPropagation()}
            onSelect={onSetHomepage ? () => onSetHomepage(item) : undefined}
          >
            Set as homepage
          </UnifiedMenu.Item>
        </>
      )}
      {hasDelete && (
        <>
          {(hasDuplicate || hasHomepage) && <UnifiedMenu.Separator />}
          <AlertDialog.Root>
            <AlertDialog.Trigger>
              <UnifiedMenu.Item
                onClick={(event) => event.stopPropagation()}
                onSelect={(event) => event.preventDefault()}
                color="red"
              >
                Delete page
              </UnifiedMenu.Item>
            </AlertDialog.Trigger>
            <AlertDialog.Content>
              <AlertDialog.Title>Delete {item.title} page</AlertDialog.Title>
              <AlertDialog.Description size="2">
                This action will permanently delete the page and all of its
                contents. This action cannot be undone.
              </AlertDialog.Description>
              <Flex gap="3" mt="4" justify="end">
                <AlertDialog.Cancel>
                  <Button variant="soft" color="gray">
                    Cancel
                  </Button>
                </AlertDialog.Cancel>
                <AlertDialog.Action>
                  <Button
                    variant="solid"
                    color="red"
                    onClick={() => onDelete?.(item)}
                  >
                    Delete page
                  </Button>
                </AlertDialog.Action>
              </Flex>
            </AlertDialog.Content>
          </AlertDialog.Root>
        </>
      )}
    </>
  );
};

const ContentGroup = ({
  items,
  homepagePath,
  selectedPageId,
  onSelect,
  onDuplicate,
  onSetHomepage,
  onDelete,
}: {
  items: ContentStub[];
  homepagePath?: string;
  selectedPageId?: string | number;
  onSelect?: (value: ContentStub) => void;
  onDuplicate?: (page: ContentStub) => void;
  onSetHomepage?: (page: ContentStub) => void;
  onDelete?: (page: ContentStub) => void;
}) => {
  if (items.length === 0) {
    return (
      <Callout.Root size="1" color="gray" data-testid="canvas-page-list">
        <Callout.Icon>
          <InfoCircledIcon />
        </Callout.Icon>
        <Callout.Text>No pages found.</Callout.Text>
      </Callout.Root>
    );
  }

  return (
    <Flex data-testid="canvas-page-list" direction="column" gap="1">
      {items.map((item) => {
        const isSelected =
          selectedPageId !== undefined &&
          String(selectedPageId) === String(item.id);

        const title = `${item.autoSaveLabel || item.title} ${item.autoSavePath || item.path}`;
        const isHomepage = item.internalPath === homepagePath;
        const dropdownMenuContent = createPageMenuContent(
          item,
          onDuplicate,
          onSetHomepage,
          onDelete,
          homepagePath,
        );

        return (
          <ContextMenu.Root key={item.id}>
            <ContextMenu.Trigger>
              <SidebarNode
                title={title}
                variant={isHomepage ? 'homepage' : 'page'}
                selected={isSelected}
                dropdownMenuContent={
                  dropdownMenuContent ? (
                    <UnifiedMenu.Content menuType="dropdown">
                      {dropdownMenuContent}
                    </UnifiedMenu.Content>
                  ) : null
                }
                onClick={onSelect ? () => onSelect(item) : undefined}
                data-canvas-page-id={item.id}
              />
            </ContextMenu.Trigger>
            <UnifiedMenu.Content
              onClick={(e) => e.stopPropagation()}
              menuType="context"
              align="start"
              side="right"
            >
              {dropdownMenuContent}
            </UnifiedMenu.Content>
          </ContextMenu.Root>
        );
      })}
    </Flex>
  );
};

interface PageListProps {
  // Data
  pageItems?: ContentStub[];
  isPageItemsLoading?: boolean;
  pageItemsError?: string | null;
  homepagePath?: string;
  selectedPageId?: string | number;
  // Permissions
  canCreatePages?: boolean;
  // Event handlers
  onNewPage?: () => void;
  onDeletePage?: (item: ContentStub) => void;
  onDuplicatePage?: (item: ContentStub) => void;
  onSelectPage?: (item: ContentStub) => void;
  onSetHomepage?: (item: ContentStub) => void;
  onSearch?: (value: string) => void;
}

const PageList = ({
  pageItems = [],
  isPageItemsLoading = false,
  pageItemsError = null,
  homepagePath,
  selectedPageId,
  canCreatePages = false,
  onNewPage,
  onDeletePage,
  onDuplicatePage,
  onSelectPage,
  onSetHomepage,
  onSearch,
}: PageListProps) => {
  // Reset search when the component unmounts
  useEffect(() => {
    return () => {
      if (onSearch) {
        onSearch('');
      }
    };
  }, [onSearch]);

  return (
    <div data-testid="canvas-page-list-panel">
      <Flex direction="row" gap="2" mb="4">
        <form
          style={{ flexGrow: 1 }}
          onChange={(event: FormEvent<HTMLFormElement>) => {
            event.preventDefault();
            const form = event.currentTarget;
            const formElements = form.elements as typeof form.elements & {
              'canvas-navigation-search': HTMLInputElement;
            };
            onSearch?.(formElements['canvas-navigation-search'].value);
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
          >
            <TextField.Slot>
              <MagnifyingGlassIcon height="16" width="16" />
            </TextField.Slot>
          </TextField.Root>
        </form>
        {canCreatePages && (
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
              <DropdownMenu.Item
                onClick={onNewPage}
                data-testid="canvas-page-list-new-page-button"
              >
                <FileTextIcon />
                New page
              </DropdownMenu.Item>
            </DropdownMenu.Content>
          </DropdownMenu.Root>
        )}
      </Flex>
      <Skeleton
        height="1.2rem"
        loading={isPageItemsLoading}
        width="100%"
        my="3"
      >
        <Box>
          {!pageItemsError && (
            <ContentGroup
              items={pageItems}
              homepagePath={homepagePath}
              selectedPageId={selectedPageId}
              onSelect={onSelectPage}
              onDuplicate={onDuplicatePage}
              onSetHomepage={onSetHomepage}
              onDelete={onDeletePage}
            />
          )}
          {pageItemsError && (
            <ErrorCard
              title="An unexpected error has occurred while loading pages."
              error={pageItemsError}
            />
          )}
        </Box>
      </Skeleton>
      <Skeleton
        loading={isPageItemsLoading}
        height="1.2rem"
        width="100%"
        my="3"
      />
      <Skeleton
        loading={isPageItemsLoading}
        height="1.2rem"
        width="100%"
        my="3"
      />
    </div>
  );
};

export default PageList;
