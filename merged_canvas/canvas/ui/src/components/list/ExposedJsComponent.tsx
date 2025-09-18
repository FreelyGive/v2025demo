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
import { useGetCodeComponentQuery } from '@/services/componentAndLayout';

import type React from 'react';
import type { CodeComponentSerialized } from '@/types/CodeComponent';
import type { JSComponent } from '@/types/Component';

function removeJsPrefix(input: string): string {
  if (input.startsWith('js.')) {
    return input.substring(3);
  }
  return input;
}

const ExposedJsComponent: React.FC<{
  component: JSComponent;
  onMenuOpenChange: (open: boolean) => void;
  disabled: boolean;
}> = (props) => {
  const dispatch = useAppDispatch();
  const { component, onMenuOpenChange, disabled } = props;
  const machineName = removeJsPrefix(component.id);
  const { data: jsComponent, error } = useGetCodeComponentQuery(machineName);
  const layout = useAppSelector(selectLayout);
  const isComponentInLayout = componentExistsInLayout(layout, component.id);
  const { showBoundary } = useErrorBoundary();
  const navigate = useNavigate();
  const { codeComponentId: selectedComponent } = useParams();

  useEffect(() => {
    if (error) {
      showBoundary(error);
    }
  }, [error, showBoundary]);

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

  const menuItems = (
    <PermissionCheck
      hasPermission="codeComponents"
      denied={
        <UnifiedMenu.Item disabled>No actions available</UnifiedMenu.Item>
      }
    >
      <UnifiedMenu.Item onClick={handleRemoveFromComponentsClick}>
        Remove from components
      </UnifiedMenu.Item>
      <UnifiedMenu.Item onClick={handleEditClick}>Edit code</UnifiedMenu.Item>
      <UnifiedMenu.Item onClick={handleRenameClick}>Rename</UnifiedMenu.Item>
      <UnifiedMenu.Separator />
      <UnifiedMenu.Item color="red" onClick={handleDeleteClick}>
        Delete
      </UnifiedMenu.Item>
    </PermissionCheck>
  );
  return (
    <ContextMenu.Root key={component.id} onOpenChange={onMenuOpenChange}>
      <ContextMenu.Trigger>
        <SidebarNode
          title={component.name}
          variant="component"
          disabled={disabled}
          dropdownMenuContent={
            <UnifiedMenu.Content menuType="dropdown">
              {menuItems}
            </UnifiedMenu.Content>
          }
          selected={machineName === selectedComponent}
          onMenuOpenChange={onMenuOpenChange}
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
};

export default ExposedJsComponent;
