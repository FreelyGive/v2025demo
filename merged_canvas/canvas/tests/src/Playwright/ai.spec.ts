import { readFileSync } from 'node:fs';
import { expect } from '@playwright/test';

import { test } from './fixtures/DrupalSite';
import { Drupal } from './objects/Drupal';

// @cspell:ignore canvasai
/**
 * This test suite will verify Canvas AI related features.
 */

test.describe('AI Features', () => {
  test.beforeAll(
    'Setup test site with Drupal Canvas',
    async ({ browser, drupalSite }) => {
      const page = await browser.newPage();
      const drupal = new Drupal({ page, drupalSite });
      await drupal.installModules(['canvas', 'canvas_ai', 'canvas_ai_test']);
      await drupal.createRole({ name: 'canvas_ai' });
      await drupal.addPermissions({
        role: 'canvas_ai',
        permissions: ['view the administration theme', 'edit canvas_page'],
      });
      await drupal.createUser({
        email: `canvasai@example.com`,
        username: 'canvasai',
        password: 'superstrongpassword1337',
        roles: ['canvas_ai'],
      });
      await page.close();
    },
  );

  test('Show AI panel only to users with Canvas AI permissions', async ({
    page,
    drupal,
    canvasEditor,
  }) => {
    await drupal.login({
      username: 'canvasai',
      password: 'superstrongpassword1337',
    });
    await drupal.createCanvasPage('Canvas AI User', '/canvasai_user');
    await page.goto('/canvasai_user');
    await canvasEditor.goToEditor();
    await expect(
      page.getByRole('button', { name: 'Open AI Panel' }),
    ).not.toBeAttached();

    await drupal.addPermissions({
      role: 'canvas_ai',
      permissions: ['use Drupal Canvas AI'],
    });
    await page.reload();
    await expect(
      page.getByRole('button', { name: 'Open AI Panel' }),
    ).toBeAttached();
  });

  test('Create component workflow', async ({
    page,
    drupal,
    canvasEditor,
    ai,
  }) => {
    await drupal.loginAsAdmin();
    await drupal.createCanvasPage('Canvas AI component', '/canvasai_component');
    await page.goto('/canvasai_component');
    await canvasEditor.goToEditor();
    await ai.openPanel();
    await ai.submitQuery('Create component');
    await expect(page).toHaveURL(
      /\/canvas\/code-editor\/component\/herobanner/,
    );
    await page.getByTestId('canvas-publish-review').click();
    await page
      .getByRole('checkbox', { name: 'Select all changes in Components' })
      .click();
    await page
      .getByRole('checkbox', { name: 'Select all changes in Assets' })
      .click();
    await page.getByRole('button', { name: 'Publish 2 selected' }).click();
    await page.getByRole('button', { name: 'Add to components' }).click();
    await page.getByRole('button', { name: 'Add' }).click();
    await expect(page).toHaveURL(/\/canvas\/editor\/canvas_page\/\d+$/);
    await canvasEditor.openLibraryPanel();
    await canvasEditor.addComponent({ name: 'HeroBanner' });
    await canvasEditor.clickPreviewComponent('js.herobanner');

    // Create a second component.
    await ai.submitQuery('Create second component');
    await expect(page).toHaveURL(
      /\/canvas\/code-editor\/component\/herobannersecond/,
    );
    const preview = canvasEditor.getCodePreviewFrame();
    const redElements = preview.locator('.bg-red-600');
    const blueElements = preview.locator('.bg-blue-600');
    await expect(redElements).toHaveCount(1);
    await expect(blueElements).toHaveCount(0);

    await ai.submitQuery('Edit component');
    const updatedPreview = canvasEditor.getCodePreviewFrame();
    const redElementsUpdated = updatedPreview.locator('.bg-red-600');
    const blueElementsUpdated = updatedPreview.locator('.bg-blue-600');
    await expect(redElementsUpdated).toHaveCount(0);
    await expect(blueElementsUpdated).toHaveCount(1);
  });

  test('Image upload', async ({ page, drupal, canvasEditor, ai }) => {
    await drupal.loginAsAdmin();
    await drupal.createCanvasPage('Canvas AI image upload', '/canvasai_image');
    await page.goto('/canvasai_image');
    await canvasEditor.goToEditor();
    await ai.openPanel();

    const buffer = readFileSync('tests/fixtures/images/gracie-big.jpg');
    const dataTransfer = await page.evaluateHandle(async (bufferAsHex) => {
      const dt = new DataTransfer();
      const file = new File([bufferAsHex], 'gracie-big.jpg', {
        type: 'image/jpeg',
      });
      dt.items.add(file);
      return dt;
    }, buffer.toString('binary'));
    await page.dispatchEvent(
      '[data-testid="canvas-ai-panel"] deep-chat #drag-and-drop',
      'drop',
      {
        dataTransfer,
      },
    );

    await expect(
      page.locator('#file-attachment-container img.image-attachment'),
    ).toBeVisible();
    await expect(
      page.locator('#file-attachment-container .remove-file-attachment-button'),
    ).toBeVisible();

    const submitButton = page
      .getByTestId('canvas-ai-panel')
      .locator('.input-button.inside-right');
    await expect(submitButton).not.toBeVisible();

    await page
      .getByRole('textbox', { name: 'Build me a' })
      .fill('What is a CMS?');
    await expect(submitButton).toBeVisible();

    await page
      .locator('#file-attachment-container .remove-file-attachment-button')
      .click();
    await expect(
      page.locator('#file-attachment-container img.image-attachment'),
    ).not.toBeVisible();
    await expect(
      page.locator('#file-attachment-container .remove-file-attachment-button'),
    ).not.toBeVisible();
  });

  test('Generate title', async ({ page, drupal, canvasEditor, ai }) => {
    await drupal.loginAsAdmin();
    await drupal.createCanvasPage('Canvas AI title', '/canvasai_title');
    await page.goto('/canvasai_title');
    await canvasEditor.goToEditor();
    await ai.openPanel();
    await expect(page.getByRole('textbox', { name: 'Title*' })).toHaveValue(
      'Canvas AI title',
    );
    await ai.submitQuery('Generate title');
    await expect(page.getByRole('textbox', { name: 'Title*' })).toHaveValue(
      'Welcome to Our Interactive Experience',
    );
  });

  test('Generate metadata', async ({ page, drupal, canvasEditor, ai }) => {
    await drupal.loginAsAdmin();
    await drupal.createCanvasPage('Canvas AI metadata', '/canvasai_metadata');
    await page.goto('/canvasai_metadata');
    await canvasEditor.goToEditor();
    await ai.openPanel();
    await expect(
      page.getByRole('textbox', { name: 'Meta description' }),
    ).toHaveValue('');
    await ai.submitQuery('Generate metadata');
    await expect(
      page.getByRole('textbox', { name: 'Meta description' }),
    ).toHaveValue(
      'Experience a journey through our interactive digital space, designed to engage and inspire visitors with immersive content and seamless navigation.',
    );
  });
});
