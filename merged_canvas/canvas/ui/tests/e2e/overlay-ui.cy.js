describe('Drupal Canvas overlay UI interactions', () => {
  before(() => {
    cy.drupalCanvasInstall();
  });

  beforeEach(() => {
    cy.drupalLogin('canvasUser', 'canvasUser');
  });

  after(() => {
    cy.drupalUninstall();
  });

  it(
    'Component and slot label behavior should work correctly',
    { retries: { openMode: 0, runMode: 3 } },
    () => {
      cy.loadURLandWaitForCanvasLoaded();
      cy.get('.canvas--viewport-overlay').as('desktopPreviewOverlay');

      cy.clickComponentInLayersView('Two Column');

      cy.log(
        'Selecting a "parent" component should show its label, but not the label(s) of its children',
      );
      cy.findByTestId('scale-to-fit').click();
      cy.get('@desktopPreviewOverlay').within(() => {
        // For perf. reasons we only ever render one name tag at a time - because the name tag relies on checking
        // lots of global state e.g. hoveredComponent or isDragging - having a lot of rendered but invisible nameTags is bad.
        cy.findAllByTestId('canvas-name-tag').should('have.length', 1);
        cy.findByText('Two Column').should('be.visible');
      });

      cy.clickComponentInPreview('Hero');

      cy.log('After selecting a "child" component it should show its label.');
      cy.get('@desktopPreviewOverlay').within(() => {
        cy.findAllByTestId('canvas-name-tag').should('have.length', 1);
        cy.findByText('Hero').should('be.visible');
      });

      cy.log(
        'Now hover a different component. The selected name should not show, the hovered name only should show.',
      );
      cy.get('@desktopPreviewOverlay').within(() => {
        cy.findAllByLabelText('Test SDC Image')
          .eq(1)
          .realHover({ scrollBehavior: 'center' });
        cy.findAllByTestId('canvas-name-tag').should('have.length', 1);
        cy.findAllByTestId('canvas-name-tag').should(
          'have.text',
          'Test SDC Image',
        );
      });
    },
  );
});
