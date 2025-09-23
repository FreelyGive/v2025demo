describe('Expand Slots on Component Selection', () => {
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

  it('should expand slots when a component is selected', () => {
    cy.loadURLandWaitForCanvasLoaded();

    cy.get('.primaryPanelContent').as('layersTree');

    cy.get('@layersTree').within(() => {
      cy.findByText('Test Code Component').should('be.visible');
      cy.findByText('One Column').should('be.visible');
      cy.findByText('Column One').should('be.visible');
      cy.findByText('Column Two').should('be.visible');

      cy.findAllByLabelText('Collapse slot').each(($el) => {
        cy.wrap($el).click();
      });
      cy.findAllByLabelText('Collapse component tree').each(($el) => {
        cy.wrap($el).click();
      });

      cy.findByText('Test Code Component').should('not.exist');
      cy.findByText('One Column').should('not.exist');
      cy.findByText('Column One').should('not.exist');
      cy.findByText('Column Two').should('not.exist');
    });

    cy.findAllByLabelText('Exit Drupal Canvas').click();
    cy.go('back');
    cy.previewReady();

    cy.log(
      'After leaving the page and coming back, the collapsed layers should still be collapsed!',
    );

    cy.get('@layersTree').within(() => {
      cy.findByText('Test Code Component').should('not.exist');
      cy.findByText('One Column').should('not.exist');
      cy.findByText('Column One').should('not.exist');
      cy.findByText('Column Two').should('not.exist');
    });

    cy.clickComponentInPreview('Test Code Component');

    cy.get('@layersTree').within(() => {
      cy.findByText('Test Code Component').should('be.visible');
      cy.findByText('One Column').should('be.visible');
      cy.findByText('Column One').should('be.visible');
      cy.findByLabelText('Column Two').within(() => {
        cy.log(
          'Column two should not have been expanded (thus should say "Expand slot")',
        );
        cy.findByLabelText('Expand slot').should('exist');
      });
    });
  });
});
