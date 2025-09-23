// cspell:ignore networkidle
import nodePath from 'node:path';
import { expect } from '@playwright/test';

import type { Page } from '@playwright/test';

export class CanvasEditor {
  readonly page: Page;

  constructor({ page }: { page: Page }) {
    this.page = page;
  }

  async getSettings() {
    return await this.page.evaluate(() => {
      return window.drupalSettings;
    });
  }

  async getEditorPath() {
    const bodyClass = await this.page.locator('body').getAttribute('class');
    const hasCanvasPageClass = bodyClass?.includes('canvas-page');
    const drupalSettings = await this.getSettings();
    if (hasCanvasPageClass) {
      return `${drupalSettings.path.baseUrl}canvas/editor/canvas_${drupalSettings.path.currentPath}`;
    } else {
      return `${drupalSettings.path.baseUrl}canvas/editor/${drupalSettings.path.currentPath}`;
    }
  }

  async waitForEditorUi() {
    await this.page
      .getByTestId('canvas-contextual-panel')
      .locator('form')
      .first()
      .waitFor({ state: 'attached', timeout: 30_000 });
    const forms = this.page
      .getByTestId('canvas-contextual-panel')
      .locator('form');
    const count = await forms.count();
    await Promise.race(
      Array.from({ length: count }, (_, i) =>
        forms.nth(i).waitFor({ state: 'visible', timeout: 30_000 }),
      ),
    );

    await expect(this.page.getByTestId('canvas-primary-panel')).toContainText(
      /Layers|Library/,
      {
        timeout: 15000,
      },
    );

    await this.page.evaluate(() => {
      return new Promise<void>((resolve) => {
        const framesStable = () => {
          // When a change happens data-test-canvas-content-initialized is set to
          // false on the active iframe.
          // Then, when the inactive iframe is ready to be swapped to active,
          // data-test-canvas-content-initialized is set true, and the value of
          // data-canvas-swap-active is swapped.
          const frameAInitialized = document.querySelector(
            '[data-canvas-iframe="A"][data-test-canvas-content-initialized="true"]',
          );
          const frameBInitialized = document.querySelector(
            '[data-canvas-iframe="B"][data-test-canvas-content-initialized="true"]',
          );
          const frameAActive = document.querySelector(
            '[data-canvas-iframe="A"][data-canvas-swap-active="true"]',
          );
          const frameBActive = document.querySelector(
            '[data-canvas-iframe="B"][data-canvas-swap-active="true"]',
          );
          const xor = (a, b) => {
            return a ? !b : b;
          };
          // Only one frame is uninitialized.
          // and
          // only one frame is initialized and stable.
          return (
            xor(frameAInitialized, frameBInitialized) &&
            ((frameAInitialized && frameAActive) ||
              (frameBInitialized && frameBActive))
          );
        };
        let mutated = false;

        const targetNode = document.querySelector(
          '[data-testid="canvas-editor-frame-scaling"]',
        );
        const observer = new MutationObserver(() => {
          // Reset the mutated state as something has changed.
          mutated = true;
        });
        observer.observe(targetNode, {
          attributes: true,
          childList: true,
          subtree: true,
        });

        const intervalId = setInterval(() => {
          if (!mutated && framesStable()) {
            clearInterval(intervalId); // Stop the interval
            observer.disconnect();
            resolve();
          } else {
            mutated = false;
          }
        }, 1000);
      });
    });

    await expect(
      this.page.locator(
        '[data-testid="canvas-editor-frame-scaling"] iframe[data-test-canvas-content-initialized="true"]',
      ),
    ).toHaveCSS('opacity', '1');
    await expect(
      this.page.locator(
        '[data-testid="canvas-editor-frame-scaling"] iframe[data-canvas-swap-active="false"]',
      ),
    ).toHaveCSS('opacity', '0');
  }

  async goToEditor() {
    const path = await this.getEditorPath();
    const response = await this.page.goto(path);
    if (!response || response.status() !== 200) {
      throw new Error(
        "Editor didn't load. Before calling goToEditor, first call `await page.goto('/first');` using the page's alias and ensure its a page that can be edited by Canvas.",
      );
    }

    await this.waitForEditorUi();
  }

