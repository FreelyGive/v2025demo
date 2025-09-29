import { useMemo } from 'react';
import { InfoCircledIcon } from '@radix-ui/react-icons';
import { Callout, Flex, Skeleton } from '@radix-ui/themes';

import FolderList, { folderfyComponents, sortFolderList } from './FolderList';
import List from './List';

import type { LayoutItemType } from '@/features/ui/primaryPanelSlice';
import type { CodeComponentSerialized } from '@/types/CodeComponent';
import type { ComponentsList } from '@/types/Component';
import type { PatternsList } from '@/types/Pattern';
import type { FolderData } from './FolderList';

type AllowedItemTypes =
  | ComponentsList
  | PatternsList
  | Record<string, CodeComponentSerialized>;

type AllowedLayoutType =
  | LayoutItemType.PATTERN
  | LayoutItemType.COMPONENT
  | LayoutItemType.CODE;

interface LibraryItemListProps<T extends { id: string; name: string }> {
  items: AllowedItemTypes | undefined;
  folders: FolderData | undefined;
  isLoading: boolean;
  searchTerm: string;
  layoutType: AllowedLayoutType;
  topLevelLabel: string;
  itemType: string;
  renderItem: (item: T, extra?: any) => React.ReactNode;
}

function LibraryItemList<T extends { id: string; name: string }>({
  items,
  folders,
  isLoading,
  searchTerm,
  layoutType,
  topLevelLabel,
  itemType,
  renderItem,
}: LibraryItemListProps<T>) {
  const { topLevelComponents, folderComponents } = useMemo(
    () => folderfyComponents(items, folders, isLoading, false, itemType),
    [items, folders, isLoading, itemType],
  );
  const folderEntries = useMemo(
    () => sortFolderList(folderComponents),
    [folderComponents],
  );

  const filterBySearch = (item: T) => {
    if (!searchTerm) return true;
    return item.name?.toLowerCase().includes(searchTerm.toLowerCase());
  };

  // Sort top-level items alphabetically by name
  const filteredTopLevelArray: T[] = Object.values(topLevelComponents || {});
  const filteredTopLevelFilteredArray = filteredTopLevelArray
    .filter(filterBySearch)
    .sort((a, b) => a.name.localeCompare(b.name));
  const filteredTopLevel = Object.fromEntries(
    filteredTopLevelFilteredArray.map((item) => [item.id, item]),
  ) as unknown as AllowedItemTypes;

  // Sort folder items alphabetically by name
  const filteredFolderEntries = folderEntries
    .map((folder) => {
      const filteredArray = Object.values(folder.items)
        .filter(filterBySearch)
        .sort((a, b) => a.name.localeCompare(b.name));
      return {
        ...folder,
        items: Object.fromEntries(
          filteredArray.map((item) => [item.id, item]),
        ) as unknown as AllowedItemTypes,
      };
    })
    .filter((folder) =>
      searchTerm ? Object.keys(folder.items).length > 0 : true,
    );

  // Only show Callout if there are no items to show and loading is done.
  const showCallout =
    !isLoading &&
    filteredFolderEntries.length === 0 &&
    filteredTopLevelFilteredArray.length === 0;

  if (isLoading) {
    return (
      <>
        <Skeleton
          data-testid="canvas-components-library-loading"
          loading={isLoading}
          height="1.2rem"
          width="100%"
          my="3"
        />
        <Skeleton loading={isLoading} height="1.2rem" width="100%" my="3" />
        <Skeleton loading={isLoading} height="1.2rem" width="100%" my="3" />
      </>
    );
  }

  if (showCallout) {
    return (
      <Callout.Root size="1" variant="soft" color="gray" my="3">
        <Flex align="center" gapX="2">
          <Callout.Icon>
            <InfoCircledIcon />
          </Callout.Icon>
          {searchTerm ? (
            <Callout.Text size="1">
              No results for "<strong>{searchTerm}</strong>" in {topLevelLabel}
            </Callout.Text>
          ) : (
            <Callout.Text size="1">
              No items to show in {topLevelLabel}
            </Callout.Text>
          )}
        </Flex>
      </Callout.Root>
    );
  }

  return (
    <>
      {/* Render folders with filtered items */}
      {filteredFolderEntries.length > 0 &&
        filteredFolderEntries.map((folder) => (
          <FolderList key={folder.id} folder={folder}>
            <List
              items={folder.items as ComponentsList | PatternsList}
              type={
                layoutType as
                  | LayoutItemType.COMPONENT
                  | LayoutItemType.PATTERN
                  | LayoutItemType.DYNAMIC
              }
              renderItem={renderItem}
              key={folder.id}
              indent={2.5}
            />
          </FolderList>
        ))}
      {/* Render top-level items not in folders */}
      <List
        items={filteredTopLevel as ComponentsList | PatternsList}
        type={
          layoutType as
            | LayoutItemType.COMPONENT
            | LayoutItemType.PATTERN
            | LayoutItemType.DYNAMIC
        }
        renderItem={renderItem}
      />
    </>
  );
}

export default LibraryItemList;
