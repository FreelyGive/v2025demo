import { useEffect } from 'react';
import { useErrorBoundary } from 'react-error-boundary';

import CodeComponentItem from '@/components/list/CodeComponentItem';
import LibraryItemList from '@/components/list/LibraryItemList';
import UnifiedMenu from '@/components/UnifiedMenu';
import { LayoutItemType } from '@/features/ui/primaryPanelSlice';
import {
  useGetCodeComponentsQuery,
  useGetFoldersQuery,
} from '@/services/componentAndLayout';

import type { FolderData } from '@/components/list/FolderList';
import type { CodeComponentSerialized } from '@/types/CodeComponent';

const CodeComponentList = ({ searchTerm }: { searchTerm: string }) => {
  const {
    data: codeComponents,
    error,
    isLoading,
  } = useGetCodeComponentsQuery({ status: false });
  const {
    data: folders,
    error: foldersError,
    isLoading: foldersLoading,
  } = useGetFoldersQuery({ status: false });
  const { showBoundary } = useErrorBoundary();

  useEffect(() => {
    if (error || foldersError) {
      showBoundary(error || foldersError);
    }
  }, [error, showBoundary, foldersError]);

  const menuTitleItems = (component: CodeComponentSerialized) => (
    <>
      <UnifiedMenu.Label>{component.name}</UnifiedMenu.Label>
      <UnifiedMenu.Separator />
    </>
  );

  const renderItem = (component: CodeComponentSerialized & { id: string }) => (
    // @Todo: Can this return a <ListItem /> instead so it doesn't need to duplicate menuTitleItems prop and gets the thumbnail preview on hover?
    <CodeComponentItem
      component={component}
      exposed={false}
      menuTitleItems={menuTitleItems(component)}
    />
  );

  // Map machineName to id for compatibility with LibraryItemList's generic
  const codeComponentsWithId = codeComponents
    ? Object.fromEntries(
        Object.entries(codeComponents).map(([key, component]) => [
          key,
          { ...component, id: component.machineName },
        ]),
      )
    : undefined;

  return (
    <LibraryItemList<CodeComponentSerialized & { id: string }>
      items={
        codeComponentsWithId as Record<
          string,
          CodeComponentSerialized & { id: string }
        >
      }
      folders={folders as FolderData}
      isLoading={isLoading || foldersLoading}
      searchTerm={searchTerm}
      layoutType={LayoutItemType.CODE}
      topLevelLabel="Code"
      itemType="js_component"
      renderItem={renderItem}
    />
  );
};

export default CodeComponentList;
