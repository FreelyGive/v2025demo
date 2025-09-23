import { useEffect } from 'react';
import { useNavigate } from 'react-router-dom';

import { useAppDispatch, useAppSelector } from '@/app/hooks';
import Dialog from '@/components/Dialog';
import {
  closeAllDialogs,
  selectDialogStates,
  selectSelectedCodeComponent,
} from '@/features/ui/codeComponentDialogSlice';
import { useDeleteCodeComponentMutation } from '@/services/componentAndLayout';

const DeleteCodeComponentDialog = () => {
  const selectedComponent = useAppSelector(selectSelectedCodeComponent);
  const [deleteCodeComponent, { isLoading, isSuccess, isError, error, reset }] =
    useDeleteCodeComponentMutation();
  const navigate = useNavigate();
  const dispatch = useAppDispatch();
  const { isDeleteDialogOpen } = useAppSelector(selectDialogStates);

  const handleDelete = async () => {
    if (!selectedComponent) return;
    await deleteCodeComponent(selectedComponent.machineName);
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
      navigate('/editor');
    }
  }, [isSuccess, dispatch, navigate]);

  useEffect(() => {
    if (isError) {
      console.error('Failed to delete component:', error);
    }
  }, [isError, error]);

  if (!selectedComponent) return null;

  return (
    <Dialog
      open={isDeleteDialogOpen}
      onOpenChange={handleOpenChange}
      title="Delete component"
      description={`Are you sure you want to delete "${selectedComponent.name}"? This action cannot be undone.`}
      error={
        isError
          ? {
              title: 'Failed to delete component',
              message: `An error ${
                'status' in error ? '(HTTP ' + error.status + ')' : ''
              } occurred while deleting the component. Please check the browser console for more details.`,
              resetButtonText: 'Try again',
              onReset: handleDelete,
            }
          : undefined
      }
      footer={{
        cancelText: 'Cancel',
        confirmText: 'Delete',
        onConfirm: handleDelete,
        isConfirmDisabled: false,
        isConfirmLoading: isLoading,
        isDanger: true,
      }}
    />
  );
};

export default DeleteCodeComponentDialog;
