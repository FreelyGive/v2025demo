import { useState } from 'react';
import clsx from 'clsx';
import * as Collapsible from '@radix-ui/react-collapsible';
import {
  ExclamationTriangleIcon,
  OpenInNewWindowIcon,
} from '@radix-ui/react-icons';
import {
  Box,
  ChevronDownIcon,
  Flex,
  Heading,
  Separator,
  Text,
} from '@radix-ui/themes';

import ChangeIcon from '@/components/review/changes/ChangeIcon';
import { getBaseUrl } from '@/utils/drupal-globals';

import type { ErrorResponse } from '@/services/pendingChangesApi';

import detailsStyle from '@/components/form/components/AccordionAndDetails.module.css';
import style from '@/components/review/ReviewErrors.module.css';

interface ReviewErrorsProps {
  errorState: ErrorResponse | undefined;
}

interface EntityError {
  detail: string;
  meta?: {
    label?: string;
    entity_type?: string;
    entity_id?: string | number;
  };
  entityLabel: string;
  source: {
    pointer?: string;
  };
}

interface ErrorsByEntity {
  [key: string]: EntityError[];
}

interface ErrorGroupProps {
  errorGroup: EntityError[];
}

const baseUrl = getBaseUrl();

const ErrorGroup: React.FC<ErrorGroupProps> = ({ errorGroup }) => {
  const [isOpen, setIsOpen] = useState(true);
  return (
    <Collapsible.Root
      data-testid="error-details"
      open={isOpen}
      onOpenChange={setIsOpen}
    >
      <Collapsible.Trigger className={style.collapseButton}>
        <Flex px="1" py="2" gap="2" align="center">
          <ChangeIcon
            entityType={errorGroup[0]?.meta?.entity_type || ''}
            entityId={errorGroup[0]?.meta?.entity_id || ''}
          />
          <Heading as="h4" size="1" weight="regular">
            {errorGroup[0].entityLabel}
          </Heading>
          <ChevronDownIcon
            className={clsx(style.chevron, !isOpen && style.closed)}
            aria-hidden
          />
        </Flex>
      </Collapsible.Trigger>

      <Collapsible.Content
        className={clsx(detailsStyle.content, detailsStyle.detailsContent)}
      >
        {errorGroup.map((error: EntityError, ix: number) => {
          let componentId = '';
          let errorPath =
            error?.meta?.entity_type &&
            error?.meta?.entity_id &&
            `${baseUrl}canvas/editor/${error.meta.entity_type}/${error.meta.entity_id}`;

          if (typeof error?.source?.pointer === 'string') {
            const sourcePointerParts = error.source.pointer.split('.');
            // Find the UUID in the pointer.
            componentId = sourcePointerParts
              .reverse()
              .filter((part) =>
                part.match(
                  /^[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[1-5][0-9a-fA-F]{3}-[89abAB][0-9a-fA-F]{3}-[0-9a-fA-F]{12}$/,
                ),
              )?.[0];
          }

          if (errorPath && componentId) {
            errorPath = `${errorPath}/component/${componentId}`;
          }

          return (
            <Flex px="5" py="1" gap="2" align="start" key={ix}>
              <Flex pt="2.5px">
                <ExclamationTriangleIcon color="red" />
              </Flex>
              <Text size="1" data-testid="publish-error-detail">
                {error.detail}{' '}
                {errorPath && (
                  <a
                    data-testid="publish-error-link"
                    href={errorPath}
                    target="_blank"
                    rel="noreferrer"
                  >
                    {
                      <OpenInNewWindowIcon
                        color="blue"
                        width="16"
                        height="16"
                        style={{ position: 'relative', top: '4px' }}
                      />
                    }
                  </a>
                )}
              </Text>
            </Flex>
          );
        })}
      </Collapsible.Content>
    </Collapsible.Root>
  );
};

const ReviewErrors: React.FC<ReviewErrorsProps> = ({ errorState }) => {
  const [isOpen, setIsOpen] = useState(true);

  if (errorState?.errors?.length) {
    // Organize errors by entity label.
    const errorsByEntity: ErrorsByEntity = errorState.errors.reduce(
      (carry, error) => {
        const label = error.meta?.label;
        if (label) {
          if (!carry[label]) {
            carry[label] = [];
          }
          carry[label].push({
            ...error,
            entityLabel: label,
          });
        }
        return carry;
      },
      {} as ErrorsByEntity,
    );
    return (
      <Box
        data-testid="canvas-review-publish-errors"
        maxWidth="360px"
        className={style.reviewErrors}
      >
        <Box px="4" pb="2" pt="5">
          <Collapsible.Root open={isOpen} onOpenChange={setIsOpen}>
            <Collapsible.Trigger className={style.collapseButton}>
              <Flex gap="2" mb="1" align="center">
                <ExclamationTriangleIcon color="red" />
                <Heading as="h3" size="1" mb="0">
                  {errorState.errors.length} Error
                  {errorState.errors.length > 1 ? 's' : ''}
                </Heading>
                <ChevronDownIcon
                  className={clsx(style.chevron, !isOpen && style.closed)}
                />
              </Flex>
            </Collapsible.Trigger>

            <Collapsible.Content
              className={clsx(
                detailsStyle.content,
                detailsStyle.detailsContent,
              )}
            >
              {Object.values(errorsByEntity).map(
                (errorGroup: EntityError[], ix: number) => (
                  <ErrorGroup key={ix} errorGroup={errorGroup} />
                ),
              )}
            </Collapsible.Content>
          </Collapsible.Root>
        </Box>
        <Separator my="3" size="4" />
      </Box>
    );
  }
  return null;
};

export default ReviewErrors;
