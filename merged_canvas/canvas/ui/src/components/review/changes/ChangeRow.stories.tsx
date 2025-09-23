// cspell:ignore Balint Takacs Chueka
import { useState } from 'react';
import { fn } from '@storybook/test';

import ChangeRow from './ChangeRow';

import type { Meta, StoryObj } from '@storybook/react';
import type { UnpublishedChange } from '@/types/Review';

// Mock data for different entity types and scenarios
const mockChanges: Record<string, UnpublishedChange> = {
  page: {
    pointer: 'node:1:en',
    label: 'Noah Lee Homepage',
    updated: Math.floor(Date.now() / 1000) - 15 * 60, // 15 minutes ago
    entity_type: 'node',
    data_hash: 'data-hash-1',
    entity_id: 1,
    langcode: 'en',
    owner: {
      name: 'Balint Takacs',
      avatar: null,
      id: 1,
      uri: '/user/1',
    },
  },
  component: {
    pointer: 'js_component:2:en',
    label: 'Navigation Component',
    updated: Math.floor(Date.now() / 1000) - 2 * 60 * 60, // 2 hours ago
    entity_type: 'js_component',
    data_hash: 'data-hash-2',
    entity_id: 2,
    langcode: 'en',
    owner: {
      name: 'Jillian Chueka',
      avatar:
        'https://images.unsplash.com/photo-1526510747491-58f928ec870f?&w=64&h=64&dpr=2&q=70&crop=focalpoint&fp-x=0.48&fp-y=0.48&fp-z=1.3&fit=crop',
      id: 2,
      uri: '/user/2',
    },
  },
  asset: {
    pointer: 'asset_library:3:en',
    label: 'Brand Assets Library',
    updated: Math.floor(Date.now() / 1000) - 24 * 60 * 60, // 1 day ago
    entity_type: 'asset_library',
    data_hash: 'data-hash-3',
    entity_id: 3,
    langcode: 'en',
    owner: {
      name: 'Renee Lund',
      avatar: null,
      id: 3,
      uri: '/user/3',
    },
  },
  region: {
    pointer: 'page_region:4:en',
    label: 'Header Region',
    updated: Math.floor(Date.now() / 1000) - 7 * 24 * 60 * 60, // 1 week ago
    entity_type: 'page_region',
    data_hash: 'data-hash-4',
    entity_id: 4,
    langcode: 'en',
    owner: {
      name: 'Madelyn Levis',
      avatar:
        'https://images.unsplash.com/photo-1522075469751-3a6694fb2f61?&w=64&h=64&dpr=2&q=70&crop=faces&fit=crop',
      id: 4,
      uri: '/user/4',
    },
  },
  conflicted: {
    pointer: 'node:5:en',
    label: 'Contact Page with Conflicts',
    updated: Math.floor(Date.now() / 1000) - 30 * 60, // 30 minutes ago
    entity_type: 'node',
    data_hash: 'data-hash-5',
    entity_id: 5,
    langcode: 'en',
    hasConflict: true,
    owner: {
      name: 'Alex Morgan',
      avatar: null,
      id: 5,
      uri: '/user/5',
    },
  },
  recentChange: {
    pointer: 'js_component:6:en',
    label: 'Hero Banner Component',
    updated: Math.floor(Date.now() / 1000) - 30, // 30 seconds ago
    entity_type: 'js_component',
    data_hash: 'data-hash-6',
    entity_id: 6,
    langcode: 'en',
    owner: {
      name: 'Samuel Chen',
      avatar:
        'https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?&w=64&h=64&dpr=2&q=70&crop=faces&fit=crop',
      id: 6,
      uri: '/user/6',
    },
  },
  oldChange: {
    pointer: 'node:7:en',
    label: 'Old Blog Post',
    updated: Math.floor(Date.now() / 1000) - 35 * 24 * 60 * 60, // 35 days ago
    entity_type: 'node',
    data_hash: 'data-hash-7',
    entity_id: 7,
    langcode: 'en',
    owner: {
      name: 'Taylor Swift',
      avatar: null,
      id: 7,
      uri: '/user/7',
    },
  },
};

// Interactive wrapper component for stories that need state management
const InteractiveChangeRow = (args: any) => {
  const [selectedChanges, setSelectedChanges] = useState<UnpublishedChange[]>(
    args.selectedChanges || [],
  );

  return (
    <ul style={{ listStyle: 'none', padding: 0, margin: 0, maxWidth: '600px' }}>
      <ChangeRow
        {...args}
        selectedChanges={selectedChanges}
        setSelectedChanges={setSelectedChanges}
      />
    </ul>
  );
};

