import { transformSync } from '@swc/wasm';

import type { Options as SwcOptions } from '@swc/wasm';

// @see src/features/code-editor/hooks/useCompileJavaScript.ts
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

export function compileJS(source: string): string {
  const { code } = transformSync(source, SWC_OPTIONS);
  return code;
}
