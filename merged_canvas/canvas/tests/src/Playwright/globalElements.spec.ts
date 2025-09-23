import { readFile } from 'fs/promises';
import { expect } from '@playwright/test';

import { test } from './fixtures/DrupalSite';
import { Drupal } from './objects/Drupal';
import { getModuleDir } from './utilities/DrupalFilesystem';

/**
 * Tests global elements.
 */

test.describe('Global elements', () => {
  test.beforeAll(
    'Setup test site with Drupal Canvas',
    async ({ browser, drupalSite }) => {
      const page = await browser.newPage();
      const drupal: Drupal = new Drupal({ page, drupalSite });
      await drupal.installModules(['canvas']);
      await page.close();
    },
  );

  test('Page title', async ({ page, canvasEditor, drupal }) => {
    await drupal.loginAsAdmin();
    await drupal.createCanvasPage('Page Title', '/page-title');
    await page.goto('/page-title');
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
  });

  test('Site branding', async ({ page, canvasEditor, drupal }) => {
    await drupal.loginAsAdmin();
    await drupal.createCanvasPage('Site Branding', '/site-branding');
    await page.goto('/site-branding');
    await canvasEditor.goToEditor();
    const moduleDir = await getModuleDir();
    const code = await readFile(
      `${moduleDir}/canvas/tests/fixtures/code_components/page-elements/SiteBranding.jsx`,
      'utf-8',
    );
    await canvasEditor.createCodeComponent('SiteBranding', code);
    const preview = canvasEditor.getCodePreviewFrame();
    // Site name defaults to 'Drupal'.
    // @see \Drupal\Core\Command\InstallCommand::configure
    await expect(preview.getByRole('link', { name: 'Drupal' })).toBeVisible();
  });

  test('Breadcrumbs', async ({ page, canvasEditor, drupal }) => {
    await drupal.loginAsAdmin();
    await drupal.createCanvasPage('Breadcrumbs', '/breadcrumbs');
    await page.goto('/breadcrumbs');
    await canvasEditor.goToEditor();
    const moduleDir = await getModuleDir();
    const code = await readFile(
      `${moduleDir}/canvas/tests/fixtures/code_components/page-elements/Breadcrumbs.jsx`,
      'utf-8',
    );
    await canvasEditor.createCodeComponent('Breadcrumbs', code);
    const preview = canvasEditor.getCodePreviewFrame();
    // @see \Drupal\canvas\Controller\CanvasController::__invoke
    await expect(preview.getByRole('link', { name: 'Home' })).toBeVisible();
    await expect(
      preview.getByRole('link', { name: 'My account' }),
    ).toBeVisible();
    expect(await preview.getByRole('listitem').all()).toHaveLength(2);
    await expect(
      preview.getByRole('heading', { name: 'Breadcrumb' }),
    ).toBeVisible();
  });
});
