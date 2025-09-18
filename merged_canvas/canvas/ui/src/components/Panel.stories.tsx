import { Heading } from '@radix-ui/themes';

import Panel from './Panel';

import type React from 'react';
import type { Meta, StoryObj } from '@storybook/react';

const meta: Meta<typeof Panel> = {
  title: 'Components/Panel',
  component: Panel,
  argTypes: {
    asChild: {
      control: 'boolean',
      description:
        'Whether to use the component as a child (renders a Slot if true else Box).',
      defaultValue: false,
    },
    className: {
      control: 'text',
      description: 'Additional class names for styling.',
    },
    children: {
      control: 'text',
      description: 'Content to render inside the panel.',
    },
  },
  parameters: {
    layout: 'centered',
  },
};

export default meta;

type Story = StoryObj<typeof Panel>;

const CompHeading = ({
  text,
  style,
  ...rest
}: {
  text: string;
  style?: React.CSSProperties;
}) => {
  return (
    <Heading style={style} as={'h3'} {...rest} m={'2'}>
      {text}
    </Heading>
  );
};

export const Default: Story = {
  args: {
    children: <CompHeading text="This is a default Panel." />,
    className: '',
    asChild: false,
  },
  render: (args) => <Panel p={'2'} {...args} />,
};

export const CustomStyledPanel: Story = {
  args: {
    children: (
      <CompHeading
        style={{ color: 'white' }}
        text={'This Panel has custom class.'}
      />
    ),
    className: 'dark',
    asChild: false,
  },
  render: (args) => <Panel p={'2'} {...args} />,
};

export const WithAsChild: Story = {
  args: {
    children: <CompHeading text={'This is a Box-wrapped content.'} />,
    asChild: false,
    className: '',
  },
  render: (args) => <Panel p={'2'} {...args} />,
};
