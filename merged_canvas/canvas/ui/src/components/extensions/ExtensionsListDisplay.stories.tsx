import { ExtensionsListDisplay } from '@/components/extensions/ExtensionsList';

import type { Meta, StoryObj } from '@storybook/react';

const kittenBase64 =
  /* cspell:disable-next-line */
  'data:image/jpeg;base64, /9j/2wBDAAYEBQYFBAYGBQYHBwYIChAKCgkJChQODwwQFxQYGBcUFhYaHSUfGhsjHBYWICwgIyYnKSopGR8tMC0oMCUoKSj/2wBDAQcHBwoIChMKChMoGhYaKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCj/wAARCAAYABgDASIAAhEBAxEB/8QAGAABAAMBAAAAAAAAAAAAAAAAAAMGBwX/xAAlEAABBAEDBAIDAAAAAAAAAAABAgMEEQAFEiEGBzFBE1EUFSL/xAAYAQADAQEAAAAAAAAAAAAAAAACAwQBBf/EAB4RAAICAgIDAAAAAAAAAAAAAAECAAMRIQQSIlJx/9oADAMBAAIRAxEAPwDPOopUeU604VNEPLKFniwFKJonOXqHS+pIeUzA02VIbcaUW1CI4vddGhQo5pR1TTdPYBg6HCbktuBTTxWtexI9Uu+fP9CssumddCe3KQ18rEptvei3B4FCxfF2br6vA5Nl1ewmvsuTg68jiWruVcHssy2sbVtwWm1JqqIYIIr1z6xmS9R9wP2kB6JqUZJU2C2+x+QfkVzR4Io8349YzKncjJWJaj1Mpz09aydxvn3kAnKaeQ42va6k2D9EYxnRbcvLEjBkM+U5qGoLmzZC35KwApaqs1wPAxjGAABoRagKOqjAn//Z';

const mockExtensions = [
  {
    name: 'Extension 1',
    imgSrc: kittenBase64,
    id: 'extension1',
  },
  {
    name: 'Extension with longer name 2',
    imgSrc: kittenBase64,
    id: 'extension2',
  },
  {
    name: 'Extension 3',
    imgSrc: kittenBase64,
    id: 'extension3',
  },
  {
    name: 'Extension 4',
    imgSrc: kittenBase64,
    id: 'extension4',
  },
  {
    name: 'Extension name 5',
    imgSrc: kittenBase64,
    id: 'extension5',
  },
];

const meta: Meta<typeof ExtensionsListDisplay> = {
  title: 'Components/ExtensionsList',
  component: ExtensionsListDisplay,
  args: {
    extensions: mockExtensions,
  },
  argTypes: {
    extensions: {
      control: { type: 'object' },
    },
  },
  decorators: [
    (Story) => (
      <div
        style={{
          maxWidth: '375px',
          background: '#fff',
          padding: '1em',
        }}
      >
        <Story />
      </div>
    ),
  ],
};

export default meta;

type Story = StoryObj<typeof ExtensionsListDisplay>;

export const Default: Story = {};
export const Empty: Story = {
  args: {
    extensions: [],
  },
};
