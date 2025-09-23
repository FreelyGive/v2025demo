import { defineConfig } from 'tsup';

export default defineConfig({
  entry: ['src/index.ts'],
  format: ['esm'],
  clean: true,
  sourcemap: process.env.NODE_ENV === 'development',
  splitting: false,
  treeshake: true,
  minify: false,
  publicDir: 'assets',
  noExternal: ['tailwindcss-in-browser'],
  loader: {
    '.wasm': 'file',
  },
});
