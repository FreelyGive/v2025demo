import AxeBuilder from '@axe-core/playwright';
import { expect } from '@playwright/test';

import { test } from './fixtures/DrupalSite';
import { Drupal } from './objects/Drupal';

/**
 * Perfunctory accessibility scan.
 */

test.describe('Basic accessibility', () => {
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

  test('Axe scan', async ({ page, drupal, canvasEditor }, testInfo) => {
    // These are the rules that these screens currently violate.
    // @todo not do that.
    const baseline = [
      'aria-required-children',
      'aria-valid-attr-value',
      'button-name',
      'color-contrast',
      'frame-focusable-content',
      'landmark-unique',
      'meta-viewport',
      'region',
      'scrollable-region-focusable',
    ];
    await drupal.loginAsAdmin();
    await page.goto('/homepage');
    await canvasEditor.goToEditor();
    const editorScan = await new AxeBuilder({ page })
      .disableRules(baseline)
      .analyze();
    await testInfo.attach('a11y-editor-scan', {
      body: JSON.stringify(editorScan, null, 2),
      contentType: 'application/json',
    });
    expect(editorScan.violations).toEqual([]);

    // Layers Panel.
    await canvasEditor.openLayersPanel();
    const layersScan = await new AxeBuilder({ page })
      .disableRules(baseline)
      .analyze();
    await testInfo.attach('a11y-layers-panel-scan', {
      body: JSON.stringify(layersScan, null, 2),
      contentType: 'application/json',
    });
    expect(layersScan.violations).toEqual([]);

    // Library Panel.
    await canvasEditor.openLibraryPanel();
    const libraryScan = await new AxeBuilder({ page })
      .disableRules(baseline)
      .analyze();
    await testInfo.attach('a11y-library-panel-scan', {
      body: JSON.stringify(libraryScan, null, 2),
      contentType: 'application/json',
    });
    expect(libraryScan.violations).toEqual([]);

    // Props Panel.
    canvasEditor.addComponent({ id: 'sdc.canvas_test_sdc.my-hero' });
    const propsScan = await new AxeBuilder({ page })
      .disableRules(baseline)
      .analyze();
    await testInfo.attach('a11y-props-panel-scan', {
      body: JSON.stringify(libraryScan, null, 2),
      contentType: 'application/json',
    });
    expect(propsScan.violations).toEqual([]);
  });
});
