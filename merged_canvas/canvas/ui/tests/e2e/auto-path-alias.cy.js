// cspell:ignore lauris
describe('Auto path alias generation', () => {
  before(() => {
    cy.drupalCanvasInstall();
  });

  beforeEach(() => {
    cy.drupalSession();
    cy.drupalLogin('canvasUser', 'canvasUser');
  });

  after(() => {
    cy.drupalUninstall();
  });

  it(`canvas/editor/canvas_page/2 doesn't auto update path alias based on title if path is already set`, () => {
    cy.loadURLandWaitForCanvasLoaded({ url: 'canvas/editor/canvas_page/2' });
    cy.get('iframe[data-canvas-preview]').should('exist');

    cy.get('#edit-title-0-value').should('exist');
    cy.get('#edit-path-0-alias').should('exist');
    cy.get('#edit-path-0-alias').should('have.value', '/test-page');

    cy.get('#edit-title-0-value').clear();
    cy.get('#edit-title-0-value').type('New page title');
    cy.get('#edit-title-0-value').blur();
    cy.get('#edit-path-0-alias').should('have.value', '/test-page');
  });

  it('on a new page path is generated automatically until manually overridden', () => {
    cy.loadURLandWaitForCanvasLoaded({
      url: 'canvas/editor/canvas_page/1',
      clearAutoSave: true,
    });
    cy.get('iframe[data-canvas-preview]').should('exist');

    cy.findByTestId('canvas-navigation-button').click();
    cy.findByTestId('canvas-navigation-new-button').click();
    cy.findByTestId('canvas-navigation-new-page-button').click();
    cy.url().should('not.contain', '/canvas/editor/canvas_page/1');
    cy.url().should('contain', '/canvas/editor/canvas_page/4');
    cy.findByTestId('canvas-topbar').findByText('Draft');

    // Make sure that the path alias is empty to begin with.
    cy.get('#edit-path-0-alias').should('exist');
    cy.get('#edit-path-0-alias').should('have.value', '');

    // Make sure that alias is empty even after the page is reloaded.
    cy.loadURLandWaitForCanvasLoaded({
      url: 'canvas/editor/canvas_page/4',
      clearAutoSave: false,
    });
    cy.get('iframe[data-canvas-preview]').should('exist');
    cy.get('#edit-path-0-alias').should('exist');
    cy.get('#edit-path-0-alias').should('have.value', '');

    // Set the title and make sure that the path alias is generated automatically.
    cy.get('#edit-title-0-value').clear();
    cy.intercept('POST', '**/canvas/api/v0/layout/**').as('updatePreview');
    cy.get('#edit-title-0-value').type("Lauri's new page");
    cy.wait('@updatePreview');
    cy.intercept('POST', '**/canvas/api/v0/layout/**').as('updatePreview');
    cy.get('#edit-title-0-value').blur();
    cy.get('#edit-path-0-alias').should('have.value', '/lauris-new-page');
    cy.wait('@updatePreview');

    // Refresh the page and make sure that the path alias is still set.
    cy.loadURLandWaitForCanvasLoaded({
      url: 'canvas/editor/canvas_page/4',
      clearAutoSave: false,
    });
    cy.get('iframe[data-canvas-preview]').should('exist');
    cy.get('#edit-path-0-alias').should('exist');
    cy.get('#edit-path-0-alias').should('have.value', '/lauris-new-page');

    // Update the title and make sure that the path alias is updated automatically.
    cy.get('#edit-title-0-value').clear();
    cy.get('#edit-title-0-value').type("Lauri's updated page");
    cy.get('#edit-title-0-value').blur();
    cy.get('#edit-path-0-alias').should('have.value', '/lauris-updated-page');

    // Set the path alias manually and make sure that the auto-generated path alias is not updated anymore.
    cy.get('#edit-path-0-alias').clear();
    cy.get('#edit-path-0-alias').type('/custom-url');
    cy.get('#edit-path-0-alias').blur();
    cy.get('#edit-title-0-value').clear();
    cy.intercept('POST', '**/canvas/api/v0/layout/**').as('updatePreview');
    cy.get('#edit-title-0-value').type("Lauri's page");
    cy.wait('@updatePreview');
    cy.get('#edit-title-0-value').blur();
    cy.get('#edit-path-0-alias').should('have.value', '/custom-url');

    // Refresh the page and make sure that the path alias is still set to the
    // custom value and doesn't get updated.
    cy.loadURLandWaitForCanvasLoaded({
      url: 'canvas/editor/canvas_page/4',
      clearAutoSave: false,
    });
    cy.get('iframe[data-canvas-preview]').should('exist');
    cy.get('#edit-title-0-value').clear();
    cy.get('#edit-title-0-value').type("Lauri's updated page");
    cy.get('#edit-title-0-value').blur();
    cy.get('#edit-path-0-alias').should('have.value', '/custom-url');
  });

  it(
    'after changes are published, new path is no longer generated automatically',
    { retries: { openMode: 0, runMode: 3 } },
    () => {
      cy.loadURLandWaitForCanvasLoaded({ url: 'canvas/editor/canvas_page/1' });
      cy.get('iframe[data-canvas-preview]').should('exist');

      cy.findByTestId('canvas-navigation-button').click();
      cy.findByTestId('canvas-navigation-new-button').click();
      cy.findByTestId('canvas-navigation-new-page-button').click();
      cy.url().should('not.contain', '/canvas/editor/canvas_page/1');
      cy.url().should('contain', '/canvas/editor/canvas_page/5');
      cy.findByTestId('canvas-topbar').findByText('Draft');

      // Set the title and make sure that the path alias is generated automatically.
      cy.get('#edit-title-0-value').clear();
      cy.intercept('POST', '**/canvas/api/v0/layout/**').as('updatePreview');
      cy.get('#edit-title-0-value').type("Lauri's another page");
      cy.wait('@updatePreview');
      cy.intercept('POST', '**/canvas/api/v0/layout/**').as('updatePreview');
      cy.get('#edit-title-0-value').blur();
      cy.intercept('POST', '**/canvas/api/v0/layout/**').as('updatePreview');
      cy.get('#edit-path-0-alias').should('have.value', '/lauris-another-page');
      cy.wait('@updatePreview');
      cy.get('[data-testid="canvas-publish-review"]:not([disabled])', {
        timeout: 20000,
      }).should('exist');

      cy.publishAllPendingChanges([
        "Lauri's another page",
        "Lauri's updated page",
      ]);

      cy.get('[aria-label="Close"]').parent().click();

      cy.get('#edit-title-0-value').clear();
      cy.get('#edit-title-0-value').type("Lauri's updated page");
      cy.get('#edit-title-0-value').blur();
      cy.get('#edit-path-0-alias').should('have.value', '/lauris-another-page');
    },
  );

  it(`canvas/editor/canvas_page/3 will generate path alias based on title for published content if path is empty`, () => {
    cy.loadURLandWaitForCanvasLoaded({ url: 'canvas/editor/canvas_page/3' });
    cy.get('iframe[data-canvas-preview]').should('exist');

    cy.get('#edit-title-0-value').should('exist');
    cy.get('#edit-path-0-alias').should('exist');
    cy.get('#edit-title-0-value').clear();
    cy.get('#edit-title-0-value').type('My new page title');
    cy.get('#edit-title-0-value').blur();
    cy.get('#edit-path-0-alias').should('have.value', '/my-new-page-title');
  });
});
