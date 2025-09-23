import { useCallback } from 'react';
import { Box } from '@radix-ui/themes';

import { useAppDispatch, useAppSelector } from '@/app/hooks';
import Dialog from '@/components/Dialog';
import {
  selectActiveExtension,
  unsetActiveExtension,
} from '@/features/extensions/extensionsSlice';
import {
  selectDialogOpen,
  setDialogClosed,
  setDialogOpen,
} from '@/features/ui/dialogSlice';

import type React from 'react';

interface ExtensionDialogProps {}

const ExtensionDialog: React.FC<ExtensionDialogProps> = () => {
  const { extension } = useAppSelector(selectDialogOpen);
  const activeExtension = useAppSelector(selectActiveExtension);
  const dispatch = useAppDispatch();

  const handleOpenChange = useCallback(
    (open: boolean) => {
      if (open) {
        dispatch(setDialogOpen('extension'));
      } else {
        dispatch(setDialogClosed('extension'));
        dispatch(unsetActiveExtension());
      }
    },
    [dispatch],
  );
  if (!extension || activeExtension === null) {
    return null;
  }

  return (
    <Dialog
      open={extension}
      onOpenChange={handleOpenChange}
      title={activeExtension.name}
      modal={false}
      headerClose={true}
      footer={{ hidden: true }}
    >
      <Box
        id="extensionPortalContainer"
        className={`canvas-extension-${activeExtension.id}`}
      ></Box>
    </Dialog>
  );
};

export default ExtensionDialog;
