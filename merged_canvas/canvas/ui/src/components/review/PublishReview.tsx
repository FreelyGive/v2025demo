import { useEffect, useMemo, useState } from 'react';
import clsx from 'clsx';
import { CheckIcon, Cross2Icon } from '@radix-ui/react-icons';
import {
  Box,
  Button,
  Checkbox,
  Flex,
  Heading,
  Popover,
  ScrollArea,
  Spinner,
  Text,
} from '@radix-ui/themes';

import PermissionCheck from '@/components/PermissionCheck';
import ReviewErrors from '@/components/review/ReviewErrors';
import { Divider } from '@/features/code-editor/component-data/FormElement';

import ChangeList from './changes/ChangeList';

import type { ErrorResponse } from '@/services/pendingChangesApi';
import type {
  UnpublishedChange,
  UnpublishedChangeGroups,
} from '@/types/Review';

import styles from './PublishReview.module.css';

export const DEFAULT_TITLE = 'Unpublished changes';

interface PublishReviewProps {
  title?: string;
  changes: UnpublishedChange[];
  errors?: ErrorResponse | undefined;
  onPublishClick: (selectedChanges: UnpublishedChange[]) => void;
  onDiscardClick: (selectedChange: UnpublishedChange) => void;
  onViewClick?: (change: UnpublishedChange) => void;
  onOpenChangeCallback: (open: boolean) => void;
  isPublishing: boolean;
  isDiscarding: boolean;
  isFetching: boolean; // indicates if the list of autosaved changes is being fetched
  isUpdating: boolean; // indicates if the preview is being updated
}

