import { useEffect, useMemo } from 'react';
import { useErrorBoundary } from 'react-error-boundary';

import FolderList, {
  folderfyComponents,
  sortFolderList,
} from '@/components/list/FolderList';
import List from '@/components/list/List';
import { LayoutItemType } from '@/features/ui/primaryPanelSlice';
import { useGetFoldersQuery } from '@/services/componentAndLayout';
import { useGetPatternsQuery } from '@/services/patterns';

import type { PatternsList } from '@/types/Pattern';

const PatternList = () => {
  const { data: patterns, error, isLoading } = useGetPatternsQuery();
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

  const {
    topLevelComponents: topLevelPatterns,
    folderComponents: folderPatterns,
  } = useMemo(
    () =>
      folderfyComponents(
        patterns,
        folders,
        isLoading,
        foldersLoading,
        'pattern',
      ),
    [patterns, folders, isLoading, foldersLoading],
  );
  const folderEntries = sortFolderList(folderPatterns);
  return (
    <>
      {/* First, render any folders and the items they contain. */}
      {folderEntries.length > 0 &&
        folderEntries.map((folder) => {
          return (
            <FolderList key={folder.id} folder={folder}>
              <List
                items={folder.items as PatternsList}
                isLoading={isLoading}
                type={LayoutItemType.PATTERN}
                label={`Patterns in folder ${folder.name}`}
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
        !!Object.keys(topLevelPatterns || {}).length) && (
        <List
          items={topLevelPatterns}
          isLoading={isLoading || foldersLoading}
          type={LayoutItemType.PATTERN}
          label="Patterns"
        />
      )}
    </>
  );
};

export default PatternList;
