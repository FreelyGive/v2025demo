import { readFile } from 'fs/promises';
import { expect } from '@playwright/test';

import { test } from './fixtures/DrupalSite';
import { Drupal } from './objects/Drupal';
import { getModuleDir } from './utilities/DrupalFilesystem';

// @cspell:ignore PageTitle
/**
 * Tests data dependencies.
 */

test.describe('Data dependencies', () => {
  test.beforeAll(
    'Setup test site with Drupal Canvas',
    async ({ browser, drupalSite }) => {
      const page = await browser.newPage();
      const drupal: Drupal = new Drupal({ page, drupalSite });
      await drupal.installModules(['canvas']);
      await drupal.createCanvasPage('Homepage', '/homepage');
      await page.close();
    },
  );

  test('Are extracted and saved to the entity', async ({
    page,
    canvasEditor,
    drupal,
  }) => {
    await drupal.loginAsAdmin();
    await page.goto('/homepage');
    await canvasEditor.goToEditor();
    const moduleDir = await getModuleDir();
    const code = await readFile(
      `${moduleDir}/canvas/tests/fixtures/code_components/page-elements/PageTitle.jsx`,
      'utf-8',
    );
    await canvasEditor.createCodeComponent('PageTitle', code);
    const preview = canvasEditor.getCodePreviewFrame();
    // @see \Drupal\canvas\Controller\CanvasController::__invoke
    await expect(
      preview.getByRole('heading', {
        name: 'This is a page title for testing purposes',
      }),
    ).toBeVisible();
    await canvasEditor.publishAllChanges(['PageTitle', 'Global CSS']);
    await page.getByRole('button', { name: 'Add to components' }).click();
    await page.getByRole('dialog').getByRole('button', { name: 'Add' }).click();
    await canvasEditor.addComponent(
      { id: 'js.pagetitle' },
      { hasInputs: false },
    );
    await canvasEditor.publishAllChanges(['Homepage']);
    await page.goto('/homepage');
    await expect(
      page.locator('canvas-island').getByRole('heading', { name: 'Homepage' }),
    ).toBeVisible();
  });
});
