/**
 * @file
 * Loads the code component and global asset library data.
 *
 * If there is an auto-save entry for either, it will be preferred over the
 * canonical entity source.
 */

import { useEffect, useState } from 'react';
import { useErrorBoundary } from 'react-error-boundary';

import {
  useGetAssetLibraryQuery,
  useGetAutoSaveQuery as useGetAutoSaveQueryAssetLibrary,
} from '@/services/assetLibrary';
import {
  useGetAutoSaveQuery as useGetAutoSaveQueryCodeComponent,
  useGetCodeComponentQuery,
} from '@/services/componentAndLayout';

import type {
  AssetLibrary,
  CodeComponentSerialized,
} from '@/types/CodeComponent';

const ASSET_LIBRARY_ID = 'global';

type CodeEditorData = {
  dataCodeComponent: CodeComponentSerialized | undefined;
  dataGlobalAssetLibrary: AssetLibrary | undefined;
  isLoading: boolean;
  isSuccess: boolean;
};

const useGetCodeEditorData = (
  currentComponentId: string,
  { skip }: { skip?: boolean } = { skip: false },
): CodeEditorData => {
  const { showBoundary } = useErrorBoundary();

  // Returned values are tracked in local states.
  const [dataCodeComponent, setDataCodeComponent] = useState<
    CodeEditorData['dataCodeComponent'] | undefined
  >(undefined);
  const [dataGlobalAssetLibrary, setDataGlobalAssetLibrary] = useState<
    CodeEditorData['dataGlobalAssetLibrary'] | undefined
  >(undefined);
  const [isLoading, setIsLoading] =
    useState<CodeEditorData['isLoading']>(false);
  const [isSuccess, setIsSuccess] =
    useState<CodeEditorData['isSuccess']>(false);

  // Get the auto-saved data of the code component if it exists.
  const {
    currentData: dataGetAutoSaveCodeComponent,
    error: errorGetAutoSaveCodeComponent,
    isFetching: isLoadingGetAutoSaveCodeComponent,
    isSuccess: isSuccessGetAutoSaveCodeComponent,
  } = useGetAutoSaveQueryCodeComponent(currentComponentId, {
    skip,
  });

  // Get the code component data, but skip if auto-saved data exists.
  const {
    currentData: dataGetCodeComponent,
    error: errorGetCodeComponent,
    isFetching: isLoadingGetCodeComponent,
    isSuccess: isSuccessGetCodeComponent,
  } = useGetCodeComponentQuery(currentComponentId, {
    skip:
      skip ||
      isLoadingGetAutoSaveCodeComponent ||
      (isSuccessGetAutoSaveCodeComponent &&
        dataGetAutoSaveCodeComponent &&
        ('data' in dataGetAutoSaveCodeComponent
          ? !!dataGetAutoSaveCodeComponent.data
          : !!dataGetAutoSaveCodeComponent)),
  });

  // Set the code component data in a local state.
  useEffect(() => {
    const autoSaveData =
      dataGetAutoSaveCodeComponent && 'data' in dataGetAutoSaveCodeComponent
        ? dataGetAutoSaveCodeComponent.data
        : dataGetAutoSaveCodeComponent;
    setDataCodeComponent(autoSaveData || dataGetCodeComponent);
  }, [dataGetAutoSaveCodeComponent, dataGetCodeComponent]);

  // Get the auto-saved data of the global asset library if it exists.
  const {
    currentData: dataGetAutoSaveAssetLibrary,
    error: errorGetAutoSaveAssetLibrary,
    isFetching: isLoadingGetAutoSaveAssetLibrary,
    isSuccess: isSuccessGetAutoSaveAssetLibrary,
  } = useGetAutoSaveQueryAssetLibrary(ASSET_LIBRARY_ID, {
    skip,
  });

  // Get the global asset library data, but skip if auto-saved data exists.
  const {
    currentData: dataGetAssetLibrary,
    error: errorGetAssetLibrary,
    isFetching: isLoadingGetAssetLibrary,
    isSuccess: isSuccessGetAssetLibrary,
  } = useGetAssetLibraryQuery(ASSET_LIBRARY_ID, {
    skip:
      skip ||
      isLoadingGetAutoSaveAssetLibrary ||
      (isSuccessGetAutoSaveAssetLibrary &&
        dataGetAutoSaveAssetLibrary &&
        ('data' in dataGetAutoSaveAssetLibrary
          ? !!dataGetAutoSaveAssetLibrary.data
          : !!dataGetAutoSaveAssetLibrary)),
  });

  // Set the global asset library data in a local state.
  useEffect(() => {
    const autoSaveData =
      dataGetAutoSaveAssetLibrary && 'data' in dataGetAutoSaveAssetLibrary
        ? dataGetAutoSaveAssetLibrary.data
        : dataGetAutoSaveAssetLibrary;
    setDataGlobalAssetLibrary(autoSaveData || dataGetAssetLibrary);
  }, [dataGetAutoSaveAssetLibrary, dataGetAssetLibrary]);

  // Set the loading state in a local state.
  useEffect(() => {
    setIsLoading(
      isLoadingGetAutoSaveCodeComponent ||
        isLoadingGetCodeComponent ||
        isLoadingGetAutoSaveAssetLibrary ||
        isLoadingGetAssetLibrary,
    );
  }, [
    isLoadingGetAutoSaveCodeComponent,
    isLoadingGetCodeComponent,
    isLoadingGetAutoSaveAssetLibrary,
    isLoadingGetAssetLibrary,
  ]);

  // Set the success state in a local state.
  useEffect(() => {
    setIsSuccess(
      (isSuccessGetAutoSaveCodeComponent || isSuccessGetCodeComponent) &&
        (isSuccessGetAutoSaveAssetLibrary || isSuccessGetAssetLibrary),
    );
  }, [
    isSuccessGetAutoSaveCodeComponent,
    isSuccessGetCodeComponent,
    isSuccessGetAutoSaveAssetLibrary,
    isSuccessGetAssetLibrary,
  ]);

  // Show error boundary if there is an error.
  useEffect(() => {
    if (
      errorGetAutoSaveCodeComponent ||
      errorGetCodeComponent ||
      errorGetAutoSaveAssetLibrary ||
      errorGetAssetLibrary
    ) {
      showBoundary(errorGetCodeComponent || errorGetAssetLibrary);
    }
  }, [
    errorGetAutoSaveCodeComponent,
    errorGetCodeComponent,
    errorGetAutoSaveAssetLibrary,
    errorGetAssetLibrary,
    showBoundary,
  ]);

  return { dataCodeComponent, dataGlobalAssetLibrary, isLoading, isSuccess };
};

export default useGetCodeEditorData;
