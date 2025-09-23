import { getDrupal } from '@/utils/drupal-globals';

import type { PropsValues } from '@/types/Form';

const Drupal = getDrupal();

/**
 * Takes a response rendered by CanvasTemplateRenderer, identifies any attached
 * assets, then uses Drupal's AJAX API to add them to the page.
 *
 * This is designed to be used in `transformResponse` setting in endpoints
 * services by createApi such as the one in componentInstanceForm.ts.
 *
 * This is a factory and needs to be called in order to return the response
 * processor. The factory accepts an array of keys to be returned from the
 * response and default to 'html'. If only one key is specified, it will return
 * just the value of that key in the response. Depending on the key and the
 * response, this might be a string or an object. If multiple keys are specified
 * it will return an object with only those keys.
 *
 * To use CanvasTemplateRenderer for a route set the  _wrapper_format option to
 * 'canvas_template' in its route definition.
 *
 * @see core/misc/ajax.js
 * @see \Drupal\canvas\Render\MainContent\CanvasTemplateRenderer
 * @see ui/src/services/componentInstanceForm.ts
 */
// @see core/misc/ajax.js
const processResponseAssets = (keys: Array<string> = ['html']) => {
  return async (response: any, meta: any) => {
    const { css, js, settings } = response;

    if (css && css.length) {
      try {
        await Drupal.AjaxCommands.prototype['add_css'](
          { instanceIndex: Drupal.ajax.instances.length },
          {
            command: 'add_css',
            status: 'success',
            data: css,
          },
        );
      } catch (e) {
        console.error(e);
      }
    }
    if (js && Object.values(js).length) {
      // Although ajax_page_state does a good job preventing assets from
      // reloading, there are race conditions that can result in assets being
      // requested despite already being present, and this check prevents the
      // duplicate addition from occurring.
      const jsToAdd = Object.values(js as PropsValues[]).filter(
        (asset) => !document.querySelector(`script[src="${asset.src}"]`),
      );
      try {
        jsToAdd.length &&
          (await Drupal.AjaxCommands.prototype['add_js'](
            {
              instanceIndex: Drupal.ajax.instances.length + 1,
              selector: 'head',
            },
            {
              command: 'add_js',
              status: 'success',
              data: jsToAdd,
            },
          ));
      } catch (e) {
        console.error(e);
      }
    }
    if (settings && Object.keys(settings).length) {
      try {
        await Drupal.AjaxCommands.prototype['settings'](
          { instanceIndex: Drupal.ajax.instances.length + 2 },
          {
            command: 'settings',
            status: 'success',
            merge: true,
            settings: settings,
          },
        );
      } catch (e) {
        console.error(e);
      }
    }

    if (keys.length === 1) {
      return response[keys[0]];
    }
    return Object.entries(response).reduce(
      (carry, [key, value]) => ({
        ...carry,
        ...(keys.includes(key) ? { [key]: value } : {}),
      }),
      {},
    );
  };
};

export default processResponseAssets;
