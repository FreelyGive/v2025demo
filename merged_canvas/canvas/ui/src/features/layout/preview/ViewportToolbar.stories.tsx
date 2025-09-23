import { useRef } from 'react';
import { Provider } from 'react-redux';
import { configureStore } from '@reduxjs/toolkit';

import { uiSliceReducer } from '@/features/ui/uiSlice';

import ViewportToolbar from './ViewportToolbar';

import type { Meta, StoryObj } from '@storybook/react';

const store = configureStore({
  reducer: {
    ui: uiSliceReducer,
  },
});

const ViewportToolbarWithRefs = (args: any) => {
  const editorPaneRef = useRef<HTMLDivElement>(null);
  const scalingContainerRef = useRef<HTMLDivElement>(null);

  return (
    <Provider store={store}>
      <div style={{ width: '1000px' }}>
        <div style={{ padding: '20px' }}>
          <ViewportToolbar
            editorPaneRef={editorPaneRef}
            scalingContainerRef={scalingContainerRef}
          />
          <div
            ref={editorPaneRef}
            style={{ width: '100%', height: '500px', overflow: 'auto' }}
          >
            <div
              ref={scalingContainerRef}
              style={{
                width: '100%',
                height: '300px',
                background: 'peachpuff',
              }}
            ></div>
          </div>
        </div>
      </div>
    </Provider>
  );
};

const meta: Meta<typeof ViewportToolbar> = {
  title: 'Components/ViewportToolbar',
  component: ViewportToolbarWithRefs,
  parameters: {
    layout: 'centered',
  },
};

export default meta;

type Story = StoryObj<typeof ViewportToolbar>;

export const Default: Story = {};
