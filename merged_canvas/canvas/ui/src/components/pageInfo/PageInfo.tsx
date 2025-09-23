import { useEffect, useState } from 'react';
import { useErrorBoundary } from 'react-error-boundary';
import { NavLink, useLocation, useParams } from 'react-router-dom';
import TemplateIcon from '@assets/icons/template.svg?react';
import {
  ChevronLeftIcon,
  CodeIcon,
  CubeIcon,
  FileTextIcon,
  HomeIcon,
  SectionIcon,
  StackIcon,
} from '@radix-ui/react-icons';
import {
  Badge,
  Button,
  ChevronDownIcon,
  Flex,
  Popover,
} from '@radix-ui/themes';

import { useAppDispatch, useAppSelector } from '@/app/hooks';
import ErrorCard from '@/components/error/ErrorCard';
import Navigation from '@/components/navigation/Navigation';
import PageStatus from '@/components/pageStatus/PageStatus';
import Panel from '@/components/Panel';
import { selectCodeComponentProperty } from '@/features/code-editor/codeEditorSlice';
import {
  extractHomepagePathFromStagedConfig,
  selectHomepagePath,
  selectHomepageStagedConfigExists,
  setHomepagePath,
} from '@/features/configuration/configurationSlice';
import { selectLayout } from '@/features/layout/layoutModelSlice';
import {
  DEFAULT_REGION,
  selectEditorFrameContext,
  selectPreviouslyEdited,
} from '@/features/ui/uiSlice';
import useDebounce from '@/hooks/useDebounce';
import useEditorNavigation from '@/hooks/useEditorNavigation';
import { useEntityTitle } from '@/hooks/useEntityTitle';
import { useTemplateCaption } from '@/hooks/useTemplateCaption';
import {
  useCreateContentMutation,
  useDeleteContentMutation,
  useGetContentListQuery,
  useGetStagedConfigQuery,
  useSetStagedConfigMutation,
} from '@/services/content';
import { pageDataFormApi } from '@/services/pageDataForm';
import { getBaseUrl, getCanvasSettings } from '@/utils/drupal-globals';
import { getQueryErrorMessage } from '@/utils/error-handling';
import {
  removeComponentFromPathname,
  removeRegionFromPathname,
} from '@/utils/route-utils';

import type { ReactElement } from 'react';
import type { ContentStub } from '@/types/Content';

interface PageType {
  [key: string]: ReactElement;
}

const iconMap: PageType = {
  Page: <FileTextIcon />,
  ContentType: <StackIcon />,
  ComponentName: <CodeIcon />,
  GlobalPatternName: <SectionIcon />,
  Homepage: <HomeIcon />,
  Template: <TemplateIcon />,
};

const canvasSettings = getCanvasSettings();

export const HOMEPAGE_CONFIG_ID = 'canvas_set_homepage';

