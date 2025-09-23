import React from 'react';
import ReactDom from 'react-dom';
import { createRoot } from 'react-dom/client';
import { Provider } from 'react-redux';
// eslint-disable-next-line @typescript-eslint/no-restricted-imports
import * as ReactRedux from 'react-redux';
import { Theme } from '@radix-ui/themes';
import * as ReduxToolkit from '@reduxjs/toolkit';

import AppRoutes from '@/app/AppRoutes';
import { makeStore } from '@/app/store';
import ErrorBoundary from '@/components/error/ErrorBoundary';
import twigToJSXComponentMap from '@/components/form/twig-to-jsx-component-map';
import { initialState } from '@/features/configuration/configurationSlice';
import hyperscriptify from '@/local_packages/hyperscriptify';
import propsify from '@/local_packages/hyperscriptify/propsify/standard';
import transforms from '@/utils/transforms';

import type { FC, ReactHTMLElement } from 'react';
import type { EnhancedStore } from '@reduxjs/toolkit';
import type { AppConfiguration } from '@/features/configuration/configurationSlice';

import '@/styles/radix-themes';
import '@/styles/index.css';

import { AJAX_UPDATE_FORM_STATE_EVENT } from '@/types/Ajax';
import {
  getBaseUrl,
  getCanvasSettings,
  getDrupal,
  getDrupalSettings,
} from '@/utils/drupal-globals';

// Provide these dependencies as globals so extensions do not have redundant and
// potentially conflicting dependencies.
window.React = React;
window.ReactDom = ReactDom;
window.Redux = ReactRedux;
window.ReduxToolkit = ReduxToolkit;

interface ProviderComponentProps {
  store: EnhancedStore;
}

const Drupal = getDrupal();
const canvasSettings = getCanvasSettings();
const baseUrl = getBaseUrl();
const drupalSettings = getDrupalSettings();

const container = document.getElementById('canvas');

const appConfiguration: AppConfiguration = {
  ...initialState,
  baseUrl: baseUrl || import.meta.env.BASE_URL,
  devMode: canvasSettings.devMode || false,
  homepagePath: canvasSettings.homepagePath,
};

const isAjaxing = () =>
  Drupal.ajax.instances.some(
    (instance: { ajaxing: boolean }) => instance && instance.ajaxing === true,
  );

const attachBehaviorsAfterAjaxing = (
  theContext: HTMLElement,
  theSettings: { doNotReinvoke?: boolean },
) => {
  document.body.dataset.canvasAjaxBehaviors = 'true';
  const attachTheBehaviors = () => {
    setTimeout(() => {
      Drupal.attachBehaviors(theContext, {
        ...theSettings,
        doNotReinvoke: true,
      });
    });
  };

  // If no AJAX operations are taking place, behaviors will be attached at
  // the end of the stack.
  if (!isAjaxing()) {
    attachTheBehaviors();
    document.body.dataset.canvasAjaxBehaviors = 'false';
  } else {
    // If AJAX operations are occurring, set up an interval that will run
    // until AJAX operations have stopped, after which behaviors are
    // attached and the interval cleared.
    const interval = setInterval(() => {
      if (!isAjaxing()) {
        attachTheBehaviors();
        document.body.dataset.canvasAjaxBehaviors = 'false';
        clearInterval(interval);
      }
    });
  }
};

Drupal.attachBehaviorsAfterAjaxing = attachBehaviorsAfterAjaxing;

// Add dialog-scoped CSS to the page on load so even dialogs triggered without
// AJAX have the correct CSS.
const dialogCss = drupalSettings?.canvas?.dialogCss || [];
const asResponse = dialogCss.map((path: string) => ({
  media: 'all',
  href: path,
  // Set this to let 'scopeCss' in ajax.command.customizations account for the
  // paths not being resolved by the rendering process.
  processPaths: true,
}));

// Timeout to ensure Drupal.AjaxCommands.prototype.add_css exists.
setTimeout(() => {
  Drupal.AjaxCommands.prototype.add_css(
    { dialog: { useAdminTheme: true } },
    { data: asResponse },
  );
});

