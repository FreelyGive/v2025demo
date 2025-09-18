// cspell:ignore Balint Takacs Chueka
import { useState } from 'react';
import { fn } from '@storybook/test';

import ChangeGroup from './ChangeGroup';

import type { Meta, StoryObj } from '@storybook/react';
import type { UnpublishedChange } from '@/types/Review';

// Mock data for different entity types and scenarios
const mockChanges: Record<string, UnpublishedChange[]> = {
  pages: [
    {
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
    {
      pointer: 'node:2:en',
      label: 'About Us Page',
      updated: Math.floor(Date.now() / 1000) - 45 * 60, // 45 minutes ago
      entity_type: 'node',
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
    {
      pointer: 'node:3:en',
      label: 'Contact Page with Conflicts',
      updated: Math.floor(Date.now() / 1000) - 30 * 60, // 30 minutes ago
      entity_type: 'node',
      data_hash: 'data-hash-3',
      entity_id: 3,
      langcode: 'en',
      hasConflict: true,
      owner: {
        name: 'Alex Morgan',
        avatar: null,
        id: 3,
        uri: '/user/3',
      },
    },
  ],
  components: [
    {
      pointer: 'js_component:1:en',
      label: 'Navigation Component',
      updated: Math.floor(Date.now() / 1000) - 2 * 60 * 60, // 2 hours ago
      entity_type: 'js_component',
      data_hash: 'data-hash-4',
      entity_id: 1,
      langcode: 'en',
      owner: {
        name: 'Samuel Chen',
        avatar:
          'https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?&w=64&h=64&dpr=2&q=70&crop=faces&fit=crop',
        id: 4,
        uri: '/user/4',
      },
    },
    {
      pointer: 'js_component:2:en',
      label: 'Hero Banner Component',
      updated: Math.floor(Date.now() / 1000) - 30, // 30 seconds ago
      entity_type: 'js_component',
      data_hash: 'data-hash-5',
      entity_id: 2,
      langcode: 'en',
      owner: {
        name: 'Renee Lund',
        avatar: null,
        id: 5,
        uri: '/user/5',
      },
    },
  ],
  assets: [
    {
      pointer: 'asset_library:1:en',
      label: 'Brand Assets Library',
      updated: Math.floor(Date.now() / 1000) - 24 * 60 * 60, // 1 day ago
      entity_type: 'asset_library',
      data_hash: 'data-hash-6',
      entity_id: 1,
      langcode: 'en',
      owner: {
        name: 'Madelyn Levis',
        avatar:
          'https://images.unsplash.com/photo-1522075469751-3a6694fb2f61?&w=64&h=64&dpr=2&q=70&crop=faces&fit=crop',
        id: 6,
        uri: '/user/6',
      },
    },
  ],
  regions: [
    {
      pointer: 'page_region:1:en',
      label: 'Header Region',
      updated: Math.floor(Date.now() / 1000) - 7 * 24 * 60 * 60, // 1 week ago
      entity_type: 'page_region',
      data_hash: 'data-hash-7',
      entity_id: 1,
      langcode: 'en',
      owner: {
        name: 'Taylor Swift',
        avatar: null,
        id: 7,
        uri: '/user/7',
      },
    },
    {
      pointer: 'page_region:2:en',
      label: 'Footer Region',
      updated: Math.floor(Date.now() / 1000) - 5 * 24 * 60 * 60, // 5 days ago
      entity_type: 'page_region',
      data_hash: 'data-hash-8',
      entity_id: 2,
      langcode: 'en',
      owner: {
        name: 'Jordan Smith',
        avatar:
          'https://images.unsplash.com/photo-1489980557514-251d61e3eeb6?&w=64&h=64&dpr=2&q=70&crop=faces&fit=crop',
        id: 8,
        uri: '/user/8',
      },
    },
  ],
  singleChange: [
    {
      pointer: 'node:99:en',
      label: 'Single Page Example',
      updated: Math.floor(Date.now() / 1000) - 10 * 60, // 10 minutes ago
      entity_type: 'node',
      data_hash: 'data-hash-99',
      entity_id: 99,
      langcode: 'en',
      owner: {
        name: 'Solo Developer',
        avatar: null,
        id: 99,
        uri: '/user/99',
      },
    },
  ],
};

// Interactive wrapper component for stories that need state management
const InteractiveChangeGroup = (args: any) => {
  const [selectedChanges, setSelectedChanges] = useState<UnpublishedChange[]>(
    args.selectedChanges || [],
  );

  return (
    <div style={{ maxWidth: '600px', width: '100%' }}>
      <ChangeGroup
        {...args}
        selectedChanges={selectedChanges}
        setSelectedChanges={setSelectedChanges}
      />
    </div>
  );
};

const meta: Meta<typeof ChangeGroup> = {
  title: 'Components/Publish Review/ChangeGroup',
  component: ChangeGroup,
  parameters: {
    layout: 'centered',
    docs: {
      description: {
        component: `
ChangeGroup displays a group of unpublished changes of the same entity type within the review interface.
It provides group-level selection functionality and displays all changes of a specific type with their individual actions.
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
    entityType: {
      control: 'select',
      options: ['node', 'js_component', 'asset_library', 'page_region'],
      description: 'The entity type that determines the group label',
    },
    changes: {
      control: false,
      description: 'Array of unpublished changes to display in the group',
    },
    isBusy: {
      control: 'boolean',
      description: 'Whether operations are in progress (disables interactions)',
    },
    selectedChanges: {
      control: false,
      description: 'Array of currently selected changes across all groups',
    },
    setSelectedChanges: {
      action: 'setSelectedChanges',
      description: 'Callback to update selected changes',
    },
    onDiscardClick: {
      action: 'onDiscardClick',
      description: 'Callback when discard action is triggered on a change',
    },
    onViewClick: {
      action: 'onViewClick',
      description: 'Callback when view action is triggered on a change',
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

type Story = StoryObj<typeof ChangeGroup>;

// Different entity type groups
export const ContentGroup: Story = {
  render: InteractiveChangeGroup,
  args: {
    entityType: 'node',
    changes: mockChanges.pages,
  },
  parameters: {
    docs: {
      description: {
        story: 'A group of content changes (pages/nodes) with multiple items.',
      },
    },
  },
};

export const ComponentsGroup: Story = {
  render: InteractiveChangeGroup,
  args: {
    entityType: 'js_component',
    changes: mockChanges.components,
  },
  parameters: {
    docs: {
      description: {
        story: 'A group of component changes with different update times.',
      },
    },
  },
};

export const AssetsGroup: Story = {
  render: InteractiveChangeGroup,
  args: {
    entityType: 'asset_library',
    changes: mockChanges.assets,
  },
  parameters: {
    docs: {
      description: {
        story: 'A group of asset library changes.',
      },
    },
  },
};

export const RegionsGroup: Story = {
  render: InteractiveChangeGroup,
  args: {
    entityType: 'page_region',
    changes: mockChanges.regions,
  },
  parameters: {
    docs: {
      description: {
        story: 'A group of page region changes.',
      },
    },
  },
};

// Single change scenarios
export const SingleChange: Story = {
  render: InteractiveChangeGroup,
  args: {
    entityType: 'node',
    changes: mockChanges.singleChange,
  },
  parameters: {
    docs: {
      description: {
        story: 'A group with only one change item.',
      },
    },
  },
};

// Selection state variations
export const PartiallySelected: Story = {
  render: InteractiveChangeGroup,
  args: {
    entityType: 'node',
    changes: mockChanges.pages,
    selectedChanges: [mockChanges.pages[0]], // Only first change selected
  },
  parameters: {
    docs: {
      description: {
        story:
          'Group with some changes selected, showing indeterminate checkbox state.',
      },
    },
  },
};

export const FullySelected: Story = {
  render: InteractiveChangeGroup,
  args: {
    entityType: 'node',
    changes: mockChanges.pages,
    selectedChanges: mockChanges.pages, // All changes selected
  },
  parameters: {
    docs: {
      description: {
        story:
          'Group with all changes selected, showing checked checkbox state.',
      },
    },
  },
};

export const NotSelected: Story = {
  render: InteractiveChangeGroup,
  args: {
    entityType: 'node',
    changes: mockChanges.pages,
    selectedChanges: [], // No changes selected
  },
  parameters: {
    docs: {
      description: {
        story:
          'Group with no changes selected, showing unchecked checkbox state.',
      },
    },
  },
};

// Busy state
export const BusyState: Story = {
  render: InteractiveChangeGroup,
  args: {
    entityType: 'js_component',
    changes: mockChanges.components,
    isBusy: true,
  },
  parameters: {
    docs: {
      description: {
        story: 'Group in busy state with disabled interactions.',
      },
    },
  },
};

// Without view action
export const WithoutViewAction: Story = {
  render: InteractiveChangeGroup,
  args: {
    entityType: 'node',
    changes: mockChanges.pages,
    onViewClick: undefined,
  },
  parameters: {
    docs: {
      description: {
        story: 'Group without view action - view buttons will not be shown.',
      },
    },
  },
};

// Interactive playground
export const Playground: Story = {
  render: InteractiveChangeGroup,
  args: {
    entityType: 'node',
    changes: mockChanges.pages,
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