  async getActivePreviewFrame() {
    await this.waitForEditorUi();
    return this.page
      .locator(
        '[data-testid="canvas-editor-frame-scaling"] iframe[data-canvas-swap-active="true"]',
      )
      .contentFrame();
  }

  /**
   * Opens the Library Panel by clicking the "Add" button in the side menu.
   * Waits for the library loading indicator to disappear.
   *
   */
  async openLibraryPanel() {
    // Click the layers panel first so it doesn't matter if the layers panel
    // is already open or not.
    await this.page
      .getByTestId('canvas-side-menu')
      .getByLabel('Layers')
      .click();
    await this.page.getByTestId('canvas-side-menu').getByLabel('Add').click();
    await expect(
      this.page.getByTestId('canvas-components-library-loading'),
    ).not.toBeVisible();
    await expect(
      this.page.locator(
        '[data-testid="canvas-primary-panel"] h4:has-text("Library")',
      ),
    ).toBeVisible();
    const components = this.page.locator(
      '[data-testid="canvas-primary-panel"] [data-canvas-type="component"]',
    );
    const count = await components.count();
    for (let i = 0; i < count; i++) {
      await components.nth(i).waitFor({ state: 'visible' });
    }

    await this.page
      .getByTestId('canvas-manage-library-components-tab-select')
      .click();
  }

  async openLayersPanel() {
    // Click the library panel first so it doesn't matter if the layers panel
    // is already open or not.
    await this.page.getByTestId('canvas-side-menu').getByLabel('Add').click();
    await this.page
      .getByTestId('canvas-side-menu')
      .getByLabel('Layers')
      .click();
    await expect(
      this.page.locator(
        '[data-testid="canvas-primary-panel"] h4:has-text("Layers")',
      ),
    ).toBeVisible();
  }

  async openComponent(title: string) {
    await this.page
      .locator(
        '[data-testid="canvas-primary-panel"] [data-canvas-type="component"]',
      )
      .locator(`text="${title}"`)
      .click();
  }

  /**
   * Adds a component to the preview by clicking it in .
   *
   * @param identifier An object with either an 'id' (sdc.canvas_test_sdc.card) or 'name' (Hero) property to identify the component.
   * @param options Optional parameters:
   * - hasInputs: If true, waits for the component inputs form to be visible. (default: true)
   *
   * Example usage:
   *   await canvasEditor.addComponent({ name: 'Card' }, { waitForNetworkResponses: true });
   */
  async addComponent(
    identifier: { id?: string; name?: string },
    options: {
      hasInputs?: boolean;
    } = {},
  ) {
    const { id, name } = identifier;
    const { hasInputs = true } = options;

    await this.openLibraryPanel();

    let selector, previewSelector;

    if (id) {
      selector = `[data-canvas-type="component"][data-canvas-component-id="${id}"]`;
      previewSelector = `#canvasPreviewOverlay [data-canvas-component-id="${id}"]`;
    } else if (name) {
      selector = `[data-canvas-type="component"][data-canvas-name="${name}"]`;
      previewSelector = `#canvasPreviewOverlay [aria-label="${name}"]`;
    } else {
      throw new Error("Either 'id' or 'name' must be provided.");
    }

    const componentLocator = this.page
      .getByTestId('canvas-primary-panel')
      .locator(selector);

    const existingInstances = this.page.locator(previewSelector);
    const initialCount = await existingInstances.count();
    await componentLocator.click();

    expect(await this.page.locator(previewSelector).count()).toBe(
      initialCount + 1,
    );

    const updatedInstances = this.page.locator(previewSelector);
    const updatedCount = await updatedInstances.count();
    for (let i = 0; i < updatedCount; i++) {
      await this.page.waitForFunction(
        ([selector, index]) => {
          const element = document.querySelectorAll(selector)[index];
          if (!element) return false;
          const box = element.getBoundingClientRect();
          return box.width > 0 && box.height > 0;
        },
        [previewSelector, i],
      );
    }

    if (hasInputs) {
      const formElement = this.page.locator(
        'form[data-form-id="component_instance_form"]',
      );
      await formElement.waitFor({ state: 'visible' });
    }
  }

