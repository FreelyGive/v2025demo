/**
 * @file
 * Renders a code editor preview: used inside the code editor preview iframe.
 *
 * The iframe's markup is expected to contain a <script> tag with the following
 * content:
 *
 * @code
 * <script id="canvas-code-editor-preview-data" type="application/json">
 *   {
 *     "compiledJsUrl": ...,
 *     "propValues": ...,
 *     "slotNames": ...,
 *   }
 * </script>
 * @endcode
 *
 * This file is copied to the dist directory by Vite when the app is built.
 * @see ui/vite.config.ts
 */

import { h, render } from 'preact';

// Get the data from the script tag.
const dataElement = document.getElementById('canvas-code-editor-preview-data');
if (!dataElement) {
  throw new Error('Could not find code editor preview data element');
}

let data;
try {
  data = JSON.parse(dataElement.textContent);
} catch (e) {
  throw new Error('Failed to parse code editor preview data: ' + e.message);
}

const {
  compiledJsUrl,
  compiledJsForSlotsUrl,
  propValues,
  slotNames,
  drupalSettings,
} = data;

if (!compiledJsUrl) {
  throw new Error(
    'Missing required property in code editor preview data: compiledJsUrl',
  );
}

if (!compiledJsForSlotsUrl) {
  throw new Error(
    'Missing required property in code editor preview data: compiledJsForSlotsUrl',
  );
}

if (!propValues) {
  throw new Error(
    'Missing required property in code editor preview data: propValues',
  );
}

if (!slotNames) {
  throw new Error(
    'Missing required property in code editor preview data: slotNames',
  );
}

if (!drupalSettings) {
  throw new Error(
    'Missing required property in code editor preview data: drupalSettings',
  );
}

window.drupalSettings = drupalSettings;

// Import the compiled JavaScript modules and render the component.
Promise.all([import(compiledJsUrl), import(compiledJsForSlotsUrl)]).then(
  ([mainModule, slotsModule]) => {
    // Revoke the URLs to free up resources.
    URL.revokeObjectURL(compiledJsUrl);
    URL.revokeObjectURL(compiledJsForSlotsUrl);

    // Create a new object with the props and slots.
    const propsAndSlots = {
      ...propValues,
      ...slotNames.reduce((acc, name) => {
        // The example slot values are compiled as Preact components.
        acc[name] = h(slotsModule[name]);
        return acc;
      }, {}),
    };

    // Render the component.
    render(
      h(mainModule.default, propsAndSlots),
      document.getElementById('canvas-code-editor-preview-root'),
    );
  },
);