const meta: Meta<typeof ChangeRow> = {
  title: 'Components/Publish Review/ChangeRow',
  component: ChangeRow,
  parameters: {
    layout: 'centered',
    docs: {
      description: {
        component: `
ChangeRow displays an individual unpublished change item within the review interface.
It shows change details, selection state, owner information, and provides actions like view and discard.
        `,
      },
    },
  },
  args: {
    isBusy: false,
    selectedChanges: [],
    setSelectedChanges: fn(),
    onDiscardClick: fn(),
    onViewClick: fn(),
  },
  argTypes: {
    change: {
      control: false,
      description: 'The unpublished change object to display',
    },
    isBusy: {
      control: 'boolean',
      description: 'Whether operations are in progress (disables interactions)',
    },
    selectedChanges: {
      control: false,
      description: 'Array of currently selected changes',
    },
    setSelectedChanges: {
      action: 'setSelectedChanges',
      description: 'Callback to update selected changes',
    },
    onDiscardClick: {
      action: 'onDiscardClick',
      description: 'Callback when discard action is triggered',
    },
    onViewClick: {
      action: 'onViewClick',
      description: 'Callback when view action is triggered',
    },
  },
  decorators: [
    (Story) => (
      <div style={{ maxWidth: '600px', width: '100%' }}>
        <Story />
      </div>
    ),
  ],
};

export default meta;

type Story = StoryObj<typeof ChangeRow>;

// Default state stories
export const PageChange: Story = {
  render: InteractiveChangeRow,
  args: {
    change: mockChanges.page,
  },
};

export const ComponentChange: Story = {
  render: InteractiveChangeRow,
  args: {
    change: mockChanges.component,
  },
};

export const AssetChange: Story = {
  render: InteractiveChangeRow,
  args: {
    change: mockChanges.asset,
  },
};

export const RegionChange: Story = {
  render: InteractiveChangeRow,
  args: {
    change: mockChanges.region,
  },
};

// State variations
export const SelectedChange: Story = {
  render: InteractiveChangeRow,
  args: {
    change: mockChanges.page,
    selectedChanges: [mockChanges.page],
  },
};

export const ConflictedChange: Story = {
  render: InteractiveChangeRow,
  args: {
    change: mockChanges.conflicted,
  },
};

export const BusyState: Story = {
  render: InteractiveChangeRow,
  args: {
    change: mockChanges.component,
    isBusy: true,
  },
};

// Time-based variations
export const RecentChange: Story = {
  render: InteractiveChangeRow,
  args: {
    change: mockChanges.recentChange,
  },
};

export const OldChange: Story = {
  render: InteractiveChangeRow,
  args: {
    change: mockChanges.oldChange,
  },
};

// Avatar variations
export const WithoutAvatar: Story = {
  render: InteractiveChangeRow,
  args: {
    change: mockChanges.page,
  },
};

export const WithAvatar: Story = {
  render: InteractiveChangeRow,
  args: {
    change: mockChanges.component,
  },
};

// Action scenarios
export const WithoutViewAction: Story = {
  render: InteractiveChangeRow,
  args: {
    change: mockChanges.page,
    onViewClick: undefined,
  },
};

export const WithViewAction: Story = {
  render: InteractiveChangeRow,
  args: {
    change: mockChanges.component,
    onViewClick: fn(),
  },
};

// Component for showing multiple changes
const MultipleChangesExample = () => {
  const [selectedChanges, setSelectedChanges] = useState<UnpublishedChange[]>(
    [],
  );

  return (
    <ul style={{ listStyle: 'none', padding: 0, margin: 0, maxWidth: '600px' }}>
      {Object.values(mockChanges).map((change) => (
        <ChangeRow
          key={change.pointer}
          change={change}
          isBusy={false}
          selectedChanges={selectedChanges}
          setSelectedChanges={setSelectedChanges}
          onDiscardClick={fn()}
          onViewClick={fn()}
        />
      ))}
    </ul>
  );
};

// Multiple changes comparison
export const MultipleChanges: Story = {
  render: () => <MultipleChangesExample />,
  parameters: {
    docs: {
      description: {
        story:
          'Shows multiple ChangeRow components together to demonstrate how they look in a list.',
      },
    },
  },
};

// Interactive playground
export const Playground: Story = {
  render: InteractiveChangeRow,
  args: {
    change: mockChanges.page,
  },
  parameters: {
    docs: {
      description: {
        story:
          'Interactive playground to test different combinations of props and states.',
      },
    },
  },
};
