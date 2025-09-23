import { useEffect } from 'react';

import { useAppDispatch, useAppSelector } from '@/app/hooks';
import Dialog from '@/components/Dialog';
import {
  selectDialogOpen,
  setDialogWithDataClosed,
} from '@/features/ui/dialogSlice';
import { useDeletePatternMutation } from '@/services/patterns';

import type { Pattern } from '@/types/Pattern';

const DeletePatternDialog = () => {
  const dispatch = useAppDispatch();
  const { deletePatternConfirm } = useAppSelector(selectDialogOpen);
  const { open, data } = deletePatternConfirm;
  const [deletePattern, { isLoading, isSuccess, isError, error, reset }] =
    useDeletePatternMutation();
  const { name, id } = data as Pattern;

  const handleDelete = async () => {
    await deletePattern(id);
  };

  const handleOpenChange = (open: boolean) => {
    if (!open) {
      reset();
      dispatch(setDialogWithDataClosed('deletePatternConfirm'));
    }
  };

  useEffect(() => {
    if (isSuccess) {
      dispatch(setDialogWithDataClosed('deletePatternConfirm'));
    }
    if (isError) {
      console.error('Failed to delete pattern:', error);
    }
  }, [isSuccess, isError, dispatch, error]);

  return (
    <Dialog
      open={open}
      onOpenChange={handleOpenChange}
      title="Delete pattern"
      description={`Are you sure you want to delete "${name}"? This action cannot be undone.`}
      error={
        isError
          ? {
              title: 'Failed to delete pattern',
              message: `An error ${
                'status' in error ? '(HTTP ' + error.status + ')' : ''
              } occurred while deleting the pattern. Please check the browser console for more details.`,
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

export default DeletePatternDialog;