if (container) {
  const root = createRoot(container);
  let routerRoot = appConfiguration.baseUrl;
  if (canvasSettings.base) {
    routerRoot = `${routerRoot}${canvasSettings.base}`;
  }
  const store = makeStore({ configuration: appConfiguration });

  // Make the store available to extensions.
  canvasSettings.store = store;

  root.render(
    <React.StrictMode>
      <Theme
        accentColor="blue"
        hasBackground={false}
        panelBackground="solid"
        appearance="light"
      >
        <ErrorBoundary variant="page">
          <Provider store={store}>
            <AppRoutes basePath={routerRoot} />
          </Provider>
        </ErrorBoundary>
      </Theme>
    </React.StrictMode>,
  );

  // Make the list of twig-to-JSX components available to Drupal behaviors.
  Drupal.JSXComponents = twigToJSXComponentMap;

  Drupal.canvasTransforms = transforms;

  // Make this application's hyperscriptify functionality available to
  // Drupal behaviors.
  Drupal.Hyperscriptify = (context: HTMLElement) => {
    return hyperscriptify(
      context,
      React.createElement,
      React.Fragment,
      twigToJSXComponentMap,
      { propsify },
    );
  };

  // Provide Drupal behaviors this method for hyperscriptifying content added
  // via the Drupal AJAX API.
  Drupal.HyperscriptifyAdditional = (
    Application: ReactHTMLElement<any>,
    context: HTMLElement,
    settings: { doNotReinvoke?: boolean },
  ): void => {
    const container = document.createElement('drupal-html-fragment');
    context.after(container);
    const root = createRoot(container);

    // Wrap the newly rendered content in the Redux provider so it has access
    // to the existing store.
    root.render(
      <Theme
        asChild
        accentColor="blue"
        hasBackground={false}
        panelBackground="solid"
        appearance="light"
      >
        {React.createElement<ProviderComponentProps>(
          Provider as FC,
          { store },
          Application as ReactHTMLElement<any>,
        )}
      </Theme>,
    );
    // If the render root already has content, we know it is rendered and can
    // return it.
    if (container.innerHTML.length > 0) {
      context.remove();
      attachBehaviorsAfterAjaxing(
        container,
        settings as { doNotReinvoke?: boolean },
      );
    } else {
      // If the render root does not have content yet, it isn't yet rendered.
      // Set an interval to check for content length, and return the element
      // once it is ready. If the process exceeds an unlikely 600ms, the
      // empty div will be returned regardless.
      let attempts = 0;
      const intervalDuration = 5;
      const intervalId = setInterval(() => {
        attempts += 1;
        if (container.innerHTML.length || attempts * intervalDuration > 600) {
          clearInterval(intervalId);
          context.remove();
          attachBehaviorsAfterAjaxing(
            container,
            settings as { doNotReinvoke?: boolean },
          );
        }
      }, intervalDuration);
    }
  };

  /**
   * A global function that can be called to notify the application that inputs
   * have been changed via an AJAX response.
   *
   * @param {HTMLElement[]} updatedInputs - The updated elements
   */
  Drupal.HyperscriptifyUpdateStore = (updatedInputs: Array<HTMLElement>) => {
    let formId: string | null = null;
    const updates = updatedInputs
      .map((el) => {
        // For each element, parse out its attributes. These are JSON sent by
        // the canvas_stark.theme with the semi_coupled theme engine.
        return JSON.parse(el.getAttribute('attributes') || '{}');
      })
      // Build a key-value pair of input names and values.
      .reduce((carry, attributes) => {
        if (
          // Collect inputs that have all the 'name', 'value', 'data-ajax' and
          // 'data-form-id' attributes set.
          'name' in attributes &&
          'value' in attributes &&
          'data-ajax' in attributes &&
          'data-form-id' in attributes
        ) {
          if (formId === null) {
            // This is the first element so let's make note of the form that
            // triggered the AJAX event.
            formId = attributes['data-form-id'];
          }
          return {
            // Push this element's name and value into the key value pair.
            ...carry,
            [attributes.name]: attributes.value,
          };
        }
        return carry;
      }, {});
    if (Object.values(updates).length > 0) {
      // At least one input name and value pair exists, so fire a custom event
      // so that any component in the tree can react to the ajax update.
      // @todo Consider interacting directly with the store https://www.drupal.org/i/3505039
      const event = new CustomEvent(AJAX_UPDATE_FORM_STATE_EVENT, {
        detail: { updates, formId },
      });
      document.dispatchEvent(event);
    }
  };
} else {
  throw new Error(
    "Root element with ID 'root' was not found in the document. Ensure there is a corresponding HTML element with the ID 'root' in your HTML file.",
  );
}
