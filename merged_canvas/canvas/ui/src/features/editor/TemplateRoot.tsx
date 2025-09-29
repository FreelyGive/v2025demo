import { useEffect } from 'react';
import { useLocation, useNavigate, useParams } from 'react-router-dom';
import { ExclamationTriangleIcon, PlusIcon } from '@radix-ui/react-icons';
import { Button, Flex, Text } from '@radix-ui/themes';

import { useAppDispatch } from '@/app/hooks';
import PrimaryPanel from '@/components/sidePanel/PrimaryPanel';
import {
  EditorFrameContext,
  setEditorFrameContext,
} from '@/features/ui/uiSlice';
import { useGetContentTemplatesQuery } from '@/services/componentAndLayout';
import { getBaseUrl } from '@/utils/drupal-globals';

import styles from './TemplateRoot.module.css';

const TemplateRoot = () => {
  const navigate = useNavigate();
  const { entityType, bundle, viewMode } = useParams();
  const dispatch = useAppDispatch();

  const { data: templatesData, isSuccess } = useGetContentTemplatesQuery();
  const location = useLocation();

  useEffect(() => {
    if (isSuccess && templatesData && entityType && bundle && viewMode) {
      const entityTemplates = templatesData[entityType];
      const bundleData = entityTemplates?.bundles?.[bundle];
      const viewModeData = bundleData?.viewModes?.[viewMode];
      const suggestedEntityId = viewModeData?.suggestedPreviewEntityId;

      if (suggestedEntityId) {
        navigate(
          `/template/${entityType}/${bundle}/${viewMode}/${suggestedEntityId}`,
          {
            replace: true,
          },
        );
      }
    }
  }, [isSuccess, templatesData, entityType, bundle, viewMode, navigate]);

  useEffect(() => {
    dispatch(setEditorFrameContext(EditorFrameContext.TEMPLATE));
    return () => {
      dispatch(setEditorFrameContext(EditorFrameContext.NONE));
    };
  }, [dispatch]);

  // Get entity type and bundle names for the button
  const getEntityInfo = () => {
    if (!templatesData || !entityType || !bundle) {
      return { entityLabel: 'content', bundleLabel: 'item' };
    }

    const entityTemplates = templatesData[entityType];
    const bundleData = entityTemplates?.bundles?.[bundle];

    return {
      entityLabel: entityTemplates?.label || entityType,
      bundleLabel: bundleData?.label || bundle,
    };
  };

  const { bundleLabel } = getEntityInfo();
  const baseUrl = getBaseUrl();

  const handleCreateContent = () => {
    // Navigate to Drupal's content creation page
    // Add Drupal destination to return to Canvas UI after creating content.
    const createUrl = `${baseUrl}${entityType}/add/${bundle}?destination=/canvas${location.pathname}${location.search}`;
    window.location.href = createUrl;
  };

  return (
    <>
      <PrimaryPanel />
      <Flex
        className={styles.noContentNotice}
        align="center"
        justify="center"
        direction="column"
        gap="2"
        pr="calc(var(--sidebar-left-width) + var(--side-menu-width))"
      >
        <ExclamationTriangleIcon width="16" height="16" />
        <Text size="1" weight="bold">
          No preview content is available
        </Text>
        <Text size="1">
          To build a template, you must have at least one{' '}
          <strong>{bundleLabel}</strong>.
        </Text>
        <Button onClick={handleCreateContent} size="1" variant="solid" mt="2">
          <PlusIcon /> Add new {bundleLabel}
        </Button>
      </Flex>
    </>
  );
};

export default TemplateRoot;