  async editComponentProp(
    propName: string,
    propValue: string,
    propType = 'text',
  ) {
    const inputLocator = `[data-testid="canvas-contextual-panel"] [data-drupal-selector="component-instance-form"] .field--name-${propName.toLowerCase()} input`;
    const labelLocator = `[data-testid="canvas-contextual-panel"] [data-drupal-selector="component-instance-form"] .field--name-${propName.toLowerCase()} label`;

    switch (propType) {
      case 'file':
        // For a moment there's 2 file choosers whilst the elements are processed.
        await expect(
          this.page.locator(`${inputLocator}[type="file"]`),
        ).toHaveCount(1);
        await expect(
          this.page.locator(`${inputLocator}[type="file"]`),
        ).toBeVisible();
        await this.page
          .locator(`${inputLocator}[type="file"]`)
          .setInputFiles(nodePath.join(__dirname, propValue));
        await expect(
          this.page.locator(`${inputLocator}[type="file"]`),
        ).not.toBeVisible();
        break;
      default:
        await this.page.locator(inputLocator).fill(propValue);
        // Click the label as autocomplete/link fields will not update until the
        // element has lost focus.
        await this.page.locator(labelLocator).click();
        break;
    }
  }

  async moveComponent(componentName: string, target: string) {
    const component = this.page
      .locator(
        '[data-testid="canvas-primary-panel"] [data-canvas-type="component"]',
      )
      .getByText(componentName);
    const dropzoneLocator = `[data-testid="canvas-primary-panel"] [data-canvas-uuid*="${target}"] [class*="DropZone"]`;
    const dropzone = this.page.locator(dropzoneLocator);
    // See https://playwright.dev/docs/input#dragging-manually on why this needs
    // to be done like this.
    await component.hover({ force: true });
    await this.page.mouse.down();

    // Force a layout recalculation in headless mode, this is only needed for
    // webkit.
    await this.page.evaluate(() => {
      // eslint-disable-next-line @typescript-eslint/no-unused-expressions
      document.body.offsetHeight; // Forces reflow
    });
    await dropzone.hover({ force: true });
    await this.page.evaluate((locator) => {
      // Force another reflow to ensure drop zone state is updated.
      // Again, only needed for webkit.
      const dropzone = document.querySelector(locator);
      if (dropzone) {
        // eslint-disable-next-line @typescript-eslint/no-unused-expressions
        dropzone.offsetHeight; // Forces reflow on the drop zone
      }
    }, dropzoneLocator);
    await dropzone.hover({ force: true });
    await this.page.mouse.up();
    await expect(
      this.page.locator(
        `[data-testid="canvas-primary-panel"] [data-canvas-type="slot"][data-canvas-uuid*="${target}"]`,
      ),
    ).toContainText(componentName);
  }

  async deleteComponent(componentId: string) {
    const component = this.page.locator(
      `.componentOverlay:has([data-canvas-component-id="${componentId}"])`,
    );
    await expect(component).toHaveCount(1);
    // get the component's data-canvas-uuid attribute value from the child .canvas--sortable-item element
    const componentUuid = await component
      .locator('> .canvas--sortable-item')
      .getAttribute('data-canvas-uuid');

    if (!componentUuid) {
      const html = await component.evaluate((el) => el.outerHTML);
      throw new Error(`data-canvas-uuid is null. Element HTML: ${html}`);
    }

    await expect(
      (await this.getActivePreviewFrame()).locator(
        `[data-canvas-uuid="${componentUuid}"]`,
      ),
    ).toHaveCount(1);
    await this.clickPreviewComponent(componentId);
    await this.page.keyboard.press('Delete');
    // Should be gone from the overlay
    await expect(
      this.page.locator(`[data-canvas-uuid="${componentUuid}"]`),
    ).toHaveCount(0);
    // should be gone from inside the preview frame
    await expect(
      (await this.getActivePreviewFrame()).locator(
        `[data-canvas-uuid="${componentUuid}"]`,
      ),
    ).toHaveCount(0);
  }

