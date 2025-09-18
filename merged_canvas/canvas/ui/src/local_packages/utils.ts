import propsify from '@/local_packages/hyperscriptify/propsify/standard/index.js';

import type { Attributes } from '@/types/DrupalAttribute';

/**
 * Turns an array of Drupal attributes into a React props object.
 * @param attributesFromDrupal (Drupal) HTML attributes.
 * @param attributesFromComponent Additional attributes to be merged with the Drupal attributes.
 * @param options Options for the function.
 * @param options.skipAttributes An array of attribute names to skip.
 * @returns
 */
export function a2p(
  attributesFromDrupal: Attributes,
  attributesFromComponent: Attributes = {},
  { skipAttributes = [] }: { skipAttributes?: string[] } = {},
) {
  const combinedAttributes: Attributes = {};
  const attributeNames = new Set(
    Object.keys(attributesFromDrupal).concat(
      Object.keys(attributesFromComponent),
    ),
  );
  attributeNames.forEach((name) => {
    if (skipAttributes.includes(name)) {
      return;
    }

    let value;
    if (
      Array.isArray(attributesFromDrupal[name]) &&
      Array.isArray(attributesFromComponent[name])
    ) {
      value = attributesFromDrupal[name].concat(attributesFromComponent[name]);
    } else {
      value = attributesFromComponent[name] ?? attributesFromDrupal[name];
    }

    if (Array.isArray(value)) {
      value = value.join(' ');
    }
    combinedAttributes[name] = value;
  });

  return propsify(combinedAttributes, {}, {});
}

// Replacement for Drupal's clean_class function.
export function cleanClass(input: string) {
  input = input.replace(/[\s_]+/g, '-').toLowerCase();
  input = input.replace(/[^\w-]/g, '');
  return input;
}
