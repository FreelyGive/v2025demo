import { expect } from '@playwright/test';

import { test } from './fixtures/DrupalSite';
import { Drupal } from './objects/Drupal';

const consoleErrors = [];

test.describe('Perform CRUD operations on components', () => {
  test.beforeAll(
    'Setup test site with Drupal Canvas',
    async ({ browser, drupalSite }) => {
      const page = await browser.newPage();
      const drupal: Drupal = new Drupal({ page, drupalSite });
      await drupal.installModules(['canvas', 'canvas_test_sdc']);
      await page.close();
    },
  );

  test.beforeEach(async ({ drupal, page }) => {
    await drupal.loginAsAdmin();
    page.on('console', async (msg) => {
      if (msg.type() === 'error') {
        const args = msg.args();
        // If the error was logged as an object, get its JSON representation.
        let error = null;
        if (args.length > 0) {
          try {
            error = await args[0].jsonValue();
            // eslint-disable-next-line @typescript-eslint/no-unused-vars
          } catch (e) {
            // If it's not JSON serializable, get the text
            error = await args[0].evaluate((arg) => arg.toString());
          }
          consoleErrors.push(error);
        }
      }
    });
  });

  test.afterEach(async () => {
    consoleErrors.forEach((consoleMessage) => {
      if (
        consoleMessage &&
        typeof consoleMessage === 'object' &&
        'status' in consoleMessage
      ) {
        expect(consoleMessage.status).not.toBe(500);
      }
    });
  });

  test('Layer and Components Panel', async ({ page, drupal, canvasEditor }) => {
    await drupal.createCanvasPage('Panels', '/panels');
    await page.goto('/panels');
    await canvasEditor.goToEditor();
    await expect(page.locator('[data-testid="canvas-side-menu"]'))
      .toMatchAriaSnapshot(`
        - button "Add":
          - img
        - button "Layers":
          - img
        - separator
        - button "Manage library":
          - img
      `);
    await page
      .locator('[data-testid="canvas-side-menu"]')
      .locator('[aria-label="Add"]')
      .click();
    await expect(
      page.locator(
        '[data-testid="canvas-primary-panel"] h4:has-text("Library")',
      ),
    ).toBeVisible();
    const collapsedButtons = page.locator(
      '[data-testid="canvas-primary-panel"] button[aria-expanded="false"]',
    );
    const buttonCount = await collapsedButtons.count();
    for (let i = 0; i < buttonCount; i++) {
      const collapsedButton = page
        .locator(
          '[data-testid="canvas-primary-panel"] button[aria-expanded="false"]',
        )
        .first();
      await collapsedButton.click();
    }
    await expect(
      page.locator('[data-testid="canvas-primary-panel"]'),
    ).toMatchAriaSnapshot({
      name: 'Perform-CRUD-operations-on-components-Layer-and-Components-Panel-1.aria.yml',
    });
  });

  test('Component hovers and clicks', async ({
    page,
    drupal,
    canvasEditor,
  }) => {
    await drupal.createCanvasPage('Hovers', '/hovers');
    await page.goto('/hovers');
    await canvasEditor.goToEditor();
    await canvasEditor.addComponent({ id: 'sdc.canvas_test_sdc.my-hero' });
    await canvasEditor.addComponent({ id: 'sdc.canvas_test_sdc.card' });
    await canvasEditor.openLayersPanel();

    // Confirm no component has a hover outline.
    await expect(
      page.locator('#canvasPreviewOverlay .componentOverlay[class*="hovered"]'),
    ).toHaveCount(0);
    // Hover over a component in the layers panel and verify it's outlined in the preview iframe.
    await page
      .locator(
        '[data-testid="canvas-primary-panel"] [data-canvas-type="component"]',
      )
      .locator(`text="Hero"`)
      .hover();
    const hero = page.locator(
      '.componentOverlay:has([data-canvas-component-id="sdc.canvas_test_sdc.my-hero"])',
    );
    const card = page.locator(
      '.componentOverlay:has([data-canvas-component-id="sdc.canvas_test_sdc.card"])',
    );
    await expect(hero).toHaveCSS('outline-width', '1px');
    await expect(hero).toHaveCSS('outline-style', 'solid');
    await expect(hero).not.toHaveCSS('outline-color', 'rgba(0, 0, 0, 0)');
    await expect(card).toHaveCSS('outline-style', 'dashed');
    await page
      .locator(
        '[data-testid="canvas-primary-panel"] [data-canvas-type="component"]',
      )
      .locator(`text="Card"`)
      .hover();
    await expect(card).toHaveCSS('outline-width', '1px');
    await expect(card).toHaveCSS('outline-style', 'solid');
    await expect(card).not.toHaveCSS('outline-color', 'rgba(0, 0, 0, 0)');
    await expect(hero).toHaveCSS('outline-color', 'rgba(0, 0, 0, 0)');

    // Hover over a component in the preview frame and verify it's outlined.
    await canvasEditor.hoverPreviewComponent('sdc.canvas_test_sdc.my-hero');
    await expect(hero).toHaveCSS('outline-width', '1px');
    await expect(hero).toHaveCSS('outline-style', 'solid');
    await expect(hero).not.toHaveCSS('outline-color', 'rgba(0, 0, 0, 0)');
    await expect(card).toHaveCSS('outline-style', 'dashed');
    await canvasEditor.hoverPreviewComponent('sdc.canvas_test_sdc.card');
    await expect(card).toHaveCSS('outline-width', '1px');
    await expect(card).toHaveCSS('outline-style', 'solid');
    await expect(card).not.toHaveCSS('outline-color', 'rgba(0, 0, 0, 0)');
    await expect(hero).toHaveCSS('outline-color', 'rgba(0, 0, 0, 0)');

    // Check edit props form opens when clicking the component in the preview.
    await canvasEditor.clickPreviewComponent('sdc.canvas_test_sdc.my-hero');
    await expect(
      page.locator(
        '[data-testid="canvas-contextual-panel"] [data-drupal-selector="component-instance-form"] .field--name-subheading input',
      ),
    ).toBeVisible();
    await canvasEditor.clickPreviewComponent('sdc.canvas_test_sdc.card');
    await expect(
      page.locator(
        '[data-testid="canvas-contextual-panel"] [data-drupal-selector="component-instance-form"] .field--name-content input',
      ),
    ).toBeVisible();
  });

  test('Can handle empty heading prop in hero component', async ({
    page,
    drupal,
    canvasEditor,
  }) => {
    await drupal.createCanvasPage('Hero', '/hero');
    await page.goto('/hero');
    await canvasEditor.goToEditor();
    await expect(page.locator('#block-stark-page-title h1')).toHaveCount(0);

    await canvasEditor.addComponent({ id: 'sdc.canvas_test_sdc.my-hero' });

    // Heading.
    await expect(
      (await canvasEditor.getActivePreviewFrame()).locator(
        '[data-component-id="canvas_test_sdc:my-hero"] h1',
      ),
    ).toContainText('There goes my hero');
    await canvasEditor.editComponentProp('heading', '');
    await expect(
      (await canvasEditor.getActivePreviewFrame()).locator(
        '[data-component-id="canvas_test_sdc:my-hero"] h1',
      ),
    ).not.toContainText('There goes my hero');

    // Refresh the page.
    await page.reload();
    await expect(
      await page.getByLabel('Heading', { exact: true }),
    ).not.toHaveValue('There goes my hero');
    await expect(
      (await canvasEditor.getActivePreviewFrame()).locator(
        '[data-component-id="canvas_test_sdc:my-hero"] h1',
      ),
    ).not.toContainText('There goes my hero');
    await expect(
      (await canvasEditor.getActivePreviewFrame()).locator(
        '[data-component-id="canvas_test_sdc:my-hero"] .my-hero__subheading',
      ),
    ).toContainText('Watch him as he goes!');

    // CTAs.
    await expect(
      (await canvasEditor.getActivePreviewFrame()).locator(
        '[data-component-id="canvas_test_sdc:my-hero"] a[href="https://example.com"]',
      ),
    ).toBeVisible();
    await canvasEditor.editComponentProp('cta1href', 'https://drupal.org');
    await expect(
      (await canvasEditor.getActivePreviewFrame()).locator(
        '[data-component-id="canvas_test_sdc:my-hero"] a.my-hero__cta--primary',
      ),
    ).toHaveAttribute('href', /drupal\.org/);
  });

  test('Can delete component with delete key', async ({
    page,
    drupal,
    canvasEditor,
  }) => {
    await drupal.createCanvasPage('Delete', '/delete');
    await page.goto('/delete');
    await canvasEditor.goToEditor();
    await canvasEditor.addComponent({ id: 'sdc.canvas_test_sdc.card' });
    await canvasEditor.deleteComponent('sdc.canvas_test_sdc.card');
  });

  test('Can add a component with slots', async ({
    page,
    drupal,
    canvasEditor,
  }) => {
    await drupal.createCanvasPage('Slots', '/slots');
    await page.goto('/slots');
    await canvasEditor.goToEditor();
    await canvasEditor.addComponent({ id: 'sdc.canvas_test_sdc.props-slots' });
    await canvasEditor.addComponent({ id: 'sdc.canvas_test_sdc.card' });
    await canvasEditor.openLayersPanel();
    await expect(page.locator('[data-testid="canvas-primary-panel"]'))
      .toMatchAriaSnapshot(`
      - heading "Layers" [level=4]
      - button:
        - img
      - img
      - text: Content
      - tree:
        - treeitem "Collapse component tree Canvas test SDC with props and slots Open contextual menu":
          - button "Collapse component tree" [expanded]:
            - img
          - img
          - button "Open contextual menu"
          - img
          - img
          - img
        - treeitem "Card Open contextual menu":
          - img
          - button "Open contextual menu"
    `);
    await canvasEditor.moveComponent('Card', 'the_footer');
    await expect(page.locator('[data-testid="canvas-primary-panel"]'))
      .toMatchAriaSnapshot(`
        - heading "Layers" [level=4]
        - button:
          - img
        - img
        - text: Content
        - tree:
          - treeitem "Collapse component tree Canvas test SDC with props and slots Open contextual menu":
            - button "Collapse component tree" [expanded]:
              - img
            - img
            - button "Open contextual menu"
            - img
            - button "Collapse slot" [expanded]:
              - img
            - img
            - tree:
              - treeitem "Card Open contextual menu":
                - img
                - button "Open contextual menu"
            - img
      `);
  });

  test('The iframe loads the SDC CSS', async ({
    drupal,
    page,
    canvasEditor,
  }) => {
    await drupal.setPreprocessing({ css: false });
    await drupal.createCanvasPage('Load SDC CSS', '/sdc-styles');
    await page.goto('/sdc-styles');
    await canvasEditor.goToEditor();
    await canvasEditor.addComponent({ name: 'Hero' });

    const head = await canvasEditor.getIframeHead();
    expect(head).not.toBeUndefined();
    const headHTML = await page.evaluate((el) => el.innerHTML, head);
    expect(headHTML).toContain('components/my-hero/my-hero.css');

    await canvasEditor.deleteComponent('sdc.canvas_test_sdc.my-hero');

    const head2 = await canvasEditor.getIframeHead();
    expect(head2).not.toBeUndefined();
    const head2HTML = await page.evaluate((el) => el.innerHTML, head2);
    expect(head2HTML).not.toContain('components/my-hero/my-hero.css');
  });

  test('Should be able to blur autocomplete without problems. See #3519734', async ({
    page,
    drupal,
    canvasEditor,
  }) => {
    await drupal.createCanvasPage('Blur Autocomplete', '/blur-autocomplete');
    await page.goto('/blur-autocomplete');
    await canvasEditor.goToEditor();

    await canvasEditor.addComponent({ name: 'Hero' });

    // Fill in Heading and Sub-heading fields
    const headType = 'Head is different';
    const subType = 'Sub also experienced change';
    await page.getByLabel('Heading', { exact: true }).fill(headType);
    await page.getByLabel('Sub-heading', { exact: true }).fill(subType);
    await expect(page.getByLabel('Heading', { exact: true })).toHaveValue(
      headType,
    );
    await expect(page.getByLabel('Sub-heading', { exact: true })).toHaveValue(
      subType,
    );
    await expect(
      (await canvasEditor.getActivePreviewFrame()).locator(
        '[data-component-id="canvas_test_sdc:my-hero"] h1',
      ),
    ).toContainText(headType);

    await expect(
      (await canvasEditor.getActivePreviewFrame()).locator(
        '[data-component-id="canvas_test_sdc:my-hero"] p',
      ),
    ).toContainText(subType);

    // Type in the autocomplete field, then blur by clicking another field
    await page.getByLabel('CTA 1 link', { exact: true }).fill('com');
    // Click another field to blur the autocomplete field, which prior to the fix in #3519734
    // would revert the preview to earlier values.
    await page.getByLabel('CTA 2 text', { exact: true }).click();

    // To make this a test that will fail without the fix present, but pass with
    // the fix in place, we need a fixed value wait here so that there's enough time
    // for the problem to appear in the preview. The correct contents will be asserted after this.
    // Wait 1000ms
    await page.waitForTimeout(1000);

    // Assert the preview still has the correct values
    await expect(
      (await canvasEditor.getActivePreviewFrame()).locator(
        '[data-component-id="canvas_test_sdc:my-hero"] h1',
      ),
    ).toContainText(headType);

    await expect(
      (await canvasEditor.getActivePreviewFrame()).locator(
        '[data-component-id="canvas_test_sdc:my-hero"] p',
      ),
    ).toContainText(subType);
    await expect(page.getByLabel('Heading', { exact: true })).toHaveValue(
      headType,
    );
    await expect(page.getByLabel('Sub-heading', { exact: true })).toHaveValue(
      subType,
    );
  });
});
