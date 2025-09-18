/* eslint-disable @typescript-eslint/no-unused-vars */
/* Add any additional functions/hooks to expose to in-browser JS components here.

  In order to have Astro bundle code with un-minified names, we use dynamic imports in this Stub component.
  Using dynamic imports results in Rollup (Astro uses Vite which uses Rollup) exporting the all hooks,
  jsx, jsxs, and Fragment functions, with names, from the corresponding module bundles, which
  can then be imported by the in-browser JS components. */

const { ...preact } = await import('preact');
const { ...preactCompat } = await import('preact/compat');
const { ...preactHooks } = await import('preact/hooks');
const { ...jsxRuntime } = await import('@/lib/jsx-runtime-default');
const { default: clsx } = await import('clsx');
const { ...tailwindMerge } = await import('tailwind-merge');
const { cva } = await import('class-variance-authority');
const FormattedText = await import('@/lib/FormattedText');
const Image = await import('@/lib/next-image-standalone');
const { cn } = await import('@/lib/utils');
const { JsonApiClient } = await import('@/lib/jsonapi-client');
const { DrupalJsonApiParams } = await import('@/lib/jsonapi-params');
const { getNodePath, sortMenu } = await import('@/lib/jsonapi-utils');
const {
  sortMenu: sortLinksetMenu,
  getPageData,
  getSiteData,
} = await import('@/lib/drupal-utils');
const useSwr = await import('@/lib/swr');
await import('@/lib/canvas-island.js');

export default function () {}
