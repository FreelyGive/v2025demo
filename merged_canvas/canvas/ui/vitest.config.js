import path from 'path';
import { defineConfig } from 'vitest/config';
import react from '@vitejs/plugin-react';

export default defineConfig({
  plugins: [react()],
  test: {
    environment: 'jsdom',
    globals: true,
    setupFiles: ['./tests/vitest/support/vitest.setup.js'],
    mockReset: true,
    restoreMocks: true,
    deps: {
      optimizer: {
        web: {
          enabled: true,
          // react-mosaic-component has some code that uses require() to import
          // an ES module, which is a problem for Vitest.
          // The following optimizations are needed to prevent an error.
          include: ['react-mosaic-component'],
          exclude: ['react-dnd'],
        },
      },
    },
  },
  resolve: {
    alias: {
      '@': path.resolve(__dirname, './src'),
      '@assets': path.resolve(__dirname, './assets'),
      '@experimental': path.resolve(__dirname, '../experimental'),
      '@tests': path.resolve(__dirname, './tests'),
    },
  },
});
