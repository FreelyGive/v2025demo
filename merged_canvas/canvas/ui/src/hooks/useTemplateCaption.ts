import { useParams } from 'react-router-dom';

import { useAppSelector } from '@/app/hooks';
import { selectEditorFrameContext } from '@/features/ui/uiSlice';
import { useGetContentTemplatesQuery } from '@/services/componentAndLayout';

function getBundleName(
  templatesData: any,
  entityType: string,
  bundle: string,
): string {
  if (!templatesData || !entityType || !bundle) {
    return bundle?.charAt(0).toUpperCase() + bundle?.slice(1) || '';
  }
  const entityTemplates = templatesData[entityType];
  const bundleData = entityTemplates?.bundles?.[bundle];
  return bundleData?.label || bundle.charAt(0).toUpperCase() + bundle.slice(1);
}

function getViewModeName(
  templatesData: any,
  entityType: string,
  bundle: string,
  viewMode: string,
): string {
  if (!templatesData || !entityType || !bundle || !viewMode) {
    return `${viewMode} template`;
  }
  const entityTemplates = templatesData[entityType];
  const bundleData = entityTemplates?.bundles?.[bundle];
  const viewModeData = bundleData?.viewModes?.[viewMode];
  return viewModeData?.viewModeLabel
    ? `${viewModeData.viewModeLabel} template`
    : `${viewMode} template`;
}

export function useTemplateCaption(): string | undefined {
  const isTemplateRoute =
    useAppSelector(selectEditorFrameContext) === 'template';
  const { entityType, bundle, viewMode } = useParams();
  const { data: templatesData } = useGetContentTemplatesQuery(undefined, {
    skip: !isTemplateRoute,
  });
  if (isTemplateRoute && entityType && bundle && viewMode) {
    return `${getBundleName(templatesData, entityType, bundle)} - ${getViewModeName(templatesData, entityType, bundle, viewMode)}`;
  }
  return undefined;
}
