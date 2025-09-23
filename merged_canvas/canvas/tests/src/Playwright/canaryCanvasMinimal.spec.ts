import { expect } from '@playwright/test';

import { test } from './fixtures/DrupalSite';
import { Drupal } from './objects/Drupal';

/**
 * Tests installing Drupal Canvas.
 */

test.describe('Canary Canvas Minimal', () => {
  test.beforeAll(
    'Setup minimal test site with Drupal Canvas',
    async ({ browser, drupalSite }) => {
      const page = await browser.newPage();
      const drupal: Drupal = new Drupal({ page, drupalSite });
      await drupal.installModules(['canvas', 'canvas_test_sdc']);
      await drupal.createCanvasPage('Homepage', '/homepage');
      await page.close();
    },
  );

  test('View homepage', async ({ page, drupal }) => {
    await drupal.loginAsAdmin();
    await page.goto('/homepage');
    /* eslint-disable no-useless-escape */
    await expect(page.locator('#block-stark-local-tasks')).toMatchAriaSnapshot(`
      - heading "Primary tabs" [level=2]
      - list:
        - listitem:
          - link "View":
            - /url: /homepage
        - listitem:
          - link "Edit":
            - /url: /\/canvas\/editor\/canvas_page\/\\d+/
        - listitem:
          - link "Revisions":
            - /url: /\/page\/\\d+\/revisions/
    `);
    /* eslint-enable no-useless-escape */
  });
});
