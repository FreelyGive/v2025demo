import {
  closestCenter,
  DndContext,
  KeyboardSensor,
  PointerSensor,
  useSensor,
  useSensors,
} from '@dnd-kit/core';
import {
  arrayMove,
  SortableContext,
  sortableKeyboardCoordinates,
  useSortable,
  verticalListSortingStrategy,
} from '@dnd-kit/sortable';
import { CSS } from '@dnd-kit/utilities';
import {
  Crosshair2Icon,
  DotsHorizontalIcon,
  DragHandleDots2Icon,
  EyeOpenIcon,
  PlusIcon,
} from '@radix-ui/react-icons';
import {
  Badge,
  Button,
  Card,
  DropdownMenu,
  Flex,
  IconButton,
  Table,
  Text,
} from '@radix-ui/themes';

import type { DragEndEvent } from '@dnd-kit/core';
import type { Segment } from '@/types/Personalization';

import styles from './SegmentList.module.css';

interface SortableTableRowProps {
  segment: Segment;
  onToggleSegment: (segmentId: string, enabled: boolean) => void;
  onEditSegment: (segmentId: string) => void;
  onRenameSegment: (segmentId: string, newName: string) => void;
  onDeleteSegment: (segmentId: string) => void;
  onPreviewSegment: (segmentId: string) => void;
}

const SortableTableRow = ({
  segment,
  onToggleSegment,
  onEditSegment,
  onRenameSegment,
  onDeleteSegment,
  onPreviewSegment,
}: SortableTableRowProps) => {
  const { id, status, label } = segment;
  const isDefaultSegment = id === 'default';
  const {
    attributes,
    listeners,
    setNodeRef,
    transform,
    transition,
    isDragging,
  } = useSortable({ id, disabled: isDefaultSegment });

  const style = {
    transform: CSS.Transform.toString(transform),
    transition,
    opacity: isDragging ? 0.5 : 1,
  };

  return (
    <Table.Row ref={setNodeRef} style={style}>
      <Table.Cell>
        {!isDefaultSegment && (
          <div
            {...attributes}
            {...listeners}
            style={{ cursor: isDragging ? 'grabbing' : 'grab' }}
          >
            <DragHandleDots2Icon />
          </div>
        )}
      </Table.Cell>
      <Table.Cell>
        <Badge color={status ? 'green' : 'gray'}>
          {status ? 'Enabled' : 'Disabled'}
        </Badge>
      </Table.Cell>
      <Table.Cell>{label}</Table.Cell>
      <Table.Cell>
        <Flex gap="6" align="center" justify="end">
          {!isDefaultSegment && (
            <DropdownMenu.Root>
              <DropdownMenu.Trigger>
                <IconButton variant="ghost">
                  <DotsHorizontalIcon />
                </IconButton>
              </DropdownMenu.Trigger>
              <DropdownMenu.Content align="end">
                <DropdownMenu.Item
                  onSelect={() => {
                    onToggleSegment?.(id, !status);
                  }}
                >
                  {status ? 'Disable segment' : 'Enable segment'}
                </DropdownMenu.Item>
                <DropdownMenu.Item onSelect={() => onEditSegment?.(id)}>
                  Edit segment rules
                </DropdownMenu.Item>
                <DropdownMenu.Item
                  onSelect={() =>
                    onRenameSegment?.(id, prompt('New segment name') || label)
                  }
                >
                  Rename segment
                </DropdownMenu.Item>
                <DropdownMenu.Separator />
                <DropdownMenu.Item
                  color="red"
                  onSelect={() => onDeleteSegment?.(id)}
                >
                  Delete segment
                </DropdownMenu.Item>
              </DropdownMenu.Content>
            </DropdownMenu.Root>
          )}
          <Button variant="outline" onClick={() => onPreviewSegment?.(id)}>
            <EyeOpenIcon />
            <span className={styles.previewLabel}>Preview</span>
          </Button>
        </Flex>
      </Table.Cell>
    </Table.Row>
  );
};

interface SegmentListProps {
  segments: Segment[];
  onCreateSegment: () => void;
  onReorderSegments: (segments: Segment[]) => void;
  onToggleSegment: (segmentId: string, enabled: boolean) => void;
  onEditSegment: (segmentId: string) => void;
  onRenameSegment: (segmentId: string, newName: string) => void;
  onDeleteSegment: (segmentId: string) => void;
  onPreviewSegment: (segmentId: string) => void;
}

const SegmentList = ({
  segments = [],
  onCreateSegment,
  onReorderSegments,
  onToggleSegment,
  onEditSegment,
  onRenameSegment,
  onDeleteSegment,
  onPreviewSegment,
}: SegmentListProps) => {
  // Sort segments by weight (ascending), with undefined weights treated as 0
  const sortedSegments = [...segments].sort((a, b) => a.weight - b.weight);
  const sensors = useSensors(
    useSensor(PointerSensor),
    useSensor(KeyboardSensor, {
      coordinateGetter: sortableKeyboardCoordinates,
    }),
  );

  const handleDragEnd = (event: DragEndEvent) => {
    const { active, over } = event;

    if (active.id !== over?.id) {
      const oldIndex = sortedSegments.findIndex(
        (segment) => segment.id === active.id,
      );
      const newIndex = sortedSegments.findIndex(
        (segment) => segment.id === over?.id,
      );

      const reorderedSegments = arrayMove(sortedSegments, oldIndex, newIndex);
      onReorderSegments?.(reorderedSegments);
    }
  };

  return (
    <Flex direction="column" gap="4">
      {sortedSegments.length > 0 && (
        <Text size="3" weight="bold">
          Personalization segments
        </Text>
      )}
      <Card className={styles.segmentListCard}>
        {sortedSegments.length > 0 ? (
          <DndContext
            sensors={sensors}
            collisionDetection={closestCenter}
            onDragEnd={handleDragEnd}
          >
            <Table.Root className={styles.segmentTable}>
              <Table.Header>
                <Table.Row>
                  <Table.ColumnHeaderCell width="2rem"></Table.ColumnHeaderCell>
                  <Table.ColumnHeaderCell width="6rem">
                    Status
                  </Table.ColumnHeaderCell>
                  <Table.ColumnHeaderCell>Segment title</Table.ColumnHeaderCell>
                  <Table.ColumnHeaderCell width="12rem"></Table.ColumnHeaderCell>
                </Table.Row>
              </Table.Header>

              <Table.Body>
                <SortableContext
                  items={sortedSegments.map((segment) => segment.id)}
                  strategy={verticalListSortingStrategy}
                >
                  {sortedSegments.map((segment) => (
                    <SortableTableRow
                      key={segment.id}
                      segment={segment}
                      onToggleSegment={onToggleSegment}
                      onEditSegment={onEditSegment}
                      onRenameSegment={onRenameSegment}
                      onDeleteSegment={onDeleteSegment}
                      onPreviewSegment={onPreviewSegment}
                    />
                  ))}
                </SortableContext>
              </Table.Body>
            </Table.Root>
          </DndContext>
        ) : (
          <Flex p="8" direction="column" gap="3" align="center">
            <Flex align="center" gap="0" direction="column">
              <Crosshair2Icon />
              <Text size="1" weight="medium">
                Create a new segment
              </Text>
              <Text size="1" align="center">
                A segment is a group of visitors with shared interests or
                behaviors.
              </Text>
            </Flex>
            <Button onClick={onCreateSegment}>
              <PlusIcon /> Create Segment
            </Button>
          </Flex>
        )}
      </Card>
    </Flex>
  );
};

export default SegmentList;
