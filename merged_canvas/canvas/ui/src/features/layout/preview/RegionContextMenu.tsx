import { ContextMenu } from '@radix-ui/themes';

import { UnifiedMenu } from '@/components/UnifiedMenu';
import useEditorNavigation from '@/hooks/useEditorNavigation';

import type React from 'react';
import type { ReactNode } from 'react';
import type { UnifiedMenuType } from '@/components/UnifiedMenu';
import type { RegionNode } from '@/features/layout/layoutModelSlice';

interface RegionContextMenuProps {
  children: ReactNode;
  region: RegionNode;
}
export const RegionContextMenuContent: React.FC<
  Pick<RegionContextMenuProps, 'region'> & {
    menuType?: UnifiedMenuType;
  }
> = ({ region, menuType = 'context' }) => {
  const { setSelectedRegion } = useEditorNavigation();

  const handleEditGlobalRegion = () => {
    setSelectedRegion(region.id);
  };

  return (
    <UnifiedMenu.Content
      aria-label="Context menu for region"
      menuType={menuType}
      align="start"
      side="right"
      onClick={(e) => e.stopPropagation()}
    >
      <UnifiedMenu.Label>{region.name}</UnifiedMenu.Label>
      <UnifiedMenu.Separator />
      <UnifiedMenu.Item onClick={handleEditGlobalRegion}>
        Edit global region
      </UnifiedMenu.Item>
    </UnifiedMenu.Content>
  );
};

const RegionContextMenu: React.FC<RegionContextMenuProps> = ({
  children,
  region,
}) => {
  return (
    <ContextMenu.Root>
      <ContextMenu.Trigger>{children}</ContextMenu.Trigger>
      <RegionContextMenuContent region={region} />
    </ContextMenu.Root>
  );
};

export default RegionContextMenu;
