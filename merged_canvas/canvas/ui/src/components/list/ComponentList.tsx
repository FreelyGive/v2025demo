import { useEffect, useMemo } from 'react';
import { useErrorBoundary } from 'react-error-boundary';

import FolderList, {
  folderfyComponents,
  sortFolderList,
} from '@/components/list/FolderList';
import List from '@/components/list/List';
import { LayoutItemType } from '@/features/ui/primaryPanelSlice';
import {
  useGetComponentsQuery,
  useGetFoldersQuery,
} from '@/services/componentAndLayout';

import type { ComponentsList } from '@/types/Component';

const ComponentList = () => {
  const { data: components, error, isLoading } = useGetComponentsQuery();
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
  }, [error, foldersError, showBoundary]);

  const { topLevelComponents, folderComponents } = useMemo(
    () =>
      folderfyComponents(
        components,
        folders,
        isLoading,
        foldersLoading,
        'component',
      ),
    [components, folders, isLoading, foldersLoading],
  );
  const folderEntries = sortFolderList(folderComponents);

  return (
    <>
      {/* First, render any folders and the items they contain. */}
      {folderEntries.length > 0 &&
        folderEntries.map((folder) => {
          return (
            <FolderList key={folder.id} folder={folder}>
              <List
                items={folder.items as ComponentsList}
                isLoading={foldersLoading}
                type={LayoutItemType.COMPONENT}
                label={`Components in folder ${folder.name}`}
                key={folder.id}
                inFolder={true}
              />
            </FolderList>
          );
        })}
      {/* Show if components are still loading (to show skeleton) or if there
          are folder-less components (to display the components). */}
      {(isLoading ||
        foldersLoading ||
        !!Object.keys(topLevelComponents || {}).length) && (
        <List
          items={topLevelComponents || {}}
          isLoading={isLoading || foldersLoading}
          type={LayoutItemType.COMPONENT}
          label="Components"
        />
      )}
    </>
  );
};

export default ComponentList;
