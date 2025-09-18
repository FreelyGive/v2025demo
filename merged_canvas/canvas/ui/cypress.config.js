import * as fs from 'fs';
import * as path from 'path';
import { fileURLToPath } from 'url';
import { defineConfig } from 'cypress';
import installLogsPrinter from 'cypress-terminal-report/src/installLogsPrinter.js';
import dotenv from 'dotenv';
import glob from 'fast-glob';
import minimist from 'minimist';
import webpackPreprocessor from '@cypress/webpack-preprocessor';

dotenv.config();

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);

const viewportHeight = 1080;
const viewportWidth = 1920;

const getCoreDir = () => {
  let count = 0;
  let path = 'core';
  while (!fs.existsSync(path) && count < 15) {
    count += 1;
    const stepsUp = `../`.repeat(count);
    path = `${stepsUp}core`;
  }
  if (fs.existsSync(path)) {
    return path;
  } else {
    throw new Error(`Path not found, stuck at ${path}`);
  }
};

export default defineConfig({
  chromeWebSecurity: false,
  defaultBrowser: process.env.DRUPAL_TEST_DEFAULT_BROWSER || 'chrome',
  watchForFileChanges: false,
  retries: { openMode: 0, runMode: 3 },
  env: {
    baseUrl: process.env.BASE_URL,
    dbUrl: process.env.DB_URL,
    defaultTheme: 'olivero',
    adminTheme: 'claro',
    coreDir: process.env.DRUPAL_ROOT_CORE || getCoreDir(),
    testWebserverUser: process.env.DRUPAL_TEST_WEBSERVER_USER,
    args: minimist(process.argv),
    setupFile: path.resolve('../tests/src/TestSite/CanvasTestSetup.php'),
    // Set this to true to enable our custom debugPause Cypress command,
    // otherwise this has no effect.
    debugPauses: false,
  },
  e2e: {
    experimentalRunAllSpecs: true,
    baseUrl: process.env.BASE_URL,
    setupNodeEvents(on, config) {
      installLogsPrinter(on);
      on('before:browser:launch', (browser, launchOptions) => {
        if (browser.family === 'chromium' && browser.isHeadless) {
          launchOptions.args.push(
            `--window-size=${viewportWidth},${viewportHeight}`,
          );
          launchOptions.args.push('--force-device-scale-factor=1');
        }

        if (browser.name === 'electron' && browser.isHeadless) {
          launchOptions.preferences.width = viewportWidth;
          launchOptions.preferences.height = viewportHeight;
        }

        if (browser.family === 'firefox' && browser.isHeadless) {
          launchOptions.args.push(`--width=${viewportWidth}`);
          launchOptions.args.push(`--height=${viewportHeight}`);
        }

        return launchOptions;
      });

      on('task', {
        log(message) {
          console.log(message);
          return null;
        },
        table(message) {
          console.table(message);

          return null;
        },
        countFiles(pattern) {
          return glob.sync(pattern).length;
        },
      });

      // This makes e2e tests aware of the project's node_modules directory
      // even though those tests are not in a child directory of the path
      // containing it.
      const options = webpackPreprocessor.defaultOptions;
      options.webpackOptions.resolve = {
        modules: [path.resolve(__dirname, 'node_modules'), 'node_modules'],
        extensions: ['.ts', '.js'],
        fullySpecified: false,
        alias: {
          '@': path.resolve(__dirname, 'src/'),
        },
      };
      options.webpackOptions.module.rules.push({
        test: /\.tsx?$/,
        use: 'ts-loader',
        exclude: /node_modules/,
      });

      options.webpackOptions.module.rules.push({
        test: /\.css$/i,
        use: ['style-loader', 'css-loader'],
      });

      on('file:preprocessor', webpackPreprocessor(options));
    },
    specPattern: ['tests/e2e/**/*.cy.{js,ts,jsx,tsx}'],
    supportFile: 'tests/support/e2e.js',
    downloadsFolder: 'tests/downloads',
    screenshotsFolder: 'tests/screenshots',
    viewportHeight,
    viewportWidth,
  },

  component: {
    specPattern: [
      'tests/component/**/*.cy.{js,ts,jsx,tsx}',
      'tests/unit/**/*.cy.{js,ts,jsx,tsx}',
    ],
    devServer: {
      framework: 'react',
      bundler: 'vite',
    },
    indexHtmlFile: 'tests/support/component-index.html',
    supportFile: 'tests/support/component.js',
    downloadsFolder: 'tests/downloads',
    screenshotsFolder: 'tests/screenshots',
    fixturesFolder: 'tests/fixtures',
    setupNodeEvents(on, config) {
      on('task', {
        log(message) {
          console.log(message);
          return null;
        },
      });
    },
  },
});