const PublishReview: React.FC<PublishReviewProps> = ({
  title = DEFAULT_TITLE,
  changes,
  errors,
  onPublishClick,
  onDiscardClick,
  onViewClick,
  onOpenChangeCallback,
  isPublishing = false,
  isDiscarding = false,
  isFetching = false,
  isUpdating = false,
}) => {
  // State to manage the open/close state of the popover
  const [isOpen, setIsOpen] = useState<boolean>(false);

  // Single source to determine if something is happening
  const isBusy = isUpdating || isPublishing || isDiscarding || isFetching;

  // State to manage selected changes
  const [selectedChanges, setSelectedChanges] = useState<UnpublishedChange[]>(
    [],
  );

  // Memoize the selected changes to avoid unnecessary re-renders
  const allSelected = useMemo(() => {
    if (!changes?.length) return false;
    return selectedChanges?.length === changes?.length ? true : 'indeterminate';
  }, [changes, selectedChanges]);

  // Used to display the `Published` state, which resets on new selections
  const [hasPublished, setHasPublished] = useState<boolean>(false);
  useEffect(() => {
    if (!isPublishing && !errors?.errors?.length) {
      setHasPublished(true);
      setSelectedChanges([]);
    }
  }, [isPublishing, errors]);
  useEffect(() => {
    if (selectedChanges.length > 0) {
      setHasPublished(false);
    }
  }, [selectedChanges.length]);

  // The trigger button text changes based on the pending changes
  const triggerButtonText = useMemo(() => {
    if (!changes?.length) return 'No changes';
    if (changes.length === 1) return 'Review 1 change';
    return `Review ${changes.length} changes`;
  }, [changes]);

  // The button caption changes based on the state of the review
  const buttonText = useMemo(() => {
    if (isPublishing) return 'Publishing';
    if (isBusy) return 'Please wait';
    if (hasPublished) return 'Published';
    if (!changes?.length) return 'No changes available';
    if (!selectedChanges?.length) return 'No items selected';
    return `Publish ${selectedChanges.length} selected`;
  }, [isPublishing, isBusy, hasPublished, changes, selectedChanges]);

  const groups: UnpublishedChangeGroups = useMemo(() => {
    if (!changes?.length) return {};
    return changes.reduce((acc, change) => {
      const key = change.entity_type ?? 'unknown';
      if (!acc[key]) {
        acc[key] = [];
      }
      acc[key].push(change);
      return acc;
    }, {} as UnpublishedChangeGroups);
  }, [changes]);

  // Remove selections if all are selected, otherwise select all
  const handleSelectAll = () => {
    if (isBusy) return;
    if (allSelected === true) {
      setSelectedChanges([]);
    } else {
      setSelectedChanges(changes);
    }
  };

  // Publish the selected changes
  const handlePublishClick = () => {
    if (onPublishClick && selectedChanges?.length) {
      onPublishClick(selectedChanges);
    }
  };

  const handleDiscardClick = (change: UnpublishedChange) => {
    setSelectedChanges(
      selectedChanges.filter((c) => c.pointer !== change.pointer),
    );
    onDiscardClick(change);
  };

  const onOpenChangeHandler = (open: boolean): void => {
    if (isFetching) return;
    setHasPublished(false);
    setIsOpen(open);
    onOpenChangeCallback(open);
  };

  return (
    <Popover.Root open={isOpen} onOpenChange={onOpenChangeHandler}>
      <Popover.Trigger>
        <Button
          variant="solid"
          disabled={!changes?.length || isBusy}
          data-testid="canvas-publish-review"
          className={clsx(styles.triggerButton, {
            [styles.disableClick]: isBusy,
            [styles.noChanges]: !changes?.length,
          })}
        >
          {triggerButtonText}
        </Button>
      </Popover.Trigger>
      <Popover.Content
        asChild
        data-testid="canvas-publish-reviews-content"
        width="100vw"
        maxWidth="360px"
      >
        <Box p="0" m="0">
          <Flex p="4" align="center" justify="between" width="100%">
            <Box>
              <Heading as="h3" size="3" weight="medium">
                {title}
              </Heading>
            </Box>
            <Box>
              <Popover.Close className={styles.close} aria-label="Close">
                <Cross2Icon />
              </Popover.Close>
            </Box>
          </Flex>
          <Divider />
          <Box
            p="4"
            className={isBusy || !changes?.length ? styles.disabled : ''}
          >
            <Text as="label" size="1" className={styles.selectAll}>
              <Flex align="center" gap="2">
                <Checkbox
                  id="select-all-changes"
                  disabled={isBusy}
                  checked={allSelected === true}
                  onCheckedChange={handleSelectAll}
                  size="1"
                  aria-label="Select all changes"
                  data-testid="canvas-publish-review-select-all"
                />
                Select All
              </Flex>
            </Text>
          </Box>
          <Divider />
          <Box className={isBusy ? styles.disabled : ''}>
            <ScrollArea
              style={{ maxHeight: '380px', width: '100%' }}
              type="scroll"
            >
              <ReviewErrors errorState={errors} />
              <Box px="4" pt="4">
                <Text size="1">
                  {changes.length
                    ? `${selectedChanges.length} of ${changes?.length ?? 0} changes selected`
                    : 'All changes published!'}
                </Text>
              </Box>
              <Box px="4" pt="4">
                {changes?.length > 0 && (
                  <>
                    <ChangeList
                      groups={groups}
                      isBusy={isBusy}
                      selectedChanges={selectedChanges}
                      setSelectedChanges={setSelectedChanges}
                      onDiscardClick={handleDiscardClick}
                      onViewClick={onViewClick}
                    />
                  </>
                )}
              </Box>
            </ScrollArea>
          </Box>
          <Divider />
          <PermissionCheck hasPermission="publishChanges">
            <Flex p="4" justify="end" align="center" gap="2" width="100%">
              <Button
                className={
                  isPublishing || hasPublished ? styles.buttonBlue : ''
                }
                disabled={!onPublishClick || isBusy || !selectedChanges?.length}
                size="1"
                variant="solid"
                onClick={handlePublishClick}
              >
                {buttonText}
                <Spinner loading={isPublishing}>
                  {(isPublishing || hasPublished) && <CheckIcon />}
                </Spinner>
              </Button>
            </Flex>
          </PermissionCheck>
        </Box>
      </Popover.Content>
    </Popover.Root>
  );
};

export default PublishReview;
