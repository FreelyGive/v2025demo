import { useCallback } from 'react';
import { useLocation, useNavigate } from 'react-router-dom';

import { useAppDispatch, useAppSelector } from '@/app/hooks';
import { selectDevMode } from '@/features/configuration/configurationSlice';
import { selectLayout } from '@/features/layout/layoutModelSlice';
import {
  areConsecutiveSiblings,
  findComponentByUuid,
  isParentOf,
} from '@/features/layout/layoutUtils';
import {
  selectSelectedComponentUuid,
  selectSelection,
  setSelection,
} from '@/features/ui/uiSlice';
import { getCanvasSettings } from '@/utils/drupal-globals';
import {
  removeComponentFromPathname,
  setComponentInPathname,
} from '@/utils/route-utils';

import type { RegionNode } from '@/features/layout/layoutModelSlice';

const canvasSettings = getCanvasSettings();

/**
 * Filters out any components that are parents or children of components in the selection
 * @param layout - The entire layout tree
 * @param currentSelection - Current selection array
 * @param newComponentUuid - UUID of the component (potentially) being added to selection
 * @returns An array of component UUIDs with no parent-child relationships
 */
function filterParentChildRelationships(
  layout: RegionNode[],
  currentSelection: string[],
  newComponentUuid: string,
): string[] {
  // If we're adding to an empty selection, no filtering needed
  if (currentSelection.length === 0) {
    return [newComponentUuid];
  }

  // First check if the new component is a parent of any currently selected components
  const newComponent = findComponentByUuid(layout, newComponentUuid);
  if (!newComponent) {
    return currentSelection; // Component not found, return current selection
  }

  // Check if the new component is a parent of any selected components
  // If so, we need to remove those child components from the selection
  const childrenToRemove = currentSelection.filter((selectedUuid) =>
    isParentOf(newComponent, selectedUuid),
  );

  // Check if the new component is a child of any selected components
  // If so, we need to remove those parent components from the selection
  const parentsToRemove = currentSelection.filter((selectedUuid) => {
    const possibleParent = findComponentByUuid(layout, selectedUuid);
    return possibleParent && isParentOf(possibleParent, newComponentUuid);
  });

  // Create a new selection with:
  // 1. All items from current selection
  // 2. Minus any children of the new component
  // 3. Minus any parents of the new component
  // 4. Plus the new component
  const itemsToRemove = new Set([...childrenToRemove, ...parentsToRemove]);
  const filteredSelection = currentSelection.filter(
    (uuid) => !itemsToRemove.has(uuid),
  );

  return [...filteredSelection, newComponentUuid];
}

/**
 * Hook for component selection functionality.
 * Handles selecting and deselecting components, managing multi-selection and ensuring the page URL is updated to
 * show the selectedComponent (when there is exactly one selected)
 * Also exposes functions to drupalSettings.canvas.componentSelectionUtils for extensions to use.
 */
