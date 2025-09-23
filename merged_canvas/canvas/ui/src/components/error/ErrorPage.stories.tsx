import { Text } from '@radix-ui/themes';
import { fn } from '@storybook/test';

import ErrorCard from '@/components/error/ErrorCard';

import ErrorPage from './ErrorPage';

import type { Meta, StoryObj } from '@storybook/react';

const meta: Meta<typeof ErrorPage> = {
  title: 'Components/Errors/ErrorPage',
  component: ErrorPage,
  parameters: {
    layout: 'fullscreen',
  },
};

export default meta;

export const Default: StoryObj<typeof ErrorPage> = {
  args: {
    children: (
      <Text size="4" style={{ color: 'var(--text-primary)' }}>
        An error occurred!
      </Text>
    ),
  },
};

export const WithCustomMessage: StoryObj<typeof ErrorCard> = {
  args: {
    resetErrorBoundary: fn(),
    title: 'Something went wrong. Please try again later.',
    error: 'An unknown error occurred.',
    resetButtonText: 'Reset Text',
  },
  render: (args) => (
    <ErrorPage>
      <ErrorCard {...args} />
    </ErrorPage>
  ),
};
