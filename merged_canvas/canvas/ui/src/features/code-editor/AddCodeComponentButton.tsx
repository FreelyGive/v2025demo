import { useCallback } from 'react';
import { PlusIcon } from '@radix-ui/react-icons';
import { Button } from '@radix-ui/themes';

import { useAppDispatch } from '@/app/hooks';
import { openAddDialog } from '@/features/ui/codeComponentDialogSlice';

const AddCodeComponentButton = () => {
  const dispatch = useAppDispatch();

  const handleClick = useCallback(() => {
    dispatch(openAddDialog());
  }, [dispatch]);

  return (
    <Button onClick={handleClick} variant="soft" size="1" my="2">
      <PlusIcon />
      Create code component
    </Button>
  );
};

export default AddCodeComponentButton;
