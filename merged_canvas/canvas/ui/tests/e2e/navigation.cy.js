const navigationButtonTestId = 'canvas-navigation-button';
const navigationContentTestId = 'canvas-navigation-content';
const navigationResultsTestId = 'canvas-navigation-results';
const navigationNewButtonTestId = 'canvas-navigation-new-button';
const navigationNewPageButtonTestId = 'canvas-navigation-new-page-button';
// @see import { HomeIcon } from '@radix-ui/react-icons';
const radixHomeIconDValue =
  'M7.07926 0.222253C7.31275 -0.007434 7.6873 -0.007434 7.92079 0.222253L14.6708 6.86227C14.907 7.09465 14.9101 7.47453 14.6778 7.71076C14.4454 7.947 14.0655 7.95012 13.8293 7.71773L13 6.90201V12.5C13 12.7761 12.7762 13 12.5 13H2.50002C2.22388 13 2.00002 12.7761 2.00002 12.5V6.90201L1.17079 7.71773C0.934558 7.95012 0.554672 7.947 0.32229 7.71076C0.0899079 7.47453 0.0930283 7.09465 0.32926 6.86227L7.07926 0.222253ZM7.50002 1.49163L12 5.91831V12H10V8.49999C10 8.22385 9.77617 7.99999 9.50002 7.99999H6.50002C6.22388 7.99999 6.00002 8.22385 6.00002 8.49999V12H3.00002V5.91831L7.50002 1.49163ZM7.00002 12H9.00002V8.99999H7.00002V12Z';

