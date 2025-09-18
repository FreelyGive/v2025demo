import { test as base, mergeTests } from '@playwright/test';

import { Ai } from '../objects/Ai';
import { CanvasEditor } from '../objects/CanvasEditor';
import { Drupal } from '../objects/Drupal';
import { exec } from '../utilities/DrupalExec';
import { hasDrush } from '../utilities/DrupalFilesystem';

export type DrupalSite = {
  dbPrefix: string;
  userAgent: string;
  sitePath: string;
  url: string;
  hasDrush: boolean;
  teardown: Promise<string>;
};

type DrupalSiteInstall = {
  drupalSite: DrupalSite;
};

const drupalSite = base.extend<DrupalSiteInstall>({
  drupalSite: [
    async ({}, use) => {
      const stdout = await exec(
        `php core/scripts/test-site.php install --no-interaction --install-profile minimal --base-url ${process.env.DRUPAL_TEST_BASE_URL} --db-url ${process.env.DRUPAL_TEST_DB_URL} --json`,
      );
      const installData = JSON.parse(stdout.toString());
      const withDrush = await hasDrush();

      await use({
        dbPrefix: installData.db_prefix,
        userAgent: installData.user_agent,
        sitePath: installData.site_path,
        url: process.env.DRUPAL_TEST_BASE_URL,
        hasDrush: withDrush,
        teardown: async () => {
          if (
            process.env.DRUPAL_TEST_PLAYWRIGHT_SKIP_TEARDOWN &&
            process.env.DRUPAL_TEST_PLAYWRIGHT_SKIP_TEARDOWN === 'true'
          ) {
            return Promise.resolve('');
          }
          return await exec(
            `php core/scripts/test-site.php tear-down --no-interaction --db-url ${process.env.DRUPAL_TEST_DB_URL} ${installData.db_prefix}`,
          );
        },
      });
    },
    { scope: 'worker' },
  ],
});

type DrupalObj = {
  drupal: Drupal;
};

const drupal = base.extend<DrupalObj>({
  drupal: [
    async ({ page, drupalSite }, use) => {
      const drupal = new Drupal({ page, drupalSite });
      await use(drupal);
    },
    { auto: true },
  ],
});

type CanvasEditorObj = {
  canvasEditor: CanvasEditor;
};

const canvasEditor = base.extend<CanvasEditorObj>({
  canvasEditor: [
    async ({ page }, use) => {
      const canvasEditor = new CanvasEditor({ page });
      await use(canvasEditor);
    },
    { auto: true },
  ],
});

const ai = base.extend<{ ai: Ai }>({
  ai: [
    async ({ page }, use) => {
      const ai = new Ai({ page });
      await use(ai);
    },
    { auto: true },
  ],
});

export const beforeAllTests = base.extend<{ forEachWorker: void }>({
  forEachWorker: [
    async ({ drupalSite }, use) => {
      await use();
      // This code runs after all the tests in the worker process.
      drupalSite.teardown();
    },
    { scope: 'worker', auto: true },
  ], // automatically starts for every worker.
});

const beforeEachTest = base.extend<{ forEachTest: void }>({
  forEachTest: [
    async ({ drupal }, use) => {
      // This code runs before every test.
      await drupal.setTestCookie();
      await use();
    },
    { auto: true },
  ], // automatically starts for every test.
});

export const test = mergeTests(
  drupalSite,
  drupal,
  canvasEditor,
  ai,
  beforeAllTests,
  beforeEachTest,
);
