# Import sorting

The project uses the [`@ianvs/prettier-plugin-sort-imports`](https://www.npmjs.com/package/@ianvs/prettier-plugin-sort-imports) Prettier plugin to automatically sort import declarations in JavaScript and TypeScript files.

The following is a quick reference to our configuration in `.prettierrc.json`, which defines the desired order under the `importOrder` key:

| Expression                | Target                                                                                       | Example                                                                |
| ------------------------- | -------------------------------------------------------------------------------------------- | ---------------------------------------------------------------------- |
| `<BUILTIN_MODULES>`       | Node.js built-in modules                                                                     | `import fs from 'fs'`                                                  |
| `^react$`                 | Imports from React                                                                           | `import { useEffect } from 'react'`                                    |
| `^(?![.@])(?!.*[.]css$)`  | External modules without scope (not starting with `.` or `@`, and excluding CSS)             | `import clsx from 'clsx'`                                              |
| `^@(?!/)(?!.*[.]css$)`    | External modules with scope (starting with `@`, not followed by `/`, and excluding CSS)      | `import { Flex } from '@radix-ui/themes'`                              |
| `""`                      | (empty line separator)                                                                       |                                                                        |
| `<THIRD_PARTY_MODULES>`   | All imports not targeted by another expression<sup>1</sup>                                   |                                                                        |
| `""`                      | (empty line separator)                                                                       |                                                                        |
| `^(?!.*[.]css$)[./]@/.*$` | Local imports using TypeScript path aliases (excluding CSS)                                  | `import { useAppSelector } from '@/app/hooks'`                         |
| `^(?!.*[.]css$)[./].*$`   | Local relative imports (excluding CSS)                                                       | `import Panel from './Panel'`                                          |
|                           | (empty line separator)                                                                       |                                                                        |
| `<TYPES>^(node:)`         | Node.js built-in type imports                                                                | `import type { Buffer } from 'node:buffer'`                            |
| `<TYPES>^(react)`         | React type imports                                                                           | `import type { ReactHTMLElement } from 'react'`                        |
| `<TYPES>`                 | Other type imports                                                                           | `import type { UnknownAction } from 'redux'`                           |
| `<TYPES>^@/`              | Local type imports using TypeScript path aliases                                             | `import type { UndoRedoType } from '@/features/ui/uiSlice';`           |
| `<TYPES>^[.]`             | Local relative type imports                                                                  | `import type { DrupalSettings } from '../../src/types/DrupalSettings'` |
|                           | (empty line separator)                                                                       |                                                                        |
| `^@/.*[.]css$`            | CSS imports using TypeScript path aliases                                                    | `import '@radix-ui/themes/styles.css'`                                 |
| `.css$`                   | Relative CSS imports and any other CSS import not targeted by another expression<sup>2</sup> | `import styles from './Dialog.module.css'`                             |

<sup>1</sup>: The naming for the special string <THIRD_PARTY_MODULES> is misleading in the plugin. We could also omit this as our logic covers everything, but doing so would cause it to be added at the first spot automatically, which would impact the order.

<sup>2</sup>: Most CSS imports from external packages will be [side-effect imports](https://developer.mozilla.org/en-US/docs/Web/JavaScript/Reference/Statements/import#import_a_module_for_its_side_effects_only), which will not be touched by the plugin, so we're keeping the ordering of imported CSS simple.