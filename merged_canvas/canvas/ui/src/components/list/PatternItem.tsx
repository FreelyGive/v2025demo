import { ContextMenu } from '@radix-ui/themes';

import { useAppDispatch, useAppSelector } from '@/app/hooks';
import PermissionCheck from '@/components/PermissionCheck';
import SidebarNode from '@/components/sidePanel/SidebarNode';
import UnifiedMenu from '@/components/UnifiedMenu';
import { setDialogWithDataOpen } from '@/features/ui/dialogSlice';
import { selectActivePanel } from '@/features/ui/primaryPanelSlice';

import type React from 'react';
import type { Pattern } from '@/types/Pattern';

const PatternItem: React.FC<{
  pattern: Pattern;
  onMenuOpenChange: (open: boolean) => void;
  disabled: boolean;
  insertMenuItem?: React.ReactNode;
  menuTitleItems?: React.ReactNode;
}> = (props) => {
  const {
    pattern,
    onMenuOpenChange,
    disabled,
    insertMenuItem,
    menuTitleItems,
  } = props;
  const dispatch = useAppDispatch();
  const activePanel = useAppSelector(selectActivePanel);

  const handleDeleteClick = (e: React.MouseEvent<HTMLDivElement>) => {
    e.stopPropagation();
    dispatch(
      setDialogWithDataOpen({
        operation: 'deletePatternConfirm',
        data: pattern,
      }),
    );
  };

  const menuItems = (
    <>
      {menuTitleItems}
      {activePanel === 'library' && insertMenuItem}
      {activePanel === 'manageLibrary' && (
        <PermissionCheck
          hasPermission="patterns"
          denied={
            <UnifiedMenu.Item disabled>No actions available</UnifiedMenu.Item>
          }
        >
          <UnifiedMenu.Item color="red" onClick={handleDeleteClick}>
            Delete pattern
          </UnifiedMenu.Item>
        </PermissionCheck>
      )}
    </>
  );

  return (
    <ContextMenu.Root key={pattern.id} onOpenChange={onMenuOpenChange}>
      <ContextMenu.Trigger>
        <SidebarNode
          title={pattern.name}
          variant="pattern"
          disabled={disabled}
          dropdownMenuContent={
            <UnifiedMenu.Content menuType="dropdown">
              {menuItems}
            </UnifiedMenu.Content>
          }
          onMenuOpenChange={onMenuOpenChange}
          draggable={activePanel !== 'manageLibrary'}
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

export default PatternItem;
