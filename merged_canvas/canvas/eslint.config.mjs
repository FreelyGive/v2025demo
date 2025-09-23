import { fileURLToPath } from 'node:url';
import prettier from 'eslint-config-prettier';
import { defineConfig } from 'eslint/config';
import globals from 'globals';
import tseslint from 'typescript-eslint';
import { includeIgnoreFile } from '@eslint/compat';
import js from '@eslint/js';

const gitignorePath = fileURLToPath(new URL('.gitignore', import.meta.url));

export default defineConfig([
  includeIgnoreFile(gitignorePath, 'Imported .gitignore patterns'),
  {
    files: ['**/*.{js,mjs,cjs,ts}'],
    plugins: { js },
    extends: ['js/recommended'],
  },
  {
    files: ['**/*.{js,mjs,cjs,ts}'],
    languageOptions: { globals: globals.node },
  },
  tseslint.configs.recommended,
  prettier,
  {
    rules: {
      'no-empty-pattern': ['error', { allowObjectPatternsAsParameters: true }],
      '@typescript-eslint/consistent-type-imports': 'error',
    },
  },
]);
