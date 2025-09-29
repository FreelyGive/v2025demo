import clsx from 'clsx';
import { useLocation, useNavigate, useParams } from 'react-router-dom';
import DropIcon from '@assets/icons/drop.svg?react';
import { CardStackPlusIcon, PersonIcon } from '@radix-ui/react-icons';
import * as Menubar from '@radix-ui/react-menubar';
import { Box, Button, Flex, Grid, Tooltip } from '@radix-ui/themes';

import { useAppSelector } from '@/app/hooks';
import AIToggleButton from '@/components/aiExtension/AiToggleButton';
import PreviewControls from '@/components/PreviewControls';
import UnpublishedChanges from '@/components/review/UnpublishedChanges';
import ContentPreviewSelector from '@/components/templates/ContentPreviewSelector';
import UndoRedo from '@/components/UndoRedo';
import { selectEditorFrameContext } from '@/features/ui/uiSlice';
import { useGetPreviewContentEntitiesQuery } from '@/services/componentAndLayout';
import { getBaseUrl, getDrupalSettings } from '@/utils/drupal-globals';

import PageInfo from '../pageInfo/PageInfo';

import styles from './Topbar.module.css';

const PREVIOUS_URL_STORAGE_KEY = 'CanvasPreviousURL';

const Topbar = () => {
  const navigate = useNavigate();
  const location = useLocation();
  const { entityType, bundle, viewMode, previewEntityId } = useParams();
  const isPreview = location.pathname.includes('/preview');
  const isEditor = location.pathname.includes('/editor');
  const isSegments = location.pathname.includes('/segments');
  const isTemplateEditorContext =
    useAppSelector(selectEditorFrameContext) === 'template';
  const drupalBaseUrl = getBaseUrl();

  let hasAiExtensionAvailable = false;
  let hasPersonalizeExtensionAvailable = false;

  const drupalSettings = getDrupalSettings();
  if (
    drupalSettings?.canvas?.aiExtensionAvailable &&
    (drupalSettings.canvas as any).permissions?.useCanvasAi === true
  ) {
    hasAiExtensionAvailable = true;
  }
  if (
    drupalSettings &&
    drupalSettings.canvas.personalizationExtensionAvailable
  ) {
    hasPersonalizeExtensionAvailable = true;
  }

  // Fetch preview content entities for template routes
  const { data: previewEntities = {} } = useGetPreviewContentEntitiesQuery(
    {
      entityTypeId: entityType || '',
      bundle: bundle || '',
    },
    {
      skip: !isTemplateEditorContext || !entityType || !bundle,
    },
  );

  // Handle preview entity selection change
  const handlePreviewEntityChange = (selectedEntityId: string) => {
    if (entityType && bundle && viewMode) {
      // @todo: Change to use navigate() when we can do full FE routing.
      setTimeout(() => {
        // Use a timeout to ensure that RTK query cleans up its subscriptions first before navigating away.
        // Without this timeout, RTK throws an error because it tries to make a request following cache invalidation while
        // the window.location.href is in progress.
        window.location.href = `${drupalBaseUrl}canvas/template/${entityType}/${bundle}/${viewMode}/${selectedEntityId}`;
      }, 100);
    }
  };

  const backHref =
    window.sessionStorage.getItem(PREVIOUS_URL_STORAGE_KEY) ?? '/';

  // Must be wide enough to accommodate all the buttons that can appear in the top left and top right. The two
  // columns must be the same width so that the center column is always centered to the whole window.
  const leftRightColumnWidth = '300px';

  return (
    <Menubar.Root data-testid="canvas-topbar" asChild>
      <Box
        className={clsx(styles.root, styles.topBar, {
          [styles.inPreview]: isPreview,
        })}
        pr="4"
      >
        <Grid
          columns="max-content 1fr max-content"
          gap="0"
          width="auto"
          height="100%"
        >
          <Flex align="center" gap="2" width={leftRightColumnWidth}>
            <Tooltip content="Exit Drupal Canvas">
              <a
                href={backHref}
                aria-labelledby="back-to-previous-label"
                className={clsx(styles.topBarButton, styles.exitButton)}
              >
                <span className="visually-hidden" id="back-to-previous-label">
                  Exit Drupal Canvas
                </span>
                <DropIcon
                  className={styles.drupalLogo}
                  height="24"
                  width="auto"
                />
              </a>
            </Tooltip>
            {!isPreview && hasAiExtensionAvailable && (
              <>
                <div className={clsx(styles.verticalDivider)}></div>
                <AIToggleButton />
              </>
            )}
            {!isPreview && hasPersonalizeExtensionAvailable && (
              <>
                <Button
                  variant={isEditor ? 'soft' : 'ghost'}
                  color={isEditor ? 'blue' : 'gray'}
                  onClick={() => navigate('/editor')}
                >
                  <CardStackPlusIcon />
                  <span className={isEditor ? '' : 'visually-hidden'}>
                    Builder
                  </span>
                </Button>
                <Button
                  variant={isSegments ? 'soft' : 'ghost'}
                  color={isSegments ? 'blue' : 'gray'}
                  onClick={() => navigate('/segments')}
                >
                  <PersonIcon />
                  <span className={isSegments ? '' : 'visually-hidden'}>
                    Segments
                  </span>
                </Button>
              </>
            )}
            <div className={clsx(styles.verticalDivider)}></div>
            {!isPreview && (
              <>
                <UndoRedo />
              </>
            )}
          </Flex>
          <Flex align="center" justify="center" gap="2">
            <PageInfo />
            {isTemplateEditorContext && (
              <ContentPreviewSelector
                items={previewEntities}
                selectedItemId={previewEntityId}
                onSelectionChange={handlePreviewEntityChange}
              />
            )}
          </Flex>
          <Flex
            align="center"
            justify="end"
            gap="2"
            width={leftRightColumnWidth}
          >
            <PreviewControls isPreview={isPreview} />
            <UnpublishedChanges />
          </Flex>
        </Grid>
      </Box>
    </Menubar.Root>
  );
};

export default Topbar;
