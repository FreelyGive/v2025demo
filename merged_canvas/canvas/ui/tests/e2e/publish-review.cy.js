// @todo Expand this test to include coverage for "Page Data" fields such as 'title' and 'URL alias' in https://drupal.org/i/3495752.
// @todo Expand this test to include coverage for adding a component with no properties in https://drupal.org/i/3498227.

describe('Publish review functionality', () => {
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

  it(
    'Can make a change and see changes in the "Review x changes" button',
    { retries: { openMode: 0, runMode: 3 } },
    () => {
      cy.loadURLandWaitForCanvasLoaded();

      cy.findByTestId('canvas-topbar').findByText('Published');

      cy.clickComponentInPreview('Hero');

      cy.findByTestId(/^canvas-component-form-.*/)
        .findByLabelText('Heading')
        .type(' updated');

      cy.findByText('Changed');

      cy.findByText('Review 1 change').click();

      // Delete the image that uses an adapted source. This node (1) includes prop
      // sources that make use of adapters, we need to delete the adapted source
      // image in order to publish.
      cy.clickComponentInPreview('Test SDC Image', 1);
      cy.realType('{del}');

      cy.visit('/node/1');

      cy.findByText('hello, world! updated').should('not.exist');

      cy.loadURLandWaitForCanvasLoaded({ clearAutoSave: false });

      cy.publishAllPendingChanges('Canvas Needs This For The Time Being');

      cy.log('After publishing, there should be no changes.');
      cy.findByTestId('canvas-topbar')
        .findByText('No changes', { selector: 'button' })
        .should('exist');
      cy.findByTestId('canvas-topbar').findByText('Published');

      cy.log(
        'After publishing and reloading the page, there should be no changes.',
      );
      cy.loadURLandWaitForCanvasLoaded({ clearAutoSave: false });
      cy.findByTestId('canvas-topbar')
        .findByText('No changes', { selector: 'button' })
        .should('exist');

      cy.log(
        'Make another change and ensure the button still updates say "Review n changes"',
      );
      cy.clickComponentInPreview('Hero');
      cy.findByTestId(/^canvas-component-form-.*/)
        .findByLabelText('Heading')
        .type(' updated again');

      cy.findByTestId('canvas-topbar').findByText('Changed');
      cy.findByText('Review 1 change').click();

      cy.log('...and make sure the change shows up in the drop-down');
      cy.findByTestId('canvas-publish-reviews-content').within(() => {
        cy.findByText('Canvas Needs This For The Time Being');
      });

      cy.log('After publishing, the change should be visible page!');
      cy.visit('/node/1');
      cy.findByText('hello, world! updated').should('exist');
    },
  );

  it(
    'User without "publish auto-saves" permission cannot publish changes',
    { retries: { openMode: 0, runMode: 3 } },
    () => {
      // Create user with all canvasUser permissions except publish auto-saves
      cy.visit('admin/people/permissions/canvas');
      cy.get(
        'input[type="checkbox"][data-drupal-selector="edit-canvas-publish-auto-saves"]',
      ).uncheck();
      cy.get('input[data-drupal-selector="edit-submit"]').click();

      cy.loadURLandWaitForCanvasLoaded();

      cy.clickComponentInPreview('Hero');
      cy.findByTestId(/^canvas-component-form-.*/)
        .findByLabelText('Heading')
        .type(' updated by user without publish permission');

      cy.findByText('Changed');
      cy.findByText('Review 1 change').click();

      cy.findByTestId('canvas-publish-reviews-content').within(() => {
        cy.findByTestId('canvas-publish-review-select-all').click();
        cy.findByText(/Publish \d selected/).should('not.exist');
      });
    },
  );
});
