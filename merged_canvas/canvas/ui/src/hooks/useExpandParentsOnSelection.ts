import { useEffect } from 'react';

import { useAppDispatch, useAppSelector } from '@/app/hooks';
import { selectLayout } from '@/features/layout/layoutModelSlice';
import { findNodeParents } from '@/features/layout/layoutUtils';
import { removeCollapsedLayers, selectSelection } from '@/features/ui/uiSlice';

/**
 * Hook that watches the user's selection and ensures that any component that is selected is visible in the
 * layers panel (by finding and expanding all of its parents).
 */
const useExpandParentsOnSelection = () => {
  const dispatch = useAppDispatch();
  const layout = useAppSelector(selectLayout);

  const selection = useAppSelector(selectSelection);

  useEffect(() => {
    // For each selected item, find its parents and remove them from the collapsed layers array.
    selection.items.forEach((id) => {
      const parents = findNodeParents(layout, id);
      if (parents) {
        dispatch(removeCollapsedLayers(parents));
      }
    });
  }, [selection, layout, dispatch]);
};

export default useExpandParentsOnSelection;
