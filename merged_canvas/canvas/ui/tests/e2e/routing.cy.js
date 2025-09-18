describe('Routing', () => {
  before(() => {
    cy.drupalCanvasInstall();
  });

  beforeEach(() => {
    cy.drupalLogin('canvasUser', 'canvasUser');
  });

  after(() => {
    cy.drupalUninstall();
  });

  it('Visits a component router URL directly', () => {
    // Ideally the UUID would get its value dynamically, but that value can
    // only be accessed reliably in a command callback, and visiting a url
    // can't happen in that scope.
    // @see \Drupal\Tests\canvas\TestSite\CanvasTestSetup
    const uuid = '5944ef12-4a3d-4f3a-8e67-086661be9ffc';
    cy.intercept('GET', '**/canvas/api/v0/layout/node/1').as('getLayout');
    cy.intercept('PATCH', '**/canvas/api/v0/form/component-instance/node/1').as(
      'getPropsForm',
    );
    cy.drupalRelativeURL(`canvas/editor/node/1/component/${uuid}`);

    cy.wait('@getLayout');
    cy.wait('@getPropsForm');
    cy.findByTestId(`canvas-contextual-panel-${uuid}`).should('exist');
    cy.url().should('contain', `/canvas/editor/node/1/component/${uuid}`);
  });

  it('Visits a preview router URL directly', () => {
    cy.drupalRelativeURL(`canvas/preview/node/1/full`);

    cy.findByText('Exit Preview');

    cy.get('iframe[title="Page preview"]')
      .its('0.contentDocument.body')
      .should('not.be.empty')
      .then(cy.wrap)
      .within(() => {
        cy.get('.my-hero__heading').should('exist');
      });

    cy.url().should('contain', `/canvas/preview/node/1/full`);
  });

  it('has the expected performance', () => {
    cy.intercept('GET', '**/canvas/api/v0/layout/node/1').as('getLayout');
    cy.intercept('POST', '**/canvas/api/v0/layout/node/1').as('getPreview');

    cy.visit('/canvas/editor/node/1');
    cy.wait('@getLayout').its('response.statusCode').should('eq', 200);

    // Assert that only the get layout request was sent
    cy.get('@getLayout.all').should('have.length', 1);
    cy.get('@getPreview.all').should('have.length', 0);
  });
});
