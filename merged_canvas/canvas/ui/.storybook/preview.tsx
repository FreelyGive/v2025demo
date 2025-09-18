import type { Preview } from '@storybook/react';
import { Theme } from "@radix-ui/themes";
import '@/styles/radix-themes';
import '@/styles/index.css';

const preview: Preview = {
  decorators: [
    (Story) => (
      <Theme
        accentColor="blue"
        hasBackground={false}
        panelBackground="solid"
        appearance="light"
      >
        <div className="canvas-app">
          {Story()}
        </div>
      </Theme>
    )
  ]
};

export default preview;
