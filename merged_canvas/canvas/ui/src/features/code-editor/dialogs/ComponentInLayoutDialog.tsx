import { useAppDispatch, useAppSelector } from '@/app/hooks';
import Dialog from '@/components/Dialog';
import {
  closeAllDialogs,
  selectDialogStates,
} from '@/features/ui/codeComponentDialogSlice';

const ComponentInLayoutDialog = () => {
  const dispatch = useAppDispatch();
  const { isInLayoutDialogOpen } = useAppSelector(selectDialogStates);

  const handleOpenChange = (open: boolean) => {
    if (!open) {
      dispatch(closeAllDialogs());
    }
  };

  return (
    <Dialog
      open={isInLayoutDialogOpen}
      onOpenChange={handleOpenChange}
      title={
        <>
          Unable to perform action:
          <br />
          Component in use
        </>
      }
      description="Please remove all instances of the component on the page before removing or deleting."
      footer={{
        cancelText: 'Cancel',
      }}
    />
  );
};

export default ComponentInLayoutDialog;
