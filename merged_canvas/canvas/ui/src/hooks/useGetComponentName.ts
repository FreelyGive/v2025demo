import { useCallback } from 'react';

import { getDisplayNameForNode } from '@/features/layout/layoutUtils';
import { useGetComponentsQuery } from '@/services/componentAndLayout';

import type {
  ComponentNode,
  LayoutChildNode,
  LayoutNode,
  SlotNode,
} from '@/features/layout/layoutModelSlice';
import type { ComponentsList } from '@/types/Component';

const useGetComponentName = (
  node: LayoutChildNode | null,
  parentNode?: LayoutNode | null,
) => {
  const { data: components } = useGetComponentsQuery();

  // Helper to cast parentNode to ComponentNode if needed
  const getParentComponentNode = (
    parent: LayoutNode | null | undefined,
  ): ComponentNode | null => {
    if (parent && 'type' in parent) {
      return parent as ComponentNode;
    }
    return null;
  };

  // Helper to cast node to SlotNode if needed
  const getSlotNode = (n: LayoutChildNode | null): SlotNode | null => {
    if (n && n.nodeType === 'slot') {
      return n as SlotNode;
    }
    return null;
  };

  // Helper to cast node to ComponentNode if needed
  const getComponentNode = (
    n: LayoutChildNode | null,
  ): ComponentNode | null => {
    if (n && n.nodeType === 'component') {
      return n as ComponentNode;
    }
    return null;
  };

  const getName = useCallback(() => {
    if (!node || !components) return '';
    if (node.nodeType === 'component') {
      return getDisplayNameForNode(
        getComponentNode(node),
        null,
        components as ComponentsList,
      );
    } else if (node.nodeType === 'slot') {
      return getDisplayNameForNode(
        getSlotNode(node),
        getParentComponentNode(parentNode),
        components as ComponentsList,
      );
    }
    return '';
  }, [node, parentNode, components]);

  return getName();
};

export default useGetComponentName;
