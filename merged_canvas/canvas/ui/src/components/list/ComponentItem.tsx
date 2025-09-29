import { ContextMenu } from '@radix-ui/themes';

import { useAppSelector } from '@/app/hooks';
import SidebarNode from '@/components/sidePanel/SidebarNode';
import UnifiedMenu from '@/components/UnifiedMenu';
import { selectActivePanel } from '@/features/ui/primaryPanelSlice';

import type React from 'react';
import type { CanvasComponent } from '@/types/Component';

const ComponentItem: React.FC<{
  component: CanvasComponent;
  onMenuOpenChange: (open: boolean) => void;
  disabled: boolean;
  insertMenuItem?: React.ReactNode;
  menuTitleItems?: React.ReactNode;
}> = (props) => {
  const {
    component,
    onMenuOpenChange,
    disabled,
    insertMenuItem,
    menuTitleItems,
  } = props;
  const activePanel = useAppSelector(selectActivePanel);

  const menuItems = activePanel === 'library' ? insertMenuItem : null;

  const sidebarNode = (
    <SidebarNode
      title={component.name}
      variant={
        (component as CanvasComponent).source === 'Blocks'
          ? 'dynamicComponent'
          : 'component'
      }
      disabled={disabled}
      onMenuOpenChange={onMenuOpenChange}
      draggable={activePanel !== 'manageLibrary'}
      {...(menuItems && {
        dropdownMenuContent: (
          <UnifiedMenu.Content menuType="dropdown">
            {menuTitleItems}
            {menuItems}
          </UnifiedMenu.Content>
        ),
      })}
    />
  );

  if (!menuItems) {
    return sidebarNode;
  }

  return (
    <ContextMenu.Root key={component.id} onOpenChange={onMenuOpenChange}>
      <ContextMenu.Trigger>{sidebarNode}</ContextMenu.Trigger>
      <UnifiedMenu.Content
        onClick={(e) => e.stopPropagation()}
        menuType="context"
        align="start"
        side="right"
      >
        {menuTitleItems}
        {menuItems}
      </UnifiedMenu.Content>
    </ContextMenu.Root>
  );
};

export default ComponentItem;
