import { useCallback, useEffect, useMemo, useState } from 'react';
import parse from 'html-react-parser';
import { PlusIcon } from '@radix-ui/react-icons';
import { Box, Button, Flex, Select, Text } from '@radix-ui/themes';

import Dialog, { DialogFieldLabel } from '@/components/Dialog';
import ErrorBoundary from '@/components/error/ErrorBoundary';
import {
  AccordionDetails,
  AccordionRoot,
} from '@/components/form/components/Accordion';
import TemplateList from '@/components/list/TemplateList';
import PermissionCheck from '@/components/PermissionCheck';
import { extractErrorMessageFromApiResponse } from '@/features/error-handling/error-handling';
import {
  useCreateContentTemplateMutation,
  useGetViewModesQuery,
} from '@/services/componentAndLayout';
import { getBaseUrl, getCanvasSettings } from '@/utils/drupal-globals';

import type { ModeData } from '@/services/componentAndLayout';

const canvasSettings = getCanvasSettings();

const Templates = () => {
  const [openEntityTypes, setOpenEntityTypes] = useState<string[]>([
    'content-types',
  ]);

  const onClickHandler = (categoryName: string) => {
    setOpenEntityTypes((state) => {
      if (!state.includes(categoryName)) {
        return [...state, categoryName];
      }
      return state.filter((stateName) => stateName !== categoryName);
    });
  };

  return (
    <>
      <PermissionCheck hasPermission="contentTemplates">
        <Flex direction="column" mb="4">
          <AddTemplateButton />
        </Flex>
      </PermissionCheck>
      <AccordionRoot value={openEntityTypes}>
        <AccordionDetails
          value="content-types"
          title="Content types"
          onTriggerClick={() => onClickHandler('content-types')}
        >
          <ErrorBoundary title="An unexpected error has occurred while fetching templates.">
            <TemplateList />
          </ErrorBoundary>
        </AccordionDetails>
      </AccordionRoot>
    </>
  );
};

const AddTemplateButton = () => {
  const [isOpen, setIsOpen] = useState(false);

  return (
    <>
      <Button
        data-testid="big-add-template-button"
        variant="soft"
        size="1"
        onClick={() => setIsOpen(true)}
      >
        <PlusIcon />
        Add new template
      </Button>
      {isOpen && <AddTemplateDialog isOpen={isOpen} setIsOpen={setIsOpen} />}
    </>
  );
};

interface TemplateDialogProps {
  isOpen: boolean;
  setIsOpen: (isOpen: boolean) => void;
  contentType?: string | null;
  entityType?: string | null;
}

