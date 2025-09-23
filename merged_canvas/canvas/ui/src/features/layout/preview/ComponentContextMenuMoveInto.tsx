import { useMemo } from 'react';

import { useAppDispatch, useAppSelector } from '@/app/hooks';
import { UnifiedMenu } from '@/components/UnifiedMenu';
import { moveNode, selectLayout } from '@/features/layout/layoutModelSlice';
import {
  findNodePathByUuid,
  findSiblings,
  getDisplayNameForNode,
} from '@/features/layout/layoutUtils';
import { unsetHoveredComponent } from '@/features/ui/uiSlice';

import type React from 'react';
import type {
  ComponentNode,
  SlotNode,
} from '@/features/layout/layoutModelSlice';
import type { ComponentsList } from '@/types/Component';

const SiblingSlotsSubMenu: React.FC<{
  component: ComponentNode;
  components: ComponentsList;
}> = ({ component, components }) => {
  const dispatch = useAppDispatch();
  const layout = useAppSelector(selectLayout);
  const siblingSlots = useMemo(() => {
    const siblings = findSiblings(layout, component.uuid);
    const slots: {
      slot: SlotNode;
      parentComponent: ComponentNode;
      parentComponentName: string;
      slotDisplayName: string;
    }[] = [];
    siblings
      .filter((sib) => sib.slots && sib.slots.length > 0) // Only display siblings with slots
      .forEach((sib) => {
        const name = getDisplayNameForNode(sib, null, components);
        sib.slots.forEach((slot) => {
          slots.push({
            slot,
            parentComponent: sib,
            parentComponentName: name,
            slotDisplayName: getDisplayNameForNode(slot, sib, components),
          });
        });
      });
    return slots;
  }, [layout, component.uuid, components]);

  const handleMoveIntoSlot =
    (slotId: string) => (ev: React.MouseEvent<HTMLElement>) => {
      ev.stopPropagation();
      const slotPath = findNodePathByUuid(layout, slotId);
      if (!slotPath) return;
      // Insert at the end of the slot's component array
      const to = [...slotPath, 0];
      dispatch(moveNode({ uuid: component.uuid, to }));
      dispatch(unsetHoveredComponent());
    };

  return (
    <UnifiedMenu.Sub>
      <UnifiedMenu.SubTrigger>Move into</UnifiedMenu.SubTrigger>
      <UnifiedMenu.SubContent>
        {siblingSlots.length === 0 && (
          <UnifiedMenu.Item disabled>No sibling slots</UnifiedMenu.Item>
        )}
        {siblingSlots.map(({ slot, parentComponentName, slotDisplayName }) => (
          <UnifiedMenu.Item key={slot.id} onClick={handleMoveIntoSlot(slot.id)}>
            {slotDisplayName} ({parentComponentName})
          </UnifiedMenu.Item>
        ))}
      </UnifiedMenu.SubContent>
    </UnifiedMenu.Sub>
  );
};

export default SiblingSlotsSubMenu;
