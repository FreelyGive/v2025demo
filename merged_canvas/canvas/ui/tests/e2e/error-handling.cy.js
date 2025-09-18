describe('Error handling', () => {
  before(() => {
    cy.drupalCanvasInstall();
  });

  beforeEach(() => {
    cy.drupalLogin('canvasUser', 'canvasUser');
  });

  after(() => {
    cy.drupalUninstall();
  });

  it('Handles and resets errors', () => {
    // Intercept the request to the preview endpoint and return a 418 status
    // code. This will cause the error boundary to display an error message.
    // Note the times: 1 option, which ensures the request is only intercepted
    // once.
    cy.intercept(
      { url: '**/canvas/api/v0/layout/node/1', times: 1, method: 'GET' },
      { statusCode: 418 },
    );
    cy.drupalRelativeURL('canvas/editor/node/1');

    cy.findByTestId('canvas-error-alert')
      .should('exist')
      .invoke('text')
      .should('include', 'An unexpected error has occurred');

    // Click the reset button to clear the error, and confirm the error message
    // is no longer present.
    cy.findByTestId('canvas-error-reset').click();
    cy.contains('An unexpected error has occurred').should('not.exist');
  });

  it('Has special handling for being logged out while in the UI', () => {
    // We are intentionally causing exceptions that would automatically fail the
    // test without this.
    Cypress.on('uncaught:exception', (err, runnable) => {
      return false;
    });

    cy.loadURLandWaitForCanvasLoaded();
    cy.findByLabelText('Title').should('exist');
    cy.intercept(
      '**/session/token**',
      'EEEEEEE_EEEEE_EEEEEEEEEEEEEEEEEEEEEEEEEEEEE',
    );

    // Effectively log the user out while in the UI.
    cy.getAllCookies().then((result) => {
      result.forEach((cookie) => {
        if (cookie.name.match(/^S?SESS/)) {
          cy.setCookie(cookie.name, 'thisWillFail');
        }
      });
    });

    // Additional editing will trigger a request to a resource that is now
    // unavailable due to being logged out.
    cy.findByLabelText('Title').type('something');
    cy.get('[data-testid="canvas-error-alert"] h1').should(
      'include.text',
      'An unexpected error has occurred',
    );
    cy.get('[data-testid="canvas-error-alert"] p').should(
      'include.text',
      'Error 401: You must be logged in to access this resource.',
    );
    cy.get(
      '[data-testid="canvas-error-alert"] [data-testid="canvas-error-reset"]',
    ).should('include.text', 'Go to login');
    cy.get(
      '[data-testid="canvas-error-alert"] [data-testid="canvas-error-reset"]',
    ).click();
    cy.url().should('contain', 'user/login');
  });
});
