import { useEffect, useRef } from 'react';
import { useErrorBoundary } from 'react-error-boundary';

import { useAppDispatch, useAppSelector } from '@/app/hooks';
import {
  selectLayout,
  selectModel,
  selectUpdatePreview,
} from '@/features/layout/layoutModelSlice';
import ComponentHtmlMapProvider from '@/features/layout/preview/DataToHtmlMapContext';
import Viewport from '@/features/layout/preview/Viewport';
import { selectPageData } from '@/features/pageData/pageDataSlice';
import {
  selectPreviewBackgroundUpdate,
  selectPreviewHtml,
} from '@/features/pagePreview/previewSlice';
import {
  selectEditorFrameContext,
  selectSelectedComponentUuid,
} from '@/features/ui/uiSlice';
import { useEntityTitle } from '@/hooks/useEntityTitle';
import { usePostTemplateLayoutMutation } from '@/services/componentAndLayout';
import { contentApi } from '@/services/content';
import {
  selectUpdateComponentLoadingState,
  usePostPreviewMutation,
} from '@/services/preview';

import type React from 'react';

interface PreviewProps {}

const Preview: React.FC<PreviewProps> = () => {
  const layout = useAppSelector(selectLayout);
  const updatePreview = useAppSelector(selectUpdatePreview);
  const model = useAppSelector(selectModel);
  const selectedComponent = useAppSelector(selectSelectedComponentUuid);
  const backgroundUpdate = useAppSelector(selectPreviewBackgroundUpdate);
  const selectedComponentId = selectedComponent || 'noop';
  const entity_form_fields = useAppSelector(selectPageData);
  const title = useEntityTitle();
  const previousEntityFormTitle = useRef(title);
  // @todo stop hardcoding `path` after https://drupal.org/i/3503446.
  const previousEntityFormAlias = useRef(entity_form_fields['path[0][alias]']);

  const [postPreview, { isLoading: isFetching }] = usePostPreviewMutation({
    fixedCacheKey: 'editorFramePreview',
  });
  const [postTemplatePreview, { isLoading: isTemplateFetching }] =
    usePostTemplateLayoutMutation({
      fixedCacheKey: 'editorFrameTemplatePreview',
    });
  const isPatching = useAppSelector((state) =>
    selectUpdateComponentLoadingState(state, selectedComponentId),
  );
  const dispatch = useAppDispatch();
  const frameSrcDoc = useAppSelector(selectPreviewHtml);
  const { showBoundary } = useErrorBoundary();
  const editorFrameContext = useAppSelector(selectEditorFrameContext);

  useEffect(() => {
    const sendPreviewRequest = async () => {
      try {
        // Trigger the mutation
        await postPreview({ layout, model, entity_form_fields }).unwrap();
      } catch (err) {
        showBoundary(err);
      }
    };
    const sendTemplatePreviewRequest = async () => {
      try {
        // Trigger the mutation
        await postTemplatePreview({
          layout,
          model,
          entity_form_fields,
        }).unwrap();
      } catch (err) {
        showBoundary(err);
      }
    };
    if (updatePreview) {
      // Specifically when updating the Title or Alias, the page list used in the navigator must be re-fetched so that
      // it can display those updated values.
      let invalidatePageList = false;
      if (
        title !== previousEntityFormTitle.current ||
        // @todo stop hardcoding `path` after https://drupal.org/i/3503446.
        entity_form_fields['path[0][alias]'] !== previousEntityFormAlias.current
      ) {
        invalidatePageList = true;
        previousEntityFormTitle.current = title;
        // @todo stop hardcoding `path` after https://drupal.org/i/3503446.
        previousEntityFormAlias.current = entity_form_fields['path[0][alias]'];
      }

      if (editorFrameContext === 'template') {
        sendTemplatePreviewRequest().then(() => {});
      } else {
        sendPreviewRequest().then(() => {
          if (invalidatePageList) {
            dispatch(
              contentApi.util.invalidateTags([{ type: 'Content', id: 'LIST' }]),
            );
          }
        });
      }
    }
  }, [
    layout,
    model,
    postPreview,
    entity_form_fields,
    updatePreview,
    showBoundary,
    dispatch,
    title,
    postTemplatePreview,
    editorFrameContext,
  ]);

  return (
    <ComponentHtmlMapProvider>
      <Viewport
        frameSrcDoc={frameSrcDoc}
        isFetching={
          (isFetching || isPatching || isTemplateFetching) && !backgroundUpdate
        }
      />
    </ComponentHtmlMapProvider>
  );
};
export default Preview;