describe('Navigation functionality', () => {
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

  it('Has page title in the top bar', () => {
    cy.loadURLandWaitForCanvasLoaded({ url: 'canvas/editor/canvas_page/1' });
    cy.findByTestId(navigationButtonTestId)
      .should('exist')
      .and('have.text', 'Homepage')
      .and('be.enabled');
    cy.loadURLandWaitForCanvasLoaded({ url: 'canvas/editor/canvas_page/2' });
    cy.findByTestId(navigationButtonTestId)
      .should('exist')
      .and('have.text', 'Empty Page')
      .and('be.enabled');
  });

  it('Clicking the page title in the top bar opens the navigation', () => {
    cy.loadURLandWaitForCanvasLoaded({ url: 'canvas/editor/canvas_page/1' });
    cy.findByTestId(navigationButtonTestId)
      .should('exist')
      .and('have.text', 'Homepage')
      .and('be.enabled');
    cy.findByTestId(navigationButtonTestId).click();
    cy.findByTestId(navigationContentTestId)
      .should('exist')
      .and('contain.text', 'Homepage')
      .and('contain.text', 'Empty Page');
  });

  it('Verify that search works', () => {
    cy.loadURLandWaitForCanvasLoaded({ url: 'canvas/editor/canvas_page/1' });
    cy.findByTestId(navigationButtonTestId).click();
    cy.findByLabelText('Search content').clear();
    cy.findByLabelText('Search content').type('ome');
    cy.findByTestId(navigationResultsTestId)
      .findAllByRole('listitem')
      .should(($children) => {
        const count = $children.length;
        expect(count).to.be.eq(1);
        expect($children.text()).to.contain('Homepage');
        expect($children.text()).to.not.contain('Empty Page');
      });
    cy.findByLabelText('Search content').clear();
    cy.findByLabelText('Search content').type('NonExistentPage');
    cy.findByTestId(navigationResultsTestId)
      .findAllByRole('listitem')
      .should(($children) => {
        const count = $children.length;
        expect(count).to.be.eq(0);
      });
    cy.findByTestId(navigationResultsTestId)
      .findByText('No pages found', { exact: false })
      .should('exist');
    cy.findByLabelText('Search content').clear();
    cy.findByTestId(navigationResultsTestId)
      .findAllByRole('listitem')
      .should(($children) => {
        const count = $children.length;
        expect(count).to.be.eq(3);
        expect($children.text()).to.contain('Homepage');
        expect($children.text()).to.contain('Empty Page');
      });
  });

  it('Clicking "New page" creates a new page and navigates to it', () => {
    cy.loadURLandWaitForCanvasLoaded({ url: 'canvas/editor/canvas_page/1' });

    cy.findByTestId(navigationButtonTestId).click();
    cy.findByTestId(navigationNewButtonTestId).click();
    cy.findByTestId(navigationNewPageButtonTestId).click();
    cy.url().should('not.contain', '/canvas/editor/canvas_page/1');
    cy.url().should('contain', '/canvas/editor/canvas_page/4');
    cy.findByTestId('canvas-topbar').findByText('Draft');
  });

  it('Updating the page title in the page data form updates title in the navigation list.', () => {
    cy.loadURLandWaitForCanvasLoaded({ url: 'canvas/editor/canvas_page/1' });

    cy.findByTestId(navigationButtonTestId).click();
    cy.findByTestId(navigationContentTestId)
      .should('exist')
      .and('contain.text', 'Homepage')
      .and('contain.text', 'Empty Page');

    // Open the page data form by clicking on the "Page data" tab in the sidebar.
    cy.findByTestId('canvas-contextual-panel--page-data').click();
    cy.findByTestId('canvas-page-data-form')
      .findByLabelText('Title')
      .should('have.value', 'Homepage');

    // Type a new value into the title field.
    cy.findByTestId('canvas-page-data-form')
      .findByLabelText('Title')
      .as('titleField');
    cy.get('@titleField').focus();
    cy.get('@titleField').clear();
    cy.get('@titleField').type('My Awesome Site');
    cy.get('@titleField').should('have.value', 'My Awesome Site');
    cy.get('@titleField').blur();

    cy.findByTestId(navigationButtonTestId)
      .should('exist')
      .and('have.text', 'My Awesome Site')
      .and('be.enabled');

    cy.findByTestId(navigationButtonTestId).click();
    cy.findByTestId(navigationContentTestId)
      .should('exist')
      .and('contain.text', 'My Awesome Site')
      .and('contain.text', 'Empty Page');
  });

  it('Clicking page title navigates to edit page', () => {
    cy.loadURLandWaitForCanvasLoaded({ url: 'canvas/editor/canvas_page/1' });

    cy.findByTestId(navigationButtonTestId).click();
    cy.contains('div', '/test-page').click();
    cy.url().should('contain', '/canvas/editor/canvas_page/2');
    cy.findByTestId(navigationButtonTestId).click();
    cy.contains('div', '/homepage').click();
    cy.url().should('contain', '/canvas/editor/canvas_page/1');
  });

  it(
    'Duplicate pages through navigation',
    { retries: { openMode: 0, runMode: 3 } },
    () => {
      cy.loadURLandWaitForCanvasLoaded({ url: 'canvas/editor/canvas_page/1' });

      cy.findByTestId(navigationButtonTestId).click();
      cy.findByText('Empty Page').realHover();
      cy.findByLabelText('Page options for Empty Page').click();
      cy.findByRole('menuitem', {
        name: 'Duplicate page',
        exact: false,
      }).click();

      cy.loadURLandWaitForCanvasLoaded({ url: 'canvas/editor/canvas_page/1' });
      cy.findByTestId(navigationButtonTestId).click();
      cy.findByTestId(navigationContentTestId)
        .should('exist')
        .and('contain.text', 'Empty Page (Copy)');
    },
  );

  it(
    'Deleting pages through navigation',
    { retries: { openMode: 0, runMode: 3 } },
    () => {
      // Intercept the DELETE request
      cy.intercept('DELETE', '**/canvas/api/v0/content/canvas_page/*').as(
        'deletePage',
      );

      // Intercept the GET request to the list endpoint
      cy.intercept('GET', '**/canvas/api/v0/content/canvas_page').as('getList');

      cy.loadURLandWaitForCanvasLoaded({ url: 'canvas/editor/canvas_page/1' });

      cy.findByTestId(navigationButtonTestId).click();
      cy.wait('@getList').its('response.statusCode').should('eq', 200);
      cy.findByText('Empty Page').realHover();
      cy.findByLabelText('Page options for Empty Page').click();
      cy.findByRole('menuitem', { name: 'Delete page', exact: false }).click();
      cy.contains('button', 'Delete page').click();

      // Wait for the DELETE request to be made and assert it
      cy.wait('@deletePage').its('response.statusCode').should('eq', 204);

      // Wait for the GET request to the list endpoint which should be triggered by the deletion of a page.
      cy.wait('@getList').its('response.statusCode').should('eq', 200);

      cy.loadURLandWaitForCanvasLoaded({ url: 'canvas/editor/canvas_page/1' });
      cy.findByTestId(navigationButtonTestId).click();
      cy.findByTestId(navigationContentTestId)
        .should('exist')
        .and('contain.text', 'Homepage')
        .and('not.contain.text', 'Test page');
    },
  );

  it(
    'Set the homepage within Canvas and check deleting the current page will navigate to the homepage',
    { retries: { openMode: 0, runMode: 3 } },
    () => {
      cy.loadURLandWaitForCanvasLoaded({ url: 'canvas/editor/canvas_page/4' });
      cy.intercept('DELETE', '**/canvas/api/v0/content/canvas_page/*').as(
        'deletePage',
      );
      cy.intercept('GET', '**/canvas/api/v0/content/canvas_page').as('getList');
      cy.intercept('POST', '**/canvas/api/v0/staged-update/auto-save').as(
        'setHomepage',
      );
      cy.log('loaded canvas/editor/canvas_page/4');
      cy.findByTestId(navigationButtonTestId).click();
      cy.findByTestId(navigationContentTestId)
        .should('exist')
        .and('contain.text', 'Homepage')
        .and('contain.text', 'Untitled page');
      cy.url().should('contain', '/canvas/editor/canvas_page/4');
      cy.get('[data-canvas-page-id="1"]').realHover();
      cy.findByLabelText('Page options for Homepage').click();
      // Confirm the delete option is available since this isn't the homepage yet.
      cy.findByRole('menuitem', {
        name: 'Delete page',
        exact: false,
      }).should('exist');
      cy.findByRole('menuitem', {
        name: 'Set as homepage',
        exact: false,
      }).click();
      // Wait for the POST request to be made and assert it
      cy.wait('@setHomepage').its('response.statusCode').should('eq', 201);
      // Wait for the GET request to the config endpoint which should be triggered by setting the homepage.
      cy.wait('@getList').its('response.statusCode').should('eq', 200);

      // Delete the untitled page.
      cy.get('[data-canvas-page-id="4"]').realHover();
      cy.findByLabelText('Page options for Untitled page').click();
      cy.findByRole('menuitem', { name: 'Delete page', exact: false }).click();
      cy.contains('button', 'Delete page').click();
      // Wait for the DELETE request to be made and assert it
      cy.wait('@deletePage').its('response.statusCode').should('eq', 204);
      cy.log('Deleted Untitled page');
      // Wait for the GET request to the list endpoint which should be triggered by the deletion of a page.
      cy.wait('@getList').its('response.statusCode').should('eq', 200);
      cy.url().should('not.contain', '/canvas/editor/canvas_page/4');

      // Confirm we are now on the homepage.
      cy.url().should('contain', '/canvas/editor/canvas_page/1');
      cy.log('loaded canvas/editor/canvas_page/1');
      cy.findByTestId(navigationButtonTestId)
        .should('exist')
        .and('have.text', 'Homepage')
        .and('be.enabled');

      // Check that the home icon is present in the navigation button.
      cy.findByTestId(navigationButtonTestId)
        .find('svg path')
        .should('have.attr', 'd', radixHomeIconDValue);
    },
  );

  it('Clicking the back button navigates to last visited page', () => {
    const BASE_URL = `${Cypress.config().baseUrl}/`;
    // TRICKY: use a query string rather than a path, because this test site has zero content on offer.
    const LAST_VISITED_URL = `${BASE_URL}user/2?whatever`;
    // Visit the base URL.
    cy.visit(BASE_URL);

    // Store the current URL
    cy.url().then((previousUrl) => {
      cy.loadURLandWaitForCanvasLoaded();
      cy.findByLabelText('Exit Drupal Canvas').click();
      // Check if the URL is the previous URL
      cy.url().should('eq', previousUrl);
    });

    // Ensure the "last visited URL" actually is what it is.
    cy.visit(LAST_VISITED_URL);

    // Store the current URL
    cy.url().then((previousUrl) => {
      cy.loadURLandWaitForCanvasLoaded();

      cy.findByLabelText('Exit Drupal Canvas').should(
        'have.attr',
        'href',
        LAST_VISITED_URL,
      );

      cy.findByLabelText('Exit Drupal Canvas').click();

      // Check if the URL is the previous URL
      cy.url().should('eq', previousUrl);
    });
  });

  it(
    'Publish the homepage staged update and confirm the homepage icon is present even after page refresh',
    { retries: { openMode: 0, runMode: 3 } },
    () => {
      cy.loadURLandWaitForCanvasLoaded({ url: 'canvas/editor/canvas_page/1' });
      cy.intercept('POST', '**/canvas/api/v0/auto-saves/publish').as(
        'publishChanges',
      );
      cy.findByTestId(navigationButtonTestId)
        .should('exist')
        .and('have.text', 'Homepage')
        .and('be.enabled');
      cy.findByTestId(navigationButtonTestId).click();
      cy.findByTestId(navigationContentTestId)
        .should('exist')
        .and('contain.text', 'Homepage')
        .and('not.contain.text', 'Untitled page');
      cy.get('[data-canvas-page-id="1"]').realHover();
      cy.findByLabelText('Page options for Homepage').click();
      // The page set as Homepage should not have the "Set as homepage" option anymore.
      cy.findByRole('menuitem', {
        name: 'Set as homepage',
        exact: false,
      }).should('not.exist');
      // We still expect to see the "Delete page" option since the homepage staged update is not published yet.
      cy.findByRole('menuitem', {
        name: 'Delete page',
        exact: false,
      }).should('exist');
      cy.get('html').click();

      // Publish the homepage staged update.
      cy.findByText('Review 1 change').click();
      cy.findByTestId('canvas-publish-reviews-content').within(() => {
        cy.findByText('Update homepage').click();
        cy.findByText(/Publish \d selected/).click();
      });
      cy.wait('@publishChanges').its('response.statusCode').should('eq', 200);
      cy.log('Published homepage staged update');

      // Refresh the page to ensure the update persists.
      cy.reload();

      cy.findByTestId(navigationButtonTestId)
        .should('exist')
        .and('contain.text', 'Homepage');

      // Check that the home icon is present in the navigation button.
      cy.findByTestId(navigationButtonTestId)
        .find('svg path')
        .should('have.attr', 'd', radixHomeIconDValue);
      cy.findByTestId(navigationButtonTestId).click();
      // Check that the home icon is present in the page menu item.
      cy.get('[data-canvas-page-id="1"]')
        .find('svg path')
        .should('have.attr', 'd', radixHomeIconDValue);
      cy.get('[data-canvas-page-id="1"]').realHover();
      cy.findByLabelText('Page options for Homepage').click();

      // The page set as homepage should not have the "Set as homepage" option anymore.
      cy.findByRole('menuitem', {
        name: 'Set as homepage',
        exact: false,
      }).should('not.exist');
      // Deleting the homepage with the staged update published is not allowed.
      cy.findByRole('menuitem', {
        name: 'Delete page',
        exact: false,
      }).should('not.exist');
    },
  );
});
