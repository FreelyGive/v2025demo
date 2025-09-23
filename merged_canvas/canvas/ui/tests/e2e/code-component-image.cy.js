describe('Image code component', () => {
  before(() => {
    cy.drupalCanvasInstall(['canvas_test_code_components']);
  });

  beforeEach(() => {
    cy.drupalSession();
    cy.drupalLogin('canvasUser', 'canvasUser');
  });

  after(() => {
    cy.drupalUninstall();
  });

  it(
    'Can add an optional image component with a preview but empty input',
    { retries: { openMode: 0, runMode: 3 } },
    () => {
      cy.loadURLandWaitForCanvasLoaded({ url: 'canvas/editor/node/2' });
      cy.openLibraryPanel();
      cy.get('.primaryPanelContent').findByText('Vanilla Image').click();
      // Check the default image src is set.
      cy.waitForElementInIframe(
        'img[src="https://placehold.co/1200x900@2x.png"]',
        '[data-test-canvas-content-initialized="true"][data-canvas-swap-active="true"]',
        10000,
      );
      cy.get('[data-testid="canvas-publish-review"]:not([disabled])', {
        timeout: 20000,
      }).should('exist');
      cy.publishAllPendingChanges('I am an empty node');
      cy.visit('/node/2');
      cy.get('img[src="https://placehold.co/1200x900@2x.png"]').should(
        'exist',
        {
          timeout: 10000,
        },
      );
    },
  );
});
