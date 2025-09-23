import clsx from 'clsx';
import {
  closestCenter,
  DndContext,
  KeyboardSensor,
  PointerSensor,
  useSensor,
  useSensors,
} from '@dnd-kit/core';
import {
  SortableContext,
  sortableKeyboardCoordinates,
  useSortable,
  verticalListSortingStrategy,
} from '@dnd-kit/sortable';
import { CSS } from '@dnd-kit/utilities';
import {
  DragHandleDots2Icon,
  PlusIcon,
  TrashIcon,
} from '@radix-ui/react-icons';
import { Button, Flex } from '@radix-ui/themes';

import type { DragEndEvent } from '@dnd-kit/core';

import styles from './SortableList.module.css';

interface SortableListProps<T> {
  items: T[];
  onAdd: () => void;
  onReorder: (oldIndex: number, newIndex: number) => void;
  onRemove: (id: string) => void;
  renderContent: (item: T) => React.ReactNode;
  getItemId: (item: T) => string;
  'data-testid'?: string;
  moveAriaLabel?: string;
  removeAriaLabel?: string;
  isDisabled?: boolean;
}

export default function SortableList<T>({
  items,
  onAdd,
  onReorder,
  onRemove,
  renderContent,
  getItemId,
  'data-testid': dataTestId,
  moveAriaLabel = 'Move item',
  removeAriaLabel = 'Remove item',
  isDisabled = false,
}: SortableListProps<T>) {
  const sensors = useSensors(
    useSensor(PointerSensor),
    useSensor(KeyboardSensor, {
      coordinateGetter: sortableKeyboardCoordinates,
    }),
  );

  const handleDragEnd = (event: DragEndEvent) => {
    const { active, over } = event;

    if (over && active.id !== over.id) {
      const oldIndex = items.findIndex((item) => getItemId(item) === active.id);
      const newIndex = items.findIndex((item) => getItemId(item) === over.id);

      onReorder(oldIndex, newIndex);
    }
  };

  return (
    <DndContext
      sensors={sensors}
      collisionDetection={closestCenter}
      onDragEnd={handleDragEnd}
    >
      <SortableContext
        items={items.map(getItemId)}
        strategy={verticalListSortingStrategy}
      >
        <Flex direction="column" gap="4" py="4" mx="auto" maxWidth="500px">
          {items.map((item, index) => (
            <SortablePanel
              key={getItemId(item)}
              id={getItemId(item)}
              onRemove={() => onRemove(getItemId(item))}
              data-testid={dataTestId ? `${dataTestId}-${index}` : undefined}
              moveAriaLabel={moveAriaLabel}
              removeAriaLabel={removeAriaLabel}
              isDisabled={isDisabled}
            >
              {renderContent(item)}
            </SortablePanel>
          ))}
          <Button
            size="1"
            variant="soft"
            mb="4"
            onClick={onAdd}
            disabled={isDisabled}
          >
            <PlusIcon />
            Add
          </Button>
        </Flex>
      </SortableContext>
    </DndContext>
  );
}

interface SortablePanelProps {
  id: string;
  children: React.ReactNode;
  onRemove: () => void;
  'data-testid'?: string;
  moveAriaLabel?: string;
  removeAriaLabel?: string;
  isDisabled?: boolean;
}

function SortablePanel({
  id,
  children,
  onRemove,
  'data-testid': dataTestId,
  moveAriaLabel,
  removeAriaLabel,
  isDisabled = false,
}: SortablePanelProps) {
  const {
    attributes,
    listeners,
    setNodeRef,
    transform,
    transition,
    isDragging,
  } = useSortable({ id, disabled: isDisabled });

  const style = {
    transform: CSS.Transform.toString(transform),
    transition,
    opacity: isDragging ? 0.5 : 1,
  };

  return (
    <Flex
      ref={setNodeRef}
      p="4"
      pl="2"
      gap="4"
      className={clsx(styles.panel, {
        [styles.dragging]: isDragging,
      })}
      style={style}
      data-testid={dataTestId}
    >
      <Flex
        direction="column"
        justify="between"
        align="center"
        flexGrow="0"
        flexShrink="0"
      >
        <Button
          {...attributes}
          {...listeners}
          aria-label={moveAriaLabel}
          variant="ghost"
          color="gray"
          className={clsx(styles.moveRemoveControls, styles.moveControl)}
          disabled={isDisabled}
        >
          <DragHandleDots2Icon />
        </Button>
        <Button
          onClick={onRemove}
          aria-label={removeAriaLabel}
          variant="ghost"
          color="red"
          className={styles.moveRemoveControls}
          disabled={isDisabled}
        >
          <TrashIcon />
        </Button>
      </Flex>
      <Flex direction="column" flexGrow="1">
        {children}
      </Flex>
    </Flex>
  );
}
