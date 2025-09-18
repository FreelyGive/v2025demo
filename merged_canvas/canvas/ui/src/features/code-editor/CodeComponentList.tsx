import React, { useEffect, useMemo } from 'react';
import { useErrorBoundary } from 'react-error-boundary';
import { useNavigate, useParams } from 'react-router-dom';
import { InfoCircledIcon } from '@radix-ui/react-icons';
import { Callout, ContextMenu, Flex, Skeleton } from '@radix-ui/themes';

import { useAppDispatch } from '@/app/hooks';
import FolderList, {
  folderfyComponents,
  sortFolderList,
} from '@/components/list/FolderList';
import SidebarNode from '@/components/sidePanel/SidebarNode';
import UnifiedMenu from '@/components/UnifiedMenu';
import {
  openDeleteDialog,
  openRenameDialog,
} from '@/features/ui/codeComponentDialogSlice';
import {
  useGetCodeComponentsQuery,
  useGetFoldersQuery,
} from '@/services/componentAndLayout';

import type { CodeComponentSerialized } from '@/types/CodeComponent';

import styles from './CodeComponentList.module.css';

export interface FolderCodeComponent {
  id: string;
  name: string;
  items: Array<{ id: string; component: CodeComponentSerialized }>;
  weight?: number;
}

const CodeComponentList = () => {
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
  const dispatch = useAppDispatch();
  const { showBoundary } = useErrorBoundary();
  const navigate = useNavigate();
  const { codeComponentId: componentId } = useParams();
  useEffect(() => {
    if (error || foldersError) {
      showBoundary(error || foldersError);
    }
  }, [error, showBoundary, foldersError]);

  const handleComponentClick = (machineName: string) => {
    navigate(`/code-editor/code/${machineName}`);
  };

  const handleRenameClick = (component: CodeComponentSerialized) => {
    dispatch(openRenameDialog(component));
  };

  const handleDeleteClick = (component: CodeComponentSerialized) => {
    dispatch(openDeleteDialog(component));
  };

  const { topLevelComponents, folderComponents } = useMemo(
    () =>
      folderfyComponents(
        codeComponents,
        folders,
        isLoading,
        foldersLoading,
        'js_component',
      ),
    [codeComponents, folders, isLoading, foldersLoading],
  );
  const folderEntries = sortFolderList(folderComponents);

  if ((!codeComponents || !Object.keys(codeComponents).length) && !isLoading) {
    return (
      <Callout.Root size="1" variant="soft" color="gray" my="3">
        <Flex align="center" gapX="2">
          <Callout.Icon>
            <InfoCircledIcon />
          </Callout.Icon>
          <Callout.Text size="1">
            No items to show in Code components
          </Callout.Text>
        </Flex>
      </Callout.Root>
    );
  }

  const componentItem = (
    id: string,
    component: CodeComponentSerialized,
    indent = 0,
  ) => {
    const menuItems = (
      <>
        <UnifiedMenu.Item
          onClick={(e: React.MouseEvent<HTMLDivElement>) => {
            e.stopPropagation();
            handleComponentClick(component.machineName);
          }}
        >
          Edit
        </UnifiedMenu.Item>
        <UnifiedMenu.Item
          onClick={(e: React.MouseEvent<HTMLDivElement>) => {
            e.stopPropagation();
            handleRenameClick(component);
          }}
        >
          Rename
        </UnifiedMenu.Item>
        {/* @todo: Add this item back in https://drupal.org/i/3524274.}
                  {/*<UnifiedMenu.Item*/}
        {/*  onClick={(e: React.MouseEvent<HTMLDivElement>) => {*/}
        {/*    e.stopPropagation();*/}
        {/*    handleAddToComponentsClick(component);*/}
        {/*  }}*/}
        {/*>*/}
        {/*  Add to components*/}
        {/*</UnifiedMenu.Item>*/}
        <UnifiedMenu.Separator />
        <UnifiedMenu.Item
          color="red"
          onClick={(e: React.MouseEvent<HTMLDivElement>) => {
            e.stopPropagation();
            handleDeleteClick(component);
          }}
        >
          Delete
        </UnifiedMenu.Item>
      </>
    );

    const item = (
      <ContextMenu.Root key={id}>
        <ContextMenu.Trigger>
          <SidebarNode
            key={id}
            title={component.name}
            variant="code"
            draggable={false}
            onClick={() => handleComponentClick(component.machineName)}
            className={styles.listItem}
            selected={component.machineName === componentId}
            dropdownMenuContent={
              <UnifiedMenu.Content menuType="dropdown">
                {menuItems}
              </UnifiedMenu.Content>
            }
          />
        </ContextMenu.Trigger>
        <UnifiedMenu.Content
          onClick={(e) => e.stopPropagation()}
          menuType="context"
          align="start"
          side="right"
        >
          {menuItems}
        </UnifiedMenu.Content>
      </ContextMenu.Root>
    );
    if (indent > 0) {
      return (
        <Flex direction="row" pl={`${indent}`} width="100%" key={id}>
          {item}
        </Flex>
      );
    }
    return item;
  };

  return (
    <>
      <Skeleton
        loading={isLoading || foldersLoading}
        height="1.2rem"
        width="100%"
        my="3"
      >
        <Flex direction="column">
          {/* First, render any folders and the items they contain. */}
          {Object.entries(folderComponents).length &&
            folderEntries.map((folder, index) => {
              return (
                <FolderList key={index} folder={folder}>
                  {Object.values(folder.items).map(
                    (comp: CodeComponentSerialized, index) => {
                      const codeComponent =
                        comp as unknown as CodeComponentSerialized;

                      return (
                        <React.Fragment key={index}>
                          {componentItem(
                            codeComponent.machineName,
                            codeComponent,
                            2,
                          )}
                        </React.Fragment>
                      );
                    },
                  )}
                </FolderList>
              );
            })}
          {/* Then, render any items not in folders. */}
          {Object.keys(topLevelComponents).length &&
            Object.entries(topLevelComponents || {}).map(
              ([id, component]: [string, CodeComponentSerialized]) => {
                return componentItem(id, component);
              },
            )}
        </Flex>
      </Skeleton>
      <Skeleton loading={isLoading} height="1.2rem" width="100%" my="3" />
      <Skeleton loading={isLoading} height="1.2rem" width="100%" my="3" />
    </>
  );
};

export default CodeComponentList;
