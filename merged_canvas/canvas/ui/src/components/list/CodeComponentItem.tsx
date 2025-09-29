import { useEffect } from 'react';
import { useErrorBoundary } from 'react-error-boundary';
import { useNavigate, useParams } from 'react-router-dom';
import { ContextMenu } from '@radix-ui/themes';

import { useAppDispatch, useAppSelector } from '@/app/hooks';
import PermissionCheck from '@/components/PermissionCheck';
import SidebarNode from '@/components/sidePanel/SidebarNode';
import UnifiedMenu from '@/components/UnifiedMenu';
import { selectLayout } from '@/features/layout/layoutModelSlice';
import { componentExistsInLayout } from '@/features/layout/layoutUtils';
import {
  openDeleteDialog,
  openInLayoutDialog,
  openRemoveFromComponentsDialog,
  openRenameDialog,
} from '@/features/ui/codeComponentDialogSlice';
import { selectActivePanel } from '@/features/ui/primaryPanelSlice';
import { useGetCodeComponentQuery } from '@/services/componentAndLayout';

import type { CodeComponentSerialized } from '@/types/CodeComponent';
import type { JSComponent } from '@/types/Component';

function removeJsPrefix(input: string): string {
  if (input.startsWith('js.')) {
    return input.substring(3);
  }
  return input;
}

// Helper to get the correct id for a component
function getComponentId(
  component: JSComponent | CodeComponentSerialized,
): string {
  // JSComponent has id, CodeComponentSerialized has machineName
  return (component as any).id || (component as any).machineName;
}

interface CodeComponentItemProps {
  component: JSComponent | CodeComponentSerialized;
  exposed: boolean;
  onMenuOpenChange?: (open: boolean) => void;
  disabled?: boolean;
  insertMenuItem?: React.ReactNode;
  menuTitleItems?: React.ReactNode;
}

const CodeComponentItem: React.FC<CodeComponentItemProps> = ({
  component,
  exposed,
  onMenuOpenChange = () => {},
  disabled = false,
  insertMenuItem,
  menuTitleItems,
}) => {
  const dispatch = useAppDispatch();
  const componentId = getComponentId(component);
  const machineName = removeJsPrefix(componentId);
  const { data: jsComponent, error } = useGetCodeComponentQuery(machineName);
  const layout = useAppSelector(selectLayout);
  const isComponentInLayout = componentExistsInLayout(layout, componentId);
  const { showBoundary } = useErrorBoundary();
  const navigate = useNavigate();
  const { codeComponentId: selectedComponent } = useParams();
  const activePanel = useAppSelector(selectActivePanel);

  useEffect(() => {
    if (error) {
      showBoundary(error);
    }
  }, [error, showBoundary]);

  // Menu item handlers
  const handleRemoveFromComponentsClick = (
    e: React.MouseEvent<HTMLDivElement>,
  ) => {
    e.stopPropagation();
    if (isComponentInLayout) {
      dispatch(openInLayoutDialog());
    } else {
      dispatch(
        openRemoveFromComponentsDialog(jsComponent as CodeComponentSerialized),
      );
    }
  };
  const handleRenameClick = (e: React.MouseEvent<HTMLDivElement>) => {
    e.stopPropagation();
    dispatch(openRenameDialog(jsComponent as CodeComponentSerialized));
  };
  const handleDeleteClick = (e: React.MouseEvent<HTMLDivElement>) => {
    e.stopPropagation();
    if (isComponentInLayout) {
      dispatch(openInLayoutDialog());
    } else {
      dispatch(openDeleteDialog(jsComponent as CodeComponentSerialized));
    }
  };
  const handleEditClick = (e: React.MouseEvent<HTMLDivElement>) => {
    e.stopPropagation();
    navigate(`/code-editor/component/${machineName}`);
  };

  // Menu items for exposed code components
  const exposedMenuItems = (
    <>
      {activePanel === 'library' && insertMenuItem}
      <PermissionCheck
        hasPermission="codeComponents"
        denied={
          activePanel !== 'library' && (
            <UnifiedMenu.Item disabled>No actions available</UnifiedMenu.Item>
          )
        }
      >
        <UnifiedMenu.Item onClick={handleRemoveFromComponentsClick}>
          Remove from components
        </UnifiedMenu.Item>
        <UnifiedMenu.Item onClick={handleEditClick}>Edit code</UnifiedMenu.Item>
        <UnifiedMenu.Item onClick={handleRenameClick}>Rename</UnifiedMenu.Item>
      </PermissionCheck>
    </>
  );

  // Menu items for non-exposed code components
  const nonExposedMenuItems = (
    <PermissionCheck
      hasPermission="codeComponents"
      denied={
        <UnifiedMenu.Item disabled>No actions available</UnifiedMenu.Item>
      }
    >
      <UnifiedMenu.Item onClick={handleEditClick}>Edit code</UnifiedMenu.Item>
      <UnifiedMenu.Item onClick={handleRenameClick}>Rename</UnifiedMenu.Item>
      {/* @todo: Add this item back in https://drupal.org/i/3524274.}
      {/* <UnifiedMenu.Item*/}
      {/*  onClick={(e: React.MouseEvent<HTMLDivElement>) => {*/}
      {/*    e.stopPropagation();*/}
      {/*    handleAddToComponentsClick(component);*/}
      {/*  }}*/}
      {/*>*/}
      {/*  Add to components*/}
      {/*</UnifiedMenu.Item>*/}
      <UnifiedMenu.Separator />
      <UnifiedMenu.Item color="red" onClick={handleDeleteClick}>
        Delete
      </UnifiedMenu.Item>
    </PermissionCheck>
  );

  // Choose menu content based on 'exposed'
  const menuContent = exposed ? exposedMenuItems : nonExposedMenuItems;

  return (
    <ContextMenu.Root key={componentId} onOpenChange={onMenuOpenChange}>
      <ContextMenu.Trigger>
        <SidebarNode
          key={componentId}
          title={component.name}
          variant={exposed ? 'component' : 'code'}
          disabled={disabled}
          dropdownMenuContent={
            <UnifiedMenu.Content menuType="dropdown">
              {menuTitleItems}
              {menuContent}
            </UnifiedMenu.Content>
          }
          selected={machineName === selectedComponent}
          onMenuOpenChange={onMenuOpenChange}
          draggable={activePanel !== 'manageLibrary'}
          onClick={() => {
            activePanel === 'manageLibrary' &&
              navigate(`/code-editor/component/${machineName}`);
          }}
        />
      </ContextMenu.Trigger>
      <UnifiedMenu.Content
        onClick={(e) => e.stopPropagation()}
        menuType="context"
        align="start"
        side="right"
      >
        {menuTitleItems}
        {menuContent}
      </UnifiedMenu.Content>
    </ContextMenu.Root>
  );
};

export default CodeComponentItem;
