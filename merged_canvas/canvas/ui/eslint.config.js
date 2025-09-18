import path from 'node:path';
import { fileURLToPath } from 'node:url';
import mochaPlugin from 'eslint-plugin-mocha';
import reactHooks from 'eslint-plugin-react-hooks';
import globals from 'globals';
import { FlatCompat } from '@eslint/eslintrc';
import js from '@eslint/js';
import typescriptEslint from '@typescript-eslint/eslint-plugin';
import tsParser from '@typescript-eslint/parser';

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);
const compat = new FlatCompat({
  baseDirectory: __dirname,
  recommendedConfig: js.configs.recommended,
  allConfig: js.configs.all,
});

export default [
  {
    ignores: [
      '**/dist',
      'src/local_packages',
      '**/vite.config.ts',
      '**/astro-hydration',
      '.storybook',
    ],
  },
  ...compat.extends(
    'eslint:recommended',
    'plugin:react/recommended',
    'plugin:react/jsx-runtime',
    'plugin:prettier/recommended',
  ),
  reactHooks.configs['recommended-latest'],
  {
    plugins: {
      '@typescript-eslint': typescriptEslint,
      mocha: mochaPlugin,
    },
    languageOptions: {
      globals: {
        ...globals.browser,
        ...globals.node,
        JSX: true,
        NodeJS: true,
        React: true,
      },

      parser: tsParser,
      ecmaVersion: 5,
      sourceType: 'commonjs',

      parserOptions: {
        project: true,
        tsconfigRootDir: './',
      },
    },

    settings: {
      react: {
        version: '18.2',
      },
    },

    rules: {
      ...mochaPlugin.configs.recommended.rules,
      '@typescript-eslint/consistent-type-imports': [
        2,
        {
          fixStyle: 'separate-type-imports',
        },
      ],

      '@typescript-eslint/no-restricted-imports': [
        2,
        {
          paths: [
            {
              name: 'react-redux',
              importNames: ['useSelector', 'useStore', 'useDispatch'],
              message:
                'Please use pre-typed versions from `src/app/hooks.ts` instead.',
            },
          ],
        },
      ],
      'mocha/no-mocha-arrows': 'off',
      'mocha/no-top-level-hooks': 'off',
      'mocha/max-top-level-suites': 'off',
      'mocha/no-exclusive-tests': 'error',
      'jsx-no-undef': 'off',
      'react/prop-types': 'off',
      'react/no-unescaped-entities': 'off',
      'react/display-name': 'off',
      'no-shadow': 'off',
      'no-unused-vars': 'off',
      '@typescript-eslint/no-unused-vars': [
        'error',
        { args: 'none', caughtErrors: 'none' },
      ],
      'no-redeclare': ['error', { builtinGlobals: false }],
    },
  },
  {
    files: ['**/*.{c,m,}{t,j}s', '**/*.{t,j}sx'],
  },
  {
    files: ['**/*{test,spec}.{t,j}s?(x)'],

    languageOptions: {
      globals: {
        ...globals.jest,
      },
    },
  },
  ...compat
    .extends('plugin:cypress/recommended', 'plugin:chai-friendly/recommended')
    .map((config) => ({
      ...config,
      files: ['tests/**/*.{js,jsx,ts,tsx}'],
    })),
];
