import { useCallback } from 'react';
import { CodeIcon } from '@radix-ui/react-icons';
import { DropdownMenu } from '@radix-ui/themes';

import { useAppDispatch } from '@/app/hooks';
import { openAddDialog } from '@/features/ui/codeComponentDialogSlice';

const AddCodeComponentButton = () => {
  const dispatch = useAppDispatch();

  const handleClick = useCallback(() => {
    dispatch(openAddDialog());
  }, [dispatch]);

  return (
    <DropdownMenu.Item
      onClick={handleClick}
      data-testid="canvas-library-new-code-component-button"
    >
      <CodeIcon />
      Code component
    </DropdownMenu.Item>
  );
};

export default AddCodeComponentButton;
