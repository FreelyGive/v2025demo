import fs from 'fs';
import path from 'path';
import { defineConfig, loadEnv } from 'vite';
import svgr from 'vite-plugin-svgr';
import react from '@vitejs/plugin-react';

// https://vitejs.dev/config/

export default defineConfig(({ command, mode }) => {
  // https://vitejs.dev/config/#using-environment-variables-in-config
  const env = loadEnv(mode, process.cwd(), '');
  return {
    define: {
      __APP_ENV__: JSON.stringify(env.APP_ENV),
    },
    plugins: [
      react(),
      svgr(),
      {
        // Copy the code editor preview script to the dist directory when the
        // app is built.
        // @see ui/lib/code-editor-preview.js
        name: 'copy-code-editor-preview',
        writeBundle() {
          fs.copyFileSync(
            'lib/code-editor-preview.js',
            'dist/assets/code-editor-preview.js',
          );
        },
      },
    ],
    server: {
      // open: true,
      fs: {
        // Component tests using this vite config do not have this as a parent
        // directory. We disable strict so they can be served by Vite.
        strict: false,
      },
      origin: env.VITE_SERVER_ORIGIN || 'http://localhost:5173', // Origin for the generated asset URLs.
      headers: {
        // Allow the dev server to be accessed from any origin (unless it's
        // restricted by the VITE_SERVER_CORS_ALLOW_ORIGIN environment
        // variable), because development setups may vary.
        // These settings are insecure for production use.
        'Access-Control-Allow-Origin': env.VITE_SERVER_CORS_ALLOW_ORIGIN || '*',
        'Access-Control-Allow-Methods': 'GET',
        'Access-Control-Allow-Headers': 'Content-Type, Authorization',
        'Referrer-Policy': '*',
      },
    },
    build: {
      rollupOptions: {
        // external: ['react', 'react-dom', "redux", "@reduxjs/toolkit"],
        output: {
          entryFileNames: `assets/[name].js`,
          chunkFileNames: `assets/[name].js`,
          assetFileNames: `assets/[name].[ext]`,
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
    optimizeDeps: {
      // These libraries need to be excluded from Vite's dependency optimization until
      // https://github.com/vitejs/vite/issues/8427 is fixed.
      exclude: ['@swc/wasm-web', 'tailwindcss-in-browser'],
    },
  };
});
