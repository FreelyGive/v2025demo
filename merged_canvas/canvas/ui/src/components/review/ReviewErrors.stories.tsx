import { Theme } from '@radix-ui/themes';

import ReviewErrors from './ReviewErrors';

import type { Meta, StoryObj } from '@storybook/react';

const meta: Meta<typeof ReviewErrors> = {
  title: 'Components/Publish Review/ReviewErrors',
  component: ReviewErrors,
  parameters: {
    layout: 'padded',
  },
  decorators: [
    (Story) => (
      <Theme>
        <Story />
      </Theme>
    ),
  ],
};

export default meta;
type Story = StoryObj<typeof ReviewErrors>;

export const NoErrors: Story = {
  args: {
    errorState: undefined,
  },
};

export const SingleError: Story = {
  args: {
    errorState: {
      errors: [
        {
          detail: 'The title field is required.',
          meta: {
            label: 'Article: Sample Article',
            entity_type: '',
            entity_id: '',
          },
          code: 0,
          source: {
            pointer: '',
          },
        },
      ],
    },
  },
};

export const MultipleErrors: Story = {
  args: {
    errorState: {
      errors: [
        {
          detail: 'The title field is required.',
          meta: {
            label: 'Article: Sample Article',
            entity_type: 'node',
            entity_id: '1',
          },
          code: 0,
          source: {
            pointer: 'node/1/title',
          },
        },
        {
          detail: 'The body field cannot be empty.',
          meta: {
            label: 'Article: Sample Article',
            entity_type: 'node',
            entity_id: '2',
          },
          code: 0,
          source: {
            pointer: 'node/2/body',
          },
        },
        {
          detail: 'Invalid email format.',
          meta: {
            label: 'User: John Doe',
            entity_type: 'user',
            entity_id: '3',
          },
          code: 0,
          source: {
            pointer: 'user/3/email',
          },
        },
      ],
    },
  },
};
