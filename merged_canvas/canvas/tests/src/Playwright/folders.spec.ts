import { expect } from '@playwright/test';

import { test } from './fixtures/DrupalSite';
import { Drupal } from './objects/Drupal';

/**
 * Tests folder management in Drupal Canvas.
 */
test.describe('Folder Management', () => {
  test.beforeAll(
    'Setup test site with Drupal Canvas',
    async ({ browser, drupalSite }) => {
      const page = await browser.newPage();
      const drupal: Drupal = new Drupal({ page, drupalSite });
      await drupal.drush('cr');

      await drupal.installModules(['canvas', 'canvas_test_folders']);

      // @todo remove the cache clear once https://www.drupal.org/project/drupal/issues/3534825
      // is fixed.
      await drupal.drush('cr');
      await page.close();
    },
  );

  test('Folder display and creation', async ({
    page,
    drupal,
    canvasEditor,
  }) => {
    await drupal.loginAsAdmin();
    await drupal.createCanvasPage('Test Page', '/test-page');
    await page.goto('/test-page');
    await canvasEditor.goToEditor();

    await page.click('[aria-label="Manage library"]');
    await expect(
      page.locator('[data-testid="add-new-folder-button"]'),
    ).toBeVisible();

    // We begin on the Components tab.
    await expect(
      page.locator(
        '[data-testid="canvas-manage-library-components-tab-select"][aria-selected="true"]',
      ),
    ).toBeVisible();
    await expect(
      page.locator(
        '[data-testid="canvas-manage-library-components-tab-content"]',
      ),
    ).toBeVisible();

    // Confirm the Components tab contents.
    await expect(
      page.locator(
        '[data-testid="canvas-manage-library-components-tab-content"]',
      ),
    ).toMatchAriaSnapshot({
      name: 'Folder-Management-Folder-display-and-creation-1.aria.yml',
    });
    await page
      .locator('[data-testid="canvas-manage-library-patterns-tab-select"]')
      .click();

    // Move to the Patterns tab.
    await expect(
      page.locator(
        '[data-testid="canvas-manage-library-patterns-tab-select"][aria-selected="true"]',
      ),
    ).toBeVisible();
    await expect(
      page.locator(
        '[data-testid="canvas-manage-library-patterns-tab-content"]',
      ),
    ).toBeVisible();

    // Confirm the Patterns tab contents.
    await expect(
      page.locator(
        '[data-testid="canvas-manage-library-patterns-tab-content"]',
      ),
    ).toMatchAriaSnapshot({
      name: 'Folder-Management-Folder-display-and-creation-2.aria.yml',
    });

    // Move to the Code tab.
    await page
      .locator('[data-testid="canvas-manage-library-code-tab-select"]')
      .click();
    await expect(
      page.locator(
        '[data-testid="canvas-manage-library-code-tab-select"][aria-selected="true"]',
      ),
    ).toBeVisible();
    await expect(
      page.locator('[data-testid="canvas-manage-library-code-tab-content"]'),
    ).toBeVisible();

    // Confirm the Code tab contents.
    await expect(
      page.locator('[data-testid="canvas-manage-library-code-tab-content"]'),
    ).toMatchAriaSnapshot({
      name: 'Folder-Management-Folder-display-and-creation-3.aria.yml',
    });

    // Helper to add folders and confirm they appear.
    const testAddFolder = async (
      foldersToAdd: string[],
      allExpectedFolders: string[],
    ) => {
      for (const folderName of foldersToAdd) {
        await expect(
          page.locator(
            '[data-testid="canvas-manage-library-add-folder-content"]',
          ),
        ).not.toBeAttached();
        await page.click('[data-testid="add-new-folder-button"]');
        page
          .locator('[data-testid="canvas-manage-library-add-folder-content"]')
          .waitFor({ state: 'visible' });
        await expect(
          page.locator('#add-new-folder-in-tab-form'),
        ).toBeAttached();
        await expect(
          page.locator(
            '[data-testid="canvas-manage-library-new-folder-name-submit"]',
          ),
        ).not.toBeEnabled();
        await page
          .locator('[data-testid="canvas-manage-library-new-folder-name"]')
          .fill(folderName);
        await expect(
          page.locator(
            '[data-testid="canvas-manage-library-new-folder-name-submit"]',
          ),
        ).toBeEnabled({ timeout: 5000 });
        await page.click(
          '[data-testid="canvas-manage-library-new-folder-name-submit"]',
        );
        await page
          .locator(`[data-canvas-folder-name="${folderName}"]`)
          .waitFor({ state: 'attached' });
      }

      const folderElements = await page
        .locator('[data-canvas-folder-name]')
        .all();
      const actualFolderNames = await Promise.all(
        folderElements.map(async (element) => {
          return await element.getAttribute('data-canvas-folder-name');
        }),
      );
      expect(actualFolderNames).toEqual(allExpectedFolders);
    };

    // Test adding a folder to the Code tab.
    await testAddFolder(
      ['Awesome New Folder', 'Is a Code Folder', 'Very Nice New Folder'],
      [
        'Active Users of Using',
        'Awesome New Folder',
        'Empty Code',
        'Is a Code Folder',
        'Proclaimers of With',
        'Very Nice New Folder',
      ],
    );

    // Test adding a folder to the Patterns tab.
    await page
      .locator('[data-testid="canvas-manage-library-patterns-tab-select"]')
      .click();
    await expect(
      page.locator(
        '[data-testid="canvas-manage-library-patterns-tab-select"][aria-selected="true"]',
      ),
    ).toBeVisible();
    await testAddFolder(
      ['Awesome New Folder', 'Is a Pattern Folder', 'Very Nice New Folder'],
      [
        'Animal Pats',
        'Awesome New Folder',
        'Color Patterns',
        'Empty Patterns',
        'Is a Pattern Folder',
        'Very Nice New Folder',
      ],
    );

    // Test adding a folder to the Components tab.

    await page
      .locator('[data-testid="canvas-manage-library-components-tab-select"]')
      .click();
    await expect(
      page.locator(
        '[data-testid="canvas-manage-library-components-tab-select"][aria-selected="true"]',
      ),
    ).toBeVisible();
    await testAddFolder(
      ['Awesome New Folder', 'Is a Component Folder', 'Very Nice New Folder'],
      [
        // @see \Drupal\canvas\Plugin\Canvas\ComponentSource\JsComponent::createConfigEntity()
        // @todo Remove next line in https://www.drupal.org/project/canvas/issues/3541364
        '@todo',
        'Atom/Media',
        'Atom/Tabs',
        'Atom/Text',
        'Awesome New Folder',
        'Container',
        'Container/Special',
        'core',
        'Empty Components',
        'Forms',
        'Is a Component Folder',
        'Lists (Views)',
        'Menus',
        'Other',
        'Status',
        'System',
        'User',
        'Very Nice New Folder',
      ],
    );
  });
});
