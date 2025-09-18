describe('Page data form', () => {
  before(() => {
    cy.drupalCanvasInstall();
  });

  beforeEach(() => {
    cy.drupalLogin('canvasUser', 'canvasUser');
    cy.loadURLandWaitForCanvasLoaded();
  });

  after(() => {
    cy.drupalUninstall();
  });

  it('Loads and displays the article node form', () => {
    cy.get('#canvasPreviewOverlay .canvas--viewport-overlay')
      .first()
      .as('desktopPreviewOverlay');
    cy.get('.primaryPanelContent').as('layersTree');
    cy.get('@layersTree').findByText('Two Column').should('exist');
    // Open the right sidebar by clicking on a component.
    cy.clickComponentInPreview('Hero');
    // Open the page data form by clicking on the "Page data" tab in the sidebar.
    cy.findByTestId('canvas-contextual-panel--page-data').click();
    cy.findByTestId('canvas-page-data-form')
      .findByLabelText('Title')
      .should('have.value', 'Canvas Needs This For The Time Being');

    // Type a new value into the title field.
    cy.findByTestId('canvas-page-data-form')
      .findByLabelText('Title')
      .as('titleField');
    cy.get('@titleField').focus();
    cy.get('@titleField').clear();
    cy.get('@titleField').type('This is a new title');
    cy.intercept('POST', '**/canvas/api/v0/layout/**').as('updatePreview');
    cy.get('@titleField').should('have.value', 'This is a new title');
    cy.wait('@updatePreview');
    // Wait until the alias has been generated and preview updated.
    cy.intercept('POST', '**/canvas/api/v0/layout/**').as('updatePreview');
    cy.get('@titleField').blur();
    cy.wait('@updatePreview');
    cy.get('button[aria-label="Undo"]').should('be.enabled');
    cy.get('button[aria-label="Redo"]').should('be.disabled');
    cy.intercept('POST', '**/canvas/api/v0/layout/**').as('updatePreview');
    cy.realPress(['Meta', 'Z']);
    cy.wait('@updatePreview');

    // Undo twice to first revert the page alias and then to revert the page title.
    // @todo find a way to bundle the two undo actions into one.
    cy.realPress(['Meta', 'Z']);
    cy.get('@titleField').should(
      'have.value',
      'Canvas Needs This For The Time Being',
    );
    cy.get('button[aria-label="Undo"]').should('be.disabled');
    cy.get('@layersTree').findByText('Two Column').should('exist');
    cy.get('button[aria-label="Redo"]').should('be.enabled');

    cy.intercept('PATCH', '**/canvas/api/v0/layout/node/1').as('patchPreview');
    // Switch back to component instance form.
    cy.clickComponentInPreview('Hero');
    cy.findByTestId('canvas-contextual-panel--settings').click();
    cy.get(
      '[class*="contextualPanel"] [data-drupal-selector="component-instance-form"]',
    )
      .findByLabelText('Heading')
      .as('heroTitle');
    cy.get('@heroTitle').should('have.value', 'hello, world!');
    cy.get('@heroTitle').focus();
    cy.get('@heroTitle').clear();
    cy.get('@heroTitle').type('This is a new hero title');
    cy.wait('@patchPreview');
    // Editing a component field should push that onto the undo state.
    cy.get('button[aria-label="Undo"]').should('be.enabled');

    // Changing a field on the components prop form should invalidate the redo
    // state for the page data form.
    cy.get('button[aria-label="Redo"]').should('be.disabled');
    cy.get('@heroTitle').should('have.value', 'This is a new hero title');
    cy.get('@heroTitle').blur();
    cy.realPress(['Meta', 'Z']);
    cy.get('@heroTitle').should('have.value', 'hello, world!');
    cy.get('button[aria-label="Undo"]').should('be.disabled');
    cy.get('@layersTree').findByText('Two Column').should('exist');
    cy.get('button[aria-label="Redo"]').should('be.enabled');
    cy.realPress(['Meta', 'Shift', 'Z']);
    cy.get('@heroTitle').should('have.value', 'This is a new hero title');
    cy.get('button[aria-label="Undo"]').should('be.enabled');
    cy.get('button[aria-label="Redo"]').should('be.disabled');
    cy.realPress(['Meta', 'Z']);
    cy.get('@heroTitle').should('have.value', 'hello, world!');
    cy.get('button[aria-label="Redo"]').should('be.enabled');

    // Changing a field on the data form, should invalidate the redo state for
    // the layoutModel.
    cy.findByTestId('canvas-contextual-panel--page-data').click();
    cy.findByTestId('canvas-page-data-form')
      .findByLabelText('Title')
      .should('have.value', 'Canvas Needs This For The Time Being');

    cy.get('@titleField').focus();
    cy.get('@titleField').clear();
    cy.get('@titleField').should('have.value', '');
    cy.get('@titleField').type('This is a new title');
    cy.get('@titleField').should('have.value', 'This is a new title');
    cy.get('button[aria-label="Undo"]').should('be.enabled');
    cy.get('button[aria-label="Redo"]').should('be.disabled');
  });
});
