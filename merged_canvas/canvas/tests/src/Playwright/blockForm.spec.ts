import { expect } from '@playwright/test';

import { test } from './fixtures/DrupalSite';
import { Drupal } from './objects/Drupal';
import { getModuleDir } from './utilities/DrupalFilesystem';

test.describe('Block form', () => {
  test.beforeAll(
    'Setup test site with Drupal Canvas',
    async ({ browser, drupalSite }) => {
      const page = await browser.newPage();
      const drupal: Drupal = new Drupal({ page, drupalSite });
      await drupal.installModules(['canvas']);
      const moduleDir = await getModuleDir();
      await drupal.applyRecipe(
        `${moduleDir}/canvas/tests/fixtures/recipes/block_form`,
      );
      await page.close();
    },
  );

  test('Block settings form with details element', async ({
    page,
    drupal,
    canvasEditor,
  }) => {
    await drupal.loginAsAdmin();
    await page.goto('/menu-block');
    await canvasEditor.goToEditor();
    await canvasEditor.openLayersPanel();
    await canvasEditor.openComponent('Administration');

    // @todo
    // In the Cypress test it now goes on to verify that the two dropdowns
    // "Initial visibility level" and "Number of levels to display are hidden
    // until the button with text "Menu levels" is clicked to reveal it. However,
    // in this test setup, the dropdowns are both visible and the button to hide
    // them doesn't function.
    // For now just verify that the elements are there.
    const inputsForm = page.locator(
      '[data-testid="canvas-contextual-panel"] [data-drupal-selector="component-instance-form"]',
    );
    await expect(inputsForm).toContainText('Menu levels');
    await expect(inputsForm.locator('select')).toHaveCount(2);
    await expect(inputsForm.locator('input[type="checkbox"]')).toBeVisible();
  });

  test('Block settings form values are stored and the preview is updated', async ({
    page,
    drupal,
    canvasEditor,
  }) => {
    await drupal.loginAsAdmin();
    await page.goto('/branding-block');
    await canvasEditor.goToEditor();

    const componentUuid = '78c73c1d-4988-4f9b-ad17-f7e337d40c29';
    await canvasEditor.openComponent('Site branding');

    // Remove and re-add the site logo.
    const siteLogoCheckbox = page
      .locator(
        `[data-testid="canvas-contextual-panel"] [data-drupal-selector="component-instance-form"]`,
      )
      .getByLabel('Site logo');
    await expect(siteLogoCheckbox).toBeChecked();
    await expect(
      (await canvasEditor.getActivePreviewFrame()).locator(
        `[data-canvas-uuid="${componentUuid}"] img`,
      ),
    ).toBeVisible();
    await siteLogoCheckbox.click();
    await expect(siteLogoCheckbox).not.toBeChecked();
    await expect(
      (await canvasEditor.getActivePreviewFrame()).locator(
        `[data-canvas-uuid="${componentUuid}"] img`,
      ),
    ).not.toBeVisible();
    await siteLogoCheckbox.click();
    await expect(siteLogoCheckbox).toBeChecked();
    await expect(
      (await canvasEditor.getActivePreviewFrame()).locator(
        `[data-canvas-uuid="${componentUuid}"] img`,
      ),
    ).toBeVisible();

    // Remove the site name.
    const siteNameCheckbox = page
      .locator(
        `[data-testid="canvas-contextual-panel"] [data-drupal-selector="component-instance-form"]`,
      )
      .getByLabel('Site name');
    await expect(siteNameCheckbox).toBeChecked();
    await expect(
      (await canvasEditor.getActivePreviewFrame()).locator(
        `[data-canvas-uuid="${componentUuid}"]`,
      ),
    ).toHaveText('Drupal');
    await siteNameCheckbox.click();
    await expect(siteNameCheckbox).not.toBeChecked();
    await expect(
      (await canvasEditor.getActivePreviewFrame()).locator(
        `[data-canvas-uuid="${componentUuid}"]`,
      ),
    ).not.toHaveText('Drupal');

    // Verify the component is saved and renders with the new options.
    await canvasEditor.publishAllChanges();
    await page.goto('/branding-block');
    await expect(page.locator(`#block-${componentUuid} img`)).toBeVisible();
    await expect(page.locator(`#block-${componentUuid} img`)).toHaveAttribute(
      'src',
      /logo\.svg/,
    );
    await expect(page.locator(`#block-${componentUuid}`)).not.toHaveText(
      'Drupal',
    );
  });
});