const PageInfo = () => {
  const { showBoundary } = useErrorBoundary();
  const { setEditorEntity } = useEditorNavigation();
  const {
    regionId: focusedRegion = DEFAULT_REGION,
    entityType,
    entityId,
  } = useParams();
  const codeComponentName = useAppSelector(selectCodeComponentProperty('name'));
  const isCodeEditor = codeComponentName !== '';
  const layout = useAppSelector(selectLayout);
  const previouslyEdited = useAppSelector(selectPreviouslyEdited);
  const dispatch = useAppDispatch();
  const focusedRegionName = layout.find(
    (region) => region.id === focusedRegion,
  )?.name;
  const location = useLocation();
  const title = useEntityTitle();

  // Check if we're on a template route
  const isTemplateRoute =
    useAppSelector(selectEditorFrameContext) === 'template';
  const templateCaption = useTemplateCaption();

  const [searchTerm, setSearchTerm] = useState<string>('');
  const debouncedSearchTerm = useDebounce(searchTerm, 300);
  // @todo: https://www.drupal.org/i/3513566 this needs to be generalized to check all content entity types.
  const canCreatePages =
    !!canvasSettings.contentEntityCreateOperations?.canvas_page?.canvas_page;
  const {
    data: pageItems,
    isLoading: isPageItemsLoading,
    error: pageItemsError,
    isSuccess: isGetPageItemsSuccess,
  } = useGetContentListQuery({
    // @todo Generalize in https://www.drupal.org/i/3498525
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
  const [isCurrentPageHomepage, setIsCurrentPageHomepage] =
    useState<boolean>(false);

  useEffect(() => {
    if (isGetPageItemsSuccess) {
      // Check if the current page is the homepage.
      const homepage = pageItems.find(
        (page) => page.internalPath === homepagePath,
      );
      setIsCurrentPageHomepage(
        entityType === 'canvas_page' && entityId === String(homepage?.id),
      );
    }
  }, [entityId, entityType, homepagePath, isGetPageItemsSuccess, pageItems]);

  useEffect(() => {
    if (isGetStagedUpdateSuccess) {
      dispatch(
        setHomepagePath(extractHomepagePathFromStagedConfig(homepageConfig)),
      );
    }
  }, [dispatch, homepageConfig, isGetStagedUpdateSuccess]);

  function handleNewPage() {
    createContent({
      entity_type: 'canvas_page',
    });
  }

  const [deleteContent, { error: deleteContentError }] =
    useDeleteContentMutation();
  const [setHomepage, { error: setHomepageError }] =
    useSetStagedConfigMutation();

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
    if (entityType === 'canvas_page' && entityId === pageToDeleteId) {
      if (homepage) {
        setEditorEntity('canvas_page', String(homepage.id));
      } else if (remainingPages.length > 0) {
        // It's possible there is no homepage set yet right now, so we redirect to the first remaining page.
        setEditorEntity('canvas_page', String(remainingPages[0].id));
      } else {
        // If there are no more pages, redirect out of Canvas.
        // @todo: Remove this in https://www.drupal.org/i/3506434
        //   since deleting the homepage in Canvas should be disallowed in that issue so remaining pages should never be 0.
        setTimeout(() => {
          window.location.href = baseUrl;
        }, 100);
      }
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

  // @todo https://www.drupal.org/i/3498525 should generalize this to all eligible content entity types.
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

  return (
    <Flex gap="2" align="center">
      {isCodeEditor && previouslyEdited.path ? (
        <NavLink
          to={{
            pathname: previouslyEdited.path,
          }}
          aria-label={`Back`}
          title={`${previouslyEdited.name}`}
          onClick={() => {
            // Fetch a new version of the page data form as it has been
            // unmounted and the cached versions won't reflect any AJAX updates
            // to the form.
            dispatch(
              pageDataFormApi.util.invalidateTags([
                { type: 'PageDataForm', id: 'FORM' },
              ]),
            );
          }}
        >
          <Button color="sky" variant="soft" size="1">
            <ChevronLeftIcon />
            Back
          </Button>
        </NavLink>
      ) : null}
      {focusedRegion === DEFAULT_REGION ? (
        <Popover.Root>
          <Popover.Trigger>
            <Button
              color="gray"
              variant="soft"
              size="1"
              data-testid="canvas-navigation-button"
            >
              <Flex gap="2" align="center">
                {isCodeEditor ? (
                  <>
                    <CodeIcon />
                    {codeComponentName}
                  </>
                ) : isTemplateRoute ? (
                  <>
                    {iconMap['Template']}
                    {templateCaption || 'Template'}
                  </>
                ) : (
                  <>
                    {isCurrentPageHomepage
                      ? iconMap['Homepage']
                      : iconMap['Page']}
                    {title !== undefined
                      ? title
                        ? title
                        : 'Untitled page'
                      : 'No page selected'}
                  </>
                )}
                <ChevronDownIcon />
              </Flex>
            </Button>
          </Popover.Trigger>
          <Popover.Content
            size="2"
            width="100vw"
            maxWidth="400px"
            asChild
            align="center"
          >
            <Panel className="canvas-app" mt="4">
              {!pageItemsError && (
                <Navigation
                  loading={isPageItemsLoading}
                  items={pageItems || []}
                  showNew={canCreatePages}
                  onNewPage={handleNewPage}
                  onSearch={setSearchTerm}
                  onSelect={handleOnSelect}
                  onDuplicate={handleDuplication}
                  onSetHomepage={handleSetHomepage}
                  onDelete={handleDeletePage}
                />
              )}
              {pageItemsError && (
                <ErrorCard
                  title="An unexpected error has occurred while loading pages."
                  error={getQueryErrorMessage(pageItemsError)}
                />
              )}
            </Panel>
          </Popover.Content>
        </Popover.Root>
      ) : (
        <NavLink
          to={{
            pathname: removeComponentFromPathname(
              removeRegionFromPathname(location.pathname),
            ),
          }}
          aria-label="Back to Content region"
          onClick={() => {
            // Fetch a new version of the page data form as it has been
            // unmounted and the cached versions won't reflect any AJAX updates
            // to the form.
            dispatch(
              pageDataFormApi.util.invalidateTags([
                { type: 'PageDataForm', id: 'FORM' },
              ]),
            );
          }}
        >
          <Badge color="grass" size="2">
            <ChevronLeftIcon />
            <CubeIcon />
            {focusedRegionName}
          </Badge>
        </NavLink>
      )}

      {entityId && <PageStatus />}
    </Flex>
  );
};

export default PageInfo;
