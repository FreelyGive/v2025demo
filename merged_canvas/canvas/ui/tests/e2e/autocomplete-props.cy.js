describe('Prop with autocomplete', () => {
  before(() => {
    cy.drupalCanvasInstall(['canvas_test_autocomplete']);
  });

  beforeEach(() => {
    cy.drupalSession();
    cy.drupalLogin('canvasUser', 'canvasUser');
  });

  after(() => {
    cy.drupalUninstall();
  });

  it('has a working autocomplete in the props form', () => {
    cy.loadURLandWaitForCanvasLoaded();
    cy.get('iframe[data-canvas-preview]').should('exist');
    cy.get(
      `#canvasPreviewOverlay .canvas--viewport-overlay .canvas--region-overlay__content`,
    )
      .findAllByLabelText('Hero')
      .eq(0)
      .click({ force: true });
    cy.get('[data-drupal-selector="edit-test-autocomplete"]').should('exist');
    cy.get('[data-drupal-selector="edit-test-autocomplete"]').type('Ban', {
      force: true,
    });
    cy.get('ul.ui-autocomplete').should('exist');
    cy.get('ul.ui-autocomplete li').should('have.text', 'Banana');
    cy.get('ul.ui-autocomplete li').click();
    cy.get('[data-drupal-selector="edit-test-autocomplete"]').should(
      'have.value',
      'banana',
    );
  });

  it('Works with link fields', () => {
    cy.loadURLandWaitForCanvasLoaded();
    cy.openLibraryPanel();
    cy.get('.primaryPanelContent').should('contain.text', 'Components');
    cy.get('.primaryPanelContent')
      .findByText('Hero', { timeout: 6000 })
      .click();
    cy.waitForElementContentInIframe('div', 'There goes my hero');
    cy.testInIframe(
      '[data-component-id="canvas_test_sdc:my-hero"]',
      (myHeroComponent) => {
        expect(myHeroComponent.length).to.equal(4);
      },
    );
    cy.findByLabelText('CTA 1 link')
      .as('linkField')
      .should('have.value', 'https://example.com')
      .click();
    // @see CanvasTestSetup, there is a node with title
    // 'Canvas With a block in the layout'
    cy.get('@linkField').clear();
    cy.get('@linkField').type('Canvas With a block');
    cy.get('ul.ui-autocomplete').should('exist');
    cy.get('ul.ui-autocomplete li').should(
      'have.text',
      'Canvas With a block in the layout',
    );
    cy.intercept('PATCH', '**/canvas/api/layout/node/1').as('patchPreview');
    cy.get('ul.ui-autocomplete li').click();
    cy.get('@linkField').should('have.value', 'entity:node/3');
    cy.get('@linkField').blur();
    // Wait for the preview to update.
    cy.waitFor('@patchPreview');

    cy.waitForElementContentInIframe(
      '[data-component-id="canvas_test_sdc:my-hero"] a[href*="/the-one-with-a-block"]',
      'View',
    );
  });
});
