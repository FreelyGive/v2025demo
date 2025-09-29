import { useEffect } from 'react';
import { useNavigate } from 'react-router-dom';

import { useAppDispatch, useAppSelector } from '@/app/hooks';
import Dialog from '@/components/Dialog';
import {
  closeAllDialogs,
  selectDialogStates,
  selectSelectedCodeComponent,
} from '@/features/ui/codeComponentDialogSlice';
import {
  setActivePanel,
  setManageLibraryTab,
} from '@/features/ui/primaryPanelSlice';
import { selectPreviouslyEdited } from '@/features/ui/uiSlice';
import { useUpdateCodeComponentMutation } from '@/services/componentAndLayout';

// This handles the dialog for adding a JS component to components. This changes
// the component from being "internal" to "exposed".
const AddToComponentsDialog = () => {
  const navigate = useNavigate();
  const selectedComponent = useAppSelector(selectSelectedCodeComponent);
  const [updateCodeComponent, { isLoading, isSuccess, isError, error, reset }] =
    useUpdateCodeComponentMutation();
  const dispatch = useAppDispatch();
  const { isAddToComponentsDialogOpen } = useAppSelector(selectDialogStates);
  const previouslyEdited = useAppSelector(selectPreviouslyEdited);

  const handleSave = async () => {
    if (!selectedComponent) return;

    await updateCodeComponent({
      id: selectedComponent.machineName,
      changes: {
        // @todo: Remove "...selectedComponent" and only send wanted changes in the PATCH request in
        //   https://drupal.org/i/3524274.
        ...selectedComponent,
        // Mark this code component as "exposed", to make it available to content creators.
        // @see docs/config-management.md, section 3.2.1
        // @see \Drupal\canvas\EntityHandlers\JavascriptComponentStorage::createOrUpdateComponentEntity()
        status: true,
      },
    });
    dispatch(setManageLibraryTab('components'));
    dispatch(setActivePanel('manageLibrary'));
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
      if (!previouslyEdited.path) {
        navigate('/editor');
      } else {
        navigate(previouslyEdited.path);
      }
    }
  }, [isSuccess, dispatch, navigate, previouslyEdited.path]);

  useEffect(() => {
    if (isError) {
      console.error('Failed to add to components:', error);
    }
  }, [isError, error]);

  return (
    <Dialog
      open={isAddToComponentsDialogOpen}
      onOpenChange={handleOpenChange}
      title="Add to components"
      description={
        <>
          This component will be moved to the <b>Components</b> section and will
          be available to use on the page.
          <br />
          <br />
          You can remove it from <b>Components</b> at any time.
        </>
      }
      error={
        isError
          ? {
              title: 'Failed to add to components',
              message: `An error ${
                'status' in error ? '(HTTP ' + error.status + ')' : ''
              } occurred while adding to components. Please check the browser console for more details.`,
              resetButtonText: 'Try again',
              onReset: handleSave,
            }
          : undefined
      }
      footer={{
        cancelText: 'Cancel',
        confirmText: 'Add',
        onConfirm: handleSave,
        isConfirmDisabled: false,
        isConfirmLoading: isLoading,
      }}
    />
  );
};

export default AddToComponentsDialog;
