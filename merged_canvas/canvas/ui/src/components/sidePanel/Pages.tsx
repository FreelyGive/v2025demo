import { useEffect, useState } from 'react';
import { useErrorBoundary } from 'react-error-boundary';
import { useParams } from 'react-router-dom';

import { useAppDispatch, useAppSelector } from '@/app/hooks';
import PageList from '@/components/pageInfo/PageList';
import {
  extractHomepagePathFromStagedConfig,
  selectHomepagePath,
  selectHomepageStagedConfigExists,
  setHomepagePath,
} from '@/features/configuration/configurationSlice';
import useDebounce from '@/hooks/useDebounce';
import useEditorNavigation from '@/hooks/useEditorNavigation';
import {
  useCreateContentMutation,
  useDeleteContentMutation,
  useGetContentListQuery,
  useGetStagedConfigQuery,
  useSetStagedConfigMutation,
} from '@/services/content';
import { getBaseUrl, getCanvasSettings } from '@/utils/drupal-globals';

import type { ContentStub } from '@/types/Content';

const canvasSettings = getCanvasSettings();
export const HOMEPAGE_CONFIG_ID = 'canvas_set_homepage';

const Pages = () => {
  const { showBoundary } = useErrorBoundary();
  const { setEditorEntity } = useEditorNavigation();
  const dispatch = useAppDispatch();
  const { entityType, entityId } = useParams();
  const [searchTerm, setSearchTerm] = useState<string>('');
  const debouncedSearchTerm = useDebounce(searchTerm, 300);

  const canCreatePages =
    !!canvasSettings.contentEntityCreateOperations?.canvas_page?.canvas_page;

  const {
    data: pageItems,
    isLoading: isPageItemsLoading,
    error: pageItemsError,
  } = useGetContentListQuery({
    entityType: 'canvas_page',
    search: debouncedSearchTerm,
  });

  const baseUrl = getBaseUrl();
  const [
    createContent,
    {
      data: createContentData,
      error: createContentError,
      isSuccess: isCreateContentSuccess,
    },
  ] = useCreateContentMutation();
  const homepagePath = useAppSelector(selectHomepagePath);
  const homepageStagedConfigExists = useAppSelector(
    selectHomepageStagedConfigExists,
  );
  const { data: homepageConfig, isSuccess: isGetStagedUpdateSuccess } =
    useGetStagedConfigQuery(HOMEPAGE_CONFIG_ID, {
      // Only fetch the homepage staged config if it exists to avoid
      // unnecessary API calls that return 404s.
      skip: !homepageStagedConfigExists,
    });

  const [deleteContent, { error: deleteContentError }] =
    useDeleteContentMutation();
  const [setHomepage, { error: setHomepageError }] =
    useSetStagedConfigMutation();

  function handleNewPage() {
    createContent({
      entity_type: 'canvas_page',
    });
  }

  async function handleDeletePage(item: ContentStub) {
    // Find another page to redirect to (filtering out the page being deleted)
    const remainingPages =
      pageItems?.filter((page) => page.id !== item.id) || [];
    const pageToDeleteId = String(item.id);
    await deleteContent({
      entityType: 'canvas_page',
      entityId: pageToDeleteId,
    });
    const homepage = pageItems?.find(
      (page) => page.internalPath === homepagePath,
    );
    // If the current page is the one being deleted, redirect to the homepage.
    if (homepage) {
      setEditorEntity('canvas_page', String(homepage.id));
    } else if (remainingPages.length > 0) {
      // It's possible there is no homepage set yet right now, so we redirect to the first remaining page.
      setEditorEntity('canvas_page', String(remainingPages[0].id));
    } else {
      // If there are no more pages, redirect out of Canvas.
      setTimeout(() => {
        window.location.href = baseUrl;
      }, 100);
    }
    // Keep local storage tidy and clear out the array of collapsed layers for the deleted item.
    window.localStorage.removeItem(
      `Canvas.collapsedLayers.canvas_page.${pageToDeleteId}`,
    );
  }

  function handleDuplication(item: ContentStub) {
    createContent({
      entity_type: 'canvas_page',
      entity_id: String(item.id),
    });
  }

  function handleOnSelect(item: ContentStub) {
    setEditorEntity('canvas_page', String(item.id));
  }

  function handleSetHomepage(item: ContentStub) {
    const { internalPath } = item;
    dispatch(setHomepagePath(internalPath));
    setHomepage({
      data: {
        id: HOMEPAGE_CONFIG_ID,
        label: 'Update homepage',
        target: 'system.site',
        actions: [
          {
            name: 'simpleConfigUpdate',
            input: {
              'page.front': internalPath,
            },
          },
        ],
      },
      autoSaves: '',
    });
  }

  useEffect(() => {
    if (isGetStagedUpdateSuccess) {
      dispatch(
        setHomepagePath(extractHomepagePathFromStagedConfig(homepageConfig)),
      );
    }
  }, [dispatch, homepageConfig, isGetStagedUpdateSuccess]);

  useEffect(() => {
    if (isCreateContentSuccess) {
      setEditorEntity(
        createContentData.entity_type,
        createContentData.entity_id,
      );
    }
  }, [isCreateContentSuccess, createContentData, setEditorEntity]);

  useEffect(() => {
    if (createContentError) {
      showBoundary(createContentError);
    }
  }, [createContentError, showBoundary]);

  useEffect(() => {
    if (deleteContentError) {
      showBoundary(deleteContentError);
    }
  }, [deleteContentError, showBoundary]);

  useEffect(() => {
    if (setHomepageError) {
      showBoundary(setHomepageError);
    }
  }, [setHomepageError, showBoundary]);

  // Determine the currently selected page
  const selectedPageId = entityType === 'canvas_page' ? entityId : undefined;

  return (
    <PageList
      pageItems={pageItems || []}
      isPageItemsLoading={isPageItemsLoading}
      pageItemsError={pageItemsError ? String(pageItemsError) : null}
      homepagePath={homepagePath}
      selectedPageId={selectedPageId}
      canCreatePages={canCreatePages}
      onNewPage={handleNewPage}
      onDeletePage={handleDeletePage}
      onDuplicatePage={handleDuplication}
      onSelectPage={handleOnSelect}
      onSetHomepage={handleSetHomepage}
      onSearch={setSearchTerm}
    />
  );
};

export default Pages;
