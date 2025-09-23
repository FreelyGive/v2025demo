import { useCallback } from 'react';
import snakeCase from 'lodash/snakeCase';
import { useNavigate } from 'react-router-dom';
import { PlusIcon } from '@radix-ui/react-icons';
import { Button, Flex } from '@radix-ui/themes';

import DefaultSitePanel from '@/components/personalization/DefaultSitePanel';
import SegmentList from '@/components/personalization/SegmentList';
import {
  useCreateSegmentMutation,
  useDeleteSegmentMutation,
  useGetSegmentsQuery,
  useUpdateSegmentMutation,
} from '@/services/personalization';

import type { Segment } from '@/types/Personalization';

export default function SegmentDashboard() {
  const navigate = useNavigate();
  const { data: segments = {}, isLoading, error } = useGetSegmentsQuery();
  const [createSegment] = useCreateSegmentMutation();
  const [updateSegment] = useUpdateSegmentMutation();
  const [deleteSegment] = useDeleteSegmentMutation();

  const handleCreateSegment = useCallback(async () => {
    // dispatch(openAddSegmentDialog());
    const name = prompt('Enter a name for the new segment:')?.trim();
    if (name) {
      try {
        const id = snakeCase(name);

        // First, update weights of existing segments to push them down
        // Skip the default segment as it has a fixed high weight
        const existingSegments = Object.values(segments).filter(
          (segment) => segment.id !== 'default',
        );
        const updateExistingPromises = existingSegments.map((segment) =>
          updateSegment({
            id: segment.id,
            changes: { weight: segment.weight + 1 },
          }),
        );

        await Promise.all(updateExistingPromises);

        // Create new segment with weight 0 to show at the top
        await createSegment({ id, label: name, status: true, weight: 0 });
      } catch (error) {
        console.error('Failed to create segment:', error);
      }
    }
  }, [createSegment, updateSegment, segments]);

  const handleEditSegment = useCallback(
    (id: string) => {
      navigate(`/segments/${id}`);
    },
    [navigate],
  );

  const handleDeleteSegment = useCallback(
    async (id: string) => {
      const segment = segments[id];
      const segmentName = segment?.label || id;

      if (
        confirm(
          `Are you sure you want to delete the segment "${segmentName}"? This action cannot be undone.`,
        )
      ) {
        try {
          await deleteSegment(id);
        } catch (error) {
          console.error('Failed to delete segment:', error);
        }
      }
    },
    [deleteSegment, segments],
  );

  const handlePreviewSegment = useCallback(
    (_id: string) => {
      // Navigate to preview with segment context - for now just go to full preview
      // TODO: In the future, we might want to include segment ID in the preview URL
      navigate('/preview/full');
    },
    [navigate],
  );

  const handleToggleSegment = useCallback(
    async (id: string, enabled: boolean) => {
      try {
        await updateSegment({
          id,
          changes: { status: enabled },
        });
      } catch (error) {
        console.error('Failed to auto-save segment:', error);
      }
    },
    [updateSegment],
  );

  const handleReorderSegments = useCallback(
    async (reorderedSegments: Segment[]) => {
      try {
        // Update weights for all segments based on their new order
        // Skip the default segment as it has a fixed high weight
        const updatePromises = reorderedSegments
          .filter((segment) => segment.id !== 'default')
          .map((segment, index) =>
            updateSegment({
              id: segment.id,
              changes: { weight: index },
            }),
          );

        await Promise.all(updatePromises);
      } catch (error) {
        console.error('Failed to update segment weights:', error);
      }
    },
    [updateSegment],
  );

  const handleRenameSegment = useCallback(
    async (segmentId: string, newName: string) => {
      try {
        updateSegment({
          id: segmentId,
          changes: { label: newName },
        });
      } catch (error) {
        console.error('Failed to rename segment:', error);
      }
    },
    [updateSegment],
  );

  if (isLoading) {
    return <div>Loading segments...</div>;
  }

  if (error) {
    return <div>Error loading segments: {JSON.stringify(error)}</div>;
  }

  return (
    <Flex direction="column" gap="6">
      <Flex justify="end" align="center">
        <Button onClick={handleCreateSegment}>
          <PlusIcon />
          Create segment
        </Button>
      </Flex>
      <DefaultSitePanel
        onClickEdit={() => navigate('/editor')}
        onClickPreview={() => navigate('/preview/full')}
      />
      <SegmentList
        segments={Object.values(segments)}
        onCreateSegment={handleCreateSegment}
        onEditSegment={handleEditSegment}
        onDeleteSegment={handleDeleteSegment}
        onPreviewSegment={handlePreviewSegment}
        onToggleSegment={handleToggleSegment}
        onReorderSegments={handleReorderSegments}
        onRenameSegment={handleRenameSegment}
      />
    </Flex>
  );
}
