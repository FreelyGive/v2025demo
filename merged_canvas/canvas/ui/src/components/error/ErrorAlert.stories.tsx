import { fn } from '@storybook/test';

import ErrorAlert from './ErrorAlert';

import type { Meta, StoryObj } from '@storybook/react';

// Metadata for the story
const meta: Meta<typeof ErrorAlert> = {
  title: 'Components/Errors/ErrorAlert',
  component: ErrorAlert,
  argTypes: {
    title: { control: 'text' },
    error: { control: 'text' },
    resetErrorBoundary: { action: 'resetErrorBoundary' },
    resetButtonText: { control: 'text' },
  },
};

export default meta;

// Default Story
export const Default: StoryObj<typeof ErrorAlert> = {
  args: {
    title: 'An unexpected error has occurred.',
    error: 'Something went wrong while processing your request.',
    resetButtonText: 'Try again',
    resetErrorBoundary: fn(),
  },
};

// Story for when a reset function is provided
export const WithReset: StoryObj<typeof ErrorAlert> = {
  args: {
    title: 'An unexpected error has occurred.',
    error: 'An error occurred while fetching data.',
    resetButtonText: 'Reload',
    resetErrorBoundary: fn(),
  },
};

// Story without an error message
export const WithoutErrorMessage: StoryObj<typeof ErrorAlert> = {
  args: {
    title: 'Error Alert',
    error: undefined,
    resetButtonText: 'Retry',
    resetErrorBoundary: fn(),
  },
};
