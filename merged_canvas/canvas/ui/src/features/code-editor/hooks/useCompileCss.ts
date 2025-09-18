/**
 * @file
 * Compiles CSS code for individual components or global Tailwind CSS.
 *
 * Utility functions are provided by our `tailwindcss-in-browser` package.
 * @see https://www.npmjs.com/package/tailwindcss-in-browser
 */

import { useCallback } from 'react';
import {
  compileCss as compileTailwindCss,
  extractClassNameCandidates,
  transformCss,
} from 'tailwindcss-in-browser';

const useCompileCss = (): {
  extractClassNameCandidates: (markup: string) => string[];
  transformCss: (css: string) => Promise<string>;
  buildTailwindCssFromClassNameCandidates: (
    classNameCandidates: string[],
    configurationCss: string,
  ) => Promise<{ css: string; error?: string }>;
} => ({
  /**
   * Extracts class names candidates from markup.
   *
   * They can be used to build CSS with Tailwind CSS.
   */
  extractClassNameCandidates,

  /**
   * The transformCss() function transforms modern CSS syntax into
   * browser-compatible code.
   *
   * It uses Lightning CSS under the hood with the same configuration
   * as Tailwind CSS does internally to transform CSS syntax.
   */
  transformCss,

  /**
   * Builds CSS with Tailwind CSS using class name candidates.
   *
   * @param classNameCandidates - Class name candidates.
   * @param configurationCss - Global CSS / Tailwind CSS configuration.
   *
   * @see https://www.npmjs.com/package/tailwindcss-in-browser#tailwind-css-4-configuration
   */
  buildTailwindCssFromClassNameCandidates: useCallback(
    async (classNameCandidates: string[], configurationCss: string) => {
      try {
        const compiledCss = await compileTailwindCss(
          classNameCandidates,
          configurationCss,
        );
        // The CSS syntax needs to be transformed.
        const transformedCss = await transformCss(compiledCss);
        return { css: transformedCss };
      } catch (error) {
        console.error('Failed to compile Tailwind CSS:', error);
        return {
          css: '/*! Compiling Tailwind CSS failed. */',
          error: `Failed to compile Tailwind CSS:', ${error}`,
        };
      }
    },
    [],
  ),
});

export default useCompileCss;
