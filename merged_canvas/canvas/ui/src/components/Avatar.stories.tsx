import Avatar from './Avatar';

import type { Meta, StoryObj } from '@storybook/react';

const meta: Meta<typeof Avatar> = {
  title: 'Components/Avatar',
  component: Avatar,
  argTypes: {
    name: { control: 'text', description: 'User name' },
    imageUrl: {
      control: 'text',
      description: 'Avatar image URL.',
    },
  },
};

export default meta;

type Story = StoryObj<typeof Avatar>;

// cSpell:disable
export const Default: Story = {
  args: {
    name: 'Dries Buytaert',
    imageUrl:
      'https://www.drupal.org/files/styles/grid-2-2x-square/public/user-pictures/picture-1-1401055330.jpg?itok=E9No1cHd',
  },
};
