import { useParams } from 'react-router';

import { useAppSelector } from '@/app/hooks';
import { selectPageData } from '@/features/pageData/pageDataSlice';
import { getCanvasSettings } from '@/utils/drupal-globals';

/**
 * Centralized hook to get the entity title from form fields.
 * Accepts optional value overrides.
 */
export function useEntityTitle(): string | undefined {
  const { entityType } = useParams();
  const entityFormFields = useAppSelector(selectPageData);
  const canvasSettings = getCanvasSettings();

  const titleLabel =
    entityType && canvasSettings.entityTypeKeys[entityType]?.label
      ? canvasSettings.entityTypeKeys[entityType].label
      : 'title';
  return entityFormFields[`${titleLabel}[0][value]`];
}
