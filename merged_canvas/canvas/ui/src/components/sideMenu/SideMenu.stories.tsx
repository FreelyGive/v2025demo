import { Provider } from 'react-redux';
import { configureStore } from '@reduxjs/toolkit';

import { primaryPanelSlice } from '@/features/ui/primaryPanelSlice';

import { SideMenu } from './SideMenu';

import type { Meta, StoryObj } from '@storybook/react';

// Define the Story args interface separately
interface StoryArgs {
  activePanel?: string;
  isDevMode?: boolean;
}

// Create a mock store with the required slices
const createMockStore = (
  activePanel: string = 'layers',
  isDevMode: boolean = false,
) => {
  return configureStore({
    reducer: {
      primaryPanel: primaryPanelSlice.reducer,
      configuration: (
        state = {
          devMode: isDevMode,
          baseUrl: '/',
          entityType: 'none',
          entity: 'none',
          isNew: true,
          isPublished: false,
        },
      ) => state,
    },
    preloadedState: {
      primaryPanel: {
        activePanel,
        isHidden: false,
        aiPanelOpen: false,
        manageLibraryTab: null,
      },
    },
  });
};

const meta = {
  component: SideMenu,
  title: 'Components/SideMenu',
  parameters: {
    layout: 'centered',
  },
  decorators: [
    (Story: any, context: any) => {
      // Access custom args safely
      const args = context.args as StoryArgs;
      const activePanel = args.activePanel || 'layers';
      const isDevMode = args.isDevMode || false;
      const mockStore = createMockStore(activePanel, isDevMode);

      return (
        <Provider store={mockStore}>
          <div style={{ height: '600px', display: 'flex' }}>
            <Story />
          </div>
        </Provider>
      );
    },
  ],
} satisfies Meta<typeof SideMenu>;

export default meta;

type Story = StoryObj<typeof SideMenu>;

export const Default: Story = {
  args: {
    activePanel: 'layers',
  } as StoryArgs,
};

export const LibraryActive: Story = {
  args: {
    activePanel: 'library',
  } as StoryArgs,
};

export const WithExtensions: Story = {
  args: {
    activePanel: 'extensions',
    isDevMode: true,
  } as StoryArgs,
};
