describe('📸️ Code image component', () => {
  beforeEach(() => {
    cy.drupalCanvasInstall(['canvas_test_e2e_code_components']);
    cy.drupalSession();
    // A larger viewport makes it easier to debug in the test runner app.
    cy.viewport(2000, 1000);
  });

  afterEach(() => {
    cy.drupalUninstall();
  });

  it(
    'Removing an optional image falls back to the default',
    { retries: { openMode: 0, runMode: 3 } },
    () => {
      cy.drupalLogin('canvasUser', 'canvasUser');
      cy.loadURLandWaitForCanvasLoaded({ url: 'canvas/editor/node/2' });
      cy.openLibraryPanel();
      cy.get('.primaryPanelContent').should('contain.text', 'Components');
      cy.get('.primaryPanelContent').findByText('CC Optional Image').click();
      // Check the default video src is set.
      const iframeSelector =
        '[data-test-canvas-content-initialized="true"][data-canvas-swap-active="true"]';
      cy.waitForElementInIframe(
        'img[src*="https://placehold.co/1200x900@2x.png"]',
        iframeSelector,
        10000,
      );
      cy.get('[data-testid*="canvas-component-form-"]').as('inputForm');
      cy.get('@inputForm').recordFormBuildId();
      // Log all ajax form requests to help with debugging.
      cy.intercept('PATCH', '**/canvas/api/v0/form/component-instance/**').as(
        'patch',
      );
      cy.get('@inputForm')
        .findByRole('button', { name: 'Add media', timeout: 10000 })
        .should('not.be.disabled')
        .click();
      // The first time the media dialog opens there are a lot of CSS files to
      // load, and it can take more than the default timeout of 4s.
      cy.findByRole('dialog', { timeout: 10000 }).as('dialog');
      cy.get('@inputForm').shouldHaveUpdatedFormBuildId();
      cy.get('@dialog')
        .findByLabelText('Select Sorry I resemble a dog')
        .check();
      cy.get('@dialog')
        .findByRole('button', {
          name: 'Insert selected',
        })
        .click();
      cy.findByRole('dialog').should('not.exist');
      // Wait for the preview to finish loading.
      cy.findByLabelText('Loading Preview').should('not.exist');
      cy.get('@inputForm')
        .findByAltText('My barber may have been looking at a picture of a dog')
        .should('exist');
      cy.get('@inputForm').shouldHaveUpdatedFormBuildId({ timeout: 11000 });
      cy.waitForElementInIframe(
        'img[alt="My barber may have been looking at a picture of a dog"]',
        iframeSelector,
        10000,
      );

      cy.get('@inputForm')
        .findByRole('button', { name: 'Remove Sorry I resemble a dog' })
        .click();
      cy.get('@inputForm').shouldHaveUpdatedFormBuildId({ timeout: 11000 });
      // Wait for the preview to finish loading.
      cy.findByLabelText('Loading Preview').should('not.exist');

      // Removing the media item should fall back to the default because the
      // image is optional
      cy.waitForElementInIframe(
        'img[src*="https://placehold.co/1200x900@2x.png"]',
        iframeSelector,
        10000,
      );
    },
  );
});
