import { useEffect, useMemo, useState } from 'react';
import { useErrorBoundary } from 'react-error-boundary';
import { useParams } from 'react-router';

import { useAppDispatch, useAppSelector } from '@/app/hooks';
import PublishReview from '@/components/review/PublishReview';
import {
  selectConflicts,
  selectErrors,
  selectPreviousPendingChanges,
  setConflicts,
  setPreviousPendingChanges,
} from '@/components/review/PublishReview.slice';
import { setUpdatePreview } from '@/features/layout/layoutModelSlice';
import {
  selectPageData,
  setInitialPageData,
} from '@/features/pageData/pageDataSlice';
import { selectSelectedComponentUuid } from '@/features/ui/uiSlice';
import { componentAndLayoutApi } from '@/services/componentAndLayout';
import { contentApi } from '@/services/content';
import {
  CONFLICT_CODE,
  pendingChangesApi,
  useDiscardPendingChangeMutation,
  useGetAllPendingChangesQuery,
  usePublishAllPendingChangesMutation,
} from '@/services/pendingChangesApi';
import {
  usePostPreviewMutation,
  useUpdateComponentMutation,
} from '@/services/preview';
import { findInChanges } from '@/utils/function-utils';

import type { PendingChanges } from '@/services/pendingChangesApi';
import type { UnpublishedChange } from '@/types/Review';

const REFETCH_INTERVAL_MS = 10000;

