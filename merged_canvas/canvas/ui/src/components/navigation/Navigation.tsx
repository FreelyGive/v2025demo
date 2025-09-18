import { useEffect } from 'react';
import {
  ChevronDownIcon,
  DotsVerticalIcon,
  FileTextIcon,
  HomeIcon,
  InfoCircledIcon,
  MagnifyingGlassIcon,
  PlusIcon,
} from '@radix-ui/react-icons';
import {
  AlertDialog,
  Box,
  Button,
  Callout,
  DropdownMenu,
  Flex,
  Heading,
  IconButton,
  ScrollArea,
  Text,
  TextField,
} from '@radix-ui/themes';

import { useAppSelector } from '@/app/hooks';
import { selectHomepagePath } from '@/features/configuration/configurationSlice';

import type { FormEvent } from 'react';
import type { ContentStub } from '@/types/Content';

import styles from './Navigation.module.css';

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

// Helper functions to return JSX or null based on item links/permissions
const renderDuplicateButton = (
  item: ContentStub,
  onDuplicate?: (page: ContentStub) => void,
) =>
  hasPermission('duplicate', item) ? (
    <DropdownMenu.Item
      onClick={(event) => event.stopPropagation()}
      onSelect={onDuplicate ? () => onDuplicate(item) : undefined}
    >
      Duplicate page
    </DropdownMenu.Item>
  ) : null;

const renderSetAsHomepageButton = (
  item: ContentStub,
  onSetHomepage?: (page: ContentStub) => void,
  homepagePath?: string,
) =>
  hasPermission('homepage', item) && item.internalPath !== homepagePath ? (
    <>
      <DropdownMenu.Separator />
      <DropdownMenu.Item
        onClick={(event) => event.stopPropagation()}
        onSelect={onSetHomepage ? () => onSetHomepage(item) : undefined}
      >
        Set as homepage
      </DropdownMenu.Item>
    </>
  ) : null;

const renderDeleteButton = (
  item: ContentStub,
  onDelete?: (page: ContentStub) => void,
) =>
  hasPermission('delete', item) ? (
    <>
      {/* If there are any links above, show a separator */}
      {hasPermission('edit', item) ||
      hasPermission('homepage', item) ||
      hasPermission('duplicate', item) ? (
        <DropdownMenu.Separator />
      ) : null}
      <AlertDialog.Root>
        <AlertDialog.Trigger>
          <DropdownMenu.Item
            onClick={(event) => event.stopPropagation()}
            onSelect={(event) => event.preventDefault()}
            color="red"
          >
            Delete page
          </DropdownMenu.Item>
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
              <DropdownMenu.Item
                onClick={(event) => event.stopPropagation()}
                onSelect={() => (onDelete ? onDelete(item) : undefined)}
              >
                <Button variant="solid" color="red">
                  Delete page
                </Button>
              </DropdownMenu.Item>
            </AlertDialog.Action>
          </Flex>
        </AlertDialog.Content>
      </AlertDialog.Root>
    </>
  ) : null;

const ContentGroup = ({
  title,
  items,
  onSelect,
  onDuplicate,
  onSetHomepage,
  onDelete,
}: {
  title: string;
  items: ContentStub[];
  onSelect?: (value: ContentStub) => void;
  onDuplicate?: (page: ContentStub) => void;
  onSetHomepage?: (page: ContentStub) => void;
  onDelete?: (page: ContentStub) => void;
}) => {
  const homepagePath = useAppSelector(selectHomepagePath);
  if (items.length === 0) {
    return (
      <Callout.Root
        size="1"
        color="gray"
        data-testid="canvas-navigation-results"
      >
        <Callout.Icon>
          <InfoCircledIcon />
        </Callout.Icon>
        <Callout.Text>No pages found.</Callout.Text>
      </Callout.Root>
    );
  }

  return (
    <div>
      <Heading as="h5" size="1" color="gray">
        {title}
      </Heading>
      <Flex
        data-testid="canvas-navigation-results"
        direction="column"
        gap="2"
        mt="2"
      >
        {items.map((item) => {
          return (
            <Flex
              direction={'row'}
              align={'center'}
              role={'list'}
              mr="4"
              p="1"
              pr="2"
              className={styles.item}
              key={item.id}
              data-canvas-page-id={item.id}
            >
              <Flex
                role={'listitem'}
                className={styles.pageLink}
                flexGrow="1"
                onClick={onSelect ? () => onSelect(item) : undefined}
              >
                <Box px="3" pt="1">
                  {item.internalPath === homepagePath ? (
                    <HomeIcon />
                  ) : (
                    <FileTextIcon />
                  )}
                </Box>
                <Flex flexGrow="1" align="center">
                  <Text as="span" size="1">
                    {item.autoSaveLabel || item.title}{' '}
                    <span className={styles.path}>
                      {item.autoSavePath || item.path}
                    </span>
                  </Text>
                </Flex>
              </Flex>
              {Object.keys(item.links).length && (
                <DropdownMenu.Root>
                  <DropdownMenu.Trigger>
                    <IconButton
                      variant="ghost"
                      color="gray"
                      className={styles.optionsButton}
                      aria-label={`Page options for ${item.title}`}
                    >
                      <DotsVerticalIcon />
                    </IconButton>
                  </DropdownMenu.Trigger>
                  <DropdownMenu.Content>
                    {renderDuplicateButton(item, onDuplicate)}
                    {renderSetAsHomepageButton(
                      item,
                      onSetHomepage,
                      homepagePath,
                    )}
                    {renderDeleteButton(item, onDelete)}
                  </DropdownMenu.Content>
                </DropdownMenu.Root>
              )}
            </Flex>
          );
        })}
      </Flex>
    </div>
  );
};

const Navigation = ({
  loading = false,
  showNew,
  items = [],
  onNewPage,
  onSearch,
  onSelect,
  onDuplicate,
  onSetHomepage,
  onDelete,
}: {
  loading: boolean;
  showNew: boolean;
  items: ContentStub[];
  onNewPage?: () => void;
  onSearch?: (value: string) => void;
  onSelect?: (value: ContentStub) => void;
  onDuplicate?: (page: ContentStub) => void;
  onSetHomepage?: (page: ContentStub) => void;
  onDelete?: (page: ContentStub) => void;
}) => {
  // Reset search when the component unmounts
  useEffect(() => {
    return () => {
      if (onSearch) {
        onSearch('');
      }
    };
  }, [onSearch]);

  return (
    <div data-testid="canvas-navigation-content">
      <Flex direction="row" gap="2" mb="4">
        <form
          className={styles.search}
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
        {showNew && (
          <DropdownMenu.Root>
            <DropdownMenu.Trigger>
              <Button
                variant="soft"
                data-testid="canvas-navigation-new-button"
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
                data-testid="canvas-navigation-new-page-button"
              >
                <FileTextIcon />
                New page
              </DropdownMenu.Item>
            </DropdownMenu.Content>
          </DropdownMenu.Root>
        )}
      </Flex>
      <ScrollArea scrollbars="vertical" style={{ height: 175 }}>
        {loading && <p>Loading...</p>}
        {!loading && (
          <ContentGroup
            title="Pages"
            items={items}
            onSelect={onSelect}
            onDuplicate={onDuplicate}
            onSetHomepage={onSetHomepage}
            onDelete={onDelete}
          />
        )}
      </ScrollArea>
    </div>
  );
};
export default Navigation;
