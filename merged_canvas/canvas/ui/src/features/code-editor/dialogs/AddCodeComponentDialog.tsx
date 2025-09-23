import { useEffect, useState } from 'react';
import parse from 'html-react-parser';
import { useParams } from 'react-router';
import { useNavigate } from 'react-router-dom';
import { Flex, Text, TextField } from '@radix-ui/themes';

import { useAppDispatch, useAppSelector } from '@/app/hooks';
import Dialog, { DialogFieldLabel } from '@/components/Dialog';
import { setCodeComponentProperty } from '@/features/code-editor/codeEditorSlice';
import getStarterComponentTemplate from '@/features/code-editor/starterComponentTemplate';
import { extractErrorMessageFromApiResponse } from '@/features/error-handling/error-handling';
import {
  closeAllDialogs,
  selectDialogStates,
} from '@/features/ui/codeComponentDialogSlice';
import { validateMachineNameClientSide } from '@/features/validation/validation';
import { useCreateCodeComponentMutation } from '@/services/componentAndLayout';

const AddCodeComponentDialog = () => {
  const [componentName, setComponentName] = useState('');
  const [validationError, setValidationError] = useState('');
  const [
    createCodeComponent,
    { isLoading, isSuccess, isError, error, reset, data },
  ] = useCreateCodeComponentMutation();
  const navigate = useNavigate();
  const dispatch = useAppDispatch();
  const { isAddDialogOpen } = useAppSelector(selectDialogStates);
  const { entityId, entityType } = useParams();

  const handleSave = async () => {
    if (validationError) {
      return;
    }

    await createCodeComponent({
      name: componentName,
      machineName: componentName.toLowerCase().replace(/\s+/g, '_'),
      // Mark this code component as "internal": do not make it available to Content Creators yet.
      // @see docs/config-management.md, section 3.2.1
      status: false,
      sourceCodeJs: getStarterComponentTemplate(componentName),
      sourceCodeCss: '',
      compiledJs: '',
      compiledCss: '',
      importedJsComponents: [],
      dataDependencies: {},
    });
  };

  const handleOpenChange = (open: boolean) => {
    if (!open) {
      setComponentName('');
      setValidationError('');
      reset();
      dispatch(closeAllDialogs());
    }
  };

  useEffect(() => {
    if (isSuccess && data?.machineName) {
      dispatch(setCodeComponentProperty(['name', componentName]));
      setComponentName('');
      setValidationError('');
      dispatch(closeAllDialogs());
      navigate(`/code-editor/code/${data.machineName}`);
      reset();
    }
  }, [
    isSuccess,
    data?.machineName,
    dispatch,
    navigate,
    componentName,
    reset,
    entityType,
    entityId,
  ]);

  useEffect(() => {
    if (isError) {
      console.error('Failed to add code component:', error);
    }
  }, [isError, error]);

  const handleOnChange = (newName: string) => {
    setComponentName(newName);
    setValidationError(
      newName.trim() ? validateMachineNameClientSide(newName) : '',
    );
  };

  return (
    <Dialog
      open={isAddDialogOpen}
      onOpenChange={handleOpenChange}
      title="Add new code component"
      error={
        isError
          ? {
              title: 'Failed to add code component',
              message: parse(extractErrorMessageFromApiResponse(error)),
              resetButtonText: 'Try again',
              onReset: handleSave,
            }
          : undefined
      }
      footer={{
        cancelText: 'Cancel',
        confirmText: 'Add',
        onConfirm: handleSave,
        isConfirmDisabled: !componentName.trim() || !!validationError,
        isConfirmLoading: isLoading,
      }}
    >
      <form
        onSubmit={(e) => {
          e.preventDefault();
          if (componentName.trim() && !validationError) {
            handleSave();
          }
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
            onChange={(e) => handleOnChange(e.target.value)}
            placeholder="Enter a name"
            size="1"
          />
          {validationError && (
            <Text size="1" color="red" weight="medium">
              {validationError}
            </Text>
          )}
        </Flex>
      </form>
    </Dialog>
  );
};

export default AddCodeComponentDialog;