const UnpublishedChanges = () => {
  const previousPendingChanges = useAppSelector(selectPreviousPendingChanges);
  const conflicts = useAppSelector(selectConflicts);
  const errorResponse = useAppSelector(selectErrors);
  const selectedComponent = useAppSelector(selectSelectedComponentUuid);
  const [publishAllChanges, { isLoading: isPublishing }] =
    usePublishAllPendingChangesMutation();
  const [discardChange, { isLoading: isDiscarding }] =
    useDiscardPendingChangeMutation();
  const [, { isLoading: isUpdatingComponent }] = useUpdateComponentMutation({
    fixedCacheKey: selectedComponent,
  });
  const [, { isLoading: isUpdatingPreview }] = usePostPreviewMutation({
    fixedCacheKey: 'editorFramePreview',
  });
  const [pollingInterval, setPollingInterval] =
    useState<number>(REFETCH_INTERVAL_MS);
  const {
    data: changes,
    error,
    refetch,
    isFetching,
  } = useGetAllPendingChangesQuery(undefined, {
    pollingInterval: pollingInterval,
    skipPollingIfUnfocused: true,
  });
  const { entityType, entityId } = useParams();
  const dispatch = useAppDispatch();
  const { showBoundary } = useErrorBoundary();
  const entity_form_fields = useAppSelector(selectPageData);

  // If either the selected component or the preview layout is being updated, disable the Publish button.
  const isUpdating = isUpdatingComponent || isUpdatingPreview;

  useEffect(() => {
    if (error) {
      showBoundary(error);
    }
  }, [error, showBoundary]);

  const unpublishedChanges: UnpublishedChange[] = useMemo(
    () =>
      Object.entries(changes || {})
        .map(([pointer, change]) => ({
          ...change,
          pointer,
        }))
        .sort((a, b) => b.updated - a.updated),
    [changes],
  );

  useEffect(() => {
    if (previousPendingChanges) refetch();
  }, [previousPendingChanges, refetch]);

  const onOpenChangeHandler = (open: boolean): void => {
    if (open) {
      refetch().then(() => {
        setPollingInterval(0);
      });
    } else {
      setPollingInterval(REFETCH_INTERVAL_MS);
    }
  };

  const onPublishClick = async (selectedChanges: UnpublishedChange[]) => {
    if (selectedChanges?.length) {
      const changesToPublish = selectedChanges.reduce((acc, change) => {
        acc[change.pointer] = {
          entity_type: change.entity_type,
          entity_id: change.entity_id,
          data_hash: change.data_hash,
          langcode: change.langcode,
          owner: change.owner,
          label: change.label,
          updated: change.updated,
        };
        return acc;
      }, {} as PendingChanges);
      const isCurrentChanged = findInChanges(
        changesToPublish,
        entityId,
        entityType,
      );
      const changedCodeComponentIds = Object.values(changesToPublish)
        .filter((change) => change.entity_type === 'js_component')
        .map((change) => change.entity_id);

      await publishAllChanges(changesToPublish);

      if (isCurrentChanged) {
        // Update the isPublished and isNew status.
        dispatch(
          componentAndLayoutApi.util.updateQueryData(
            'getPageLayout',
            undefined,
            (draft) => {
              draft.isPublished = true;
              draft.isNew = false;
            },
          ),
        );

        // Pause updating the preview/POSTing to Drupal for this action.
        dispatch(setUpdatePreview(false));
        // Invalidate the layout query cache of the current entity to ensure that the autoSaves hash is updated
        // ALSO, For example Drupal has hook_entity_presave which allows altering an entity before it is saved.
        // Canvas will not be aware of any changes made in custom code here, therefore if Canvas doesn't re-request
        // after publishing, the auto-save request could wipe out any changes that were made in
        // any hook_entity_presave code
        dispatch(
          componentAndLayoutApi.util.invalidateTags([{ type: 'Layout' }]),
        );

        // Avoid the "The content has either been modified by another user, or
        // you have already submitted modifications. As a result, your changes
        // cannot be saved" error.
        if ('changed' in entity_form_fields) {
          // Pause updating the preview/POSTing to Drupal for this action.
          dispatch(setUpdatePreview(false));
          dispatch(
            setInitialPageData({
              ...entity_form_fields,
              changed: Math.floor(new Date().getTime() / 1000),
            }),
          );
        }
      }

      // After publishing, the list of other pages might be out of date where the pages' title/alias has been updated.
      dispatch(
        contentApi.util.invalidateTags([{ type: 'Content', id: 'LIST' }]),
      );

      if (changedCodeComponentIds.length) {
        // Invalidate cache of all changed code component entities. This is
        // critical to prevent data loss, which would otherwise occur in the
        // following scenario:
        // 1. A code component change is auto-saved, then published.
        // 2. As a result, the auto-save entry gets deleted on the backend.
        // 3. The auto-save that occurred previously invalidated the auto-save
        //    query cache, so fetching data for the code component will correctly
        //    see the 204 response that is now returned.
        // 4. That will cause a fallback to the canonical source of the config
        //    entity. This is why we need to invalidate the cache for those.
        //    In the absence of this, a stale version would be returned, which
        //    would get auto-saved if anything changes, resulting to the loss of
        //    changes in step 1. E.g. with newly created and first-time published
        //    code components, this would wipe out all data.
        dispatch(
          componentAndLayoutApi.util.invalidateTags(
            changedCodeComponentIds.map((id) => ({
              type: 'CodeComponents',
              id,
            })),
          ),
        );
      }
    }
  };

  const onDiscardClick = async (selectedChange: UnpublishedChange) => {
    if (!selectedChange) return;

    await discardChange(selectedChange);

    // The discardPendingChange mutation will automatically reload the page
    // when it is successful, so there is nothing more to do here until a better
    // solution is implemented.

    // After discarding, refresh the list to ensure UI is up to date
    refetch();
  };

  if (!isFetching && conflicts && conflicts.length) {
    window.setTimeout(() => {
      conflicts.forEach((conflict) => {
        if (conflict.code === CONFLICT_CODE.UNEXPECTED) {
          dispatch(
            pendingChangesApi.util.updateQueryData(
              'getAllPendingChanges',
              undefined,
              (draft) => {
                if (previousPendingChanges) {
                  draft[conflict.source.pointer] = {
                    ...previousPendingChanges[conflict.source.pointer],
                    hasConflict: true,
                  };
                }
              },
            ),
          );
        }
        if (conflict.code === CONFLICT_CODE.EXPECTED) {
          dispatch(
            pendingChangesApi.util.updateQueryData(
              'getAllPendingChanges',
              undefined,
              (draft) => {
                if (draft[conflict.source.pointer]) {
                  draft[conflict.source.pointer].hasConflict = true;
                }
              },
            ),
          );
        }
      });
      dispatch(setConflicts());
      dispatch(setPreviousPendingChanges());
    }, 100);
  }

  return (
    <PublishReview
      isUpdating={isUpdating}
      isFetching={isFetching}
      changes={unpublishedChanges}
      errors={errorResponse}
      onOpenChangeCallback={onOpenChangeHandler}
      onPublishClick={onPublishClick}
      onDiscardClick={onDiscardClick}
      isPublishing={isPublishing}
      isDiscarding={isDiscarding}
    />
  );
};

export default UnpublishedChanges;
