import { useEffect, useState } from 'react';
import { Flex, TextField } from '@radix-ui/themes';

import { useAppDispatch, useAppSelector } from '@/app/hooks';
import Dialog, { DialogFieldLabel } from '@/components/Dialog';
import {
  selectCodeComponentProperty,
  setCodeComponentProperty,
} from '@/features/code-editor/codeEditorSlice';
import {
  closeAllDialogs,
  selectDialogStates,
  selectSelectedCodeComponent,
} from '@/features/ui/codeComponentDialogSlice';
import { useUpdateCodeComponentMutation } from '@/services/componentAndLayout';

const RenameCodeComponentDialog = () => {
  const selectedComponent = useAppSelector(selectSelectedCodeComponent);
  const codeEditorId = useAppSelector(
    selectCodeComponentProperty('machineName'),
  );
  const [componentName, setComponentName] = useState('');
  const [updateCodeComponent, { isLoading, isSuccess, isError, error, reset }] =
    useUpdateCodeComponentMutation();
  const dispatch = useAppDispatch();
  const { isRenameDialogOpen } = useAppSelector(selectDialogStates);

  useEffect(() => {
    if (selectedComponent) {
      setComponentName(selectedComponent.name);
    }
  }, [selectedComponent]);

  const handleSave = async () => {
    if (!selectedComponent) return;

    await updateCodeComponent({
      id: selectedComponent.machineName,
      changes: {
        name: componentName,
      },
    });
    if (codeEditorId === selectedComponent.machineName) {
      dispatch(setCodeComponentProperty(['name', componentName]));
    }
  };

  const handleOpenChange = (open: boolean) => {
    if (!open) {
      setComponentName('');
      reset();
      dispatch(closeAllDialogs());
    }
  };

  useEffect(() => {
    if (isSuccess) {
      setComponentName('');
      dispatch(closeAllDialogs());
    }
  }, [isSuccess, dispatch]);

  useEffect(() => {
    if (isError) {
      console.error('Failed to rename component:', error);
    }
  }, [isError, error]);

  return (
    <Dialog
      open={isRenameDialogOpen}
      onOpenChange={handleOpenChange}
      title="Rename component"
      error={
        isError
          ? {
              title: 'Failed to rename component',
              message: `An error ${
                'status' in error ? '(HTTP ' + error.status + ')' : ''
              } occurred while renaming the component. Please check the browser console for more details.`,
              resetButtonText: 'Try again',
              onReset: handleSave,
            }
          : undefined
      }
      footer={{
        cancelText: 'Cancel',
        confirmText: 'Rename',
        onConfirm: handleSave,
        isConfirmDisabled:
          !componentName.trim() || componentName === selectedComponent?.name,
        isConfirmLoading: isLoading,
      }}
    >
      <Flex direction="column" gap="2">
        <DialogFieldLabel htmlFor={'componentName'}>
          Component name
        </DialogFieldLabel>
        <TextField.Root
          autoComplete="off"
          id={'componentName'}
          value={componentName}
          onChange={(e) => setComponentName(e.target.value)}
          placeholder="Enter a new name"
          size="1"
        />
      </Flex>
    </Dialog>
  );
};

export default RenameCodeComponentDialog;