  async hoverPreviewComponent(componentId: string) {
    const component = this.page.locator(
      `#canvasPreviewOverlay [data-canvas-component-id="${componentId}"]`,
    );
    // Directly trigger mouse events via JavaScript because of webkit.
    await component.evaluate((el) => {
      // First ensure element is visible in its container
      el.scrollIntoView({
        behavior: 'instant',
        block: 'center',
        inline: 'center',
      });

      // Create and dispatch mouse events
      const mouseenterEvent = new MouseEvent('mouseenter', {
        view: window,
        bubbles: true,
        cancelable: true,
      });

      const mouseoverEvent = new MouseEvent('mouseover', {
        view: window,
        bubbles: true,
        cancelable: true,
      });

      el.dispatchEvent(mouseenterEvent);
      el.dispatchEvent(mouseoverEvent);
    });
  }

  async clickPreviewComponent(componentId: string) {
    const component = this.page.locator(
      `#canvasPreviewOverlay [data-canvas-component-id="${componentId}"]`,
    );

    // Directly trigger click events via JavaScript because of webkit
    await component.evaluate((el) => {
      // First ensure element is visible in its container
      el.scrollIntoView({
        behavior: 'instant',
        block: 'center',
        inline: 'center',
      });

      // Create and dispatch the full click sequence
      const mousedownEvent = new MouseEvent('mousedown', {
        view: window,
        bubbles: true,
        cancelable: true,
        button: 0, // Left mouse button
        buttons: 1,
      });

      const mouseupEvent = new MouseEvent('mouseup', {
        view: window,
        bubbles: true,
        cancelable: true,
        button: 0,
        buttons: 0,
      });

      const clickEvent = new MouseEvent('click', {
        view: window,
        bubbles: true,
        cancelable: true,
        button: 0,
        buttons: 0,
      });

      // Dispatch the full sequence: mousedown → mouseup → click
      el.dispatchEvent(mousedownEvent);
      el.dispatchEvent(mouseupEvent);
      el.dispatchEvent(clickEvent);
    });
  }

  async createCodeComponent(componentName: string, code: string) {
    await this.openLibraryPanel();
    await this.page
      .locator('[data-testid="canvas-primary-panel"]')
      .getByText('Create code component')
      .click();
    await this.page.fill('#componentName', componentName);
    await this.page
      .locator('.rt-BaseDialogContent button')
      .getByText('Add')
      .click();
    await expect(
      this.page.locator('[data-testid="canvas-mosaic-container"]'),
    ).toBeVisible();
    const codeEditor = this.page.locator(
      '.canvas-mosaic-window-editor div[role="textbox"]',
    );
    await codeEditor.waitFor({ state: 'visible' });
    await expect(codeEditor).toContainText(
      'for documentation on how to build a code component',
    );
    await codeEditor.selectText();
    await this.page.keyboard.press('Delete');
    await codeEditor.fill(code);
  }

  async addCodeComponentProp(
    propName: string,
    propType: string,
    example: { label: string; value: string; type: string }[] = [],
    required: boolean = false,
  ) {
    await this.page
      .locator('.canvas-mosaic-window-component-data button:has-text("Props")')
      .click();
    await this.page
      .locator('.canvas-mosaic-window-component-data')
      .getByRole('button')
      .getByText('Add')
      .click();
    const propForm = this.page
      .locator('.canvas-mosaic-window-component-data [data-testid^="prop-"]')
      .last();
    await propForm.locator('[id^="prop-name-"]').fill(propName);
    await propForm.locator('[id^="prop-type-"]').click();
    await this.page
      .locator('body > div > div.rt-SelectContent')
      .getByRole('option', { name: propType, exact: true })
      .click();
    await expect(propForm.locator('[id^="prop-type-"]')).toHaveText(propType);
    const requiredChecked = await propForm
      .locator('[id^="prop-required-"]')
      .getAttribute('data-state');
    if (required && requiredChecked === 'unchecked') {
      await propForm.locator('[id^="prop-required-"]').click();
    }
    if (required) {
      expect(
        await propForm
          .locator('[id^="prop-required-"]')
          .getAttribute('data-state'),
      ).toEqual('checked');
    } else {
      expect(
        await propForm
          .locator('[id^="prop-required-"]')
          .getAttribute('data-state'),
      ).toEqual('unchecked');
    }
    for (const { label, value, type } of example) {
      switch (type) {
        case 'text':
          await propForm
            .locator(
              `label[for^="prop-example-"]:has-text("${label}") + div input[id^="prop-example-"]`,
            )
            .fill(value);
          break;
        case 'select':
          await propForm
            .locator(
              `label[for^="prop-example-"]:has-text("${label}") + button`,
            )
            .click();
          await this.page
            .locator('body > div > div.rt-SelectContent')
            .getByRole('option', { name: value, exact: true })
            .click();
          await expect(
            propForm.locator(
              `label[for^="prop-example-"]:has-text("${label}") + button`,
            ),
          ).toHaveText(value);
          break;
        default:
          throw new Error(`Unknown form element type ${type}`);
      }
    }

    await this.page.waitForResponse(
      (response) =>
        response
          .url()
          .includes('/canvas/api/v0/config/auto-save/js_component/') &&
        response.request().method() === 'PATCH',
    );

    await expect(this.getCodePreviewFrame()).toBeVisible();
  }

