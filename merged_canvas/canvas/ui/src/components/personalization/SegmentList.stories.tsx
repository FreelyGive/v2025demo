import { fn } from '@storybook/test';

import SegmentList from './SegmentList';

import type { Meta, StoryObj } from '@storybook/react';

const meta: Meta<typeof SegmentList> = {
  title: 'Personalization/SegmentList',
  component: SegmentList,
  tags: ['autodocs'],
  args: {
    onCreateSegment: fn(),
    onReorderSegments: fn(),
    onToggleSegment: fn(),
    onEditSegment: fn(),
    onRenameSegment: fn(),
    onDeleteSegment: fn(),
    onPreviewSegment: fn(),
  },
};

export default meta;

type Story = StoryObj<typeof SegmentList>;

export const Empty: Story = {};

export const WithSegments: Story = {
  args: {
    segments: [
      {
        id: 'default',
        label: 'Default',
        status: true,
        weight: 2147483647,
      },
      {
        id: '1',
        label: 'High-value customers',
        status: true,
        weight: 0,
      },
      {
        id: '2',
        label: 'Mobile users',
        status: false,
        weight: 2,
      },
      {
        id: '3',
        label: 'Returning visitors',
        status: true,
        weight: 1,
      },
      {
        id: '4',
        label: 'European users',
        status: false,
        weight: 4,
      },
      {
        id: '5',
        label: 'Newsletter subscribers',
        status: true,
        weight: 3,
      },
    ],
  },
};
