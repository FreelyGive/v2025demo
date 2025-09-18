/**
 * @file
 * Main hook for working with the code editor.
 *
 * Responsibilities:
 * - Get the currently edited code component's ID from the URL.
 * - Load the code component and global asset library data.
 * - Initialize the code editor with the data.
 * - Continuously compile source code.
 * - Continuously auto-save the code component and global asset library.
 */

import { useEffect } from 'react';
import { useParams } from 'react-router';

import { useAppDispatch, useAppSelector } from '@/app/hooks';
import {
  initializeCodeEditor,
  resetCodeEditor,
  selectCodeComponentProperty,
} from '@/features/code-editor/codeEditorSlice';
import useAutoSave from '@/features/code-editor/hooks/useAutoSave';
import useGetCodeEditorData from '@/features/code-editor/hooks/useGetCodeEditorData';
import useSourceCode from '@/features/code-editor/hooks/useSourceCode';
import { deserializeCodeComponent } from '@/features/code-editor/utils';

import type {
  AssetLibrary,
  CodeComponentSerialized,
} from '@/types/CodeComponent';

const useCodeEditor: () => {
  isLoading: boolean;
} = () => {
  const dispatch = useAppDispatch();

  // Get the currently edited code component's ID from the URL.
  const { codeComponentId: requestedComponentId } = useParams();

  // Get the ID of the code component that has been loaded into the code editor.
  const loadedComponentId = useAppSelector(
    selectCodeComponentProperty('machineName'),
  );

  // Get the code component and global asset library data.
  const { dataCodeComponent, dataGlobalAssetLibrary, isLoading, isSuccess } =
    useGetCodeEditorData(requestedComponentId as string, {
      // Do not re-fetch data unless there is a new requested component.
      skip: requestedComponentId === loadedComponentId,
    });

  // Initialize the code editor with the data.
  useEffect(() => {
    if (
      isLoading ||
      (!isLoading && !isSuccess) ||
      !dataCodeComponent ||
      !dataGlobalAssetLibrary ||
      (loadedComponentId &&
        requestedComponentId &&
        loadedComponentId === requestedComponentId)
    ) {
      return;
    }
    dispatch(
      initializeCodeEditor({
        codeComponent: deserializeCodeComponent(
          dataCodeComponent as CodeComponentSerialized,
        ),
        globalAssetLibrary: dataGlobalAssetLibrary as AssetLibrary,
        status: {
          // The first compilation normally bypasses auto-save.
          // However, if the compiled JS is empty or a previous compilation
          // produced fallback content after an error, we need to auto-save the
          // newly compiled code.
          needsAutoSaveOnFirstCompilation:
            dataCodeComponent?.compiledJs === '' ||
            dataCodeComponent?.compiledJs.startsWith('// @error'),
        },
      }),
    );
  }, [
    requestedComponentId,
    loadedComponentId,
    dataCodeComponent,
    dataGlobalAssetLibrary,
    isLoading,
    isSuccess,
    dispatch,
  ]);

  // Compile source code.
  useSourceCode(requestedComponentId as string);

  // Auto-save the code component and global asset library.
  useAutoSave(requestedComponentId as string);

  // Reset the code editor when the component is unmounted.
  useEffect(() => {
    return () => {
      dispatch(resetCodeEditor());
    };
  }, [dispatch]);

  return { isLoading };
};

export default useCodeEditor;
