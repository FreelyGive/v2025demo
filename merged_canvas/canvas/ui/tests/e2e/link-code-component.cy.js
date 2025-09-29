describe('♾️ Link component', () => {
  beforeEach(() => {
    cy.drupalCanvasInstall(['canvas_test_code_components']);
    cy.drupalSession();
    // A larger viewport makes it easier to debug in the test runner app.
    cy.viewport(2000, 1000);
  });

  afterEach(() => {
    cy.drupalUninstall();
  });

  it(
    'A link prop renders with the canonical alias if existing',
    { retries: { openMode: 0, runMode: 1 } },
    () => {
      cy.drupalLogin('canvasUser', 'canvasUser');
      cy.loadURLandWaitForCanvasLoaded({ url: 'canvas/editor/node/2' });
      cy.openLibraryPanel();
      cy.get('.primaryPanelContent').should('contain.text', 'Components');
      cy.insertComponent({ name: 'My Code Component Link' });

      const iframeSelector =
        '[data-test-canvas-content-initialized="true"][data-canvas-swap-active="true"]';
      cy.waitForElementInIframe('a[href*="/llamas"]', iframeSelector, 10000);
      cy.get('[data-testid*="canvas-component-form-"]').as('inputForm');
      cy.get('@inputForm').recordFormBuildId();
      // Log all ajax form requests to help with debugging.
      cy.intercept('PATCH', '**/canvas/api/v0/form/component-instance/**').as(
        'patch',
      );
      cy.get('@inputForm').findByLabelText('Link').clear();
      cy.get('@inputForm')
        .findByLabelText('Link')
        .type('/node/3?param=value#fragment');
      cy.get('@inputForm').findByLabelText('Link').blur();
      cy.waitForElementInIframe(
        'a[href*="/the-one-with-a-block?param=value#fragment"]',
        iframeSelector,
        10000,
      );
    },
  );
});