  async saveCodeComponent(componentName: string) {
    await this.page.getByRole('button', { name: 'Add to components' }).click();
    await this.page.getByRole('button', { name: 'Add' }).click();
    await this.waitForEditorUi();
    await expect(
      this.page.locator(
        `[data-canvas-type="component"][data-canvas-component-id="${componentName}"]`,
      ),
    ).toBeVisible();
  }

  getCodePreviewFrame() {
    return this.page
      .locator('.canvas-mosaic-window-preview iframe')
      .contentFrame()
      .locator('#canvas-code-editor-preview-root');
  }

  async preview() {
    await this.page
      .locator('[data-testid="canvas-topbar"]')
      .getByRole('button', { name: 'Preview' })
      .click();
    await this.page
      .locator('iframe[class^="_PagePreviewIframe"]')
      .contentFrame()
      .locator('.layout-container')
      .waitFor({ state: 'visible' });
    await this.page.waitForLoadState('networkidle');
    await this.page
      .locator('iframe[class^="_PagePreviewIframe"]')
      .contentFrame()
      .locator('main')
      .waitFor({ state: 'visible' });
  }

  async exitPreview() {
    await this.page
      .locator('[data-testid="canvas-topbar"]')
      .getByRole('button', { name: 'Exit Preview' })
      .click();
    await this.waitForEditorUi();
  }

  async publishAllChanges(expectedTitles: string[] = []) {
    await this.page
      .getByRole('button', { name: /Review \d+ changes?/ })
      .click();
    await expect(async () => {
      await this.page.getByLabel('Select all changes', { exact: true }).click();
      if (expectedTitles.length > 0) {
        await Promise.all(
          expectedTitles.map(async (title: string) =>
            expect(
              await this.page.getByLabel(`Select change ${title}`),
            ).toBeChecked(),
          ),
        );
      }
      await this.page
        .getByRole('button', { name: /Publish \d+ selected?/ })
        .click();
      await expect(this.page.getByText('All changes published!')).toBeVisible();
    }).toPass({
      // Probe, wait 1s, probe, wait 2s, probe, wait 10s, probe, wait 10s, probe
      intervals: [1_000, 2_000, 10_000],
      // Fail after a minute of trying.
      timeout: 60_000,
    });
  }

  /**
   * Clears the auto-save for a given entity type and ID.
   *
   * Requires that the module canvas_e2e_support is enabled.
   *
   * @param type The entity type (e.g., 'node', 'canvas_page').
   * @param id The entity ID (default '1').
   */
  async clearAutoSave(type: string = 'node', id: string = '1') {
    const url = `/canvas-test/clear-auto-save/${type}/${id}`;
    const response = await this.page.request.get(url);
    if (response.status() !== 200) {
      throw new Error(
        `Failed to clear auto-save for ${type}/${id}: ${response.status()}`,
      );
    }
  }

  /**
   * Returns the <head> element from the preview iframe.
   */
  async getIframeHead(
    iframeSelector = '[data-test-canvas-content-initialized="true"][data-canvas-swap-active="true"]',
  ) {
    const iframeHandle = await this.page.waitForSelector(iframeSelector, {
      timeout: 10000,
    });
    const headHandle = await iframeHandle.evaluateHandle(
      (iframe: HTMLIFrameElement) => {
        return iframe.contentDocument?.head;
      },
    );
    return headHandle;
  }
}
