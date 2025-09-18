import { QuestionMarkCircledIcon } from '@radix-ui/react-icons';
import { Box, Callout, Flex, TextField } from '@radix-ui/themes';

import { useAppDispatch, useAppSelector } from '@/app/hooks';
import {
  addSlot,
  removeSlot,
  reorderSlots,
  selectCodeComponentProperty,
  updateSlot,
} from '@/features/code-editor/codeEditorSlice';
import {
  FormElement,
  Label,
} from '@/features/code-editor/component-data/FormElement';
import FormPropTypeSlot from '@/features/code-editor/component-data/forms/FormPropTypeSlot';
import SortableList from '@/features/code-editor/component-data/SortableList';

import type { CodeComponentSlot } from '@/types/CodeComponent';

export default function Slots() {
  const dispatch = useAppDispatch();
  const slots = useAppSelector(selectCodeComponentProperty('slots'));
  const componentStatus = useAppSelector(selectCodeComponentProperty('status'));

  const handleAddSlot = () => {
    dispatch(addSlot());
  };

  const handleRemoveSlot = (slotId: string) => {
    dispatch(removeSlot({ slotId }));
  };

  const handleReorder = (oldIndex: number, newIndex: number) => {
    dispatch(reorderSlots({ oldIndex, newIndex }));
  };

  const renderSlotContent = (slot: CodeComponentSlot) => (
    <Flex direction="column" flexGrow="1">
      <Box mb="4" width="100%" flexShrink="0" flexGrow="1">
        <FormElement>
          <Label htmlFor={`slot-name-${slot.id}`}>Slot name</Label>
          <TextField.Root
            autoComplete="off"
            id={`slot-name-${slot.id}`}
            placeholder="Enter a name"
            value={slot.name}
            size="1"
            onChange={(e) =>
              dispatch(
                updateSlot({
                  id: slot.id,
                  updates: { name: e.target.value },
                }),
              )
            }
            disabled={componentStatus}
          />
        </FormElement>
      </Box>
      <FormPropTypeSlot
        id={slot.id}
        example={slot.example}
        isDisabled={componentStatus}
      />
    </Flex>
  );

  return (
    <>
      <Box flexGrow="1" pt="4" maxWidth="500px" mx="auto">
        <Callout.Root size="1" variant="surface" color="gray">
          <Callout.Icon>
            <QuestionMarkCircledIcon />
          </Callout.Icon>
          <Callout.Text>
            Slots allow you to place other components inside of your component.
          </Callout.Text>
        </Callout.Root>
      </Box>
      <SortableList
        items={slots}
        onAdd={handleAddSlot}
        onReorder={handleReorder}
        onRemove={handleRemoveSlot}
        renderContent={renderSlotContent}
        getItemId={(item) => item.id}
        data-testid="slot"
        moveAriaLabel="Move slot"
        removeAriaLabel="Remove slot"
        isDisabled={componentStatus}
      />
    </>
  );
}
