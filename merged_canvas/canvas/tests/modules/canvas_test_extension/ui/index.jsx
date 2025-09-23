import ReactDOM from 'react-dom';
import * as React from 'react';
import { Provider } from 'react-redux';
import { createRoot } from 'react-dom/client';
import ConceptProver from './components/ConceptProver.jsx';
import ExampleExtension from './components/ExampleExtension';

const { drupalSettings } = window;
const container = document.createElement('div');
container.id = 'canvas-test-extension';

document.body.append(container);
const root = createRoot(container);

// The Canvas store is available in Drupal settings, making it possible to add it
// to this App via a <Provider>, giving us access to its data and actions.
const { store } = drupalSettings.canvas;
root.render(
  <Provider store={store}>
    <ExampleExtension />
  </Provider>,
);
