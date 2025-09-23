import { useParams } from 'react-router';

/**
 * Hook to get the currently edited template ID from the current route as a string in the format
 * "entityType.bundle.viewMode"
 */
export function useGetEditedTemplateId(): string | undefined {
  const { entityType, bundle, viewMode } = useParams();

  if (!entityType || !bundle || !viewMode) {
    return undefined;
  }
  return `${entityType}.${bundle}.${viewMode}`;
}
