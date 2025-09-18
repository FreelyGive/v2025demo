import { expect } from '@playwright/test';

import { test } from './fixtures/DrupalSite';
import { Drupal } from './objects/Drupal';

/**
 * Tests installing Drupal Canvas.
 */
test.describe('Canary Canvas', () => {
  test.beforeAll(
    'Setup test site with Drupal Canvas',
    async ({ browser, drupalSite }) => {
      const page = await browser.newPage();
      const drupal: Drupal = new Drupal({ page, drupalSite });
      await drupal.setupCanvasTestSite();
      await page.close();
    },
  );

  test('Setup test site with Drupal Canvas', async ({ page }) => {
    await page.goto('/first');
    await expect(
      page
        .locator('xpath=//*[@data-component-id="canvas_test_sdc:my-hero"]')
        .first(),
    ).toMatchAriaSnapshot(`
    - heading "meow!" [level=1]
    - paragraph: this is a hero
    - link "View":
      - /url: https://drupal.org
    - button "Click"
    `);
  });

  test('Rendered Pages', async ({ page }) => {
    await page.goto('/homepage');
    await expect(page.locator('css=.layout-content')).toMatchAriaSnapshot(`
      - heading "Homepage" [level=1]
      - heading "Welcome to the site!" [level=1]
      - paragraph:
        - text: Example value for
        - strong: the_body
        - text: slot in
        - strong: prop-slots
        - text: component.
      - text: Example value for <strong>the_footer</strong>.
      - link "Home":
        - /url: /
        - img "Home"
      - link "Drupal":
        - /url: /
    `);
    await page.goto('/test-page');
    await expect(page.locator('css=.layout-content')).toMatchAriaSnapshot(`
      - heading "Empty Page" [level=1]
    `);
    await page.goto('/the-one-with-a-block');
    await expect(page.locator('css=.layout-content')).toMatchAriaSnapshot(`
      - heading "Canvas With a block in the layout" [level=1]
      - article:
        - text: Submitted by admin on
        - time: Wed, 28 May 2025 - 07:01
        - img "A cat on top of a cat tree trying to reach a Christmas tree"
        - heading "meow!" [level=1]
        - paragraph: this is a hero
        - link "View":
          - /url: https://drupal.org
        - button "Click"
        - text: Component With props, Hello Kitty, 50 years old.
        - heading "hello, world!" [level=1]
        - paragraph: protect yourself from dangerous cats
        - link "Yes":
          - /url: https://drupal.org
        - button "No thanks"
        - text: A Media Image Field Image
        - img "A pub called The Princes Head surrounded by trees and two red London phone boxes"
    `);
    await page.goto('/i-am-an-empty-node');
    await expect(page.locator('css=.layout-content')).toMatchAriaSnapshot(`
      - heading "I am an empty node" [level=1]
      - article:
        - text: Submitted by admin on
        - time: Wed, 28 May 2025 - 07:00
        - text: A Media Image Field Image
        - img "A pub called The Princes Head surrounded by trees and two red London phone boxes"
    `);
  });

  test('Drupal Canvas Layer Panel', async ({ page, drupal, canvasEditor }) => {
    await drupal.loginAsAdmin();
    await page.goto('/first');
    await canvasEditor.goToEditor();
    const layerPanel = 'xpath=//*[@data-testid="canvas-primary-panel"]';
    const layerPanelElement = await page.locator(layerPanel);
    await expect(layerPanelElement).toContainText('Two Column');
    await expect(layerPanelElement).toContainText('Column One');
    await expect(layerPanelElement).toContainText('Column Two');
  });

  test('Component can be deleted', async ({ page, drupal, canvasEditor }) => {
    await drupal.loginAsAdmin();
    await page.goto('/first');
    await canvasEditor.goToEditor();
    // Delete the image that uses an adapted source.
    await canvasEditor.clickPreviewComponent('sdc.canvas_test_sdc.image');
    await page.keyboard.press('Delete');
    await page
      .locator(
        '#canvasPreviewOverlay [data-canvas-component-id="sdc.canvas_test_sdc.image"]',
      )
      .waitFor({ state: 'detached' });
    await expect(
      page.locator(
        '#canvasPreviewOverlay [data-canvas-component-id="sdc.canvas_test_sdc.image"]',
      ),
    ).toHaveCount(0);
  });
});
