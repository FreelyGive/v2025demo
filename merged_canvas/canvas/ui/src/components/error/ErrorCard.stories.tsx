import { Box } from '@radix-ui/themes';
import { fn } from '@storybook/test';

import ErrorCard from './ErrorCard';

import type { Meta, StoryObj } from '@storybook/react';

const meta: Meta<typeof ErrorCard> = {
  title: 'Components/Errors/ErrorCard',
  component: ErrorCard,
  parameters: {
    layout: 'centered',
  },
  argTypes: {
    title: { control: 'text' },
    error: { control: 'text' },
    resetErrorBoundary: { action: 'resetErrorBoundary' },
    resetButtonText: { control: 'text' },
  },
};

export default meta;

type Story = StoryObj<typeof ErrorCard>;

export const Default: Story = {
  args: {
    title: 'An unexpected error has occurred.',
    error: 'Something went wrong while fetching the data.',
  },
};

export const WithResetButton: Story = {
  args: {
    title: 'An unexpected error has occurred.',
    error: 'Please try again later.',
    resetErrorBoundary: fn(),
    resetButtonText: 'Retry',
  },
};

export const CustomTitleAndError: Story = {
  args: {
    title: 'Custom Error Title',
    error: 'A specific error has been encountered.',
    resetErrorBoundary: fn(),
    resetButtonText: 'Go Back',
  },
};

// Wrapping the component with a container if needed
export const WithinContainer: Story = {
  args: {
    title: 'An error within a container',
    error: 'The system could not process your request.',
    resetErrorBoundary: fn(),
    resetButtonText: 'Retry',
  },
  render: (args) => (
    <Box>
      <ErrorCard {...args} />
    </Box>
  ),
};
