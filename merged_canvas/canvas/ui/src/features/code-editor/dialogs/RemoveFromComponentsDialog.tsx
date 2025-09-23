import { useEffect } from 'react';
import { useNavigate } from 'react-router-dom';

import { useAppDispatch, useAppSelector } from '@/app/hooks';
import Dialog from '@/components/Dialog';
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

// This handles the dialog for removing a JS component from components. This changes
// the component from being "exposed" to "internal".
const RemoveFromComponentsDialog = () => {
  const navigate = useNavigate();
  const selectedComponent = useAppSelector(selectSelectedCodeComponent);
  const isCodeEditorOpen = !!useAppSelector(
    selectCodeComponentProperty('machineName'),
  );
  const [updateCodeComponent, { isLoading, isSuccess, isError, error, reset }] =
    useUpdateCodeComponentMutation();
  const dispatch = useAppDispatch();
  const { isRemoveFromComponentsDialogOpen } =
    useAppSelector(selectDialogStates);

  const handleSave = async () => {
    if (!selectedComponent) return;

    await updateCodeComponent({
      id: selectedComponent.machineName,
      changes: {
        status: false,
      },
    });

    if (isCodeEditorOpen) {
      // If the code editor is open when the component is being set to internal,
      // also set the status in the codeEditorSlice to internal. While the
      // `updateCodeComponent` mutation invalidates cache of the code component
      // data, the code editor won't refetch while it's open.
      dispatch(setCodeComponentProperty(['status', false]));
      // Navigate to the code editor route that handles internal code components.
      navigate(`/code-editor/code/${selectedComponent.machineName}`);
    }
  };

  const handleOpenChange = (open: boolean) => {
    if (!open) {
      reset();
      dispatch(closeAllDialogs());
    }
  };

  useEffect(() => {
    if (isSuccess) {
      dispatch(closeAllDialogs());
    }
  }, [isSuccess, dispatch]);

  useEffect(() => {
    if (isError) {
      console.error('Failed to remove from components:', error);
    }
  }, [isError, error]);

  return (
    <Dialog
      open={isRemoveFromComponentsDialogOpen}
      onOpenChange={handleOpenChange}
      title="Remove from components"
      description={
        <>
          This component will be moved to the <b>Code</b> section and will no
          longer be available to use on the page.
          <br />
          <br />
          You can re-add it to <b>Components</b> from the <b>Code</b> section at
          any time.
        </>
      }
      error={
        isError
          ? {
              title: 'Failed to remove from components',
              message: `An error ${
                'status' in error ? '(HTTP ' + error.status + ')' : ''
              } occurred while removing from components. Please check the browser console for more details.`,
              resetButtonText: 'Try again',
              onReset: handleSave,
            }
          : undefined
      }
      footer={{
        cancelText: 'Cancel',
        confirmText: 'Remove',
        onConfirm: handleSave,
        isConfirmDisabled: false,
        isConfirmLoading: isLoading,
        isDanger: true,
      }}
    />
  );
};

export default RemoveFromComponentsDialog;
