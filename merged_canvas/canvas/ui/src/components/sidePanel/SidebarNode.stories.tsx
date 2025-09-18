import { TriangleRightIcon } from '@radix-ui/react-icons';
import { Flex } from '@radix-ui/themes';

import Panel from '@/components/Panel';
import UnifiedMenu from '@/components/UnifiedMenu';

import SidebarNode from './SidebarNode';

import type { Meta, StoryObj } from '@storybook/react';
import type { SideBarNodeVariant } from './SidebarNode';

const meta: Meta<typeof SidebarNode> = {
  title: 'Components/Sidebar/SidebarNode',
  component: SidebarNode,
  parameters: {
    layout: 'centered',
  },
  argTypes: {
    variant: {
      control: {
        type: 'select',
        options: Object.keys({} as Record<SideBarNodeVariant, any>),
      },
    },
  },
  decorators: [
    (Story) => (
      <Panel
        width="var(--sidebar-left-width)"
        px="2"
        py="4"
        style={{ backgroundColor: 'var(--sand-1)' }}
      >
        <Story />
      </Panel>
    ),
  ],
};

export default meta;

type Story = StoryObj<typeof SidebarNode>;

const dropdownMenuContent = (
  <UnifiedMenu.Content menuType="dropdown" align="start" side="right">
    <UnifiedMenu.Item>Edit</UnifiedMenu.Item>
    <UnifiedMenu.Item>Duplicate</UnifiedMenu.Item>
    <UnifiedMenu.Separator />
    <UnifiedMenu.Item color="red">Delete</UnifiedMenu.Item>
  </UnifiedMenu.Content>
);

export const Default: Story = {
  name: 'All variants: Default',
  render: () => (
    <Flex direction="column" gap="2">
      <SidebarNode
        title="Component"
        variant="component"
        dropdownMenuContent={dropdownMenuContent}
      />
      <SidebarNode
        title="Code component"
        variant="codeComponent"
        dropdownMenuContent={dropdownMenuContent}
      />
      <SidebarNode
        title="Block component"
        variant="dynamicComponent"
        dropdownMenuContent={dropdownMenuContent}
      />
      <SidebarNode
        title="Pattern"
        variant="pattern"
        dropdownMenuContent={dropdownMenuContent}
      />
      <SidebarNode
        title="Slot"
        variant="slot"
        dropdownMenuContent={dropdownMenuContent}
      />
      <SidebarNode
        title="Code"
        variant="code"
        dropdownMenuContent={dropdownMenuContent}
      />
      <SidebarNode
        title="Region"
        variant="region"
        dropdownMenuContent={dropdownMenuContent}
      />
    </Flex>
  ),
};

export const Hovered: Story = {
  name: 'All variants: Hovered',
  render: () => (
    <Flex direction="column" gap="2">
      <SidebarNode
        title="Component"
        variant="component"
        hovered
        dropdownMenuContent={dropdownMenuContent}
      />
      <SidebarNode
        title="Code component"
        variant="codeComponent"
        hovered
        dropdownMenuContent={dropdownMenuContent}
      />
      <SidebarNode
        title="Block component"
        variant="dynamicComponent"
        hovered
        dropdownMenuContent={dropdownMenuContent}
      />
      <SidebarNode
        title="Pattern"
        variant="pattern"
        hovered
        dropdownMenuContent={dropdownMenuContent}
      />
      <SidebarNode
        title="Slot"
        variant="slot"
        hovered
        dropdownMenuContent={dropdownMenuContent}
      />
      <SidebarNode
        title="Code"
        variant="code"
        hovered
        dropdownMenuContent={dropdownMenuContent}
      />
      <SidebarNode
        title="Region"
        variant="region"
        hovered
        dropdownMenuContent={dropdownMenuContent}
      />
    </Flex>
  ),
};

export const Selected: Story = {
  name: 'All variants: Selected',
  render: () => (
    <Flex direction="column" gap="2">
      <SidebarNode
        title="Component"
        variant="component"
        selected
        dropdownMenuContent={dropdownMenuContent}
      />
      <SidebarNode
        title="Code component"
        variant="codeComponent"
        selected
        dropdownMenuContent={dropdownMenuContent}
      />
      <SidebarNode
        title="Block component"
        variant="dynamicComponent"
        selected
        dropdownMenuContent={dropdownMenuContent}
      />
      <SidebarNode
        title="Pattern"
        variant="pattern"
        selected
        dropdownMenuContent={dropdownMenuContent}
      />
      <SidebarNode
        title="Slot"
        variant="slot"
        selected
        dropdownMenuContent={dropdownMenuContent}
      />
      <SidebarNode
        title="Code"
        variant="code"
        selected
        dropdownMenuContent={dropdownMenuContent}
      />
      <SidebarNode
        title="Region"
        variant="region"
        selected
        dropdownMenuContent={dropdownMenuContent}
      />
    </Flex>
  ),
};

export const SelectedAndHovered: Story = {
  name: 'All variants: Selected and hovered',
  render: () => (
    <Flex direction="column" gap="2">
      <SidebarNode
        title="Component"
        variant="component"
        selected
        hovered
        dropdownMenuContent={dropdownMenuContent}
      />
      <SidebarNode
        title="Code component"
        variant="codeComponent"
        selected
        hovered
        dropdownMenuContent={dropdownMenuContent}
      />
      <SidebarNode
        title="Block component"
        variant="dynamicComponent"
        selected
        dropdownMenuContent={dropdownMenuContent}
      />
      <SidebarNode
        title="Pattern"
        variant="pattern"
        selected
        hovered
        dropdownMenuContent={dropdownMenuContent}
      />
      <SidebarNode
        title="Slot"
        variant="slot"
        selected
        hovered
        dropdownMenuContent={dropdownMenuContent}
      />
      <SidebarNode
        title="Code"
        variant="code"
        selected
        hovered
        dropdownMenuContent={dropdownMenuContent}
      />
      <SidebarNode
        title="Region"
        variant="region"
        selected
        hovered
        dropdownMenuContent={dropdownMenuContent}
      />
    </Flex>
  ),
};

export const Open: Story = {
  name: 'Open',
  args: {
    title: 'Component',
    open: true,
    selected: true,
  },
};

export const WithoutDropdownMenu: Story = {
  name: 'Without dropdown menu',
  args: {
    title: 'Component',
    dropdownMenuContent: null,
  },
};

export const WithLongTitle: Story = {
  name: 'With long title',
  args: {
    title: 'This is a very long title that should be truncated',
    dropdownMenuContent: dropdownMenuContent,
  },
};

export const WithLongTitleAndWithoutDropdownMenu: Story = {
  name: 'With long title and without dropdown menu',
  args: {
    title: 'This is a very long title that should be truncated',
    dropdownMenuContent: null,
  },
};

export const WithLeadingContent: Story = {
  name: 'With leading content',
  args: {
    title: 'Example slot',
    variant: 'slot',
    leadingContent: <TriangleRightIcon />,
    dropdownMenuContent: dropdownMenuContent,
  },
};

export const WithLeadingContentAndLongTitle: Story = {
  name: 'With leading content and long title',
  args: {
    title: 'This is a very long title that should be truncated',
    variant: 'slot',
    leadingContent: <TriangleRightIcon />,
    dropdownMenuContent: dropdownMenuContent,
  },
};
