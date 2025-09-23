import { useCallback, useEffect, useState } from 'react';
import { Flex, TextField } from '@radix-ui/themes';

import { useAppDispatch, useAppSelector } from '@/app/hooks';
import Dialog, { DialogFieldLabel } from '@/components/Dialog';
import { selectLayout, selectModel } from '@/features/layout/layoutModelSlice';
import {
  findComponentByUuid,
  recurseNodes,
} from '@/features/layout/layoutUtils';
import {
  selectDialogOpen,
  setDialogClosed,
  setDialogOpen,
} from '@/features/ui/dialogSlice';
import { selectSelectedComponentUuid } from '@/features/ui/uiSlice';
import useGetComponentName from '@/hooks/useGetComponentName';
import { useSavePatternMutation } from '@/services/patterns';

import type React from 'react';
import type { SerializedError } from '@reduxjs/toolkit';
import type { FetchBaseQueryError } from '@reduxjs/toolkit/query/react';

interface ErrorData {
  message?: string;
}

function getErrorMessage(error: FetchBaseQueryError | SerializedError): string {
  if ('status' in error) {
    // TODO: I think any calls to /api/ should respond in JSON, not an HTML document?
    if (error.status === 'PARSING_ERROR') {
      return 'The server returned an unexpected response format.';
    }
    if (error.status === 404) {
      return 'Resource not found.';
    }
    // Handle other HTTP status errors generically
    const errorData = error.data as ErrorData;
    return `Error ${error.status}: ${errorData?.message || 'No additional information'}`;
  } else {
    // Handle SerializedError
    return error.message || 'Unknown error occurred';
  }
}

const SavePatternDialog: React.FC = () => {
  const { saveAsPattern } = useAppSelector(selectDialogOpen);
  const dispatch = useAppDispatch();
  const selectedComponent = useAppSelector(selectSelectedComponentUuid);
  const model = useAppSelector(selectModel);
  const layout = useAppSelector(selectLayout);
  const selectedNode = findComponentByUuid(layout, selectedComponent || '');
  const selectedComponentName = useGetComponentName(selectedNode);
  const [patternName, setPatternName] = useState('My pattern');
  const [
    savePattern,
    { isLoading: isSaving, isSuccess, isError, error, reset },
  ] = useSavePatternMutation();

  const handleOpenChange = useCallback(
    (open: boolean) => {
      open
        ? dispatch(setDialogOpen('saveAsPattern'))
        : dispatch(setDialogClosed('saveAsPattern'));
      if (!open) {
        reset();
      }
    },
    [dispatch, reset],
  );

  useEffect(() => {
    if (selectedComponent) {
      setPatternName(`${selectedComponentName} pattern`);
    }
  }, [model, selectedComponent, selectedComponentName]);

  const handleSaveClick = useCallback(() => {
    if (!selectedComponent || !layout) {
      return;
    }

    let modelsToSave = {
      [selectedComponent]: model[selectedComponent],
    };
    const thisNode = findComponentByUuid(layout, selectedComponent);
    if (!thisNode) {
      return;
    }

    recurseNodes(thisNode, (node) => {
      if (model[node.uuid]) {
        modelsToSave[node.uuid] = model[node.uuid];
      }
    });

    savePattern({
      layout: [thisNode],
      model: modelsToSave,
      name: patternName,
    });
  }, [layout, model, savePattern, selectedComponent, patternName]);

  const handleInputChange = (event: React.ChangeEvent<HTMLInputElement>) => {
    setPatternName(event.target.value);
  };

  useEffect(() => {
    if (isSuccess) {
      dispatch(setDialogClosed('saveAsPattern'));
    }
    if (isError) {
      console.error('Save failed', error);
    }
  }, [isSuccess, isError, dispatch, error]);

  if (!selectedComponent) {
    return null;
  }

  return (
    <Dialog
      open={saveAsPattern}
      onOpenChange={handleOpenChange}
      title="Add new pattern"
      description={`Saving this configuration of "${selectedComponentName}" as a pattern allows it to be used again later and customized there without affecting other copies.`}
      error={
        isError
          ? {
              title: 'Failed to save pattern',
              message: getErrorMessage(error),
              resetButtonText: 'Try again',
              onReset: handleSaveClick,
            }
          : undefined
      }
      footer={{
        cancelText: 'Cancel',
        confirmText: 'Add to library',
        onConfirm: handleSaveClick,
        isConfirmDisabled: !patternName.trim(),
        isConfirmLoading: isSaving,
      }}
    >
      <Flex direction="column" gap="2">
        <label>
          <DialogFieldLabel htmlFor={'patternName'}>
            Pattern name
          </DialogFieldLabel>
          <TextField.Root
            autoComplete="off"
            value={patternName}
            onChange={handleInputChange}
            placeholder="Enter a name"
            id="patternName"
            name="patternName"
            size="1"
          />
        </label>
      </Flex>
    </Dialog>
  );
};

export default SavePatternDialog;
