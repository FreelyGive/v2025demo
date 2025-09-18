import { defineConfig } from 'astro/config';
import preact from '@astrojs/preact';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);

// https://astro.build/config
export default defineConfig({
  // Enable Preact to support Preact JSX components.
  integrations: [preact()],
  vite: {
    resolve: {
      alias: {
        '@': path.resolve(__dirname, 'src/'),
      },
    },
    build: {
      rollupOptions: {
        output: {
          // Filename pattern for the output files
          entryFileNames: '[name].js',
          chunkFileNames: (chunkInfo) => {
            // Make sure the output chunks for dependencies have useful file
            // names so we can easily distinguish between them.
            const matches = {
              'lib/astro-hydration/src/lib/jsx-runtime-default.js': 'jsx-runtime-default.js',
              clsx: 'clsx.js',
              'class-variance-authority': 'class-variance-authority.js',
              'tailwind-merge': 'tailwind-merge.js',
              'lib/astro-hydration/src/lib/FormattedText.tsx': 'FormattedText.js',
              'lib/astro-hydration/src/lib/next-image-standalone.tsx': 'next-image-standalone.js',
              'lib/astro-hydration/src/lib/utils.ts': 'util.js',
              'lib/astro-hydration/src/lib/jsonapi-client.ts': 'jsonapi-client.js',
              'lib/astro-hydration/src/lib/jsonapi-params.ts': 'jsonapi-params.js',
              'lib/astro-hydration/src/lib/jsonapi-utils.ts': 'jsonapi-utils.js',
              'lib/astro-hydration/src/lib/drupal-utils.ts': 'drupal-utils.js',
              'lib/astro-hydration/src/lib/swr.ts': 'swr.js'
            };
            return Object.entries(matches).reduce((carry, [key, value]) => {
              if (chunkInfo.facadeModuleId?.includes(`node_modules/${key}`)) {
                return value;
              }
              return carry;
            }, '[name].js');
          },
          assetFileNames: '[name][extname]',
        },
        // Mark React external so Astro's bundler doesn't bundle it. This way if
        // a module (e.g., lib/astro-hydration/src/lib/swr.ts) imports React,
        // our import maps will handle the module resolution, which will take
        // care of aliasing to `preact/compat`. This ensures that imports in the
        // code of code components as well as in bundled packages can be mapped
        // to the same module.
        // (An alternative would be to use the `compat` option of the
        // @astrojs/preact plugin, but it doesn't produce a bundle that can work
        // in both code components and bundled packages.)
        // @see src/features/code-editor/Preview.tsx
        // @see src/Plugin/Canvas/ComponentSource/JsComponent.php
        external: ['react', 'react-dom', 'react-dom/client', 'react/jsx-runtime']
      },
    },
  },
});
