import { InfoCircledIcon } from '@radix-ui/react-icons';
import { IconButton } from '@radix-ui/themes';

import Avatar from '@/components/Avatar';

import Tooltip from './Tooltip';

import type { Meta, StoryObj } from '@storybook/react';

const meta: Meta<typeof Tooltip> = {
  title: 'Components/Tooltip',
  component: Tooltip,
  argTypes: {
    content: { control: 'text', description: 'Tooltip content text' },
    children: {
      control: false,
      description: 'The element that triggers the tooltip',
    },
  },
};

export default meta;

type Story = StoryObj<typeof Tooltip>;

export const Default: Story = {
  args: {
    content: 'This is a tooltip',
    children: (
      <IconButton radius="full">
        <InfoCircledIcon />
      </IconButton>
    ),
  },
};

export const WithLongText: Story = {
  args: {
    content:
      'Lorem Ipsum is simply dummy text of the printing and typesetting industry.',
    children: (
      <IconButton radius="full">
        <InfoCircledIcon />
      </IconButton>
    ),
  },
};

// cSpell:disable
export const WithAvatar: Story = {
  args: {
    content: 'Dries Buytaert',
    children: (
      <Avatar
        name={'Dries Buytaert'}
        imageUrl={
          'https://www.drupal.org/files/styles/grid-2-2x-square/public/user-pictures/picture-1-1401055330.jpg?itok=E9No1cHd,'
        }
      />
    ),
  },
};

export const WithAvatarWithoutImage: Story = {
  args: {
    content: 'Dries Buytaert',
    children: <Avatar name={'Dries Buytaert'} />,
  },
};
