// PageStatusBadge.stories.tsx
import { PageStatusBadge } from './PageStatus';

import type { Meta, StoryFn } from '@storybook/react';
import type { PageStatusBadgeProps } from './PageStatus';

export default {
  title: 'Components/PageStatusBadge',
  component: PageStatusBadge,
  argTypes: {
    isNew: { control: 'boolean' },
    hasAutoSave: { control: 'boolean' },
    isPublished: { control: 'boolean' },
  },
} as Meta;

const Template: StoryFn<typeof PageStatusBadge> = (
  args: PageStatusBadgeProps,
) => <PageStatusBadge {...args} />;

export const Draft = Template.bind({});
Draft.args = {
  isNew: true,
  hasAutoSave: false,
  isPublished: false,
};

export const Changed = Template.bind({});
Changed.args = {
  isNew: false,
  hasAutoSave: true,
  isPublished: false,
};

export const Published = Template.bind({});
Published.args = {
  isNew: false,
  hasAutoSave: false,
  isPublished: true,
};

export const Archived = Template.bind({});
Archived.args = {
  isNew: false,
  hasAutoSave: false,
  isPublished: false,
};
