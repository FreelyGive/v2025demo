/**
 * @file
 *
 * Compiles Preact components' JavaScript code using the SWC compiler.
 *
 * @see https://swc.rs/docs/usage/wasm
 */

import { useCallback, useEffect, useState } from 'react';
import initSwc, { transformSync } from '@swc/wasm-web';

import { getBaseUrl, getCanvasSettings } from '@/utils/drupal-globals';

import type { Options as SwcOptions } from '@swc/wasm-web';

const SWC_OPTIONS: SwcOptions = {
  jsc: {
    parser: {
      syntax: 'ecmascript',
      jsx: true,
    },
    target: 'es2015',
    transform: {
      react: {
        pragmaFrag: 'Fragment',
        throwIfNamespace: true,
        development: false,
        runtime: 'automatic',
      },
    },
  },
  module: {
    type: 'es6',
  },
} as const;

const CANVAS_MODULE_UI_PATH =
  `${getBaseUrl()}${getCanvasSettings().canvasModulePath}/ui` as const;

const getFallbackCompiledJs = (fallbackContentMessage: string) =>
  `// @error
import { jsx as _jsx } from "react/jsx-runtime";
  export default function() {
    return /*#__PURE__*/ _jsx("div", {
        dangerouslySetInnerHTML: {
            __html: '<!-- ${fallbackContentMessage} -->'
        }
`;

const useCompileJavaScript = (): {
  isJavaScriptCompilerReady: boolean;
  compileJavaScript: (
    code: string,
    fallbackContentMessage?: string,
  ) => { code: string; error?: string };
} => {
  const [isSwcInitialized, setIsSwcInitialized] = useState(false);

  useEffect(() => {
    const initializeSwc = async () => {
      try {
        // When served in production, the WASM asset URLs need to be relative
        // to the Drupal web root, so we pass that in to the initSwc() function.
        if (import.meta.env.MODE === 'production') {
          await initSwc(`${CANVAS_MODULE_UI_PATH}/dist/assets/wasm_bg.wasm`);
        } else {
          await initSwc();
        }
        setIsSwcInitialized(true);
      } catch (error) {
        console.error('Failed to initialize SWC:', error);
      }
    };
    initializeSwc();
  }, []);

  const compileJavaScript = useCallback(
    (
      code: string,
      fallbackContentMessage?: string,
    ): { code: string; error?: string } => {
      if (!isSwcInitialized) {
        return { code: '', error: 'JavaScript compiler is not initialized' };
      }
      try {
        const { code: compiledCode } = transformSync(code, SWC_OPTIONS);
        return { code: compiledCode };
      } catch (error) {
        console.error('Failed to compile JavaScript:', error);
        return {
          code: fallbackContentMessage
            ? getFallbackCompiledJs(fallbackContentMessage)
            : '// @error',
          error: `Failed to compile JavaScript: ${error}`,
        };
      }
    },
    [isSwcInitialized],
  );

  return { isJavaScriptCompilerReady: isSwcInitialized, compileJavaScript };
};

export default useCompileJavaScript;
