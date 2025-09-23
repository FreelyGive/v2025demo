import { fn } from '@storybook/test';

import ContentPreviewSelector from './ContentPreviewSelector';

import type { Meta, StoryObj } from '@storybook/react';

const meta: Meta<typeof ContentPreviewSelector> = {
  title: 'Components/Templates/ContentPreviewSelector',
  component: ContentPreviewSelector,
  tags: ['autodocs'],
  args: {
    onSelectionChange: fn(),
  },
};

export default meta;

type Story = StoryObj<typeof ContentPreviewSelector>;

export const EmptyState: Story = {
  args: {
    items: {},
  },
};

export const WithContentItems: Story = {
  args: {
    items: {
      '1': { id: '1', label: 'Homepage Article' },
      '2': { id: '2', label: 'About Us Page' },
      '3': { id: '3', label: 'Product Launch Blog Post' },
      '4': { id: '4', label: 'Contact Information' },
      '5': { id: '5', label: 'Terms and Conditions' },
    },
  },
};

export const WithSelectedItem: Story = {
  args: {
    items: {
      '1': { id: '1', label: 'Homepage Article' },
      '2': { id: '2', label: 'About Us Page' },
      '3': { id: '3', label: 'Product Launch Blog Post' },
      '4': { id: '4', label: 'Contact Information' },
      '5': { id: '5', label: 'Terms and Conditions' },
    },
    selectedItemId: '3',
  },
};
