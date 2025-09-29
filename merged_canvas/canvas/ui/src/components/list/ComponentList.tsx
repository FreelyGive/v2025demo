import { useEffect } from 'react';
import { useErrorBoundary } from 'react-error-boundary';

import ListItem from '@/components/list/ListItem';
import { LayoutItemType } from '@/features/ui/primaryPanelSlice';
import {
  useGetComponentsQuery,
  useGetFoldersQuery,
} from '@/services/componentAndLayout';

import LibraryItemList from './LibraryItemList';

import type { CanvasComponent, ComponentsList } from '@/types/Component';
import type { FolderData } from './FolderList';

interface ComponentListProps {
  searchTerm: string;
}

const ComponentList = ({ searchTerm }: ComponentListProps) => {
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

  const renderItem = (item: CanvasComponent) => {
    return <ListItem item={item} type={LayoutItemType.COMPONENT} />;
  };

  return (
    <LibraryItemList<CanvasComponent>
      items={components as ComponentsList}
      folders={folders as FolderData}
      isLoading={isLoading || foldersLoading}
      searchTerm={searchTerm}
      layoutType={LayoutItemType.COMPONENT}
      topLevelLabel="Components"
      itemType="component"
      renderItem={renderItem}
    />
  );
};

export default ComponentList;