export function useComponentSelection() {
  const layout = useAppSelector(selectLayout);
  const navigate = useNavigate();
  const location = useLocation();
  const dispatch = useAppDispatch();
  const selection = useAppSelector(selectSelection);
  const selectedComponent = useAppSelector(selectSelectedComponentUuid);
  // Temp. while multi-selection is still in development
  const isDevMode = useAppSelector(selectDevMode);

  // Remove the /component/:componentId from the URL, keeping other parts intact
  const updateUrlToNoSelection = useCallback(() => {
    // Remove /component/:componentId
    const cleanPath = removeComponentFromPathname(location.pathname);

    // If the path ends up exactly "/editor/", remove trailing slash for consistency
    const finalPath =
      cleanPath.endsWith('/') && cleanPath !== '/'
        ? cleanPath.slice(0, -1)
        : cleanPath;

    navigate({
      pathname: finalPath,
      search: location.search,
      hash: location.hash,
    });
  }, [navigate, location]);

  // Robustly update the URL so /component/:componentId is always at the end, preserving everything before it
  const updateUrlToSelectedComponent = useCallback(
    (componentId?: string) => {
      if (!componentId) return;
      const { pathname, search, hash } = location;
      const newPath = setComponentInPathname(pathname, componentId);
      navigate({
        pathname: newPath,
        search,
        hash,
      });
    },
    [navigate, location],
  );

  /**
   * Central function to update component selection in Redux and manage URL state
   * @param newSelection - An array of component UUIDs, a single UUID string, or undefined to clear selection
   * @param currentLayoutState - Optional current layout state to use for URL construction
   */
  const updateSelectionInRedux = useCallback(
    (
      newSelection: string[] | string | undefined,
      currentLayoutState?: RegionNode[],
    ) => {
      const layoutToSearch = currentLayoutState || layout;
      // Handle different input types
      let selectionArray: string[] = [];

      if (typeof newSelection === 'string') {
        // Single component selection
        selectionArray = [newSelection];
      } else if (Array.isArray(newSelection)) {
        // Array of selections
        selectionArray = [...newSelection];
      }

      // Check if components are siblings
      const areComponentsSiblings = areConsecutiveSiblings(
        layoutToSearch,
        selectionArray,
      );

      // Update Redux state with the new selection and siblings status
      dispatch(
        setSelection({
          items: selectionArray,
          consecutive: areComponentsSiblings,
        }),
      );

      // Update URL based on selection state
      if (selectionArray.length === 1) {
        // Single selection - update URL to include the component ID
        const componentUuid = selectionArray[0];
        updateUrlToSelectedComponent(componentUuid);
      } else if (selectionArray.length === 0) {
        // No selection - remove component from URL
        updateUrlToNoSelection();
      } else {
        // Multiple selection - remove component from URL
        updateUrlToNoSelection();
      }
    },
    [dispatch, layout, updateUrlToNoSelection, updateUrlToSelectedComponent],
  );

  const setSelectedComponent = useCallback(
    (componentUuid: string, currentLayoutState?: RegionNode[]) => {
      updateSelectionInRedux(componentUuid, currentLayoutState);
    },
    [updateSelectionInRedux],
  );

  // Clear selection in state and update URL
  const unsetSelectedComponent = useCallback(() => {
    updateSelectionInRedux(undefined);
  }, [updateSelectionInRedux]);

  // Handle component selection with support for cmd+click (multi-selection)
  // and preventing parent-child selection
  const handleComponentSelection = useCallback(
    (componentUuid: string, metaKey: boolean) => {
      // Temp. while multi-selection is still in development
      if (!isDevMode) {
        updateSelectionInRedux(componentUuid);
        return;
      }

      // 'normal' click just set the selected component
      if (!metaKey) {
        updateSelectionInRedux(componentUuid);
        return;
      }

      // 'cmd+click' for multiple selection
      if (metaKey) {
        if (selection.items.length === 0) {
          // No items selected yet, just select this one
          updateSelectionInRedux(componentUuid);
          return;
        }

        // If the component is already in the selection, handle removal
        if (selection.items.includes(componentUuid)) {
          // Remove the item from selection
          const newSelection = selection.items.filter(
            (item) => item !== componentUuid,
          );
          updateSelectionInRedux(newSelection);
          return;
        }

        // At this point, we're adding a new component to the selection
        // We have the first selection item
        if (selection.items.length === 1 && selectedComponent) {
          // Check for parent-child relationships before adding
          const newSelection = filterParentChildRelationships(
            layout,
            selection.items,
            componentUuid,
          );

          // Now we need to decide what to do based on the new selection
          updateSelectionInRedux(newSelection);
          return;
        }

        // We already have multiple items selected
        // Filter the selection to remove any parent/child conflicts
        const newSelection = filterParentChildRelationships(
          layout,
          selection.items,
          componentUuid,
        );

        // Replace the entire selection with our filtered selection
        updateSelectionInRedux(newSelection);
        return;
      }
    },
    [
      isDevMode,
      updateSelectionInRedux,
      selection.items,
      selectedComponent,
      layout,
    ],
  );

  const componentSelectionUtils = {
    setSelectedComponent,
    unsetSelectedComponent,
    updateUrlToNoSelection,
    updateUrlToSelectedComponent,
    handleComponentSelection,
    selectedComponent,
    updateSelectionInRedux,
  };

  // Add to Drupal settings for external access by extensions etc
  canvasSettings.componentSelectionUtils = componentSelectionUtils;

  return componentSelectionUtils;
}

export default useComponentSelection;
