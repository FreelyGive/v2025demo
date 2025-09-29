import { useEffect } from 'react';
import { useErrorBoundary } from 'react-error-boundary';

import ListItem from '@/components/list/ListItem';
import { LayoutItemType } from '@/features/ui/primaryPanelSlice';
import { useGetFoldersQuery } from '@/services/componentAndLayout';
import { useGetPatternsQuery } from '@/services/patterns';

import LibraryItemList from './LibraryItemList';

import type { Pattern, PatternsList } from '@/types/Pattern';
import type { FolderData } from './FolderList';

interface PatternListProps {
  searchTerm: string;
}

const PatternList = ({ searchTerm }: PatternListProps) => {
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

  const renderItem = (item: Pattern) => {
    return <ListItem item={item} type={LayoutItemType.PATTERN} />;
  };

  return (
    <LibraryItemList<Pattern>
      items={patterns as PatternsList}
      folders={folders as FolderData}
      isLoading={isLoading || foldersLoading}
      searchTerm={searchTerm}
      layoutType={LayoutItemType.PATTERN}
      topLevelLabel="Patterns"
      itemType="pattern"
      renderItem={renderItem}
    />
  );
};

export default PatternList;
