// cspell:ignore Balint Takacs Chueka
import { useState } from 'react';
import { fn } from '@storybook/test';

import ChangeList from './ChangeList';

import type { Meta, StoryObj } from '@storybook/react';
import type {
  UnpublishedChange,
  UnpublishedChangeGroups,
} from '@/types/Review';

// Mock data for comprehensive testing
const createMockChangeGroups = (): UnpublishedChangeGroups => ({
  node: [
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
  js_component: [
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
    {
      pointer: 'js_component:3:en',
      label: 'Footer Component',
      updated: Math.floor(Date.now() / 1000) - 6 * 60 * 60, // 6 hours ago
      entity_type: 'js_component',
      data_hash: 'data-hash-6',
      entity_id: 3,
      langcode: 'en',
      owner: {
        name: 'Jordan Smith',
        avatar:
          'https://images.unsplash.com/photo-1489980557514-251d61e3eeb6?&w=64&h=64&dpr=2&q=70&crop=faces&fit=crop',
        id: 6,
        uri: '/user/6',
      },
    },
  ],
  asset_library: [
    {
      pointer: 'asset_library:1:en',
      label: 'Brand Assets Library',
      updated: Math.floor(Date.now() / 1000) - 24 * 60 * 60, // 1 day ago
      entity_type: 'asset_library',
      data_hash: 'data-hash-7',
      entity_id: 1,
      langcode: 'en',
      owner: {
        name: 'Madelyn Levis',
        avatar:
          'https://images.unsplash.com/photo-1522075469751-3a6694fb2f61?&w=64&h=64&dpr=2&q=70&crop=faces&fit=crop',
        id: 7,
        uri: '/user/7',
      },
    },
    {
      pointer: 'asset_library:2:en',
      label: 'Image Gallery Assets',
      updated: Math.floor(Date.now() / 1000) - 12 * 60 * 60, // 12 hours ago
      entity_type: 'asset_library',
      data_hash: 'data-hash-8',
      entity_id: 2,
      langcode: 'en',
      owner: {
        name: 'Casey Williams',
        avatar: null,
        id: 8,
        uri: '/user/8',
      },
    },
  ],
  page_region: [
    {
      pointer: 'page_region:1:en',
      label: 'Header Region',
      updated: Math.floor(Date.now() / 1000) - 7 * 24 * 60 * 60, // 1 week ago
      entity_type: 'page_region',
      data_hash: 'data-hash-9',
      entity_id: 1,
      langcode: 'en',
      owner: {
        name: 'Taylor Swift',
        avatar: null,
        id: 9,
        uri: '/user/9',
      },
    },
  ],
});

// Smaller datasets for specific scenarios
const singleGroupData: UnpublishedChangeGroups = {
  node: [
    {
      pointer: 'node:1:en',
      label: 'Single Page Example',
      updated: Math.floor(Date.now() / 1000) - 10 * 60, // 10 minutes ago
      entity_type: 'node',
      data_hash: 'data-hash-single',
      entity_id: 1,
      langcode: 'en',
      owner: {
        name: 'Solo Developer',
        avatar: null,
        id: 1,
        uri: '/user/1',
      },
    },
  ],
};

const emptyGroupsData: UnpublishedChangeGroups = {};

// Interactive wrapper component for stories that need state management
const InteractiveChangeList = (args: any) => {
  const [selectedChanges, setSelectedChanges] = useState<UnpublishedChange[]>(
    args.selectedChanges || [],
  );

  return (
    <div style={{ maxWidth: '700px', width: '100%' }}>
      <ChangeList
        {...args}
        selectedChanges={selectedChanges}
        setSelectedChanges={setSelectedChanges}
      />
    </div>
  );
};

const meta: Meta<typeof ChangeList> = {
  title: 'Components/Publish Review/ChangeList',
  component: ChangeList,
  parameters: {
    layout: 'centered',
    docs: {
      description: {
        component: `
ChangeList is the container component that organizes and displays multiple groups of unpublished changes.
It renders ChangeGroup components for each entity type that has pending changes.
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
    groups: {
      control: false,
      description: 'Object containing changes grouped by entity type',
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
      <div style={{ maxWidth: '700px', width: '100%' }}>
        <Story />
      </div>
    ),
  ],
};

export default meta;

type Story = StoryObj<typeof ChangeList>;

// Main scenarios
export const FullChangeList: Story = {
  render: InteractiveChangeList,
  args: {
    groups: createMockChangeGroups(),
  },
  parameters: {
    docs: {
      description: {
        story:
          'Complete change list showing all entity types with multiple changes in each group. Demonstrates the full functionality with cross-group selection management.',
      },
    },
  },
};

export const SingleGroup: Story = {
  render: InteractiveChangeList,
  args: {
    groups: singleGroupData,
  },
  parameters: {
    docs: {
      description: {
        story:
          'Change list with only one entity type group containing one change.',
      },
    },
  },
};

export const EmptyGroups: Story = {
  render: InteractiveChangeList,
  args: {
    groups: emptyGroupsData,
  },
  parameters: {
    docs: {
      description: {
        story:
          'Empty groups object - component should render nothing when no changes exist.',
      },
    },
  },
};

// Selection state scenarios
export const WithSomeSelected: Story = {
  render: InteractiveChangeList,
  args: {
    groups: createMockChangeGroups(),
    selectedChanges: [
      createMockChangeGroups().node[0], // First content item
      createMockChangeGroups().js_component[1], // Second component
    ],
  },
  parameters: {
    docs: {
      description: {
        story:
          'Change list with some changes pre-selected across different groups, demonstrating cross-group selection state.',
      },
    },
  },
};

export const WithGroupFullySelected: Story = {
  render: InteractiveChangeList,
  args: {
    groups: createMockChangeGroups(),
    selectedChanges: createMockChangeGroups().page_region, // All region changes selected
  },
  parameters: {
    docs: {
      description: {
        story:
          'Change list with one entire group selected, showing how group checkboxes respond to full selection.',
      },
    },
  },
};

// Operational states
export const BusyState: Story = {
  render: InteractiveChangeList,
  args: {
    groups: createMockChangeGroups(),
    isBusy: true,
  },
  parameters: {
    docs: {
      description: {
        story:
          'Change list in busy state - all interactions disabled during operations.',
      },
    },
  },
};

export const WithoutViewAction: Story = {
  render: InteractiveChangeList,
  args: {
    groups: createMockChangeGroups(),
    onViewClick: undefined,
  },
  parameters: {
    docs: {
      description: {
        story:
          'Change list without view action capability - view buttons will not be shown.',
      },
    },
  },
};

// Specific entity type scenarios
export const ContentOnly: Story = {
  render: InteractiveChangeList,
  args: {
    groups: {
      node: createMockChangeGroups().node,
    },
  },
  parameters: {
    docs: {
      description: {
        story: 'Change list showing only content changes.',
      },
    },
  },
};

export const ComponentsOnly: Story = {
  render: InteractiveChangeList,
  args: {
    groups: {
      js_component: createMockChangeGroups().js_component,
    },
  },
  parameters: {
    docs: {
      description: {
        story: 'Change list showing only component changes.',
      },
    },
  },
};

export const AssetsAndRegions: Story = {
  render: InteractiveChangeList,
  args: {
    groups: {
      asset_library: createMockChangeGroups().asset_library,
      page_region: createMockChangeGroups().page_region,
    },
  },
  parameters: {
    docs: {
      description: {
        story: 'Change list showing only assets and regions groups.',
      },
    },
  },
};

// Edge cases and stress testing
export const ManyGroups: Story = {
  render: InteractiveChangeList,
  args: {
    groups: {
      ...createMockChangeGroups(),
      // Add some additional entity types
      custom_entity: [
        {
          pointer: 'custom_entity:1:en',
          label: 'Custom Entity Example',
          updated: Math.floor(Date.now() / 1000) - 60 * 60, // 1 hour ago
          entity_type: 'custom_entity',
          data_hash: 'data-hash-custom',
          entity_id: 1,
          langcode: 'en',
          owner: {
            name: 'Custom User',
            avatar: null,
            id: 10,
            uri: '/user/10',
          },
        },
      ],
      another_type: [
        {
          pointer: 'another_type:1:en',
          label: 'Another Type Example',
          updated: Math.floor(Date.now() / 1000) - 3 * 60 * 60, // 3 hours ago
          entity_type: 'another_type',
          data_hash: 'data-hash-another',
          entity_id: 1,
          langcode: 'en',
          owner: {
            name: 'Another User',
            avatar: null,
            id: 11,
            uri: '/user/11',
          },
        },
      ],
    },
  },
  parameters: {
    docs: {
      description: {
        story:
          'Change list with many different entity types to test scalability and unknown entity type handling.',
      },
    },
  },
};

// Interactive playground
export const Playground: Story = {
  render: InteractiveChangeList,
  args: {
    groups: createMockChangeGroups(),
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
