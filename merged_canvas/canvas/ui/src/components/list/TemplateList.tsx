import { useEffect, useState } from 'react';
import { useErrorBoundary } from 'react-error-boundary';
import NewTabIcon from '@assets/icons/new-tab.svg?react';
import { ContextMenu, Flex, Skeleton, Text } from '@radix-ui/themes';

import Dialog from '@/components/Dialog';
import SidebarFolder from '@/components/sidePanel/SidebarFolder';
import SidebarNode from '@/components/sidePanel/SidebarNode';
import UnifiedMenu from '@/components/UnifiedMenu';
import { useGetEditedTemplateId } from '@/hooks/useGetEditedTemplateId';
import {
  useDeleteContentTemplateMutation,
  useGetContentTemplatesQuery,
} from '@/services/componentAndLayout';

import type {
  TemplateInBundle,
  TemplateViewMode,
} from '@/services/componentAndLayout';

import nodeStyles from '@/components/sidePanel/SidebarNode.module.css';

type BundleListItemProps = {
  bundle: TemplateInBundle;
};
const TemplateList = () => {
  const { showBoundary } = useErrorBoundary();

  const { data, isLoading, isFetching, error } = useGetContentTemplatesQuery();
  useEffect(() => {
    if (error) {
      showBoundary(error);
    }
  }, [error, showBoundary]);

  return (
    <Skeleton
      loading={isLoading || isFetching}
      height="1.2rem"
      width="100%"
      my="3"
    >
      {!!data?.node?.bundles &&
        Object.entries(data.node.bundles).map(([bundleKey, bundle]) => (
          <BundleListItem key={bundleKey} bundle={bundle} />
        ))}
    </Skeleton>
  );
};

const BundleListItem = ({ bundle }: BundleListItemProps) => {
  const [isOpen, setIsOpen] = useState(true);
  const menuItems = [];

  if (bundle.editFieldsUrl) {
    menuItems.push(
      <UnifiedMenu.Item
        key="edit-fields"
        onClick={() => window.open(bundle.editFieldsUrl, '_blank')}
      >
        Edit fields
        <Flex ml="auto" align="end">
          <NewTabIcon />
        </Flex>
      </UnifiedMenu.Item>,
    );
  }
  if (bundle.deleteUrl) {
    if (menuItems.length > 0) {
      menuItems.push(<UnifiedMenu.Separator key="pre-delete-separator" />);
    }

    menuItems.push(
      <UnifiedMenu.Item
        key="delete-bundle"
        color="red"
        onClick={() => window.open(bundle.deleteUrl, '_blank')}
      >
        Delete content type
        <Flex align="end">
          <NewTabIcon />
        </Flex>
      </UnifiedMenu.Item>,
    );
  }

  if (menuItems.length > 0) {
    menuItems.unshift(<UnifiedMenu.Separator key="bundle-label-separator" />);
    menuItems.unshift(
      <UnifiedMenu.Label key="bundle-label">{bundle.label}</UnifiedMenu.Label>,
    );
  }

  return (
    <SidebarFolder
      name={bundle.label}
      menuItems={menuItems.length ? menuItems : undefined}
      isOpen={isOpen}
      onOpenChange={setIsOpen}
      className={nodeStyles.contextualAccordionVariant}
    >
      <Flex pl="0" direction="column">
        {Object.entries(bundle.viewModes).map(([key, viewMode]) => (
          <TemplateListItem key={`${viewMode.id}-${key}`} viewMode={viewMode} />
        ))}
      </Flex>
    </SidebarFolder>
  );
};

const TemplateListItem = ({ viewMode }: { viewMode: TemplateViewMode }) => {
  const [deleteDialogOpen, setDeleteDialogOpen] = useState(false);
  const [
    deleteContentTemplate,
    { isLoading, error, isError, isSuccess, reset },
  ] = useDeleteContentTemplateMutation();
  const selectedTemplateId = useGetEditedTemplateId();

  const handleDelete = async () => {
    await deleteContentTemplate(viewMode.id);
  };

  const deleteDialog = (
    <Dialog
      onOpenChange={(open) => {}}
      open={deleteDialogOpen}
      title="Delete template"
      description={`Are you sure you want to delete "${viewMode.label}"? This action cannot be undone.`}
      error={
        isError
          ? {
              title: 'Failed to delete template',
              message: `An error ${
                'status' in error ? '(HTTP ' + error.status + ')' : ''
              } occurred while deleting the template. Please check the browser console for more details.`,
              resetButtonText: 'Try again',
              onReset: handleDelete,
            }
          : undefined
      }
      footer={{
        cancelText: 'Cancel',
        confirmText: 'Delete',
        onConfirm: handleDelete,
        isConfirmDisabled: false,
        isConfirmLoading: isLoading,
        isDanger: true,
        onCancel: () => setDeleteDialogOpen(false),
      }}
    ></Dialog>
  );

  useEffect(() => {
    if (isSuccess) {
      setDeleteDialogOpen(false);
      reset();
    }
  }, [isSuccess, reset]);

  useEffect(() => {
    if (isError) {
      console.error('Failed to delete template:', error);
    }
  }, [isError, error]);

  const menuItems = (
    <>
      <UnifiedMenu.Label>{viewMode.viewModeLabel}</UnifiedMenu.Label>
      <UnifiedMenu.Separator />
      <UnifiedMenu.Item
        color="red"
        onClick={() => {
          setDeleteDialogOpen(true);
        }}
      >
        Delete template
      </UnifiedMenu.Item>
    </>
  );

  return (
    <>
      <ContextMenu.Root key={viewMode.id}>
        <ContextMenu.Trigger>
          <SidebarNode
            key={viewMode.id}
            href={`/canvas/template/${viewMode.entityType}/${viewMode.bundle}/${viewMode.viewMode}/${viewMode.suggestedPreviewEntityId || ''}`}
            title={`${viewMode.viewModeLabel} template`}
            variant="template"
            dropdownMenuContent={
              <UnifiedMenu.Content menuType="dropdown">
                {menuItems}
              </UnifiedMenu.Content>
            }
            selected={selectedTemplateId === viewMode.id}
          />
        </ContextMenu.Trigger>
        <UnifiedMenu.Content
          onClick={(e) => e.stopPropagation()}
          menuType="context"
          align="start"
          side="right"
        >
          {menuItems}
        </UnifiedMenu.Content>
      </ContextMenu.Root>
      {deleteDialogOpen && deleteDialog}
    </>
  );
};

export default TemplateList;