const AddTemplateDialog = ({
  isOpen,
  setIsOpen,
  contentType = null,
  entityType = 'node',
}: TemplateDialogProps) => {
  const [selectedContentType, setSelectedContentType] = useState<string | null>(
    contentType,
  );
  const [selectedEntityType, setSelectedEntityType] = useState<string | null>(
    entityType,
  );
  const [selectedViewMode, setSelectedViewMode] = useState<string | null>();

  const [createTemplate, { reset, isSuccess, isError, error }] =
    useCreateContentTemplateMutation();
  const {
    data,
    error: getViewModeError,
    isError: isGetViewModeError,
  } = useGetViewModesQuery();
  const drupalBaseUrl = getBaseUrl();

  const redirectToSelectedAfterCreation = useCallback(() => {
    // For now, we are using window.location.href to force a full page reload
    // to ensure all state is reset when switching entities. Later we can use navigate:
    // navigate(`${baseUrl}canvas/${entityType}/${contentType}/${viewMode}`);
    setTimeout(() => {
      window.location.href = `${drupalBaseUrl}canvas/template/${selectedEntityType}/${selectedContentType}/${selectedViewMode}`;
    }, 100);
  }, [
    drupalBaseUrl,
    selectedContentType,
    selectedEntityType,
    selectedViewMode,
  ]);

  useEffect(() => {
    if (isSuccess) {
      setIsOpen(false);
      redirectToSelectedAfterCreation();
      setSelectedContentType('');
      setSelectedViewMode(null);
      setSelectedEntityType('null');
      reset();
    }
  }, [isSuccess, reset, setIsOpen, redirectToSelectedAfterCreation]);

  useEffect(() => {
    setSelectedViewMode('');
  }, [selectedContentType]);

  const handleCreateTemplate = () => {
    createTemplate({
      entityType: selectedEntityType,
      bundle: selectedContentType,
      viewMode: selectedViewMode,
    });
  };

  const availableTemplates = useMemo(
    () =>
      selectedEntityType && selectedContentType
        ? Object.entries(
            data?.[selectedEntityType]?.[selectedContentType] || {},
          ).filter(([mode, modeData]) => {
            const typedModeData = modeData as unknown as ModeData;
            return mode === 'full' && !typedModeData.hasTemplate;
          }).length
        : null,
    [data, selectedContentType, selectedEntityType],
  );

  const entityBundleLabels =
    typeof entityType === 'string' &&
    canvasSettings?.entityTypeLabels?.[entityType];
  const bundleLabelType = typeof entityBundleLabels;
  return (
    <Dialog
      open={isOpen}
      title="Add new template"
      headerClose={false}
      error={
        isError || isGetViewModeError
          ? {
              title: isError
                ? 'Failed to add template'
                : 'Failed to load view modes',
              message: parse(
                extractErrorMessageFromApiResponse(error || getViewModeError),
              ),
              resetButtonText: 'Try again',
              onReset: isError ? handleCreateTemplate : undefined,
            }
          : undefined
      }
      footer={{
        cancelText: 'Cancel',
        confirmText: 'Add new template',
        isConfirmDisabled: !(selectedContentType && selectedViewMode),
        onConfirm: handleCreateTemplate,
      }}
      onOpenChange={(open) => setIsOpen(open)}
    >
      <Flex
        direction="column"
        data-testid="canvas-manage-library-add-template-content"
        p="0"
        gap="2"
        mb="2"
      >
        <Box>
          <Text as="p" size="1" color="gray">
            Creates a new template for a content type using existing view modes
            as the template name.
          </Text>
        </Box>
        {!contentType && (
          <Flex direction="column" gap="2">
            <Box>
              <DialogFieldLabel htmlFor="content-type">
                Content type
              </DialogFieldLabel>
            </Box>
            <Select.Root
              name="content-type"
              required
              value={selectedContentType || undefined}
              onValueChange={(value) => setSelectedContentType(value as string)}
              size="1"
            >
              <Select.Trigger
                id="content-type"
                placeholder="Select content type…"
                style={{ width: '100%' }}
              />
              <Select.Content>
                {bundleLabelType === 'object' &&
                  Object.entries(entityBundleLabels).map(([type, label]) => (
                    <Select.Item key={type} value={type}>
                      {String(label)}
                    </Select.Item>
                  ))}
              </Select.Content>
            </Select.Root>
          </Flex>
        )}
        <Flex direction="column" gap="2">
          <Box>
            <DialogFieldLabel htmlFor="template-name">
              Template
            </DialogFieldLabel>
          </Box>
          <Select.Root
            name="template name"
            required
            disabled={!selectedContentType}
            value={selectedViewMode || ''}
            onValueChange={(value) => setSelectedViewMode(value)}
            size="1"
          >
            <Select.Trigger
              id="template-name"
              placeholder={
                availableTemplates === 0
                  ? 'No more available templates'
                  : 'Available templates…'
              }
              style={{ width: '100%' }}
              disabled={!selectedContentType}
            />
            <Select.Content>
              <Select.Group>
                {!!selectedEntityType &&
                  selectedContentType &&
                  Object.entries(
                    data?.[selectedEntityType]?.[selectedContentType] || {},
                  ).map(([mode, modeData]) => {
                    const typedModeData = modeData as unknown as ModeData;
                    if (mode === 'full') {
                      return (
                        <Select.Item
                          key={mode}
                          value={mode}
                          disabled={
                            mode !== 'full' || typedModeData.hasTemplate
                          }
                        >
                          {typedModeData.label}{' '}
                          {typedModeData.hasTemplate &&
                            '(template already exists)'}{' '}
                          {mode !== 'full' && '(support coming soon)'}
                        </Select.Item>
                      );
                    }
                    return null;
                  })}
              </Select.Group>
            </Select.Content>
          </Select.Root>
        </Flex>
      </Flex>
    </Dialog>
  );
};

export default Templates;
