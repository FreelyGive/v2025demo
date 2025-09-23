import { expect } from '@playwright/test';

import { test } from './fixtures/DrupalSite';
import { Drupal } from './objects/Drupal';

test.describe('Theming', () => {
  test.beforeAll(
    'Setup test site with Drupal Canvas',
    async ({ browser, drupalSite }) => {
      const page = await browser.newPage();
      const drupal: Drupal = new Drupal({ page, drupalSite });
      await drupal.installModules(['canvas']);
      await page.close();
    },
  );

  // See https://www.drupal.org/project/canvas/issues/3485842
  test("The active theme's base CSS should not be loaded when loading the Canvas UI.", async ({
    page,
    drupal,
    canvasEditor,
  }) => {
    await drupal.loginAsAdmin();
    await drupal.createCanvasPage('Olivero', '/olivero');
    await drupal.drush('theme:enable olivero');
    await drupal.drush('config:set system.theme default olivero');
    await drupal.setPreprocessing({ css: false });
    await page.goto('/olivero');
    await canvasEditor.goToEditor();
    // We expect the correct CSS files to be loaded for the Drupal Canvas UI.
    await expect(
      page.locator(
        'link[rel="stylesheet"][href^="/modules/contrib/canvas/ui/dist/assets/index.css"]',
      ),
    ).toHaveCount(1);
    // But we do not expect the base CSS of the active theme to be loaded.
    await expect(
      page.locator(
        'link[rel="stylesheet"][href^="/core/themes/olivero/css/base/base.css"]',
      ),
    ).toHaveCount(0);
  });
});
