import { useEffect, useRef } from 'react';
import { useParams } from 'react-router';

import { useAppDispatch, useAppSelector } from '@/app/hooks';
import { selectModel } from '@/features/layout/layoutModelSlice';
import {
  removeCollapsedLayers,
  selectCollapsedLayers,
  setCollapsedLayers,
} from '@/features/ui/uiSlice';

/**
 * Hook to ensure that when the collapsed layers are updated, they are stored in localStorage. The allows the user's
 * choice of layers to persist across page reloads etc.
 */
const useSyncCollapsedLayersLocalStorage = () => {
  const dispatch = useAppDispatch();
  const { entityType, entityId } = useParams();
  const model = useAppSelector(selectModel);
  const collapsedLayers = useAppSelector(selectCollapsedLayers);
  const isInitialized = useRef(false);
  // Key the storage by edited entity
  const key = `Canvas.collapsedLayers.${entityType}.${entityId}`;

  // Initialize Redux state from local storage - run only once on component mount
  useEffect(() => {
    const storedData = window.localStorage.getItem(key);
    if (storedData) {
      try {
        const parsedData = JSON.parse(storedData) as string[];
        if (Array.isArray(parsedData) && parsedData.length > 0) {
          // Only set if there's actual data to restore
          dispatch(setCollapsedLayers(parsedData));
        }
      } catch (e) {
        dispatch(setCollapsedLayers([]));
        console.error('Error parsing collapsed layers from localStorage:', e);
      }
    }
    // This should only run once on mount, not on model changes
  }, [dispatch, key]);

  // Clean up stale UUIDs when model changes
  useEffect(() => {
    if (!isInitialized.current || !model || Object.keys(model).length === 0) {
      return; // Don't attempt cleanup until initialized and model is loaded
    }

    // When the model changes, clean out collapsed items that no longer exist in the model.
    // The split on '/' handles slotIDs by just checking their parent components UUID.
    const uuidsToRemove = collapsedLayers.filter((uuid) => {
      return !Object.keys(model).includes(uuid.split('/')[0]);
    });

    if (uuidsToRemove.length > 0) {
      dispatch(removeCollapsedLayers(uuidsToRemove));
    }
  }, [dispatch, model, collapsedLayers]);

  // Update local storage on state change so collapsed layers persist across browser refresh etc.
  useEffect(() => {
    // Only start syncing to localStorage after the first load is complete
    if (isInitialized.current) {
      window.localStorage.setItem(key, JSON.stringify(collapsedLayers));
    } else {
      isInitialized.current = true;
    }
  }, [collapsedLayers, key]);
};

export default useSyncCollapsedLayersLocalStorage;
